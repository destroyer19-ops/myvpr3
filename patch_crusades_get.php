<?php
$file = '/Users/home/Documents/myvprNew/api/endpoints/crusades.php';
$content = file_get_contents($file);

$search = <<<EOD
        if (\$crusade) {
            json_response(['success' => true, 'data' => \$crusade]);
        }
EOD;

$replace = <<<EOD
        if (\$crusade) {
            \$actual_url = \$crusade['video_url'];
            
            if (strpos(\$actual_url, 'LIVE_STREAM_OFFICIAL_') === 0) {
                \$id = (int)str_replace('LIVE_STREAM_OFFICIAL_', '', \$actual_url);
                \$stmt_live = \$conn->prepare("SELECT stream_url FROM praise_live_tv WHERE id = ?");
                \$stmt_live->bind_param("i", \$id);
                \$stmt_live->execute();
                \$res_live = \$stmt_live->get_result();
                if (\$row_live = \$res_live->fetch_assoc()) {
                    \$crusade['resolved_video_url'] = \$row_live['stream_url'];
                }
                \$stmt_live->close();
            } elseif (strpos(\$actual_url, 'VPR_VOD_') === 0) {
                \$id = (int)str_replace('VPR_VOD_', '', \$actual_url);
                \$stmt_vod = \$conn->prepare("SELECT video_url FROM praise_videos WHERE id = ?");
                \$stmt_vod->bind_param("i", \$id);
                \$stmt_vod->execute();
                \$res_vod = \$stmt_vod->get_result();
                if (\$row_vod = \$res_vod->fetch_assoc()) {
                    \$crusade['resolved_video_url'] = \$row_vod['video_url'];
                }
                \$stmt_vod->close();
            } else {
                \$crusade['resolved_video_url'] = \$actual_url;
            }
            
            json_response(['success' => true, 'data' => \$crusade]);
        }
EOD;

$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
echo "Patched API.";
