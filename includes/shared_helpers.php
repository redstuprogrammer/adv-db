<?php

// Shared global helper wrappers for session-managed users.
// This file is the canonical source for wrapper functions used by session-based pages.

if (!function_exists('getCurrentUserId')) {
    function getCurrentUserId(): ?int {
        return SessionManager::getInstance()->getUserId();
    }
}

if (!function_exists('getCurrentUsername')) {
    function getCurrentUsername(): ?string {
        return SessionManager::getInstance()->getUsername();
    }
}

if (!function_exists('getCurrentRole')) {
    function getCurrentRole(): ?string {
        return SessionManager::getInstance()->getRole();
    }
}

if (!function_exists('requireSuperAdminLogin')) {
    function requireSuperAdminLogin(): void {
        SessionManager::getInstance()->requireSuperAdmin();
    }
}
