<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Finance Officer');

$activePage = "receivables";
$currentEmployeeId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: receivables.php");
        exit();
    }

    $invoiceId = (int) $_POST["invoice_id"];
    $amount = (float) $_POST["amount"];
    $paymentMethod = $_POST["payment_method"];

    $invoiceStatement = mysqli_prepare(
        $connection,
        "SELECT inv.total_amount, so.customer_id,
                inv.total_amount - COALESCE((SELECT SUM(amount) FROM payment WHERE invoice_id = inv.invoice_id), 0) AS balance
         FROM invoice inv JOIN sales_order so ON so.order_id = inv.order_id
         WHERE inv.invoice_id = ?"
    );
    mysqli_stmt_bind_param($invoiceStatement, "i", $invoiceId);
    mysqli_stmt_execute($invoiceStatement);
    $invoice = mysqli_fetch_assoc(mysqli_stmt_get_result($invoiceStatement));
    mysqli_stmt_close($invoiceStatement);

    if ($invoice) {
        $statement = mysqli_prepare(
            $connection,
            "INSERT INTO payment (invoice_id, customer_id, received_by, amount, payment_method, payment_date, payment_status)
             VALUES (?, ?, ?, ?, ?, NOW(), 'completed')"
        );
        mysqli_stmt_bind_param($statement, "iiids", $invoiceId, $invoice["customer_id"], $currentEmployeeId, $amount, $paymentMethod);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);

        $newBalance = $invoice["balance"] - $amount;
        $newStatus = $newBalance <= 0 ? "paid" : "partial";
        $updateStatement = mysqli_prepare($connection, "UPDATE invoice SET payment_status = ? WHERE invoice_id = ?");
        mysqli_stmt_bind_param($updateStatement, "si", $newStatus, $invoiceId);
        mysqli_stmt_execute($updateStatement);
        mysqli_stmt_close($updateStatement);

        setFlash("Payment recorded.");
    }

    header("Location: receivables.php");
    exit();
}

$perPage = 10;
$totalReceivables = countRows($connection, "SELECT COUNT(*) FROM invoice WHERE payment_status != 'paid'");
$totalReceivablePages = max(1, (int) ceil($totalReceivables / $perPage));
$currentPage = min(getCurrentPage(), $totalReceivablePages);
$offset = ($currentPage - 1) * $perPage;

$receivables = mysqli_query(
    $connection,
    "SELECT inv.invoice_id, inv.order_id, cu.name AS customer_name, inv.issue_date, inv.total_amount, inv.payment_status,
            DATE_ADD(inv.issue_date, INTERVAL 30 DAY) AS due_date,
            COALESCE((SELECT SUM(amount) FROM payment WHERE invoice_id = inv.invoice_id), 0) AS paid_amount
     FROM invoice inv
     JOIN sales_order so ON so.order_id = inv.order_id
     JOIN customer cu ON cu.customer_id = so.customer_id
     WHERE inv.payment_status != 'paid'
     ORDER BY due_date ASC
     LIMIT $perPage OFFSET $offset"
);
$receivables = mysqli_fetch_all($receivables, MYSQLI_ASSOC);

foreach ($receivables as &$row) {
    $row["balance"] = $row["total_amount"] - $row["paid_amount"];
    $row["is_overdue"] = strtotime($row["due_date"]) < strtotime(date("Y-m-d"));
}
unset($row);

// Total Outstanding card reflects all unpaid invoices, not just this page,
// so it's computed with its own aggregate query independent of pagination.
$totalOutstandingResult = mysqli_query(
    $connection,
    "SELECT COALESCE(SUM(inv.total_amount - COALESCE((SELECT SUM(amount) FROM payment WHERE invoice_id = inv.invoice_id), 0)), 0) AS total_outstanding
     FROM invoice inv
     WHERE inv.payment_status != 'paid'"
);
$totalOutstanding = (float) mysqli_fetch_assoc($totalOutstandingResult)["total_outstanding"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Outstanding Receivables</title>
  <link rel="stylesheet" href="css/fo_style.css">
</head>
<body>
  <header class="topbar"><h1>Finance Officer</h1><p>Track outstanding receivables</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Outstanding Receivables</h2><p>Track unpaid invoices and record customer payments. Due date assumes 30-day terms.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="cards">
        <div class="card"><h3>Total Outstanding</h3><p class="number">Rs. <?= number_format($totalOutstanding, 2) ?></p><p>Across all unpaid invoices</p></div>
      </section>

      <section class="panel">
        <h3>Receivable Records</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Invoice</th><th>Customer</th><th>Due Date</th><th>Total</th><th>Balance</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (empty($receivables)): ?>
                <tr><td colspan="7">No outstanding receivables.</td></tr>
              <?php endif; ?>
              <?php foreach ($receivables as $row): ?>
                <tr>
                  <td>#<?= $row["invoice_id"] ?> (Order #<?= $row["order_id"] ?>)</td>
                  <td><?= htmlspecialchars($row["customer_name"]) ?></td>
                  <td><?= htmlspecialchars($row["due_date"]) ?></td>
                  <td><?= number_format($row["total_amount"], 2) ?></td>
                  <td><?= number_format($row["balance"], 2) ?></td>
                  <td>
                    <span class="status <?= $row["is_overdue"] ? "pending" : "progress" ?>">
                      <?= $row["is_overdue"] ? "Overdue" : htmlspecialchars(ucfirst($row["payment_status"])) ?>
                    </span>
                  </td>
                  <td>
                    <details>
                      <summary class="btn secondary" style="display:inline-block; cursor:pointer;">Record Payment</summary>
                      <form method="post" action="receivables.php" style="margin-top: 10px;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="invoice_id" value="<?= $row["invoice_id"] ?>">
                        <div class="form-grid">
                          <div class="form-group">
                            <label>Amount</label>
                            <input type="number" name="amount" min="0.01" max="<?= $row["balance"] ?>" step="0.01" value="<?= $row["balance"] ?>" required>
                          </div>
                          <div class="form-group">
                            <label>Payment Method</label>
                            <select name="payment_method" required>
                              <option value="cash">Cash</option>
                              <option value="bank_transfer">Bank Transfer</option>
                              <option value="card">Card</option>
                            </select>
                          </div>
                        </div>
                        <div class="button-row"><button class="btn" type="submit">Save Payment</button></div>
                      </form>
                    </details>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalReceivables, $perPage); ?>
      </section>
    </main>
  </div>
</body>
</html>
