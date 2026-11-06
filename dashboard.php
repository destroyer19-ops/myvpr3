<?php
ini_set("display_errors", 1);
require_once 'includes/session.php';
session_init();
$is_admin = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
if (!isset($_SESSION['user_id']) && !$is_admin) {
  header('Location: login.php');
  exit;
}

require_once 'includes/db.php';

$user_id = $_SESSION['user_id'] ?? 0;

// Resolve user_id for admins if needed
if ($user_id === 0 && $is_admin) {
    $admin_username = $_SESSION['admin_username'] ?? 'admin';
    $stmt_u = $conn->prepare("SELECT id FROM praise_users WHERE username = ? LIMIT 1");
    $stmt_u->bind_param("s", $admin_username);
    $stmt_u->execute();
    $res_u = $stmt_u->get_result();
    if ($row_u = $res_u->fetch_assoc()) {
        $user_id = $row_u['id'];
    } else {
        $res_f = $conn->query("SELECT id FROM praise_users ORDER BY id ASC LIMIT 1");
        if ($row_f = $res_f->fetch_assoc()) {
            $user_id = $row_f['id'];
        }
    }
    $stmt_u->close();
}

$display_name = $_SESSION['username'] ?? $_SESSION['admin_username'] ?? 'Esteemed User';

// Check subscription status for the logged-in user
$is_subscribed = false;
$stmt_sub = $conn->prepare("SELECT end_date FROM subscriptions WHERE user_id = ? ORDER BY end_date DESC LIMIT 1");
if ($stmt_sub) {
    $stmt_sub->bind_param("i", $user_id);
    $stmt_sub->execute();
    $result_sub = $stmt_sub->get_result();
    $subscription = $result_sub->fetch_assoc();
    $stmt_sub->close();

    if ($subscription) {
        $end_date = new DateTime($subscription['end_date']);
        $now = new DateTime();
        if ($end_date > $now) {
            $is_subscribed = true;
        }
    }
}

