<?php
require_once __DIR__ . '/../../../auth.php';
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../helpers.php';
require_login('Inventory Manager', '../../../login.php');

$activePage = "reports";
$navBasePath = "../";
$printView = ($_GET["print"] ?? "") === "1";

$products = getAllProducts($connection);
$reportType = $_GET["report_type"] ?? "";
$productId = (int) ($_GET["product_id"] ?? 0);
$fromDate = $_GET["from_date"] ?? "";
$toDate = $_GET["to_date"] ?? "";

$valuationRows = [];
$movementRows = [];
$turnoverRows = [];

if ($reportType === "valuation") {
    $sql = "SELECT p.product_id, p.product_name, p.category, p.selling_price,
                   COALESCE(SUM(sb.current_quantity), 0) AS current_stock
            FROM product p
            LEFT JOIN stock_batch sb ON sb.product_id = p.product_id AND sb.status = 'active'";
    $params = [];
    $types = "";
    if ($productId > 0) {
        $sql .= " WHERE p.product_id = ?";
        $params[] = $productId;
        $types = "i";
    }
    $sql .= " GROUP BY p.product_id, p.product_name, p.category, p.selling_price ORDER BY p.category, p.product_name";

    $statement = mysqli_prepare($connection, $sql);
    if ($types !== "") {
        mysqli_stmt_bind_param($statement, $types, ...$params);
    }
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    while ($row = mysqli_fetch_assoc($result)) {
        $row["value"] = $row["current_stock"] * $row["selling_price"];
        $valuationRows[] = $row;
    }
    mysqli_stmt_close($statement);
}

if ($reportType === "movement" && $fromDate !== "" && $toDate !== "") {
    $sql = "SELECT sm.movement_id, p.product_name, p.selling_price, sm.movement_type, sm.quantity, sm.movement_date, sm.notes
            FROM stock_movement sm
            JOIN product p ON p.product_id = sm.product_id
            WHERE sm.movement_date BETWEEN ? AND ?";
    $params = [$fromDate, $toDate . " 23:59:59"];
    $types = "ss";
    if ($productId > 0) {
        $sql .= " AND sm.product_id = ?";
        $params[] = $productId;
        $types .= "i";
    }
    $sql .= " ORDER BY sm.movement_type, sm.movement_date DESC";

    $statement = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($statement, $types, ...$params);
    mysqli_stmt_execute($statement);
    $result = mysqli_stmt_get_result($statement);
    while ($row = mysqli_fetch_assoc($result)) {
        $movementRows[] = $row;
    }
    mysqli_stmt_close($statement);
}

if ($reportType === "turnover" && $fromDate !== "" && $toDate !== "") {
    $stockInTypes = ["in", "return"];
    $stockOutTypes = ["out", "damaged", "expired", "transfer"];

    foreach ($products as $product) {
        if ($productId > 0 && $productId !== (int) $product["product_id"]) {
            continue;
        }

        $pid = (int) $product["product_id"];

        $closingStatement = mysqli_prepare(
            $connection,
            "SELECT COALESCE(SUM(current_quantity), 0) FROM stock_batch WHERE product_id = ? AND status = 'active'"
        );
        mysqli_stmt_bind_param($closingStatement, "i", $pid);
        mysqli_stmt_execute($closingStatement);
        $closingStock = (int) mysqli_fetch_row(mysqli_stmt_get_result($closingStatement))[0];
        mysqli_stmt_close($closingStatement);

        $movementStatement = mysqli_prepare(
            $connection,
            "SELECT movement_type, COALESCE(SUM(quantity), 0) AS total
             FROM stock_movement
             WHERE product_id = ? AND movement_date BETWEEN ? AND ?
             GROUP BY movement_type"
        );
        $toDateEnd = $toDate . " 23:59:59";
        mysqli_stmt_bind_param($movementStatement, "iss", $pid, $fromDate, $toDateEnd);
        mysqli_stmt_execute($movementStatement);
        $movementResult = mysqli_stmt_get_result($movementStatement);
        $stockIn = 0;
        $stockOut = 0;
        while ($row = mysqli_fetch_assoc($movementResult)) {
            if (in_array($row["movement_type"], $stockInTypes, true)) {
                $stockIn += (int) $row["total"];
            } elseif (in_array($row["movement_type"], $stockOutTypes, true)) {
                $stockOut += (int) $row["total"];
            }
        }
        mysqli_stmt_close($movementStatement);

        $openingStock = $closingStock - $stockIn + $stockOut;
        $averageStock = ($openingStock + $closingStock) / 2;
        $turnoverRate = $averageStock > 0 ? round($stockOut / $averageStock, 2) : 0;

        $turnoverRows[] = [
            "product_name" => $product["product_name"],
            "opening_stock" => $openingStock,
            "closing_stock" => $closingStock,
            "sold_quantity" => $stockOut,
            "turnover_rate" => $turnoverRate,
        ];
    }
}

