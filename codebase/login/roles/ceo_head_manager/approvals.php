<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('CEO');

$activePage = "approvals";
$currentEmployeeId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: approvals.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "create_budget") {
        $statement = mysqli_prepare(
            $connection,
            "INSERT INTO budget_plan (budget_purpose, period, amount, prepared_by, status) VALUES (?, ?, ?, ?, 'pending')"
        );
        $purpose = trim($_POST["budget_purpose"]);
        $period = trim($_POST["period"]);
        $amount = (float) $_POST["amount"];
        mysqli_stmt_bind_param($statement, "ssdi", $purpose, $period, $amount, $currentEmployeeId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Budget request recorded.");
    } elseif ($action === "decide_budget") {
        $budgetId = (int) $_POST["record_id"];
        $status = $_POST["status"];
        $approvedBy = $status === "approved" ? $currentEmployeeId : null;
        $statement = mysqli_prepare($connection, "UPDATE budget_plan SET status = ?, approved_by = ? WHERE budget_id = ?");
        mysqli_stmt_bind_param($statement, "sii", $status, $approvedBy, $budgetId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Budget decision saved.");
    } elseif ($action === "delete_budget") {
        $budgetId = (int) $_POST["record_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM budget_plan WHERE budget_id = ?");
        mysqli_stmt_bind_param($statement, "i", $budgetId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Budget request deleted.");
    } elseif ($action === "create_purchase") {
        $supplierId = (int) $_POST["supplier_id"];
        $totalAmount = (float) $_POST["total_amount"];
        $expectedDate = $_POST["expected_date"] !== "" ? $_POST["expected_date"] : null;
        $statement = mysqli_prepare(
            $connection,
            "INSERT INTO purchase_order (supplier_id, requested_by, request_date, approval_status, total_amount, expected_date)
             VALUES (?, ?, CURDATE(), 'pending', ?, ?)"
        );
        mysqli_stmt_bind_param($statement, "iids", $supplierId, $currentEmployeeId, $totalAmount, $expectedDate);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Purchase request recorded.");
    } elseif ($action === "decide_purchase") {
        $purchaseId = (int) $_POST["record_id"];
        $status = $_POST["status"];
        $approvedBy = $status === "approved" ? $currentEmployeeId : null;
        $statement = mysqli_prepare($connection, "UPDATE purchase_order SET approval_status = ?, approved_by = ? WHERE purchase_id = ?");
        mysqli_stmt_bind_param($statement, "sii", $status, $approvedBy, $purchaseId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Purchase decision saved.");
    } elseif ($action === "delete_purchase") {
        $purchaseId = (int) $_POST["record_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM purchase_order WHERE purchase_id = ?");
        mysqli_stmt_bind_param($statement, "i", $purchaseId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Purchase request deleted.");
    } elseif ($action === "create_discount") {
        $policyName = trim($_POST["policy_name"]);
        $discountRate = (float) $_POST["discount_rate"];
        $validFrom = $_POST["valid_from"];
        $validTo = $_POST["valid_to"] !== "" ? $_POST["valid_to"] : null;
        $statement = mysqli_prepare(
            $connection,
            "INSERT INTO discount_policy (policy_name, discount_rate, valid_from, valid_to, status, proposed_by)
             VALUES (?, ?, ?, ?, 'pending', ?)"
        );
        mysqli_stmt_bind_param($statement, "sdssi", $policyName, $discountRate, $validFrom, $validTo, $currentEmployeeId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Discount policy recorded.");
    } elseif ($action === "decide_discount") {
        $policyId = (int) $_POST["record_id"];
        $status = $_POST["status"];
        $approvedBy = $status === "approved" ? $currentEmployeeId : null;
        $statement = mysqli_prepare($connection, "UPDATE discount_policy SET status = ?, approved_by = ? WHERE policy_id = ?");
        mysqli_stmt_bind_param($statement, "sii", $status, $approvedBy, $policyId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Discount policy decision saved.");
    } elseif ($action === "delete_discount") {
        $policyId = (int) $_POST["record_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM discount_policy WHERE policy_id = ?");
        mysqli_stmt_bind_param($statement, "i", $policyId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Discount policy deleted.");
    } elseif ($action === "create_expansion") {
        $planTitle = trim($_POST["plan_title"]);
        $estimatedCost = (float) $_POST["estimated_cost"];
        $statement = mysqli_prepare(
            $connection,
            "INSERT INTO expansion_plan (plan_title, estimated_cost, submitted_date, status, proposed_by)
             VALUES (?, ?, CURDATE(), 'pending', ?)"
        );
        mysqli_stmt_bind_param($statement, "sdi", $planTitle, $estimatedCost, $currentEmployeeId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Expansion plan recorded.");
    } elseif ($action === "decide_expansion") {
        $planId = (int) $_POST["record_id"];
        $status = $_POST["status"];
        $approvedBy = $status === "approved" ? $currentEmployeeId : null;
        $statement = mysqli_prepare($connection, "UPDATE expansion_plan SET status = ?, approved_by = ? WHERE plan_id = ?");
        mysqli_stmt_bind_param($statement, "sii", $status, $approvedBy, $planId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Expansion plan decision saved.");
    } elseif ($action === "delete_expansion") {
        $planId = (int) $_POST["record_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM expansion_plan WHERE plan_id = ?");
        mysqli_stmt_bind_param($statement, "i", $planId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Expansion plan deleted.");
    }

    header("Location: approvals.php");
    exit();
}

$suppliers = mysqli_fetch_all(mysqli_query($connection, "SELECT supplier_id, supplier_name FROM supplier ORDER BY supplier_name"), MYSQLI_ASSOC);

$perPage = 5;

$totalBudgets = countRows($connection, "SELECT COUNT(*) FROM budget_plan");
$totalBudgetPages = max(1, (int) ceil($totalBudgets / $perPage));
$currentBudgetPage = min(getCurrentPage("budget_page"), $totalBudgetPages);
$budgetOffset = ($currentBudgetPage - 1) * $perPage;
$budgets = mysqli_query($connection, "SELECT budget_id, budget_purpose, period, amount, status FROM budget_plan ORDER BY status = 'pending' DESC, budget_id DESC LIMIT $perPage OFFSET $budgetOffset");

$totalPurchases = countRows($connection, "SELECT COUNT(*) FROM purchase_order");
$totalPurchasePages = max(1, (int) ceil($totalPurchases / $perPage));
$currentPurchasePage = min(getCurrentPage("purchase_page"), $totalPurchasePages);
$purchaseOffset = ($currentPurchasePage - 1) * $perPage;
$purchases = mysqli_query(
    $connection,
    "SELECT po.purchase_id, s.supplier_name, po.total_amount, po.request_date, po.approval_status
     FROM purchase_order po JOIN supplier s ON s.supplier_id = po.supplier_id
     ORDER BY po.approval_status = 'pending' DESC, po.purchase_id DESC
     LIMIT $perPage OFFSET $purchaseOffset"
);

$totalDiscounts = countRows($connection, "SELECT COUNT(*) FROM discount_policy");
$totalDiscountPages = max(1, (int) ceil($totalDiscounts / $perPage));
$currentDiscountPage = min(getCurrentPage("discount_page"), $totalDiscountPages);
$discountOffset = ($currentDiscountPage - 1) * $perPage;
$discounts = mysqli_query($connection, "SELECT policy_id, policy_name, discount_rate, valid_from, valid_to, status FROM discount_policy ORDER BY status = 'pending' DESC, policy_id DESC LIMIT $perPage OFFSET $discountOffset");

$totalExpansions = countRows($connection, "SELECT COUNT(*) FROM expansion_plan");
$totalExpansionPages = max(1, (int) ceil($totalExpansions / $perPage));
$currentExpansionPage = min(getCurrentPage("expansion_page"), $totalExpansionPages);
$expansionOffset = ($currentExpansionPage - 1) * $perPage;
$expansions = mysqli_query($connection, "SELECT plan_id, plan_title, estimated_cost, submitted_date, status FROM expansion_plan ORDER BY status = 'pending' DESC, plan_id DESC LIMIT $perPage OFFSET $expansionOffset");

$statusClasses = ["pending" => "progress", "approved" => "resolved", "rejected" => "pending"];
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Approvals</title><link rel="stylesheet" href="css/ceo_style.css"></head>
<body>
  <header class="topbar"><h1>CEO / Head Manager</h1><p>Approve budgets, purchases, discount policies, and expansion plans</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Approvals</h2><p>Review and decide on budgets, major purchases, discount policies, and business expansion plans.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3>Budgets and Financial Plans</h3>
        <form method="post" action="approvals.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="create_budget">
          <div class="form-grid">
            <div class="form-group"><label for="budgetPurpose">Purpose</label><input id="budgetPurpose" name="budget_purpose" required></div>
            <div class="form-group"><label for="period">Period</label><input id="period" name="period" placeholder="e.g. 2026-Q3" required></div>
            <div class="form-group"><label for="budgetAmount">Amount</label><input id="budgetAmount" type="number" name="amount" min="0" step="0.01" required></div>
          </div>
          <div class="button-row"><button class="btn" type="submit">Record Budget Request</button></div>
        </form>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Purpose</th><th>Period</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($budgets) === 0): ?><tr><td colspan="5">No budget requests loaded yet.</td></tr><?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($budgets)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["budget_purpose"]) ?></td>
                  <td><?= htmlspecialchars($row["period"]) ?></td>
                  <td><?= number_format($row["amount"], 2) ?></td>
                  <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars(ucfirst($row["status"])) ?></span></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <?php if ($row["status"] === "pending"): ?>
                        <form method="post" action="approvals.php" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"><input type="hidden" name="action" value="decide_budget"><input type="hidden" name="record_id" value="<?= $row["budget_id"] ?>"><input type="hidden" name="status" value="approved"><button class="btn" type="submit">Approve</button></form>
                        <form method="post" action="approvals.php" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"><input type="hidden" name="action" value="decide_budget"><input type="hidden" name="record_id" value="<?= $row["budget_id"] ?>"><input type="hidden" name="status" value="rejected"><button class="btn danger" type="submit">Reject</button></form>
                      <?php endif; ?>
                      <form method="post" action="approvals.php" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"><input type="hidden" name="action" value="delete_budget"><input type="hidden" name="record_id" value="<?= $row["budget_id"] ?>"><button class="btn danger" type="submit" onclick="return confirm('Delete this budget request?');">Delete</button></form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentBudgetPage, $totalBudgets, $perPage, "budget_page"); ?>
      </section>

      <section class="panel">
        <h3>Major Purchases</h3>
        <form method="post" action="approvals.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="create_purchase">
          <div class="form-grid">
            <div class="form-group">
              <label for="supplierId">Supplier</label>
              <select id="supplierId" name="supplier_id" required>
                <option value="">Select supplier</option>
                <?php foreach ($suppliers as $supplier): ?>
                  <option value="<?= $supplier["supplier_id"] ?>"><?= htmlspecialchars($supplier["supplier_name"]) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label for="totalAmount">Amount</label><input id="totalAmount" type="number" name="total_amount" min="0" step="0.01" required></div>
            <div class="form-group"><label for="expectedDate">Expected Date</label><input id="expectedDate" type="date" name="expected_date"></div>
          </div>
          <div class="button-row"><button class="btn" type="submit">Record Purchase Request</button></div>
        </form>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Supplier</th><th>Amount</th><th>Requested</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($purchases) === 0): ?><tr><td colspan="5">No purchase requests loaded yet.</td></tr><?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($purchases)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["supplier_name"]) ?></td>
                  <td><?= number_format($row["total_amount"], 2) ?></td>
                  <td><?= htmlspecialchars($row["request_date"]) ?></td>
                  <td><span class="status <?= $statusClasses[$row["approval_status"]] ?? "progress" ?>"><?= htmlspecialchars(ucfirst($row["approval_status"])) ?></span></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <?php if ($row["approval_status"] === "pending"): ?>
                        <form method="post" action="approvals.php" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"><input type="hidden" name="action" value="decide_purchase"><input type="hidden" name="record_id" value="<?= $row["purchase_id"] ?>"><input type="hidden" name="status" value="approved"><button class="btn" type="submit">Approve</button></form>
                        <form method="post" action="approvals.php" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"><input type="hidden" name="action" value="decide_purchase"><input type="hidden" name="record_id" value="<?= $row["purchase_id"] ?>"><input type="hidden" name="status" value="rejected"><button class="btn danger" type="submit">Reject</button></form>
                      <?php endif; ?>
                      <form method="post" action="approvals.php" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"><input type="hidden" name="action" value="delete_purchase"><input type="hidden" name="record_id" value="<?= $row["purchase_id"] ?>"><button class="btn danger" type="submit" onclick="return confirm('Delete this purchase request?');">Delete</button></form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPurchasePage, $totalPurchases, $perPage, "purchase_page"); ?>
      </section>

      <section class="panel">
        <h3>Discount Policies</h3>
        <form method="post" action="approvals.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="create_discount">
          <div class="form-grid">
            <div class="form-group"><label for="policyName">Policy Name</label><input id="policyName" name="policy_name" required></div>
            <div class="form-group"><label for="discountRate">Discount Rate (%)</label><input id="discountRate" type="number" name="discount_rate" min="0" max="100" step="0.01" required></div>
            <div class="form-group"><label for="validFrom">Valid From</label><input id="validFrom" type="date" name="valid_from" required></div>
            <div class="form-group"><label for="validTo">Valid To</label><input id="validTo" type="date" name="valid_to" data-after="#validFrom"></div>
          </div>
          <div class="button-row"><button class="btn" type="submit">Record Discount Policy</button></div>
        </form>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Policy</th><th>Rate</th><th>Valid Period</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($discounts) === 0): ?><tr><td colspan="5">No discount policies loaded yet.</td></tr><?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($discounts)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["policy_name"]) ?></td>
                  <td><?= number_format($row["discount_rate"], 2) ?>%</td>
                  <td><?= htmlspecialchars($row["valid_from"]) ?> to <?= htmlspecialchars($row["valid_to"] ?? "—") ?></td>
                  <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars(ucfirst($row["status"])) ?></span></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <?php if ($row["status"] === "pending"): ?>
                        <form method="post" action="approvals.php" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"><input type="hidden" name="action" value="decide_discount"><input type="hidden" name="record_id" value="<?= $row["policy_id"] ?>"><input type="hidden" name="status" value="approved"><button class="btn" type="submit">Approve</button></form>
                        <form method="post" action="approvals.php" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"><input type="hidden" name="action" value="decide_discount"><input type="hidden" name="record_id" value="<?= $row["policy_id"] ?>"><input type="hidden" name="status" value="rejected"><button class="btn danger" type="submit">Reject</button></form>
                      <?php endif; ?>
                      <form method="post" action="approvals.php" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"><input type="hidden" name="action" value="delete_discount"><input type="hidden" name="record_id" value="<?= $row["policy_id"] ?>"><button class="btn danger" type="submit" onclick="return confirm('Delete this discount policy?');">Delete</button></form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentDiscountPage, $totalDiscounts, $perPage, "discount_page"); ?>
      </section>

      <section class="panel">
        <h3>Business Expansion Plans</h3>
        <form method="post" action="approvals.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="create_expansion">
          <div class="form-grid">
            <div class="form-group"><label for="planTitle">Plan Title</label><input id="planTitle" name="plan_title" required></div>
            <div class="form-group"><label for="estimatedCost">Estimated Cost</label><input id="estimatedCost" type="number" name="estimated_cost" min="0" step="0.01" required></div>
          </div>
          <div class="button-row"><button class="btn" type="submit">Record Expansion Plan</button></div>
        </form>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Plan</th><th>Estimated Cost</th><th>Submitted</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($expansions) === 0): ?><tr><td colspan="5">No expansion plans loaded yet.</td></tr><?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($expansions)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["plan_title"]) ?></td>
                  <td><?= number_format($row["estimated_cost"], 2) ?></td>
                  <td><?= htmlspecialchars($row["submitted_date"]) ?></td>
                  <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars(ucfirst($row["status"])) ?></span></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <?php if ($row["status"] === "pending"): ?>
                        <form method="post" action="approvals.php" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"><input type="hidden" name="action" value="decide_expansion"><input type="hidden" name="record_id" value="<?= $row["plan_id"] ?>"><input type="hidden" name="status" value="approved"><button class="btn" type="submit">Approve</button></form>
                        <form method="post" action="approvals.php" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"><input type="hidden" name="action" value="decide_expansion"><input type="hidden" name="record_id" value="<?= $row["plan_id"] ?>"><input type="hidden" name="status" value="rejected"><button class="btn danger" type="submit">Reject</button></form>
                      <?php endif; ?>
                      <form method="post" action="approvals.php" style="display:inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>"><input type="hidden" name="action" value="delete_expansion"><input type="hidden" name="record_id" value="<?= $row["plan_id"] ?>"><button class="btn danger" type="submit" onclick="return confirm('Delete this expansion plan?');">Delete</button></form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentExpansionPage, $totalExpansions, $perPage, "expansion_page"); ?>
      </section>
    </main>
  </div>
  <script src="js/validate.js"></script>
</body>
</html>
