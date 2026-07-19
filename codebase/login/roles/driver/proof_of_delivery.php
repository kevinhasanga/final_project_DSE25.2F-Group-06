<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../pagination.php';
require_once __DIR__ . '/helpers.php';
require_login('Driver');

$activePage = "proof";
$currentDriverId = getCurrentEmployeeId($connection, (int) $_SESSION["user_id"]);
ensureCsrfToken();

$allowedExtensions = ["jpg" => "image/jpeg", "jpeg" => "image/jpeg", "png" => "image/png", "gif" => "image/gif", "pdf" => "application/pdf"];
$maxFileSize = 5 * 1024 * 1024;
$uploadDir = __DIR__ . '/uploads/proof/';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCsrfToken($_POST["csrf_token"] ?? "")) {
        setFlash("Your session expired. Refresh the page and try again.");
        header("Location: proof_of_delivery.php");
        exit();
    }

    $deliveryId = (int) $_POST["delivery_id"];
    $receiverName = trim($_POST["receiver_name"]);
    $notes = trim($_POST["notes"] ?? "");

    $ownerCheck = mysqli_prepare($connection, "SELECT delivery_id FROM delivery WHERE delivery_id = ? AND driver_id = ?");
    mysqli_stmt_bind_param($ownerCheck, "ii", $deliveryId, $currentDriverId);
    mysqli_stmt_execute($ownerCheck);
    $owns = mysqli_fetch_assoc(mysqli_stmt_get_result($ownerCheck));
    mysqli_stmt_close($ownerCheck);

    if (!$owns) {
        setFlash("That delivery is not assigned to you.");
        header("Location: proof_of_delivery.php");
        exit();
    }

    $file = $_FILES["proof_file"] ?? null;
    if (!$file || $file["error"] !== UPLOAD_ERR_OK) {
        setFlash("Please choose a valid file to upload.");
        header("Location: proof_of_delivery.php");
        exit();
    }

    if ($file["size"] > $maxFileSize) {
        setFlash("File is too large. Maximum size is 5 MB.");
        header("Location: proof_of_delivery.php");
        exit();
    }

    $extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedType = finfo_file($finfo, $file["tmp_name"]);
    finfo_close($finfo);

    if (!isset($allowedExtensions[$extension]) || $allowedExtensions[$extension] !== $detectedType) {
        setFlash("Only JPG, PNG, GIF, or PDF files are allowed.");
        header("Location: proof_of_delivery.php");
        exit();
    }

    $safeFilename = bin2hex(random_bytes(16)) . "." . $extension;
    if (!move_uploaded_file($file["tmp_name"], $uploadDir . $safeFilename)) {
        setFlash("Upload failed. Please try again.");
        header("Location: proof_of_delivery.php");
        exit();
    }

    $imageUrl = "uploads/proof/" . $safeFilename;

    $existing = mysqli_prepare($connection, "SELECT proof_id FROM delivery_proof WHERE delivery_id = ?");
    mysqli_stmt_bind_param($existing, "i", $deliveryId);
    mysqli_stmt_execute($existing);
    $existingRow = mysqli_fetch_assoc(mysqli_stmt_get_result($existing));
    mysqli_stmt_close($existing);

    if ($existingRow) {
        $statement = mysqli_prepare(
            $connection,
            "UPDATE delivery_proof SET image_url = ?, uploaded_at = NOW(), received_by_name = ?, notes = ? WHERE delivery_id = ?"
        );
        mysqli_stmt_bind_param($statement, "sssi", $imageUrl, $receiverName, $notes, $deliveryId);
    } else {
        $statement = mysqli_prepare(
            $connection,
            "INSERT INTO delivery_proof (delivery_id, image_url, uploaded_at, received_by_name, notes)
             VALUES (?, ?, NOW(), ?, ?)"
        );
        mysqli_stmt_bind_param($statement, "isss", $deliveryId, $imageUrl, $receiverName, $notes);
    }
    mysqli_stmt_execute($statement);
    mysqli_stmt_close($statement);

    setFlash("Proof of delivery uploaded.");
    header("Location: proof_of_delivery.php");
    exit();
}

