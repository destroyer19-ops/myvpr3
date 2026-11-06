<?php
// videos.php - Videos (VOD) endpoints

// Endpoint: GET /videos/languages
if ($method === 'GET' && $path === '/videos/languages') {
    require_auth();
    
    $languages = [];
    $lang_result = $conn->query("SELECT DISTINCT language FROM praise_videos WHERE language IS NOT NULL AND language != '' ORDER BY language ASC");
    if ($lang_result && $lang_result->num_rows > 0) {
        while($row = $lang_result->fetch_assoc()) {
            $languages[] = $row['language'];
        }
    }

    json_response([
        'success' => true,
        'data' => $languages
    ]);
}

// Endpoint: GET /videos/:id
if ($method === 'GET' && preg_match('#^/videos/(\d+)$#', $path, $matches)) {
    require_auth();
    $video_id = (int)$matches[1];
    
    $stmt = $conn->prepare("SELECT * FROM praise_videos WHERE id = ?");
    $stmt->bind_param("i", $video_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $video = $result->fetch_assoc();
    $stmt->close();

    if (!$video) {
        json_response(['success' => false, 'message' => 'Video not found.'], 404);
    }

    json_response([
        'success' => true,
        'data' => $video
    ]);
}

// Endpoint: GET /videos
if ($method === 'GET' && $path === '/videos') {
    require_auth();

    // Filters
    $filter_language = $_GET['language'] ?? '';
    $filter_children = isset($_GET['children_outreach']) && $_GET['children_outreach'] === '1';
    $filter_movie = isset($_GET['movie_outreach']) && $_GET['movie_outreach'] === '1';
    $filter_ministrations = isset($_GET['ministrations']) && $_GET['ministrations'] === '1';

    $sql = "SELECT id, title, video_url, age_category, type_category, created_at, language, category_children_outreach, category_movie_outreach, category_ministrations FROM praise_videos WHERE 1=1";
    $params = [];
    $types = "";

    if ($filter_language) {
        $sql .= " AND language = ?";
        $params[] = $filter_language;
        $types .= "s";
    }
    if ($filter_children) {
        $sql .= " AND category_children_outreach = 1";
    }
    if ($filter_movie) {
        $sql .= " AND category_movie_outreach = 1";
    }
    if ($filter_ministrations) {
        $sql .= " AND category_ministrations = 1";
    }

    $sql .= " ORDER BY title";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $videos = [];
        while($row = $result->fetch_assoc()) {
            $videos[] = $row;
        }
        $stmt->close();

        json_response([
            'success' => true,
            'data' => $videos
        ]);
    } else {
        json_response(['success' => false, 'message' => 'Database error.'], 500);
    }
}
