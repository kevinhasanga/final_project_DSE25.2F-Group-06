<?php

function getCurrentEmployeeId($connection, $userId)
{
    $statement = mysqli_prepare($connection, "SELECT employee_id FROM employee WHERE user_id = ?");
    mysqli_stmt_bind_param($statement, "i", $userId);
    mysqli_stmt_execute($statement);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);

    return $row ? (int) $row["employee_id"] : null;
}

function getDriverDeliveries($connection, $driverId)
{
    $statement = mysqli_prepare(
        $connection,
        "SELECT d.delivery_id, d.order_id, cu.name AS customer_name, cu.address, v.plate_number,
                d.scheduled_date, d.route_details, d.status
         FROM delivery d
         JOIN sales_order so ON so.order_id = d.order_id
         JOIN customer cu ON cu.customer_id = so.customer_id
         JOIN vehicle v ON v.vehicle_id = d.vehicle_id
         WHERE d.driver_id = ?
         ORDER BY d.scheduled_date DESC, d.delivery_id DESC"
    );
    mysqli_stmt_bind_param($statement, "i", $driverId);
    mysqli_stmt_execute($statement);
    $result = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);

    return $result;
}

function setFlash($message)
{
    $_SESSION["flash"] = $message;
}

function popFlash()
{
    $message = $_SESSION["flash"] ?? "";
    unset($_SESSION["flash"]);

    return $message;
}
