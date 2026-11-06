<?php
ini_set('session.use_only_cookies', 1);
if (session_status() == PHP_SESSION_NONE) {
    require_once 'includes/session.php';
    session_init();
}
require_once 'includes/db.php';

$page_title = 'Zonal Church Progress';

$users = [];
$query = "
    SELECT pu.id, pu.username, pu.email, pu.satellite_campus,
           s.subscription_type, s.end_date
    FROM praise_users pu
    LEFT JOIN (
        SELECT s1.*
        FROM subscriptions s1
        INNER JOIN (
            SELECT user_id, MAX(end_date) AS max_end
            FROM subscriptions
            GROUP BY user_id
        ) latest ON latest.user_id = s1.user_id AND s1.end_date = latest.max_end
    ) s ON s.user_id = pu.id
    WHERE pu.satellite_campus IS NOT NULL AND pu.satellite_campus <> ''
    ORDER BY pu.satellite_campus ASC, pu.username ASC
";

$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
$conn->close();

$zones = [];
$now = new DateTime();
foreach ($users as $user) {
    $zone = $user['satellite_campus'];
    if (!isset($zones[$zone])) {
        $zones[$zone] = [
            'total' => 0,
            'active' => 0,
            'expired' => 0,
            'none' => 0,
            'daily' => 0,
            'monthly' => 0,
            'users' => [],
        ];
    }

    $zones[$zone]['total']++;

    $subscription_type = $user['subscription_type'] ?? '';
    $end_date = $user['end_date'] ?? '';
    $status = 'None';

    if (!empty($end_date)) {
        $end = new DateTime($end_date);
        if ($end > $now) {
            $status = 'Active';
            $zones[$zone]['active']++;
        } else {
            $status = 'Expired';
            $zones[$zone]['expired']++;
        }
    } else {
        $zones[$zone]['none']++;
    }

    if ($subscription_type === 'daily') {
        $zones[$zone]['daily']++;
    } elseif ($subscription_type === 'monthly') {
        $zones[$zone]['monthly']++;
    }

    $zones[$zone]['users'][] = [
        'username' => $user['username'],
        'email' => $user['email'],
        'subscription_type' => $subscription_type ?: '—',
        'status' => $status,
        'end_date' => $end_date ?: '—',
    ];
}
?>
<?php include 'includes/header.php'; ?>
<body>
    <div class="container page-container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <h2 class="text-center mt-4">Zonal Church Progress</h2>
                <p class="text-center text-muted">Overview of each zone, subscription types, and status.</p>

                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Zone Summary</h5>
                        <?php if (empty($zones)): ?>
                            <p class="text-muted mb-0">No zonal data found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Zone</th>
                                            <th>Total People</th>
                                            <th>Active</th>
                                            <th>Expired</th>
                                            <th>Daily</th>
                                            <th>Monthly</th>
                                            <th>No Sub</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($zones as $zone_name => $zone_data): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($zone_name); ?></td>
                                                <td><?php echo (int)$zone_data['total']; ?></td>
                                                <td><?php echo (int)$zone_data['active']; ?></td>
                                                <td><?php echo (int)$zone_data['expired']; ?></td>
                                                <td><?php echo (int)$zone_data['daily']; ?></td>
                                                <td><?php echo (int)$zone_data['monthly']; ?></td>
                                                <td><?php echo (int)$zone_data['none']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php foreach ($zones as $zone_name => $zone_data): ?>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Zone: <?php echo htmlspecialchars($zone_name); ?></h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Email</th>
                                            <th>Subscription</th>
                                            <th>Status</th>
                                            <th>Ends</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($zone_data['users'] as $zone_user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($zone_user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($zone_user['email']); ?></td>
                                                <td><?php echo htmlspecialchars($zone_user['subscription_type']); ?></td>
                                                <td><?php echo htmlspecialchars($zone_user['status']); ?></td>
                                                <td><?php echo htmlspecialchars($zone_user['end_date']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
