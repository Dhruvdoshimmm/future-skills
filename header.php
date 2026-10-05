<?php
require_once __DIR__ . '/includes/config.php';
content();   // connect to the database before any HTML is sent, so a DB problem shows a clean error page
$pageTitle = $pageTitle ?? c('site.brand');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(c('site.brand')) ?> <?= e(c('site.brand_sub')) ?></title>
    <meta name="description" content="<?= e(c('home.hero_subtitle')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="site-header">
    <div class="container nav-wrap">
        <a href="index.php" class="brand">
            <span class="brand-icon"><i class="fa-solid fa-robot"></i></span>
            <span><strong><?= e(c('site.brand')) ?></strong> <?= e(c('site.brand_sub')) ?></span>
        </a>
        <button class="nav-toggle" aria-label="Toggle menu" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
        <nav class="main-nav" id="main-nav">
            <a href="index.php"<?= nav_active('index.php') ?>>Home</a>
            <a href="scope.php"<?= nav_active('scope.php') ?>>Scope</a>
            <a href="skills.php"<?= nav_active('skills.php') ?>>Future Skills</a>
            <a href="faq.php"<?= nav_active('faq.php') ?>>FAQ</a>
            <a href="chat.php"<?= nav_active('chat.php') ?>>Live Chat</a>
            <a href="contact.php"<?= nav_active('contact.php') ?>>Contact Us</a>
            <?php $hu = current_user(); ?>
            <a href="<?= $hu ? 'account.php' : 'login.php' ?>"<?= nav_active($hu ? 'account.php' : 'login.php') ?>><i class="fa-solid fa-circle-user"></i> <?= $hu ? 'My Account' : 'Login' ?></a>
            <a href="admin.php" class="btn-admin"><i class="fa-solid fa-lock"></i> Admin Panel</a>
        </nav>
    </div>
</header>
<main>
