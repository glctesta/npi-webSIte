<?php
// Shared tab bar for newsletter pages. Expects $nl_tab = 'campaigns' | 'subscribers'
?>
<div style="display:flex; gap:0.5rem; margin-bottom:1.5rem; border-bottom:1px solid var(--border-glass); padding-bottom:0.75rem;">
    <a href="/staff/newsletter-campaigns.php" class="btn btn-sm <?= $nl_tab === 'campaigns' ? 'btn-primary' : 'btn-outline' ?>">✉️ Campaigns</a>
    <a href="/staff/newsletter.php" class="btn btn-sm <?= $nl_tab === 'subscribers' ? 'btn-primary' : 'btn-outline' ?>">👥 Subscribers</a>
</div>
