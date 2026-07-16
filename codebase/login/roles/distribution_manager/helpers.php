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

function getAllDrivers($connection)
{
    $result = mysqli_query(
        $connection,
        "SELECT e.employee_id, e.full_name FROM employee e
         JOIN user_account ua ON ua.user_id = e.user_id
         WHERE ua.role_name = 'driver'
         ORDER BY e.full_name"
    );

    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function getAllVehicles($connection)
{
    $result = mysqli_query($connection, "SELECT vehicle_id, plate_number, vehicle_type FROM vehicle ORDER BY plate_number");

    return mysqli_fetch_all($result, MYSQLI_ASSOC);
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