// Fetch total visits across user's crusades
$total_visits = 0;
$stmt_total_visits = $conn->prepare("
    SELECT COUNT(*) AS total_count
    FROM praise_crusade_stats s
    JOIN praise_crusades c ON c.id = s.crusade_id
    WHERE c.user_id = ?
");
$stmt_total_visits->bind_param("i", $user_id);
$stmt_total_visits->execute();
$result_total_visits = $stmt_total_visits->get_result();
if ($row_total_visits = $result_total_visits->fetch_assoc()) {
    $total_visits = $row_total_visits['total_count'];
}
$stmt_total_visits->close();

// Fetch crusades for the current user, categorized
$now = new DateTime();
$now_str = $now->format('Y-m-d H:i:s');

$scheduled_crusades = [];
$stmt_scheduled = $conn->prepare("SELECT c.*, COUNT(s.id) as clicks FROM praise_crusades c LEFT JOIN praise_crusade_stats s ON c.id = s.crusade_id WHERE c.user_id = ? AND c.is_active = 0 AND c.start_time > ? GROUP BY c.id ORDER BY c.start_time ASC");
$stmt_scheduled->bind_param("is", $user_id, $now_str);
$stmt_scheduled->execute();
$result_scheduled = $stmt_scheduled->get_result();
while ($row = $result_scheduled->fetch_assoc()) {
    $scheduled_crusades[] = $row;
}
$stmt_scheduled->close();

$active_crusades = [];
$stmt_active = $conn->prepare("SELECT c.*, COUNT(s.id) as clicks FROM praise_crusades c LEFT JOIN praise_crusade_stats s ON c.id = s.crusade_id WHERE c.user_id = ? AND c.is_active = 1 GROUP BY c.id ORDER BY c.created_at DESC");
$stmt_active->bind_param("i", $user_id);
$stmt_active->execute();
$result_active = $stmt_active->get_result();
while ($row = $result_active->fetch_assoc()) {
    $active_crusades[] = $row;
}
$stmt_active->close();

$past_crusades = [];
$stmt_past = $conn->prepare("SELECT c.*, COUNT(s.id) as clicks FROM praise_crusades c LEFT JOIN praise_crusade_stats s ON c.id = s.crusade_id WHERE c.user_id = ? AND c.is_active = 0 AND (c.start_time <= ? OR (c.start_time IS NULL AND c.created_at <= ?)) GROUP BY c.id ORDER BY c.start_time DESC");
$stmt_past->bind_param("iss", $user_id, $now_str, $now_str); // If start_time is null, use created_at as a fallback for 'past'
$stmt_past->execute();
$result_past = $stmt_past->get_result();
while ($row = $result_past->fetch_assoc()) {
    $past_crusades[] = $row;
}
$stmt_past->close();

// Fetch meetings for the current user
$user_meetings = [];
$stmt_meetings = $conn->prepare("SELECT * FROM praise_meetings WHERE user_id = ? ORDER BY created_at DESC");
$stmt_meetings->bind_param("i", $user_id);
$stmt_meetings->execute();
$result_meetings = $stmt_meetings->get_result();
while ($row = $result_meetings->fetch_assoc()) {
    $user_meetings[] = $row;
}
$stmt_meetings->close();

?>
<?php include 'includes/header.php'; ?>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="dashboard-container container">
    <?php if (!$is_subscribed): ?>
        <div class="sponsorship-banner">
            <div>
                <span class="sponsorship-badge">Sponsorship</span>
                <h5>Partner with the mission and expand your reach.</h5>
                <p>Your sponsorship powers more crusades, more lives touched, and better tools for your ministry.</p>
            </div>
            <div class="sponsorship-actions">
                <a class="btn btn-primary" href="subscribe.php">Become a Sponsor</a>
            </div>
        </div>
    <?php endif; ?>

        <?php if (isset($_SESSION['current_crusade_code'])): ?>
            <div class="alert alert-info d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                <div>
                    <strong>Active Crusade:</strong> Continue your last session or share it with others.
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="crusade_room.php?code=<?php echo htmlspecialchars($_SESSION['current_crusade_code']); ?>" class="btn btn-primary btn-sm">Resume</a>
                    <button class="btn btn-outline-primary btn-sm open-share-modal" data-code="<?php echo htmlspecialchars($_SESSION['current_crusade_code']); ?>">Share</button>
                </div>
            </div>
        <?php endif; ?>
        <section class="dashboard-hero-section">
            <div class="container text-center dashboard-hero-content">
                <h2 class="display-5 fw-bold mb-3">Welcome, Esteemed <?php echo htmlspecialchars($display_name); ?>!</h2>
                <p class="lead mb-4">Your central hub for managing crusades, meetings, and connecting with your community.</p>
                <div class="dashboard-actions">
                    <a href="create_crusade.php" class="btn btn-light btn-lg">Create Crusade</a>
                    <a href="videos.php" class="btn btn-outline-light btn-lg">Watch Videos</a>
                    <a href="live_tv.php" class="btn btn-light btn-lg">Live TV</a>
                </div>
            </div>
        </section>

        <div class="row mb-4">
            <div class="col-md-8 offset-md-2">
                <div class="stats-card p-4 text-center">
                    <h5 class="mb-2 text-primary">Your Crusade Views</h5>
                    <p class="display-4 fw-bold mb-0"><?php echo number_format($total_visits); ?></p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-10 offset-md-1">
                <div class="custom-card p-4">
                    <h2 class="card-title mb-4">Your Crusades</h2>

                    <ul class="nav nav-tabs mb-3" id="crusadeTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="scheduled-tab" data-bs-toggle="tab" data-bs-target="#scheduled" type="button" role="tab" aria-controls="scheduled" aria-selected="true">Scheduled (<?php echo count($scheduled_crusades); ?>)</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab" aria-controls="active" aria-selected="false">Active (<?php echo count($active_crusades); ?>)</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="past-tab" data-bs-toggle="tab" data-bs-target="#past" type="button" role="tab" aria-controls="past" aria-selected="false">Past (<?php echo count($past_crusades); ?>)</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="crusadeTabsContent">
                        <!-- Scheduled Crusades Tab -->
                        <div class="tab-pane fade show active" id="scheduled" role="tabpanel" aria-labelledby="scheduled-tab">
                            <?php if (!empty($scheduled_crusades)): ?>
                                <div class="table-responsive">
                                    <table class="table custom-table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Code</th>
                                                <th>Starts</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($scheduled_crusades as $crusade): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($crusade['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($crusade['crusade_code']); ?></td>
                                                    <td><?php echo (new DateTime($crusade['start_time']))->format('M j, Y, g:i A'); ?></td>
                                                    <td>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <a href="edit_crusade.php?code=<?php echo $crusade['crusade_code']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                                            <button type="button" class="btn btn-danger btn-sm delete-crusade-btn" data-code="<?php echo htmlspecialchars($crusade['crusade_code']); ?>">Cancel</button>
                                                            <button type="button" class="btn btn-outline-primary btn-sm open-share-modal" data-code="<?php echo htmlspecialchars($crusade['crusade_code']); ?>">Share</button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-center">No crusades scheduled yet. <a href="create_crusade.php">Schedule one now!</a></p>
                            <?php endif; ?>
                        </div>

                        <!-- Active Crusades Tab -->
                        <div class="tab-pane fade" id="active" role="tabpanel" aria-labelledby="active-tab">
                            <?php if (!empty($active_crusades)): ?>
                                <div class="table-responsive">
                                    <table class="table custom-table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Code</th>
                                                <th>Clicks</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($active_crusades as $crusade): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($crusade['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($crusade['crusade_code']); ?></td>
                                                    <td><?php echo $crusade['clicks']; ?></td>
                                                    <td>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <a href="crusade_room.php?code=<?php echo $crusade['crusade_code']; ?>" class="btn btn-secondary btn-sm">Resume</a>
                                                            <a href="crusade_stats.php?code=<?php echo $crusade['crusade_code']; ?>" class="btn btn-info btn-sm">Stats</a>
                                                            <button type="button" class="btn btn-danger btn-sm end-crusade-btn" data-code="<?php echo htmlspecialchars($crusade['crusade_code']); ?>">End</button>
                                                            <button type="button" class="btn btn-outline-primary btn-sm open-share-modal" data-code="<?php echo htmlspecialchars($crusade['crusade_code']); ?>">Share</button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-center">No crusades currently active. <a href="create_crusade.php">Start a new one!</a></p>
                            <?php endif; ?>
                        </div>

                        <!-- Past Crusades Tab -->
                        <div class="tab-pane fade" id="past" role="tabpanel" aria-labelledby="past-tab">
                            <?php if (!empty($past_crusades)): ?>
                                <div class="table-responsive">
                                    <table class="table custom-table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Code</th>
                                                <th>Clicks</th>
                                                <th>Ended</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($past_crusades as $crusade): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($crusade['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($crusade['crusade_code']); ?></td>
                                                    <td><?php echo $crusade['clicks']; ?></td>
                                                    <td><?php echo (new DateTime($crusade['end_time'] ?? $crusade['created_at']))->format('M j, Y, g:i A'); ?></td>
                                                    <td>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <a href="crusade_stats.php?code=<?php echo $crusade['crusade_code']; ?>" class="btn btn-info btn-sm">Stats</a>
                                                            <button type="button" class="btn btn-outline-danger btn-sm delete-crusade-btn" data-code="<?php echo htmlspecialchars($crusade['crusade_code']); ?>">Delete</button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-center">No past crusades.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="custom-card p-4 mt-5">
                    <h2 class="card-title mb-4">Your Meetings</h2>
                    <?php if (!empty($user_meetings)): ?>
                        <div class="table-responsive">
                            <table class="table custom-table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Code</th>
                                        <th>Platform</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($user_meetings as $meeting): ?>
                                        <?php $is_daily = !empty($meeting['daily_room_url']); ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($meeting['title']); ?></td>
                                            <td><code><?php echo htmlspecialchars($meeting['meeting_code']); ?></code></td>
                                            <td>
                                                <?php if ($is_daily): ?>
                                                    <span class="badge bg-success">Daily.co</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary">KingsConference</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo (new DateTime($meeting['created_at']))->format('M j, g:i A'); ?></td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <a href="<?php echo $is_daily ? 'meeting-room-daily.php' : 'meeting-room.php'; ?>?code=<?php echo $meeting['meeting_code']; ?>" class="btn btn-secondary btn-sm">Join</a>
                                                    <button type="button" class="btn btn-outline-primary btn-sm copy-link-btn" data-link="join.php?code=<?php echo $meeting['meeting_code']; ?>">Copy Link</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">No meetings found. <a href="create_meeting.php">Start one now!</a></p>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <?php include 'includes/bottom_navbar.php'; ?>
    <?php if (!$is_subscribed && empty($_SESSION['sponsorship_modal_shown'])): ?>
        <div class="modal fade" id="sponsorshipModal" tabindex="-1" aria-labelledby="sponsorshipModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="sponsorshipModalLabel">Help Us Reach More Souls</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>To partake in any of the streaming services, you'd need to subscribe. The subscription will enable us to send the gospel to the ends of the earth.</p>
                    </div>
                    <div class="modal-footer">
                        <a href="subscribe.php" class="btn btn-primary">Subscribe</a>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Maybe Later</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modalEl = document.getElementById('sponsorshipModal');
                if (modalEl && window.bootstrap) {
                    var modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            });
        </script>
        <?php $_SESSION['sponsorship_modal_shown'] = true; ?>
    <?php endif; ?>
<?php include 'includes/footer.php'; ?>
<script>
    document.querySelectorAll('.copy-link-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const link = this.getAttribute('data-link');
            const fullLink = `${window.location.origin}/${link}`;
            navigator.clipboard.writeText(fullLink).then(function() {
                Toastify({
                    text: "Link copied!",
                    duration: 2000,
                    gravity: "top",
                    position: "right",
                    backgroundColor: "linear-gradient(to right, #2b8c8c, #35a1a1)",
                }).showToast();
            });
        });
    });

    // Handle End Crusade button click
    document.querySelectorAll('.end-crusade-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const crusadeCode = this.getAttribute('data-code');
            if (confirm("Are you sure you want to end this crusade? It will be moved to Past Crusades.")) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                fetch('end_crusade.php', { // Reusing existing end_crusade.php
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'crusade_code=' + crusadeCode + '&csrf_token=' + encodeURIComponent(csrfToken)
                })
                .then(response => response.json()) // Expect JSON response
                .then(data => {
                    if (data.success) {
                        Toastify({
                            text: "Crusade ended successfully!",
                            duration: 3000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)",
                        }).showToast();
                        setTimeout(() => location.reload(), 500); // Reload to update tabs
                    } else {
                        Toastify({
                            text: data.message || "Failed to end crusade.",
                            duration: 3000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "linear-gradient(to right, #ff5f6d, #ffc371)",
                        }).showToast();
                    }
                })
                .catch(error => {
                    console.error('Error ending crusade:', error);
                    Toastify({
                        text: "Error ending crusade.",
                        duration: 3000,
                        gravity: "top",
                        position: "right",
                        backgroundColor: "linear-gradient(to right, #ff5f6d, #ffc371)",
                    }).showToast();
                });
            }
        });
    });

    // Handle Delete/Cancel Crusade button click
    document.querySelectorAll('.delete-crusade-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const crusadeCode = this.getAttribute('data-code');
            const actionType = this.textContent.trim(); // "Cancel" or "Delete"
            if (confirm(`Are you sure you want to ${actionType.toLowerCase()} this crusade? This action cannot be undone.`)) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                fetch('api/delete_crusade.php', { // This will be a new endpoint
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'crusade_code=' + crusadeCode + '&csrf_token=' + encodeURIComponent(csrfToken)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Toastify({
                            text: `Crusade ${actionType.toLowerCase()}ed successfully!`,
                            duration: 3000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "linear-gradient(to right, #00b09b, #96c93d)",
                        }).showToast();
                        setTimeout(() => location.reload(), 500); // Reload to update tabs
                    } else {
                        Toastify({
                            text: data.message || `Failed to ${actionType.toLowerCase()} crusade.`,
                            duration: 3000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "linear-gradient(to right, #ff5f6d, #ffc371)",
                        }).showToast();
                    }
                })
                .catch(error => {
                    console.error('Error deleting crusade:', error);
                    Toastify({
                        text: `Error ${actionType.toLowerCase()}ing crusade.`,
                        duration: 3000,
                        gravity: "top",
                        position: "right",
                        backgroundColor: "linear-gradient(to right, #ff5f6d, #ffc371)",
                    }).showToast();
                });
            }
        });
    });
