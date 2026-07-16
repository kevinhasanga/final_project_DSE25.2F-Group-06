<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Inventory Manager');

$activePage = "movements";
$currentEmployeeId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: stock_movements.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "save") {
        $movementId = (int) ($_POST["movement_id"] ?? 0);
        $productId = (int) $_POST["product_id"];
        $batchId = (int) ($_POST["batch_id"] ?? 0) ?: null;
        $movementType = $_POST["movement_type"];
        $quantity = (int) $_POST["quantity"];
        $movementDate = $_POST["movement_date"];
        $notes = trim($_POST["notes"] ?? "");

        if ($movementId > 0) {
            $statement = mysqli_prepare(
                $connection,
                "UPDATE stock_movement SET product_id = ?, batch_id = ?, movement_type = ?, quantity = ?, movement_date = ?, notes = ?
                 WHERE movement_id = ?"
            );
            mysqli_stmt_bind_param($statement, "iisissi", $productId, $batchId, $movementType, $quantity, $movementDate, $notes, $movementId);
        } else {
            $statement = mysqli_prepare(
                $connection,
                "INSERT INTO stock_movement (product_id, batch_id, movement_type, quantity, movement_date, recorded_by, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($statement, "iisisis", $productId, $batchId, $movementType, $quantity, $movementDate, $currentEmployeeId, $notes);
        }

        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Stock movement saved.");
        header("Location: stock_movements.php");
        exit();
    }

    if ($action === "delete") {
        $movementId = (int) $_POST["movement_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM stock_movement WHERE movement_id = ? AND movement_type IN ('return', 'transfer')");
        mysqli_stmt_bind_param($statement, "i", $movementId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Stock movement deleted.");
        header("Location: stock_movements.php");
        exit();
    }
}

$editRecord = null;
if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];
    $statement = mysqli_prepare($connection, "SELECT * FROM stock_movement WHERE movement_id = ? AND movement_type IN ('return', 'transfer')");
    mysqli_stmt_bind_param($statement, "i", $editId);
    mysqli_stmt_execute($statement);
    $editRecord = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

$products = getAllProducts($connection);

$perPage = 10;
$totalMovements = countRows($connection, "SELECT COUNT(*) FROM stock_movement WHERE movement_type IN ('return', 'transfer')");
$totalMovementPages = max(1, (int) ceil($totalMovements / $perPage));
$currentPage = min(getCurrentPage(), $totalMovementPages);
$offset = ($currentPage - 1) * $perPage;

$movements = mysqli_query(
    $connection,
    "SELECT sm.movement_id, p.product_name, sm.batch_id, sm.movement_type, sm.quantity, sm.movement_date, sm.notes
     FROM stock_movement sm
     JOIN product p ON p.product_id = sm.product_id
     WHERE sm.movement_type IN ('return', 'transfer')
     ORDER BY sm.movement_date DESC, sm.movement_id DESC
     LIMIT $perPage OFFSET $offset"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Returns and Transfers</title>
  <link rel="stylesheet" href="css/inventory_style.css">
</head>
<body>
  <header class="topbar"><h1>Inventory Manager</h1><p>Record stock returns and transfers</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Returns & Transfers</h2><p>Record returned stock and stock transfers between locations.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3><?= $editRecord ? "Edit Movement" : "Add Return / Transfer" ?></h3>
        <form method="post" action="stock_movements.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="movement_id" value="<?= htmlspecialchars($editRecord["movement_id"] ?? "") ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="productId">Product</label>
              <select id="productId" name="product_id" required>
                <option value="">Select product</option>
                <?php foreach ($products as $product): ?>
                  <option value="<?= $product["product_id"] ?>" <?= ($editRecord["product_id"] ?? null) == $product["product_id"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($product["product_name"]) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="movementType">Movement Type</label>
              <select id="movementType" name="movement_type" required>
                <option value="">Select type</option>
                <option value="return" <?= ($editRecord["movement_type"] ?? "") === "return" ? "selected" : "" ?>>Return</option>
                <option value="transfer" <?= ($editRecord["movement_type"] ?? "") === "transfer" ? "selected" : "" ?>>Transfer</option>
              </select>
            </div>
            <div class="form-group">
              <label for="quantity">Quantity</label>
              <input type="number" id="quantity" name="quantity" min="1" value="<?= htmlspecialchars($editRecord["quantity"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="movementDate">Date</label>
              <input type="date" id="movementDate" name="movement_date" value="<?= htmlspecialchars($editRecord["movement_date"] ?? "") ?>" required>
            </div>
            <div class="form-group full-width">
              <label for="notes">Notes (e.g. from / to location, reason)</label>
              <textarea id="notes" name="notes"><?= htmlspecialchars($editRecord["notes"] ?? "") ?></textarea>
            </div>
          </div>
          <div class="button-row">
            <button class="btn" type="submit">Save Movement</button>
            <?php if ($editRecord): ?>
              <a class="btn secondary" href="stock_movements.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </section>

      <section class="panel">
        <h3>Return and Transfer Records</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Product</th><th>Type</th><th>Quantity</th><th>Date</th><th>Notes</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($movements) === 0): ?>
                <tr><td colspan="6">No return or transfer records loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($movements)): ?>
                <tr>
                  <td><?= htmlspecialchars($row["product_name"]) ?></td>
                  <td><?= htmlspecialchars(ucfirst($row["movement_type"])) ?></td>
                  <td><?= (int) $row["quantity"] ?></td>
                  <td><?= htmlspecialchars($row["movement_date"]) ?></td>
                  <td><?= htmlspecialchars($row["notes"] ?? "") ?></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <a class="btn secondary" href="stock_movements.php?edit=<?= $row["movement_id"] ?>">Edit</a>
                      <form method="post" action="stock_movements.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="movement_id" value="<?= $row["movement_id"] ?>">
                        <button class="btn danger" type="submit" onclick="return confirm('Delete this movement record?');">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalMovements, $perPage); ?>
      </section>
    </main>
  </div>
</body>
</html>
