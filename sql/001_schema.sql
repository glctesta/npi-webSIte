-- ============================================================
-- GTMC-NPI (gtmc-npi.eu) — MySQL / MariaDB schema
-- Database: r145004gtmc_npi
--
-- Fresh install: import THIS file only. It merges what the Angajati
-- site shipped as 001 + 002 + 011..015 into a single idempotent file:
--   * release targeting (release_plans, version_sort + triggers, is_mandatory)
--   * demo request extras (vat, double opt-in, issued license)
--   * the NPI license model (plans/licenses on max_projects, max_users,
--     max_activations; tiers trial | professional | enterprise)
--
-- The license tables keep the same protocol shape as the Angajati site
-- (RSA-signed tokens) so the same server code works for both products.
-- ============================================================

-- NOTE: the database is created via cPanel / hosting panel.
-- USE r145004gtmc_npi;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- TABLE: users — customer accounts (portal)
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(200) NOT NULL,
    `password_hash` VARCHAR(256) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `company` VARCHAR(200) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `vat_id` VARCHAR(50) DEFAULT NULL,
    `billing_address` TEXT DEFAULT NULL,
    `email_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `verification_token` VARCHAR(100) DEFAULT NULL,
    `verification_token_expiry` DATETIME DEFAULT NULL,
    `reset_token` VARCHAR(100) DEFAULT NULL,
    `reset_token_expiry` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `last_login` DATETIME DEFAULT NULL,

    UNIQUE KEY `uq_users_email` (`email`),
    INDEX `ix_users_verification_token` (`verification_token`),
    INDEX `ix_users_reset_token` (`reset_token`),
    INDEX `ix_users_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: products
-- ============================================================
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(50) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,

    UNIQUE KEY `uq_products_slug` (`slug`),
    INDEX `ix_products_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: plans — slug = license tier sent to the app (payload.level)
-- ============================================================
CREATE TABLE IF NOT EXISTS `plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `slug` VARCHAR(50) NOT NULL COMMENT 'trial | professional | enterprise',
    `name` VARCHAR(100) NOT NULL,
    `price_eur` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `billing_period` VARCHAR(20) NOT NULL DEFAULT 'year',
    `max_projects` INT DEFAULT NULL COMMENT 'Progetti NPI attivi; NULL = illimitati',
    `max_users` INT DEFAULT NULL COMMENT 'Utenti dell''app; NULL = illimitati',
    `max_activations` INT DEFAULT NULL COMMENT 'Installazioni (server) incluse nel piano',
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `features` TEXT DEFAULT NULL,

    UNIQUE KEY `uq_plans_slug` (`slug`),
    INDEX `ix_plans_product` (`product_id`),
    INDEX `ix_plans_active_sort` (`is_active`, `sort_order`),

    CONSTRAINT `fk_plans_product` FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: licenses
-- tier -> plans.slug, valid_until -> expires_at
-- ============================================================
CREATE TABLE IF NOT EXISTS `licenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `plan_id` INT NOT NULL,
    `license_key` VARCHAR(40) DEFAULT NULL COMMENT 'NPI-XXXXX-XXXXX-XXXXX-XXXXX',
    `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active | suspended | revoked | expired',
    `company_name` VARCHAR(200) DEFAULT NULL COMMENT 'Shown in the app (payload.company); falls back to users.company',
    `starts_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME DEFAULT NULL COMMENT 'NULL = no expiry (payload valid_until 2099-12-31)',
    `grace_days` INT NOT NULL DEFAULT 30 COMMENT 'Days of read-only tolerance after expiry before lock',
    `grace_expires_at` DATETIME DEFAULT NULL,
    `max_activations` INT NOT NULL DEFAULT 1 COMMENT 'Installations (server fingerprints) allowed',
    `max_projects` INT DEFAULT NULL COMMENT 'Progetti NPI attivi; NULL = tier default / illimitato',
    `max_users` INT DEFAULT NULL COMMENT 'NULL = tier default',
    `extra_features` TEXT DEFAULT NULL COMMENT 'JSON array of add-ons, e.g. ["iatf_profile","client_bridge"]',
    `notes` TEXT DEFAULT NULL,
    `last_check` DATETIME DEFAULT NULL,
    `last_check_ip` VARCHAR(45) DEFAULT NULL,
    `app_version` VARCHAR(20) DEFAULT NULL,
    `reported_projects` INT DEFAULT NULL,
    `reported_users` INT DEFAULT NULL,
    `stripe_subscription_id` VARCHAR(255) DEFAULT NULL,
    `paypal_order_id` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_licenses_key` (`license_key`),
    INDEX `ix_licenses_user` (`user_id`),
    INDEX `ix_licenses_plan` (`plan_id`),
    INDEX `ix_licenses_status` (`status`),
    INDEX `ix_licenses_expires` (`expires_at`),

    CONSTRAINT `fk_licenses_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_licenses_plan` FOREIGN KEY (`plan_id`)
        REFERENCES `plans` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: license_activations — one row per installation fingerprint
