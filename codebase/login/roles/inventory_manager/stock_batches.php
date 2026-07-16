<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Inventory Manager');

$activePage = "batches";
$currentEmployeeId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: stock_batches.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "create") {
        $productId = (int) $_POST["product_id"];
        $supplierId = (int) ($_POST["supplier_id"] ?? 0) ?: null;
        $receivedDate = $_POST["received_date"];
        $expiryDate = $_POST["expiry_date"] !== "" ? $_POST["expiry_date"] : null;
        $quantity = (int) $_POST["original_quantity"];

        $statement = mysqli_prepare(
            $connection,
            "INSERT INTO stock_batch (product_id, supplier_id, received_date, expiry_date, original_quantity, current_quantity, status)
             VALUES (?, ?, ?, ?, ?, ?, 'active')"
        );
        mysqli_stmt_bind_param($statement, "iissii", $productId, $supplierId, $receivedDate, $expiryDate, $quantity, $quantity);
        mysqli_stmt_execute($statement);
        $newBatchId = mysqli_insert_id($connection);
        mysqli_stmt_close($statement);

        $movementStatement = mysqli_prepare(
            $connection,
            "INSERT INTO stock_movement (product_id, batch_id, movement_type, quantity, movement_date, recorded_by, notes)
             VALUES (?, ?, 'in', ?, NOW(), ?, 'Incoming stock received')"
        );
        mysqli_stmt_bind_param($movementStatement, "iiii", $productId, $newBatchId, $quantity, $currentEmployeeId);
        mysqli_stmt_execute($movementStatement);
        mysqli_stmt_close($movementStatement);

        setFlash("Stock batch received.");
        header("Location: stock_batches.php");
        exit();
    }

    if ($action === "update") {
        $batchId = (int) $_POST["batch_id"];
        $expiryDate = $_POST["expiry_date"] !== "" ? $_POST["expiry_date"] : null;
        $newQuantity = (int) $_POST["current_quantity"];
        $status = $_POST["status"];
        $notes = trim($_POST["notes"] ?? "");

        $statement = mysqli_prepare($connection, "SELECT product_id, current_quantity FROM stock_batch WHERE batch_id = ?");
        mysqli_stmt_bind_param($statement, "i", $batchId);
        mysqli_stmt_execute($statement);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
        mysqli_stmt_close($statement);

        if ($existing) {
            $statement = mysqli_prepare(
                $connection,
                "UPDATE stock_batch SET expiry_date = ?, current_quantity = ?, status = ? WHERE batch_id = ?"
            );
            mysqli_stmt_bind_param($statement, "sisi", $expiryDate, $newQuantity, $status, $batchId);
            mysqli_stmt_execute($statement);
            mysqli_stmt_close($statement);

            $quantityRemoved = (int) $existing["current_quantity"] - $newQuantity;
            if ($quantityRemoved > 0 && in_array($status, ["damaged", "expired"], true)) {
                $movementStatement = mysqli_prepare(
                    $connection,
                    "INSERT INTO stock_movement (product_id, batch_id, movement_type, quantity, movement_date, recorded_by, notes)
                     VALUES (?, ?, ?, ?, NOW(), ?, ?)"
                );
                mysqli_stmt_bind_param($movementStatement, "iisiss", $existing["product_id"], $batchId, $status, $quantityRemoved, $currentEmployeeId, $notes);
                mysqli_stmt_execute($movementStatement);
                mysqli_stmt_close($movementStatement);
            }
        }

        setFlash("Stock batch updated.");
        header("Location: stock_batches.php");
        exit();
    }

    if ($action === "delete") {
        $batchId = (int) $_POST["batch_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM stock_batch WHERE batch_id = ?");
        mysqli_stmt_bind_param($statement, "i", $batchId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Stock batch deleted.");
        header("Location: stock_batches.php");
        exit();
    }
}

$editRecord = null;
if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];
    $statement = mysqli_prepare(
        $connection,
        "SELECT sb.*, p.product_name FROM stock_batch sb JOIN product p ON p.product_id = sb.product_id WHERE sb.batch_id = ?"
    );
    mysqli_stmt_bind_param($statement, "i", $editId);
    mysqli_stmt_execute($statement);
    $editRecord = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

$products = getAllProducts($connection);
$suppliers = getAllSuppliers($connection);

$perPage = 10;
$totalBatches = countRows($connection, "SELECT COUNT(*) FROM stock_batch");
$totalBatchPages = max(1, (int) ceil($totalBatches / $perPage));
$currentPage = min(getCurrentPage(), $totalBatchPages);
$offset = ($currentPage - 1) * $perPage;

$batches = mysqli_query(
    $connection,
    "SELECT sb.batch_id, sb.product_id, p.product_name, sb.supplier_id, s.supplier_name,
            sb.received_date, sb.expiry_date, sb.original_quantity, sb.current_quantity, sb.status
     FROM stock_batch sb
     JOIN product p ON p.product_id = sb.product_id
     LEFT JOIN supplier s ON s.supplier_id = sb.supplier_id
     ORDER BY sb.received_date DESC, sb.batch_id DESC
     LIMIT $perPage OFFSET $offset"
);

