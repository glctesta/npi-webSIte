<?php
require_once __DIR__ . '/includes/staff_auth.php';
require_staff_auth();
require_once __DIR__ . '/includes/staff_functions.php';
require_once __DIR__ . '/../includes/newsletter.php';

newsletter_ensure_table();
$id = (int)($_GET['id'] ?? 0);
$c = $id ? db_fetch("SELECT * FROM newsletter_campaigns WHERE id = ?", [$id]) : null;
if ($id && !$c) { flash('Newsletter not found.', 'error'); header('Location: /staff/newsletter-campaigns.php'); exit; }

$langs = newsletter_languages();
$editable = !$c || $c['status'] === 'draft';
$progress = $c ? newsletter_campaign_progress($c['id']) : null;
$can_send = in_array($_SESSION['staff_role'] ?? '', ['admin', 'manager'], true);

// Active subscribers per language, for the audience selector
$counts = ['all' => 0];
foreach (db_fetch_all("SELECT lang, COUNT(*) c FROM newsletter_subscribers WHERE status = 'active' GROUP BY lang") as $r) {
    $counts[$r['lang']] = (int)$r['c'];
    $counts['all'] += (int)$r['c'];
}
$me = get_staff_user();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$page_title = $c ? 'Newsletter: ' . $c['subject'] : 'New newsletter';
include __DIR__ . '/includes/staff_header.php';
?>
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<style>
    .nl-grid { display:grid; grid-template-columns: minmax(0, 1fr) 380px; gap:1.5rem; align-items:start; }
    @media (max-width: 1200px) { .nl-grid { grid-template-columns: 1fr; } }
    .nl-label { display:block; font-size:0.85rem; color:var(--text-muted); margin:0 0 0.35rem; }
    .nl-field { margin-bottom:1.1rem; }
    .nl-hint { font-size:0.8rem; color:var(--text-muted); margin-top:0.3rem; }
    /* Editor looks like the email: white paper, dark text */
    .nl-editor-wrap { background:#fff; border-radius:var(--radius-sm); overflow:hidden; }
    .nl-editor-wrap .ql-toolbar { border:none; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
    .nl-editor-wrap .ql-container { border:none; font-family:'Segoe UI',Arial,sans-serif; font-size:15px; color:#1e293b; }
    .nl-editor-wrap .ql-editor { min-height:420px; line-height:1.65; }
    .nl-editor-wrap .ql-editor img { max-width:100%; height:auto; }
    #htmlSource { width:100%; min-height:470px; font-family:Consolas,monospace; font-size:13px; background:#0b1020; color:#e2e8f0; border:none; padding:1rem; resize:vertical; }
    .nl-mode { display:flex; gap:0.4rem; margin-bottom:0.5rem; }
    .nl-side .glass-card { padding:1.25rem; margin-bottom:1.25rem; }
    .nl-side h3 { font-size:1rem; margin:0 0 0.9rem; }
    .nl-progress { height:10px; background:rgba(255,255,255,0.08); border-radius:99px; overflow:hidden; margin:0.75rem 0; }
    .nl-progress > div { height:100%; width:0; background:var(--accent-gradient); transition:width 0.4s; }
    .nl-preview-frame { width:100%; height:640px; border:none; background:#f1f5f9; border-radius:var(--radius-sm); transition:width 0.3s; display:block; margin:0 auto; }
    .nl-msg { font-size:0.88rem; margin-top:0.6rem; min-height:1.2em; }
    .nl-msg.ok { color:var(--success); } .nl-msg.err { color:var(--error); }
    .w100 { width:100%; }
</style>

<div class="welcome-banner" style="display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
    <div>
        <h1><?= $c ? htmlspecialchars($c['subject']) : 'New newsletter' ?></h1>
        <p>
            <a href="/staff/newsletter-campaigns.php">← All newsletters</a>
            <?php if ($c): ?> · Status: <strong id="statusLabel"><?= ucfirst($c['status']) ?></strong><?php endif; ?>
        </p>
    </div>
</div>

<div class="nl-grid">
    <!-- ── Main column ── -->
    <div>
        <div class="glass-card" style="padding:1.5rem;">
            <div class="nl-field">
                <label class="nl-label" for="subject">Subject *</label>
                <input id="subject" class="form-control" maxlength="255" value="<?= htmlspecialchars($c['subject'] ?? '') ?>" <?= $editable ? '' : 'disabled' ?> placeholder="e.g. New in GTMC-NPI: readiness gates G0-G5">
            </div>
            <div class="nl-field">
                <label class="nl-label" for="preheader">Preview text</label>
                <input id="preheader" class="form-control" maxlength="255" value="<?= htmlspecialchars($c['preheader'] ?? '') ?>" <?= $editable ? '' : 'disabled' ?> placeholder="Short line shown after the subject in the inbox">
                <div class="nl-hint">Optional. Gmail and Outlook show it next to the subject.</div>
            </div>

            <?php if ($editable): ?>
                <div class="nl-mode">
                    <button type="button" class="btn btn-sm btn-primary" id="modeVisual">✏️ Visual</button>
                    <button type="button" class="btn btn-sm btn-outline" id="modeHtml">&lt;/&gt; HTML</button>
                </div>
                <div class="nl-editor-wrap">
                    <div id="editor"></div>
                    <textarea id="htmlSource" spellcheck="false" style="display:none;"></textarea>
                </div>
                <div class="nl-hint">Images: use the 🖼 button to upload (JPG/PNG/GIF/WebP, max 3 MB). Header, footer and unsubscribe link are added automatically.</div>
            <?php else: ?>
                <iframe class="nl-preview-frame" src="/staff/newsletter-preview.php?id=<?= $c['id'] ?>" title="Newsletter"></iframe>
            <?php endif; ?>
        </div>

        <?php if ($editable): ?>
        <div class="glass-card" style="padding:1.5rem; margin-top:1.5rem;" id="previewCard" hidden>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <h3 style="margin:0; font-size:1rem;">👁 Preview</h3>
                <div style="display:flex; gap:0.4rem;">
                    <button type="button" class="btn btn-sm btn-primary" data-width="100%">🖥 Desktop</button>
                    <button type="button" class="btn btn-sm btn-outline" data-width="390px">📱 Mobile</button>
                </div>
            </div>
            <iframe name="previewFrame" id="previewFrame" class="nl-preview-frame" title="Preview"></iframe>
            <form id="previewForm" method="post" action="/staff/newsletter-preview.php" target="previewFrame" hidden>
                <input name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input name="subject"><input name="preheader"><textarea name="body_html"></textarea>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Side column ── -->
    <div class="nl-side">
        <?php if ($editable): ?>
        <div class="glass-card">
            <h3>1 · Audience</h3>
            <select id="target_lang" class="form-control">
                <?php foreach ($langs as $code => $label): ?>
                    <option value="<?= $code ?>" <?= ($c['target_lang'] ?? 'all') === $code ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?> (<?= $counts[$code] ?? 0 ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="nl-hint">Language subscribers chose on the site when they signed up.</div>
        </div>

        <div class="glass-card">
            <h3>2 · Save &amp; check</h3>
            <button type="button" class="btn btn-outline w100" id="btnSave">💾 Save draft</button>
            <button type="button" class="btn btn-outline w100" id="btnPreview" style="margin-top:0.5rem;">👁 Preview</button>
            <div style="margin-top:1rem;">
                <label class="nl-label" for="testEmail">Send a test to</label>
                <input id="testEmail" type="email" class="form-control" value="<?= htmlspecialchars($me['email'] ?? '') ?>">
                <button type="button" class="btn btn-outline w100" id="btnTest" style="margin-top:0.5rem;">📧 Send test</button>
            </div>
            <div class="nl-msg" id="msgDraft" role="status"></div>
        </div>
        <?php endif; ?>

        <div class="glass-card">
            <h3><?= $editable ? '3 · Send' : 'Delivery' ?></h3>
            <?php if (!$can_send): ?>
                <p class="nl-hint">Only admin or manager users can send newsletters.</p>
            <?php endif; ?>

            <div id="sendPanel" <?= ($c && !$editable) ? '' : 'hidden' ?>>
                <div class="nl-progress"><div id="progressBar"></div></div>
                <div id="progressText" style="font-size:0.9rem;"></div>
            </div>

            <?php if ($editable && $can_send): ?>
                <button type="button" class="btn btn-primary w100" id="btnSend">🚀 Send to <span id="sendCount"><?= $counts[$c['target_lang'] ?? 'all'] ?? 0 ?></span> subscribers</button>
            <?php endif; ?>
            <?php if ($can_send): ?>
                <button type="button" class="btn btn-primary w100" id="btnResume" hidden>▶ Resume sending</button>
                <button type="button" class="btn btn-outline w100" id="btnPause" hidden>⏸ Pause</button>
                <button type="button" class="btn btn-outline w100" id="btnRetry" hidden style="margin-top:0.5rem;">🔁 Retry failed</button>
            <?php endif; ?>
            <div class="nl-msg" id="msgSend" role="status"></div>
            <p class="nl-hint" style="margin-top:0.8rem;">Emails go out in small batches. Keep this page open while sending; if you close it, press “Resume” later and it continues where it stopped, without duplicates.</p>
        </div>
    </div>
</div>

<?php if ($editable): ?>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<?php endif; ?>
<script>
(function () {
    var CSRF = <?= json_encode($_SESSION['csrf_token']) ?>;
    var campaignId = <?= (int)($c['id'] ?? 0) ?>;
    var editable = <?= $editable ? 'true' : 'false' ?>;
    var counts = <?= json_encode($counts) ?>;
    var initialProgress = <?= json_encode($progress) ?>;
    var status = <?= json_encode($c['status'] ?? 'draft') ?>;
    var $ = function (id) { return document.getElementById(id); };

    function api(payload) {
        payload.csrf = CSRF;
        return fetch('/staff/newsletter-api.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
        }).then(function (r) { return r.json(); });
    }
    function msg(el, text, ok) { el.textContent = text; el.className = 'nl-msg ' + (ok ? 'ok' : 'err'); }

    // ── Editor ────────────────────────────────────────────────────────
    var quill = null, htmlMode = false, dirty = false;
    if (editable) {
        quill = new Quill('#editor', {
            theme: 'snow',
            placeholder: 'Write your newsletter…',
            modules: { toolbar: {
                container: [[{ header: [1, 2, 3, false] }], ['bold', 'italic', 'underline'], [{ color: [] }],
                            [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote', 'link', 'image'], ['clean']],
                handlers: { image: uploadImage }
            } }
        });
        var initial = <?= json_encode($c['body_html'] ?? '') ?>;
        if (initial) quill.clipboard.dangerouslyPasteHTML(initial);
        quill.on('text-change', function () { dirty = true; });
        ['subject', 'preheader', 'target_lang', 'htmlSource'].forEach(function (id) {
            $(id).addEventListener('input', function () { dirty = true; });
        });
        window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

        $('modeHtml').onclick = function () {
            if (htmlMode) return;
            $('htmlSource').value = editorHtml();
            $('editor').style.display = 'none';
            document.querySelector('.ql-toolbar').style.display = 'none';
            $('htmlSource').style.display = 'block';
            htmlMode = true; toggleModeButtons();
        };
        $('modeVisual').onclick = function () {
            if (!htmlMode) return;
            if (!confirm('Switching to the visual editor may remove custom HTML styling. Continue?')) return;
            quill.setContents([]);
            quill.clipboard.dangerouslyPasteHTML($('htmlSource').value);
            $('htmlSource').style.display = 'none';
            $('editor').style.display = '';
            document.querySelector('.ql-toolbar').style.display = '';
            htmlMode = false; toggleModeButtons();
        };
        $('target_lang').onchange = function () { $('sendCount') && ($('sendCount').textContent = counts[this.value] || 0); };
    }
    function toggleModeButtons() {
        $('modeHtml').className = 'btn btn-sm ' + (htmlMode ? 'btn-primary' : 'btn-outline');
        $('modeVisual').className = 'btn btn-sm ' + (htmlMode ? 'btn-outline' : 'btn-primary');
    }
    function editorHtml() {
        if (htmlMode) return $('htmlSource').value;
        if (quill.getText().trim() === '' && !quill.root.querySelector('img')) return '';
        // getSemanticHTML gives real <ul>/<ol>; Quill 2.0.3 turns spaces into &nbsp; which breaks line wrapping in email
        return quill.getSemanticHTML().replace(/&nbsp;/g, ' ');
    }
    function uploadImage() {
        var input = document.createElement('input');
        input.type = 'file'; input.accept = 'image/png,image/jpeg,image/gif,image/webp';
        input.onchange = function () {
            if (!input.files[0]) return;
            var fd = new FormData(); fd.append('image', input.files[0]); fd.append('csrf', CSRF);
            msg($('msgDraft'), 'Uploading image…', true);
            fetch('/staff/newsletter-upload.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d.ok) { msg($('msgDraft'), d.error || 'Upload failed', false); return; }
                    var range = quill.getSelection(true);
                    quill.insertEmbed(range ? range.index : quill.getLength(), 'image', d.url, 'user');
                    msg($('msgDraft'), 'Image inserted.', true);
                }).catch(function () { msg($('msgDraft'), 'Upload failed', false); });
        };
        input.click();
    }

    function save() {
        return api({ action: 'save', id: campaignId, subject: $('subject').value, preheader: $('preheader').value,
                     target_lang: $('target_lang').value, body_html: editorHtml() })
            .then(function (d) {
                if (!d.ok) throw new Error(d.error || 'Save failed');
                if (!campaignId) { campaignId = d.id; history.replaceState(null, '', '?id=' + d.id); }
                dirty = false; return d;
            });
    }

    if (editable) {
        $('btnSave').onclick = function () {
            save().then(function () { msg($('msgDraft'), 'Draft saved ✓', true); })
                  .catch(function (e) { msg($('msgDraft'), e.message, false); });
        };
        $('btnPreview').onclick = function () {
            var f = $('previewForm');
            f.subject.value = $('subject').value; f.preheader.value = $('preheader').value; f.body_html.value = editorHtml();
            $('previewCard').hidden = false; f.submit();
            $('previewCard').scrollIntoView({ behavior: 'smooth' });
        };
        document.querySelectorAll('[data-width]').forEach(function (b) {
            b.onclick = function () {
                $('previewFrame').style.width = b.dataset.width;
                document.querySelectorAll('[data-width]').forEach(function (x) { x.className = 'btn btn-sm ' + (x === b ? 'btn-primary' : 'btn-outline'); });
            };
        });
        $('btnTest').onclick = function () {
            var btn = this; btn.disabled = true; msg($('msgDraft'), 'Saving and sending test…', true);
            save().then(function () { return api({ action: 'test', id: campaignId, email: $('testEmail').value }); })
                .then(function (d) { d.ok ? msg($('msgDraft'), 'Test sent to ' + $('testEmail').value + ' ✓ (check spam too)', true) : msg($('msgDraft'), d.error, false); })
                .catch(function (e) { msg($('msgDraft'), e.message, false); })
                .finally(function () { btn.disabled = false; });
        };
    }

    // ── Sending ───────────────────────────────────────────────────────
    var running = false;
    function show(id, on) { if ($(id)) $(id).hidden = !on; }
    function renderProgress(p) {
        if (!p || !p.total) return;
        show('sendPanel', true);
        var doneCount = p.sent + p.failed + p.skipped;
        $('progressBar').style.width = Math.round(doneCount / p.total * 100) + '%';
        $('progressText').innerHTML = '<strong>' + p.sent + '</strong> sent of ' + p.total +
            (p.failed ? ' · <span class="text-danger">' + p.failed + ' failed</span>' : '') +
            (p.skipped ? ' · ' + p.skipped + ' skipped (unsubscribed)' : '') +
            ((p.pending + p.processing) ? ' · ' + (p.pending + p.processing) + ' waiting' : '');
        show('btnRetry', !running && p.failed > 0 && (p.pending + p.processing) === 0);
    }
    function setStatus(s) { status = s; if ($('statusLabel')) $('statusLabel').textContent = s.charAt(0).toUpperCase() + s.slice(1); }
    function loop() {
        if (!running) return;
        api({ action: 'batch', id: campaignId, size: 10 }).then(function (d) {
            if (!d.ok) throw new Error(d.error || 'Error');
            renderProgress(d.progress);
            if (d.done) {
                running = false; setStatus('sent'); show('btnPause', false); show('btnResume', false);
                renderProgress(d.progress);
                msg($('msgSend'), d.progress.failed ? 'Finished with some failures. You can retry them.' : 'Newsletter sent ✓', !d.progress.failed);
                return;
            }
            // Whole batch failed: SMTP probably blocked (hosting hourly limit) — pause instead of burning the list
            if (d.batch_sent === 0 && d.batch_failed > 0) {
                pause(); msg($('msgSend'), 'The mail server rejected a full batch (hourly limit?). Paused — retry later.', false);
                return;
            }
            setTimeout(loop, 1500);
        }).catch(function (e) { pause(); msg($('msgSend'), e.message + ' — paused.', false); });
    }
    function start() {
        running = true; show('btnResume', false); show('btnPause', true); show('btnRetry', false);
        msg($('msgSend'), 'Sending… keep this page open.', true); loop();
    }
    function pause() { running = false; show('btnPause', false); show('btnResume', status === 'sending'); }

    if ($('btnSend')) $('btnSend').onclick = function () {
        var n = counts[$('target_lang').value] || 0;
        if (!n) { msg($('msgSend'), 'No active subscribers for this audience.', false); return; }
        if (!confirm('Send this newsletter to ' + n + ' subscribers now? This cannot be undone.')) return;
        var btn = this; btn.disabled = true;
        save().then(function () { return api({ action: 'start', id: campaignId }); })
            .then(function (d) {
                if (!d.ok) throw new Error(d.error);
                dirty = false;
                // Lock the editor: content is now frozen
                location.href = '/staff/newsletter-edit.php?id=' + campaignId + '&autostart=1';
            })
            .catch(function (e) { msg($('msgSend'), e.message, false); btn.disabled = false; });
    };
    if ($('btnPause')) $('btnPause').onclick = function () { pause(); msg($('msgSend'), 'Paused. Press Resume to continue.', true); };
    if ($('btnResume')) $('btnResume').onclick = start;
    if ($('btnRetry')) $('btnRetry').onclick = function () {
        api({ action: 'retry_failed', id: campaignId }).then(function (d) {
            if (!d.ok) { msg($('msgSend'), d.error, false); return; }
            setStatus('sending'); renderProgress(d.progress); start();
        });
    };

    // Initial state for campaigns already started
    if (!editable) {
        renderProgress(initialProgress);
        if (status === 'sending') {
            if (/autostart=1/.test(location.search)) { history.replaceState(null, '', '?id=' + campaignId); start(); }
            else { show('btnResume', true); msg($('msgSend'), 'Sending is paused or was interrupted.', false); }
        }
    }
})();
</script>
<?php include __DIR__ . '/includes/staff_footer.php'; ?>
