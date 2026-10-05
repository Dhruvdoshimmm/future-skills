</main>
<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <a href="index.php" class="footer-brand"><span class="brand-icon sm"><i class="fa-solid fa-robot"></i></span> <?= e(c('site.brand')) ?></a>
            <p class="muted"><?= e(c('site.footer_about')) ?></p>
        </div>
        <div>
            <h4>Quick Links</h4>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="scope.php">10-Month Scope</a></li>
                <li><a href="skills.php">Future Ready Skills</a></li>
                <li><a href="faq.php">Partner FAQs</a></li>
                <li><a href="chat.php">Live Chat</a></li>
                <li><a href="contact.php">Contact Us</a></li>
            </ul>
        </div>
        <div>
            <h4>Contact Info</h4>
            <ul class="contact-list">
                <li><i class="fa-solid fa-envelope"></i> <?= e(c('site.email')) ?></li>
                <li><i class="fa-solid fa-phone"></i> <?= e(c('site.phone')) ?></li>
                <li><i class="fa-solid fa-location-dot"></i> <?= e(c('site.address')) ?></li>
            </ul>
        </div>
        <div>
            <h4><?= e(c('site.footer_tech_title')) ?></h4>
            <p class="muted"><?= e(c('site.footer_tech_text')) ?></p>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">&copy; <?= date('Y') ?> <?= e(c('site.brand')) ?> - <?= e(c('site.brand_sub')) ?>. All rights reserved.</div>
    </div>
</footer>
<script src="assets/js/main.js"></script>
</body>
</html>
