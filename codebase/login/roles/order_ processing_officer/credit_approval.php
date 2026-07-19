<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Order Processing Officer');

$activePage = "credit";
$currentEmployeeId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: credit_approval.php");
        exit();
    }

    $action = $_POST["action"] ?? "";
    $orderId = (int) $_POST["order_id"];

    if ($action === "approve") {
        $statement = mysqli_prepare($connection, "UPDATE sales_order SET credit_approved_by = ? WHERE order_id = ?");
        mysqli_stmt_bind_param($statement, "ii", $currentEmployeeId, $orderId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Credit order approved.");
    }

    if ($action === "reject") {
        $statement = mysqli_prepare($connection, "UPDATE sales_order SET status = 'cancelled' WHERE order_id = ?");
        mysqli_stmt_bind_param($statement, "i", $orderId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Credit order rejected and cancelled.");
    }

    header("Location: credit_approval.php");
    exit();
}

$perPage = 5;
$totalCreditOrders = countRows($connection, "SELECT COUNT(*) FROM sales_order so WHERE so.is_credit = 1");
$totalCreditOrderPages = max(1, (int) ceil($totalCreditOrders / $perPage));
$currentPage = min(getCurrentPage(), $totalCreditOrderPages);
$offset = ($currentPage - 1) * $perPage;

$creditOrders = mysqli_query(
    $connection,
    "SELECT so.order_id, c.name AS customer_name, so.total_amount, so.status, so.credit_approved_by
     FROM sales_order so
     JOIN customer c ON c.customer_id = so.customer_id
     WHERE so.is_credit = 1
     ORDER BY (so.credit_approved_by IS NULL AND so.status != 'cancelled') DESC, so.order_date DESC
     LIMIT $perPage OFFSET $offset"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Credit Order Approval</title>
  <link rel="stylesheet" href="css/opo_style.css">
</head>
<body>
  <header class="topbar"><h1>Order Processing</h1><p>Approve or reject credit sales orders</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Credit Order Approval</h2><p>Review credit order requests and record approval decisions.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3>Credit Orders</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Order ID</th><th>Customer</th><th>Amount</th><th>Order Status</th><th>Credit Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($creditOrders) === 0): ?>
                <tr><td colspan="6">No credit orders loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($creditOrders)): ?>
                <?php
                  if ($row["status"] === "cancelled") {
                      $creditStatus = "Rejected";
                      $creditClass = "pending";
                  } elseif ($row["credit_approved_by"]) {
                      $creditStatus = "Approved";
                      $creditClass = "resolved";
                  } else {
                      $creditStatus = "Pending";
                      $creditClass = "progress";
                  }
                ?>
                <tr>
                  <td><?= $row["order_id"] ?></td>
                  <td><?= htmlspecialchars($row["customer_name"]) ?></td>
                  <td><?= number_format($row["total_amount"], 2) ?></td>
                  <td><?= htmlspecialchars(ucfirst($row["status"])) ?></td>
                  <td><span class="status <?= $creditClass ?>"><?= $creditStatus ?></span></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <?php if ($creditStatus === "Pending"): ?>
                        <form method="post" action="credit_approval.php" style="display:inline;">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                          <input type="hidden" name="action" value="approve">
                          <input type="hidden" name="order_id" value="<?= $row["order_id"] ?>">
                          <button class="btn" type="submit">Approve</button>
                        </form>
                        <form method="post" action="credit_approval.php" style="display:inline;">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                          <input type="hidden" name="action" value="reject">
                          <input type="hidden" name="order_id" value="<?= $row["order_id"] ?>">
                          <button class="btn danger" type="submit">Reject</button>
                        </form>
                      <?php else: ?>
                        —
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalCreditOrders, $perPage); ?>
      </section>
    </main>
  </div>
  <script src="js/validate.js"></script>
</body>
</html>
