<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Customer Relationship Officer');

$activePage = "complaints";
$currentEmployeeId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: complaints.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "save") {
        $complaintId = (int) ($_POST["complaint_id"] ?? 0);
        $customerId = (int) $_POST["customer_id"];
        $description = trim($_POST["description"]);
        $status = $_POST["status"];
        $resolvedDateExpression = $status === "resolved" ? "NOW()" : "NULL";

        if ($complaintId > 0) {
            $statement = mysqli_prepare(
                $connection,
                "UPDATE complaint SET customer_id = ?, description = ?, status = ?, resolved_date = $resolvedDateExpression
                 WHERE complaint_id = ?"
            );
            mysqli_stmt_bind_param($statement, "issi", $customerId, $description, $status, $complaintId);
        } else {
            $statement = mysqli_prepare(
                $connection,
                "INSERT INTO complaint (customer_id, officer_id, description, status, created_date, resolved_date)
                 VALUES (?, ?, ?, ?, NOW(), $resolvedDateExpression)"
            );
            mysqli_stmt_bind_param($statement, "iiss", $customerId, $currentEmployeeId, $description, $status);
        }

        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Complaint saved.");
        header("Location: complaints.php");
        exit();
    }

    if ($action === "delete") {
        $complaintId = (int) $_POST["complaint_id"];
        $statement = mysqli_prepare($connection, "DELETE FROM complaint WHERE complaint_id = ?");
        mysqli_stmt_bind_param($statement, "i", $complaintId);
        mysqli_stmt_execute($statement);
        mysqli_stmt_close($statement);
        setFlash("Complaint deleted.");
        header("Location: complaints.php");
        exit();
    }
}

$editRecord = null;
if (isset($_GET["edit"])) {
    $editId = (int) $_GET["edit"];
    $statement = mysqli_prepare($connection, "SELECT * FROM complaint WHERE complaint_id = ?");
    mysqli_stmt_bind_param($statement, "i", $editId);
    mysqli_stmt_execute($statement);
    $editRecord = mysqli_fetch_assoc(mysqli_stmt_get_result($statement));
    mysqli_stmt_close($statement);
}

$customers = getAllCustomers($connection);

$perPage = 10;
$totalComplaints = countRows($connection, "SELECT COUNT(*) FROM complaint c JOIN customer cu ON cu.customer_id = c.customer_id");
$totalComplaintPages = max(1, (int) ceil($totalComplaints / $perPage));
$currentPage = min(getCurrentPage(), $totalComplaintPages);
$offset = ($currentPage - 1) * $perPage;

$complaints = mysqli_query(
    $connection,
    "SELECT c.complaint_id, c.customer_id, cu.name AS customer_name, c.description, c.status, c.created_date, c.resolved_date
     FROM complaint c
     JOIN customer cu ON cu.customer_id = c.customer_id
     ORDER BY c.status = 'resolved', c.created_date DESC
     LIMIT $perPage OFFSET $offset"
);

$statusOptions = ["open" => "Open", "in_progress" => "In Progress", "resolved" => "Resolved"];
$statusClasses = ["open" => "pending", "in_progress" => "progress", "resolved" => "resolved"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Complaints</title><link rel="stylesheet" href="css/cro_style.css">
</head>
<body>
  <header class="topbar"><h1>Customer Relationship Officer</h1><p>Record and respond to complaints; track resolution status</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Complaints</h2><p>Record customer complaints and track resolution status.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3><?= $editRecord ? "Edit Complaint" : "Record Complaint" ?></h3>
        <form method="post" action="complaints.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="save">
          <input type="hidden" name="complaint_id" value="<?= htmlspecialchars($editRecord["complaint_id"] ?? "") ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="customerId">Customer</label>
              <select id="customerId" name="customer_id" required>
                <option value="">Select customer</option>
                <?php foreach ($customers as $customer): ?>
                  <option value="<?= $customer["customer_id"] ?>" <?= ($editRecord["customer_id"] ?? null) == $customer["customer_id"] ? "selected" : "" ?>>
                    <?= htmlspecialchars($customer["name"]) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="status">Status</label>
              <select id="status" name="status" required>
                <?php foreach ($statusOptions as $value => $label): ?>
                  <option value="<?= $value ?>" <?= ($editRecord["status"] ?? "open") === $value ? "selected" : "" ?>><?= $label ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group full-width">
              <label for="description">Description</label>
              <textarea id="description" name="description" required><?= htmlspecialchars($editRecord["description"] ?? "") ?></textarea>
            </div>
          </div>
          <div class="button-row">
            <button class="btn" type="submit">Save Complaint</button>
            <?php if ($editRecord): ?>
              <a class="btn secondary" href="complaints.php">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </section>

      <section class="panel">
        <h3>Complaints</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>ID</th><th>Customer</th><th>Description</th><th>Status</th><th>Created</th><th>Resolved</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($complaints) === 0): ?>
                <tr><td colspan="7">No complaint records loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($complaints)): ?>
                <tr>
                  <td><?= $row["complaint_id"] ?></td>
                  <td><?= htmlspecialchars($row["customer_name"]) ?></td>
                  <td><?= htmlspecialchars($row["description"]) ?></td>
                  <td><span class="status <?= $statusClasses[$row["status"]] ?? "progress" ?>"><?= htmlspecialchars($statusOptions[$row["status"]] ?? $row["status"]) ?></span></td>
                  <td><?= htmlspecialchars($row["created_date"]) ?></td>
                  <td><?= htmlspecialchars($row["resolved_date"] ?? "—") ?></td>
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: nowrap;">
                      <a class="btn secondary" href="complaints.php?edit=<?= $row["complaint_id"] ?>">Edit</a>
                      <form method="post" action="complaints.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="complaint_id" value="<?= $row["complaint_id"] ?>">
                        <button class="btn danger" type="submit" onclick="return confirm('Delete this complaint?');">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalComplaints, $perPage); ?>
      </section>
    </main>
  </div>
</body>
</html>
