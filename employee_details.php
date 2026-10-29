<?php
session_start();
require_once 'config/connection.php'; 


if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

date_default_timezone_set('Asia/Kolkata');
$today = date('Y-m-d');

$emp_code = $_GET['emp_code'] ?? null;
if (!$emp_code) {
    header('Location: index.php');
    exit;
}

$clean_emp_code = mysqli_real_escape_string($conn, $emp_code);

$emp_sql = "SELECT emp_code, name, designation, doj, basic_pay FROM employees WHERE emp_code = '$clean_emp_code'";
$emp_res = mysqli_query($conn, $emp_sql);
$employee = mysqli_fetch_assoc($emp_res);

if (!$employee) {
    die("Employee data not found.");
}

$page_title = "Payslip History for: " . $employee['name'];


$payslip_sql = "SELECT * FROM payslips WHERE emp_code = '$clean_emp_code' ORDER BY pay_year DESC, pay_month DESC";
$payslip_res = mysqli_query($conn, $payslip_sql);

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
                        <a href="index.php" class="nav-link">
                            <i class="nav-icon fas fa-users"></i>
                            <p>Manage Employees</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="pay_components.php" class="nav-link active">
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
                        <a href="index.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back to Employee List</a>
                    </div>
                </div>
            </div>
        </section>

        <div class="content">
            <div class="container-fluid">
                
                <?php 
           
                if (isset($_SESSION['message'])): ?>
                <div class="alert alert-<?php echo htmlspecialchars($_SESSION['alert_type']); ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['message']; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <?php 
                unset($_SESSION['message']); 
                unset($_SESSION['alert_type']);
                endif; 
                ?>

                <div class="card card-default">
                    <div class="card-header">
                        <h3 class="card-title">Employee Master Data</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3"><strong>Employee Code:</strong> <?php echo htmlspecialchars($employee['emp_code']); ?></div>
                            <div class="col-md-3"><strong>Name:</strong> <?php echo htmlspecialchars($employee['name']); ?></div>
                            <div class="col-md-3"><strong>Designation:</strong> <?php echo htmlspecialchars($employee['designation']); ?></div>
                            <div class="col-md-3"><strong>Basic Pay:</strong> ₹<?php echo number_format($employee['basic_pay'], 2); ?></div>
                        </div>
                    </div>
                </div>


                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">Payslip History</h3>
                        <div class="card-tools">
                            <a href="generate_payslip.php?emp_code=<?php echo urlencode($clean_emp_code); ?>" class="btn btn-sm btn-success">
                                <i class="fa fa-plus"></i> Generate New Payslip
                            </a>
                            <a href="edit_employee.php?emp_code=<?php echo urlencode($clean_emp_code); ?>" class="btn btn-sm btn-warning">
                                <i class="fa fa-edit"></i> Edit Master
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 10px">#</th>
                                        <th>Pay Period</th>
                                        <th>Base Basic Pay</th>
                                        <th>Gross Total</th>
                                        <th>Total Deduction</th>
                                        <th>Net Payable</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $j = 1;
                                    if (mysqli_num_rows($payslip_res) > 0) {
                                        while ($payslip = mysqli_fetch_assoc($payslip_res)) {
                                            $month_name = date("F", mktime(0, 0, 0, $payslip['pay_month'], 10)); 
                                            
                                            echo "<tr>";
                                            echo "<td>" . $j . "</td>";
                                            echo "<td>" . htmlspecialchars($month_name) . ", " . htmlspecialchars($payslip['pay_year']) . "</td>";
                                            echo "<td>₹ " . number_format($payslip['base_basic_pay'], 2) . "</td>";
                                            echo "<td>₹ " . number_format($payslip['gross_total'], 2) . "</td>";
                                            echo "<td>₹ " . number_format($payslip['total_deduction'], 2) . "</td>";
                                            echo "<td><span class='badge bg-success'>₹ " . number_format($payslip['net_total'], 2) . "</span></td>";
                                            echo "<td>
                                                <button onclick='alert(\"View detailed breakdown for Payslip ID: " . $payslip['payslip_id'] . "\")' class='btn btn-sm btn-info' title='View Details'>
                                                    <i class='fa fa-eye'></i> Details
                                                </button>
                                                <a href='exportpdf.php?payslip_id=" . urlencode($payslip['payslip_id']) . "' target='_blank' class='btn btn-sm btn-danger' title='Export PDF'>
                                                    <i class='fa fa-file-pdf'></i> PDF
                                                </a>
                                            </td>";
                                            echo "</tr>";
                                            $j++;
                                        }
                                    } else {
                                        echo "<tr><td colspan='7' class='text-center'>No payslips found for this employee.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    </div>
                </div></div>
        </div>