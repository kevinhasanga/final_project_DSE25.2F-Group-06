<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Distribution Manager');

$activePage = "confirmed";

$search = trim($_GET["q"] ?? "");
$sql = "SELECT so.order_id, cu.name AS customer_name, cu.address, so.order_date
        FROM sales_order so
        JOIN customer cu ON cu.customer_id = so.customer_id
        LEFT JOIN delivery d ON d.order_id = so.order_id
        WHERE so.status IN ('completed', 'invoiced') AND d.delivery_id IS NULL";
$params = [];
$types = "";
if ($search !== "") {
    $sql .= " AND (so.order_id = ? OR cu.name LIKE ?)";
    $params[] = (int) $search;
    $params[] = "%$search%";
    $types = "is";
}
$sql .= " ORDER BY so.order_date DESC";

$perPage = 10;
$countSql = "SELECT COUNT(*) FROM sales_order so
        JOIN customer cu ON cu.customer_id = so.customer_id
        LEFT JOIN delivery d ON d.order_id = so.order_id
        WHERE so.status IN ('completed', 'invoiced') AND d.delivery_id IS NULL";
if ($search !== "") {
    $countSql .= " AND (so.order_id = ? OR cu.name LIKE ?)";
}
$totalOrders = countRows($connection, $countSql, $types, $params);
$totalOrderPages = max(1, (int) ceil($totalOrders / $perPage));
$currentPage = min(getCurrentPage(), $totalOrderPages);
$offset = ($currentPage - 1) * $perPage;

$sql .= " LIMIT $perPage OFFSET $offset";
$statement = mysqli_prepare($connection, $sql);
if ($types !== "") {
    mysqli_stmt_bind_param($statement, $types, ...$params);
}
mysqli_stmt_execute($statement);
$orders = mysqli_stmt_get_result($statement);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Confirmed Orders</title>
  <link rel="stylesheet" href="css/dm_style.css">
</head>
<body>
  <header class="topbar">
    <h1>Distribution Management</h1>
    <p>View confirmed orders ready for delivery planning</p>
  </header>

  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title">
        <h2>Confirmed Orders</h2>
        <p>Orders that are confirmed and not yet scheduled for delivery.</p>
      </section>

      <section class="panel">
        <h3>Search Orders</h3>
        <form method="get" action="confirmed_orders.php">
          <div class="form-grid">
            <div class="form-group full-width">
              <label for="q">Order ID or customer name</label>
              <input type="text" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search orders">
            </div>
          </div>
          <div class="button-row">
            <button class="btn" type="submit">Search</button>
            <?php if ($search !== ""): ?>
              <a class="btn secondary" href="confirmed_orders.php">Clear</a>
            <?php endif; ?>
          </div>
        </form>

        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Address</th>
                <th>Order Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (mysqli_num_rows($orders) === 0): ?>
                <tr><td colspan="5">No confirmed orders waiting to be scheduled.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($orders)): ?>
                <tr>
                  <td><?= $row["order_id"] ?></td>
                  <td><?= htmlspecialchars($row["customer_name"]) ?></td>
                  <td><?= htmlspecialchars($row["address"] ?? "") ?></td>
                  <td><?= htmlspecialchars(substr($row["order_date"], 0, 10)) ?></td>
                  <td><a class="btn secondary" href="deliveries.php?order_id=<?= $row["order_id"] ?>">Schedule Delivery</a></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalOrders, $perPage); ?>
      </section>
    </main>
  </div>
  <script src="js/validate.js"></script>
</body>
</html>
