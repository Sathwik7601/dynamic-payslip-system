<?php
session_start();
require_once 'config/connection.php'; 

// --- 1. SECURITY CHECK ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

date_default_timezone_set('Asia/Kolkata');
$today = date('Y-m-d');
$current_month = date('m');
$current_year = date('Y');
$message = '';
$alert_type = '';

// --- FUNCTION FOR PERMANENT FIX: Fetches component IDs by name ---
function get_pay_components_map($conn) {
    $map = [];
    $sql = "SELECT component_id, component_name FROM pay_components";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            // Map the name to the ID: ['Basic Pay' => 10]
            $map[trim($row['component_name'])] = (int)$row['component_id'];
        }
    }
    return $map;
}

// Get the map immediately
$component_map = get_pay_components_map($conn);


// --- 2. HANDLE EMPLOYEE CODE INPUT (Integrated Search) ---
$emp_code = $_GET['emp_code'] ?? null;
if (!$emp_code) {
    $emp_code = $_POST['emp_code_search'] ?? null; 
}

$employee = null;
$clean_emp_code = null;

if ($emp_code) {
    $clean_emp_code = mysqli_real_escape_string($conn, $emp_code);
    
    $emp_sql = "SELECT emp_code, name, designation, basic_pay, sb_account_no, bank_name, branch_name, doj FROM employees WHERE emp_code = '$clean_emp_code'";
    $emp_res = mysqli_query($conn, $emp_sql);
    $employee = mysqli_fetch_assoc($emp_res);

    if (!$employee) {
        $message = "Error: Employee Code '{$emp_code}' not found. Please enter a valid code.";
        $alert_type = "danger";
        $emp_code = null; 
    }
}

$page_title = "Generate Acquittance Roll";

// --- 3. INITIALIZE FORM DATA AND CALCULATE/SAVE ---
$gross_total = 0.00;
$total_deduction = 0.00;
$net_total = 0.00;
$form_data = $_POST;

