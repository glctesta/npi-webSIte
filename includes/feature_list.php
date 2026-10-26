<?php
/**
 * Product modules shown on the home page, /features, footer and sitemap.
 * slug => [icon, i18n section]. Each slug has a tiny page (/<slug>.php)
 * rendered by includes/feature_page.php from the i18n section keys.
 */
function npi_feature_pages() {
    return [
        'projects'     => ['📊', 'fp_projects'],
        'tasks'        => ['✅', 'fp_tasks'],
        'gantt'        => ['🗓️', 'fp_gantt'],
        'readiness'    => ['🚦', 'fp_gates'],
        'documents'    => ['📂', 'fp_documents'],
        'budget'       => ['💶', 'fp_budget'],
        'checklist'    => ['📋', 'fp_checklist'],
        'notifications'=> ['🔔', 'fp_notifications'],
        'change'       => ['🔁', 'fp_change'],
        'iatf'         => ['🛡️', 'fp_iatf'],
        'capacity'     => ['⚖️', 'fp_capacity'],
        'dashboards'   => ['📈', 'fp_dashboards'],
    ];
}
