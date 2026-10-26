    </main>

    <!-- Footer -->
    <footer>
        <div class="footer-grid">
            <div class="footer-col">
                <img src="/static/images/logo.png" alt="GTMC-NPI" style="height:32px; filter: invert(1); margin-bottom:0.5rem;">
                <p><?= t('footer.about_text') ?></p>
            </div>
            <div class="footer-col">
                <h4><?= t('footer.product') ?></h4>
                <ul>
                    <li><a href="/features"><?= t('footer.features') ?></a></li>
                    <?php require_once __DIR__ . '/feature_list.php';
                    foreach (array_slice(npi_feature_pages(), 0, 6, true) as $f_slug => [$f_icon, $f_sec]): ?>
                    <li><a href="/<?= $f_slug ?>"><?= htmlspecialchars(t($f_sec . '.title')) ?></a></li>
                    <?php endforeach; ?>
                    <li><a href="/demo"><?= t('footer.demo') ?></a></li>
                    <li><a href="/pricing"><?= t('footer.pricing') ?></a></li>
                    <li><a href="/blog"><?= t('nav.blog') ?></a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4><?= t('footer.company') ?></h4>
                <ul>
                    <li><a href="/about"><?= t('footer.about') ?></a></li>
                    <li><a href="/security"><?= t('features.security') ?></a></li>
                    <li><a href="mailto:help@gtmc-npi.eu"><?= t('footer.contact') ?> 📧</a></li>
                    <li><a href="/privacy-policy"><?= t('footer.privacy') ?></a></li>
                    <li><a href="/terms-of-use"><?= t('footer.terms') ?></a></li>
                    <li><a href="/cookie-policy"><?= t('footer.cookies') ?></a></li>
                    <li><a href="/staff/login.php" style="opacity: 0.5; font-size: 1.0rem;">🔐 Staff</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4><?= t('footer.newsletter') ?></h4>
                <form id="newsletterForm" novalidate>
                    <div class="form-group">
                        <input type="email" name="email" class="form-control" required maxlength="200" placeholder="<?= t('footer.email_placeholder') ?>" aria-label="<?= htmlspecialchars(t('footer.email_placeholder')) ?>">
                        <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;" aria-hidden="true">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%"><?= t('footer.subscribe') ?></button>
                    <p id="newsletterMsg" role="status" style="margin-top:0.6rem;font-size:0.9rem;min-height:1.2em;"></p>
                </form>
                <script>
                (function () {
                    var form = document.getElementById('newsletterForm');
                    if (!form) return;
                    var msg = document.getElementById('newsletterMsg');
                    var btn = form.querySelector('button');
                    var texts = <?= json_encode([
                        'ok'      => t('footer.subscribe_ok'),
                        'already' => t('footer.subscribe_already'),
                        'invalid' => t('footer.subscribe_invalid'),
                        'error'   => t('footer.subscribe_error'),
                    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
                    function show(code) {
                        msg.textContent = texts[code] || texts.error;
                        msg.style.color = (code === 'ok' || code === 'already') ? '#34d399' : '#f87171';
                    }
                    form.addEventListener('submit', function (e) {
                        e.preventDefault();
                        var email = form.email.value.trim();
                        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { show('invalid'); return; }
                        btn.disabled = true;
                        fetch('/api/newsletter/subscribe.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ email: email, website: form.website.value, lang: '<?= get_lang() ?>' })
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (d) { show(d.code); if (d.ok) form.reset(); })
                        .catch(function () { show('error'); })
                        .finally(function () { btn.disabled = false; });
                    });
                })();
                </script>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; 2026 GTMC. <?= t('footer.rights') ?>
        </div>
    </footer>

    <div class="promo-marquee promo-marquee-bottom" aria-label="<?= htmlspecialchars(t('promo.label')) ?>">
        <div class="promo-marquee-track">
            <span class="promo-marquee-item"><?= htmlspecialchars(t('promo.ticker')) ?></span>
            <span class="promo-marquee-item" aria-hidden="true"><?= htmlspecialchars(t('promo.ticker')) ?></span>
        </div>
    </div>

    <script src="/static/js/main.js"></script>
</body>
</html>
