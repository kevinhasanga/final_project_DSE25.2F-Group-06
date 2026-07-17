<?php
require_once __DIR__ . '/../../../auth.php';
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../helpers.php';
require_login('Customer Relationship Officer', '../../../login.php');

$activePage = "reports";
$navBasePath = "../";

$customers = getAllCustomers($connection);
$reportType = $_GET["report_type"] ?? "";
$customerId = (int) ($_GET["customer_id"] ?? 0);
$fromDate = $_GET["from_date"] ?? "";
$toDate = $_GET["to_date"] ?? "";

$purchaseRows = [];
$complaintRows = [];
$loyaltyRows = [];
$promotionRows = [];

if ($reportType === "purchases" && $fromDate !== "" && $toDate !== "") {
    $sql = "SELECT so.order_id, cu.customer_id, cu.name AS customer_name, so.order_date, p.product_name, oi.quantity, oi.line_total
            FROM sales_order so
            JOIN customer cu ON cu.customer_id = so.customer_id
            JOIN order_item oi ON oi.order_id = so.order_id
            JOIN product p ON p.product_id = oi.product_id
            WHERE DATE(so.order_date) BETWEEN ? AND ?";
    $params = [$fromDate, $toDate];
    $types = "ss";
    if ($customerId > 0) {
        $sql .= " AND so.customer_id = ?";
        $params[] = $customerId;
        $types .= "i";
    }
    $sql .= " ORDER BY so.order_date DESC";
    $statement = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($statement, $types, ...$params);
    mysqli_stmt_execute($statement);
    $purchaseRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}

if ($reportType === "complaints" && $fromDate !== "" && $toDate !== "") {
    $sql = "SELECT c.complaint_id, cu.name AS customer_name, c.description, c.status, c.created_date, c.resolved_date
            FROM complaint c
            JOIN customer cu ON cu.customer_id = c.customer_id
            WHERE DATE(c.created_date) BETWEEN ? AND ?";
    $params = [$fromDate, $toDate];
    $types = "ss";
    if ($customerId > 0) {
        $sql .= " AND c.customer_id = ?";
        $params[] = $customerId;
        $types .= "i";
    }
    $sql .= " ORDER BY c.created_date DESC";
    $statement = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($statement, $types, ...$params);
    mysqli_stmt_execute($statement);
    $complaintRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}

if ($reportType === "loyalty") {
    $sql = "SELECT customer_id, name, loyalty_points FROM customer";
    $params = [];
    $types = "";
    if ($customerId > 0) {
        $sql .= " WHERE customer_id = ?";
        $params[] = $customerId;
        $types = "i";
    }
    $sql .= " ORDER BY loyalty_points DESC";
    $statement = mysqli_prepare($connection, $sql);
    if ($types !== "") {
        mysqli_stmt_bind_param($statement, $types, ...$params);
    }
    mysqli_stmt_execute($statement);
    $loyaltyRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}

if ($reportType === "promotions" && $fromDate !== "" && $toDate !== "") {
    $statement = mysqli_prepare(
        $connection,
        "SELECT title, customer_group, message, sent_at
         FROM promotional_notification
         WHERE DATE(sent_at) BETWEEN ? AND ?
         ORDER BY sent_at DESC"
    );
    mysqli_stmt_bind_param($statement, "ss", $fromDate, $toDate);
    mysqli_stmt_execute($statement);
    $promotionRows = mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
    mysqli_stmt_close($statement);
}

// --- Group and summarize each report the way a manager would actually read it ---

// Purchases: group by customer, ranked by spend, so the top accounts show first.
$purchasesByCustomer = [];
$purchasesGrandTotal = 0;
foreach ($purchaseRows as $row) {
    $key = $row["customer_id"];
    if (!isset($purchasesByCustomer[$key])) {
        $purchasesByCustomer[$key] = ["name" => $row["customer_name"], "rows" => [], "quantity" => 0, "amount" => 0];
    }
    $purchasesByCustomer[$key]["rows"][] = $row;
    $purchasesByCustomer[$key]["quantity"] += (int) $row["quantity"];
    $purchasesByCustomer[$key]["amount"] += $row["line_total"];
    $purchasesGrandTotal += $row["line_total"];
}
uasort($purchasesByCustomer, fn($a, $b) => $b["amount"] <=> $a["amount"]);
$topCustomerName = "";
$topCustomerAmount = 0;
if (!empty($purchasesByCustomer)) {
    $topCustomerName = reset($purchasesByCustomer)["name"];
    $topCustomerAmount = reset($purchasesByCustomer)["amount"];
}

