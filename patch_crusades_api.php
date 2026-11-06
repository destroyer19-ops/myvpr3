<?php
$content = file_get_contents('api/endpoints/crusades.php');

$content = str_replace(
    'SELECT c.id, c.title, c.crusade_code as code, c.start_time as startTime, COUNT(s.id) as clicks',
    'SELECT c.id, c.title, c.crusade_code as code, c.start_time as startTime, c.status, COUNT(s.id) as clicks',
    $content
);

$content = str_replace(
    'SELECT id, user_id, title, description, video_url, is_active, crusade_code as code, start_time as startTime, end_time as endTime FROM praise_crusades WHERE crusade_code = ?',
    'SELECT id, user_id, title, description, video_url, is_active, status, crusade_code as code, start_time as startTime, end_time as endTime FROM praise_crusades WHERE crusade_code = ?',
    $content
);

$content = str_replace(
    '$crusade[\'status\'] = $status;',
    '$crusade[\'status\'] = $crusade[\'status\'] ?? $status;',
    $content
);

$content = str_replace(
    'INSERT INTO praise_crusades (user_id, title, description, video_url, crusade_code, is_active, start_time, end_time, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
    'INSERT INTO praise_crusades (user_id, title, description, video_url, crusade_code, is_active, status, start_time, end_time, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
    $content
);

$content = str_replace(
    '$stmt->bind_param("issssiss", $user_id, $title, $description, $video_url, $code, $is_active, $start_time, $end_time);',
    '$explicit_status = $schedule ? \'scheduled\' : \'live\'; if(isset($data[\'draft\']) && $data[\'draft\']) { $explicit_status = \'draft\'; }'."\n".'    $stmt->bind_param("issssiss", $user_id, $title, $description, $video_url, $code, $is_active, $explicit_status, $start_time, $end_time);',
    $content
);

file_put_contents('api/endpoints/crusades.php', $content);
