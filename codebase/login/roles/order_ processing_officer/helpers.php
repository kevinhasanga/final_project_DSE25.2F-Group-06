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

function getAllCustomers($connection)
{
    $result = mysqli_query($connection, "SELECT customer_id, name FROM customer ORDER BY name");

    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function getAllProductsWithStock($connection)
{
    $result = mysqli_query(
        $connection,
        "SELECT p.product_id, p.product_name, p.selling_price,
                COALESCE(SUM(sb.current_quantity), 0) AS current_stock
         FROM product p
         LEFT JOIN stock_batch sb ON sb.product_id = p.product_id AND sb.status = 'active'
         GROUP BY p.product_id, p.product_name, p.selling_price
         ORDER BY p.product_name"
    );

    return mysqli_fetch_all($result, MYSQLI_ASSOC);
}

function recalculateOrderTotals($connection, $orderId)
{
    $statement = mysqli_prepare(
        $connection,
        "SELECT COALESCE(SUM(line_total), 0) FROM order_item WHERE order_id = ?"
    );
    mysqli_stmt_bind_param($statement, "i", $orderId);
    mysqli_stmt_execute($statement);
    $subtotal = (float) mysqli_fetch_row(mysqli_stmt_get_result($statement))[0];
    mysqli_stmt_close($statement);

    $orderStatement = mysqli_prepare($connection, "SELECT discount_rate, tax_rate FROM sales_order WHERE order_id = ?");
    mysqli_stmt_bind_param($orderStatement, "i", $orderId);
    mysqli_stmt_execute($orderStatement);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($orderStatement));
    mysqli_stmt_close($orderStatement);

    $discountRate = (float) ($order["discount_rate"] ?? 0);
    $taxRate = (float) ($order["tax_rate"] ?? 0);
    $discountAmount = round($subtotal * $discountRate / 100, 2);
    $taxAmount = round(($subtotal - $discountAmount) * $taxRate / 100, 2);
    $totalAmount = $subtotal - $discountAmount + $taxAmount;

    $updateStatement = mysqli_prepare(
        $connection,
        "UPDATE sales_order SET discount_amount = ?, tax_amount = ?, total_amount = ? WHERE order_id = ?"
    );
    mysqli_stmt_bind_param($updateStatement, "dddi", $discountAmount, $taxAmount, $totalAmount, $orderId);
    mysqli_stmt_execute($updateStatement);
    mysqli_stmt_close($updateStatement);
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
