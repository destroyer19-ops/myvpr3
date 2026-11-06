<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php
    if (function_exists('csrf_token')) {
        $csrf_meta_token = csrf_token();
    } else {
        $csrf_meta_token = '';
    }
    ?>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_meta_token); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    $resolved_page_title = !empty($page_title) ? $page_title : 'Virtual Praise Room';
    $resolved_page_description = !empty($page_description) ? $page_description : '';
    $resolved_page_image = !empty($page_image) ? $page_image : '';
    ?>
    <title><?php echo htmlspecialchars($resolved_page_title); ?></title>
    <?php if (!empty($resolved_page_description)): ?>
    <meta name="description" content="<?php echo htmlspecialchars($resolved_page_description); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($resolved_page_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($resolved_page_description); ?>">
    <?php endif; ?>
    <?php if (!empty($resolved_page_image)): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($resolved_page_image); ?>">
    <?php endif; ?>
    <link rel="icon" href="logo.png" type="image/png">
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="PraiseRoom">
    <link rel="apple-touch-icon" href="assets/img/icon-192x192.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css?v=<?php echo time(); ?>" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css?v=<?php echo time(); ?>">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap?v=<?php echo time(); ?>" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/navbar_x.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../N-gage/Ngage/Ngage.css?v=<?php echo time(); ?>"> <!-- Ngage Chat Stylesheet -->
    <?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php'): ?>
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo time(); ?>">
    <?php endif; ?>
    <?php if (basename($_SERVER['PHP_SELF']) == 'meeting-room.php'): ?>
    <link rel="stylesheet" href="assets/css/meeting-room.css?v=<?php echo time(); ?>">
    <?php endif; ?>


</head>
<body<?php echo isset($body_classes) ? ' class="' . htmlspecialchars($body_classes) . '"' : ''; ?>>
