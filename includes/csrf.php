<?php
/**
 * CSRF protection for public forms (auth, demo, portal).
 * Staff pages keep using $_SESSION['csrf_token'] (same token).
 */

function csrf_token() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to place inside every POST form. */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

/** True if the submitted token matches the session token. */
function csrf_valid($token = null) {
    $token = $token ?? ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    return !empty($_SESSION['csrf_token']) && is_string($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Abort the request with 400 if the CSRF token is missing or wrong. */
function csrf_require() {
    if (!csrf_valid()) {
        http_response_code(400);
        exit('Invalid or expired form token. Please reload the page and try again.');
    }
}
