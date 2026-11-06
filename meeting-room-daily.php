<?php
ini_set("display_errors", 1);
require_once 'includes/session.php';
session_init();

$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
if (!isset($_SESSION['user_id']) && !$is_admin) {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';

$join_code = isset($_GET['code']) ? $_GET['code'] : '';
$user_name = $_SESSION['username'] ?? $_SESSION['admin_username'] ?? 'Guest';

// Get meeting details
$stmt = $conn->prepare("SELECT * FROM praise_meetings WHERE meeting_code = ?");
$stmt->bind_param("s", $join_code);
$stmt->execute();
$result = $stmt->get_result();
$meeting = $result->fetch_assoc();

if (!$meeting || empty($meeting['daily_room_url'])) {
    die('Daily.co meeting not found or URL missing.');
}

$room_url = $meeting['daily_room_url'];
$meeting_id = $meeting['id'];
?>
<?php include 'includes/header.php'; ?>
<!-- Toastify CSS -->
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<style>
    body {
        background: radial-gradient(circle at center, #0a192f 0%, #020c1b 100%);
        color: #e1e1e6;
        overflow-x: hidden;
    }
    .cinematic-wrapper {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        padding-top: 20px;
    }
    #daily-container {
        width: 100%;
        height: calc(100vh - 180px);
        min-height: 500px;
        border: none;
        background: #000;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(0,0,0,0.8);
        border: 1px solid rgba(100,255,218,0.1);
    }
    .cinematic-header {
        padding: 10px 20px 20px;
        background: linear-gradient(180deg, rgba(2,12,27,0.8) 0%, rgba(2,12,27,0) 100%);
    }
    .meeting-badge {
        background: rgba(40, 167, 69, 0.1);
        color: #28a745;
        border: 1px solid rgba(40, 167, 69, 0.2);
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 600;
    }
    .btn-cinematic {
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.1);
        color: #e1e1e6;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
    }
    .btn-cinematic:hover {
        background: rgba(255,255,255,0.15);
        border-color: rgba(255,255,255,0.2);
        color: #fff;
        transform: translateY(-2px);
    }
    .btn-cinematic-danger {
        background: rgba(220, 53, 69, 0.1);
        border: 1px solid rgba(220, 53, 69, 0.2);
        color: #ff4d5e;
    }
    .btn-cinematic-danger:hover {
        background: rgba(220, 53, 69, 0.2);
        color: #fff;
    }
    .meeting-title {
        font-weight: 700;
        letter-spacing: -0.5px;
        background: linear-gradient(to right, #fff, #999);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    /* Hide scrollbar for cinematic feel */
    ::-webkit-scrollbar { width: 6px; }
    ::-webkit-scrollbar-track { background: #020c1b; }
    ::-webkit-scrollbar-thumb { background: #0a192f; border-radius: 10px; }
    
    .navbar { background: rgba(2, 12, 27, 0.8) !important; backdrop-filter: blur(20px); }
</style>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="cinematic-wrapper container-fluid">
        <div class="row justify-content-center">
            <div class="col-lg-11 col-xl-10">
                <div class="cinematic-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-3 mb-1">
                            <h2 class="meeting-title mb-0"><?php echo htmlspecialchars($meeting['title']); ?></h2>
                            <span class="meeting-badge">Live Experience</span>
                        </div>
                        <p class="text-muted small mb-0">Powered by Virtual Praise Room Cinematic Engine</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-cinematic px-4" onclick="copyLink()">
                            <i class="fas fa-link me-2"></i>Share Link
                        </button>
                        <a href="<?php echo $is_admin ? 'admin/manage_meetings.php' : 'dashboard.php'; ?>" class="btn btn-cinematic btn-cinematic-danger px-4">
                            <i class="fas fa-power-off me-2"></i>Leave Room
                        </a>
                    </div>
                </div>
                
                <div id="daily-container">
                    <!-- Daily.co Iframe will be injected here -->
                </div>
                
                <div class="mt-4 text-center pb-4">
                    <p class="small text-muted mb-0">Join the global community in worship. Securely encrypted and optimized for high-capacity attendance.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Daily.co SDK -->
    <script src="https://unpkg.com/@daily-co/daily-js"></script>
    <!-- Toastify JS -->
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    <script>
        const roomUrl = "<?php echo $room_url; ?>";
        const userName = "<?php echo $user_name; ?>";
        const meetingId = "<?php echo $meeting_id; ?>";
        const meetingCode = "<?php echo $join_code; ?>";
        const csrfToken = "<?php echo csrf_token(); ?>";

        function showToast(message, success = true) {
            Toastify({
                text: message,
                duration: 4000,
                gravity: "bottom",
                position: "center",
                style: {
                    background: success ? "rgba(40, 167, 69, 0.9)" : "rgba(220, 53, 69, 0.9)",
                    backdropFilter: "blur(10px)",
                    borderRadius: "12px",
                    boxShadow: "0 10px 30px rgba(0,0,0,0.5)",
                    border: "1px solid rgba(255,255,255,0.1)"
                }
            }).showToast();
        }

        function copyLink() {
            const link = `${window.location.origin}/join.php?code=${meetingCode}`;
            navigator.clipboard.writeText(link).then(() => {
                showToast("Access link has been secured to your clipboard.");
            });
        }

        async function joinMeeting() {
            const callFrame = window.DailyIframe.createFrame(
                document.getElementById('daily-container'),
                {
                    showLeaveButton: true,
                    theme: {
                        colors: {
                            accent: '#28a745',
                            accentText: '#ffffff',
                            background: '#000000',
                            backgroundAccent: '#1a1a1a',
                            baseText: '#e1e1e6',
                            border: '#333333',
                            mainAreaBg: '#000000',
                            mainAreaBgAccent: '#0a0a0a',
                            mainAreaText: '#ffffff',
                            supportiveText: '#999999',
                        },
                    },
                    iframeStyle: {
                        width: '100%',
                        height: '100%',
                        border: '0',
                    }
                }
            );

            callFrame.on('joined-meeting', async () => {
                showToast(`Welcome to the sanctuary, ${userName}.`);
                // Log session start
                try {
                    await fetch('start_meeting.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `meeting_id=${meetingId}&csrf_token=${encodeURIComponent(csrfToken)}`
                    });
                } catch (e) { console.error("Failed to log start", e); }
            });

            callFrame.on('left-meeting', async () => {
                // Log session end
                try {
                    await fetch('end_meeting.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `meeting_id=${meetingCode}&csrf_token=${encodeURIComponent(csrfToken)}`
                    });
                } catch (e) { console.error("Failed to log end", e); }
                window.location.href = '<?php echo $is_admin ? 'admin/manage_meetings.php' : 'dashboard.php'; ?>';
            });

            await callFrame.join({ 
                url: roomUrl,
                userName: userName
            });
        }

        joinMeeting();
    </script>

    <?php include 'includes/bottom_navbar.php'; ?>
<?php include 'includes/footer.php'; ?>
