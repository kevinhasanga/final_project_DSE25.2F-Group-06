<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Finance Officer');

$activePage = "reconciliation";
$currentEmployeeId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: reconciliation.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "save") {
        $reconciliationId = (int) ($_POST["reconciliation_id"] ?? 0);
        $accountNumber = trim($_POST["account_number"]);
        $reconciliationDate = $_POST["reconciliation_date"];
        $systemBalance = (float) $_POST["system_balance"];
        $bankBalance = (float) $_POST["bank_balance"];
        $status = $_POST["status"];
        $remarks = trim($_POST["remarks"] ?? "");

        if ($reconciliationId > 0) {
            $statement = mysqli_prepare(
                $connection,
                "UPDATE account_reconciliation SET account_number = ?, reconciliation_date = ?, system_balance = ?, bank_balance = ?, status = ?, remarks = ?
                 WHERE reconciliation_id = ?"
            );
            mysqli_stmt_bind_param($statement, "ssddssi", $accountNumber, $reconciliationDate, $systemBalance, $bankBalance, $status, $remarks, $reconciliationId);
        } else {
            $statement = mysqli_prepare(
                $connection,
                "INSERT INTO account_reconciliation (account_number, reconciliation_date, system_balance, bank_balance, status, remarks, performed_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($statement, "ssddssi", $accountNumber, $reconciliationDate, $systemBalance, $bankBalance, $status, $remarks, $currentEmployeeId);
        }
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Reconciliation record saved.");
        header("Location: reconciliation.php");
        exit();
    }

    if ($action === "delete") {
        $reconciliationId = (int) $_POST["reconciliation_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM account_reconciliation WHERE reconciliation_id = ?");
        mysqli_stmt_bind_param($statement, "i", $reconciliationId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Reconciliation record deleted.");
        header("Location: reconciliation.php");
        exit();
    }
}

$editRecord = null;
if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];
    $statement = mysqli_prepare($connection, "SELECT * FROM account_reconciliation WHERE reconciliation_id = ?");
    mysqli_stmt_bind_param($statement, "i", $editId);
    mysqli_stmt_execute($statement);
    $editRecord = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

$perPage = 10;
$totalRecords = countRows($connection, "SELECT COUNT(*) FROM account_reconciliation");
$totalRecordPages = max(1, (int) ceil($totalRecords / $perPage));
$currentPage = min(getCurrentPage(), $totalRecordPages);
$offset = ($currentPage - 1) * $perPage;

$records = mysqli_query($connection, "SELECT * FROM account_reconciliation ORDER BY reconciliation_date DESC LIMIT $perPage OFFSET $offset");
$statusOptions = ["matched" => "Matched", "difference_found" => "Difference Found", "pending" => "Pending"];
$statusClasses = ["matched" => "resolved", "difference_found" => "pending", "pending" => "progress"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account Reconciliation</title>
  <link rel="stylesheet" href="css/fo_style.css">
</head>
<body>
  <header class="topbar"><h1>Finance Officer</h1><p>Perform account reconciliation</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Account Reconciliation</h2><p>Compare system balances with bank statement balances.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3><?= $editRecord ? "Edit Reconciliation" : "Add Reconciliation" ?></h3>
        <form method="post" action="reconciliation.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="reconciliation_id" value="<?= htmlspecialchars($editRecord["reconciliation_id"] ?? "") ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="accountNumber">Account Number</label>
              <input type="text" id="accountNumber" name="account_number" value="<?= htmlspecialchars($editRecord["account_number"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="reconciliationDate">Reconciliation Date</label>
              <input type="date" id="reconciliationDate" name="reconciliation_date" value="<?= htmlspecialchars($editRecord["reconciliation_date"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="systemBalance">System Balance</label>
              <input type="number" id="systemBalance" name="system_balance" step="0.01" value="<?= htmlspecialchars($editRecord["system_balance"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="bankBalance">Bank Balance</label>
              <input type="number" id="bankBalance" name="bank_balance" step="0.01" value="<?= htmlspecialchars($editRecord["bank_balance"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="status">Status</label>
              <select id="status" name="status" required>
                <?php foreach ($statusOptions as $value => $label): ?>
                  <option value="<?= $value ?>" <?= ($editRecord["status"] ?? "pending") === $value ? "selected" : "" ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group full-width">
              <label for="remarks">Remarks</label>
              <textarea id="remarks" name="remarks"><?= htmlspecialchars($editRecord["remarks"] ?? "") ?></textarea>
            </div>
          </div>
          <div class="button-row">
            <button class="btn" type="submit">Save Reconciliation</button>
            <?php if ($editRecord): ?>
              <a class="btn secondary" href="reconciliation.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </section>

      <section class="panel">
        <h3>Reconciliation Records</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Account</th><th>Date</th><th>System Balance</th><th>Bank Balance</th><th>Difference</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($records) === 0): ?>
                <tr><td colspan="7">No reconciliation records loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($records)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["account_number"]) ?></td>
                  <td><?= htmlspecialchars($row["reconciliation_date"]) ?></td>
                  <td><?= number_format($row["system_balance"], 2) ?></td>
                  <td><?= number_format($row["bank_balance"], 2) ?></td>
                  <td><?= number_format($row["system_balance"] - $row["bank_balance"], 2) ?></td>
                  <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars($statusOptions[$row["status"]] ?? $row["status"]) ?></span></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <a class="btn secondary" href="reconciliation.php?edit=<?= $row["reconciliation_id"] ?>">Edit</a>
                      <form method="post" action="reconciliation.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="reconciliation_id" value="<?= $row["reconciliation_id"] ?>">
                        <button class="btn danger" type="submit" onclick="return confirm('Delete this reconciliation record?');">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalRecords, $perPage); ?>
      </section>
    </main>
  </div>
  <script src="js/validate.js"></script>
</body>
</html>
