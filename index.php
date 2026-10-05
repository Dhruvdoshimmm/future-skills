<?php $pageTitle = 'Home'; require __DIR__ . '/header.php'; ?>

<section class="hero">
    <div class="container hero-grid">
        <div class="hero-text">
            <span class="pill"><i class="fa-solid fa-graduation-cap"></i> <?= e(c('home.badge')) ?></span>
            <h1><?= e(c('home.hero_title')) ?></h1>
            <p class="hero-sub"><?= e(c('home.hero_subtitle')) ?></p>
            <div class="hero-btns">
                <a href="scope.php" class="btn btn-light"><i class="fa-solid fa-book-open"></i> <?= e(c('home.btn_primary')) ?></a>
                <a href="contact.php" class="btn btn-outline"><i class="fa-solid fa-school"></i> <?= e(c('home.btn_secondary')) ?></a>
            </div>
        </div>
        <aside class="vp-card">
            <h2><i class="fa-solid fa-star"></i> <?= e(c('home.card_title')) ?></h2>
            <ul class="check-list">
                <?php foreach (c_list('home.value_props') as $vp): ?>
                    <li><i class="fa-solid fa-circle-check"></i>
                        <span><strong><?= e($vp['title'] ?? '') ?>:</strong> <?= e($vp['text'] ?? '') ?></span></li>
                <?php endforeach; ?>
            </ul>
            <a href="contact.php" class="btn btn-primary btn-block"><?= e(c('home.card_cta')) ?></a>
        </aside>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <h2 class="section-title"><?= e(c('home.why_title')) ?></h2>
        <p class="section-sub"><?= e(c('home.why_subtitle')) ?></p>
        <div class="card-grid">
            <?php foreach (c_list('home.features') as $f): ?>
                <article class="card">
                    <div class="icon-box"><i class="fa-solid <?= e($f['icon'] ?? 'fa-star') ?>"></i></div>
                    <h3><?= e($f['title'] ?? '') ?></h3>
                    <p><?= e($f['text'] ?? '') ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section">
    <div class="container stats">
        <?php foreach (c_list('home.stats') as $s): ?>
            <div class="stat"><span class="stat-num"><?= e($s['num'] ?? '') ?></span><span class="stat-label"><?= e($s['label'] ?? '') ?></span></div>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>