// --- Group and summarize each report the way a manager would actually read it ---

$valuationByCategory = [];
$valuationGrandTotal = 0;
foreach ($valuationRows as $row) {
    $category = ($row["category"] ?? "") !== "" ? $row["category"] : "Uncategorized";
    if (!isset($valuationByCategory[$category])) {
        $valuationByCategory[$category] = ["rows" => [], "stock" => 0, "value" => 0];
    }
    $valuationByCategory[$category]["rows"][] = $row;
    $valuationByCategory[$category]["stock"] += (int) $row["current_stock"];
    $valuationByCategory[$category]["value"] += $row["value"];
    $valuationGrandTotal += $row["value"];
}
uasort($valuationByCategory, fn($a, $b) => $b["value"] <=> $a["value"]);

$movementTypeOrder = ["in" => "Stock In", "return" => "Customer Return", "out" => "Stock Out (Sold)", "transfer" => "Transferred", "damaged" => "Damaged", "expired" => "Expired"];
$movementByType = [];
$movementTotalIn = 0;
$movementTotalOut = 0;
$shrinkageQuantity = 0;
$shrinkageValue = 0;
$shrinkageTypes = ["damaged", "expired"];
foreach ($movementRows as $row) {
    $type = $row["movement_type"];
    if (!isset($movementByType[$type])) {
        $movementByType[$type] = ["rows" => [], "quantity" => 0];
    }
    $movementByType[$type]["rows"][] = $row;
    $movementByType[$type]["quantity"] += (int) $row["quantity"];

    if (in_array($type, ["in", "return"], true)) {
        $movementTotalIn += (int) $row["quantity"];
    } elseif (in_array($type, ["out", "transfer"], true)) {
        $movementTotalOut += (int) $row["quantity"];
    }
    if (in_array($type, $shrinkageTypes, true)) {
        $shrinkageQuantity += (int) $row["quantity"];
        $shrinkageValue += $row["quantity"] * $row["selling_price"];
    }
}
uksort($movementByType, function ($a, $b) use ($movementTypeOrder) {
    $order = array_keys($movementTypeOrder);
    return array_search($a, $order) <=> array_search($b, $order);
});

foreach ($turnoverRows as &$row) {
    $rate = $row["turnover_rate"];
    if ($rate >= 1) {
        $row["classification"] = "Fast-moving";
    } elseif ($rate >= 0.3) {
        $row["classification"] = "Moderate";
    } elseif ($rate > 0) {
        $row["classification"] = "Slow-moving";
    } else {
        $row["classification"] = "Dead stock";
    }
}
unset($row);
usort($turnoverRows, fn($a, $b) => $a["turnover_rate"] <=> $b["turnover_rate"]);
$turnoverSummary = ["Dead stock" => 0, "Slow-moving" => 0, "Moderate" => 0, "Fast-moving" => 0];
foreach ($turnoverRows as $row) {
    $turnoverSummary[$row["classification"]]++;
}

// --- Printable report header content ---

$reportLabels = [
    "valuation" => "Stock Valuation Report",
    "movement" => "Stock Movement Report",
    "turnover" => "Inventory Turnover Report",
];
$reportTitle = $reportLabels[$reportType] ?? "";

$selectedProductName = "All products";
if ($productId > 0) {
    foreach ($products as $product) {
        if ((int) $product["product_id"] === $productId) {
            $selectedProductName = $product["product_name"];
            break;
        }
    }
}
$filterParts = ["Product: " . $selectedProductName];
if ($fromDate !== "" && $toDate !== "") {
    $filterParts[] = "Period: " . $fromDate . " to " . $toDate;
}
$generatedBy = $_SESSION["full_name"] ?? $_SESSION["username"] ?? "User";
$generatedOn = date("Y-m-d H:i");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports</title>
  <link rel="stylesheet" href="../css/inventory_style.css?v=<?= filemtime(__DIR__ . '/../css/inventory_style.css') ?>">
