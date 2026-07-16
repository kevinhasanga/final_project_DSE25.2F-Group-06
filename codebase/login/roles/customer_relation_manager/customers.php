<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Customer Relationship Officer');

$activePage = "customers";
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: customers.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "save") {
        $customerId = (int) ($_POST["customer_id"] ?? 0);
        $name = trim($_POST["name"]);
        $email = trim($_POST["email"]) ?: null;
        $contactNo = trim($_POST["contact_no"]);
        $address = trim($_POST["address"] ?? "");
        $loyaltyPoints = (int) ($_POST["loyalty_points"] ?: 0);

        if ($customerId > 0) {
            $statement = mysqli_prepare(
                $connection,
                "UPDATE customer SET name = ?, contact_no = ?, email = ?, address = ?, loyalty_points = ?
                 WHERE customer_id = ?"
            );
            mysqli_stmt_bind_param($statement, "ssssii", $name, $contactNo, $email, $address, $loyaltyPoints, $customerId);
        } else {
            $statement = mysqli_prepare(
                $connection,
                "INSERT INTO customer (name, contact_no, email, address, loyalty_points) VALUES (?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($statement, "ssssi", $name, $contactNo, $email, $address, $loyaltyPoints);
        }

        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Customer saved.");
        header("Location: customers.php");
        exit();
    }

    if ($action === "delete") {
        $customerId = (int) $_POST["customer_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM customer WHERE customer_id = ?");
        mysqli_stmt_bind_param($statement, "i", $customerId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Customer deleted.");
        header("Location: customers.php");
        exit();
    }
}

$editRecord = null;
if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];
    $statement = mysqli_prepare($connection, "SELECT * FROM customer WHERE customer_id = ?");
    mysqli_stmt_bind_param($statement, "i", $editId);
    mysqli_stmt_execute($statement);
    $editRecord = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

$search = trim($_GET["q"] ?? "");
$sql = "SELECT * FROM customer";
$params = [];
$types = "";
if ($search !== "") {
    $sql .= " WHERE name LIKE ? OR email LIKE ? OR contact_no LIKE ?";
    $like = "%$search%";
    $params = [$like, $like, $like];
    $types = "sss";
}
$countSql = "SELECT COUNT(*) FROM customer";
if ($search !== "") {
    $countSql .= " WHERE name LIKE ? OR email LIKE ? OR contact_no LIKE ?";
}
$totalCustomers = countRows($connection, $countSql, $types, $params);
$perPage = 10;
$totalCustomerPages = max(1, (int) ceil($totalCustomers / $perPage));
$currentPage = min(getCurrentPage(), $totalCustomerPages);
$offset = ($currentPage - 1) * $perPage;

$sql .= " ORDER BY name LIMIT $perPage OFFSET $offset";
$statement = mysqli_prepare($connection, $sql);
if ($types !== "") {
    mysqli_stmt_bind_param($statement, $types, ...$params);
}
mysqli_stmt_execute($statement);
$customers = mysqli_stmt_get_result($statement);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Customers</title><link rel="stylesheet" href="css/cro_style.css">
</head>
<body>
  <header class="topbar"><h1>Customer Relationship Officer</h1><p>Register, update, and search customer details; manage loyalty points</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Customers</h2><p>Maintain the client database and manage loyalty points.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3><?= $editRecord ? "Edit Customer" : "Add Customer" ?></h3>
        <form method="post" action="customers.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="customer_id" value="<?= htmlspecialchars($editRecord["customer_id"] ?? "") ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="name">Full Name</label>
              <input id="name" name="name" value="<?= htmlspecialchars($editRecord["name"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="email">Email</label>
              <input id="email" type="email" name="email" value="<?= htmlspecialchars($editRecord["email"] ?? "") ?>">
            </div>
            <div class="form-group">
              <label for="contactNo">Phone Number</label>
              <input id="contactNo" type="tel" name="contact_no" value="<?= htmlspecialchars($editRecord["contact_no"] ?? "") ?>" required>
            </div>
            <div class="form-group">
              <label for="loyaltyPoints">Loyalty Points</label>
              <input id="loyaltyPoints" type="number" name="loyalty_points" min="0" value="<?= htmlspecialchars($editRecord["loyalty_points"] ?? "0") ?>">
            </div>
            <div class="form-group full-width">
              <label for="address">Address</label>
              <textarea id="address" name="address"><?= htmlspecialchars($editRecord["address"] ?? "") ?></textarea>
            </div>
          </div>
          <div class="button-row">
            <button class="btn" type="submit">Save Customer</button>
            <?php if ($editRecord): ?>
              <a class="btn secondary" href="customers.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </section>

      <section class="panel">
        <h3>Search Customers</h3>
        <form method="get" action="customers.php">
          <div class="form-grid">
            <div class="form-group full-width">
              <label for="q">Name, email, or phone</label>
              <input id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search customers">
            </div>
          </div>
          <div class="button-row">
            <button class="btn" type="submit">Search</button>
            <?php if ($search !== ""): ?>
              <a class="btn secondary" href="customers.php">Clear</a>
            <?php endif; ?>
          </div>
        </form>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Customer ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Loyalty Points</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($customers) === 0): ?>
                <tr><td colspan="6">No customer records loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($customers)): ?>
                <tr>
                  <td><?= $row["customer_id"] ?></td>
                  <td><?= htmlspecialchars($row["name"]) ?></td>
                  <td><?= htmlspecialchars($row["email"] ?? "") ?></td>
                  <td><?= htmlspecialchars($row["contact_no"]) ?></td>
                  <td><?= (int) $row["loyalty_points"] ?></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <a class="btn secondary" href="customers.php?edit=<?= $row["customer_id"] ?>">Edit</a>
                      <form method="post" action="customers.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="customer_id" value="<?= $row["customer_id"] ?>">
                        <button class="btn danger" type="submit" onclick="return confirm('Delete this customer?');">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalCustomers, $perPage); ?>
      </section>
    </main>
  </div>
</body>
</html>