// Complaints: group by status in priority order, with an age figure (days still open, or days it took to resolve).
$complaintStatusLabels = ["open" => "Open", "in_progress" => "In Progress", "resolved" => "Resolved"];
$complaintStatusOrder = array_keys($complaintStatusLabels);
$complaintsByStatus = [];
$resolvedCount = 0;
foreach ($complaintRows as $row) {
    $status = $row["status"];
    $createdAt = new DateTime($row["created_date"]);
    if ($status === "resolved" && $row["resolved_date"]) {
        $endAt = new DateTime($row["resolved_date"]);
        $row["age_days"] = $createdAt->diff($endAt)->days;
        $resolvedCount++;
    } else {
        $endAt = new DateTime();
        $row["age_days"] = $createdAt->diff($endAt)->days;
    }
    if (!isset($complaintsByStatus[$status])) {
        $complaintsByStatus[$status] = ["rows" => [], "count" => 0];
    }
    $complaintsByStatus[$status]["rows"][] = $row;
    $complaintsByStatus[$status]["count"]++;
}
uksort($complaintsByStatus, fn($a, $b) => array_search($a, $complaintStatusOrder) <=> array_search($b, $complaintStatusOrder));
$totalComplaints = count($complaintRows);
$openComplaints = ($complaintsByStatus["open"]["count"] ?? 0) + ($complaintsByStatus["in_progress"]["count"] ?? 0);
$resolutionRate = $totalComplaints > 0 ? round($resolvedCount / $totalComplaints * 100, 1) : 0;

// Loyalty: tier customers into named bands so campaigns can target a segment, not a raw points list.
function loyaltyTier($points)
{
    if ($points >= 400) {
        return "Platinum";
    }
    if ($points >= 250) {
        return "Gold";
    }
    if ($points >= 150) {
        return "Silver";
    }
    return "Bronze";
}
$loyaltyTierOrder = ["Platinum", "Gold", "Silver", "Bronze"];
$loyaltyByTier = [];
foreach ($loyaltyRows as $row) {
    $tier = loyaltyTier((int) $row["loyalty_points"]);
    if (!isset($loyaltyByTier[$tier])) {
        $loyaltyByTier[$tier] = ["rows" => [], "count" => 0];
    }
    $loyaltyByTier[$tier]["rows"][] = $row;
    $loyaltyByTier[$tier]["count"]++;
}
uksort($loyaltyByTier, fn($a, $b) => array_search($a, $loyaltyTierOrder) <=> array_search($b, $loyaltyTierOrder));

// Promotions: group by customer segment so reach per segment is obvious.
$promoGroupLabels = ["corporate" => "Corporate", "loyalty" => "Loyalty Members", "all" => "All Customers"];
$promoGroupOrder = array_keys($promoGroupLabels);
$promotionsByGroup = [];
foreach ($promotionRows as $row) {
    $group = $row["customer_group"];
    if (!isset($promotionsByGroup[$group])) {
        $promotionsByGroup[$group] = ["rows" => [], "count" => 0];
    }
    $promotionsByGroup[$group]["rows"][] = $row;
    $promotionsByGroup[$group]["count"]++;
}
uksort($promotionsByGroup, function ($a, $b) use ($promoGroupOrder) {
    $posA = array_search($a, $promoGroupOrder);
    $posB = array_search($b, $promoGroupOrder);
    $posA = $posA === false ? count($promoGroupOrder) : $posA;
    $posB = $posB === false ? count($promoGroupOrder) : $posB;
    return $posA <=> $posB;
});

// --- Printable report header content ---

$reportLabels = [
    "purchases" => "Customer Purchases Report",
    "complaints" => "Complaints Report",
    "loyalty" => "Loyalty Points Report",
    "promotions" => "Promotions Report",
];
$reportTitle = $reportLabels[$reportType] ?? "";

