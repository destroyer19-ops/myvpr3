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

$search_query = trim($_GET['q'] ?? '');
$search_results = [];

if ($search_query !== '') {
    $like = '%' . $search_query . '%';
    $stmt = $conn->prepare("SELECT id, username, email, satellite_campus FROM praise_users WHERE username LIKE ? OR email LIKE ? OR kc_handle LIKE ? ORDER BY username ASC LIMIT 20");
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $search_results[] = $row;
    }
    $stmt->close();
}

include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="container page-container">
    <div class="row justify-content-center">
        <div class="col-md-9">
            <h2 class="text-center mt-4">Gift a Subscription</h2>
            <p class="text-center text-muted">Search for the person you want to bless, then submit payment proof for their subscription.</p>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" action="gift_subscription.php" class="row g-2 align-items-center">
                        <div class="col-md-9">
                            <input type="text" name="q" class="form-control form-control-dark" placeholder="Search by username, email, or KC handle" value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        <div class="col-md-3 d-grid">
                            <button type="submit" class="btn btn-primary-custom">Search</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($search_query !== ''): ?>
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Search Results</h5>
                        <?php if (empty($search_results)): ?>
                            <p class="text-muted mb-0">No users found. Try a different search.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Email</th>
                                            <th>Zone</th>
                                            <th class="text-end">Select</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($search_results as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><?php echo htmlspecialchars($user['satellite_campus'] ?? ''); ?></td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-sm btn-outline-primary select-user-btn" data-user-id="<?php echo (int)$user['id']; ?>" data-user-name="<?php echo htmlspecialchars($user['username']); ?>" data-user-email="<?php echo htmlspecialchars($user['email']); ?>">Select</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Gift Details</h5>
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Selected Recipient</label>
                        <div id="selected-recipient" class="text-muted">No recipient selected.</div>
                    </div>

                    <form action="process_gift_subscription.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <input type="hidden" name="gifted_user_id" id="gifted_user_id" value="">
                        <input type="hidden" name="subscription_type" id="subscription_type">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Your Full Name</label>
                                <input type="text" name="full_name" class="form-control form-control-dark" id="full_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="kc_handle" class="form-label">KC Handle (for Espees)</label>
                                <input type="text" name="kc_handle" class="form-control form-control-dark" id="kc_handle">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Select Plan</label>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-outline-primary" onclick="selectPlan('daily', 2)">24-Hour Access (2 Espees)</button>
                                <button type="button" class="btn btn-outline-primary" onclick="selectPlan('monthly', 10)">1-Month Subscription (10 Espees)</button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="amount" class="form-label">Amount</label>
                            <input type="text" name="amount" class="form-control form-control-dark" id="amount" readonly>
                        </div>

                        <div class="mb-3">
                            <label for="gift_message" class="form-label">Gift Message (Optional)</label>
                            <input type="text" name="gift_message" class="form-control form-control-dark" id="gift_message" maxlength="255">
                        </div>

                        <div class="mb-3">
                            <label for="proof" class="form-label">Proof of Transaction</label>
                            <input type="file" name="proof" class="form-control" id="proof" required>
                        </div>

                        <div class="d-grid mt-3">
                            <button type="submit" class="btn btn-primary-custom">Submit Gift</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-body">
                    <h5 class="card-title">Payment Info</h5>
                    <p class="card-text"><strong>Acct no:</strong> 1000316347</p>
                    <p class="card-text"><strong>Acct Name:</strong> LMAM - Music App</p>
                    <p class="card-text"><strong>Bank:</strong> Parallex Bank</p>
                    <p class="card-text"><strong>Espees Merchant Code:</strong> LMM01</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function selectPlan(plan, amount) {
    document.getElementById('subscription_type').value = plan;
    document.getElementById('amount').value = amount;
}

document.addEventListener('DOMContentLoaded', function() {
    const buttons = document.querySelectorAll('.select-user-btn');
    const recipient = document.getElementById('selected-recipient');
    const recipientInput = document.getElementById('gifted_user_id');

    buttons.forEach((btn) => {
        btn.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            const userName = this.getAttribute('data-user-name');
            const userEmail = this.getAttribute('data-user-email');
            recipientInput.value = userId;
            recipient.textContent = userName + ' (' + userEmail + ')';
            recipient.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });
});
</script>

<?php
include 'includes/bottom_navbar.php';
include 'includes/footer.php';
?>
