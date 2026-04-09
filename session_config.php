<?php
/**
 * Shared session configuration.
 * Must be included BEFORE session_start() on every page.
 * Ensures consistent cookie params and lifetime across the entire app.
 */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 86400 * 7); // 7 days
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'samesite' => 'Lax',
        'httponly' => true,
    ]);
    session_start();
}
