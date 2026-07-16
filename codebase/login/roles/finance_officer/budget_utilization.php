<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Finance Officer');

$activePage = "budget";
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: budget_utilization.php");
        exit();
    }

    $budgetId = (int) $_POST["budget_id"];
    $usedAmount = (float) $_POST["used_amount"];
    $statement = mysqli_prepare($connection, "UPDATE budget_plan SET used_amount = ? WHERE budget_id = ?");
    mysqli_stmt_bind_param($statement, "di", $usedAmount, $budgetId);
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);
    setFlash("Budget usage updated.");
    header("Location: budget_utilization.php");
    exit();
}

$perPage = 10;
$totalBudgets = countRows($connection, "SELECT COUNT(*) FROM budget_plan WHERE status = 'approved'");
$totalBudgetPages = max(1, (int) ceil($totalBudgets / $perPage));
$currentPage = min(getCurrentPage(), $totalBudgetPages);
$offset = ($currentPage - 1) * $perPage;

$budgets = mysqli_query(
    $connection,
    "SELECT budget_id, budget_purpose, period, amount, used_amount
     FROM budget_plan
     WHERE status = 'approved'
     ORDER BY period DESC
     LIMIT $perPage OFFSET $offset"
);
$budgets = mysqli_fetch_all($budgets, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Budget Utilization</title>
  <link rel="stylesheet" href="css/fo_style.css">
</head>
<body>
  <header class="topbar"><h1>Finance Officer</h1><p>Monitor budget utilization</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Budget Utilization</h2><p>Compare approved budgets with actual spending. Budgets are created and approved by the CEO / Head Manager.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3>Budget Records</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Purpose</th><th>Period</th><th>Budget</th><th>Used</th><th>Utilization</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (empty($budgets)): ?>
                <tr><td colspan="7">No approved budgets loaded yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($budgets as $row): ?>
                <?php
                  $percent = $row["amount"] > 0 ? round(($row["used_amount"] / $row["amount"]) * 100, 1) : 0;
                  if ($percent >= 100) {
                      $utilStatus = "Exceeded";
                      $utilClass = "pending";
                  } elseif ($percent >= 80) {
                      $utilStatus = "Near Limit";
                      $utilClass = "progress";
                  } else {
                      $utilStatus = "Within Budget";
                      $utilClass = "resolved";
                  }
                ?>
                <tr>
                  <td><?= htmlspecialchars($row["budget_purpose"]) ?></td>
                  <td><?= htmlspecialchars($row["period"]) ?></td>
                  <td><?= number_format($row["amount"], 2) ?></td>
                  <td><?= number_format($row["used_amount"], 2) ?></td>
                  <td><?= $percent ?>%</td>
                  <td><span class="status <?= $utilClass ?>"><?= $utilStatus ?></span></td>
                  <td>
                    <form method="post" action="budget_utilization.php" style="display:flex; gap: 8px;">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                      <input type="hidden" name="budget_id" value="<?= $row["budget_id"] ?>">
                      <input type="number" name="used_amount" min="0" step="0.01" value="<?= $row["used_amount"] ?>" style="width: 120px;">
                      <button class="btn secondary" type="submit">Update</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalBudgets, $perPage); ?>
      </section>
    </main>
  </div>
</body>
</html>
