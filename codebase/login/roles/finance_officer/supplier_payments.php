<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Finance Officer');

$activePage = "supplier_payments";
$currentEmployeeId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: supplier_payments.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "save") {
        $paymentId = (int) ($_POST["payment_id"] ?? 0);
        $supplierId = (int) $_POST["supplier_id"];
        $purchaseId = (int) ($_POST["purchase_id"] ?? 0) ?: null;
        $paymentDate = $_POST["payment_date"];
        $amount = (float) $_POST["amount"];
        $status = $_POST["status"];

        if ($paymentId > 0) {
            $statement = mysqli_prepare(
                $connection,
                "UPDATE supplier_payment SET supplier_id = ?, purchase_id = ?, amount = ?, payment_date = ?, status = ?
                 WHERE supplier_payment_id = ?"
            );
            mysqli_stmt_bind_param($statement, "iidssi", $supplierId, $purchaseId, $amount, $paymentDate, $status, $paymentId);
        } else {
            $statement = mysqli_prepare(
                $connection,
                "INSERT INTO supplier_payment (supplier_id, purchase_id, paid_by, amount, payment_date, status)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($statement, "iiidss", $supplierId, $purchaseId, $currentEmployeeId, $amount, $paymentDate, $status);
        }
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Supplier payment saved.");
        header("Location: supplier_payments.php");
        exit();
    }

    if ($action === "delete") {
        $paymentId = (int) $_POST["payment_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM supplier_payment WHERE supplier_payment_id = ?");
        mysqli_stmt_bind_param($statement, "i", $paymentId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Supplier payment deleted.");
        header("Location: supplier_payments.php");
        exit();
    }
}

$editRecord = null;
if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];
    $statement = mysqli_prepare($connection, "SELECT * FROM supplier_payment WHERE supplier_payment_id = ?");
    mysqli_stmt_bind_param($statement, "i", $editId);
    mysqli_stmt_execute($statement);
    $editRecord = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

$suppliers = mysqli_fetch_all(mysqli_query($connection, "SELECT supplier_id, supplier_name FROM supplier ORDER BY supplier_name"), MYSQLI_ASSOC);
$purchaseOrders = mysqli_fetch_all(mysqli_query($connection, "SELECT purchase_id, total_amount FROM purchase_order ORDER BY purchase_id DESC"), MYSQLI_ASSOC);

$perPage = 5;
$totalPayments = countRows($connection, "SELECT COUNT(*) FROM supplier_payment");
$totalPaymentPages = max(1, (int) ceil($totalPayments / $perPage));
$currentPage = min(getCurrentPage(), $totalPaymentPages);
$offset = ($currentPage - 1) * $perPage;

$payments = mysqli_query(
    $connection,
    "SELECT sp.supplier_payment_id, s.supplier_name, sp.purchase_id, sp.amount, sp.payment_date, sp.status
     FROM supplier_payment sp JOIN supplier s ON s.supplier_id = sp.supplier_id
     ORDER BY sp.payment_date DESC, sp.supplier_payment_id DESC
     LIMIT $perPage OFFSET $offset"
);
$statusClasses = ["pending" => "progress", "paid" => "resolved", "cancelled" => "pending"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Supplier Payments</title>
  <link rel="stylesheet" href="css/fo_style.css">
</head>
<body>
  <header class="topbar"><h1>Finance Officer</h1><p>Manage supplier payments</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Supplier Payments</h2><p>Record supplier payments and track payment status.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3><?= $editRecord ? "Edit Supplier Payment" : "Add Supplier Payment" ?></h3>
        <form method="post" action="supplier_payments.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="payment_id" value="<?= htmlspecialchars($editRecord["supplier_payment_id"] ?? "") ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="supplierId">Supplier</label>
              <select id="supplierId" name="supplier_id" required>
                <option value="">Select supplier</option>
                <?php foreach ($suppliers as $supplier): ?>
                  <option value="<?= $supplier["supplier_id"] ?>" <?= ($editRecord["supplier_id"] ?? null) == $supplier["supplier_id"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($supplier["supplier_name"]) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="purchaseId">Purchase Order (optional)</label>
              <select id="purchaseId" name="purchase_id">
                <option value="">None</option>
                <?php foreach ($purchaseOrders as $purchase): ?>
                  <option value="<?= $purchase["purchase_id"] ?>" <?= ($editRecord["purchase_id"] ?? null) == $purchase["purchase_id"] ? "selected" : "" ?>>
                    #<?= $purchase["purchase_id"] ?> (<?= number_format($purchase["total_amount"], 2) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="paymentDate">Payment Date</label>
              <input type="date" id="paymentDate" name="payment_date" value="<?= htmlspecialchars($editRecord["payment_date"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="amount">Amount</label>
              <input type="number" id="amount" name="amount" min="0" step="0.01" value="<?= htmlspecialchars($editRecord["amount"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="status">Status</label>
              <select id="status" name="status" required>
                <option value="pending" <?= ($editRecord["status"] ?? "pending") === "pending" ? "selected" : "" ?>>Pending</option>
                <option value="paid" <?= ($editRecord["status"] ?? "") === "paid" ? "selected" : "" ?>>Paid</option>
                <option value="cancelled" <?= ($editRecord["status"] ?? "") === "cancelled" ? "selected" : "" ?>>Cancelled</option>
              </select>
            </div>
          </div>
          <div class="button-row">
            <button class="btn" type="submit">Save Payment</button>
            <?php if ($editRecord): ?>
              <a class="btn secondary" href="supplier_payments.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </section>

      <section class="panel">
        <h3>Supplier Payment Records</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Supplier</th><th>Purchase Order</th><th>Date</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($payments) === 0): ?>
                <tr><td colspan="6">No supplier payments loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($payments)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["supplier_name"]) ?></td>
                  <td><?= $row["purchase_id"] ? "#" . $row["purchase_id"] : "—" ?></td>
                  <td><?= htmlspecialchars($row["payment_date"]) ?></td>
                  <td><?= number_format($row["amount"], 2) ?></td>
                  <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars(ucfirst($row["status"])) ?></span></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <a class="btn secondary" href="supplier_payments.php?edit=<?= $row["supplier_payment_id"] ?>">Edit</a>
                      <form method="post" action="supplier_payments.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="payment_id" value="<?= $row["supplier_payment_id"] ?>">
                        <button class="btn danger" type="submit" onclick="return confirm('Delete this supplier payment?');">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalPayments, $perPage); ?>
      </section>
    </main>
  </div>
  <script src="js/validate.js"></script>
</body>
</html>
