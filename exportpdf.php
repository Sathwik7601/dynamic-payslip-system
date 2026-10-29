<?php
session_start();
require_once 'config/connection.php'; 

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die("Access denied.");
}

// Ensure FPDF library is included
require_once('lib/fpdf.php'); 

// --- 1. Data Retrieval ---

$payslip_id = $_GET['payslip_id'] ?? die("Payslip ID missing.");

// Fetch Payslip and Employee Details
$payslip_sql = "
    SELECT 
        p.*, e.name, e.designation, e.doj, e.sb_account_no, e.emp_code
    FROM payslips p
    JOIN employees e ON p.emp_code = e.emp_code
    WHERE p.payslip_id = '" . mysqli_real_escape_string($conn, $payslip_id) . "'";

$payslip_res = mysqli_query($conn, $payslip_sql);
$payslip = mysqli_fetch_assoc($payslip_res);

if (!$payslip) {
    die("Payslip not found.");
}

// Fetch Payslip Details (Amounts for the components)
$components_sql = "
    SELECT 
        pd.amount, pc.component_name, pc.component_type
    FROM payslip_details pd
    JOIN pay_components pc ON pd.component_id = pc.component_id
    WHERE pd.payslip_id = '" . mysqli_real_escape_string($conn, $payslip_id) . "'";

$components_res = mysqli_query($conn, $components_sql);
$fetched_components = mysqli_fetch_all($components_res, MYSQLI_ASSOC);

// Calculate Totals for display 
$total_earnings = $payslip['gross_total']; 
$total_deductions = $payslip['total_deduction']; 
$net_pay_calculated = $payslip['net_total']; 

// --- 2. STATIC MASTER LIST & Data Merging (NEW LOGIC) ---

// Define the exact components shown in the uploaded image template
$master_earnings = [
    'Basic Pay',
    'Dearness Allowance',
    'House Rent Allowance',
    'City Compensatory Allowance',
    'Medical Allowance',
    'CPF Council Contribution',
];

$master_deductions = [
    'C.P.F. Subscription',
    'Festival Advance (01/10)',
    'Professional Tax',
    'TDS Deduction',
];

// Map fetched amounts for quick lookup
$fetched_map = [];
foreach ($fetched_components as $comp) {
    $fetched_map[$comp['component_type']][$comp['component_name']] = $comp['amount'];
}

// Build the final display lists, including zero-amount items
$display_earnings = [];
foreach ($master_earnings as $name) {
    $display_earnings[] = [
        'component_name' => $name,
        'amount' => $fetched_map['Earning'][$name] ?? 0.00
    ];
}

$display_deductions = [];
foreach ($master_deductions as $name) {
    $display_deductions[] = [
        'component_name' => $name,
        'amount' => $fetched_map['Deduction'][$name] ?? 0.00
    ];
}

// Set the final lists for iteration
$earnings = $display_earnings;
$deductions = $display_deductions;


// --- 3. FPDF Generation ---

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetMargins(15, 15, 15);
$page_width = 210 - (2 * 15);
$half_width = $page_width / 2;


// --- Title and Employee Details Section ---

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'EMPLOYEE PAY SLIP', 0, 1, 'C'); 
$pdf->Ln(5);

// Employee Details (Matching the template layout)
$pdf->SetFont('Arial', '', 10);
$line_height = 6;
$label_width = 30;
$value_width = 60;
$y_start = $pdf->GetY();

// Column 1 (Left Side)
$pdf->SetX(15);
$pdf->Cell($label_width, $line_height, 'Emp Code', 0, 0, 'L');
$pdf->Cell(2, $line_height, ':', 0, 0, 'C');
$pdf->Cell($value_width - 2, $line_height, $payslip['emp_code'], 0, 1, 'L');

$pdf->SetX(15);
$pdf->Cell($label_width, $line_height, 'Name', 0, 0, 'L');
$pdf->Cell(2, $line_height, ':', 0, 0, 'C');
$pdf->Cell($value_width - 2, $line_height, $payslip['name'], 0, 1, 'L');

$pdf->SetX(15);
$pdf->Cell($label_width, $line_height, 'Designation', 0, 0, 'L');
$pdf->Cell(2, $line_height, ':', 0, 0, 'C');
$pdf->Cell($value_width - 2, $line_height, $payslip['designation'], 0, 1, 'L');

$pdf->SetX(15);
$pdf->Cell($label_width, $line_height, 'Pay Slip for', 0, 0, 'L');
$pdf->Cell(2, $line_height, ':', 0, 0, 'C');
$pdf->Cell($value_width - 2, $line_height, date('F Y', mktime(0, 0, 0, $payslip['pay_month'], 1, $payslip['pay_year'])), 0, 1, 'L');

$pdf->SetX(15);
$pdf->Cell($label_width, $line_height, 'Bill Net Pay', 0, 0, 'L');
$pdf->Cell(2, $line_height, ':', 0, 0, 'C');
$pdf->Cell($value_width - 2, $line_height, number_format($net_pay_calculated, 2), 0, 1, 'L');

