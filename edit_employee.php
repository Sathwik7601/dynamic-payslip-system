
<?php
session_start();


if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once 'config/connection.php'; 

date_default_timezone_set('Asia/Kolkata');
$page_title = "Edit Employee";
$today = date('Y-m-d'); 
$message = '';
$alert_type = '';

$emp_code = empty($_GET['emp_code']) ? (empty($_POST['emp_code']) ? null : $_POST['emp_code']) : $_GET['emp_code'];

if (!$emp_code) {
 
    header('Location: index.php');
    exit;
}

$clean_emp_code = mysqli_real_escape_string($conn, $emp_code);



if (isset($_POST['update_employee'])) {
    
  
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $designation = mysqli_real_escape_string($conn, $_POST['designation']);
    $doj = mysqli_real_escape_string($conn, $_POST['doj']);
    $basic_pay = mysqli_real_escape_string($conn, $_POST['basic_pay']);
    $sb_account_no = mysqli_real_escape_string($conn, $_POST['sb_account_no'] ?? '');
    $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name'] ?? '');
    $branch_name = mysqli_real_escape_string($conn, $_POST['branch_name'] ?? '');

    
    $update_sql = "
        UPDATE employees SET 
            name = '$name', 
            designation = '$designation', 
            doj = '$doj', 
            basic_pay = '$basic_pay', 
            sb_account_no = '$sb_account_no', 
            bank_name = '$bank_name', 
            branch_name = '$branch_name',
            updated_at = NOW()
        WHERE emp_code = '$clean_emp_code'
    ";

    if (mysqli_query($conn, $update_sql)) {
        $message = "Success: Employee **$name** details updated successfully!";
        $alert_type = "success";
    } else {
        $message = "Error updating employee: " . mysqli_error($conn);
        $alert_type = "danger";
    }
}



$fetch_sql = "SELECT * FROM employees WHERE emp_code = '$clean_emp_code'";
$fetch_res = mysqli_query($conn, $fetch_sql);
$employee = mysqli_fetch_assoc($fetch_res);

if (!$employee) {
    $page_title = "Employee Not Found";
} else {
    $page_title = "Edit Employee: " . $employee['name'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($page_title); ?> | Pay Slip Admin</title>

    <link rel="stylesheet" href="adminlte/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="adminlte/dist/css/adminlte.min.css">
    <style>
        .card-header {
            background-color: #f7f7f7;
            border-bottom: 1px solid #dee2e6;
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a class="nav-link" href="logout.php" role="button">
                    <i class="fas fa-sign-out-alt"></i> Logout (<?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>)
                </a>
            </li>
        </ul>
    </nav>
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="index.php" class="brand-link">
            <span class="brand-text font-weight-light">Pay Slip</span>
        </a>

        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link active">
                            <i class="nav-icon fas fa-users"></i>
                            <p>Manage Employees</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="pay_components.php" class="nav-link">
                            <i class="nav-icon fas fa-receipt"></i>
                            <p>Payslips</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fas fa-chart-line"></i>
                            <p>Appraisal History</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>
    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1><?php echo htmlspecialchars($page_title); ?></h1>
                    </div>
                    <div class="col-sm-6 text-right">
                        <a href="index.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back to List</a>
                    </div>
                </div>
            </div>
        </section>

        <div class="content">
            <div class="container-fluid">
                
                <?php if ($message): ?>
                <div class="alert alert-<?php echo htmlspecialchars($alert_type); ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php endif; ?>

                <?php if ($employee): ?>
                <div class="card card-warning card-outline">
                    <div class="card-header">
                        <h3 class="card-title">Employee Master Details</h3>
                    </div>
                    <form method="POST" action="edit_employee.php" class="form-horizontal">
                        <div class="card-body">
                            <p class="text-danger">Employee Code: **<?php echo htmlspecialchars($employee['emp_code']); ?>** (Cannot be changed)</p>
                            
                            <input type="hidden" name="emp_code" value="<?php echo htmlspecialchars($employee['emp_code']); ?>">
                            
                            <div class="form-group row">
                                <div class="col-sm-4">
                                    <label>Employee Code</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($employee['emp_code']); ?>" disabled>
                                </div>
                                <div class="col-sm-4">
                                    <label for="name">Name*</label>
                                    <input type="text" class="form-control" name="name" required value="<?php echo htmlspecialchars($employee['name']); ?>">
                                </div>
                                <div class="col-sm-4">
                                    <label for="basic_pay">Basic Pay*</label>
                                    <input type="number" step="0.01" class="form-control" name="basic_pay" required value="<?php echo htmlspecialchars($employee['basic_pay']); ?>">
                                </div>
                            </div>

                            <div class="form-group row">
                                <div class="col-sm-4">
                                    <label for="designation">Designation*</label>
                                    <input type="text" class="form-control" name="designation" required value="<?php echo htmlspecialchars($employee['designation']); ?>">
                                </div>
                                <div class="col-sm-4">
                                    <label for="sb_account_no">SB Account No</label>
                                    <input type="text" class="form-control" name="sb_account_no" value="<?php echo htmlspecialchars($employee['sb_account_no']); ?>">
                                </div>
                                <div class="col-sm-4">
                                    <label for="bank_name">Bank Name</label>
                                    <input type="text" class="form-control" name="bank_name" value="<?php echo htmlspecialchars($employee['bank_name']); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group row">
                                <div class="col-sm-4">
                                    <label for="doj">Date of Joining*</label>
                                    <input type="date" class="form-control" name="doj" required max="<?php echo $today; ?>" value="<?php echo htmlspecialchars($employee['doj']); ?>">
                                </div>
                                <div class="col-sm-4">
                                    <label for="branch_name">Branch Name</label>
                                    <input type="text" class="form-control" name="branch_name" value="<?php echo htmlspecialchars($employee['branch_name']); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="update_employee" class="btn btn-warning"><i class="fas fa-save"></i> Update Employee Details</button>
                        </div>
                        </form>
                </div>
                <?php else: ?>
                    <div class="alert alert-danger">Employee data could not be loaded. Please ensure the employee code is valid.</div>
                <?php endif; ?>

            </div></div>
        </div>
    <footer class="main-footer">
        <strong>Copyright &copy; 2025 Pay Slip Project.</strong> All rights reserved.
    </footer>
</div>
<script src="adminlte/plugins/jquery/jquery.min.js"></script>
<script src="adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="adminlte/dist/js/adminlte.min.js"></script>
</body>
</html>
<?php 
mysqli_close($conn); 
?>