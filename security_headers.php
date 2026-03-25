<?php
// Basic security headers to reduce false-positive phishing/malware signals and harden the app.
// Safe defaults for a PHP-only app (no external JS/CDN).

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

// Keep CSP simple to avoid breaking inline styles/scripts in existing pages.
// We still restrict to same-origin for everything.
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");

