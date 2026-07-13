<?php
// Start the session once so login details can be used on other pages.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function normalizeRole($role)
{
    $normalizedRole = strtolower(trim($role));
    $normalizedRole = preg_replace('/[^a-z0-9]+/', '_', $normalizedRole);
    $normalizedRole = trim($normalizedRole, '_');

    $aliases = [
        'admin' => 'system_admin',
        'ceo' => 'ceo_head_manager',
        'customer_relationship_officer' => 'customer_relation_manager',
        'customer_ralation_manager' => 'customer_relation_manager',
        'finance_officer' => 'financial_officer',
    ];

    return $aliases[$normalizedRole] ?? $normalizedRole;
}

function getDashboard($role)
{
    $dashboards = [
        'system_admin' => 'roles/system_administrator/system_administrator_dashboard.php',
        'ceo_head_manager' => 'roles/ceo_head_manager/ceo_dashboard.php',
        'inventory_manager' => 'roles/inventory_manager/inventory_manager_dashboard.php',
        'order_processing_officer' => 'roles/order_%20processing_officer/order_processing_officer_dashboard.php',
        'customer_relation_manager' => 'roles/customer_relation_manager/customer_relationship_officer_dashboard.php',
        'distribution_manager' => 'roles/distribution_manager/distribution_manager_dashboard.php',
        'driver' => 'roles/driver/driver_dashboard.php',
        'supervisor' => 'roles/supervisor/supervisor_dashboard.php',
        'financial_officer' => 'roles/finance_officer/finance_officer_dashboard.php',
    ];

    return $dashboards[normalizeRole($role)] ?? "";
}

function require_login($requiredRole)
{
    requireAuthenticated("../../login.php");

    if (
        normalizeRole($_SESSION["role"]) != normalizeRole($requiredRole)
    ) {
        header("Location: ../../login.php");
        exit();
    }
}

function requireAuthenticated($loginUrl = "login.php")
{
    if (!isset($_SESSION["user_id"]) || !isset($_SESSION["role"])) {
        header("Location: " . $loginUrl);
        exit();
    }
}
