<?php
require_once 'config/connection.php';

// Capture all filters
$emp_code           = $_GET['emp_code'] ?? '';
$search_name        = $_GET['search_name'] ?? '';
$search_designation = $_GET['search_designation'] ?? '';
$from_date          = $_GET['from_date'] ?? '';
$to_date            = $_GET['to_date'] ?? '';
$search_status      = $_GET['search_status'] ?? ''; // Status Filter
$order_by           = $_GET['order_by'] ?? 'name_asc';

// Build WHERE conditions dynamically
$where = [];
if (!empty($emp_code))           $where[] = "emp_code LIKE '%" . mysqli_real_escape_string($conn, $emp_code) . "%'";
if (!empty($search_name))        $where[] = "name LIKE '%" . mysqli_real_escape_string($conn, $search_name) . "%'";
if (!empty($search_designation)) $where[] = "designation LIKE '%" . mysqli_real_escape_string($conn, $search_designation) . "%'";
if (!empty($from_date) && !empty($to_date)) 
                                 $where[] = "doj BETWEEN '" . mysqli_real_escape_string($conn, $from_date) . "' AND '" . mysqli_real_escape_string($conn, $to_date) . "'";
if (!empty($search_status))      $where[] = "status = '" . mysqli_real_escape_string($conn, $search_status) . "'";

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Handle Order By
switch($order_by) {
    case 'name_asc':         $order_sql = "ORDER BY name ASC"; break;
    case 'name_desc':        $order_sql = "ORDER BY name DESC"; break;
    case 'basic_pay_desc':   $order_sql = "ORDER BY basic_pay DESC"; break;
    case 'doj_asc':          $order_sql = "ORDER BY doj ASC"; break;
    case 'doj_desc':         $order_sql = "ORDER BY doj DESC"; break;
    default:                 $order_sql = "ORDER BY name ASC";
}

// Final Query (Removed pagination logic)
$query = "SELECT emp_code, name, designation, doj, basic_pay, sb_account_no, bank_name, branch_name
          FROM employees $where_sql $order_sql";
$result = mysqli_query($conn, $query);

// Headers for Excel Download
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Employee_List.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Output as Excel TSV (tab-separated)
echo "Emp Code\tName\tDesignation\tDate of Joining\tBasic Pay\tSB Account No\tBank Name\tBranch Name\n";

if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {

        // Format date safely
        $doj = '';
        if (!empty($row['doj']) && $row['doj'] !== '0000-00-00') {
            $doj = date('d-m-Y', strtotime($row['doj']));
        }

        // Prevent SB Account number from converting to scientific notation
        $sb_account_no = "=\"" . $row['sb_account_no'] . "\"";

        // Prepare row data
        $output_row = array(
            $row['emp_code'],
            $row['name'],
            $row['designation'],
            $doj,
            number_format($row['basic_pay'], 2),
            $sb_account_no,
            $row['bank_name'],
            $row['branch_name']
        );
        
        // Output row, joined by tabs
        echo implode("\t", $output_row) . "\n";
    }
}
?>