<?php

// tenant_utils.php
// Shared tenant session/login utilities for multi-tenant isolation.

function getCurrentTenantId(): ?int {
    return isset($_SESSION['tenant_id']) ? (int)$_SESSION['tenant_id'] : null;
}

function getCurrentTenantSlug(): string {
    return isset($_SESSION['tenant_slug']) ? (string)$_SESSION['tenant_slug'] : '';
}

function getCurrentTenantName(): string {
    return isset($_SESSION['tenant_name']) ? (string)$_SESSION['tenant_name'] : '';
}

function tenantIsLoggedIn(): bool {
    return getCurrentTenantId() !== null && getCurrentTenantSlug() !== '';
}

function requireTenantLogin(string $expectedSlug = ''): void {
    $slug = trim((string)($_GET['tenant'] ?? ''));
    $sessionSlug = getCurrentTenantSlug();

    if (!tenantIsLoggedIn() || $slug === '' || $sessionSlug === '' || ($expectedSlug !== '' && $slug !== $expectedSlug) || ($expectedSlug === '' && $slug !== $sessionSlug)) {
        $redirect = 'tenant_login.php?tenant=' . rawurlencode($slug ?: $sessionSlug ?: 'unknown');
        header('Location: ' . $redirect);
        exit;
    }
}

function tenantWhereClause(): string {
    // Simple helper for your queries later
    return 'tenant_id = ?';
}

function getTenantQueryBindings(): array {
    // Useful for building prepared statements in the main app
    return [getCurrentTenantId()];
}

/**
 * Metric Helpers for Dashboard
 * Returns NULL if database error or no tenant context
 */

function getTenantPatientCount(?int $tenantId): ?int {
    if (!$tenantId) return null;
    global $conn;
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM patient WHERE tenant_id = ?');
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'] ?? 0;
}

function getTenantUpcomingAppointmentCount(?int $tenantId): ?int {
    if (!$tenantId) return null;
    global $conn;
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM appointment WHERE tenant_id = ? AND appointment_date >= DATE(NOW())');
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'] ?? 0;
}

function getTenantOutstandingInvoiceCount(?int $tenantId): ?int {
    if (!$tenantId) return null;
    global $conn;
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM payment WHERE tenant_id = ? AND status != "paid"');
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'] ?? 0;
}

function getTenantTodayRevenue(?int $tenantId): ?float {
    if (!$tenantId) return null;
    global $conn;
    $stmt = $conn->prepare('SELECT COALESCE(SUM(amount), 0) as total FROM payment WHERE tenant_id = ? AND DATE(payment_date) = DATE(NOW()) AND status = "paid"');
    $stmt->bind_param('i', $tenantId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return (float)($row['total'] ?? 0);
}
