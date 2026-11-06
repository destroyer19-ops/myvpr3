<?php
/**
 * SquadService.php
 * Decoupled, reusable payment gateway service for Squad (HabariPay / GTCO).
 * Official Documentation: https://docs.squadco.com/
 */

class SquadService
{
    private string $secretKey;
    private string $webhookSecret;
    private string $publicKey;
    private string $environment;
    private string $baseUrl;
    private bool $isDemoKey;

    public function __construct(?string $secretKey = null, ?string $publicKey = null, ?string $environment = null, ?string $webhookSecret = null)
    {
        $this->secretKey = $secretKey ?? (defined('SQUAD_SECRET_KEY') ? SQUAD_SECRET_KEY : (getenv('SQUAD_SECRET_KEY') ?: ''));
        $this->webhookSecret = $webhookSecret ?? (defined('SQUAD_WEBHOOK_SECRET') && SQUAD_WEBHOOK_SECRET !== '' ? SQUAD_WEBHOOK_SECRET : $this->secretKey);
        $this->publicKey = $publicKey ?? (defined('SQUAD_PUBLIC_KEY') ? SQUAD_PUBLIC_KEY : (getenv('SQUAD_PUBLIC_KEY') ?: ''));
        $this->environment = strtolower($environment ?? (defined('SQUAD_ENV') ? SQUAD_ENV : (getenv('SQUAD_ENV') ?: 'sandbox')));
        
        $this->baseUrl = ($this->environment === 'production')
            ? 'https://api-d.squadco.com'
            : 'https://sandbox-api-d.squadco.com';

        // Check if using demo sandbox key for local automated testing
        $this->isDemoKey = (strpos($this->secretKey, 'sandbox_sk_demo') === 0 || empty($this->secretKey));
    }

    /**
     * Generate a unique, cryptographically random transaction reference.
     * Format: vpr_txn_{timestamp}_{randomHex}
     */
    public static function generateReference(string $prefix = 'vpr_txn_'): string
    {
        return $prefix . time() . '_' . bin2hex(random_bytes(6));
    }

    /**
     * Convert standard currency amount (e.g. 3000.00 NGN) to Kobo integer (300000).
     */
    public static function toKobo(float $amount): int
    {
        return (int)round($amount * 100);
    }

    /**
     * Convert Kobo integer (300000) to standard currency amount (3000.00).
     */
    public static function fromKobo(int $kobo): float
    {
        return round($kobo / 100, 2);
    }

    /**
     * Initiate Payment Transaction via Squad API.
     * POST /transaction/initiate
     *
     * @param array $params Required: email, amount (decimal), currency, transaction_ref, callback_url
     * @return array [ 'success' => bool, 'checkout_url' => string, 'transaction_ref' => string, 'message' => string, 'raw' => array ]
     */
    public function initiatePayment(array $params): array
    {
        $amount = (float)($params['amount'] ?? 0);
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Invalid transaction amount.'];
        }

        $email = filter_var($params['email'] ?? '', FILTER_VALIDATE_EMAIL);
        if (!$email) {
            return ['success' => false, 'message' => 'A valid customer email address is required.'];
        }

        $currency = strtoupper($params['currency'] ?? 'NGN');
        $transactionRef = $params['transaction_ref'] ?? self::generateReference();
        $callbackUrl = $params['callback_url'] ?? '';
        $customerName = $params['customer_name'] ?? '';
        $isRecurring = !empty($params['is_recurring']);
        $metadata = $params['metadata'] ?? [];

        // Lowest currency value (Kobo for NGN / cents for USD)
        $amountInLowestUnit = self::toKobo($amount);

        $payload = [
            'amount'           => $amountInLowestUnit,
            'email'            => $email,
            'currency'         => $currency,
            'initiate_type'    => 'inline',
            'transaction_ref'  => $transactionRef,
            'customer_name'    => $customerName,
            'callback_url'     => $callbackUrl,
            'payment_channels' => ['card', 'bank', 'ussd', 'transfer'],
            'pass_charge'      => false,
            'metadata'         => $metadata,
            'is_recurring'     => $isRecurring,
        ];

        // If in sandbox mode with demo credentials, simulate checkout URL
        if ($this->environment === 'sandbox' && $this->isDemoKey) {
            $simulatedCheckoutUrl = (defined('REACT_APP_URL') ? REACT_APP_URL : 'http://localhost:5173')
                . '/subscribe?squad_simulated=1&reference=' . urlencode($transactionRef);

            return [
                'success'         => true,
                'checkout_url'    => $simulatedCheckoutUrl,
                'transaction_ref' => $transactionRef,
                'message'         => 'Payment initiated in Sandbox simulation mode.',
                'raw'             => [
                    'status' => 200,
                    'success' => true,
                    'data' => [
                        'checkout_url' => $simulatedCheckoutUrl,
                        'transaction_ref' => $transactionRef,
                        'currency' => $currency,
                        'amount' => $amountInLowestUnit
                    ]
                ]
            ];
        }

