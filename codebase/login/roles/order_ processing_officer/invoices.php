<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Order Processing Officer');

$activePage = "invoices";
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: invoices.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "generate") {
        $orderId = (int) $_POST["order_id"];
        $issueDate = $_POST["issue_date"];

        $orderStatement = mysqli_prepare(
            $connection,
            "SELECT total_amount - tax_amount + discount_amount AS subtotal, discount_amount, tax_amount, total_amount
             FROM sales_order WHERE order_id = ?"
        );
        mysqli_stmt_bind_param($orderStatement, "i", $orderId);
        mysqli_stmt_execute($orderStatement);
        $order = mysqli_fetch_assoc(mysqli_stmt_get_result($orderStatement));
        mysqli_stmt_close($orderStatement);

        if ($order) {
            $statement = mysqli_prepare(
                $connection,
                "INSERT INTO invoice (order_id, issue_date, subtotal, discount_amount, tax_amount, total_amount, payment_status)
                 VALUES (?, ?, ?, ?, ?, ?, 'unpaid')"
            );
            mysqli_stmt_bind_param(
                $statement,
                "isdddd",
                $orderId,
                $issueDate,
                $order["subtotal"],
                $order["discount_amount"],
                $order["tax_amount"],
                $order["total_amount"]
            );
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);

            $updateOrderStatement = mysqli_prepare(
                $connection,
                "UPDATE sales_order SET status = 'invoiced' WHERE order_id = ? AND status NOT IN ('completed', 'cancelled')"
            );
            mysqli_stmt_bind_param($updateOrderStatement, "i", $orderId);
            mysqli_stmt_execute($updateOrderStatement);
            mysqli_stmt_close($updateOrderStatement);

            setFlash("Invoice generated.");
        }

        header("Location: invoices.php");
        exit();
    }

    if ($action === "update_payment_status") {
        $invoiceId = (int) $_POST["invoice_id"];
        $paymentStatus = $_POST["payment_status"];
        $statement = mysqli_prepare($connection, "UPDATE invoice SET payment_status = ? WHERE invoice_id = ?");
        mysqli_stmt_bind_param($statement, "si", $paymentStatus, $invoiceId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Invoice updated.");
        header("Location: invoices.php");
        exit();
    }

    if ($action === "delete") {
        $invoiceId = (int) $_POST["invoice_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM invoice WHERE invoice_id = ?");
        mysqli_stmt_bind_param($statement, "i", $invoiceId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Invoice deleted.");
        header("Location: invoices.php");
        exit();
    }
}

$uninvoicedOrders = mysqli_query(
    $connection,
    "SELECT so.order_id, c.name AS customer_name, so.total_amount
     FROM sales_order so
     JOIN customer c ON c.customer_id = so.customer_id
     LEFT JOIN invoice i ON i.order_id = so.order_id
     WHERE i.invoice_id IS NULL AND so.status != 'cancelled'
     ORDER BY so.order_date DESC"
);
$uninvoicedOrders = mysqli_fetch_all($uninvoicedOrders, MYSQLI_ASSOC);

$perPage = 5;
$totalInvoices = countRows($connection, "SELECT COUNT(*) FROM invoice inv");
$totalInvoicePages = max(1, (int) ceil($totalInvoices / $perPage));
$currentPage = min(getCurrentPage(), $totalInvoicePages);
$offset = ($currentPage - 1) * $perPage;

$invoices = mysqli_query(
    $connection,
    "SELECT inv.invoice_id, inv.order_id, c.name AS customer_name, inv.issue_date,
            inv.subtotal, inv.discount_amount, inv.tax_amount, inv.total_amount, inv.payment_status
     FROM invoice inv
     JOIN sales_order so ON so.order_id = inv.order_id
     JOIN customer c ON c.customer_id = so.customer_id
     ORDER BY inv.issue_date DESC, inv.invoice_id DESC
     LIMIT $perPage OFFSET $offset"
);

$paymentStatusOptions = ["unpaid" => "Unpaid", "partial" => "Partial", "paid" => "Paid"];
$paymentStatusClasses = ["unpaid" => "pending", "partial" => "progress", "paid" => "resolved"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Invoices</title>
  <link rel="stylesheet" href="css/opo_style.css">
</head>
<body>
  <header class="topbar"><h1>Order Processing</h1><p>Generate invoices for confirmed sales orders</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Invoices</h2><p>Generate invoice records from sales orders and track payment status.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3>Generate Invoice</h3>
        <form method="post" action="invoices.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="generate">
          <div class="form-grid">
            <div class="form-group">
              <label for="orderId">Order</label>
              <select id="orderId" name="order_id" required>
                <option value="">Select order</option>
                <?php foreach ($uninvoicedOrders as $order): ?>
                  <option value="<?= $order["order_id"] ?>">
                    #<?= $order["order_id"] ?> — <?= htmlspecialchars($order["customer_name"]) ?> (<?= number_format($order["total_amount"], 2) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="issueDate">Invoice Date</label>
              <input type="date" id="issueDate" name="issue_date" required>
            </div>
          </div>
          <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Subtotal, discount, tax, and total are pulled automatically from the selected order.</p>
          <div class="button-row"><button class="btn" type="submit">Generate Invoice</button></div>
        </form>
      </section>

      <section class="panel">
        <h3>Generated Invoices</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Invoice ID</th><th>Order</th><th>Customer</th><th>Date</th><th>Total</th><th>Payment Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($invoices) === 0): ?>
                <tr><td colspan="7">No invoices loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($invoices)): ?>
                <tr>
                  <td><?= $row["invoice_id"] ?></td>
                  <td>#<?= $row["order_id"] ?></td>
                  <td><?= htmlspecialchars($row["customer_name"]) ?></td>
                  <td><?= htmlspecialchars($row["issue_date"]) ?></td>
                  <td><?= number_format($row["total_amount"], 2) ?></td>
                  <td><span class="status <?= $paymentStatusClasses[$row["payment_status"]] ?? "progress" ?>"><?= htmlspecialchars($paymentStatusOptions[$row["payment_status"]] ?? $row["payment_status"]) ?></span></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <form method="post" action="invoices.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="update_payment_status">
                        <input type="hidden" name="invoice_id" value="<?= $row["invoice_id"] ?>">
                        <select name="payment_status" onchange="this.form.submit()" style="min-width: 100px;">
                          <?php foreach ($paymentStatusOptions as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $row["payment_status"] === $value ? "selected" : "" ?>><?= $label ?></option>
                          <?php endforeach; ?>
                        </select>
                      </form>
                      <form method="post" action="invoices.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="invoice_id" value="<?= $row["invoice_id"] ?>">
                        <button class="btn danger" type="submit" onclick="return confirm('Delete this invoice?');">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalInvoices, $perPage); ?>
      </section>
    </main>
  </div>
  <script src="js/validate.js"></script>
</body>
</html>
