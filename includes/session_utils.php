<?php
/**
 * Session Utilities for Role-Based Multi-Tenant Session Management
 * Supports multiple concurrent sessions in different tabs
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

    private function detectCurrentContext(): void {
        // Detect current role and tenant from URL or session
        $this->currentTenantSlug = trim($_GET['tenant'] ?? $_SESSION['tenant_slug_current'] ?? '');

        // If tenant slug is specified in URL and tenant session exists, prioritize tenant context
        if ($this->currentTenantSlug && isset($_SESSION['tenant'][$this->currentTenantSlug])) {
            $tenantSession = $_SESSION['tenant'][$this->currentTenantSlug];
            $this->currentRole = strtolower($tenantSession['role'] ?? '');
            return;
        }

        // Check if superadmin
        if (isset($_SESSION['superadmin']['authed']) && $_SESSION['superadmin']['authed']) {
            $this->currentRole = 'superadmin';
            return;
        }

        // Fallback to tenant context if no URL slug but current session exists
        if (!$this->currentTenantSlug && isset($_SESSION['tenant_slug_current']) && isset($_SESSION['tenant'][$_SESSION['tenant_slug_current']])) {
            $this->currentTenantSlug = $_SESSION['tenant_slug_current'];
            $tenantSession = $_SESSION['tenant'][$this->currentTenantSlug];
            $this->currentRole = strtolower($tenantSession['role'] ?? '');
            return;
        }

        $this->currentRole = null;
    }

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
        return in_array($this->currentRole, ['admin', 'dentist', 'receptionist']);
    }

    public function getSuperAdminData(): ?array {
        return $_SESSION['superadmin'] ?? null;
    }

    public function getTenantData(?string $slug = null): ?array {
        $slug = $slug ?? $this->currentTenantSlug;
        return $_SESSION['tenant'][$slug] ?? null;
    }

    public function getUserId(): ?int {
        if ($this->isSuperAdmin()) {
            return $_SESSION['superadmin']['id'] ?? null;
        } elseif ($this->isTenantUser()) {
            $data = $this->getTenantData();
            return $data['user_id'] ?? null;
        }
        return null;
    }

    public function getUsername(): ?string {
        if ($this->isSuperAdmin()) {
            return $_SESSION['superadmin']['username'] ?? null;
        } elseif ($this->isTenantUser()) {
            $data = $this->getTenantData();
            return $data['username'] ?? null;
        }
        return null;
    }

    public function getRole(): ?string {
        return $this->currentRole;
    }

    public function getTenantId(): ?int {
        if ($this->isTenantUser()) {
            $data = $this->getTenantData();
            return $data['tenant_id'] ?? null;
        }
        return null;
    }

    public function loginSuperAdmin(int $id, string $username): void {
        $_SESSION['superadmin'] = [
            'authed' => true,
            'id' => $id,
            'username' => $username,
            'login_time' => time()
        ];
        // Backward compatibility for legacy pages that still check the old session key
        $_SESSION['superadmin_authed'] = true;
        $_SESSION['role'] = 'superadmin';
        $_SESSION['superadmin_username'] = $username;

        $this->currentRole = 'superadmin';
    }

    public function loginTenantUser(string $tenantSlug, array $userData): void {
        if (!isset($_SESSION['tenant'])) {
            $_SESSION['tenant'] = [];
        }

        $_SESSION['tenant'][$tenantSlug] = [
            'tenant_id' => $userData['tenant_id'],
            'tenant_slug' => $tenantSlug,
            'tenant_name' => $userData['tenant_name'] ?? '',
            'role' => $userData['role'],
            'user_id' => $userData['user_id'],
            'username' => $userData['username'],
            'email' => $userData['email'],
            'login_time' => time()
        ];

        $_SESSION['tenant_slug_current'] = $tenantSlug;
        $this->currentTenantSlug = $tenantSlug;
        $this->currentRole = strtolower($userData['role']);
    }

    public function logoutSuperAdmin(): void {
        unset($_SESSION['superadmin']);
        unset($_SESSION['superadmin_authed']);
        unset($_SESSION['superadmin_username']);
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin') {
            unset($_SESSION['role']);
        }
        if ($this->currentRole === 'superadmin') {
            $this->currentRole = null;
        }
    }

    public function logoutTenant(?string $tenantSlug = null): void {
        $slug = $tenantSlug ?? $this->currentTenantSlug;
        if ($slug && isset($_SESSION['tenant'][$slug])) {
            unset($_SESSION['tenant'][$slug]);
            if ($this->currentTenantSlug === $slug) {
                $this->currentTenantSlug = null;
                $this->currentRole = null;
            }
        }
    }

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

    public function getDashboardUrl(): ?string {
        if ($this->isSuperAdmin()) {
            return 'superadmin_dash.php';
        } elseif ($this->isTenantUser()) {
            $role = $this->currentRole;
            $slug = $this->currentTenantSlug;
            switch ($role) {
                case 'admin':
                    return "dashboard.php?tenant=" . rawurlencode($slug);
                case 'dentist':
                    return "dentist_dashboard.php?tenant=" . rawurlencode($slug);
                case 'receptionist':
                    return "receptionist_dashboard.php?tenant=" . rawurlencode($slug);
            }
        }
        return null;
    }
}

// Global functions for backward compatibility
require_once __DIR__ . '/shared_helpers.php';
?>
