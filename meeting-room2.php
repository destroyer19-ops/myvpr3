<?php
ini_set("display_errors", 1);
require_once 'includes/session.php';
session_init();

$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
if (!isset($_SESSION['user_id']) && !$is_admin) {
  header('Location: login.php');
  exit;
}

$start_time = time(); 
$end_time = $start_time + (24 * 60 * 60); // 24 hours
$remaining_time = $end_time - $start_time;

// Check if there's a meeting code in the URL
$join_code = isset($_GET['code']) ? $_GET['code'] : '';
$user_name = $_SESSION['username'] ?? $_SESSION['admin_username'] ?? 'Guest';
$user_id = $_SESSION['user_id'] ?? 0;
?>
<?php include 'includes/header.php'; ?>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="meeting-container">
        <div class="meeting-header">
            <h2><i class="fas fa-video me-2"></i> Virtual Praise Room</h2>
            <div id="countdown" class="countdown-timer d-none"></div>
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
            <button class="control-btn" onclick="raiseHand()" title="Raise Hand"><i class="fas fa-hand-paper"></i></button>
            <button class="control-btn danger" onclick="endMeeting()" title="End Meeting"><i class="fas fa-phone-slash"></i></button>
        </div>

        <div class="share-section">
            <div class="input-group">
                <input id="meetingLinkInput" type="text" class="form-control" value="" placeholder="Meeting link will appear here" readonly>
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
        let currentMeetingCode = "<?php echo $join_code; ?>";
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
            const input = document.getElementById('meetingLinkInput');
            input.select();
            document.execCommand('copy');
            showToast("Meeting link copied to clipboard!");
        }

        function loadMeet(meetingCode) {
            const domain = 'hq2.kingsconference.org';
            const roomPrefix = 'Praise/';
            const userName = "<?php echo $user_name; ?>";
            
            const options = {
                roomName: roomPrefix + meetingCode,
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
                        'fodeviceselection', 'hangup', 'profile', 'chat', 'recording',
                        'livestreaming', 'etherpad', 'sharedvideo', 'settings', 'raisehand',
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
                showToast(`Welcome to the meeting, ${userName}!`);
                updateParticipantsList();
            });

            jitsiApi.addEventListener('participantJoined', updateParticipantsList);
            jitsiApi.addEventListener('participantLeft', updateParticipantsList);

            jitsiApi.addEventListener('readyToClose', () => {
                endMeeting();
                window.location.href = "<?php echo $is_admin ? 'admin/manage_meetings.php' : 'index.php'; ?>";
            });
        }

        function updateParticipantsList() {
            if (!jitsiApi) return;

            const participants = jitsiApi.getParticipantsInfo();
            const participantsList = document.getElementById('participantsList');
            let html = '<ul class="list-group">';
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

        function raiseHand() {
            if (jitsiApi) jitsiApi.executeCommand('toggleRaiseHand');
        }

        function endMeeting() {
            if (jitsiApi) {
                jitsiApi.dispose();
            }
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            fetch('end_meeting.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `meeting_id=${currentMeetingCode}&csrf_token=${encodeURIComponent(csrfToken)}`
            }).then(() => {
                window.location.href = "<?php echo $is_admin ? 'admin/manage_meetings.php' : 'index.php'; ?>";
            }).catch(() => {
                window.location.href = "<?php echo $is_admin ? 'admin/manage_meetings.php' : 'index.php'; ?>";
            });
        }

        if (currentMeetingCode) {
            loadMeet(currentMeetingCode);
            document.getElementById('meetingLinkInput').value = `${window.location.origin}/join.php?code=${currentMeetingCode}`;
        } else {
            // Handle case where there is no meeting code
            // Maybe redirect to a page to create or join a meeting
        }

    </script>
    <?php include 'includes/bottom_navbar.php'; ?>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