-- machine_id = SHA-256 fingerprint (64 hex) sent by the app
-- is_active = 0 -> installation blocked by the vendor (error machine_deactivated)
-- ============================================================
CREATE TABLE IF NOT EXISTS `license_activations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `license_id` INT NOT NULL,
    `machine_id` VARCHAR(255) NOT NULL,
    `machine_name` VARCHAR(200) DEFAULT NULL,
    `activated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_seen` DATETIME DEFAULT NULL,
    `last_ip` VARCHAR(45) DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,

    UNIQUE KEY `uq_activations_license_machine` (`license_id`, `machine_id`),
    INDEX `ix_activations_machine` (`machine_id`),
    INDEX `ix_activations_last_seen` (`last_seen`),

    CONSTRAINT `fk_activations_license` FOREIGN KEY (`license_id`)
        REFERENCES `licenses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: license_audit
-- action: validate / activate / deactivate / rejected:<reason> / admin:<op>
-- ============================================================
CREATE TABLE IF NOT EXISTS `license_audit` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `license_id` INT DEFAULT NULL,
    `license_key` VARCHAR(40) DEFAULT NULL,
    `action` VARCHAR(60) NOT NULL,
    `fingerprint` VARCHAR(64) DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `ix_audit_license` (`license_id`),
    INDEX `ix_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: releases
-- version_sort: sortable key ('1.10.0' -> '0001.0010.0000.0000'),
--               kept up to date by the triggers below.
-- UNIQUE(version, is_update): the same version can exist once as a full
--               installer and once as an update package.
-- is_mandatory: 1 = patch the customer should not skip.
-- ============================================================
CREATE TABLE IF NOT EXISTS `releases` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `version` VARCHAR(20) NOT NULL,
    `version_sort` VARCHAR(60) DEFAULT NULL,
    `release_date` DATE DEFAULT NULL,
    `changelog` TEXT DEFAULT NULL,
    `file_path` VARCHAR(500) DEFAULT NULL,
    `file_size` VARCHAR(50) DEFAULT NULL,
    `min_requirements` TEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_update` TINYINT(1) NOT NULL DEFAULT 0,
    `is_mandatory` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_releases_versione_tipo` (`version`, `is_update`),
    INDEX `ix_releases_active` (`is_active`),
    INDEX `ix_releases_update` (`is_update`),
    INDEX `ix_releases_version_sort` (`version_sort`),
    INDEX `ix_releases_date` (`release_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP FUNCTION IF EXISTS `fn_version_sort`;
