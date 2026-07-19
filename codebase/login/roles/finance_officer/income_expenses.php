<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Finance Officer');

$activePage = "income_expenses";
$currentEmployeeId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: income_expenses.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "save") {
        $recordId = (int) ($_POST["record_id"] ?? 0);
        $recordDate = $_POST["record_date"];
        $type = $_POST["type"];
        $category = trim($_POST["category"] ?? "");
        $amount = (float) $_POST["amount"];
        $description = trim($_POST["description"] ?? "");
        $taxType = trim($_POST["tax_type"] ?? "") ?: null;
        $taxRate = (float) ($_POST["tax_rate"] ?: 0);
        $taxAmount = round($amount * $taxRate / 100, 2);

        if ($recordId > 0) {
            if ($taxRate <= 0) {
                $existingStatement = mysqli_prepare($connection, "SELECT tax_amount FROM financial_record WHERE record_id = ?");
                mysqli_stmt_bind_param($existingStatement, "i", $recordId);
                mysqli_stmt_execute($existingStatement);
                $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($existingStatement));
                mysqli_stmt_close($existingStatement);
                $taxAmount = $existing ? (float) $existing["tax_amount"] : 0;
            }

            $statement = mysqli_prepare(
                $connection,
                "UPDATE financial_record SET record_date = ?, type = ?, category = ?, amount = ?, description = ?, tax_type = ?, tax_amount = ?
                 WHERE record_id = ?"
            );
            mysqli_stmt_bind_param($statement, "sssdssdi", $recordDate, $type, $category, $amount, $description, $taxType, $taxAmount, $recordId);
        } else {
            $statement = mysqli_prepare(
                $connection,
                "INSERT INTO financial_record (recorded_by, type, category, amount, description, record_date, tax_type, tax_amount)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($statement, "issdsssd", $currentEmployeeId, $type, $category, $amount, $description, $recordDate, $taxType, $taxAmount);
        }
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Financial record saved.");
        header("Location: income_expenses.php");
        exit();
    }

    if ($action === "delete") {
        $recordId = (int) $_POST["record_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM financial_record WHERE record_id = ?");
        mysqli_stmt_bind_param($statement, "i", $recordId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Financial record deleted.");
        header("Location: income_expenses.php");
        exit();
    }
}

$editRecord = null;
if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];
    $statement = mysqli_prepare($connection, "SELECT * FROM financial_record WHERE record_id = ?");
    mysqli_stmt_bind_param($statement, "i", $editId);
    mysqli_stmt_execute($statement);
    $editRecord = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

$perPage = 5;
$totalRecords = countRows($connection, "SELECT COUNT(*) FROM financial_record");
$totalRecordPages = max(1, (int) ceil($totalRecords / $perPage));
$currentPage = min(getCurrentPage(), $totalRecordPages);
$offset = ($currentPage - 1) * $perPage;

$records = mysqli_query($connection, "SELECT * FROM financial_record ORDER BY record_date DESC, record_id DESC LIMIT $perPage OFFSET $offset");
$taxTypeOptions = ["vat" => "VAT", "income_tax" => "Income Tax", "withholding_tax" => "Withholding Tax"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Income and Expenses</title>
  <link rel="stylesheet" href="css/fo_style.css">
</head>
<body>
  <header class="topbar"><h1>Finance Officer</h1><p>Record daily income, operational expenses, and tax details</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Income and Expenses</h2><p>Add daily income and expense records, including applicable tax.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3><?= $editRecord ? "Edit Record" : "Add Record" ?></h3>
        <form method="post" action="income_expenses.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="record_id" value="<?= htmlspecialchars($editRecord["record_id"] ?? "") ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="recordDate">Date</label>
              <input type="date" id="recordDate" name="record_date" value="<?= htmlspecialchars($editRecord["record_date"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="type">Record Type</label>
              <select id="type" name="type" required>
                <option value="income" <?= ($editRecord["type"] ?? "") === "income" ? "selected" : "" ?>>Income</option>
                <option value="expense" <?= ($editRecord["type"] ?? "") === "expense" ? "selected" : "" ?>>Expense</option>
              </select>
            </div>
            <div class="form-group">
              <label for="category">Category</label>
              <input type="text" id="category" name="category" value="<?= htmlspecialchars($editRecord["category"] ?? "") ?>" placeholder="e.g. Rent, Utilities, Sales">
            </div>
            <div class="form-group">
              <label for="amount">Amount</label>
              <input type="number" id="amount" name="amount" min="0" step="0.01" value="<?= htmlspecialchars($editRecord["amount"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="taxType">Tax Type</label>
              <select id="taxType" name="tax_type">
                <option value="">None</option>
                <?php foreach ($taxTypeOptions as $value => $label): ?>
                  <option value="<?= $value ?>" <?= ($editRecord["tax_type"] ?? "") === $value ? "selected" : "" ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="taxRate">Tax Rate (%)</label>
              <input type="number" id="taxRate" name="tax_rate" min="0" max="100" step="0.01" value="0">
            </div>
            <div class="form-group full-width">
              <label for="description">Description</label>
              <textarea id="description" name="description"><?= htmlspecialchars($editRecord["description"] ?? "") ?></textarea>
            </div>
          </div>
          <?php if ($editRecord): ?>
            <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Current tax amount on file: <?= number_format($editRecord["tax_amount"], 2) ?>. Set a tax rate above to recalculate it on save.</p>
          <?php endif; ?>
          <div class="button-row">
            <button class="btn" type="submit">Save Record</button>
            <?php if ($editRecord): ?>
              <a class="btn secondary" href="income_expenses.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </section>

      <section class="panel">
        <h3>Income and Expense Records</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Amount</th><th>Tax</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($records) === 0): ?>
                <tr><td colspan="6">No income or expense records loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($records)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["record_date"]) ?></td>
                  <td><span class="status <?= $row["type"] === "income" ? "resolved" : "pending" ?>"><?= htmlspecialchars(ucfirst($row["type"])) ?></span></td>
                  <td><?= htmlspecialchars($row["category"] ?? "") ?></td>
                  <td><?= number_format($row["amount"], 2) ?></td>
                  <td><?= number_format($row["tax_amount"], 2) ?></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <a class="btn secondary" href="income_expenses.php?edit=<?= $row["record_id"] ?>">Edit</a>
                      <form method="post" action="income_expenses.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="record_id" value="<?= $row["record_id"] ?>">
                        <button class="btn danger" type="submit" onclick="return confirm('Delete this financial record?');">Delete</button>
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
