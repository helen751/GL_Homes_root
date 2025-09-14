<?php
session_start();

// Database configuration
$host = "localhost";
$db = "glhorgia_users";
$user = "glhorgia_admin";
$pass = "GLHOMES_DB_ADMIN06";

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === 'AdminGL') {
        $_SESSION['logged_in'] = true;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = "Incorrect password!";
    }
}

// If not logged in, show password prompt
if (empty($_SESSION['logged_in'])):
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<script>
  Swal.fire({
    title: 'Enter Password',
    input: 'password',
    inputPlaceholder: 'Enter admin password',
    confirmButtonText: 'Login',
    allowOutsideClick: false,
    allowEscapeKey: false,
    preConfirm: (value) => {
      if (!value) {
        Swal.showValidationMessage('Password is required!');
      }
      return value;
    }
  }).then((result) => {
    if (result.value) {
      // submit password via POST
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = '';
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'password';
      input.value = result.value;
      form.appendChild(input);
      document.body.appendChild(form);
      form.submit();
    }
  });
</script>
</body>
</html>
<?php
exit;
endif;

// If logged in, show dashboard
try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch attendees sorted with Paid first
    $stmt = $conn->query("
        SELECT fullname, email, phone_number, payment_amount,
               CASE WHEN payment_status = 1 THEN 'Paid' ELSE 'Unpaid' END AS payment_status
        FROM mindset_shift_attendees
        ORDER BY payment_status ASC, created_at
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f8f9fa; }
    .container { margin-top: 50px; }
    .table th { background-color: #0d6efd; color: white; }
    .summary-row { font-weight: bold; text-align: center; background-color: #e9ecef; }
  </style>
</head>
<body>
  <div class="container">
    <h2 class="mb-4 text-center">Mindset Shift Attendees Dashboard</h2>
    <table id="attendeesTable" class="table table-bordered table-striped">
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

    <div class="text-center mt-4">
      <button class="btn btn-primary" onclick="window.print()">Print as PDF</button>
      <button class="btn btn-success" onclick="exportTableToExcel('attendeesTable')">Export to Excel</button>
    </div>
  </div>

  <script>
    function exportTableToExcel(tableID, filename = ''){
      let table = document.getElementById(tableID);
      let html = table.outerHTML.replace(/ /g, '%20');
      filename = filename ? filename + '.xls' : 'attendees.xls';
      let link = document.createElement("a");
      link.href = 'data:application/vnd.ms-excel,' + html;
      link.download = filename;
      link.click();
    }
  </script>
</body>
</html>
