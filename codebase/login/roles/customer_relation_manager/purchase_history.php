<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Customer Relationship Officer');

$activePage = "purchases";

$customers = getAllCustomers($connection);
$customerId = (int) ($_GET["customer_id"] ?? 0);

$purchases = [];
$totalPurchases = 0;
$perPage = 10;
$currentPage = 1;
if ($customerId > 0) {
    $totalPurchases = countRows(
        $connection,
        "SELECT COUNT(*) FROM sales_order so JOIN order_item oi ON oi.order_id = so.order_id JOIN product p ON p.product_id = oi.product_id WHERE so.customer_id = ?",
        "i",
        [$customerId]
    );
    $totalPurchasePages = max(1, (int) ceil($totalPurchases / $perPage));
    $currentPage = min(getCurrentPage(), $totalPurchasePages);
    $offset = ($currentPage - 1) * $perPage;

    $statement = mysqli_prepare(
        $connection,
        "SELECT so.order_id, so.order_date, p.product_name, oi.quantity, oi.line_total, so.status
         FROM sales_order so
         JOIN order_item oi ON oi.order_id = so.order_id
         JOIN product p ON p.product_id = oi.product_id
         WHERE so.customer_id = ?
         ORDER BY so.order_date DESC
         LIMIT $perPage OFFSET $offset"
    );
    mysqli_stmt_bind_param($statement, "i", $customerId);
    mysqli_stmt_execute($statement);
    $purchases = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Purchase History</title><link rel="stylesheet" href="css/cro_style.css">
</head>
<body>
  <header class="topbar"><h1>Customer Relationship Officer</h1><p>View customer purchase history</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Purchase History</h2><p>Search and view a customer's past purchases.</p></section>

      <section class="panel">
        <h3>Find Purchases</h3>
        <form method="get" action="purchase_history.php">
          <div class="form-grid">
            <div class="form-group">
              <label for="customerId">Customer</label>
              <select id="customerId" name="customer_id" required>
                <option value="">Select customer</option>
                <?php foreach ($customers as $customer): ?>
                  <option value="<?= $customer["customer_id"] ?>" <?= $customerId === (int) $customer["customer_id"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($customer["name"]) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="button-row"><button class="btn" type="submit">View History</button></div>
        </form>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Order ID</th><th>Date</th><th>Item</th><th>Quantity</th><th>Line Total</th><th>Status</th></tr></thead>
            <tbody>
              <?php if ($customerId === 0): ?>
                <tr><td colspan="6">Select a customer to view purchase history.</td></tr>
              <?php elseif (empty($purchases)): ?>
                <tr><td colspan="6">No purchase history for this customer.</td></tr>
              <?php endif; ?>
              <?php foreach ($purchases as $row): ?>
                <tr>
                  <td><?= $row["order_id"] ?></td>
                  <td><?= htmlspecialchars(substr($row["order_date"], 0, 10)) ?></td>
                  <td><?= htmlspecialchars($row["product_name"]) ?></td>
                  <td><?= (int) $row["quantity"] ?></td>
                  <td><?= number_format($row["line_total"], 2) ?></td>
                  <td><?= htmlspecialchars(ucfirst($row["status"])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($customerId > 0): ?>
          <?php renderPagination($currentPage, $totalPurchases, $perPage); ?>
        <?php endif; ?>
      </section>
    </main>
  </div>
  <script src="js/validate.js"></script>
</body>
</html>
