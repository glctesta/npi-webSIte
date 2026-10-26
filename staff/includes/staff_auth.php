<?php
require_once __DIR__ . '/../../includes/db.php';

function is_staff_logged_in() {
    return isset($_SESSION['staff_id']);
}

function require_staff_auth() {
    if (!is_staff_logged_in()) {
        header('Location: /staff/login.php');
        exit;
    }
}

function require_staff_role($roles) {
    require_staff_auth();
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    if (!isset($_SESSION['staff_role']) || !in_array($_SESSION['staff_role'], $roles)) {
        // Forbidden
        http_response_code(403);
        die('Forbidden: Insufficient role.');
    }
}

function get_staff_user() {
    if (!is_staff_logged_in()) return null;
    return db_fetch('SELECT * FROM staff WHERE id = ?', [$_SESSION['staff_id']]);
}

function login_staff($user) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);   // prevent session fixation
    }
    $_SESSION['staff_id'] = $user['id'];
    $_SESSION['staff_name'] = $user['full_name'];
    $_SESSION['staff_role'] = $user['role'];
    db_query('UPDATE staff SET last_login = NOW() WHERE id = ?', [$user['id']]);
}

function logout_staff() {
    unset($_SESSION['staff_id']);
    unset($_SESSION['staff_name']);
    unset($_SESSION['staff_role']);
}
