<?php
require_once __DIR__ . '/config.php';

function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            error_log("GTMC-NPI DB connection failed: " . $e->getMessage());
            http_response_code(503);
            die('<div style="text-align:center;padding:4rem;font-family:sans-serif;color:#e2e8f0;background:#0f172a;min-height:100vh;display:flex;align-items:center;justify-content:center;"><div><h2 style="color:#f87171;">Service Temporarily Unavailable</h2><p style="color:#94a3b8;margin-top:1rem;">We are experiencing technical difficulties. Please try again later.</p></div></div>');
        }
    }
    return $pdo;
}

function db_query($sql, $params = []) {
    $stmt = get_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_fetch($sql, $params = []) {
    return db_query($sql, $params)->fetch();
}

function db_fetch_all($sql, $params = []) {
    return db_query($sql, $params)->fetchAll();
}

function db_insert($sql, $params = []) {
    db_query($sql, $params);
    return get_db()->lastInsertId();
}
