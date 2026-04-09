<?php
/**
 * Session Utilities for Role-Based Multi-Tenant Session Management
 * Supports multiple concurrent sessions in different tabs, including
 * multiple roles under the same tenant slug simultaneously.
 *
 * Session structure:
 *   $_SESSION['superadmin']          — superadmin auth block
 *   $_SESSION['tenant'][$slug:$role] — per-role tenant auth block
 *
 * Context is resolved per-request from the URL (?tenant= + page filename),
 * never from a single shared "current" pointer, so each tab is independent.
 */

class SessionManager {
    private static $instance = null;
    private $currentRole = null;
    private $currentTenantSlug = null;

    private function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->detectCurrentContext();
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // -------------------------------------------------------------------------
    // Internal: build the session key for a given slug + role
    // -------------------------------------------------------------------------
    private static function tenantKey(string $slug, string $role): string {
        return $slug . ':' . strtolower($role);
    }

    // -------------------------------------------------------------------------
    // Internal: detect the role for the current request.
    //
    // Priority order:
    //   1. Superadmin session (no slug needed)
    //   2. URL ?tenant= slug + script filename -> find matching role in session
    //   3. If only one role is active under the given slug, use that
    // -------------------------------------------------------------------------
    private function detectCurrentContext(): void {
        // Resolve tenant slug from URL first — if present, always try to
        // resolve a tenant context regardless of whether superadmin is also
        // logged in. This allows a superadmin to test tenant dashboards in
        // the same browser session without being incorrectly redirected.
        $slug = trim($_GET['tenant'] ?? '');

        // 1. Superadmin — only wins when there is no tenant slug in the URL.
        if ($slug === '' && !empty($_SESSION['superadmin']['authed'])) {
            $this->currentRole = 'superadmin';
            $this->currentTenantSlug = null;
            return;
        }

        // 2. Resolve tenant slug from URL
        if ($slug !== '') {
            $this->currentTenantSlug = $slug;

            // Try to match role from the current script name
            $roleFromScript = $this->inferRoleFromScript();
            if ($roleFromScript) {
                $key = self::tenantKey($slug, $roleFromScript);
                if (!empty($_SESSION['tenant'][$key])) {
                    $this->currentRole = $roleFromScript;
                    return;
                }
            }

            // Script-based inference failed — find whichever role is logged in under this slug
            $matched = $this->findRoleForSlug($slug);
            if ($matched) {
                $this->currentRole = $matched;
                return;
            }

            $this->currentRole = null;
            return;
        }

        // 3. No slug in URL — look for exactly one active tenant session
        //    (handles pages like index.php that don't carry ?tenant=)
        $allKeys = array_keys($_SESSION['tenant'] ?? []);
        if (count($allKeys) === 1) {
            $parts = explode(':', $allKeys[0], 2);
            $this->currentTenantSlug = $parts[0];
            $this->currentRole = $parts[1] ?? null;
            return;
        }

        $this->currentRole = null;
        $this->currentTenantSlug = null;
    }

    /**
     * Infer expected role from the current PHP script filename.
     * dashboard.php -> admin, dentist_dashboard.php -> dentist, etc.
     */
    private function inferRoleFromScript(): ?string {
        $script = strtolower(basename($_SERVER['SCRIPT_FILENAME'] ?? ''));
        $map = [
            'dashboard.php'              => 'admin',
            'dentist_dashboard.php'      => 'dentist',
            'receptionist_dashboard.php' => 'receptionist',
            'tenant_login.php'           => null,
            'tenant_logout.php'          => null,
        ];

        if (array_key_exists($script, $map)) {
            return $map[$script];
        }

        if (str_starts_with($script, 'dentist_')) {
            return 'dentist';
        }
        if (str_starts_with($script, 'receptionist_')) {
            return 'receptionist';
        }

        return null;
    }

    /**
     * Among all active sessions for a given slug, return the role
     * if exactly one is found — otherwise null (ambiguous).
     */
    private function findRoleForSlug(string $slug): ?string {
        $found = [];
        foreach ($_SESSION['tenant'] ?? [] as $key => $data) {
            if (str_starts_with($key, $slug . ':')) {
                $parts = explode(':', $key, 2);
                $found[] = $parts[1];
            }
        }
        return count($found) === 1 ? $found[0] : null;
    }

    // -------------------------------------------------------------------------
    // Public getters
    // -------------------------------------------------------------------------

    public function getCurrentRole(): ?string {
        return $this->currentRole;
    }

    public function getCurrentTenantSlug(): ?string {
        return $this->currentTenantSlug;
    }

    public function isSuperAdmin(): bool {
        return $this->currentRole === 'superadmin';
    }

    public function isTenantUser(): bool {
        return in_array($this->currentRole, ['admin', 'dentist', 'receptionist'], true);
    }

    public function getSuperAdminData(): ?array {
        return $_SESSION['superadmin'] ?? null;
    }

