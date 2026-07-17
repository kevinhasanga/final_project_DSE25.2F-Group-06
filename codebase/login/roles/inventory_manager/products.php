<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Inventory Manager');

$activePage = "products";
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: products.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "save") {
        $productId = (int) ($_POST["product_id"] ?? 0);
        $productName = trim($_POST["product_name"]);
        $category = trim($_POST["category"] ?? "");
        $sellingPrice = (float) $_POST["selling_price"];
        $minStockLevel = (int) $_POST["min_stock_level"];

        if ($productId > 0) {
            $statement = mysqli_prepare(
                $connection,
                "UPDATE product SET product_name = ?, category = ?, selling_price = ?, min_stock_level = ?
                 WHERE product_id = ?"
            );
            mysqli_stmt_bind_param($statement, "ssdii", $productName, $category, $sellingPrice, $minStockLevel, $productId);
        } else {
            $statement = mysqli_prepare(
                $connection,
                "INSERT INTO product (product_name, category, selling_price, min_stock_level)
                 VALUES (?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($statement, "ssdi", $productName, $category, $sellingPrice, $minStockLevel);
        }

        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Product saved.");
        header("Location: products.php");
        exit();
    }

    if ($action === "delete") {
        $productId = (int) $_POST["product_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM product WHERE product_id = ?");
        mysqli_stmt_bind_param($statement, "i", $productId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Product deleted.");
        header("Location: products.php");
        exit();
    }
}

$editRecord = null;
if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];
    $statement = mysqli_prepare($connection, "SELECT * FROM product WHERE product_id = ?");
    mysqli_stmt_bind_param($statement, "i", $editId);
    mysqli_stmt_execute($statement);
    $editRecord = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

$perPage = 10;
$totalProducts = countRows($connection, "SELECT COUNT(*) FROM product");
$totalProductPages = max(1, (int) ceil($totalProducts / $perPage));
$currentPage = min(getCurrentPage(), $totalProductPages);
$offset = ($currentPage - 1) * $perPage;

$products = mysqli_query(
    $connection,
    "SELECT p.product_id, p.product_name, p.category, p.selling_price, p.min_stock_level,
            COALESCE(SUM(sb.current_quantity), 0) AS current_stock
     FROM product p
     LEFT JOIN stock_batch sb ON sb.product_id = p.product_id AND sb.status = 'active'
     GROUP BY p.product_id, p.product_name, p.category, p.selling_price, p.min_stock_level
     ORDER BY p.product_name
     LIMIT $perPage OFFSET $offset"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Products</title>
  <link rel="stylesheet" href="css/inventory_style.css">
</head>
<body>
  <header class="topbar"><h1>Inventory Manager</h1><p>Add, categorize, update, and delete products; set pricing and minimum stock levels</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Products</h2><p>Create and maintain products, pricing, and minimum stock levels.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3><?= $editRecord ? "Edit Product" : "Add Product" ?></h3>
        <form method="post" action="products.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="product_id" value="<?= htmlspecialchars($editRecord["product_id"] ?? "") ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="productName">Product Name</label>
              <input type="text" id="productName" name="product_name" value="<?= htmlspecialchars($editRecord["product_name"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="category">Category</label>
              <input type="text" id="category" name="category" value="<?= htmlspecialchars($editRecord["category"] ?? "") ?>">
            </div>
            <div class="form-group">
              <label for="sellingPrice">Selling Price</label>
              <input type="number" id="sellingPrice" name="selling_price" min="0" step="0.01" value="<?= htmlspecialchars($editRecord["selling_price"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="minStockLevel">Minimum Stock Level</label>
              <input type="number" id="minStockLevel" name="min_stock_level" min="0" value="<?= htmlspecialchars($editRecord["min_stock_level"] ?? "") ?>" required>
            </div>
          </div>
          <div class="button-row">
            <button class="btn" type="submit">Save Product</button>
            <?php if ($editRecord): ?>
              <a class="btn secondary" href="products.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </section>

      <section class="panel">
        <h3>Product Records</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Product ID</th><th>Name</th><th>Category</th><th>Selling Price</th><th>Min Stock</th><th>Current Stock</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($products) === 0): ?>
                <tr><td colspan="7">No product records loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($products)): ?>
                <tr>
                  <td><?= $row["product_id"] ?></td>
                  <td><?= htmlspecialchars($row["product_name"]) ?></td>
                  <td><?= htmlspecialchars($row["category"] ?? "") ?></td>
                  <td><?= number_format($row["selling_price"], 2) ?></td>
                  <td><?= (int) $row["min_stock_level"] ?></td>
                  <td>
                    <span class="status <?= $row["current_stock"] < $row["min_stock_level"] ? "pending" : "resolved" ?>">
                      <?= (int) $row["current_stock"] ?>
                    </span>
                  </td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <a class="btn secondary" href="products.php?edit=<?= $row["product_id"] ?>">Edit</a>
                      <form method="post" action="products.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="product_id" value="<?= $row["product_id"] ?>">
                        <button class="btn danger" type="submit" onclick="return confirm('Delete this product?');">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalProducts, $perPage); ?>
      </section>
    </main>
  </div>
  <script src="js/validate.js"></script>
</body>
</html>