$statusClasses = [
    "active" => "resolved",
    "expiring_soon" => "progress",
    "expired" => "pending",
    "damaged" => "pending",
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Stock Batches</title>
  <link rel="stylesheet" href="css/inventory_style.css">
</head>
<body>
  <header class="topbar"><h1>Inventory Manager</h1><p>Record incoming stock, batch numbers, expiry, and damage</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Stock Batches</h2><p>Receive incoming stock, and track expiry and damaged items per batch.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <?php if ($editRecord): ?>
      <section class="panel">
        <h3>Update Batch — <?= htmlspecialchars($editRecord["product_name"]) ?> (Batch #<?= $editRecord["batch_id"] ?>)</h3>
        <form method="post" action="stock_batches.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="batch_id" value="<?= $editRecord["batch_id"] ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="expiryDate">Expiry Date</label>
              <input type="date" id="expiryDate" name="expiry_date" value="<?= htmlspecialchars($editRecord["expiry_date"] ?? "") ?>">
            </div>
            <div class="form-group">
              <label for="currentQuantity">Current Quantity</label>
              <input type="number" id="currentQuantity" name="current_quantity" min="0" value="<?= htmlspecialchars($editRecord["current_quantity"]) ?>" required>
            </div>
            <div class="form-group">
              <label for="status">Status</label>
              <select id="status" name="status" required>
                <option value="active" <?= $editRecord["status"] === "active" ? "selected" : "" ?>>Active</option>
                <option value="expiring_soon" <?= $editRecord["status"] === "expiring_soon" ? "selected" : "" ?>>Expiring Soon</option>
                <option value="expired" <?= $editRecord["status"] === "expired" ? "selected" : "" ?>>Expired</option>
                <option value="damaged" <?= $editRecord["status"] === "damaged" ? "selected" : "" ?>>Damaged</option>
              </select>
            </div>
            <div class="form-group full-width">
              <label for="notes">Notes</label>
              <textarea id="notes" name="notes" placeholder="Reason for quantity change, e.g. damaged in transit"></textarea>
            </div>
          </div>
          <p style="padding: 0 20px; color: #7f93b3; font-size: 13px;">Lowering the current quantity while status is Expired or Damaged automatically logs a stock movement for the removed quantity.</p>
          <div class="button-row">
            <button class="btn" type="submit">Save Update</button>
            <a class="btn secondary" href="stock_batches.php">Cancel</a>
          </div>
        </form>
      </section>
      <?php else: ?>
      <section class="panel">
        <h3>Receive Incoming Stock</h3>
        <form method="post" action="stock_batches.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="create">
          <div class="form-grid">
            <div class="form-group">
              <label for="productId">Product</label>
              <select id="productId" name="product_id" required>
                <option value="">Select product</option>
                <?php foreach ($products as $product): ?>
                  <option value="<?= $product["product_id"] ?>"><?= htmlspecialchars($product["product_name"]) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="supplierId">Supplier</label>
              <select id="supplierId" name="supplier_id">
                <option value="">Unknown / none</option>
                <?php foreach ($suppliers as $supplier): ?>
                  <option value="<?= $supplier["supplier_id"] ?>"><?= htmlspecialchars($supplier["supplier_name"]) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="receivedDate">Received Date</label>
              <input type="date" id="receivedDate" name="received_date" required>
            </div>
            <div class="form-group">
              <label for="expiryDate">Expiry Date</label>
              <input type="date" id="expiryDate" name="expiry_date">
            </div>
            <div class="form-group">
              <label for="originalQuantity">Quantity</label>
              <input type="number" id="originalQuantity" name="original_quantity" min="1" required>
            </div>
          </div>
          <div class="button-row">
            <button class="btn" type="submit">Save Stock</button>
          </div>
        </form>
      </section>
      <?php endif; ?>

      <section class="panel">
        <h3>Batch Records</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Batch</th><th>Product</th><th>Supplier</th><th>Received</th><th>Expiry</th><th>Original</th><th>Current</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($batches) === 0): ?>
                <tr><td colspan="9">No stock batches loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($batches)): ?>
                <tr>
                  <td><?= $row["batch_id"] ?></td>
                  <td><?= htmlspecialchars($row["product_name"]) ?></td>
                  <td><?= htmlspecialchars($row["supplier_name"] ?? "—") ?></td>
                  <td><?= htmlspecialchars($row["received_date"]) ?></td>
                  <td><?= htmlspecialchars($row["expiry_date"] ?? "—") ?></td>
                  <td><?= (int) $row["original_quantity"] ?></td>
                  <td><?= (int) $row["current_quantity"] ?></td>
                  <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars(ucfirst(str_replace("_", " ", $row["status"]))) ?></span></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <a class="btn secondary" href="stock_batches.php?edit=<?= $row["batch_id"] ?>">Update</a>
                      <form method="post" action="stock_batches.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="batch_id" value="<?= $row["batch_id"] ?>">
                        <button class="btn danger" type="submit" onclick="return confirm('Delete this batch record?');">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalBatches, $perPage); ?>
      </section>
    </main>
  </div>
</body>
</html>
