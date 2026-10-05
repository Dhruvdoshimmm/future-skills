<?php $pageTitle = 'Future Skills'; require __DIR__ . '/header.php'; ?>

<section class="page-hero">
    <div class="container">
        <h1><?= e(c('skills.title')) ?></h1>
        <p><?= e(c('skills.subtitle')) ?></p>
    </div>
</section>

<section class="section">
    <div class="container">
        <p class="lead"><?= e(c('skills.intro')) ?></p>
        <div class="shift">
            <div class="shift-box from">
                <i class="fa-solid fa-mobile-screen"></i>
                <h3><?= e(c('skills.shift_from_title')) ?></h3>
                <p><?= e(c('skills.shift_from_text')) ?></p>
            </div>
            <div class="shift-arrow"><i class="fa-solid fa-arrow-right"></i></div>
            <div class="shift-box to">
                <i class="fa-solid fa-screwdriver-wrench"></i>
                <h3><?= e(c('skills.shift_to_title')) ?></h3>
                <p><?= e(c('skills.shift_to_text')) ?></p>
            </div>
        </div>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <h2 class="section-title"><?= e(c('skills.pillars_title')) ?></h2>
        <div class="card-grid two">
            <?php foreach (c_list('skills.pillars') as $p): ?>
                <article class="card">
                    <div class="icon-box"><i class="fa-solid <?= e($p['icon'] ?? 'fa-star') ?>"></i></div>
                    <h3><?= e($p['title'] ?? '') ?></h3>
                    <p><?= e($p['text'] ?? '') ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>