$selectedCustomerName = "All customers";
if ($customerId > 0) {
    foreach ($customers as $customer) {
        if ((int) $customer["customer_id"] === $customerId) {
            $selectedCustomerName = $customer["name"];
            break;
        }
    }
}
$filterParts = [];
if (in_array($reportType, ["purchases", "complaints", "loyalty"], true)) {
    $filterParts[] = "Customer: " . $selectedCustomerName;
}
if (in_array($reportType, ["purchases", "complaints", "promotions"], true) && $fromDate !== "" && $toDate !== "") {
    $filterParts[] = "Period: " . $fromDate . " to " . $toDate;
}
$generatedBy = $_SESSION["full_name"] ?? $_SESSION["username"] ?? "User";
$generatedOn = date("Y-m-d H:i");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Reports</title><link rel="stylesheet" href="../css/cro_style.css?v=<?= filemtime(__DIR__ . '/../css/cro_style.css') ?>">
</head>
<body>
  <header class="topbar no-print"><h1>Customer Relationship Officer</h1><p>Generate customer activity reports</p></header>
  <div class="layout">
    <?php include __DIR__ . '/../nav.php'; ?>
    <main class="content">
      <section class="page-title no-print"><h2>Reports</h2><p>Filter and generate customer activity reports.</p></section>

      <section class="panel no-print">
        <h3>Report Filters</h3>
        <form method="get" action="reports.php">
          <div class="form-grid">
            <div class="form-group">
              <label for="reportType">Report Type</label>
              <select id="reportType" name="report_type" required>
                <option value="">Select type</option>
                <option value="purchases" <?= $reportType === "purchases" ? "selected" : "" ?>>Purchases</option>
                <option value="complaints" <?= $reportType === "complaints" ? "selected" : "" ?>>Complaints</option>
                <option value="loyalty" <?= $reportType === "loyalty" ? "selected" : "" ?>>Loyalty</option>
                <option value="promotions" <?= $reportType === "promotions" ? "selected" : "" ?>>Promotions</option>
              </select>
            </div>
            <div class="form-group">
              <label for="customerId">Customer (optional)</label>
              <select id="customerId" name="customer_id">
                <option value="0">All customers</option>
                <?php foreach ($customers as $customer): ?>
                  <option value="<?= $customer["customer_id"] ?>" <?= $customerId === (int) $customer["customer_id"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($customer["name"]) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="fromDate">From Date</label>
              <input type="date" id="fromDate" name="from_date" value="<?= htmlspecialchars($fromDate) ?>">
            </div>
            <div class="form-group">
              <label for="toDate">To Date</label>
              <input type="date" id="toDate" name="to_date" value="<?= htmlspecialchars($toDate) ?>">
            </div>
          </div>
          <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Date range is not required for the Loyalty report.</p>
          <div class="button-row"><button class="btn" type="submit">Generate Report</button></div>
        </form>
      </section>

      <?php if ($reportType === "purchases"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>
            Purchases
            <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button>
          </h3>
          <?php if ($fromDate === "" || $toDate === ""): ?>
            <p style="padding: 20px; color: #7f93b3;">Select a date range to generate this report.</p>
          <?php else: ?>
            <div class="report-summary">
              <div class="stat">
                <p class="label">Total Revenue</p>
                <p class="value"><?= number_format($purchasesGrandTotal, 2) ?></p>
              </div>
              <div class="stat">
                <p class="label">Customers</p>
                <p class="value"><?= count($purchasesByCustomer) ?></p>
              </div>
              <div class="stat">
                <p class="label">Top Customer</p>
                <p class="value" style="font-size: 15px;"><?= $topCustomerName !== "" ? htmlspecialchars($topCustomerName) : "—" ?></p>
              </div>
            </div>
            <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Grouped by customer and ranked by spend, so the accounts worth the most attention show first — useful for prioritizing account visits or loyalty outreach.</p>
            <div class="table-wrapper">
              <table>
                <thead><tr><th>Order ID</th><th>Item</th><th>Quantity</th><th>Line Total</th></tr></thead>
                <tbody>
                  <?php if (empty($purchasesByCustomer)): ?>
                    <tr><td colspan="4">No purchases found for this selection.</td></tr>
                  <?php endif; ?>
                  <?php foreach ($purchasesByCustomer as $group): ?>
                    <tr class="group-heading">
                      <td colspan="4"><?= htmlspecialchars($group["name"]) ?> — <?= $group["quantity"] ?> items, total <?= number_format($group["amount"], 2) ?></td>
                    </tr>
                    <?php foreach ($group["rows"] as $row): ?>
                      <tr>
                        <td><?= $row["order_id"] ?></td>
                        <td><?= htmlspecialchars($row["product_name"]) ?></td>
                        <td><?= (int) $row["quantity"] ?></td>
                        <td><?= number_format($row["line_total"], 2) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                </tbody>
                <?php if (!empty($purchasesByCustomer)): ?>
                  <tfoot>
                    <tr class="grand-total"><td colspan="3">Grand Total</td><td><?= number_format($purchasesGrandTotal, 2) ?></td></tr>
                  </tfoot>
                <?php endif; ?>
              </table>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "complaints"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>
            Complaints
            <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button>
          </h3>
          <?php if ($fromDate === "" || $toDate === ""): ?>
            <p style="padding: 20px; color: #7f93b3;">Select a date range to generate this report.</p>
          <?php else: ?>
            <div class="report-summary">
              <div class="stat">
                <p class="label">Total Complaints</p>
                <p class="value"><?= $totalComplaints ?></p>
              </div>
              <div class="stat warning">
                <p class="label">Still Needs Attention</p>
                <p class="value"><?= $openComplaints ?></p>
              </div>
              <div class="stat">
                <p class="label">Resolution Rate</p>
                <p class="value"><?= $resolutionRate ?>%</p>
              </div>
            </div>
            <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Grouped by status with Open first, since those need action soonest. Age is days still outstanding for Open/In Progress, or days it took to resolve for Resolved — use it to catch complaints that have been sitting too long.</p>
            <div class="table-wrapper">
              <table>
                <thead><tr><th>ID</th><th>Customer</th><th>Description</th><th>Age (days)</th></tr></thead>
                <tbody>
                  <?php if (empty($complaintsByStatus)): ?>
                    <tr><td colspan="4">No complaints found for this selection.</td></tr>
                  <?php endif; ?>
                  <?php foreach ($complaintsByStatus as $status => $group): ?>
                    <tr class="group-heading">
                      <td colspan="4"><?= htmlspecialchars($complaintStatusLabels[$status] ?? ucfirst($status)) ?> — <?= $group["count"] ?> complaints</td>
                    </tr>
                    <?php foreach ($group["rows"] as $row): ?>
                      <tr>
                        <td><?= $row["complaint_id"] ?></td>
                        <td><?= htmlspecialchars($row["customer_name"]) ?></td>
                        <td><?= htmlspecialchars($row["description"]) ?></td>
                        <td><?= $row["age_days"] ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "loyalty"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>
            Loyalty Points
            <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button>
          </h3>
          <div class="report-summary">
            <?php foreach ($loyaltyTierOrder as $tier): ?>
              <div class="stat">
                <p class="label"><?= htmlspecialchars($tier) ?></p>
                <p class="value"><?= $loyaltyByTier[$tier]["count"] ?? 0 ?></p>
              </div>
            <?php endforeach; ?>
          </div>
          <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Grouped into tiers (Platinum 400+, Gold 250+, Silver 150+, Bronze below) — use this to target the top tier for VIP perks or the bottom tier for re-engagement offers.</p>
          <div class="table-wrapper">
            <table>
              <thead><tr><th>Customer ID</th><th>Name</th><th>Loyalty Points</th></tr></thead>
              <tbody>
                <?php if (empty($loyaltyByTier)): ?>
                  <tr><td colspan="3">No customers found.</td></tr>
                <?php endif; ?>
                <?php foreach ($loyaltyByTier as $tier => $group): ?>
                  <tr class="group-heading">
                    <td colspan="3"><?= htmlspecialchars($tier) ?> — <?= $group["count"] ?> customers</td>
                  </tr>
                  <?php foreach ($group["rows"] as $row): ?>
                    <tr>
                      <td><?= $row["customer_id"] ?></td>
                      <td><?= htmlspecialchars($row["name"]) ?></td>
                      <td><?= (int) $row["loyalty_points"] ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "promotions"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>
            Promotions
            <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button>
          </h3>
          <?php if ($fromDate === "" || $toDate === ""): ?>
            <p style="padding: 20px; color: #7f93b3;">Select a date range to generate this report.</p>
          <?php else: ?>
            <div class="report-summary">
              <div class="stat">
                <p class="label">Total Sent</p>
                <p class="value"><?= count($promotionRows) ?></p>
              </div>
              <div class="stat">
                <p class="label">Segments Reached</p>
                <p class="value"><?= count($promotionsByGroup) ?></p>
              </div>
            </div>
            <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Grouped by customer segment, so reach per segment is easy to compare — useful for checking a campaign wasn't skewed to one group.</p>
            <div class="table-wrapper">
              <table>
                <thead><tr><th>Title</th><th>Message</th><th>Sent At</th></tr></thead>
                <tbody>
                  <?php if (empty($promotionsByGroup)): ?>
                    <tr><td colspan="3">No promotions found for this selection.</td></tr>
                  <?php endif; ?>
                  <?php foreach ($promotionsByGroup as $group => $data): ?>
                    <tr class="group-heading">
                      <td colspan="3"><?= htmlspecialchars($promoGroupLabels[$group] ?? ucfirst($group)) ?> — <?= $data["count"] ?> sent</td>
                    </tr>
                    <?php foreach ($data["rows"] as $row): ?>
                      <tr>
                        <td><?= htmlspecialchars($row["title"]) ?></td>
                        <td><?= htmlspecialchars($row["message"]) ?></td>
                        <td><?= htmlspecialchars($row["sent_at"]) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
