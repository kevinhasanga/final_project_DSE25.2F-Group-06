<?php
// Start the session once so login details can be used on other pages.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function getDashboard($role)
{
    if ($role == "Admin") {
        return "../system_administrator/system_administrator_dashboard.php";
    } elseif ($role == "CEO") {
        return "../ceo_head_manager/ceo_dashboard.php";
    } elseif ($role == "Inventory Manager") {
        return "../inventory_manager/inventory_manager_dashboard.php";
    } elseif ($role == "Order Processing Officer") {
        return "../orde_%20processing_officer/order_processing_officer_dashboard.php";
    } elseif ($role == "Customer Relationship Officer") {
        return "../customer_relation_manager/customer_relationship_officer_dashboard.php";
    } elseif ($role == "Distribution Manager") {
        return "../distribution_manager/distribution_manager_dashboard.php";
    } elseif ($role == "Driver") {
        return "../driver/driver_dashboard.php";
    } elseif ($role == "Supervisor") {
        return "../supervisor/supervisor_dashboard.php";
    } elseif ($role == "Finance Officer") {
        return "../finance_officer/finance_officer_dashboard.php";
    }

    return "";
}

function require_login($requiredRole)
{
    if (
        !isset($_SESSION["user_id"]) ||
        !isset($_SESSION["role"]) ||
        $_SESSION["role"] != $requiredRole
    ) {
        header("Location: ../login/login.php");
        exit();
    }
}
