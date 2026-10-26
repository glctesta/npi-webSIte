/**
 * GTMC-NPI Cookie Consent Banner
 * GDPR-compliant cookie consent with accept/reject non-essential cookies
 * Include this script on every page: <script src="assets/cookie-consent.js"></script>
 */
(function() {
    'use strict';

    // Check if consent already given
    var consent = getCookie('gtmc_cookie_consent');
    if (consent) return;

    // Create banner
    var banner = document.createElement('div');
    banner.id = 'gtmc-cookie-banner';
    banner.innerHTML = `
        <div class="gtmc-cb-overlay"></div>
        <div class="gtmc-cb-container">
            <div class="gtmc-cb-content">
                <div class="gtmc-cb-icon">🍪</div>
                <div class="gtmc-cb-text">
                    <h3>Cookie Consent</h3>
                    <p>We use cookies to enhance your experience. Essential cookies are always active. 
                       You can choose to accept or reject non-essential cookies.</p>
                    <a href="cookie-policy.php" target="_blank" class="gtmc-cb-link">Read our Cookie Policy</a>
                </div>
            </div>
            <div class="gtmc-cb-options">
                <div class="gtmc-cb-option">
                    <label class="gtmc-cb-toggle">
                        <input type="checkbox" checked disabled>
                        <span class="gtmc-cb-slider disabled"></span>
                    </label>
                    <div>
                        <strong>Essential</strong>
                        <small>Required for the site to work</small>
                    </div>
                </div>
                <div class="gtmc-cb-option">
                    <label class="gtmc-cb-toggle">
                        <input type="checkbox" id="gtmc-cb-functional" checked>
                        <span class="gtmc-cb-slider"></span>
                    </label>
                    <div>
                        <strong>Functional</strong>
                        <small>Language, preferences, customer ID</small>
                    </div>
                </div>
                <div class="gtmc-cb-option">
                    <label class="gtmc-cb-toggle">
                        <input type="checkbox" id="gtmc-cb-analytics">
                        <span class="gtmc-cb-slider"></span>
                    </label>
                    <div>
                        <strong>Analytics</strong>
                        <small>Anonymous usage statistics</small>
                    </div>
                </div>
            </div>
            <div class="gtmc-cb-actions">
                <button class="gtmc-cb-btn gtmc-cb-reject" onclick="gtmcCookieDecision('reject')">Reject Non-Essential</button>
                <button class="gtmc-cb-btn gtmc-cb-accept-selected" onclick="gtmcCookieDecision('selected')">Accept Selected</button>
                <button class="gtmc-cb-btn gtmc-cb-accept-all" onclick="gtmcCookieDecision('all')">Accept All</button>
            </div>
        </div>
    `;

    // Inject styles
    var style = document.createElement('style');
    style.textContent = `
        .gtmc-cb-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); z-index: 99998;
            animation: gtmc-fade-in 0.3s ease;
        }
        .gtmc-cb-container {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: #1e293b;
            border-top: 1px solid rgba(99, 102, 241, 0.3);
            padding: 1.5rem 2rem;
            z-index: 99999;
            animation: gtmc-slide-up 0.4s ease;
            font-family: 'Inter', -apple-system, sans-serif;
            color: #e2e8f0;
        }
        @keyframes gtmc-slide-up {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes gtmc-fade-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .gtmc-cb-content {
            display: flex; align-items: flex-start; gap: 1rem;
            max-width: 900px; margin: 0 auto 1rem;
        }
        .gtmc-cb-icon { font-size: 2rem; flex-shrink: 0; }
        .gtmc-cb-text h3 { margin: 0 0 0.25rem; font-size: 1.1rem; color: #f1f5f9; }
        .gtmc-cb-text p { margin: 0; font-size: 0.85rem; color: #94a3b8; line-height: 1.5; }
        .gtmc-cb-link { color: #818cf8; font-size: 0.8rem; text-decoration: none; }
        .gtmc-cb-link:hover { text-decoration: underline; }

        .gtmc-cb-options {
            display: flex; gap: 2rem; justify-content: center;
            margin: 1rem auto; max-width: 900px;
            flex-wrap: wrap;
        }
        .gtmc-cb-option {
            display: flex; align-items: center; gap: 0.75rem;
        }
        .gtmc-cb-option strong { display: block; font-size: 0.85rem; color: #e2e8f0; }
        .gtmc-cb-option small { font-size: 0.7rem; color: #64748b; }

        /* Toggle switch */
        .gtmc-cb-toggle { position: relative; width: 40px; height: 22px; flex-shrink: 0; }
        .gtmc-cb-toggle input { opacity: 0; width: 0; height: 0; }
        .gtmc-cb-slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: #475569; border-radius: 22px;
            transition: 0.3s;
        }
        .gtmc-cb-slider:before {
            position: absolute; content: ""; height: 16px; width: 16px;
            left: 3px; bottom: 3px; background-color: white; border-radius: 50%;
            transition: 0.3s;
        }
        .gtmc-cb-toggle input:checked + .gtmc-cb-slider { background-color: #6366f1; }
        .gtmc-cb-toggle input:checked + .gtmc-cb-slider:before { transform: translateX(18px); }
        .gtmc-cb-slider.disabled { background-color: #065f46 !important; cursor: default; }

        .gtmc-cb-actions {
            display: flex; gap: 0.75rem; justify-content: center;
            max-width: 900px; margin: 0 auto;
            flex-wrap: wrap;
        }
        .gtmc-cb-btn {
            padding: 0.6rem 1.25rem; border: none; border-radius: 8px;
            font-size: 0.85rem; font-weight: 600; cursor: pointer;
            transition: all 0.2s;
        }
        .gtmc-cb-reject { background: #334155; color: #94a3b8; }
        .gtmc-cb-reject:hover { background: #475569; }
        .gtmc-cb-accept-selected { background: rgba(99, 102, 241, 0.2); color: #a5b4fc; border: 1px solid rgba(99,102,241,0.3); }
        .gtmc-cb-accept-selected:hover { background: rgba(99, 102, 241, 0.3); }
        .gtmc-cb-accept-all { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; }
        .gtmc-cb-accept-all:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99,102,241,0.4); }

        @media (max-width: 600px) {
            .gtmc-cb-options { flex-direction: column; gap: 0.75rem; }
            .gtmc-cb-actions { flex-direction: column; }
            .gtmc-cb-content { flex-direction: column; text-align: center; }
        }
    `;

    document.head.appendChild(style);
    document.body.appendChild(banner);

    // Decision handler
    window.gtmcCookieDecision = function(choice) {
        var functional = document.getElementById('gtmc-cb-functional');
        var analytics = document.getElementById('gtmc-cb-analytics');
        var consent = { essential: true, functional: false, analytics: false };

        if (choice === 'all') {
            consent.functional = true;
            consent.analytics = true;
        } else if (choice === 'selected') {
            consent.functional = functional ? functional.checked : false;
            consent.analytics = analytics ? analytics.checked : false;
        }
        // 'reject' keeps functional & analytics as false

        // Save consent as cookie (365 days)
        var d = new Date();
        d.setTime(d.getTime() + 365 * 24 * 60 * 60 * 1000);
        document.cookie = 'gtmc_cookie_consent=' + JSON.stringify(consent) + 
                          ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';

        // Remove non-consented cookies
        if (!consent.functional) {
            deleteCookie('gtmc_lang');
            deleteCookie('gtmc_customer_id');
            deleteCookie('gtmc_license_tier');
            deleteCookie('gtmc_last_version_check');
        }
        if (!consent.analytics) {
            deleteCookie('gtmc_analytics');
        }

        // Remove banner with animation
        var container = document.querySelector('.gtmc-cb-container');
        var overlay = document.querySelector('.gtmc-cb-overlay');
        if (container) container.style.animation = 'gtmc-slide-up 0.3s ease reverse forwards';
        if (overlay) overlay.style.animation = 'gtmc-fade-in 0.3s ease reverse forwards';
        setTimeout(function() {
            var b = document.getElementById('gtmc-cookie-banner');
            if (b) b.remove();
        }, 300);
    };

    function getCookie(name) {
        var v = document.cookie.match('(^|;) ?' + name + '=([^;]*)(;|$)');
        return v ? decodeURIComponent(v[2]) : null;
    }

    function deleteCookie(name) {
        document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/;';
    }
})();
