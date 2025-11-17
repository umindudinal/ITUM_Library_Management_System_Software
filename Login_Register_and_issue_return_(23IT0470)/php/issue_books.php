<?php

session_start();
include 'connection.php';
$connection = getDatabaseConnection();

date_default_timezone_set('Asia/Colombo');
$issue_date = date('Y-m-d');
$due_date = date('Y-m-d', strtotime('+14 days'));

$alert = "";
$alert_type = "";

if (!isset($_SESSION['role'])) {
  $_SESSION['role'] = 'User';
}
if (!isset($_SESSION['reg_no'])) {
  $_SESSION['reg_no'] = '';
}
if (!isset($_SESSION['first_name'])) {
  $_SESSION['first_name'] = '';
}

if (isset($_POST['issue'])) {
  $reg_no = isset($_POST['reg_no']) ? trim($_POST['reg_no']) : trim($_SESSION['reg_no']);
  $book_id = trim($_POST['book_id']);

  if (empty($reg_no) || empty($book_id)) {
    $alert = "Please fill all required fields.";
    $alert_type = "danger";
  } 
  else {
    if (!ctype_digit($book_id)) {
      $alert = "Book ID must be a numeric ID.";
      $alert_type = "danger";
    } 
    else {
      $book_id_int = (int)$book_id;

      $checkBook = $connection->prepare("SELECT status FROM book_information WHERE id = ?");
      if (!$checkBook) {
          $alert = "Database error (prepare checkBook): " . $connection->error;
          $alert_type = "danger";
      } 
      else {
        $checkBook->bind_param("i", $book_id_int);
        $checkBook->execute();
        $result = $checkBook->get_result();

        if ($result->num_rows === 0) {
          $alert = "Book not found!";
          $alert_type = "danger";
        } 
        else {
          $book = $result->fetch_assoc();

          if ($book['status'] === 'issued') {
              $alert = "😢 This book is already issued.";
              $alert_type = "warning";
          } 
          else {
            $insert = $connection->prepare("INSERT INTO issue_return (registration_no, book_id, action, issue_date, due_date) VALUES (?, ?, 'issue', ?, ?)");
            if (!$insert) {
              $alert = "Database error (prepare insert): " . $connection->error;
              $alert_type = "danger";
            } 
            else {
              $insert->bind_param("siss", $reg_no, $book_id_int, $issue_date, $due_date);
              if ($insert->execute()) {
                $update = $connection->prepare("UPDATE book_information SET status = 'issued' WHERE id = ?");
                if ($update) {
                    $update->bind_param("i", $book_id_int);
                    $update->execute();
                }
                $alert = "😍 Book issued successfully!";
                $alert_type = "success";
              } 
              else {
                $alert = "Error issuing book: " . $insert->error;
                $alert_type = "danger";
              }
            }
          }
        }
      }
    }
  }
}

?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>ITUM Library Management System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
</head>

<body>

  <div class="topbar">
    <div class="brand">ITUM Library Management System</div>
    <a href="logout.php" class="logout-btn">Logout</a>
  </div>

  <div class="sidebar">
    <a href="dashboard.php" class="nav-item"><i class="fa-solid fa-table-cells"></i>Dashboard</a>
    <a href="book_management.php" class="nav-item"><i class="fa-solid fa-book"></i>Book Management</a>
    <a href="#" class="nav-item active"><i class="fa-solid fa-paper-plane"></i>Issue Books</a>
    <a href="return_books.php" class="nav-item"><i class="fa-solid fa-rotate-left"></i>Return Books</a>
    <?php if ($_SESSION["role"] == "Admin") { ?>
      <a href="member.php" class="nav-item"><i class="fa-solid fa-users"></i>Members</a>
      <a href="report.php" class="nav-item"><i class="fa-solid fa-chart-simple"></i>Reports</a>
      <a href="fine_management.php" class="nav-item"><i class="fa-solid fa-money-bill-wave"></i>Fine Management</a>
    <?php } ?>
    <div class="bottom-profile">
      <i class="fa-solid fa-user"></i><?= $_SESSION["first_name"] ?> (<?= $_SESSION["role"] ?>)
    </div>
  </div>

  <main class="main">

    <!-- ===== ALERT BOX ===== -->
    <?php if (!empty($alert)) { ?>
      <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show mt-2" role="alert">
        <?= htmlspecialchars($alert) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php } ?>
    <!-- ===================== -->

    <div class="form_container issue" style="background: none;">
      <div class="section2 issue_books">

        <form action="" method="post">
          <h2>Issue Book Form</h2>

          <div class="input_box">
            <label>Registration No</label>

            <?php if ($_SESSION["role"] == "Admin") {
              $selectedReg = isset($_POST['reg_no']) ? $_POST['reg_no'] : (isset($_SESSION['reg_no']) ? $_SESSION['reg_no'] : '');

              $users = $connection->query("SELECT * FROM user_registered_info ORDER BY RegistrationNo");

              if (!$users) {
                ?>
                <select disabled>
                  <option>No users found (DB error)</option>
                </select>
                <div class="small text-danger mt-1">DB error: <?= htmlspecialchars($connection->error) ?></div>
                <?php
              } 
              elseif ($users->num_rows === 0) {
                ?>
                <select disabled>
                  <option value="">-- No registered users --</option>
                </select>
                <?php
              } 
              else {
                ?>
                <select name="reg_no" class="form-select" required>
                  <option value="">-- Select User --</option>
                  <?php
                  while ($row = $users->fetch_assoc()) {
                    $displayName = '';
                    if (isset($row['first_name']) && $row['first_name'] !== '') $displayName = $row['first_name'];
                    elseif (isset($row['FirstName']) && $row['FirstName'] !== '') $displayName = $row['FirstName'];
                    elseif (isset($row['name']) && $row['name'] !== '') $displayName = $row['name'];
                    elseif (isset($row['full_name']) && $row['full_name'] !== '') $displayName = $row['full_name'];
                    elseif (isset($row['Username']) && $row['Username'] !== '') $displayName = $row['Username'];
                    $regVal = isset($row['RegistrationNo']) ? $row['RegistrationNo'] : '';

                    $isSelected = ($regVal !== '' && $regVal == $selectedReg) ? 'selected' : '';
                    ?>
                    <option value="<?= htmlspecialchars($regVal) ?>" <?= $isSelected ?>>
                      <?= htmlspecialchars($regVal) ?><?= $displayName ? ' - '.htmlspecialchars($displayName) : '' ?>
                    </option>
                  <?php } ?>
                </select>
                <?php
              }
            } else { ?>
              <input type="text" name="reg_no" value="<?= htmlspecialchars($_SESSION['reg_no']) ?>" readonly>
            <?php } ?>
          </div>

          <div class="input_box">
            <label>Book ID</label>
            <input type="text" name="book_id" required value="<?= isset($_POST['book_id']) ? htmlspecialchars($_POST['book_id']) : '' ?>">
          </div>

          <div class="input_box">
            <label>Issue Date</label>
            <input type="date" name="issue_date" value="<?= $issue_date ?>" readonly>
          </div>

          <div class="input_box">
            <label>Due Date</label>
            <input type="date" name="due_date" value="<?= $due_date ?>" readonly>
          </div>

          <button type="submit" name="issue">Issue</button>
        </form>

      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