if ($employee) {
    
    // --- Automatic Pre-filling / Calculation based on database rules (if not already posted) ---
    if (!isset($_POST['calculate']) && !isset($_POST['save_payslip']) && !isset($_POST['emp_code_form'])) {
        $base_pay = (float)($employee['basic_pay'] ?? 0);
        
        // Use component names for clarity and future proofing
        $form_data['basic_pay'] = $base_pay;
        $form_data['dearness_allowance'] = $base_pay * 0.10;      
        $form_data['house_rent_allowance'] = $base_pay * 0.05;    
        $form_data['city_compensatory_allowance'] = 0.00;         
        $form_data['medical_allowance'] = 500.00;                 
        $form_data['cpf_council_contribution_earning'] = $base_pay * 0.12; 
        
        $form_data['cpf_council_contribution_deduction'] = $base_pay * 0.12; 
        $form_data['festival_advance'] = 0.00; 
        $form_data['professional_tax'] = 200.00; 
        $form_data['tds_deduction'] = 0.00; 
        $form_data['loan_deduction'] = 0.00; 
        $form_data['custom_deduction_amount'] = 0.00; 
    }

    // Initialize all other necessary form data from POST or set page defaults
    $form_data['pay_month'] = $form_data['pay_month'] ?? $current_month;
    $form_data['pay_year'] = $form_data['pay_year'] ?? $current_year;
    $form_data['cheque_date'] = $form_data['cheque_date'] ?? $today;
    $form_data['basic_pay'] = $form_data['basic_pay'] ?? $employee['basic_pay'];
    $form_data['cheque_no'] = $form_data['cheque_no'] ?? '';
    $form_data['bill_net_total'] = $form_data['bill_net_total'] ?? '';
    $form_data['emp_code_form'] = $employee['emp_code']; 


    // --- Calculation Block (Uses the values set above, either POSTed or Auto-filled) ---
    $basic_pay = (float)($form_data['basic_pay'] ?? 0);
    $dearness_allowance = (float)($form_data['dearness_allowance'] ?? 0);
    $house_rent_allowance = (float)($form_data['house_rent_allowance'] ?? 0);
    $city_compensatory_allowance = (float)($form_data['city_compensatory_allowance'] ?? 0);
    $medical_allowance = (float)($form_data['medical_allowance'] ?? 0);
    $cpf_council_contribution = (float)($form_data['cpf_council_contribution_earning'] ?? 0); 
    $gross_total = $basic_pay + $dearness_allowance + $house_rent_allowance + $city_compensatory_allowance + $medical_allowance + $cpf_council_contribution;

    $cpf_council_contribution_deduction = (float)($form_data['cpf_council_contribution_deduction'] ?? 0);
    $festival_advance = (float)($form_data['festival_advance'] ?? 0);
    $professional_tax = (float)($form_data['professional_tax'] ?? 0);
    $tds_deduction = (float)($form_data['tds_deduction'] ?? 0);
    $loan_deduction = (float)($form_data['loan_deduction'] ?? 0);
    $custom_deduction_amount = (float)($form_data['custom_deduction_amount'] ?? 0);
    $total_deduction = $cpf_council_contribution_deduction + $festival_advance + $professional_tax + $tds_deduction + $loan_deduction + $custom_deduction_amount;

    $net_total = $gross_total - $total_deduction;
    $form_data['bill_net_total'] = number_format($net_total, 2, '.', ''); 

    // --- Save Logic (Only if Save button was pressed) ---
    if (isset($_POST['save_payslip'])) {
        $pay_month = (int)($form_data['pay_month']);
        $pay_year = (int)($form_data['pay_year']);
        $cheque_no = mysqli_real_escape_string($conn, $form_data['cheque_no'] ?? '');
        $cheque_date = mysqli_real_escape_string($conn, $form_data['cheque_date'] ?? $today);
        $bill_net_total_saved = mysqli_real_escape_string($conn, number_format($net_total, 2, '.', ''));
        
        $check_duplicate = "SELECT payslip_id FROM payslips WHERE emp_code='$clean_emp_code' AND pay_month=$pay_month AND pay_year=$pay_year";
        if (mysqli_num_rows(mysqli_query($conn, $check_duplicate)) > 0) {
            $message = "Error: Payslip already exists for this employee for " . date('F Y', mktime(0, 0, 0, $pay_month, 1, $pay_year));
            $alert_type = "danger";
        } else {
            // 1. Insert into Payslips table (summary record)
            $insert_payslip_sql = "
                INSERT INTO payslips 
                (emp_code, pay_month, pay_year, base_basic_pay, gross_total, total_deduction, net_total, cheque_no, cheque_date, bill_net_total)
                VALUES 
                ('$clean_emp_code', $pay_month, $pay_year, '$basic_pay', '$gross_total', '$total_deduction', '$net_total', '$cheque_no', '$cheque_date', '$bill_net_total_saved')
            ";

            if (mysqli_query($conn, $insert_payslip_sql)) {
                $payslip_id = mysqli_insert_id($conn); 

                // --- 2. THE PERMANENT FIX: Define components by NAME, not hardcoded ID ---
                $all_components_data = [
                    'Basic Pay' => $basic_pay, 
                    'Dearness Allowance' => $dearness_allowance, 
                    'House Rent Allowance' => $house_rent_allowance, 
                    'City Compensatory Allowance' => $city_compensatory_allowance, 
                    'Medical Allowance' => $medical_allowance, 
                    'CPF Council Contribution' => $cpf_council_contribution, 
                    'CPF Staff & Council Contribution' => $cpf_council_contribution_deduction, 
                    'Festival Advance' => $festival_advance,
                    'Professional Tax' => $professional_tax, 
                    'TDS Deduction' => $tds_deduction, 
                    'Loan Deduction' => $loan_deduction, 
                    'Custom Deduction' => $custom_deduction_amount,
                ];

                $detail_insert_success = true;
                foreach ($all_components_data as $comp_name => $comp_amount) {
                    $comp_amount = (float)$comp_amount;
                    if ($comp_amount > 0) {
                        
                        // *** CORE FIX: Look up the ID from the map ***
                        $comp_id = $component_map[$comp_name] ?? null;

                        if (!$comp_id) {
                            $detail_insert_success = false;
                            $message = "FATAL DB CONFIGURATION ERROR: The component **'{$comp_name}'** is missing from your `pay_components` table or the name is misspelled. Cannot save payslip details.";
                            $alert_type = "danger";
                            error_log("Missing Pay Component: {$comp_name}");
                            break; 
                        }

                        $detail_sql = "INSERT INTO payslip_details (payslip_id, component_id, amount, notes) VALUES ('$payslip_id', '$comp_id', '$comp_amount', NULL)";
                        
                        if (!mysqli_query($conn, $detail_sql)) {
                            $detail_insert_success = false;
                            error_log("Failed to insert payslip detail for component ID $comp_id ($comp_name). Error: " . mysqli_error($conn));
                            $message = "Database Error: Failed to save details for component '{$comp_name}'. " . mysqli_error($conn);
                            $alert_type = "danger";
                            break; 
                        }
                    }
                }
                
                // Final message
                if ($detail_insert_success) { $message = "Success: Payslip and all details saved! ID: $payslip_id. Redirecting..."; $alert_type = "success"; } 
                
                $_SESSION['message'] = $message;
                $_SESSION['alert_type'] = $alert_type;
                
                if ($alert_type !== "danger" || $detail_insert_success) {
                    header('Location: employee_details.php?emp_code=' . urlencode($clean_emp_code));
                    exit;
                }

            } else { $message = "Database Error: Could not save main payslip record. " . mysqli_error($conn); $alert_type = "danger"; }
        }
    }
}
$display_gross_total = number_format($gross_total, 2);
$display_total_deduction = number_format($total_deduction, 2);
$display_net_total = number_format($net_total, 2);

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
        .table-acquittance td, .table-acquittance th {
            padding: 4px;
            vertical-align: middle;
            border: 1px solid #ccc;
            font-size: 14px;
        }
        .table-acquittance input {
            border: none;
            text-align: right;
            padding: 2px 5px;
            height: 30px;
            width: 100%;
        }
        .table-acquittance {
            width: 100%;
            border-collapse: collapse;
        }
        .table-acquittance td:first-child, .table-acquittance th:first-child {
            text-align: left;
            padding-left: 10px;
        }
        .table-acquittance .fixed-data {
            text-align: left;
            padding-left: 10px;
            background-color: #f7f7f7;
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
                    <li class="nav-item"><a href="index.php" class="nav-link"><i class="nav-icon fas fa-users"></i><p>Manage Employees</p></a></li>
                    <li class="nav-item"><a href="generate_payslip.php" class="nav-link active"><i class="nav-icon fas fa-receipt"></i><p>Payslips / Acquittance</p></a></li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <section class="content-header">
            <div class="container-fluid">
                <h1><?php echo htmlspecialchars($page_title); ?></h1>
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

                
                <div class="card card-info card-outline">
                    <div class="card-header">
                        <h3 class="card-title">Acquittance Roll / Payslip Details Entry</h3>
                    </div>
                    <form method="POST" action="generate_payslip.php" class="form-horizontal" id="acquittanceForm">
                        
                        <div class="card-body pb-1">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="emp_code_search">Employee Code:</label>
                                    <div class="input-group">
                                        <input type="text" name="emp_code_search" id="empCodeSearchInput" class="form-control" 
                                               value="<?php echo htmlspecialchars($clean_emp_code ?? ''); ?>" required placeholder="E001">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="submit" name="fetch_employee">
                                                <i class="fa fa-search"></i> Fetch
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <?php if ($employee): ?>
                                        <h4 class="mt-2 text-primary">
                                            Employee: <?php echo htmlspecialchars($employee['name']); ?> 
                                            <small>(Designation: <?php echo htmlspecialchars($employee['designation'] ?? 'N/A'); ?>)</small>
                                        </h4>
                                    <?php else: ?>
                                        <div class="alert alert-warning mt-2 p-2">
                                            Enter an Employee Code and click **Fetch** to retrieve details and enable the payslip entry form.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <hr>
                        </div>
                        
                        <fieldset <?php echo $employee ? '' : 'disabled'; ?>>
                            <div class="card-body pt-0">
                                
                                <input type="hidden" name="emp_code_form" value="<?php echo htmlspecialchars($clean_emp_code ?? ''); ?>">

                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <table class="table-acquittance">
                                            <tr><td width="30%">Code No.</td><td width="70%" class="fixed-data"><?php echo htmlspecialchars($employee['emp_code'] ?? '-'); ?></td></tr>
                                            <tr><td>Emp Code</td><td class="fixed-data"><?php echo htmlspecialchars($employee['emp_code'] ?? '-'); ?></td></tr>
                                            <tr><td>Name</td><td class="fixed-data"><?php echo htmlspecialchars($employee['name'] ?? '-'); ?></td></tr>
                                            <tr><td>Designation</td><td class="fixed-data"><?php echo htmlspecialchars($employee['designation'] ?? '-'); ?></td></tr>
                                            <tr>
                                                <td>Pay for</td>
                                                <td>
                                                    <select name="pay_month" style="width: 45%; float: left; text-align: left;" required>
                                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                                            <option value="<?php echo $m; ?>" <?php if(($form_data['pay_month'] ?? $current_month) == $m) echo 'selected'; ?>>
                                                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                                            </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                    <input type="number" name="pay_year" style="width: 50%; float: right;" value="<?php echo htmlspecialchars($form_data['pay_year'] ?? $current_year); ?>" required>
                                                </td>
                                            </tr>
                                            <tr><td>Bill Net Total*</td><td class="fixed-data"><?php echo htmlspecialchars($form_data['bill_net_total'] ?? '0.00'); ?></td></tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table-acquittance">
                                            <tr><td>Date</td><td><input type="date" name="cheque_date" max="<?php echo $today; ?>" value="<?php echo htmlspecialchars($form_data['cheque_date'] ?? $today); ?>"></td></tr>
                                            <tr><td>Cheque No.</td><td><input type="text" name="cheque_no" value="<?php echo htmlspecialchars($form_data['cheque_no'] ?? ''); ?>"></td></tr>
                                            <tr><td>SB A/C No.</td><td class="fixed-data"><?php echo htmlspecialchars($employee['sb_account_no'] ?? '-'); ?></td></tr>
                                            <tr><td>Bank Name</td><td class="fixed-data"><?php echo htmlspecialchars($employee['bank_name'] ?? '-'); ?></td></tr>
                                            <tr><td>Branch Name</td><td class="fixed-data"><?php echo htmlspecialchars($employee['branch_name'] ?? '-'); ?></td></tr>
                                            <tr><td colspan="2" style="background-color: #f7f7f7;">*Whatever the entry we make, we have to view it here.</td></tr>
                                        </table>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table-acquittance">
                                            <thead>
                                                <tr class="bg-primary"><th width="70%">Pay Description</th><th width="30%">Amt</th></tr>
                                            </thead>
                                            <tbody>
                                                <tr><td>Basic Pay</td><td><input type="number" step="0.01" name="basic_pay" value="<?php echo number_format($form_data['basic_pay'] ?? $employee['basic_pay'] ?? 0, 2, '.', ''); ?>"></td></tr>
                                                <tr><td>Dearness Allowance</td><td><input type="number" step="0.01" name="dearness_allowance" value="<?php echo number_format($form_data['dearness_allowance'] ?? 0, 2, '.', ''); ?>"></td></tr>
                                                <tr><td>House Rent Allowance</td><td><input type="number" step="0.01" name="house_rent_allowance" value="<?php echo number_format($form_data['house_rent_allowance'] ?? 0, 2, '.', ''); ?>"></td></tr>
                                                <tr><td>City Compensatory Allowance</td><td><input type="number" step="0.01" name="city_compensatory_allowance" value="<?php echo number_format($form_data['city_compensatory_allowance'] ?? 0, 2, '.', ''); ?>"></td></tr>
                                                <tr><td>Medical Allowance</td><td><input type="number" step="0.01" name="medical_allowance" value="<?php echo number_format($form_data['medical_allowance'] ?? 0, 2, '.', ''); ?>"></td></tr>
                                                <tr><td>CPF Council Contribution</td><td><input type="number" step="0.01" name="cpf_council_contribution_earning" value="<?php echo number_format($form_data['cpf_council_contribution_earning'] ?? 0, 2, '.', ''); ?>"></td></tr>
                                                <tr><td class="text-right bg-primary">Gross Total</td><td class="bg-warning"><input type="text" value="<?php echo number_format($gross_total, 2); ?>" readonly></td></tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="col-md-6">
                                        <table class="table-acquittance">
                                            <thead>
                                                <tr class="bg-danger"><th width="70%">Pay Bill Deduction</th><th width="30%">Amt</th></tr>
                                            </thead>
                                            <tbody>
                                                <tr><td>CPF Staff & Council Contribution</td><td><input type="number" step="0.01" name="cpf_council_contribution_deduction" value="<?php echo number_format($form_data['cpf_council_contribution_deduction'] ?? 0, 2, '.', ''); ?>"></td></tr>
                                                <tr><td>Festival Advance (01/10)</td><td><input type="number" step="0.01" name="festival_advance" value="<?php echo number_format($form_data['festival_advance'] ?? 0, 2, '.', ''); ?>"></td></tr>
                                                <tr><td>Professional Tax</td><td><input type="number" step="0.01" name="professional_tax" value="<?php echo number_format($form_data['professional_tax'] ?? 0, 2, '.', ''); ?>"></td></tr>
                                                <tr><td>TDS Deduction</td><td><input type="number" step="0.01" name="tds_deduction" value="<?php echo number_format($form_data['tds_deduction'] ?? 0, 2, '.', ''); ?>"></td></tr>
                                                <tr><td>Loan Deduction</td><td><input type="number" step="0.01" name="loan_deduction" value="<?php echo number_format($form_data['loan_deduction'] ?? 0, 2, '.', ''); ?>"></td></tr>
                                                <tr><td>Custom Deduction</td><td><input type="number" step="0.01" name="custom_deduction_amount" value="<?php echo number_format($form_data['custom_deduction_amount'] ?? 0, 2, '.', ''); ?>"></td></tr>
                                                <tr><td class="text-right bg-danger">Total Deduction</td><td class="bg-warning"><input type="text" value="<?php echo number_format($total_deduction, 2); ?>" readonly></td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-md-5 offset-md-7">
                                        <table class="table-acquittance">
                                            <tr><td width="60%">Gross Total</td><td width="40%" class="fixed-data text-center"><?php echo number_format($gross_total, 2); ?></td></tr>
                                            <tr><td>Total Deduction</td><td width="40%" class="fixed-data text-center"><?php echo number_format($total_deduction, 2); ?></td></tr>
                                            <tr class="bg-info"><th class="text-white">Net Total</th><th class="text-white text-center"><?php echo number_format($net_total, 2); ?></th></tr>
                                        </table>
                                        <p class="text-muted text-right mt-1">*This amount auto visible in Bill Net Total column</p>
                                    </div>
                                </div>

                            </div>
                            
                            <div class="card-footer text-center">
                                <button type="submit" name="calculate" class="btn btn-warning me-2"><i class="fa fa-calculator"></i> Calculate</button>
                                <button type="submit" name="save_payslip" class="btn btn-success"><i class="fa fa-save"></i> SAVE</button>
                            </div>
                        </fieldset>
                    </form>
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