</head>
<body>
<?php if ($printView): ?>
  <main class="content content-embed">
<?php else: ?>
  <header class="topbar no-print"><h1>Inventory Manager</h1><p>Generate stock valuation, movement, and turnover reports</p></header>
  <div class="layout">
    <?php include __DIR__ . '/../nav.php'; ?>
    <main class="content">
      <section class="page-title no-print"><h2>Reports</h2><p>Generate valuation, movement, and turnover reports for inventory.</p></section>

      <section class="panel no-print">
        <h3>Generate Report</h3>
        <form method="get" action="reports.php" id="reportFilterForm">
          <div class="form-grid">
            <div class="form-group">
              <label for="reportType">Report Type</label>
              <select id="reportType" name="report_type" required>
                <option value="">Select type</option>
                <option value="valuation" <?= $reportType === "valuation" ? "selected" : "" ?>>Stock Valuation</option>
                <option value="movement" <?= $reportType === "movement" ? "selected" : "" ?>>Stock Movement</option>
                <option value="turnover" <?= $reportType === "turnover" ? "selected" : "" ?>>Inventory Turnover</option>
              </select>
            </div>
            <div class="form-group">
              <label for="productId">Product (optional)</label>
              <select id="productId" name="product_id">
                <option value="0">All products</option>
                <?php foreach ($products as $product): ?>
                  <option value="<?= $product["product_id"] ?>" <?= $productId === (int) $product["product_id"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($product["product_name"]) ?>
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
              <input type="date" id="toDate" name="to_date" value="<?= htmlspecialchars($toDate) ?>" data-after="#fromDate">
            </div>
          </div>
          <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Date range is required for the Movement and Turnover reports.</p>
          <div class="button-row"><button class="btn" type="submit">Generate Report</button></div>
        </form>
      </section>
<?php endif; ?>

      <?php if ($reportType === "valuation"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>
            Stock Valuation
            <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button>
          </h3>
          <div class="report-summary">
            <div class="stat">
              <p class="label">Total Inventory Value</p>
              <p class="value"><?= number_format($valuationGrandTotal, 2) ?></p>
            </div>
            <div class="stat">
              <p class="label">Categories</p>
              <p class="value"><?= count($valuationByCategory) ?></p>
            </div>
            <div class="stat">
              <p class="label">Products Listed</p>
              <p class="value"><?= count($valuationRows) ?></p>
            </div>
          </div>
          <div class="table-wrapper">
            <table>
              <thead><tr><th>Product</th><th>Current Stock</th><th>Selling Price</th><th>Value</th></tr></thead>
              <tbody>
                <?php if (empty($valuationByCategory)): ?>
                  <tr><td colspan="4">No data for this selection.</td></tr>
                <?php endif; ?>
                <?php foreach ($valuationByCategory as $category => $group): ?>
                  <tr class="group-heading">
                    <td colspan="4">
                      <?= htmlspecialchars($category) ?>
                      — <?= (int) $group["stock"] ?> units on hand, worth <?= number_format($group["value"], 2) ?>
                      (<?= $valuationGrandTotal > 0 ? number_format($group["value"] / $valuationGrandTotal * 100, 1) : "0.0" ?>% of total)
                    </td>
                  </tr>
                  <?php foreach ($group["rows"] as $row): ?>
                    <tr>
                      <td><?= htmlspecialchars($row["product_name"]) ?></td>
                      <td><?= (int) $row["current_stock"] ?></td>
                      <td><?= number_format($row["selling_price"], 2) ?></td>
                      <td><?= number_format($row["value"], 2) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              </tbody>
              <?php if (!empty($valuationByCategory)): ?>
                <tfoot>
                  <tr class="grand-total"><td colspan="3">Grand Total Inventory Value</td><td><?= number_format($valuationGrandTotal, 2) ?></td></tr>
                </tfoot>
              <?php endif; ?>
            </table>
          </div>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "movement"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>
            Stock Movement
            <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button>
          </h3>
          <?php if ($fromDate === "" || $toDate === ""): ?>
            <p style="padding: 20px; color: #7f93b3;">Select a date range to generate this report.</p>
          <?php else: ?>
            <div class="report-summary">
              <div class="stat">
                <p class="label">Stock In (incl. returns)</p>
                <p class="value"><?= $movementTotalIn ?></p>
              </div>
              <div class="stat">
                <p class="label">Stock Out (incl. transfers)</p>
                <p class="value"><?= $movementTotalOut ?></p>
              </div>
              <div class="stat warning">
                <p class="label">Shrinkage (damaged + expired)</p>
                <p class="value"><?= $shrinkageQuantity ?> units</p>
              </div>
              <div class="stat warning">
                <p class="label">Shrinkage Value Lost</p>
                <p class="value"><?= number_format($shrinkageValue, 2) ?></p>
              </div>
            </div>
            <div class="table-wrapper">
              <table>
                <thead><tr><th>Product</th><th>Quantity</th><th>Date</th><th>Notes</th></tr></thead>
                <tbody>
                  <?php if (empty($movementByType)): ?>
                    <tr><td colspan="4">No movements found for this selection.</td></tr>
                  <?php endif; ?>
                  <?php foreach ($movementByType as $type => $group): ?>
                    <tr class="group-heading">
                      <td colspan="4"><?= htmlspecialchars($movementTypeOrder[$type] ?? ucfirst($type)) ?> — <?= $group["quantity"] ?> units total</td>
                    </tr>
                    <?php foreach ($group["rows"] as $row): ?>
                      <tr>
                        <td><?= htmlspecialchars($row["product_name"]) ?></td>
                        <td><?= (int) $row["quantity"] ?></td>
                        <td><?= htmlspecialchars($row["movement_date"]) ?></td>
                        <td><?= htmlspecialchars($row["notes"] ?? "") ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "turnover"): ?>
        <section class="panel">
          <?php include __DIR__ . '/report_header.php'; ?>
          <h3>
            Inventory Turnover
            <button class="btn no-print" type="button" onclick="window.print()" style="float:right;">Print / Save as PDF</button>
          </h3>
          <?php if ($fromDate === "" || $toDate === ""): ?>
            <p style="padding: 20px; color: #7f93b3;">Select a date range to generate this report.</p>
          <?php else: ?>
            <div class="report-summary">
              <div class="stat warning">
                <p class="label">Dead Stock</p>
                <p class="value"><?= $turnoverSummary["Dead stock"] ?></p>
              </div>
              <div class="stat">
                <p class="label">Slow-moving</p>
                <p class="value"><?= $turnoverSummary["Slow-moving"] ?></p>
              </div>
              <div class="stat">
                <p class="label">Moderate</p>
                <p class="value"><?= $turnoverSummary["Moderate"] ?></p>
              </div>
              <div class="stat">
                <p class="label">Fast-moving</p>
                <p class="value"><?= $turnoverSummary["Fast-moving"] ?></p>
              </div>
            </div>
            <div class="table-wrapper">
              <table>
                <thead><tr><th>Product</th><th>Opening Stock</th><th>Closing Stock</th><th>Moved Out</th><th>Turnover Rate</th><th>Status</th></tr></thead>
                <tbody>
                  <?php if (empty($turnoverRows)): ?>
                    <tr><td colspan="6">No data for this selection.</td></tr>
                  <?php endif; ?>
                  <?php
                    $statusClass = [
                        "Dead stock" => "pending",
                        "Slow-moving" => "progress",
                        "Moderate" => "info",
                        "Fast-moving" => "resolved",
                    ];
                  ?>
                  <?php foreach ($turnoverRows as $row): ?>
                    <tr>
                      <td><?= htmlspecialchars($row["product_name"]) ?></td>
                      <td><?= (int) $row["opening_stock"] ?></td>
                      <td><?= (int) $row["closing_stock"] ?></td>
                      <td><?= (int) $row["sold_quantity"] ?></td>
                      <td><?= $row["turnover_rate"] ?></td>
                      <td><span class="status <?= $statusClass[$row["classification"]] ?>"><?= htmlspecialchars($row["classification"]) ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>
<?php if ($printView): ?>
  </main>
<?php else: ?>
    </main>
  </div>
  <script src="../js/report_tab.js"></script>
  <script src="../js/validate.js"></script>
<?php endif; ?>
</body>
</html>