        // Live/Real Sandbox Squad API call
        $endpoint = $this->baseUrl . '/transaction/initiate';
        $res = $this->sendHttpRequest('POST', $endpoint, $payload);

        if (!$res['success']) {
            return [
                'success' => false,
                'message' => $res['message'] ?? 'Failed to communicate with Squad payment gateway.',
                'raw'     => $res['data'] ?? []
            ];
        }

        $body = $res['data'];
        $checkoutUrl = $body['data']['checkout_url'] ?? null;

        if (empty($checkoutUrl)) {
            return [
                'success' => false,
                'message' => $body['message'] ?? 'Squad did not return a checkout URL.',
                'raw'     => $body
            ];
        }

        return [
            'success'         => true,
            'checkout_url'    => $checkoutUrl,
            'transaction_ref' => $transactionRef,
            'message'         => 'Payment initiated successfully.',
            'raw'             => $body
        ];
    }

    /**
     * Verify a Transaction on Squad API.
     * GET /transaction/verify/{{transaction_ref}}
     *
     * @param string $transactionRef
     * @return array [ 'success' => bool, 'status' => string, 'amount' => float, 'currency' => string, 'raw' => array, 'message' => string ]
     */
    public function verifyTransaction(string $transactionRef): array
    {
        $transactionRef = trim($transactionRef);
        if ($transactionRef === '') {
            return ['success' => false, 'status' => 'Failed', 'message' => 'Empty transaction reference.'];
        }

        // Sandbox simulated verification
        if ($this->environment === 'sandbox' && $this->isDemoKey) {
            return [
                'success'        => true,
                'status'         => 'Success', // 'Success', 'Failed', 'Pending'
                'transaction_ref'=> $transactionRef,
                'amount'         => null, // Will match expected authoritative amount in caller
                'currency'       => 'NGN',
                'channel'        => 'card',
                'card_token'     => 'demo_token_' . bin2hex(random_bytes(8)),
                'message'        => 'Transaction verified via Sandbox simulation.',
                'raw'            => [
                    'status' => 200,
                    'success' => true,
                    'data' => [
                        'transaction_status' => 'Success',
                        'transaction_ref'    => $transactionRef,
                        'currency'           => 'NGN',
                        'channel'            => 'card',
                    ]
                ]
            ];
        }

        $endpoint = $this->baseUrl . '/transaction/verify/' . rawurlencode($transactionRef);
        $res = $this->sendHttpRequest('GET', $endpoint);

        if (!$res['success']) {
            return [
                'success' => false,
                'status'  => 'Error',
                'message' => $res['message'] ?? 'Failed to verify transaction with Squad.',
                'raw'     => $res['data'] ?? []
            ];
        }

        $body = $res['data'];
        $data = $body['data'] ?? [];
        $txnStatus = $data['transaction_status'] ?? 'Unknown';
        $koboAmount = (int)($data['transaction_amount'] ?? 0);
        $currency = strtoupper($data['currency'] ?? 'NGN');
        $channel = $data['payment_information']['payment_type'] ?? $data['channel'] ?? 'card';
        $cardToken = $data['card_token'] ?? null;

        $isSuccessful = (strcasecmp($txnStatus, 'success') === 0);

        return [
            'success'         => $isSuccessful,
            'status'          => $isSuccessful ? 'Success' : $txnStatus,
            'transaction_ref' => $data['transaction_ref'] ?? $transactionRef,
            'amount'          => self::fromKobo($koboAmount),
            'currency'        => $currency,
            'channel'         => $channel,
            'card_token'      => $cardToken,
            'message'         => $body['message'] ?? 'Verification completed.',
            'raw'             => $body
        ];
    }

    /**
     * Validate incoming webhook authenticity using HMAC-SHA512.
     * Squad passes signature in 'x-squad-encrypted-body' or 'x-squad-signature'.
     *
     * @param string $rawPayload Raw HTTP request body
     * @param string $headerSignature Value from x-squad-encrypted-body or x-squad-signature
     * @return bool True if valid, false otherwise
     */
    public function validateWebhookSignature(string $rawPayload, string $headerSignature): bool
    {
        if (trim($headerSignature) === '') {
            return false;
        }

        // In sandbox demo mode, allow matching demo signatures
        if ($this->environment === 'sandbox' && $this->isDemoKey && $headerSignature === 'sandbox_demo_valid_signature') {
            return true;
        }

        $signingKey = !empty($this->webhookSecret) ? $this->webhookSecret : $this->secretKey;

        if (empty($signingKey)) {
            return false;
        }

        $computed = hash_hmac('sha512', $rawPayload, $signingKey);
        return hash_equals(strtolower($computed), strtolower(trim($headerSignature)));
    }

    /**
     * Charge a saved card token for recurring billing renewal.
     * POST /transaction/charge_card
     *
     * @param array $params Required: amount (decimal), card_token, transaction_ref, email
     * @return array [ 'success' => bool, 'status' => string, 'message' => string, 'raw' => array ]
     */
    public function chargeCard(array $params): array
    {
        $amount = (float)($params['amount'] ?? 0);
        $cardToken = trim($params['card_token'] ?? $params['token_id'] ?? '');
        $email = filter_var($params['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $txnRef = trim($params['transaction_ref'] ?? self::generateReference('vpr_rec_'));
        $currency = strtoupper($params['currency'] ?? 'NGN');

        if ($amount <= 0 || empty($cardToken) || !$email) {
            return ['success' => false, 'status' => 'Failed', 'message' => 'Invalid parameters for recurring charge.'];
        }

        $payload = [
            'amount'          => self::toKobo($amount),
            'token_id'        => $cardToken,
            'card_token'      => $cardToken,
            'email'           => $email,
            'transaction_ref' => $txnRef,
            'currency'        => $currency,
        ];

        if ($this->environment === 'sandbox' && $this->isDemoKey) {
            return [
                'success'         => true,
                'status'          => 'Success',
                'transaction_ref' => $txnRef,
                'amount'          => $amount,
                'currency'        => $currency,
                'message'         => 'Recurring card charge simulated in sandbox.',
                'raw'             => ['status' => 200, 'success' => true, 'data' => $payload]
            ];
        }

        $endpoint = $this->baseUrl . '/transaction/charge_card';
        $res = $this->sendHttpRequest('POST', $endpoint, $payload);

        if (!$res['success']) {
            return [
                'success' => false,
                'status'  => 'Failed',
                'message' => $res['message'] ?? 'Failed to execute recurring charge.',
                'raw'     => $res['data'] ?? []
            ];
        }

        $body = $res['data'];
        $data = $body['data'] ?? [];
        $txnStatus = $data['transaction_status'] ?? 'Unknown';
        $isSuccessful = (strcasecmp($txnStatus, 'success') === 0);

        return [
            'success'         => $isSuccessful,
            'status'          => $isSuccessful ? 'Success' : $txnStatus,
            'transaction_ref' => $data['transaction_ref'] ?? $txnRef,
            'amount'          => $amount,
            'currency'        => $currency,
            'message'         => $body['message'] ?? ($isSuccessful ? 'Recurring charge successful.' : 'Recurring charge failed.'),
            'raw'             => $body
        ];
    }

    /**
     * Revoke recurring billing authorization / cancel recurring token.
     * PATCH /transaction/cancel/recurring
     *
     * @param string $cardToken
     * @return array [ 'success' => bool, 'message' => string ]
     */
    public function cancelRecurringSubscription(string $cardToken): array
    {
        $cardToken = trim($cardToken);
        if ($cardToken === '') {
            return ['success' => false, 'message' => 'Missing card token.'];
        }

        if ($this->environment === 'sandbox' && $this->isDemoKey) {
            return ['success' => true, 'message' => 'Recurring authorization cancelled (simulated).'];
        }

        $endpoint = $this->baseUrl . '/transaction/cancel/recurring';
        $res = $this->sendHttpRequest('PATCH', $endpoint, ['auth_code' => [$cardToken]]);

        return [
            'success' => (bool)($res['success'] ?? false),
            'message' => $res['message'] ?? 'Cancellation processed.',
            'raw'     => $res['data'] ?? []
        ];
    }

    /**
     * Send HTTP request using cURL.
     */
    private function sendHttpRequest(string $method, string $url, ?array $payload = null): array
    {
        $ch = curl_init();
        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: MyVPR-Platform/1.0'
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        } elseif ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'success' => false,
                'message' => 'Squad connection error: ' . $curlError,
                'http_code' => 0
            ];
        }

        $decoded = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $decoded,
                'http_code' => $httpCode
            ];
        }

        return [
            'success' => false,
            'message' => $decoded['message'] ?? 'Squad API responded with HTTP status ' . $httpCode,
            'data' => $decoded,
            'http_code' => $httpCode
        ];
    }
}
