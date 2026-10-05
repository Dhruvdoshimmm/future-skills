<?php $pageTitle = 'Scope'; require __DIR__ . '/header.php'; ?>

<section class="page-hero">
    <div class="container">
        <h1><?= e(c('scope.title')) ?></h1>
        <p><?= e(c('scope.subtitle')) ?></p>
    </div>
</section>

<section class="section">
    <div class="container">
        <p class="lead"><?= e(c('scope.intro')) ?></p>

        <h2 class="section-title left"><?= e(c('scope.months_title')) ?></h2>
        <ol class="timeline">
            <?php foreach (c_list('scope.months') as $i => $m): ?>
                <li>
                    <span class="tl-num"><?= $i + 1 ?></span>
                    <div><h3><?= e($m['title'] ?? '') ?></h3><p><?= e($m['text'] ?? '') ?></p></div>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <h2 class="section-title"><?= e(c('scope.sessions_title')) ?></h2>
        <div class="card-grid four">
            <?php foreach (c_list('scope.sessions') as $i => $s): ?>
                <article class="card session">
                    <span class="session-no">Session <?= $i + 1 ?></span>
                    <h3><?= e($s['title'] ?? '') ?></h3>
                    <p><?= e($s['text'] ?? '') ?></p>
                </article>
            <?php endforeach; ?>
        </div>
        <p class="center-note">10 months &times; 8 sessions = <strong>80 hands-on sessions</strong> per year.</p>
    </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>