$deliveries = getDriverDeliveries($connection, $currentDriverId);

$perPage = 5;
$totalProofs = countRows(
    $connection,
    "SELECT COUNT(*) FROM delivery_proof dp JOIN delivery d ON d.delivery_id = dp.delivery_id WHERE d.driver_id = ?",
    "i",
    [$currentDriverId]
);
$totalProofPages = max(1, (int) ceil($totalProofs / $perPage));
$currentPage = min(getCurrentPage(), $totalProofPages);
$offset = ($currentPage - 1) * $perPage;

$proofs = mysqli_query(
    $connection,
    "SELECT dp.proof_id, d.order_id, dp.received_by_name, dp.uploaded_at, dp.image_url, dp.notes
     FROM delivery_proof dp
     JOIN delivery d ON d.delivery_id = dp.delivery_id
     WHERE d.driver_id = $currentDriverId
     ORDER BY dp.uploaded_at DESC
     LIMIT $perPage OFFSET $offset"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Proof of Delivery</title>
  <link rel="stylesheet" href="css/driver_style.css">
</head>
<body>
  <header class="topbar">
    <h1>Driver</h1>
    <p>Upload proof of delivery</p>
  </header>

  <div class="layout">
    <?php include __DIR__ . '/nav.php'; ?>
    <main class="content">
      <section class="page-title">
        <h2>Proof of Delivery</h2>
        <p>Upload delivery proof after completing an order.</p>
      </section>

      <?php if ($flash = popFlash()): ?>
        <section class="panel"><p style="padding: 14px 20px;"><?= htmlspecialchars($flash) ?></p></section>
      <?php endif; ?>

      <section class="panel">
        <h3>Upload Proof</h3>
        <form method="post" action="proof_of_delivery.php" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"]) ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="deliveryId">Delivery</label>
              <select id="deliveryId" name="delivery_id" required>
                <option value="">Select delivery</option>
                <?php foreach ($deliveries as $delivery): ?>
                  <option value="<?= $delivery["delivery_id"] ?>">
                    Order #<?= $delivery["order_id"] ?> — <?= htmlspecialchars($delivery["customer_name"]) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="receiverName">Receiver Name</label>
              <input type="text" id="receiverName" name="receiver_name" required>
            </div>
            <div class="form-group">
              <label for="proofFile">Proof File (image or PDF, max 5 MB)</label>
              <input type="file" id="proofFile" name="proof_file" accept="image/*,.pdf" required>
            </div>
            <div class="form-group full-width">
              <label for="notes">Remarks</label>
              <textarea id="notes" name="notes"></textarea>
            </div>
          </div>
          <div class="button-row"><button class="btn" type="submit">Upload Proof</button></div>
        </form>
      </section>

      <section class="panel">
        <h3>Uploaded Proof Records</h3>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Order ID</th><th>Receiver</th><th>Date</th><th>File</th></tr></thead>
            <tbody>
              <?php if (mysqli_num_rows($proofs) === 0): ?>
                <tr><td colspan="4">No proof of delivery records loaded yet.</td></tr>
              <?php endif; ?>
              <?php while ($row = mysqli_fetch_assoc($proofs)): ?>
                <tr>
                  <td>#<?= $row["order_id"] ?></td>
                  <td><?= htmlspecialchars($row["received_by_name"]) ?></td>
                  <td><?= htmlspecialchars($row["uploaded_at"]) ?></td>
                  <td><a href="<?= htmlspecialchars($row["image_url"]) ?>" target="_blank">View</a></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php renderPagination($currentPage, $totalProofs, $perPage); ?>
      </section>
    </main>
  </div>
  <script src="js/validate.js"></script>
</body>
</html>