DELIMITER $$
CREATE FUNCTION `fn_version_sort`(v VARCHAR(20))
RETURNS VARCHAR(60)
DETERMINISTIC
NO SQL
BEGIN
    -- Quattro pezzi, ciascuno riempito di zeri a sinistra: '1.10.0' diventa
    -- '0001.0010.0000.0000', che si ordina correttamente come stringa.
    DECLARE p1, p2, p3, p4 INT;
    SET p1 = CAST(SUBSTRING_INDEX(CONCAT(v, '.0.0.0'), '.', 1) AS UNSIGNED);
    SET p2 = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(CONCAT(v, '.0.0.0'), '.', 2), '.', -1) AS UNSIGNED);
    SET p3 = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(CONCAT(v, '.0.0.0'), '.', 3), '.', -1) AS UNSIGNED);
    SET p4 = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(CONCAT(v, '.0.0.0'), '.', 4), '.', -1) AS UNSIGNED);
    RETURN CONCAT(LPAD(p1, 4, '0'), '.', LPAD(p2, 4, '0'), '.',
                  LPAD(p3, 4, '0'), '.', LPAD(p4, 4, '0'));
END$$

DROP TRIGGER IF EXISTS `trg_releases_version_sort_ins`$$
CREATE TRIGGER `trg_releases_version_sort_ins`
BEFORE INSERT ON `releases`
FOR EACH ROW
BEGIN
    SET NEW.version_sort = fn_version_sort(NEW.version);
END$$

DROP TRIGGER IF EXISTS `trg_releases_version_sort_upd`$$
CREATE TRIGGER `trg_releases_version_sort_upd`
BEFORE UPDATE ON `releases`
FOR EACH ROW
BEGIN
    SET NEW.version_sort = fn_version_sort(NEW.version);
END$$
DELIMITER ;

-- ============================================================
-- TABLE: release_plans — release targeting. A release WITHOUT rows here
-- is visible to every plan; rows restrict it to those plans.
-- ============================================================
CREATE TABLE IF NOT EXISTS `release_plans` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `release_id` INT NOT NULL,
    `plan_id`    INT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_release_plan` (`release_id`, `plan_id`),
    INDEX `ix_release_plans_release` (`release_id`),
    INDEX `ix_release_plans_plan` (`plan_id`),

    CONSTRAINT `fk_release_plans_release` FOREIGN KEY (`release_id`)
        REFERENCES `releases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_release_plans_plan` FOREIGN KEY (`plan_id`)
        REFERENCES `plans` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: downloads
-- ============================================================
CREATE TABLE IF NOT EXISTS `downloads` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `license_id` INT DEFAULT NULL,
    `release_id` INT DEFAULT NULL,
    `installation_key` VARCHAR(255) DEFAULT NULL,
    `downloaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45) DEFAULT NULL,

    INDEX `ix_downloads_user` (`user_id`),
    INDEX `ix_downloads_license` (`license_id`),
    INDEX `ix_downloads_release` (`release_id`),
    INDEX `ix_downloads_date` (`downloaded_at`),

    CONSTRAINT `fk_downloads_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_downloads_license` FOREIGN KEY (`license_id`)
        REFERENCES `licenses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_downloads_release` FOREIGN KEY (`release_id`)
        REFERENCES `releases` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: installation_keys — extra NPI-… keys bound to a license
-- (accepted by license_validate exactly like the license key)
-- ============================================================
CREATE TABLE IF NOT EXISTS `installation_keys` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `license_id` INT NOT NULL,
    `release_id` INT DEFAULT NULL,
    `key_value` VARCHAR(255) NOT NULL,
    `used_at` DATETIME DEFAULT NULL,
    `offline_code` VARCHAR(8) DEFAULT NULL,
    `machine_id_pending` VARCHAR(64) DEFAULT NULL,
    `offline_code_expires` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_installation_keys_value` (`key_value`),
    INDEX `ix_installation_keys_license` (`license_id`),
    INDEX `ix_installation_keys_release` (`release_id`),

    CONSTRAINT `fk_install_keys_license` FOREIGN KEY (`license_id`)
        REFERENCES `licenses` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_install_keys_release` FOREIGN KEY (`release_id`)
        REFERENCES `releases` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: api_tokens (256-bit) — client ↔ server tokens
