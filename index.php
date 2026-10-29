<?php
session_start();

// --- 1. SECURITY CHECK: REDIRECT IF NOT LOGGED IN ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// --- 2. Database Connection & Setup ---
require_once 'config/connection.php'; // Ensure this path is correct

date_default_timezone_set('Asia/Kolkata');
$page_title = "Manage Employees"; 
$today = date('Y-m-d'); 
$message = '';
$alert_type = '';

// Check for and display session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $alert_type = $_SESSION['alert_type'];
    unset($_SESSION['message']);
    unset($_SESSION['alert_type']);
}

// --- AJAX Endpoint for fetching employee data (used by the Edit Modal) ---
if (isset($_GET['action']) && $_GET['action'] == 'fetch_employee' && isset($_GET['emp_code'])) {
    header('Content-Type: application/json');
    $emp_code = mysqli_real_escape_string($conn, $_GET['emp_code']);
    $sql = "SELECT emp_code, name, designation, doj, basic_pay, sb_account_no, bank_name, branch_name, status FROM employees WHERE emp_code = '$emp_code'";
    $res = mysqli_query($conn, $sql);
    
    if ($res && $employee = mysqli_fetch_assoc($res)) {
        echo json_encode(['status' => 'success', 'employee' => $employee]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Employee not found.']);
    }
    mysqli_close($conn);
    // CRITICAL FIX: Stop execution after sending JSON
    exit; 
}

// --- AJAX Endpoint for fetching appraisal data ---
if (isset($_GET['action']) && $_GET['action'] == 'fetch_appraisal' && isset($_GET['appraisal_id'])) {
    header('Content-Type: application/json');
    $appraisal_id = intval($_GET['appraisal_id']);
    // Note: This endpoint is likely hit from the 'appraisal.php' page, but is defined here.
    // Ensure you have an 'appraisals' table and 'employees' table with 'emp_code' foreign key.
    $sql = "SELECT a.*, e.name FROM appraisals a JOIN employees e ON a.emp_code = e.emp_code WHERE a.id = $appraisal_id";
    $res = $conn->query($sql);
    
    if ($res && $appraisal = $res->fetch_assoc()) {
        echo json_encode(['status' => 'success', 'appraisal' => $appraisal]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Appraisal record not found.']);
    }
    mysqli_close($conn);
    // CRITICAL FIX: Stop execution after sending JSON
    exit;
}


// --- 3. CRUD LOGIC (Add & Update) ---

// A. Handle ADD Employee
if (isset($_POST['add_employee'])) {
    // Collect and sanitize form data
    $emp_code = mysqli_real_escape_string($conn, trim($_POST['emp_code']));
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $basic_pay = mysqli_real_escape_string($conn, $_POST['basic_pay']);
    $designation = mysqli_real_escape_string($conn, trim($_POST['designation']));
    $sb_account_no = mysqli_real_escape_string($conn, trim($_POST['sb_account_no'] ?? ''));
    $bank_name = mysqli_real_escape_string($conn, trim($_POST['bank_name'] ?? ''));
    $branch_name = mysqli_real_escape_string($conn, trim($_POST['branch_name'] ?? ''));
    $doj = mysqli_real_escape_string($conn, $_POST['doj']);

    // Check if employee code already exists
    $check_sql = "SELECT emp_code FROM employees WHERE emp_code = '$emp_code'";
    $check_res = mysqli_query($conn, $check_sql);

    if (mysqli_num_rows($check_res) > 0) {
        $_SESSION['message'] = "Error: Employee Code '$emp_code' already exists.";
        $_SESSION['alert_type'] = "danger";
    } else {
        // Insert new employee
        $insert_sql = "INSERT INTO employees (emp_code, name, designation, doj, basic_pay, sb_account_no, bank_name, branch_name) 
                       VALUES ('$emp_code', '$name', '$designation', '$doj', '$basic_pay', '$sb_account_no', '$bank_name', '$branch_name')";
        
        if (mysqli_query($conn, $insert_sql)) {
            $_SESSION['message'] = "Employee '$name' added successfully!";
            $_SESSION['alert_type'] = "success";
        } else {
            $_SESSION['message'] = "Error adding employee: " . mysqli_error($conn);
            $_SESSION['alert_type'] = "danger";
        }
    }
    header("Location: index.php");
    exit;
}

// B. Handle UPDATE Employee (from Edit Modal)
if (isset($_POST['update_employee'])) {
    $emp_code = mysqli_real_escape_string($conn, $_POST['emp_code']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $designation = mysqli_real_escape_string($conn, $_POST['designation']);
    $doj = mysqli_real_escape_string($conn, $_POST['doj']);
    $basic_pay = mysqli_real_escape_string($conn, $_POST['basic_pay']);
    $sb_account_no = mysqli_real_escape_string($conn, $_POST['sb_account_no'] ?? '');
    $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name'] ?? '');
    $branch_name = mysqli_real_escape_string($conn, $_POST['branch_name'] ?? '');
    $status = mysqli_real_escape_string($conn, $_POST['status']);

    $update_sql = "UPDATE employees SET 
                    name = '$name', 
                    designation = '$designation', 
                    doj = '$doj', 
                    basic_pay = '$basic_pay', 
                    sb_account_no = '$sb_account_no', 
                    bank_name = '$bank_name', 
                    branch_name = '$branch_name',
                    status = '$status'
                   WHERE emp_code = '$emp_code'";

    if (mysqli_query($conn, $update_sql)) {
        $_SESSION['message'] = "Employee details for '$emp_code' updated successfully!";
        $_SESSION['alert_type'] = "warning";
    } else {
        $_SESSION['message'] = "Error updating employee: " . mysqli_error($conn);
        $_SESSION['alert_type'] = "danger";
    }
    header("Location: index.php");
    exit;
}

// C. Handle DELETE Employee
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['emp_code'])) {
    $emp_code = mysqli_real_escape_string($conn, $_GET['emp_code']);

    // Start transaction
    $conn->begin_transaction();
    try {
        // 1. Delete dependent records (e.g., payslips, if applicable) - DANGER ZONE, adjust based on your FK constraints.
        // Assuming cascade delete is NOT set, we delete payslips first
        // $delete_payslips_sql = "DELETE FROM payslips WHERE emp_code = '$emp_code'";
        // mysqli_query($conn, $delete_payslips_sql);

        // 2. Delete the employee record
        $delete_employee_sql = "DELETE FROM employees WHERE emp_code = '$emp_code'";
        if (!mysqli_query($conn, $delete_employee_sql)) {
            throw new Exception("Error deleting employee record: " . mysqli_error($conn));
        }

        $conn->commit();
        $_SESSION['message'] = "Employee '$emp_code' deleted successfully.";
        $_SESSION['alert_type'] = "danger";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = "Failed to delete employee '$emp_code'. " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
    }
    header("Location: index.php");
    exit;
}

// --- 3B. Handle APPRAISAL OPERATIONS ---

// Apply Appraisal
if (isset($_POST['apply_appraisal'])) {
    try {
        $emp_code = mysqli_real_escape_string($conn, $_POST['emp_code']);
        $increment_percentage = floatval($_POST['increment_percentage']);
        
        if (empty($emp_code) || $increment_percentage < 0) {
            throw new Exception("Invalid employee or increment percentage");
        }
        
        // Get current basic pay
        $emp_sql = "SELECT basic_pay FROM employees WHERE emp_code = '$emp_code'";
        $emp_res = mysqli_query($conn, $emp_sql);
        if (!$emp_res || mysqli_num_rows($emp_res) == 0) {
            throw new Exception("Employee not found");
        }
        
        $emp_row = mysqli_fetch_assoc($emp_res);
        $previous_basic = $emp_row['basic_pay'];
        $new_basic = $previous_basic * (1 + $increment_percentage / 100);
        $appraisal_date = date('Y-m-d');
        
        // Insert appraisal record
        $insert_sql = "INSERT INTO appraisals (emp_code, previous_basic, increment_percentage, new_basic, appraisal_date) 
                      VALUES ('$emp_code', $previous_basic, $increment_percentage, $new_basic, '$appraisal_date')";
        
        if (!mysqli_query($conn, $insert_sql)) {
            throw new Exception(mysqli_error($conn));
        }
        
        // Update employee's basic pay
        $update_sql = "UPDATE employees SET basic_pay = $new_basic WHERE emp_code = '$emp_code'";
        if (!mysqli_query($conn, $update_sql)) {
            throw new Exception(mysqli_error($conn));
        }
        
        $_SESSION['message'] = "Appraisal applied successfully! New basic pay: ₹" . number_format($new_basic, 2);
        $_SESSION['alert_type'] = "success";
        
    } catch (Exception $e) {
        $_SESSION['message'] = "Error applying appraisal: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
    }
    header("Location: index.php");
    exit;
}

// Delete Appraisal
if (isset($_POST['delete_appraisal'])) {
    try {
        $appraisal_id = intval($_POST['appraisal_id']);
        
        // Get appraisal details
        $appraisal_sql = "SELECT emp_code, previous_basic FROM appraisals WHERE id = $appraisal_id";
        $appraisal_res = mysqli_query($conn, $appraisal_sql);
        if (!$appraisal_res || mysqli_num_rows($appraisal_res) == 0) {
            throw new Exception("Appraisal record not found");
        }
        
        $appraisal_row = mysqli_fetch_assoc($appraisal_res);
        
        // Delete appraisal record
        $delete_sql = "DELETE FROM appraisals WHERE id = $appraisal_id";
        if (!mysqli_query($conn, $delete_sql)) {
            throw new Exception(mysqli_error($conn));
        }
        
        // Revert employee's basic pay
        $update_sql = "UPDATE employees SET basic_pay = " . $appraisal_row['previous_basic'] . " WHERE emp_code = '" . $appraisal_row['emp_code'] . "'";
        if (!mysqli_query($conn, $update_sql)) {
            throw new Exception(mysqli_error($conn));
        }
        
        $_SESSION['message'] = "Appraisal record deleted successfully!";
        $_SESSION['alert_type'] = "warning";
        
    } catch (Exception $e) {
        $_SESSION['message'] = "Error deleting appraisal: " . $e->getMessage();
        $_SESSION['alert_type'] = "danger";
    }
    header("Location: index.php");
    exit;
}

// --- POST Endpoint for updating appraisal ---
if (isset($_POST['update_appraisal'])) {
    // Note: This block now expects a POST request, which is fine, but it also
    // sends JSON and exits, which is typical for an AJAX POST handler.
    header('Content-Type: application/json');
    try {
        // Ensure you have a 'name="increment_percentage"' field in the edit form in appraisal.php
        $appraisal_id = intval($_POST['appraisal_id']);
        $increment_percentage = floatval($_POST['increment_percentage']); 
        
        // Get current appraisal to get emp_code and previous_basic
        $appraisal_sql = "SELECT emp_code, previous_basic FROM appraisals WHERE id = $appraisal_id";
        $appraisal_res = $conn->query($appraisal_sql);
        if (!$appraisal_res || $appraisal_res->num_rows == 0) {
            throw new Exception("Appraisal not found");
        }
        
        $appraisal = $appraisal_res->fetch_assoc();
        $emp_code = $appraisal['emp_code'];
        $previous_basic = $appraisal['previous_basic'];
        $new_basic = $previous_basic * (1 + $increment_percentage / 100);
        
        // Update appraisal record
        $update_sql = "UPDATE appraisals SET increment_percentage = $increment_percentage, new_basic = $new_basic WHERE id = $appraisal_id";
        if (!$conn->query($update_sql)) {
            throw new Exception($conn->error);
        }
        
        // Update employee's basic pay
        $emp_update_sql = "UPDATE employees SET basic_pay = $new_basic WHERE emp_code = '$emp_code'";
        if (!$conn->query($emp_update_sql)) {
            throw new Exception($conn->error);
        }
        
        // Redirect back to appraisal page if this was a non-AJAX POST (optional, but keep for robustness)
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
             $_SESSION['message'] = "Appraisal updated successfully!";
             $_SESSION['alert_type'] = "warning";
             header("Location: appraisal.php");
             exit;
        }

        echo json_encode(['status' => 'success', 'message' => 'Appraisal updated successfully']);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    mysqli_close($conn);
    exit;
}

// --- 4. FILTERING & PAGINATION SETUP ---

$search_code = mysqli_real_escape_string($conn, $_GET['emp_code'] ?? '');
$search_name = mysqli_real_escape_string($conn, $_GET['search_name'] ?? '');
$search_designation = mysqli_real_escape_string($conn, $_GET['search_designation'] ?? '');
$order_by = $_GET['order_by'] ?? 'name_asc';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$rows_per_page = intval($_GET['rows_per_page'] ?? 10);
$page = intval($_GET['page'] ?? 1);

// Initialize filter variables for display (to avoid undefined variable notice)
$from_date_display = htmlspecialchars($from_date);
$to_date_display = htmlspecialchars($to_date);
if ($page < 1) $page = 1;

$limit = $rows_per_page;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_clauses = [];
if (!empty($search_code)) {
    $where_clauses[] = "emp_code LIKE '%$search_code%'";
}
if (!empty($search_name)) {
    // Use name as pattern, allowing partial match anywhere
    $where_clauses[] = "name LIKE '%$search_name%'";
}
if (!empty($search_designation)) {
    $where_clauses[] = "designation = '$search_designation'";
}
if (!empty($from_date)) {
    $where_clauses[] = "doj >= '$from_date'";
}
if (!empty($to_date)) {
    $where_clauses[] = "doj <= '$to_date'";
}

$where_sql = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";

// Build ORDER BY clause
switch ($order_by) {
    case 'name_desc': $order_sql = "ORDER BY name DESC"; break;
    case 'basic_pay_desc': $order_sql = "ORDER BY basic_pay DESC"; break;
    case 'doj_asc': $order_sql = "ORDER BY doj ASC"; break;
    case 'doj_desc': $order_sql = "ORDER BY doj DESC"; break;
    case 'name_asc': 
    default: $order_sql = "ORDER BY name ASC"; break;
}

// 5. Fetch Unique Designations for Filter
$designation_res = mysqli_query($conn, "SELECT DISTINCT designation FROM employees ORDER BY designation ASC");
$unique_designations = [];
while ($row = mysqli_fetch_assoc($designation_res)) {
    $unique_designations[] = htmlspecialchars($row['designation']);
}

// 6. COUNT Total Rows (for pagination)
$count_sql = "SELECT COUNT(*) AS total FROM employees" . $where_sql;
$count_res = mysqli_query($conn, $count_sql);
$total_rows = mysqli_fetch_assoc($count_res)['total'];
$total_pages = ceil($total_rows / $limit);

// 7. Final SQL Query for Display
$sql = "SELECT emp_code, name, designation, basic_pay, doj, status FROM employees 
        {$where_sql} 
        {$order_sql}
        LIMIT {$limit} OFFSET {$offset}";

// 8. Prepare columns for header (not used in user's provided structure, but kept for context if needed)
$selected_columns = $_GET['columns'] ?? ['emp_code', 'name', 'designation', 'basic_pay', 'doj', 'status'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title; ?></title>

    <link rel="stylesheet" href="adminlte/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="adminlte/dist/css/adminlte.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <style>
        /* Ensure table columns display properly */
        #employeeTable th, 
        #employeeTable td {
            transition: display 0.2s ease;
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
                    <li class="nav-item"><a href="index.php" class="nav-link active"><i class="nav-icon fas fa-users"></i><p>Manage Employees</p></a></li>
                    <li class="nav-item"><a href="payslip_list.php" class="nav-link"><i class="nav-icon fas fa-receipt"></i><p>Payslips</p></a></li>
                    <li class="nav-item"><a href="appraisal.php" class="nav-link"><i class="nav-icon fas fa-chart-line"></i><p>Appraisal History</p></a></li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-12">
                        <h1>Employee Master</h1>
                    </div>
                </div>
            </div>
        </section>
        
        

        <section class="content">
            <div class="container-fluid">

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>

                <div id="manageEmployeesSection" class="content-section">
                    
                    <div class="card card-secondary card-outline" id="addEmployeeCard">
                        <div class="card-header d-flex align-items-center" style="justify-content: space-between;">
                            <h3 class="card-title mb-0">Employee Master</h3>
                            <div class="ml-auto">
                                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addEmployeeModal">
                                    <i class="fas fa-user-plus"></i> Add Employee
                                </button>
                            </div>
                        </div>
                    </div>


                    <div class="modal fade" id="addEmployeeModal" tabindex="-1" role="dialog" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <form method="POST" id="addEmployeeForm" class="form-horizontal">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title" id="addEmployeeModalLabel">Add New Employee</h5>
                                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                    <div class="modal-body">

                                        <div class="form-row">
                                            <div class="form-group col-md-3">
                                                <label>Employee Code*</label>
                                                <input type="text" name="emp_code" class="form-control" required placeholder="E001">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>Name*</label>
                                                <input type="text" 
                                                name="name" 
                                                id="add_name"
                                                class="form-control" 
                                                required 
                                                placeholder="John Doe"
                                                pattern="[A-Za-z\s]+"
                                                title="Only letters and spaces are allowed">
                                                <div class="invalid-feedback" id="add_name_error" style="display: none;">Only letters and spaces are allowed.</div>
                                            </div>

                                            <div class="form-group col-md-3">
                                                <label>Basic Pay*</label>
                                                <input type="number" step="0.01" name="basic_pay" class="form-control" required placeholder="50000.00" min="0">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>Designation*</label>
                                                <input type="text" name="designation" class="form-control" required placeholder="Software Engineer">
                                            </div>
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group col-md-3">
                                                <label>SB Account No</label>
                                                <input type="text" name="sb_account_no" id="add_account" class="form-control" placeholder="1234567890">
                                                <div class="invalid-feedback" id="add_account_error" style="display: none;">Account number must be numeric.</div>
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>Bank Name</label>
                                                <input type="text" name="bank_name" class="form-control" placeholder="Bank Name">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>Branch Name</label>
                                                <input type="text" name="branch_name" class="form-control" placeholder="Branch Name">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>Date of Joining*</label>
                                                <input type="date" name="doj" class="form-control" required max="<?php echo $today; ?>">
                                            </div>
                                        </div>

                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                        <button type="submit" name="add_employee" class="btn btn-success">Save Employee</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="card card-secondary card-outline">
                        <div class="card-header"><h3 class="card-title">Filter / Search Employees</h3></div>
                        <div class="card-body">
                            <form method="GET" id="filterForm" class="form-row">
                                <div class="form-group col-md-3">
                                    <label>Search by Emp Code</label>
                                    <input type="text" class="form-control" name="emp_code" placeholder="Enter Emp Code" value="<?= htmlspecialchars($_GET['emp_code'] ?? '') ?>">
                                </div>

                                <div class="form-group col-md-3">
                                    <label>Search by Name</label>
                                    <input type="text" 
                                    name="search_name" 
                                    value="<?php echo htmlspecialchars($search_name ?? ''); ?>" 
                                    class="form-control" 
                                    placeholder="Name"
                                    pattern="[A-Za-z\s]+" 
                                    title="Only letters and spaces are allowed">
                                </div>

                                <div class="form-group col-md-3">
                                    <label>Filter by Designation</label>
                                    <select name="search_designation" class="form-control">
                                        <option value="">-- All Designations --</option>
                                        <?php foreach ($unique_designations as $designation): ?>
                                            <option value="<?php echo $designation; ?>"
                                            <?php if ($designation == $search_designation) echo 'selected'; ?>>
                                            <?php echo $designation; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group col-md-3">
                                    <label>Order By</label>
                                    <select name="order_by" class="form-control" onchange="document.getElementById('filterForm').submit()">
                                        <option value="name_asc" <?php if($order_by=='name_asc') echo 'selected'; ?>>Name Asc</option>
                                        <option value="name_desc" <?php if($order_by=='name_desc') echo 'selected'; ?>>Name Desc</option>
                                        <option value="basic_pay_desc" <?php if($order_by=='basic_pay_desc') echo 'selected'; ?>>Basic Pay Desc</option>
                                        <option value="doj_asc" <?php if($order_by=='doj_asc') echo 'selected'; ?>>Date of Joining Asc</option>
                                        <option value="doj_desc" <?php if($order_by=='doj_desc') echo 'selected'; ?>>Date of Joining Desc</option>
                                    </select></div>

                                <div class="form-group col-md-3">
                                    <label>From Date (DOJ)</label>
                                    <input type="date" name="from_date" id="from_date" class="form-control" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_GET['from_date'] ?? ''); ?>">
                                </div>

                                <div class="form-group col-md-3">
                                    <label>To Date (DOJ)</label>
                                    <input type="date" name="to_date" id="to_date" class="form-control" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_GET['to_date'] ?? ''); ?>">
                                </div>

                                <div class="form-group col-md-3">
                                    <label>Show Entries</label>
                                    <select name="rows_per_page" id="rows_per_page" class="form-control" onchange="document.getElementById('filterForm').submit()">
                                        <option value="1"  <?php if($rows_per_page == 1) echo 'selected'; ?>>1</option>
                                        <option value="3"  <?php if($rows_per_page == 3) echo 'selected'; ?>>3</option>
                                        <option value="5"  <?php if($rows_per_page == 5) echo 'selected'; ?>>5</option>
                                        <option value="10" <?php if($rows_per_page == 10) echo 'selected'; ?>>10</option>
                                        <option value="25" <?php if($rows_per_page == 25) echo 'selected'; ?>>25</option>
                                        <option value="50" <?php if($rows_per_page == 50) echo 'selected'; ?>>50</option>
                                        <option value="100"<?php if($rows_per_page == 100) echo 'selected'; ?>>100</option>
                                    </select>
                                </div>

                                <div class="form-group col-md-3">
                                    <label>Select Columns</label>
                                    <select id="columns" name="columns[]" class="form-control" multiple>
                                        <option value="emp_code" <?php echo in_array('emp_code', $selected_columns) ? 'selected' : ''; ?>>Employee Code</option>
                                        <option value="name" <?php echo in_array('name', $selected_columns) ? 'selected' : ''; ?>>Name</option>
                                        <option value="designation" <?php echo in_array('designation', $selected_columns) ? 'selected' : ''; ?>>Designation</option>
                                        <option value="basic_pay" <?php echo in_array('basic_pay', $selected_columns) ? 'selected' : ''; ?>>Basic Pay</option>
                                        <option value="doj" <?php echo in_array('doj', $selected_columns) ? 'selected' : ''; ?>>Date of Joining</option>
                                        <option value="status" <?php echo in_array('status', $selected_columns) ? 'selected' : ''; ?>>Status</option>
                                    </select>
                                </div>               
                                    
                                <div class="form-group col-md-3 d-flex align-items-end">
                                    <button class="btn btn-secondary mr-2" type="submit" name="search">Filter</button>
                                    <a class="btn btn-default" href="index.php">Clear</a>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <?php
                            // Build query string from current GET parameters so the export receives the same filters
                            $query_params = $_GET;
                            unset($query_params['page']); // remove pagination param
                            $qs = http_build_query($query_params);
                            ?>
                            <a href="export_excel.php?<?= $qs ?>&page=<?= $page ?>&rows_per_page=<?= $limit ?>" class="btn btn-success mr-2">
                                <i class="fas fa-file-excel"></i> Download Excel
                            </a>
                            <a href="export_employees_pdf.php?<?= $qs ?>&page=<?= $page ?>&rows_per_page=<?= $limit ?>" class="btn btn-danger"><i class="fas fa-file-pdf"></i> Download PDF (A4)</a>
                        </div>


                        <div class="card-header bg-dark text-white"><h3 class="card-title">Existing Employees (Total: <?php echo $total_rows; ?>)</h3></div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table id="employeeTable" class="table table-hover">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th>Employee Code</th>
                                            <th>Name</th>
                                            <th>Designation</th>
                                            <th>Basic Pay</th>
                                            <th>Date of Joining</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $res = mysqli_query($conn, $sql);
                                        if (mysqli_num_rows($res) > 0) {
                                            while ($row = mysqli_fetch_assoc($res)) {
                                                $delete_url = "index.php?action=delete&emp_code=" . urlencode($row['emp_code']);
                                                echo "<tr>";
                                                echo "<td>".htmlspecialchars($row['emp_code'])."</td>";
                                                echo "<td>".htmlspecialchars($row['name'])."</td>";
                                                echo "<td>".htmlspecialchars($row['designation'])."</td>";
                                                echo "<td>₹ ".number_format($row['basic_pay'],2)."</td>";
                                                echo "<td>".htmlspecialchars($row['doj'])."</td>"; 
                                                $status_badge = $row['status'] === 'Active'
                                                ? "<span class='badge badge-success'>Active</span>"
                                                : "<span class='badge badge-secondary'>Inactive</span>";
                                                echo "<td>$status_badge</td>"; 
                                                echo "<td>
                                                <button class='btn btn-sm btn-warning editBtn' data-empcode='".htmlspecialchars($row['emp_code'])."'> 
                                                <i class='fas fa-edit'></i> Edit
                                                </button>
                                                <a href='$delete_url' class='btn btn-sm btn-danger deleteBtn' data-empcode='".htmlspecialchars($row['emp_code'])."' data-name='".htmlspecialchars($row['name'])."' data-toggle='modal' data-target='#confirmDeleteModal'>
                                                    <i class='fas fa-trash'></i> Delete
                                                </a>
                                                </td>"; 
                                                echo "</tr>";
                                            }
                                        } else {
                                            echo "<tr><td colspan='7' class='text-center'>No employees found matching your criteria.</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="editEmployeeModal" tabindex="-1" role="dialog" aria-labelledby="editEmployeeModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg" role="document">
                            <div class="modal-content">
                                <div class="modal-header bg-warning text-white">
                                    <h5 class="modal-title" id="editEmployeeModalLabel">Edit Employee</h5>
                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="text-center p-5"><i class="fas fa-spinner fa-spin fa-2x"></i> Loading employee data...</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" role="dialog" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Deletion</h5>
                                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <p>Are you sure you want to delete employee: <strong id="deleteEmployeeName"></strong> (<strong id="deleteEmployeeCode"></strong>)?</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <a id="confirmDeleteLink" href="#" class="btn btn-danger">Delete Employee</a>
                                </div>
                            </div>
                        </div>
                    </div>


                    <div class="card-footer text-center">
                        <nav>
                            <ul class="pagination justify-content-center mb-0">
                                <?php 
                                // Build query parameters
                                $params = $_GET;
                                
                                // Previous page
                                if ($page > 1): 
                                    $params['page'] = $page - 1;
                                ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query($params); ?>">Previous</a>
                                    </li>
                                <?php endif; ?>

                                <?php 
                                // Show page numbers
                                $start = max(1, $page - 2);
                                $end = min($total_pages, $page + 2);
                                
                                // Show first page if not in range
                                if ($start > 1) {
                                    $params['page'] = 1;
                                    echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($params) . '">1</a></li>';
                                    if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }

                                // Show page numbers
                                for ($i = $start; $i <= $end; $i++): 
                                    $params['page'] = $i;
                                ?>
                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query($params); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; 
                                
                                // Show last page if not in range
                                if ($end < $total_pages) {
                                    if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    $params['page'] = $total_pages;
                                    echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($params) . '">' . $total_pages . '</a></li>';
                                }

                                // Next page
                                if ($page < $total_pages): 
                                    $params['page'] = $page + 1;
                                ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query($params); ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <p class="mt-2 text-muted">Showing <?php echo min($total_rows, $offset + 1); ?> to <?php echo min($total_rows, $offset + $limit); ?> of <?php echo $total_rows; ?> entries</p>
                    </div>

                </div>
                </div>
        </section>
    </div>

    <footer class="main-footer">
        <strong>Copyright &copy; 2025 Payroll System.</strong> All rights reserved.
    </footer>
</div>

<script src="adminlte/plugins/jquery/jquery.min.js"></script>
<script src="adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="adminlte/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

<script>
$(document).ready(function() {
    // --- 1. Custom Validation for Add Employee Modal ---
    const addForm = document.getElementById('addEmployeeForm');
    if (addForm) {
        addForm.addEventListener('submit', function (event) {
            let isValid = true;
            
            const nameInput = document.getElementById('add_name');
            const nameError = document.getElementById('add_name_error');
            const accountInput = document.getElementById('add_account');
            const accountError = document.getElementById('add_account_error');

            // A. Check if Name contains only letters and spaces
            const nameValue = nameInput.value.trim();
            if (nameValue !== '' && !/^[A-Za-z\s]+$/.test(nameValue)) {
                nameError.style.display = 'block';
                nameInput.classList.add('is-invalid');
                isValid = false;
            } else {
                nameError.style.display = 'none';
                nameInput.classList.remove('is-invalid');
            }
            
            // B. Check if SB Account No contains only digits
            const accountValue = accountInput.value.trim();
            if (accountValue !== '' && !/^[0-9]+$/.test(accountValue)) {
                accountError.style.display = 'block';
                accountInput.classList.add('is-invalid');
                isValid = false;
            } else {
                accountError.style.display = 'none';
                accountInput.classList.remove('is-invalid');
            }

            // C. Halt submission if custom checks fail
            if (!isValid) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    }

    // --- 2. Edit Employee Modal Logic (AJAX) ---
    $(document).on('click', '.editBtn', function() {
        const empCode = $(this).data('empcode');
        const modalBody = $('#editEmployeeModal .modal-body');

        // Show loading spinner
        modalBody.html('<div class="text-center p-5"><i class="fas fa-spinner fa-spin fa-2x"></i> Loading employee data...</div>');
        $('#editEmployeeModal').modal('show');

        // Fetch employee data via AJAX (to a hidden endpoint in this same file)
        $.get('index.php', { action: 'fetch_employee', emp_code: empCode }, function(data) {
            if (data.status === 'success') {
                // Build the form using the fetched data
                const employee = data.employee;
                const editFormHtml = `
                    <form method="POST" id="updateEmployeeForm" class="form-horizontal">
                        <input type="hidden" name="emp_code" value="${employee.emp_code}">
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label>Employee Code</label>
                                <input type="text" class="form-control" value="${employee.emp_code}" readonly>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Name*</label>
                                <input type="text" name="name" id="edit_name" class="form-control" required placeholder="Name" value="${employee.name}" pattern="[A-Za-z\\s]+" title="Only letters and spaces are allowed">
                                <div class="invalid-feedback" id="edit_name_error" style="display: none;">Only letters and spaces are allowed.</div>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Basic Pay*</label>
                                <input type="number" step="0.01" name="basic_pay" class="form-control" required placeholder="50000.00" value="${employee.basic_pay}" min="0">
                            </div>
                            <div class="form-group col-md-3">
                                <label>Designation*</label>
                                <input type="text" name="designation" class="form-control" required placeholder="Designation" value="${employee.designation}">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label>SB Account No</label>
                                <input type="text" name="sb_account_no" id="edit_account" class="form-control" placeholder="1234567890" value="${employee.sb_account_no || ''}">
                                <div class="invalid-feedback" id="edit_account_error" style="display: none;">Account number must be numeric.</div>
                            </div>
                            <div class="form-group col-md-3">
                                <label>Bank Name</label>
                                <input type="text" name="bank_name" class="form-control" placeholder="Bank Name" value="${employee.bank_name || ''}">
                            </div>
                            <div class="form-group col-md-3">
                                <label>Branch Name</label>
                                <input type="text" name="branch_name" class="form-control" placeholder="Branch Name" value="${employee.branch_name || ''}">
                            </div>
                            <div class="form-group col-md-3">
                                <label>Date of Joining*</label>
                                <input type="date" name="doj" class="form-control" required max="<?php echo $today; ?>" value="${employee.doj}">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label>Status</label>
                                <select name="status" class="form-control" required>
                                    <option value="Active" ${employee.status === 'Active' ? 'selected' : ''}>Active</option>
                                    <option value="Inactive" ${employee.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="modal-footer d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_employee" class="btn btn-warning"><i class="fas fa-save"></i> Update Employee</button>
                        </div>
                    </form>
                `;
                modalBody.html(editFormHtml);

                // Re-attach validation logic for the dynamically generated form
                $('#updateEmployeeForm').on('submit', function (e) {
                    let isValid = true;
                    const nameInput = document.getElementById('edit_name');
                    const nameError = document.getElementById('edit_name_error');
                    const accountInput = document.getElementById('edit_account');
                    const accountError = document.getElementById('edit_account_error');

                    // A. Check if Name contains only letters and spaces
                    const nameValue = nameInput.value.trim();
                    if (nameValue !== '' && !/^[A-Za-z\s]+$/.test(nameValue)) {
                        nameError.style.display = 'block';
                        nameInput.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        nameError.style.display = 'none';
                        nameInput.classList.remove('is-invalid');
                    }
                    
                    // B. Check if SB Account No contains only digits
                    const accountValue = accountInput.value.trim();
                    if (accountValue !== '' && !/^[0-9]+$/.test(accountValue)) {
                        accountError.style.display = 'block';
                        accountInput.classList.add('is-invalid');
                        isValid = false;
                    } else {
                        accountError.style.display = 'none';
                        accountInput.classList.remove('is-invalid');
                    }

                    if (!isValid) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                });

            } else {
                modalBody.html('<p class="alert alert-danger">Error loading employee data: ' + data.message + '</p>');
            }
        }, 'json').fail(function() {
             modalBody.html('<p class="alert alert-danger">An unexpected error occurred while fetching employee data.</p>');
        });
    });

    // --- 3. Delete Modal Confirmation Setup ---
    $(document).on('click', '.deleteBtn', function(e) {
        e.preventDefault(); // Prevent default link action (which would delete immediately)
        const empCode = $(this).data('empcode');
        const empName = $(this).data('name');
        const deleteUrl = $(this).attr('href'); // Get the pre-built delete URL

        $('#deleteEmployeeCode').text(empCode);
        $('#deleteEmployeeName').text(empName);
        $('#confirmDeleteLink').attr('href', deleteUrl); // Set the URL on the confirm button
        
        // Modal is already triggered via data-target="#confirmDeleteModal"
    });

    // --- 4. APPRAISAL FUNCTIONALITY ---
    
    // Calculate new basic pay on increment change
    $('#increment_percentage').on('keyup change', function() {
        const empCode = $('#appraisal_emp_code').val();
        const selectedOption = $('#appraisal_emp_code option:selected');
        const basicPay = parseFloat(selectedOption.attr('data-basic')) || 0;
        const increment = parseFloat($(this).val()) || 0;
        
        if (basicPay > 0) {
            const newBasic = basicPay * (1 + increment / 100);
            $('#new_basic_preview').val('₹' + newBasic.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ","));
        }
    });

    // Recalculate when employee changes
    $('#appraisal_emp_code').on('change', function() {
        const selectedOption = $('option:selected', this);
        const basicPay = parseFloat(selectedOption.attr('data-basic')) || 0;
        const increment = parseFloat($('#increment_percentage').val()) || 0;
        
        if (basicPay > 0) {
            const newBasic = basicPay * (1 + increment / 100);
            $('#new_basic_preview').val('₹' + newBasic.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ","));
        } else {
            $('#new_basic_preview').val('₹0.00');
        }
    });

    // Load edit appraisal form via AJAX
    $(document).on('click', '.editAppraisalBtn', function() {
        const appraisalId = $(this).data('appraisal-id');
        const modalBody = $('#editAppraisalModal .modal-body');

        // Show loading
        modalBody.html(`
            <div class="text-center">
                <div class="spinner-border text-warning"></div>
                <p>Loading appraisal details...</p>
            </div>
        `);
        $('#editAppraisalModal').modal('show');

        // Fetch appraisal data
        $.get('index.php', { action: 'fetch_appraisal', appraisal_id: appraisalId }, function(data) {
            if (data.status === 'success') {
                const appraisal = data.appraisal;
                // Store previous_basic in a proper JS variable for calculation
                const previousBasicValue = parseFloat(appraisal.previous_basic); 
                
                const formHtml = `
                    <form method="POST" id="updateAppraisalForm">
                        <input type="hidden" name="appraisal_id" value="${appraisal.id}">
                        <div class="form-group">
                            <label>Employee</label>
                            <input type="text" class="form-control" value="${appraisal.emp_code} - ${appraisal.name}" readonly>
                        </div>
                        <div class="form-group">
                            <label>Previous Basic Pay</label>
                            <input type="text" class="form-control" value="₹${previousBasicValue.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}" readonly>
                        </div>
                        <div class="form-group">
                            <label>Increment Percentage *</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" class="form-control edit-increment" name="increment_percentage" value="${appraisal.increment_percentage}" required>
                                <div class="input-group-append">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>New Basic Pay</label>
                            <input type="text" class="form-control font-weight-bold text-success edit-new-basic" value="₹${parseFloat(appraisal.new_basic).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}" readonly>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_appraisal" class="btn btn-warning">
                                <i class="fas fa-save"></i> Update Appraisal
                            </button>
                        </div>
                    </form>
                `;
                modalBody.html(formHtml);

                // Recalculate on increment change
                $(document).off('keyup change', '.edit-increment').on('keyup change', '.edit-increment', function() {
                    // FIX: Use the JS variable 'previousBasicValue' instead of PHP string interpolation
                    const increment = parseFloat($(this).val()) || 0;
                    const newBasic = previousBasicValue * (1 + increment / 100); 
                    $('.edit-new-basic').val('₹' + newBasic.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                });

            } else {
                modalBody.html(`<p class="alert alert-danger">Error: ${data.message}</p>`);
            }
        }, 'json').fail(function() {
            modalBody.html('<p class="alert alert-danger">Failed to load appraisal details.</p>');
        });
    });

});
// index.php inside $(document).ready(function() { ... });
// --- TOM SELECT INITIALIZATION FOR COLUMN FILTER ---
// This creates the tag-based, searchable input format (like in the picture)
let tomSelectInstance = null;

function initializeColumnFilter() {
    const columnsSelect = document.getElementById('columns');
    if (columnsSelect && !tomSelectInstance) {
        tomSelectInstance = new TomSelect("#columns", {
            plugins: ['remove_button'], // Allows clicking the 'x' to remove a tag
            maxItems: null,              // Allows selecting unlimited options
            placeholder: "Select columns...",
            
            onChange: function(value) {
                // Update table visibility immediately
                updateTableColumns();
                
                // Then submit the form to persist the selection
                const filterForm = document.getElementById('filterForm');
                if (filterForm) {
                    filterForm.submit();
                }
            }
        });
        
        // Initial column update after TomSelect is initialized
        setTimeout(updateTableColumns, 100);
    }
}

// --- DYNAMIC COLUMN VISIBILITY ---
// Map column values to table column indices (0-based)
const columnIndexMap = {
    'emp_code': 0,
    'name': 1,
    'designation': 2,
    'basic_pay': 3,
    'doj': 4,
    'status': 5
};

function getSelectedColumns() {
    // Get selected values from TomSelect instance first
    if (tomSelectInstance) {
        const values = tomSelectInstance.getValue();
        return Array.isArray(values) ? values : (values ? [values] : []);
    }
    
    // Fallback to direct select element
    const selectElement = document.getElementById('columns');
    if (selectElement) {
        return Array.from(selectElement.querySelectorAll('option:checked')).map(el => el.value);
    }
    
    return [];
}

function updateTableColumns() {
    const selectedColumns = getSelectedColumns();
    const table = document.getElementById('employeeTable');
    
    if (!table || selectedColumns.length === 0) return;
    
    // Get all header cells and body rows
    const headerCells = table.querySelectorAll('thead th');
    const bodyRows = table.querySelectorAll('tbody tr');
    
    // Update visibility for each column
    Object.keys(columnIndexMap).forEach(colName => {
        const colIndex = columnIndexMap[colName];
        const isSelected = selectedColumns.includes(colName);
        
        // Update header cells
        if (headerCells[colIndex]) {
            headerCells[colIndex].style.display = isSelected ? '' : 'none';
        }
        
        // Update all body row cells
        bodyRows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells[colIndex]) {
                cells[colIndex].style.display = isSelected ? '' : 'none';
            }
        });
    });
}

// Initialize column filter when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize TomSelect
    initializeColumnFilter();
    
    // Update table columns display
    setTimeout(updateTableColumns, 200);
});

// ... (rest of your existing JS code follows)
</script>

<?php
// Close DB connection for the main page (if it hasn't been closed by AJAX endpoints)
if (isset($conn)) {
    mysqli_close($conn); 
}
?>
</body>
</html>