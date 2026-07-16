<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Admin');

$activePage = "backups";
$currentUserId = (int) $_SESSION["user_id"];
ensureCsrfToken();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: backups.php");
        exit();
    }

    $action = $_POST["action"] ?? "";

    if ($action === "create_backup") {
        $backupType = $_POST["backup_type"];
        $filePath = trim($_POST["file_path"]);
        $note = trim($_POST["note"] ?? "");

        $statement = mysqli_prepare(
            $connection,
            "INSERT INTO backup_record (backup_type, file_path, date, note) VALUES (?, ?, NOW(), ?)"
        );
        mysqli_stmt_bind_param($statement, "sss", $backupType, $filePath, $note);
        mysqli_stmt_execute($statement);
        $newBackupId = mysqli_insert_id($connection);
        mysqli_stmt_close($statement);

        logAudit($connection, $currentUserId, "performed a $backupType database backup", "backup_record", $newBackupId);
        setFlash("Backup recorded.");
        header("Location: backups.php");
        exit();
    }

    if ($action === "restore") {
        $backupId = (int) $_POST["backup_id"];
        $reason = trim($_POST["reason"]);

        $sourceStatement = mysqli_prepare($connection, "SELECT file_path FROM backup_record WHERE backup_id = ?");
        mysqli_stmt_bind_param($sourceStatement, "i", $backupId);
        mysqli_stmt_execute($sourceStatement);
        $source = mysqli_fetch_assoc(mysqli_stmt_get_result($sourceStatement));
        mysqli_stmt_close($sourceStatement);

        $note = "Restored from backup #$backupId. Reason: $reason";
        $statement = mysqli_prepare(
            $connection,
            "INSERT INTO backup_record (backup_type, file_path, date, note) VALUES ('restore', ?, NOW(), ?)"
        );
        $filePath = $source["file_path"] ?? null;
        mysqli_stmt_bind_param($statement, "ss", $filePath, $note);
        mysqli_stmt_execute($statement);
        $newRecordId = mysqli_insert_id($connection);
        mysqli_stmt_close($statement);

        logAudit($connection, $currentUserId, "restored system data from backup #$backupId", "backup_record", $newRecordId);
        setFlash("Restore recorded.");
        header("Location: backups.php");
        exit();
    }
}

$backupsForRestore = mysqli_fetch_all(
    mysqli_query($connection, "SELECT backup_id, file_path, date FROM backup_record WHERE backup_type != 'restore' ORDER BY date DESC"),
    MYSQLI_ASSOC
);

$perPage = 10;
$totalRecords = countRows($connection, "SELECT COUNT(*) FROM backup_record");
$totalRecordPages = max(1, (int) ceil($totalRecords / $perPage));
$currentPage = min(getCurrentPage(), $totalRecordPages);
$offset = ($currentPage - 1) * $perPage;

$records = mysqli_query($connection, "SELECT * FROM backup_record ORDER BY date DESC LIMIT $perPage OFFSET $offset");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Backups</title>
  <link rel="stylesheet" href="css/sa_style.css">
</head>
<body>
  <header class="topbar"><h1>System Administrator</h1><p>Perform database backups and restore system data</p></header>
  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title"><h2>Backups</h2><p>Record manually performed backups, and log data restores. Backups themselves are performed outside the system (e.g. via mysqldump); this page keeps the record.</p></section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3>Record Backup</h3>
        <form method="post" action="backups.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="create_backup">
          <div class="form-grid">
            <div class="form-group">
              <label for="backupType">Backup Type</label>
              <select id="backupType" name="backup_type" required>
                <option value="full">Full</option>
                <option value="partial">Partial</option>
              </select>
            </div>
            <div class="form-group">
              <label for="filePath">File Path / Name</label>
              <input type="text" id="filePath" name="file_path" placeholder="e.g. backups/ncc_2026_07_16.sql" required>
            </div>
            <div class="form-group full-width">
              <label for="note">Notes</label>
              <textarea id="note" name="note"></textarea>
            </div>
          </div>
          <div class="button-row"><button class="btn" type="submit">Record Backup</button></div>
        </form>
      </section>

      <section class="panel">
        <h3>Restore Data</h3>
        <form method="post" action="backups.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <input type="hidden" name="action" value="restore">
          <div class="form-grid">
            <div class="form-group">
              <label for="backupId">Backup to Restore</label>
              <select id="backupId" name="backup_id" required>
                <option value="">Select backup</option>
                <?php foreach ($backupsForRestore as $backup): ?>
                  <option value="<?= $backup["backup_id"] ?>"><?= htmlspecialchars($backup["file_path"] ?? "Backup #" . $backup["backup_id"]) ?> (<?= htmlspecialchars($backup["date"]) ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group full-width">
              <label for="reason">Reason for Restore</label>
              <textarea id="reason" name="reason" required></textarea>
            </div>
          </div>
          <div class="button-row"><button class="btn" type="submit">Log Restore</button></div>
        </form>
      </section>

      <section class="panel">
        <h3>Backup and Restore Records</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Type</th><th>File Path</th><th>Date</th><th>Note</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($records) === 0): ?>
                <tr><td colspan="4">No backup records loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($records)): ?>
                <tr>
                  <td><span class="status <?= $row["backup_type"] === "restore" ? "progress" : "resolved" ?>"><?= htmlspecialchars(ucfirst($row["backup_type"])) ?></span></td>
                  <td><?= htmlspecialchars($row["file_path"] ?? "") ?></td>
                  <td><?= htmlspecialchars($row["date"]) ?></td>
                  <td><?= htmlspecialchars($row["note"] ?? "") ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalRecords, $perPage); ?>
      </section>
    </main>
  </div>
</body>
</html>
