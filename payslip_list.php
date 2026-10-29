<?php
session_start();
require_once 'config/connection.php'; 

// --- 1. SECURITY CHECK ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

date_default_timezone_set('Asia/Kolkata');
$page_title = "Payslip & Acquittance Roll Listing";
$today = date('Y-m-d'); 

// --- 2. Filtering Parameters ---
$search_name = empty($_GET['search_name']) ? '' : trim($_GET['search_name']);
$search_empid = empty($_GET['search_empid']) ? '' : trim($_GET['search_empid']);
$search_month = empty($_GET['search_month']) ? '' : trim($_GET['search_month']);
$search_year = empty($_GET['search_year']) ? '' : trim($_GET['search_year']);
$order_by = empty($_GET['order_by']) ? 'date_desc' : $_GET['order_by'];

// --- 3. Construct SQL Query ---
$sql = "SELECT p.*, e.name, e.designation, e.basic_pay 
        FROM payslips p
        JOIN employees e ON p.emp_code = e.emp_code
        WHERE 1=1";

if (isset($_GET['search'])) {
    if (!empty($search_empid)) {
        $empid = mysqli_real_escape_string($conn, $search_empid);
        $sql .= " AND p.emp_code LIKE '%$empid%'";
    }
    if (!empty($search_name)) {
        $keywords = "%" . mysqli_real_escape_string($conn, $search_name) . "%";
        $sql .= " AND e.name LIKE '$keywords'";
    }
    if (!empty($search_month)) {
        $month = (int)$search_month;
        $sql .= " AND p.pay_month = $month";
    }
    if (!empty($search_year)) {
        $year = (int)$search_year;
        $sql .= " AND p.pay_year = $year";
    }
}

// Apply Ordering
if ($order_by == 'date_desc') {
    $sql .= " ORDER BY p.pay_year DESC, p.pay_month DESC";
} else if ($order_by == 'net_desc') {
    $sql .= " ORDER BY p.net_total DESC";
} else {
    $sql .= " ORDER BY e.name ASC";
}

$result = mysqli_query($conn, $sql);
$query_string = http_build_query($_GET);

// --- Get available years for dropdown (assuming payslips table is the source) ---
$year_res = mysqli_query($conn, "SELECT DISTINCT pay_year FROM payslips ORDER BY pay_year DESC");

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
        .card-header { background-color: #f7f7f7; border-bottom: 1px solid #dee2e6; }
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
                    <li class="nav-item"><a href="index.php" class="nav-link"><i class="nav-icon fas fa-users"></i><p>Manage Employees</p></a></li>
                    <li class="nav-item"><a href="payslip_list.php" class="nav-link active"><i class="nav-icon fas fa-receipt"></i><p>Payslips</p></a></li>
                    <li class="nav-item"><a href="appraisal.php" class="nav-link"><i class="nav-icon fas fa-chart-line"></i><p>Appraisal History</p></a></li>
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
                         <a href="generate_payslip.php" class="btn btn-info"><i class="fas fa-plus"></i> Generate New Payslip</a>
                    </div>
                </div>
            </div>
        </section>

        <div class="content">
            <div class="container-fluid">

                <div class="card card-secondary card-outline">
                    <div class="card-header"><h3 class="card-title">Filter Payslips</h3></div>
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            
                            <div class="col-md-3">
                                <label>Employee Code / Name</label>
                                <input type="text" name="search_empid" value="<?php echo htmlspecialchars($search_empid); ?>" class="form-control" placeholder="Code or Name">
                            </div>
                            
                            <div class="col-md-2">
                                <label>Pay Month</label>
                                <select name="search_month" class="form-control">
                                    <option value="">-- All --</option>
                                    <?php for ($m = 1; $m <= 12; $m++): 
                                        $month_name = date('F', mktime(0, 0, 0, $m, 1));
                                        $selected = ($search_month == $m) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo $m; ?>" <?php echo $selected; ?>><?php echo $month_name; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label>Pay Year</label>
                                <select name="search_year" class="form-control">
                                    <option value="">-- All --</option>
                                    <?php 
                                    if ($year_res && mysqli_num_rows($year_res) > 0) {
                                        while ($row = mysqli_fetch_assoc($year_res)) {
                                            $year_value = $row['pay_year'];
                                            $selected = ($search_year == $year_value) ? 'selected' : '';
                                            echo "<option value='$year_value' $selected>$year_value</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label>Order By</label>
                                <select name="order_by" class="form-control">
                                    <option value="date_desc" <?php if($order_by == 'date_desc') echo 'selected'; ?>>Date Descending</option>
                                    <option value="net_desc" <?php if($order_by == 'net_desc') echo 'selected'; ?>>Net Pay (High to Low)</option>
                                    <option value="name_asc" <?php if($order_by == 'name_asc') echo 'selected'; ?>>Name Ascending</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <button class="btn btn-secondary me-2" name="search"><i class="fa fa-filter"></i> Filter</button>
                                <a class="btn btn-default" href="payslip_list.php">Clear</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-dark d-flex justify-content-between align-items-center">
                        <h3 class="card-title text-white">Payslips (Acquittance History)</h3>
                        <a href="generate_payslip.php" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> Add New</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Emp Code</th>
                                        <th>Employee Name</th>
                                        <th>Pay Period</th>
                                        <th>Gross Total</th>
                                        <th>Total Deduction</th>
                                        <th>Net Payable</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($result && mysqli_num_rows($result) > 0) {
                                        while ($row = mysqli_fetch_assoc($result)) {
                                            $period = date('F Y', mktime(0, 0, 0, $row['pay_month'], 1, $row['pay_year']));
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($row['emp_code']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                                            echo "<td>" . htmlspecialchars($period) . "</td>";
                                            echo "<td>₹ " . number_format($row['gross_total'], 2) . "</td>";
                                            echo "<td>₹ " . number_format($row['total_deduction'], 2) . "</td>";
                                            echo "<td><span class='badge bg-success'>₹ " . number_format($row['net_total'], 2) . "</span></td>";
                                            echo "<td>
                                                <a href='exportpdf.php?payslip_id=" . urlencode($row['payslip_id']) . "' target='_blank' class='btn btn-sm btn-danger me-1' title='Export PDF'>
                                                    <i class='fa fa-file-pdf'></i> PDF
                                                </a>
                                                <button onclick='alert(\"View/Edit details for Payslip ID: {$row['payslip_id']}\")' class='btn btn-sm btn-info' title='View Details'>
                                                    Details
                                                </button>
                                            </td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='7' class='text-center'>";
                                        if ($result === false) {
                                            echo "<div class='alert alert-danger'>Error retrieving payslips. Please try again.</div>";
                                        } else {
                                            echo "<div class='alert alert-info'><strong>No payslip records found.</strong><br/><p>To view payslips, you need to <a href='generate_payslip.php' class='alert-link'><strong>generate payslips</strong></a> first.</p></div>";
                                        }
                                        echo "</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="adminlte/plugins/jquery/jquery.min.js"></script>
<script src="adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="adminlte/dist/js/adminlte.min.js"></script>
</body>
</html>
<?php mysqli_close($conn); ?>