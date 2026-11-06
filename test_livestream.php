<?php
ini_set("display_errors", 1);
require_once 'includes/session.php';
if (php_sapi_name() !== 'cli') {
    if (session_status() === PHP_SESSION_NONE) {
        session_init();
    }
    if (empty($_SESSION['admin_logged_in'])) {
        http_response_code(403);
        echo "Forbidden";
        exit;
    }
}
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_init();
}
require_once 'includes/db.php';

function run_all_tests() {
    echo "<h3>Running Live Crusade Page Test...</h3>";
    test_live_crusade_page();
    echo "<hr>";
    echo "<h3>Running Admin Panel Test...</h3>";
    test_admin_panel();
    echo "<hr>";
    echo "<h3>Running Create Crusade (Live Stream) Test...</h3>";
    test_create_live_crusade();
    echo "<hr>";
    echo "<h3>Running Crusade Room (Live Stream) Test...</h3>";
    test_crusade_room_live_stream();
}

function test_live_crusade_page() {
    global $conn;

    // 1. Find a stream to make live
    $stmt = $conn->prepare("SELECT * FROM praise_live_tv LIMIT 1");
    if ($stmt === false) {
        echo "TEST FAILED: Could not prepare statement to find a stream. Error: " . $conn->error . "<br>";
        return;
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $stream_to_test = $result->fetch_assoc();
    $stmt->close();

    if (!$stream_to_test) {
        echo "TEST FAILED: No streams found in the praise_live_tv table to test with.<br>";
        return;
    }

    // 2. Set the stream to live
    $stmt_update = $conn->prepare("UPDATE praise_live_tv SET is_live = 1 WHERE id = ?");
    $stmt_update->bind_param("i", $stream_to_test['id']);
    $stmt_update->execute();
    $stmt_update->close();

    // 3. Capture the output of live_crusade.php
    $_SESSION['user_id'] = 'test_user'; 
    $_SESSION['username'] = 'TestUser';
    ob_start();
    include 'live_crusade.php';
    $live_crusade_output = ob_get_clean();
    unset($_SESSION['user_id']);
    unset($_SESSION['username']);

    // 4. Check if live_crusade.php shows the correct stream
    if (strpos($live_crusade_output, htmlspecialchars($stream_to_test['title'])) !== false) {
        echo "TEST PASSED: live_crusade.php correctly displays the live stream title.<br>";
    } else {
        echo "TEST FAILED: live_crusade.php does NOT display the correct live stream title.<br>";
    }

    if (strpos($live_crusade_output, htmlspecialchars($stream_to_test['stream_url'])) !== false) {
        echo "TEST PASSED: live_crusade.php correctly displays the live stream URL.<br>";
    } else {
        echo "TEST FAILED: live_crusade.php does NOT display the correct live stream URL.<br>";
    }

    // 5. Clean up: Set the stream back to offline
    $stmt_cleanup = $conn->prepare("UPDATE praise_live_tv SET is_live = 0 WHERE id = ?");
    $stmt_cleanup->bind_param("i", $stream_to_test['id']);
    $stmt_cleanup->execute();
    $stmt_cleanup->close();
}

function test_admin_panel() {
    global $conn;

    // 1. Capture the output of admin/index.php
    $_SESSION['admin_logged_in'] = true;
    ob_start();
    include 'admin/index.php';
    $admin_index_output = ob_get_clean();
    unset($_SESSION['admin_logged_in']);

    // 2. Check if the old admin section is gone
    if (strpos($admin_index_output, 'Official Crusade Stream Management') === false) {
        echo "TEST PASSED: admin/index.php no longer shows the 'Official Crusade Stream Management' card.<br>";
    } else {
        echo "TEST FAILED: admin/index.php STILL SHOWS the 'Official Crusade Stream Management' card.<br>";
    }

    if (strpos($admin_index_output, 'manage_official_crusade.php') === false) {
        echo "TEST PASSED: admin/index.php no longer links to the old manage_official_crusade.php page.<br>";
    } else {
        echo "TEST FAILED: admin/index.php STILL LINKS to the old manage_official_crusade.php page.<br>";
    }
}

function test_create_live_crusade() {
    global $conn;

    // Simulate logged-in user
    $_SESSION['user_id'] = 999; // Use a dummy user ID
    $_SESSION['username'] = 'TestUser';

    // Simulate POST data for creating a live stream crusade
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST['title'] = 'Test Live Stream Crusade';
    $_POST['description'] = 'Description for test live stream crusade.';
    $_POST['use_live_stream'] = 'on'; // Checkbox is checked
    $_POST['video_url'] = ''; // Should be ignored

    // Include create_crusade.php
    // It will try to redirect, so we capture headers
    ob_start();
    include 'create_crusade.php';
    $output = ob_get_clean();
    unset($_SERVER['REQUEST_METHOD']);
    unset($_POST);

    // Check if redirect header was set
    $headers = xdebug_get_headers();
    $redirect_found = false;
    $crusade_code = '';
    foreach ($headers as $header) {
        if (strpos($header, 'Location: crusade_room.php?code=') !== false) {
            $redirect_found = true;
            $crusade_code = substr($header, strpos($header, 'code=') + 5, 16); // Extract code
            break;
        }
    }

    if ($redirect_found) {
        echo "TEST PASSED: create_crusade.php redirected successfully.<br>";

        // Verify the crusade in the database
        $stmt = $conn->prepare("SELECT video_url FROM praise_crusades WHERE crusade_code = ? AND user_id = ?");
        $stmt->bind_param("si", $crusade_code, $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $crusade = $result->fetch_assoc();
        $stmt->close();

        if ($crusade && $crusade['video_url'] === 'LIVE_STREAM_OFFICIAL') {
            echo "TEST PASSED: Crusade created with 'LIVE_STREAM_OFFICIAL' video_url.<br>";
        } else {
            echo "TEST FAILED: Crusade not created correctly or video_url is wrong.<br>";
        }
    } else {
        echo "TEST FAILED: create_crusade.php did not redirect.<br>";
    }

    // Clean up: delete the created crusade
    if (!empty($crusade_code)) {
        $stmt = $conn->prepare("DELETE FROM praise_crusades WHERE crusade_code = ?");
        $stmt->bind_param("s", $crusade_code);
        $stmt->execute();
        $stmt->close();
    }
    // Clean up session
    unset($_SESSION['user_id']);
    unset($_SESSION['username']);
}

function test_crusade_room_live_stream() {
    global $conn;

    // 1. Ensure an active live stream exists for the test
    $stmt_select_live = $conn->prepare("SELECT * FROM praise_live_tv LIMIT 1");
    if ($stmt_select_live === false) {
        echo "TEST FAILED: Could not prepare statement to find a stream for crusade room test. Error: " . $conn->error . "<br>";
        return;
    }
    $stmt_select_live->execute();
    $result_live = $stmt_select_live->get_result();
    $live_stream_for_test = $result_live->fetch_assoc();
    $stmt_select_live->close();

    if (!$live_stream_for_test) {
        echo "TEST FAILED: No streams found in praise_live_tv for crusade room test.<br>";
        return;
    }

    // Set it live temporarily
    $stmt_set_live = $conn->prepare("UPDATE praise_live_tv SET is_live = 1 WHERE id = ?");
    $stmt_set_live->bind_param("i", $live_stream_for_test['id']);
    $stmt_set_live->execute();
    $stmt_set_live->close();


    // 2. Create a temporary crusade with LIVE_STREAM_OFFICIAL
    $test_crusade_code = bin2hex(random_bytes(8));
    $user_id = 999; // Dummy user
    $title = "Temporary Live Crusade Room";
    $description = "This is a temporary crusade for testing live stream display.";
    $video_url = 'LIVE_STREAM_OFFICIAL';

    $stmt_insert = $conn->prepare("INSERT INTO praise_crusades (user_id, title, description, crusade_code, video_url) VALUES (?, ?, ?, ?, ?)");
    $stmt_insert->bind_param("issss", $user_id, $title, $description, $test_crusade_code, $video_url);
    $stmt_insert->execute();
    $stmt_insert->close();

    // 3. Simulate visiting crusade_room.php
    $_SESSION['user_id'] = $user_id;
    $_GET['code'] = $test_crusade_code;
    ob_start();
    include 'crusade_room.php';
    $crusade_room_output = ob_get_clean();
    unset($_SESSION['user_id']);
    unset($_GET['code']);

    // 4. Verify output contains the live stream URL
    if (strpos($crusade_room_output, htmlspecialchars($live_stream_for_test['stream_url'])) !== false) {
        echo "TEST PASSED: crusade_room.php correctly displays the current live stream.<br>";
    } else {
        echo "TEST FAILED: crusade_room.php did NOT display the current live stream. Output:<br>" . htmlspecialchars($crusade_room_output) . "<br>";
    }

    // 5. Clean up
    $stmt_delete_crusade = $conn->prepare("DELETE FROM praise_crusades WHERE crusade_code = ?");
    $stmt_delete_crusade->bind_param("s", $test_crusade_code);
    $stmt_delete_crusade->execute();
    $stmt_delete_crusade->close();

    // Set stream back to offline
    $stmt_set_offline = $conn->prepare("UPDATE praise_live_tv SET is_live = 0 WHERE id = ?");
    $stmt_set_offline->bind_param("i", $live_stream_for_test['id']);
    $stmt_set_offline->execute();
    $stmt_set_offline->close();
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Stream Feature Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; font-family: sans-serif; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Live Stream Feature Test Results</h1>
        <div class="card">
            <div class="card-body">
                <?php run_all_tests(); ?>
            </div>
        </div>
        <div class="alert alert-danger mt-4">
            <strong>Security Warning:</strong> Please delete this file (`test_livestream.php`) from your server immediately.
        </div>
    </div>
</body>
</html>
