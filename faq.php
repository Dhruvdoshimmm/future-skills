<?php $pageTitle = 'FAQ'; require __DIR__ . '/header.php'; ?>

<section class="page-hero">
    <div class="container">
        <h1><?= e(c('faq.title')) ?></h1>
        <p><?= e(c('faq.subtitle')) ?></p>
    </div>
</section>

<section class="section">
    <div class="container narrow">
        <div class="accordion">
            <?php foreach (c_list('faq.items') as $i => $item): ?>
                <div class="acc-item">
                    <button class="acc-head" aria-expanded="false" aria-controls="acc-<?= $i ?>">
                        <span><?= e($item['q'] ?? '') ?></span><i class="fa-solid fa-chevron-down"></i>
                    </button>
                    <div class="acc-body" id="acc-<?= $i ?>" hidden><p><?= e($item['a'] ?? '') ?></p></div>
                </div>
            <?php endforeach; ?>
        </div>
        <p class="center-note">Still have questions? <a href="contact.php">Contact us</a>.</p>
    </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>