</script>
<div class="modal fade" id="shareCrusadeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Share Crusade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-2">Generate a watch party link you can send to others.</p>
                <div class="input-group">
                    <input type="text" class="form-control" id="shareLinkInput" placeholder="Click Generate to create a link" readonly>
                    <button class="btn btn-primary" type="button" id="copyShareLinkBtn">Copy</button>
                </div>
                <div class="d-grid mt-3">
                    <button class="btn btn-outline-primary" type="button" id="generateShareBtn">Generate Link</button>
                </div>
                <div class="small text-muted mt-2" id="shareStatus"></div>
            </div>
        </div>
    </div>
</div>
<script>
    const shareModal = new bootstrap.Modal(document.getElementById('shareCrusadeModal'));
    let shareCode = '';

    document.querySelectorAll('.open-share-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            shareCode = btn.getAttribute('data-code');
            document.getElementById('shareLinkInput').value = '';
            document.getElementById('shareStatus').textContent = '';
            shareModal.show();
        });
    });

    document.getElementById('generateShareBtn').addEventListener('click', async () => {
        if (!shareCode) return;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        document.getElementById('shareStatus').textContent = 'Generating link...';
        const res = await fetch(`/api/crusades/${encodeURIComponent(shareCode)}/share`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ })
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('shareLinkInput').value = data.share_url;
            document.getElementById('shareStatus').textContent = data.reused ? 'Using existing active link.' : 'New link created.';
        } else {
            document.getElementById('shareStatus').textContent = data.message || 'Failed to generate link.';
        }
    });

    document.getElementById('copyShareLinkBtn').addEventListener('click', () => {
        const input = document.getElementById('shareLinkInput');
        if (!input.value) return;
        navigator.clipboard.writeText(input.value).then(() => {
            Toastify({
                text: "Share link copied!",
                duration: 2000,
                gravity: "top",
                position: "right",
                backgroundColor: "linear-gradient(to right, #2b8c8c, #35a1a1)",
            }).showToast();
        });
    });
</script>

