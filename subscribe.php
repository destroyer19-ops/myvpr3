<?php
ini_set('session.use_only_cookies', 1);
if (session_status() == PHP_SESSION_NONE) {
    require_once 'includes/session.php';
    session_init();
}
if (!isset($_SESSION['user_id'])) {
  $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
  header('Location: login.php');
  exit;
}

require_once 'includes/db.php';
include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container page-container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <h2 class="text-center mt-4">Subscription Plans</h2>
            <?php if (isset($_GET['error']) && $_GET['error'] === 'not_subscribed'): ?>
                <div class="alert alert-danger text-center">
                    You need an active subscription to create a crusade. Please subscribe to continue.
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger text-center">
                    <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            <p class="text-center">Choose a plan to continue.</p>

            <div class="row">
                <div class="col-md-6">
                    <div class="card text-center mb-4">
                        <div class="card-body">
                            <h5 class="card-title">24-Hour Access</h5>
                            <p class="card-text">2 Espees</p>
                            <button class="btn btn-primary-custom" onclick="selectPlan('daily', 2)">Subscribe</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card text-center mb-4">
                        <div class="card-body">
                            <h5 class="card-title">1-Month Subscription</h5>
                            <p class="card-text">10 Espees</p>
                            <button class="btn btn-primary-custom" onclick="selectPlan('monthly', 10)">Subscribe</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-body">
                    <h5 class="card-title">Account Details</h5>
                    <p class="card-text"><strong>Acct no:</strong> 1000316347</p>
                    <p class="card-text"><strong>Acct Name:</strong> LMAM - Music App</p>
                    <p class="card-text"><strong>Bank:</strong> Parallex Bank</p>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-body">
                    <h5 class="card-title">Espees Merchant Code</h5>
                    <p class="card-text">LMM01</p>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                    <div>
                        <h5 class="card-title mb-1">Gift a Subscription</h5>
                        <p class="card-text text-muted mb-0">Bless someone else with access.</p>
                    </div>
                    <a href="gift_subscription.php" class="btn btn-outline-primary mt-3 mt-md-0">Gift Someone</a>
                </div>
            </div>

            <div id="payment-form-container" class="card cta-block" style="display: none;">
                <div class="card-header text-center">
                    <h3>Complete Your Subscription</h3>
                </div>
                <div class="card-body">
                    <!-- Espees Instant Payment (New) -->
                    <div id="espees-button-container" class="mb-4 text-center">
                        <h5>Fastest Method: Pay with Espees</h5>
                        <p class="small text-muted">Activation is instant!</p>
                        <div id="espees-button" style="max-width: 300px; margin: 0 auto;"></div>
                    </div>

                    <div class="hr-theme-slash-2 mb-4">
                        <div class="hr-line"></div>
                        <div class="hr-icon"><small class="text-muted">OR PAY MANUALLY</small></div>
                        <div class="hr-line"></div>
                    </div>

                    <form action="process_subscription.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <input type="hidden" name="subscription_type" id="subscription_type">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control form-control-dark" id="full_name">
                        </div>
                        <div class="mb-3">
                            <label for="kc_handle" class="form-label">KC Handle (for Espees)</label>
                            <input type="text" name="kc_handle" class="form-control form-control-dark" id="kc_handle">
                        </div>
                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount</label>
                            <input type="text" name="amount" class="form-control form-control-dark" id="amount" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="proof" class="form-label">Proof of Transaction</label>
                            <input type="file" name="proof" class="form-control" id="proof" required>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary-custom">Submit Payment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/espees-sdk.js?v=<?php echo time(); ?>"></script>
<script>
function selectPlan(plan, amount) {
    document.getElementById('subscription_type').value = plan;
    document.getElementById('amount').value = amount;
    document.getElementById('payment-form-container').style.display = 'block';

    // Initialize Espees Instant Payment
    if (window.Espees) {
        Espees.init({
            amount: amount,
            sku: "sub_" + plan,
            narration: "Virtual Praise Room - " + plan + " Subscription",
            merchant_wallet: "<?php echo ESPEES_MERCHANT_WALLET; ?>",
            success_url: window.location.origin + "/api/espees_callback.php?status=success&plan=" + plan,
            fail_url: window.location.origin + "/subscribe.php?error=payment_failed",
            token: "<?php echo ESPEES_API_KEY; ?>", // Proxy handles the real security
            callback_url: window.location.origin + "/api/espees_callback.php",
            user_data: {
                user_id: "<?php echo $_SESSION['user_id']; ?>",
                plan: plan
            }
        });
        
        // Store plan in session via a small hidden request to help the callback
        fetch('api/store_pending_plan.php?plan=' + plan);
    }
}
</script>

<?php
include 'includes/bottom_navbar.php';
include 'includes/footer.php';
?>