-- ============================================================
CREATE TABLE IF NOT EXISTS `api_tokens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `license_id` INT NOT NULL,
    `token` VARCHAR(128) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_used` DATETIME NULL,
    UNIQUE KEY `uq_api_tokens_token` (`token`),
    INDEX `ix_api_tokens_license` (`license_id`),
    INDEX `ix_api_tokens_active` (`is_active`, `expires_at`),
    CONSTRAINT `fk_api_tokens_license` FOREIGN KEY (`license_id`)
        REFERENCES `licenses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: license_requests — upgrade / renew requests sent from the app
-- ============================================================
CREATE TABLE IF NOT EXISTS `license_requests` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `license_id`    INT NULL,
    `user_email`    VARCHAR(200) NOT NULL,
    `machine_id`    VARCHAR(255) NOT NULL,
    `hostname`      VARCHAR(200) NULL,
    `current_plan`  VARCHAR(50) NULL,
    `request_type`  ENUM('upgrade','renew','add_seats','support') NOT NULL,
    `desired_plan`  VARCHAR(50) NULL,
    `message`       TEXT NULL,
    `status`        ENUM('pending','in_progress','completed','rejected') NOT NULL DEFAULT 'pending',
    `staff_notes`   TEXT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_lr_status` (`status`),
    INDEX `idx_lr_license` (`license_id`),
    INDEX `idx_lr_email` (`user_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: demo_requests — double opt-in; on confirmation the trial
-- NPI-… key is issued (fullname / license_id) exactly like the Angajati flow.
-- ============================================================
CREATE TABLE IF NOT EXISTS `demo_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `email` VARCHAR(200) NOT NULL,
    `fullname` VARCHAR(200) DEFAULT NULL,
    `company` VARCHAR(200) DEFAULT NULL,
    `vat` VARCHAR(50) DEFAULT NULL COMMENT 'CUI / partita IVA della società richiedente',
    `phone` VARCHAR(50) DEFAULT NULL,
    `message` TEXT DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending | contacted | completed | cancelled',
    `email_verified` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 after the confirmation link click (double opt-in)',
    `verify_token` VARCHAR(64) DEFAULT NULL,
    `verify_token_expiry` DATETIME DEFAULT NULL,
    `license_id` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `ix_demo_email` (`email`),
    INDEX `ix_demo_status` (`status`),
    INDEX `ix_demo_verify_token` (`verify_token`),
    INDEX `ix_demo_created_at` (`created_at`),

    CONSTRAINT `fk_demo_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: invoices / payments
-- ============================================================
CREATE TABLE IF NOT EXISTS `invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `license_id` INT DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'EUR',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending | paid | cancelled | refunded',
    `stripe_invoice_id` VARCHAR(255) DEFAULT NULL,
    `paypal_invoice_id` VARCHAR(255) DEFAULT NULL,
    `paid_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `ix_invoices_user` (`user_id`),
    INDEX `ix_invoices_license` (`license_id`),
    INDEX `ix_invoices_status` (`status`),
    INDEX `ix_invoices_paid_at` (`paid_at`),

    CONSTRAINT `fk_invoices_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_invoices_license` FOREIGN KEY (`license_id`)
        REFERENCES `licenses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `license_id` INT DEFAULT NULL,
    `invoice_id` INT DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'EUR',
    `provider` VARCHAR(20) NOT NULL COMMENT 'stripe | paypal | bank_transfer',
    `provider_transaction_id` VARCHAR(255) DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending | succeeded | failed | refunded',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `ix_payments_user` (`user_id`),
    INDEX `ix_payments_license` (`license_id`),
    INDEX `ix_payments_invoice` (`invoice_id`),
    INDEX `ix_payments_status` (`status`),
    INDEX `ix_payments_provider_tx` (`provider_transaction_id`),

    CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_payments_license` FOREIGN KEY (`license_id`)
        REFERENCES `licenses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`)
        REFERENCES `invoices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: proforma_invoices
-- ============================================================
CREATE TABLE IF NOT EXISTS `proforma_invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `proforma_number` VARCHAR(50) NOT NULL UNIQUE,
    `plan_slug` VARCHAR(50) NOT NULL,
    `company_name` VARCHAR(200) DEFAULT NULL,
    `contact_name` VARCHAR(200) NOT NULL,
    `contact_email` VARCHAR(200) NOT NULL,
    `vat_id` VARCHAR(50) DEFAULT NULL,
    `billing_address` TEXT DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'EUR',
    `status` VARCHAR(20) NOT NULL DEFAULT 'sent' COMMENT 'sent | paid | cancelled',
    `payment_proof_path` VARCHAR(500) DEFAULT NULL,
    `issued_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `paid_at` DATETIME DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `lang` VARCHAR(5) NOT NULL DEFAULT 'en',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `ix_proforma_user` (`user_id`),
    INDEX `ix_proforma_status` (`status`),
    INDEX `ix_proforma_email` (`contact_email`),

    CONSTRAINT `fk_proforma_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: staff — back-office users (create with tools/create_staff.php)
-- ============================================================
CREATE TABLE IF NOT EXISTS `staff` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(200) NOT NULL,
    `password_hash` VARCHAR(256) NOT NULL,
    `full_name` VARCHAR(200) NOT NULL,
    `role` VARCHAR(20) NOT NULL DEFAULT 'admin',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_login` DATETIME DEFAULT NULL,
    `reset_token` VARCHAR(100) DEFAULT NULL,
    `reset_token_expiry` DATETIME DEFAULT NULL,

    UNIQUE KEY `uq_staff_email` (`email`),
    INDEX `ix_staff_active` (`is_active`),
    INDEX `ix_staff_reset_token` (`reset_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: tickets / ticket_messages — support
-- ============================================================
CREATE TABLE IF NOT EXISTS `tickets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_code` VARCHAR(30) UNIQUE NOT NULL,
    `license_id` INT DEFAULT NULL,
    `contact_name` VARCHAR(255) NOT NULL,
    `contact_email` VARCHAR(255) NOT NULL,
    `company` VARCHAR(255) DEFAULT NULL,
    `app_version` VARCHAR(50) DEFAULT NULL,
    `category` VARCHAR(100) DEFAULT NULL,
    `priority` VARCHAR(20) DEFAULT 'normal',
    `status` VARCHAR(20) DEFAULT 'open',
    `subject` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `assigned_staff_id` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `closed_at` DATETIME DEFAULT NULL,
    CONSTRAINT `fk_tickets_staff` FOREIGN KEY (`assigned_staff_id`)
        REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ticket_messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ticket_id` INT NOT NULL,
    `sender_type` VARCHAR(20) NOT NULL,
    `sender_name` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `attachment_path` VARCHAR(255) DEFAULT NULL,
    `attachment_name` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_ticket_messages_ticket` FOREIGN KEY (`ticket_id`)
        REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: site_visits — anonymous visit counters
-- ============================================================
CREATE TABLE IF NOT EXISTS `site_visits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `visitor_hash` VARCHAR(64) NOT NULL COMMENT 'SHA-256 of IP + User-Agent',
    `page_url` VARCHAR(512) NOT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(512) DEFAULT NULL,
    `visited_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `ix_visits_visitor` (`visitor_hash`),
    INDEX `ix_visits_date` (`visited_at`),
    INDEX `ix_visits_page` (`page_url`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- INITIAL DATA
-- ============================================================

INSERT INTO `products` (`id`, `slug`, `name`, `description`, `is_active`)
VALUES (1, 'npi', 'GTMC-NPI', 'NPI and change management on one controlled workflow: projects, waves, tasks, dependencies, interactive Gantt, versioned documents, budget approvals, quality checklists, milestone readiness gates G0-G5, IATF 16949 rule profiles.', 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

-- Plans = license tiers of the GTMC-NPI web app.
-- Prices are seed values: Staff → Pricing edits them on the live site.
INSERT INTO `plans` (`product_id`, `slug`, `name`, `price_eur`, `billing_period`, `max_projects`, `max_users`, `max_activations`, `sort_order`, `is_active`, `features`)
VALUES
(1, 'trial', 'Trial', 0.00, 'trial', 3, 5, 1, 0, 1, '["14-day evaluation","3 active projects, 5 users","1 installation (server)"]'),
(1, 'professional', 'Professional', 990.00, 'year', 25, 15, 3, 20, 1, '["One company, full NPI workflow (projects, waves, tasks, Gantt)","Versioned documents and budget approvals","Quality checklist and milestone readiness gates","Up to 3 installations (servers)"]'),
(1, 'enterprise', 'Enterprise', 1990.00, 'year', NULL, NULL, 5, 30, 1, '["Unlimited projects and users, multi-company","IATF 16949 rule profile, client bridge and NRE","Change management, capacity & costs, KPI post-launch","Up to 5 installations (servers), API access"]')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `price_eur` = VALUES(`price_eur`),
    `billing_period` = VALUES(`billing_period`), `max_projects` = VALUES(`max_projects`),
    `max_users` = VALUES(`max_users`), `max_activations` = VALUES(`max_activations`),
    `features` = VALUES(`features`);

-- First release placeholder (upload the installer in Staff → Releases)
INSERT INTO `releases` (`version`, `release_date`, `changelog`, `file_path`, `file_size`, `min_requirements`, `is_active`, `is_update`)
VALUES (
    '1.0.0',
    CURDATE(),
    'First public release of GTMC-NPI.\n- Projects, waves, tasks and dependencies\n- Interactive Gantt and milestone readiness gates G0-G5\n- Versioned documents with SHA-256\n- Budget requests and approvals\n- Quality checklist, multi-level notifications',
    '/uploads/releases/GTMC-NPI-Setup-v1.0.0.exe',
    NULL,
    'Windows Server 2019+ (IIS reverse proxy) or Linux, Microsoft SQL Server 2019+ (dedicated GTMC_NPI database), Python 3.11+ runtime for the backend service',
    1,
    0
)
ON DUPLICATE KEY UPDATE `changelog` = VALUES(`changelog`), `min_requirements` = VALUES(`min_requirements`);

UPDATE `releases` SET `version_sort` = fn_version_sort(`version`)
WHERE `version_sort` IS NULL OR `version_sort` = '';

-- No default staff account is inserted: run
--   php tools/create_staff.php admin@gtmc-npi.eu "Full Name"
-- on the server (it asks for the password and stores a bcrypt hash).

-- ============================================================
-- VIEWS (phpMyAdmin monitoring)
-- ============================================================

CREATE OR REPLACE VIEW `v_user_stats` AS
SELECT
    COUNT(*) AS total_users,
    SUM(email_verified) AS verified_users,
    SUM(CASE WHEN email_verified = 0 THEN 1 ELSE 0 END) AS pending_users,
    COUNT(DISTINCT vat_id) AS companies_with_vat
FROM `users`;

CREATE OR REPLACE VIEW `v_demo_stats` AS
SELECT
    COUNT(*) AS total_requests,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN status = 'contacted' THEN 1 ELSE 0 END) AS contacted,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed
FROM `demo_requests`;

CREATE OR REPLACE VIEW `v_download_stats` AS
SELECT
    r.version,
    r.file_path,
    COUNT(d.id) AS download_count,
    MAX(d.downloaded_at) AS last_download
FROM `releases` r
LEFT JOIN `downloads` d ON r.id = d.release_id
GROUP BY r.id, r.version, r.file_path
ORDER BY r.release_date DESC;

CREATE OR REPLACE VIEW `v_license_overview` AS
SELECT
    l.id, l.license_key, COALESCE(l.company_name, u.company) AS company,
    p.slug AS tier, l.status, l.expires_at, l.max_activations,
    (SELECT COUNT(*) FROM license_activations a WHERE a.license_id = l.id AND a.is_active = 1) AS active_installations,
    l.last_check, l.app_version, l.reported_projects
FROM `licenses` l
JOIN `plans` p ON p.id = l.plan_id
LEFT JOIN `users` u ON u.id = l.user_id;
