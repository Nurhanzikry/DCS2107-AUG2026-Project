<?php
$pageTitle = $pageTitle ?? 'Club & Society Event Registration System';
$activeUser = current_user();
$flashMessage = take_flash();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | Club & Society</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/script.js" defer></script>
</head>
<body>
<header class="site-header">
    <div class="container nav-wrap">
        <a class="brand" href="index.php"><span class="brand-mark">C</span><span>Club <b>&</b> Society</span></a>
        <button class="nav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false">☰</button>
        <nav class="main-nav" aria-label="Main navigation">
            <a href="index.php">Home</a>
            <a href="clubs.php">Clubs</a>
            <a href="events.php">Events</a>
            <?php if ($activeUser): ?>
                <a href="dashboard.php">Dashboard</a>
                <?php if ($activeUser['role'] === 'student'): ?>
                    <a href="my_events.php">My Events</a>
                <?php endif; ?>
                <?php if (in_array($activeUser['role'], ['club_member', 'admin'], true)): ?>
                    <a href="manage_events.php">Manage Events</a>
                <?php endif; ?>
                <?php if ($activeUser['role'] === 'admin'): ?>
                    <a href="manage_clubs.php">Manage Clubs</a>
                    <a href="manage_users.php">Users</a>
                <?php endif; ?>
                <span class="nav-user"><?= e($activeUser['full_name']) ?></span>
                <a class="nav-login" href="logout.php">Log Out</a>
            <?php else: ?>
                <a href="login.php">Log In</a>
                <a class="nav-cta" href="register.php">Create Account</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container main-content">
    <?php if ($flashMessage): ?>
        <div class="alert alert-<?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div>
    <?php endif; ?>