// Move back to the starting Y position for the right column
$pdf->SetY($y_start); 
$pdf->SetX(15 + $half_width); 

// Column 2 (Right Side)
$pdf->Cell($label_width, $line_height, 'Date', 0, 0, 'L');
$pdf->Cell(2, $line_height, ':', 0, 0, 'C');
$pdf->Cell($value_width - 2, $line_height, date('d-M-Y', strtotime($payslip['created_at'])), 0, 1, 'L');

$pdf->SetX(15 + $half_width);
$pdf->Cell($label_width, $line_height, 'Cheque No', 0, 0, 'L');
$pdf->Cell(2, $line_height, ':', 0, 0, 'C');
$pdf->Cell($value_width - 2, $line_height, $payslip['cheque_no'] ?? 'N/A', 0, 1, 'L');

$pdf->SetX(15 + $half_width);
$pdf->Cell($label_width, $line_height, 'Cheque Date', 0, 0, 'L');
$pdf->Cell(2, $line_height, ':', 0, 0, 'C');
$pdf->Cell($value_width - 2, $line_height, date('d-M-Y', strtotime($payslip['cheque_date'] ?? $payslip['created_at'])), 0, 1, 'L');

$pdf->SetX(15 + $half_width);
$pdf->Cell($label_width, $line_height, 'SB A/C No', 0, 0, 'L');
$pdf->Cell(2, $line_height, ':', 0, 0, 'C');
$pdf->Cell($value_width - 2, $line_height, $payslip['sb_account_no'] ?? 'N/A', 0, 1, 'L');

$pdf->SetX(15 + $half_width);
$pdf->Cell($label_width, $line_height, 'Bank Name', 0, 0, 'L');
$pdf->Cell(2, $line_height, ':', 0, 0, 'C');
$pdf->Cell($value_width - 2, $line_height, $payslip['bank_name'] ?? 'N/A', 0, 1, 'L');

$pdf->Ln(5);


// --- EARNINGS AND DEDUCTIONS TABLE ---

$pdf->SetFont('Arial', 'B', 10);
$table_row_height = 8;
$comp_width = $half_width - 20; 
$amt_width = 20; 

// Draw Table Headers (Pay Description | Amount | Pay Bill Deduction | Amount)
$pdf->SetFillColor(230, 230, 230);
$pdf->SetX(15);
$pdf->Cell($comp_width, $table_row_height, 'Pay Description', 1, 0, 'C', true);
$pdf->Cell($amt_width, $table_row_height, 'Amount', 1, 0, 'C', true);

$pdf->Cell($comp_width, $table_row_height, 'Pay Bill Deduction', 1, 0, 'C', true);
$pdf->Cell($amt_width, $table_row_height, 'Amount', 1, 1, 'C', true);


// Content Rows (Iterate over the maximum number of items)
$pdf->SetFont('Arial', '', 9);
$max_rows = max(count($earnings), count($deductions));

for ($i = 0; $i < $max_rows; $i++) {
    $pdf->SetX(15);
    
    // Earnings Column
    $earning = $earnings[$i] ?? null;
    $comp_name_e = $earning ? $earning['component_name'] : '';
    $amount_e = $earning ? number_format($earning['amount'], 2) : '';
    
    $pdf->Cell($comp_width, $table_row_height, '  ' . $comp_name_e, 1, 0, 'L');
    $pdf->Cell($amt_width, $table_row_height, $amount_e, 1, 0, 'R');

    // Deductions Column
    $deduction = $deductions[$i] ?? null;
    $comp_name_d = $deduction ? $deduction['component_name'] : '';
    $amount_d = $deduction ? number_format($deduction['amount'], 2) : '';

    $pdf->Cell($comp_width, $table_row_height, '  ' . $comp_name_d, 1, 0, 'L');
    $pdf->Cell($amt_width, $table_row_height, $amount_d, 1, 1, 'R');
}


// --- TOTALS ROW (Total Pay / Gross Total & Total Deduction) ---
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(200, 200, 200); 

$pdf->SetX(15);
// Total Pay (Earnings)
$pdf->Cell($comp_width, $table_row_height, 'Total Pay =', 1, 0, 'L', true);
$pdf->Cell($amt_width, $table_row_height, number_format($total_earnings, 2), 1, 0, 'R', true);

// Total Deduction
$pdf->Cell($comp_width, $table_row_height, 'Total Deduction =', 1, 0, 'L', true);
$pdf->Cell($amt_width, $table_row_height, number_format($total_deductions, 2), 1, 1, 'R', true);

$pdf->Ln(10);

// --- NET PAY ---
$pdf->SetFillColor(220, 255, 220); 
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell($page_width, 10, 'NET PAYABLE: INR ' . number_format($net_pay_calculated, 2), 1, 1, 'C', true);


// --- FOOTER/NOTES ---
$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 5, 'This is a computer-generated document and does not require a signature.', 0, 1, 'C');


// --- 4. Output PDF ---
$pdf->Output('I', "payslip_" . $payslip['emp_code'] . "_" . $payslip['pay_year'] . $payslip['pay_month'] . ".pdf");

mysqli_close($conn);
exit;
?>