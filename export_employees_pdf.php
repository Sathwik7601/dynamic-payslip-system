<?php
session_start();
require_once 'config/connection.php'; 

// --- Security Check ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die("Access denied.");
}

// Include FPDF Library
require_once('lib/fpdf.php'); 

date_default_timezone_set('Asia/Kolkata');

// --- 1. Column Management & Setup ---
$all_columns = [
    'emp_code' => 'Employee Code',
    'name' => 'Name',
    'designation' => 'Designation',
    'basic_pay' => 'Basic Pay',
    'doj' => 'Date of Joining',
    'status' => 'Status',
    'sb_account_no' => 'Bank Account',
    'bank_name' => 'Bank Name',
    'branch_name' => 'Branch'
];

$default_cols = ['emp_code', 'name', 'designation', 'basic_pay', 'doj', 'status'];

// Get selected columns from GET parameter (columns[] array)
$selected_columns = isset($_GET['columns']) && is_array($_GET['columns']) 
    ? array_intersect($_GET['columns'], array_keys($all_columns))
    : $default_cols;

if (empty($selected_columns)) {
    $selected_columns = $default_cols;
}

// --- 2. Filtering Logic ---
$search_emp_code = mysqli_real_escape_string($conn, $_GET['emp_code'] ?? '');
$search_name = mysqli_real_escape_string($conn, $_GET['search_name'] ?? '');
$search_designation = mysqli_real_escape_string($conn, $_GET['search_designation'] ?? '');
$from_date = mysqli_real_escape_string($conn, $_GET['from_date'] ?? '');
$to_date = mysqli_real_escape_string($conn, $_GET['to_date'] ?? '');
$order_by = $_GET['order_by'] ?? 'name_asc';

$where_sql = "WHERE 1=1";

if (!empty($search_emp_code)) {
    $where_sql .= " AND emp_code LIKE '%$search_emp_code%'";
}
if (!empty($search_name)) {
    $keywords = '%' . $search_name . '%';
    $where_sql .= " AND name LIKE '$keywords'";
}
if (!empty($search_designation)) {
    $where_sql .= " AND designation = '$search_designation'";
}
if (!empty($from_date)) {
    $where_sql .= " AND doj >= '$from_date'";
}
if (!empty($to_date)) {
    $where_sql .= " AND doj <= '$to_date'";
}

// Apply Ordering
$order_sql = " ORDER BY name ASC";
if ($order_by == 'name_asc') { $order_sql = " ORDER BY name ASC"; } 
else if ($order_by == 'name_desc') { $order_sql = " ORDER BY name DESC"; } 
else if ($order_by == 'basic_pay_desc') { $order_sql = " ORDER BY basic_pay DESC"; } 
else if ($order_by == 'doj_asc') { $order_sql = " ORDER BY doj ASC"; } 
else if ($order_by == 'doj_desc') { $order_sql = " ORDER BY doj DESC"; } 

// Build SELECT clause with only selected columns
$select_clause = implode(', ', $selected_columns);
$sql = "SELECT $select_clause FROM employees $where_sql $order_sql";
$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

$total_records = mysqli_num_rows($result);

// --- 3. Start PDF Generation with FPDF ---
// Use Landscape for better table fit
$orientation = 'L'; // Always landscape for employee list
$pdf = new FPDF($orientation, 'mm', 'A4');
$pdf->SetMargins(8, 8, 8); // Reduce margins to fit more content
$pdf->AddPage();

// PDF Title
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 12, 'Employee List', 0, 1, 'C');
$pdf->Ln(3);

// Calculate column widths
// Landscape A4 is 297mm wide, margins are now 8mm on each side
$page_width = 297; // Landscape A4 width
$margin = 8; // Custom margins set above
$available_width = $page_width - ($margin * 2);
$col_width = floor($available_width / count($selected_columns));

// Table Header
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(200, 200, 200);  // Light gray
$pdf->SetTextColor(0, 0, 0);
$pdf->SetLineWidth(0.4);
$pdf->SetDrawColor(0, 0, 0);

foreach ($selected_columns as $col_name) {
    $pdf->Cell($col_width, 8, $all_columns[$col_name], 1, 0, 'C', true);
}
$pdf->Ln();

// --- 4. Output Data Rows ---
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(255, 255, 255);
$fill = false;

if ($total_records > 0) {
    mysqli_data_seek($result, 0); // Reset result pointer
    
    while ($row = mysqli_fetch_assoc($result)) {
        $height = 7;
        
        foreach ($selected_columns as $col_name) {
            $value = $row[$col_name] ?? '';
            $align = 'L';
            
            // Format specific columns
            if ($col_name === 'basic_pay') {
                $value = number_format((float)$value, 2);
                $align = 'R';
            } else if ($col_name === 'doj' && $value) {
                $value = date('d-m-Y', strtotime($value));
                $align = 'C';
            } else if ($col_name === 'status') {
                $align = 'C';
            }
            
            // Add border and background
            $pdf->Cell($col_width, $height, substr($value, 0, 30), 1, 0, $align, $fill);
        }
        
        $pdf->Ln($height);
        $fill = !$fill; // Alternate row colors
    }
} else {
    $pdf->Cell($available_width, 8, 'No employees found matching the filter criteria.', 1, 1, 'C');
}

// Add page number footer
$pdf->SetY(-15);
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 10, 'Page ' . $pdf->PageNo(), 0, 0, 'C');

// --- 5. Output PDF ---
$pdf->Output('D', "employee_list_" . date('YmdHis') . ".pdf");

mysqli_close($conn);
exit;
?>