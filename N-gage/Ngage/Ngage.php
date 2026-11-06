<?php
ini_set('display_errors', 1);
require_once __DIR__ . '/../../includes/session.php';
session_init();
if (!isset($_SESSION['lastID'])) {
    $_SESSION['lastID'] = 0;
}

//configure chat db
$db = new mysqli('localhost', 'ghostdevmo_conference_Dev', 'onfire4God', 'ghostdevmo_virtual_praise_room') or die('DB connection failed');




function sanitize($input)
{
    global $db;
    return htmlentities(mysqli_real_escape_string($db, strip_tags($input)), ENT_QUOTES);
}

function sanitizeAll($input, $schema)
{
    $data = array();
    foreach ($input as $key => $value) {
        if (in_array($key, $schema) && $value == '' && $schema != null) {
            // return ['error' => $key . ' is required'];
            echo rspndr([
                'msg' => $key . ' is required',
                'do' => ['toast', 'toast2']
            ]);
            exit();
        }

        if (gettype($value) == 'string') {
            $data[$key] = sanitize($value);
        }
    }
    return $data;
}

function error_exists($data)
{
    if (in_array('error', $data)) {
        return $data['error'];
    }

    return false;
}


function rspndr($data)
{
    echo json_encode($data);
    return exit();
}

count($_POST) < 1 ? $BODY = json_decode(file_get_contents('php://input'), true) : $BODY = $_POST;


;

if (isset($_GET['do']) && $_GET['do'] === 'send') {
    include_once(__DIR__ . '/data/profane.php');
    $data = sanitizeAll($BODY, ['comment', 'name', 'id']);
    $date = strtotime(date('d M Y'));
    $time = date('h:i:a');
    $data['time'] = $time;


    // profanity filter 
    function contains($str, array $arr)
    {
        foreach ($arr as $a) {
            if (strcasecmp($a, $str) == 0)
                return true;
        }
        return false;
    }


    if (contains($data['comment'], $profane)) {
        rspndr([
            'status' => false,
            'msg' => 'This comment seems contains censored words. please be corteous with your comments',
            'do' => ['toast2'],
        ]);
    }


    if (
        $db->query("INSERT INTO comments(date,time,property,name,xid,comment) VALUES ('" . $date . "',
        '" . $time . "','" . $data['id'] . "', '" . $data['name'] . "', '" . $data['xid'] . "','" . $data['comment'] . "')")
    ) {
        rspndr([
            'status' => true,
            // 'msg' => 'A video with this title exists. please modify the media title',
            'do' => ['commentr'],
            'd' => $data
        ]);
    }

    rspndr([
        'status' => false,
        'msg' => 'Failed to add comment',
        'do' => ['toast2'],
    ]);
}

if (isset($_GET['get'])) {
    $propertyID = $_GET['get'];
    $chats = $db->query("SELECT * FROM comments WHERE property='" . $propertyID . "' order by id desc limit 30  ");
    if ($chats) {
        $list = [];
        while ($chat = $chats->fetch_assoc()) {
            $list[] = $chat;
        }
        $chats->num_rows > 0 ? $_SESSION['lastID'] = $list[($chats->num_rows - 1)]['id'] : $_SESSION['lastID'] = 0;
        echo json_encode(array_reverse($list));
    } else {
        echo json_encode([]);
    }
}

if (isset($_GET['fetch'])) {
    // ob_end_clean();
    $propertyID = $_GET['fetch'];
    $uid = $_GET['uid'];
    $chats = $db->query("SELECT * FROM comments WHERE property='" . $propertyID . "' and date='" . strtotime(date('Y-m-d')) . "'  and id > " . $_SESSION['lastID'] . " and xid != '" . $uid . "' ");
    if ($chats) {
        $list = [];
        while ($chat = $chats->fetch_assoc()) {
            // $list[$chat["date"]] = date('d M Y', $chat['date']);
            $list[] = $chat;
        }
        $chats->num_rows > 0 ? $_SESSION['lastID'] = $list[($chats->num_rows - 1)]['id'] : null;

        echo "data:" . json_encode($list);
        echo "\r\n\r\n";
        header('Content-Type: text/event-stream');
    } else {
        echo "data:" . json_encode([]);
        echo "\r\n\r\n";
        header('Content-Type: text/event-stream');
    }

}
