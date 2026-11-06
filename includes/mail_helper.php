<?php
// includes/mail_helper.php

require_once 'config.php';

/**
 * Sends an email using the Brevo (formerly Sendinblue) API.
 *
 * @param string $to_email The recipient's email address.
 * @param string $to_name The recipient's name.
 * @param string $subject The email subject.
 * @param string $html_content The HTML content of the email.
 * @return array An array containing 'success' (boolean) and 'message' (string).
 */
function send_brevo_email($to_email, $to_name, $subject, $html_content) {
    $api_key = BREVO_API_KEY;
    $sender_email = BREVO_SENDER_EMAIL;
    $sender_name = BREVO_SENDER_NAME;

    if ($api_key === 'YOUR_BREVO_API_KEY_HERE') {
        return ['success' => false, 'message' => 'Brevo API key is not configured.'];
    }

    $url = 'https://api.brevo.com/v3/smtp/email';

    $data = [
        'sender' => ['name' => $sender_name, 'email' => $sender_email],
        'to' => [['email' => $to_email, 'name' => $to_name]],
        'subject' => $subject,
        'htmlContent' => $html_content
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'api-key: ' . $api_key,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        return ['success' => false, 'message' => 'CURL Error: ' . $curl_error];
    }

    if ($http_code >= 200 && $http_code < 300) {
        return ['success' => true, 'message' => 'Email sent successfully.'];
    } else {
        $response_data = json_decode($response, true);
        $error_message = $response_data['message'] ?? 'Unknown API error';
        return ['success' => false, 'message' => 'Brevo API Error (' . $http_code . '): ' . $error_message];
    }
}
