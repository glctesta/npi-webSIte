<?php
$_translations = [];
$_current_lang = DEFAULT_LANG;

function init_lang() {
    global $_current_lang, $_translations;
    
    // Priority: GET param > session > cookie > default
    if (isset($_GET['lang']) && in_array($_GET['lang'], LANGUAGES)) {
        $_current_lang = $_GET['lang'];
        $_SESSION['lang'] = $_current_lang;
        setcookie('lang', $_current_lang, time() + 365*24*3600, '/');
    } elseif (isset($_SESSION['lang']) && in_array($_SESSION['lang'], LANGUAGES)) {
        $_current_lang = $_SESSION['lang'];
    } elseif (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], LANGUAGES)) {
        $_current_lang = $_COOKIE['lang'];
    }
    
    // Load translation file
    $file = __DIR__ . '/../i18n/' . $_current_lang . '.json';
    if (file_exists($file)) {
        $_translations = json_decode(file_get_contents($file), true) ?: [];
    }
}

function _t_lookup($translations, $key) {
    $value = $translations;
    foreach (explode('.', $key) as $part) {
        if (is_array($value) && isset($value[$part])) {
            $value = $value[$part];
        } else {
            return null;
        }
    }
    return is_string($value) ? $value : null;
}

function t($key) {
    global $_translations, $_current_lang;
    static $fallback = null;
    $value = _t_lookup($_translations, $key);
    if ($value !== null) return $value;
    // Fallback: English, then the key itself
    if ($_current_lang !== 'en') {
        if ($fallback === null) {
            $f = __DIR__ . '/../i18n/en.json';
            $fallback = file_exists($f) ? (json_decode(file_get_contents($f), true) ?: []) : [];
        }
        $value = _t_lookup($fallback, $key);
        if ($value !== null) return $value;
    }
    return $key;
}

function get_lang() {
    global $_current_lang;
    return $_current_lang;
}

init_lang();
