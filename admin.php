<?php
// Database configuration
$host = "localhost";
$db = "glhorgia_users";
$user = "glhorgia_admin";
$pass = "GLHOMES_DB_ADMIN06";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch attendees sorted with Paid first
    $stmt = $conn->query("
        SELECT fullname, email, phone_number, payment_amount,
               CASE WHEN payment_status = 1 THEN 'Paid' ELSE 'Unpaid' END AS payment_status
        FROM mindset_shift_attendees
        ORDER BY payment_status DESC, fullname
    ");
    $attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count totals
    $stmt2 = $conn->query("
        SELECT 
            SUM(CASE WHEN payment_status = 1 THEN 1 ELSE 0 END) AS total_paid,
            SUM(CASE WHEN payment_status = 0 THEN 1 ELSE 0 END) AS total_unpaid
        FROM mindset_shift_attendees
    ");
    $totals = $stmt2->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Mindset Shift Dashboard</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    body {
      background-color: #f8f9fa;
    }
    .container {
      margin-top: 50px;
    }
    .table th {
      background-color: #0d6efd;
      color: white;
    }
    .summary-row {
      font-weight: bold;
      text-align: center;
      background-color: #e9ecef;
    }
  </style>
</head>
<body>
  <div id="dashboard" style="display:none;">
    <div class="container">
      <h2 class="mb-4 text-center">Mindset Shift Attendees Dashboard</h2>
      <table class="table table-bordered table-striped">
        <thead>
          <tr>
            <th>#</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Phone Number</th>
            <th>Payment Amount</th>
            <th>Payment Status</th>
          </tr>
        </thead>
        <tbody>
          <?php $count = 1; foreach ($attendees as $row): ?>
            <tr>
              <td><?= $count++ ?></td>
              <td><?= htmlspecialchars($row['fullname']) ?></td>
              <td><?= htmlspecialchars($row['email']) ?></td>
              <td><?= htmlspecialchars($row['phone_number']) ?></td>
              <td><?= htmlspecialchars($row['payment_amount']) ?></td>
              <td>
                <?php if ($row['payment_status'] === 'Paid'): ?>
                  <span class="badge bg-success">Paid</span>
                <?php else: ?>
                  <span class="badge bg-danger">Unpaid</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr class="summary-row">
            <td colspan="6">Total Paid: <?= $totals['total_paid'] ?></td>
          </tr>
          <tr class="summary-row">
            <td colspan="6">Total Unpaid: <?= $totals['total_unpaid'] ?></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    // SweetAlert password check
    window.onload = function() {
      Swal.fire({
        title: 'Enter Password',
        input: 'password',
        inputPlaceholder: 'Enter admin password',
        confirmButtonText: 'Login',
        allowOutsideClick: false,
        allowEscapeKey: false,
        preConfirm: (value) => {
          if (value !== 'AdminGL') {
            Swal.showValidationMessage('Incorrect password!');
          }
          return value;
        }
      }).then((result) => {
        if (result.value === 'AdminGL') {
          document.getElementById('dashboard').style.display = 'block';
        }
      });
    }
  </script>
</body>
</html>
