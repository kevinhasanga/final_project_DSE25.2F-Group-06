<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Order Processing Officer');

$activePage = "orders";
$currentEmployeeId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: sales_orders.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "create") {
        $customerId = (int) $_POST["customer_id"];
        $orderDate = $_POST["order_date"];
        $isCredit = isset($_POST["is_credit"]) ? 1 : 0;
        $discountRate = (float) ($_POST["discount_rate"] ?: 0);
        $taxRate = (float) ($_POST["tax_rate"] ?: 0);

        $statement = mysqli_prepare(
            $connection,
            "INSERT INTO sales_order (customer_id, officer_id, order_date, status, discount_rate, tax_rate, discount_amount, tax_amount, total_amount, is_credit)
             VALUES (?, ?, ?, 'pending', ?, ?, 0, 0, 0, ?)"
        );
        mysqli_stmt_bind_param($statement, "iisddi", $customerId, $currentEmployeeId, $orderDate, $discountRate, $taxRate, $isCredit);
        mysqli_stmt_execute($statement);
        $newOrderId = mysqli_insert_id($connection);
        mysqli_stmt_close($statement);

        for ($i = 1; $i <= 3; $i++) {
            $lineProductId = (int) ($_POST["item_product_$i"] ?? 0);
            $lineQuantity = (int) ($_POST["item_quantity_$i"] ?? 0);

            if ($lineProductId > 0 && $lineQuantity > 0) {
                $priceStatement = mysqli_prepare($connection, "SELECT selling_price FROM product WHERE product_id = ?");
                mysqli_stmt_bind_param($priceStatement, "i", $lineProductId);
                mysqli_stmt_execute($priceStatement);
                $unitPrice = (float) mysqli_fetch_row(mysqli_stmt_get_result($priceStatement))[0];
                mysqli_stmt_close($priceStatement);
                $lineTotal = $unitPrice * $lineQuantity;

                $itemStatement = mysqli_prepare(
                    $connection,
                    "INSERT INTO order_item (order_id, product_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)"
                );
                mysqli_stmt_bind_param($itemStatement, "iiidd", $newOrderId, $lineProductId, $lineQuantity, $unitPrice, $lineTotal);
                mysqli_stmt_execute($itemStatement);
                mysqli_stmt_close($itemStatement);
            }
        }

        recalculateOrderTotals($connection, $newOrderId);
        setFlash("Sales order created.");
        header("Location: sales_orders.php");
        exit();
    }

    if ($action === "update_header") {
        $orderId = (int) $_POST["order_id"];
        $customerId = (int) $_POST["customer_id"];
        $orderDate = $_POST["order_date"];
        $status = $_POST["status"];
        $isCredit = isset($_POST["is_credit"]) ? 1 : 0;
        $discountRate = (float) ($_POST["discount_rate"] ?: 0);
        $taxRate = (float) ($_POST["tax_rate"] ?: 0);

        $statement = mysqli_prepare(
            $connection,
            "UPDATE sales_order SET customer_id = ?, order_date = ?, status = ?, is_credit = ?, discount_rate = ?, tax_rate = ?
             WHERE order_id = ?"
        );
        mysqli_stmt_bind_param($statement, "issiddi", $customerId, $orderDate, $status, $isCredit, $discountRate, $taxRate, $orderId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        recalculateOrderTotals($connection, $orderId);
        setFlash("Order updated.");
        header("Location: sales_orders.php?edit=$orderId");
        exit();
    }

    if ($action === "add_item") {
        $orderId = (int) $_POST["order_id"];
        $productId = (int) $_POST["product_id"];
        $quantity = (int) $_POST["quantity"];

        if ($productId > 0 && $quantity > 0) {
            $priceStatement = mysqli_prepare($connection, "SELECT selling_price FROM product WHERE product_id = ?");
            mysqli_stmt_bind_param($priceStatement, "i", $productId);
            mysqli_stmt_execute($priceStatement);
            $unitPrice = (float) mysqli_fetch_row(mysqli_stmt_get_result($priceStatement))[0];
            mysqli_stmt_close($priceStatement);
            $lineTotal = $unitPrice * $quantity;

            $itemStatement = mysqli_prepare(
                $connection,
                "INSERT INTO order_item (order_id, product_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($itemStatement, "iiidd", $orderId, $productId, $quantity, $unitPrice, $lineTotal);
            mysqli_stmt_execute($itemStatement);
            mysqli_stmt_close($itemStatement);

            recalculateOrderTotals($connection, $orderId);
        }

        setFlash("Item added to order.");
        header("Location: sales_orders.php?edit=$orderId");
        exit();
    }

    if ($action === "remove_item") {
        $orderId = (int) $_POST["order_id"];
        $itemId = (int) $_POST["item_id"];

        $statement = mysqli_prepare($connection, "DELETE FROM order_item WHERE item_id = ?");
        mysqli_stmt_bind_param($statement, "i", $itemId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        recalculateOrderTotals($connection, $orderId);
        setFlash("Item removed from order.");
        header("Location: sales_orders.php?edit=$orderId");
        exit();
    }

    if ($action === "update_status") {
        $orderId = (int) $_POST["order_id"];
        $status = $_POST["status"];
        $statement = mysqli_prepare($connection, "UPDATE sales_order SET status = ? WHERE order_id = ?");
        mysqli_stmt_bind_param($statement, "si", $status, $orderId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Order status updated.");
        header("Location: sales_orders.php");
        exit();
    }

    if ($action === "cancel") {
        $orderId = (int) $_POST["order_id"];
        $statement = mysqli_prepare($connection, "UPDATE sales_order SET status = 'cancelled' WHERE order_id = ?");
        mysqli_stmt_bind_param($statement, "i", $orderId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Order cancelled.");
        header("Location: sales_orders.php");
        exit();
    }

    if ($action === "delete") {
        $orderId = (int) $_POST["order_id"];
        mysqli_query($connection, "DELETE FROM order_item WHERE order_id = $orderId");
        mysqli_query($connection, "DELETE FROM invoice WHERE order_id = $orderId");
        $statement = mysqli_prepare($connection, "DELETE FROM sales_order WHERE order_id = ?");
        mysqli_stmt_bind_param($statement, "i", $orderId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Order deleted.");
        header("Location: sales_orders.php");
        exit();
    }
}

$customers = getAllCustomers($connection);
$products = getAllProductsWithStock($connection);

$editOrder = null;
$editItems = [];
if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];
    $statement = mysqli_prepare(
        $connection,
        "SELECT so.*, c.name AS customer_name FROM sales_order so JOIN customer c ON c.customer_id = so.customer_id WHERE so.order_id = ?"
    );
    mysqli_stmt_bind_param($statement, "i", $editId);
    mysqli_stmt_execute($statement);
    $editOrder = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);

    if ($editOrder) {
        $itemsStatement = mysqli_prepare(
            $connection,
            "SELECT oi.item_id, oi.product_id, p.product_name, oi.quantity, oi.unit_price, oi.line_total
             FROM order_item oi JOIN product p ON p.product_id = oi.product_id
             WHERE oi.order_id = ?"
        );
        mysqli_stmt_bind_param($itemsStatement, "i", $editId);
        mysqli_stmt_execute($itemsStatement);
        $editItems = mysqli_fetch_all(mysqli_stmt_get_result($itemsStatement), MYSQLI_ASSOC);
        mysqli_stmt_close($itemsStatement);
    }
}

$perPage = 10;
$totalOrders = countRows($connection, "SELECT COUNT(*) FROM sales_order");
$totalOrderPages = max(1, (int) ceil($totalOrders / $perPage));
$currentPage = min(getCurrentPage(), $totalOrderPages);
$offset = ($currentPage - 1) * $perPage;

$orders = mysqli_query(
    $connection,
    "SELECT so.order_id, c.name AS customer_name, so.order_date, so.total_amount, so.status, so.is_credit,
            (SELECT COUNT(*) FROM order_item WHERE order_id = so.order_id) AS item_count
     FROM sales_order so
     JOIN customer c ON c.customer_id = so.customer_id
     ORDER BY so.order_date DESC, so.order_id DESC
     LIMIT $perPage OFFSET $offset"
);

$statusOptions = ["pending" => "Pending", "processing" => "Processing", "invoiced" => "Invoiced", "completed" => "Completed", "cancelled" => "Cancelled"];
$statusClasses = ["pending" => "progress", "processing" => "progress", "invoiced" => "resolved", "completed" => "resolved", "cancelled" => "pending"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sales Orders</title>
  <link rel="stylesheet" href="css/opo_style.css">
</head>
<body>
  <header class="topbar"><h1>Order Processing</h1><p>Create, edit, and cancel sales orders; apply discounts and taxes; update status</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Sales Orders</h2><p>Create orders, manage line items, and track status and totals.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <?php if ($editOrder): ?>
      <section class="panel">
        <h3>Edit Order #<?= $editOrder["order_id"] ?> — <?= htmlspecialchars($editOrder["customer_name"]) ?></h3>
        <form method="post" action="sales_orders.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="update_header">
          <input type="hidden" name="order_id" value="<?= $editOrder["order_id"] ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="customerId">Customer</label>
              <select id="customerId" name="customer_id" required>
                <?php foreach ($customers as $customer): ?>
                  <option value="<?= $customer["customer_id"] ?>" <?= $editOrder["customer_id"] == $customer["customer_id"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($customer["name"]) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="orderDate">Order Date</label>
              <input type="date" id="orderDate" name="order_date" value="<?= htmlspecialchars(substr($editOrder["order_date"], 0, 10)) ?>" required>
            </div>
            <div class="form-group">
              <label for="status">Status</label>
              <select id="status" name="status" required>
                <?php foreach ($statusOptions as $value => $label): ?>
                  <option value="<?= $value ?>" <?= $editOrder["status"] === $value ? "selected" : "" ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="discountRate">Discount Rate (%)</label>
              <input type="number" id="discountRate" name="discount_rate" min="0" max="100" step="0.01" value="<?= htmlspecialchars($editOrder["discount_rate"]) ?>">
            </div>
            <div class="form-group">
              <label for="taxRate">Tax Rate (%)</label>
              <input type="number" id="taxRate" name="tax_rate" min="0" max="100" step="0.01" value="<?= htmlspecialchars($editOrder["tax_rate"]) ?>">
            </div>
            <div class="form-group">
              <label for="isCredit">Credit Order</label>
              <select id="isCredit" name="is_credit">
                <option value="0" <?= !$editOrder["is_credit"] ? "selected" : "" ?>>No</option>
                <option value="1" <?= $editOrder["is_credit"] ? "selected" : "" ?>>Yes</option>
              </select>
            </div>
          </div>
          <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">
            Subtotal <?= number_format($editOrder["total_amount"] - $editOrder["tax_amount"] + $editOrder["discount_amount"], 2) ?>,
            Discount <?= number_format($editOrder["discount_amount"], 2) ?>,
            Tax <?= number_format($editOrder["tax_amount"], 2) ?>,
            Total <strong><?= number_format($editOrder["total_amount"], 2) ?></strong>
          </p>
          <div class="button-row">
            <button class="btn" type="submit">Save Order</button>
            <a class="btn secondary" href="sales_orders.php">Back to Orders</a>
          </div>
        </form>
      </section>

      <section class="panel">
        <h3>Order Items</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Product</th><th>Quantity</th><th>Unit Price</th><th>Line Total</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (empty($editItems)): ?>
                <tr><td colspan="5">No items on this order yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($editItems as $item): ?>
                <tr>
                  <td><?= htmlspecialchars($item["product_name"]) ?></td>
                  <td><?= (int) $item["quantity"] ?></td>
                  <td><?= number_format($item["unit_price"], 2) ?></td>
                  <td><?= number_format($item["line_total"], 2) ?></td>
                  <td>
                    <form method="post" action="sales_orders.php" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                      <input type="hidden" name="action" value="remove_item">
                      <input type="hidden" name="order_id" value="<?= $editOrder["order_id"] ?>">
                      <input type="hidden" name="item_id" value="<?= $item["item_id"] ?>">
                      <button class="btn danger" type="submit">Remove</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <form method="post" action="sales_orders.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="add_item">
          <input type="hidden" name="order_id" value="<?= $editOrder["order_id"] ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="addProductId">Add Product</label>
              <select id="addProductId" name="product_id" required>
                <option value="">Select product</option>
                <?php foreach ($products as $product): ?>
                  <option value="<?= $product["product_id"] ?>">
                    <?= htmlspecialchars($product["product_name"]) ?> (Available: <?= (int) $product["current_stock"] ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="addQuantity">Quantity</label>
              <input type="number" id="addQuantity" name="quantity" min="1" required>
            </div>
          </div>
          <div class="button-row"><button class="btn" type="submit">Add Item</button></div>
        </form>
      </section>
      <?php else: ?>

      <section class="panel">
        <h3>Create Order</h3>
        <form method="post" action="sales_orders.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="create">
          <div class="form-grid">
            <div class="form-group">
              <label for="customerId">Customer</label>
              <select id="customerId" name="customer_id" required>
                <option value="">Select customer</option>
                <?php foreach ($customers as $customer): ?>
                  <option value="<?= $customer["customer_id"] ?>"><?= htmlspecialchars($customer["name"]) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="orderDate">Order Date</label>
              <input type="date" id="orderDate" name="order_date" required>
            </div>
            <div class="form-group">
              <label for="discountRate">Discount Rate (%)</label>
              <input type="number" id="discountRate" name="discount_rate" min="0" max="100" step="0.01" value="0">
            </div>
            <div class="form-group">
              <label for="taxRate">Tax Rate (%)</label>
              <input type="number" id="taxRate" name="tax_rate" min="0" max="100" step="0.01" value="0">
            </div>
            <div class="form-group">
              <label for="isCredit">Credit Order</label>
              <select id="isCredit" name="is_credit">
                <option value="0">No</option>
                <option value="1">Yes</option>
              </select>
            </div>
          </div>
          <div class="form-grid">
            <?php for ($i = 1; $i <= 3; $i++): ?>
              <div class="form-group">
                <label for="itemProduct<?= $i ?>">Product <?= $i ?></label>
                <select id="itemProduct<?= $i ?>" name="item_product_<?= $i ?>">
                  <option value="">None</option>
                  <?php foreach ($products as $product): ?>
                    <option value="<?= $product["product_id"] ?>">
                      <?= htmlspecialchars($product["product_name"]) ?> (Available: <?= (int) $product["current_stock"] ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="itemQuantity<?= $i ?>">Quantity <?= $i ?></label>
                <input type="number" id="itemQuantity<?= $i ?>" name="item_quantity_<?= $i ?>" min="1">
              </div>
            <?php endfor; ?>
          </div>
          <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">More items can be added after the order is created. Discount and tax are calculated automatically from the item subtotal.</p>
          <div class="button-row"><button class="btn" type="submit">Create Order</button></div>
        </form>
      </section>
      <?php endif; ?>

      <section class="panel">
        <h3>Sales Orders</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Order ID</th><th>Customer</th><th>Date</th><th>Items</th><th>Total</th><th>Credit</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($orders) === 0): ?>
                <tr><td colspan="8">No sales orders loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($orders)): ?>
                <tr>
                  <td><?= $row["order_id"] ?></td>
                  <td><?= htmlspecialchars($row["customer_name"]) ?></td>
                  <td><?= htmlspecialchars(substr($row["order_date"], 0, 10)) ?></td>
                  <td><?= (int) $row["item_count"] ?></td>
                  <td><?= number_format($row["total_amount"], 2) ?></td>
                  <td><?= $row["is_credit"] ? "Yes" : "No" ?></td>
                  <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars(ucfirst($row["status"])) ?></span></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <a class="btn secondary" href="sales_orders.php?edit=<?= $row["order_id"] ?>">Edit</a>
                      <form method="post" action="sales_orders.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" value="<?= $row["order_id"] ?>">
                        <select name="status" onchange="this.form.submit()" style="min-width: 110px;">
                          <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $row["status"] === $value ? "selected" : "" ?>><?= $label ?></option>
                          <?php endforeach; ?>
                        </select>
                      </form>
                      <?php if ($row["status"] !== "cancelled"): ?>
                        <form method="post" action="sales_orders.php" style="display:inline;">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                          <input type="hidden" name="action" value="cancel">
                          <input type="hidden" name="order_id" value="<?= $row["order_id"] ?>">
                          <button class="btn danger" type="submit" onclick="return confirm('Cancel this order?');">Cancel</button>
                        </form>
                      <?php endif; ?>
                      <form method="post" action="sales_orders.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="order_id" value="<?= $row["order_id"] ?>">
                        <button class="btn danger" type="submit" onclick="return confirm('Permanently delete this order and its items/invoice?');">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalOrders, $perPage); ?>
      </section>
    </main>
  </div>
</body>
</html>
