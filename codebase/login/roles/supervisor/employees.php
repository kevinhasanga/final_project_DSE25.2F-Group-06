<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Supervisor');

$activePage = "employees";

$search = trim($_GET["q"] ?? "");
$sql = "SELECT employee_id, full_name, job_title, contact_no, hire_date, base_salary, employment_status
        FROM employee";
$whereClause = "";
$params = [];
$types = "";

if ($search !== "") {
    $whereClause = " WHERE full_name LIKE ? OR employee_id = ?";
    $sql .= $whereClause;
    $params[] = "%$search%";
    $params[] = (int) $search;
    $types = "si";
}

$perPage = 10;
$totalEmployees = countRows($connection, "SELECT COUNT(*) FROM employee" . $whereClause, $types, $params);
$totalEmployeePages = max(1, (int) ceil($totalEmployees / $perPage));
$currentPage = min(getCurrentPage(), $totalEmployeePages);
$offset = ($currentPage - 1) * $perPage;

$sql .= " ORDER BY full_name LIMIT $perPage OFFSET $offset";
$statement = mysqli_prepare($connection, $sql);
if ($types !== "") {
    mysqli_stmt_bind_param($statement, $types, ...$params);
}
mysqli_stmt_execute($statement);
$employees = mysqli_stmt_get_result($statement);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employees</title>
  <link rel="stylesheet" href="css/supervisor_style.css">
</head>
<body>
  <header class="topbar"><h1>Supervisor</h1><p>View employee details</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Employees</h2><p>Search and view employee information.</p></section>

      <section class="panel">
        <h3>Search Employee</h3>
        <form method="get" action="employees.php">
          <div class="form-grid">
            <div class="form-group full-width">
              <label for="q">Name or Employee ID</label>
              <input type="text" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or ID">
            </div>
          </div>
          <div class="button-row">
            <button class="btn" type="submit">Search</button>
            <?php if ($search !== ""): ?>
              <a class="btn secondary" href="employees.php">Clear</a>
            <?php endif; ?>
          </div>
        </form>
      </section>

      <section class="panel">
        <h3>Employee Records</h3>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr><th>Employee ID</th><th>Name</th><th>Job Title</th><th>Phone</th><th>Hire Date</th><th>Base Salary</th><th>Status</th></tr>
            </thead>
            <tbody>
              <?php if (mysqli_num_rows($employees) === 0): ?>
                <tr><td colspan="7">No employees found.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($employees)): ?>
                <tr>
                  <td><?= $row["employee_id"] ?></td>
                  <td><?= htmlspecialchars($row["full_name"]) ?></td>
                  <td><?= htmlspecialchars($row["job_title"]) ?></td>
                  <td><?= htmlspecialchars($row["contact_no"]) ?></td>
                  <td><?= htmlspecialchars($row["hire_date"]) ?></td>
                  <td><?= number_format($row["base_salary"], 2) ?></td>
                  <td><span class="status <?= $row["employment_status"] === "active" ? "resolved" : "pending" ?>"><?= htmlspecialchars(ucfirst($row["employment_status"])) ?></span></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalEmployees, $perPage); ?>
      </section>
    </main>
  </div>
  <script src="js/validate.js"></script>
</body>
</html>
