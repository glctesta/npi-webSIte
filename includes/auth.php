<?php
require_once __DIR__ . '/db.php';

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_auth() {
    if (!is_logged_in()) {
        header('Location: /auth/login.php');
        exit;
    }
}

function get_logged_user() {
    if (!is_logged_in()) return null;
    return db_fetch('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);
}

function login_user($user) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);   // prevent session fixation
    }
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $_SESSION['user_email'] = $user['email'];
    db_query('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
}

function logout_user() {
    session_destroy();
}