    public function getTenantData(?string $slug = null): ?array {
        $slug = $slug ?? $this->currentTenantSlug;
        if ($slug === null || $this->currentRole === null) {
            return null;
        }
        $key = self::tenantKey($slug, $this->currentRole);
        return $_SESSION['tenant'][$key] ?? null;
    }

    public function getUserId(): ?int {
        if ($this->isSuperAdmin()) {
            return $_SESSION['superadmin']['id'] ?? null;
        }
        $data = $this->getTenantData();
        return $data['user_id'] ?? null;
    }

    public function getUsername(): ?string {
        if ($this->isSuperAdmin()) {
            return $_SESSION['superadmin']['username'] ?? null;
        }
        $data = $this->getTenantData();
        return $data['username'] ?? null;
    }

    public function getRole(): ?string {
        return $this->currentRole;
    }

    public function getTenantId(): ?int {
        $data = $this->getTenantData();
        return isset($data['tenant_id']) ? (int)$data['tenant_id'] : null;
    }

    // -------------------------------------------------------------------------
    // Login / Logout
    // -------------------------------------------------------------------------

    public function loginSuperAdmin(int $id, string $username): void {
        $_SESSION['superadmin'] = [
            'authed'     => true,
            'id'         => $id,
            'username'   => $username,
            'login_time' => time()
        ];
        // Backward-compat keys (read by legacy pages only — not used for routing)
        $_SESSION['superadmin_authed']   = true;
        $_SESSION['superadmin_username'] = $username;

        $this->currentRole       = 'superadmin';
        $this->currentTenantSlug = null;
    }

    public function loginTenantUser(string $tenantSlug, array $userData): void {
        if (!isset($_SESSION['tenant'])) {
            $_SESSION['tenant'] = [];
        }

        $role = strtolower($userData['role']);
        $key  = self::tenantKey($tenantSlug, $role);

        $_SESSION['tenant'][$key] = [
            'tenant_id'   => $userData['tenant_id'],
            'tenant_slug' => $tenantSlug,
            'tenant_name' => $userData['tenant_name'] ?? '',
            'role'        => $role,
            'user_id'     => $userData['user_id'],
            'username'    => $userData['username'],
            'email'       => $userData['email'],
            'login_time'  => time()
        ];

        $this->currentTenantSlug = $tenantSlug;
        $this->currentRole       = $role;

        // NOTE: We intentionally do NOT write $_SESSION['role'] or
        // $_SESSION['tenant_slug_current'] — those flat keys caused the
        // multi-tab collision and are no longer used for routing decisions.
    }

    public function logoutSuperAdmin(): void {
        unset($_SESSION['superadmin'], $_SESSION['superadmin_authed'], $_SESSION['superadmin_username']);
        if ($this->currentRole === 'superadmin') {
            $this->currentRole = null;
        }
    }

    public function logoutTenant(?string $tenantSlug = null): void {
        $slug = $tenantSlug ?? $this->currentTenantSlug;
        if (!$slug) {
            return;
        }

        // Remove only this role's entry, leaving other roles' sessions intact
        if ($this->currentRole) {
            $key = self::tenantKey($slug, $this->currentRole);
            unset($_SESSION['tenant'][$key]);
        }

        if ($this->currentTenantSlug === $slug) {
            $this->currentTenantSlug = null;
            $this->currentRole       = null;
        }
    }

    // -------------------------------------------------------------------------
    // Access guards
    // -------------------------------------------------------------------------

    public function requireSuperAdmin(): void {
        if (!$this->isSuperAdmin()) {
            header('Location: superadmin_login.php');
            exit;
        }
    }

    public function requireTenantUser(?string $expectedRole = null): void {
        if (!$this->isTenantUser()) {
            $slug = $this->currentTenantSlug ?: 'unknown';
            header('Location: tenant_login.php?tenant=' . rawurlencode($slug));
            exit;
        }

        if ($expectedRole && strtolower($expectedRole) !== $this->currentRole) {
            $slug = $this->currentTenantSlug ?: 'unknown';
            header('Location: tenant_login.php?tenant=' . rawurlencode($slug));
            exit;
        }
    }

    // -------------------------------------------------------------------------
    // URL helpers
    // -------------------------------------------------------------------------

    public function getDashboardUrl(): ?string {
        if ($this->isSuperAdmin()) {
            return 'superadmin_dash.php';
        }

        if ($this->isTenantUser()) {
            $slug = $this->currentTenantSlug;
            switch ($this->currentRole) {
                case 'admin':
                    return 'dashboard.php?tenant=' . rawurlencode($slug);
                case 'dentist':
                    return 'dentist_dashboard.php?tenant=' . rawurlencode($slug);
                case 'receptionist':
                    return 'receptionist_dashboard.php?tenant=' . rawurlencode($slug);
            }
        }

        return null;
    }
}

// Global functions for backward compatibility
require_once __DIR__ . '/shared_helpers.php';
