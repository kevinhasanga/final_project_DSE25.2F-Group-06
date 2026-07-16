<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/helpers.php';
require_login('Inventory Manager');

$activePage = "reports";

$products = getAllProducts($connection);
$reportType = $_GET["report_type"] ?? "";
$productId = (int) ($_GET["product_id"] ?? 0);
$fromDate = $_GET["from_date"] ?? "";
$toDate = $_GET["to_date"] ?? "";

$valuationRows = [];
$movementRows = [];
$turnoverRows = [];

if ($reportType === "valuation") {
    $sql = "SELECT p.product_id, p.product_name, p.selling_price,
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
    $sql .= " GROUP BY p.product_id, p.product_name, p.selling_price ORDER BY p.product_name";

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
    $sql = "SELECT sm.movement_id, p.product_name, sm.movement_type, sm.quantity, sm.movement_date, sm.notes
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
    $sql .= " ORDER BY sm.movement_date DESC";

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports</title>
  <link rel="stylesheet" href="css/inventory_style.css">
</head>
<body>
  <header class="topbar"><h1>Inventory Manager</h1><p>Generate stock valuation, movement, and turnover reports</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Reports</h2><p>Generate valuation, movement, and turnover reports for inventory.</p></section>

      <section class="panel">
        <h3>Generate Report</h3>
        <form method="get" action="reports.php">
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
              <input type="date" id="toDate" name="to_date" value="<?= htmlspecialchars($toDate) ?>">
            </div>
          </div>
          <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Date range is required for the Movement and Turnover reports.</p>
          <div class="button-row"><button class="btn" type="submit">Generate Report</button></div>
        </form>
      </section>

      <?php if ($reportType === "valuation"): ?>
        <section class="panel">
          <h3>Stock Valuation</h3>
          <div class="table-wrapper">
            <table>
              <thead><tr><th>Product</th><th>Current Stock</th><th>Selling Price</th><th>Value</th></tr></thead>
              <tbody>
                <?php if (empty($valuationRows)): ?>
                  <tr><td colspan="4">No data for this selection.</td></tr>
                <?php endif; ?>
                <?php foreach ($valuationRows as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars($row["product_name"]) ?></td>
                    <td><?= (int) $row["current_stock"] ?></td>
                    <td><?= number_format($row["selling_price"], 2) ?></td>
                    <td><?= number_format($row["value"], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "movement"): ?>
        <section class="panel">
          <h3>Stock Movement</h3>
          <div class="table-wrapper">
            <table>
              <thead><tr><th>Product</th><th>Type</th><th>Quantity</th><th>Date</th><th>Notes</th></tr></thead>
              <tbody>
                <?php if ($fromDate === "" || $toDate === ""): ?>
                  <tr><td colspan="5">Select a date range to generate this report.</td></tr>
                <?php elseif (empty($movementRows)): ?>
                  <tr><td colspan="5">No movements found for this selection.</td></tr>
                <?php endif; ?>
                <?php foreach ($movementRows as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars($row["product_name"]) ?></td>
                    <td><?= htmlspecialchars(ucfirst($row["movement_type"])) ?></td>
                    <td><?= (int) $row["quantity"] ?></td>
                    <td><?= htmlspecialchars($row["movement_date"]) ?></td>
                    <td><?= htmlspecialchars($row["notes"] ?? "") ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>

      <?php if ($reportType === "turnover"): ?>
        <section class="panel">
          <h3>Inventory Turnover</h3>
          <div class="table-wrapper">
            <table>
              <thead><tr><th>Product</th><th>Opening Stock</th><th>Closing Stock</th><th>Moved Out</th><th>Turnover Rate</th></tr></thead>
              <tbody>
                <?php if ($fromDate === "" || $toDate === ""): ?>
                  <tr><td colspan="5">Select a date range to generate this report.</td></tr>
                <?php elseif (empty($turnoverRows)): ?>
                  <tr><td colspan="5">No data for this selection.</td></tr>
                <?php endif; ?>
                <?php foreach ($turnoverRows as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars($row["product_name"]) ?></td>
                    <td><?= (int) $row["opening_stock"] ?></td>
                    <td><?= (int) $row["closing_stock"] ?></td>
                    <td><?= (int) $row["sold_quantity"] ?></td>
                    <td><?= $row["turnover_rate"] ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
