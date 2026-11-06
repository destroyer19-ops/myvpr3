<?php
ini_set("display_errors", 1);
require_once 'includes/session.php';
session_init();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';

$session_code = isset($_GET['code']) ? $_GET['code'] : '';
$user_name = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';
$user_id = $_SESSION['user_id'];

if (empty($session_code)) {
    die('Session code is missing.');
}

// Fetch session details
$stmt = $conn->prepare("SELECT * FROM praise_sessions WHERE session_code = ?");
$stmt->bind_param("s", $session_code);
$stmt->execute();
$result = $stmt->get_result();
$session = $result->fetch_assoc();
$stmt->close();

if (!$session) {
    die('Praise session not found.');
}

$session_title = htmlspecialchars($session['title']);
$session_duration_minutes = (int)$session['duration'];
$start_time_timestamp = strtotime($session['created_at']);
$end_time_timestamp = $start_time_timestamp + ($session_duration_minutes * 60);
$remaining_time = $end_time_timestamp - time();

// If remaining time is negative, session has already ended.
if ($remaining_time < 0) {
    // Optionally redirect or show a message that the session has ended
    // For now, we'll set remaining time to 0 to prevent negative countdown
    $remaining_time = 0;
}

?>
<?php include 'includes/header.php'; ?>

<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="meeting-container">
        <div class="meeting-header">
            <h2><i class="fas fa-hand-sparkles me-2"></i> <?php echo $session_title; ?></h2>
            <div id="countdown" class="countdown-timer"></div>
        </div>

        <div class="meeting-body">
            <div id="meet"></div>
        </div>

        <div class="meeting-controls">
            <button id="muteAudioBtn" class="control-btn" onclick="toggleAudio()" title="Mute/Unmute"><i class="fas fa-microphone"></i></button>
            <button id="muteVideoBtn" class="control-btn" onclick="toggleVideo()" title="Turn Camera On/Off"><i class="fas fa-video"></i></button>
            <button class="control-btn" onclick="toggleScreenShare()" title="Share Screen"><i class="fas fa-desktop"></i></button>
            <button class="control-btn" onclick="toggleChat()" title="Chat"><i class="fas fa-comment-alt"></i></button>
            <button class="control-btn" onclick="showParticipants()" title="Participants"><i class="fas fa-users"></i></button>
            <button class="control-btn danger" onclick="endSession()" title="End Session"><i class="fas fa-phone-slash"></i></button>
        </div>

        <div class="share-section">
            <div class="input-group">
                <input id="sessionLinkInput" type="text" class="form-control" value="" placeholder="Session link will appear here" readonly>
                <button class="btn btn-primary" type="button" onclick="copyLink()">Copy</button>
            </div>
        </div>
    </div>

    <!-- Participants Modal -->
    <div class="modal fade" id="participantsModal" tabindex="-1" aria-labelledby="participantsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="participantsModalLabel">Participants</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="participantsList"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Toastify JS -->
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <!-- Jitsi Meet API -->
    <script src="https://hq2.kingsconference.org/external_api.js"></script>

    <script>
        let currentSessionCode = "<?php echo $session_code; ?>";
        let jitsiApi = null;
        const participantsModal = new bootstrap.Modal(document.getElementById('participantsModal'));

        function showToast(message, success = true) {
            Toastify({
                text: message,
                duration: 3000,
                gravity: "top",
                position: "right",
                style: {
                    background: success ? "linear-gradient(to right, #00b09b, #96c93d)" : "linear-gradient(to right, #ff5f6d, #ffc371)",
                }
            }).showToast();
        }

        function copyLink() {
            const input = document.getElementById('sessionLinkInput');
            input.select();
            document.execCommand('copy');
            showToast("Session link copied to clipboard!");
        }

        function loadMeet(sessionCode) {
            const domain = 'hq2.kingsconference.org';
            const roomPrefix = 'PraiseSession/'; // Use a different room prefix for praise sessions
            const userName = "<?php echo $user_name; ?>";

            const options = {
                roomName: roomPrefix + sessionCode,
                width: '100%',
                height: '100%',
                parentNode: document.querySelector('#meet'),
                userInfo: {
                    displayName: userName
                },
                configOverwrite: {
                    startWithAudioMuted: true,
                    startWithVideoMuted: true,
                    prejoinPageEnabled: false,
                },
                interfaceConfigOverwrite: {
                    TOOLBAR_BUTTONS: [
                        'microphone', 'camera', 'closedcaptions', 'desktop', 'fullscreen',
                        'fodeviceselection', 'hangup', 'profile', 'chat',
                        'etherpad', 'sharedvideo', 'settings', 'raisehand',
                        'videoquality', 'filmstrip', 'invite', 'feedback', 'stats', 'shortcuts',
                        'tileview', 'videobackgroundblur', 'download', 'help', 'mute-everyone'
                    ],
                    SHOW_JITSI_WATERMARK: false,
                    SHOW_WATERMARK_FOR_GUESTS: false,
                    DEFAULT_BACKGROUND: '#263238',
                },
            };

            jitsiApi = new JitsiMeetExternalAPI(domain, options);

            jitsiApi.addEventListener('videoConferenceJoined', () => {
                showToast(`Welcome to the Praise Session, ${userName}!`);
                updateParticipantsList();
            });

            jitsiApi.addEventListener('participantJoined', updateParticipantsList);
            jitsiApi.addEventListener('participantLeft', updateParticipantsList);

            jitsiApi.addEventListener('readyToClose', () => {
                endSession();
                window.location.href = 'index.php'; // Redirect after session ends
            });
        }

        function updateParticipantsList() {
            if (!jitsiApi) return;

            const participants = jitsiApi.getParticipantsInfo();
            const participantsList = document.getElementById('participantsList');
            let html = '<ul class="list-group">';
            // Add current user to list
            html += `<li class="list-group-item">${jitsiApi.getDisplayName()} (You)</li>`;
            participants.forEach(p => {
                html += `<li class="list-group-item">${p.displayName}</li>`;
            });
            html += '</ul>';
            participantsList.innerHTML = html;
        }

        function showParticipants() {
            updateParticipantsList();
            participantsModal.show();
        }

        function toggleAudio() {
            if (jitsiApi) jitsiApi.executeCommand('toggleAudio');
        }

        function toggleVideo() {
            if (jitsiApi) jitsiApi.executeCommand('toggleVideo');
        }

        function toggleScreenShare() {
            if (jitsiApi) jitsiApi.executeCommand('toggleShareScreen');
        }

        function toggleChat() {
            if (jitsiApi) jitsiApi.executeCommand('toggleChat');
        }

        function endSession() {
            if (jitsiApi) {
                jitsiApi.dispose();
            }
            // Optionally update database that session has ended
            // fetch('end_praise_session.php', { method: 'POST', body: 'session_code=' + currentSessionCode });
        }

        let timerInterval = null;

        function startTimer(duration) {
            let timer = duration,
                minutes, seconds;
            const countdownEl = document.getElementById('countdown');
            countdownEl.classList.remove('d-none'); // Ensure timer is visible

            if (timerInterval) {
                clearInterval(timerInterval);
            }

            timerInterval = setInterval(function() {
                minutes = parseInt(timer / 60, 10);
                seconds = parseInt(timer % 60, 10);

                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;

                countdownEl.textContent = minutes + ":" + seconds;

                if (--timer < 0) {
                    clearInterval(timerInterval);
                    countdownEl.textContent = "00:00"; // Display 00:00 when time is up
                    showToast("Time's up! The session will now end.", false);
                    endSession();
                    window.location.href = 'index.php'; // Redirect after timer runs out
                }
            }, 1000);
        }

        if (currentSessionCode) {
            loadMeet(currentSessionCode);
            document.getElementById('sessionLinkInput').value = `${window.location.origin}/praise_session_room.php?code=${currentSessionCode}`;
            startTimer(<?php echo $remaining_time; ?>);
        } else {
            // Handle case where there is no session code
            window.location.href = 'create_praise_session.php'; // Redirect to create page
        }
    </script>
    <?php include 'includes/bottom_navbar.php'; ?>
    <?php include 'includes/footer.php'; ?>
</body>

</html>
