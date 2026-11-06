<?php
ini_set("display_errors", 1);
if (session_status() == PHP_SESSION_NONE) {
    require_once __DIR__ . '/../includes/session.php';
    session_init();
}

if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/db.php';

// Fetch pending transactions
$pendingTransactions = [];
$stmt = $conn->prepare("SELECT t.id, t.amount, t.proof_of_transaction, t.subscription_type, t.created_at, pu.username AS user_username, pu.email AS user_email, gu.username AS gifted_username, gu.email AS gifted_email FROM transactions t JOIN praise_users pu ON t.user_id = pu.id LEFT JOIN praise_users gu ON t.gifted_user_id = gu.id WHERE t.status = 'pending' ORDER BY t.created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $pendingTransactions[] = $row;
}
$stmt->close();

// Fetch active subscriptions
$activeSubscriptions = [];
$stmt = $conn->prepare("SELECT s.id, s.subscription_type, s.start_date, s.end_date, pu.username AS user_username, pu.email AS user_email FROM subscriptions s JOIN praise_users pu ON s.user_id = pu.id WHERE s.end_date > NOW() ORDER BY s.end_date ASC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $activeSubscriptions[] = $row;
}
$stmt->close();

// Fetch expired subscriptions
$expiredSubscriptions = [];
$stmt = $conn->prepare("SELECT s.id, s.subscription_type, s.start_date, s.end_date, pu.username AS user_username, pu.email AS user_email FROM subscriptions s JOIN praise_users pu ON s.user_id = pu.id WHERE s.end_date <= NOW() ORDER BY s.end_date DESC");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $expiredSubscriptions[] = $row;
}
$stmt->close();

$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Manage Subscriptions</title>
    <link rel="icon" href="../logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="admin-container container">
        <div class="row">
            <div class="col-md-10 offset-md-1">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title text-center mb-4">Manage Subscriptions</h2>

                        <!-- Tabs for Subscription Status -->
                        <ul class="nav nav-tabs" id="subscriptionTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">Pending (<?php echo count($pendingTransactions); ?>)</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab" aria-controls="active" aria-selected="false">Active (<?php echo count($activeSubscriptions); ?>)</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="expired-tab" data-bs-toggle="tab" data-bs-target="#expired" type="button" role="tab" aria-controls="expired" aria-selected="false">Expired (<?php echo count($expiredSubscriptions); ?>)</button>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content" id="subscriptionTabsContent">
                            <!-- Pending Tab Pane -->
                            <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                                <h4 class="mt-4 mb-3">Pending Transactions for Review</h4>
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Gift For</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Submitted On</th>
                                            <th>Proof</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($pendingTransactions)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted">No pending subscriptions.</td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php foreach ($pendingTransactions as $transaction): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($transaction['user_username']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($transaction['user_email']); ?></small>
                                                </td>
                                                <td>
                                                    <?php if (!empty($transaction['gifted_username'])): ?>
                                                        <strong><?php echo htmlspecialchars($transaction['gifted_username']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($transaction['gifted_email']); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($transaction['subscription_type']); ?></td>
                                                <td><?php echo htmlspecialchars($transaction['amount']); ?></td>
                                                <td><?php echo htmlspecialchars($transaction['created_at']); ?></td>
                                                <td><a href="../<?php echo htmlspecialchars($transaction['proof_of_transaction']); ?>" target="_blank">View Proof</a></td>
                                                <td class="text-end">
                                                    <a href="process_subscription_approval.php?id=<?php echo $transaction['id']; ?>&action=approve" class="btn btn-sm btn-success">Approve</a>
                                                    <a href="process_subscription_approval.php?id=<?php echo $transaction['id']; ?>&action=reject" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to reject this subscription?');">Reject</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Active Tab Pane -->
                            <div class="tab-pane fade" id="active" role="tabpanel" aria-labelledby="active-tab">
                                <h4 class="mt-4 mb-3">Currently Active Subscriptions</h4>
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Type</th>
                                            <th>Starts</th>
                                            <th>Ends</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($activeSubscriptions)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">No active subscriptions.</td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php foreach ($activeSubscriptions as $subscription): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($subscription['user_username']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($subscription['user_email']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($subscription['subscription_type']); ?></td>
                                                <td><?php echo htmlspecialchars($subscription['start_date']); ?></td>
                                                <td><?php echo htmlspecialchars($subscription['end_date']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Expired Tab Pane -->
                            <div class="tab-pane fade" id="expired" role="tabpanel" aria-labelledby="expired-tab">
                                <h4 class="mt-4 mb-3">Expired Subscriptions</h4>
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Type</th>
                                            <th>Starts</th>
                                            <th>Ended</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($expiredSubscriptions)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">No expired subscriptions.</td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php foreach ($expiredSubscriptions as $subscription): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($subscription['user_username']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($subscription['user_email']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($subscription['subscription_type']); ?></td>
                                                <td><?php echo htmlspecialchars($subscription['start_date']); ?></td>
                                                <td><?php echo htmlspecialchars($subscription['end_date']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
