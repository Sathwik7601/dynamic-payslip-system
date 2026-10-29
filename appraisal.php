<?php
session_start();

// --- SECURITY CHECK: REDIRECT IF NOT LOGGED IN ---
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// --- Database Connection ---
require_once 'config/connection.php';

date_default_timezone_set('Asia/Kolkata');
$page_title = "Appraisal History";
$message = '';
$alert_type = '';

// Load session messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $alert_type = $_SESSION['alert_type'];
    unset($_SESSION['message']);
    unset($_SESSION['alert_type']);
}

// --- APPRAISAL OPERATIONS ---

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
    header("Location: appraisal.php");
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
    header("Location: appraisal.php");
    exit;
}

// Update Appraisal
if (isset($_POST['update_appraisal'])) {
    header('Content-Type: application/json');
    try {
        $appraisal_id = intval($_POST['appraisal_id']);
        $increment_percentage = floatval($_POST['increment_percentage']);
        
        // Get current appraisal to get emp_code and previous_basic
        $appraisal_sql = "SELECT emp_code, previous_basic FROM appraisals WHERE id = $appraisal_id";
        $appraisal_res = mysqli_query($conn, $appraisal_sql);
        if (!$appraisal_res || mysqli_num_rows($appraisal_res) == 0) {
            throw new Exception("Appraisal not found");
        }
        
        $appraisal = mysqli_fetch_assoc($appraisal_res);
        $emp_code = $appraisal['emp_code'];
        $previous_basic = $appraisal['previous_basic'];
        $new_basic = $previous_basic * (1 + $increment_percentage / 100);
        
        // Update appraisal record
        $update_sql = "UPDATE appraisals SET increment_percentage = $increment_percentage, new_basic = $new_basic WHERE id = $appraisal_id";
        if (!mysqli_query($conn, $update_sql)) {
            throw new Exception(mysqli_error($conn));
        }
        
        // Update employee's basic pay
        $emp_update_sql = "UPDATE employees SET basic_pay = $new_basic WHERE emp_code = '$emp_code'";
        if (!mysqli_query($conn, $emp_update_sql)) {
            throw new Exception(mysqli_error($conn));
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Appraisal updated successfully']);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    mysqli_close($conn);
    exit;
}

// AJAX: Fetch appraisal data
if (isset($_GET['action']) && $_GET['action'] == 'fetch_appraisal' && isset($_GET['appraisal_id'])) {
    header('Content-Type: application/json');
    $appraisal_id = intval($_GET['appraisal_id']);
    $sql = "SELECT a.id, a.emp_code, a.previous_basic, a.increment_percentage, a.new_basic, e.name 
            FROM appraisals a 
            JOIN employees e ON a.emp_code = e.emp_code COLLATE utf8mb4_unicode_ci
            WHERE a.id = $appraisal_id";
    $res = mysqli_query($conn, $sql);
    
    if ($res && $appraisal = mysqli_fetch_assoc($res)) {
        echo json_encode(['status' => 'success', 'appraisal' => $appraisal]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Appraisal record not found.']);
    }
    mysqli_close($conn);
    exit;
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
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">

    <!-- Header/Navbar -->
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

    <!-- Sidebar -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="index.php" class="brand-link">
            <span class="brand-text font-weight-light">Pay Slip</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item"><a href="index.php" class="nav-link"><i class="nav-icon fas fa-users"></i><p>Manage Employees</p></a></li>
                    <li class="nav-item"><a href="payslip_list.php" class="nav-link"><i class="nav-icon fas fa-receipt"></i><p>Payslips</p></a></li>
                    <li class="nav-item"><a href="appraisal.php" class="nav-link active"><i class="nav-icon fas fa-chart-line"></i><p>Appraisal History</p></a></li>
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
                        <button type="button" class="btn btn-info" data-toggle="modal" data-target="#newAppraisalModal">
                            <i class="fas fa-plus"></i> New Appraisal
                        </button>
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

                <!-- New Appraisal Modal -->
                <div class="modal fade" id="newAppraisalModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" id="appraisalForm">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title">New Appraisal</h5>
                                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label for="appraisal_emp_code">Select Employee *</label>
                                        <select class="form-control" id="appraisal_emp_code" name="emp_code" required>
                                            <option value="">-- Select Employee --</option>
                                            <?php
                                            $emp_sql = "SELECT emp_code, name, COALESCE(basic_pay, 0) as basic_pay FROM employees ORDER BY name ASC";
                                            $emp_result = mysqli_query($conn, $emp_sql);
                                            if ($emp_result) {
                                                while ($emp = mysqli_fetch_assoc($emp_result)) {
                                                    $basic = number_format($emp['basic_pay'], 2);
                                                    echo "<option value='" . htmlspecialchars($emp['emp_code']) . "' data-basic='" . $emp['basic_pay'] . "'>";
                                                    echo htmlspecialchars($emp['name']) . " (₹$basic)";
                                                    echo "</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="increment_percentage">Increment Percentage *</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0" class="form-control" id="increment_percentage" name="increment_percentage" value="3.00" required>
                                            <div class="input-group-append">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>New Basic Pay (Preview)</label>
                                        <input type="text" class="form-control font-weight-bold text-success" id="new_basic_preview" readonly value="₹0.00">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                    <button type="submit" name="apply_appraisal" class="btn btn-primary">
                                        <i class="fas fa-check"></i> Apply Appraisal
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Appraisal History Table -->
                    <div class="card-body">
                        <div class="table-responsive rounded overflow-hidden">
                            <table class="table table-hover">
                                <thead class="bg-dark">
                                    <tr>
                                        <th>Employee Code</th>
                                        <th>Employee</th>
                                        <th>Previous Basic</th>
                                        <th>Increment %</th>
                                        <th>New Basic</th>
                                        <th>Appraisal Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $appraisal_sql = "SELECT a.id, a.emp_code, a.previous_basic, a.increment_percentage, a.new_basic, a.appraisal_date, e.name 
                                                    FROM appraisals a 
                                                    JOIN employees e ON a.emp_code = e.emp_code COLLATE utf8mb4_unicode_ci
                                                    ORDER BY a.appraisal_date DESC";
                                    $appraisal_result = mysqli_query($conn, $appraisal_sql);
                                    if ($appraisal_result && mysqli_num_rows($appraisal_result) > 0) {
                                        while ($row = mysqli_fetch_assoc($appraisal_result)) {
                                            echo "<tr>";
                                            echo "<td>" . htmlspecialchars($row['emp_code']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                                            echo "<td>₹" . number_format($row['previous_basic'], 2) . "</td>";
                                            echo "<td>" . number_format($row['increment_percentage'], 2) . "%</td>";
                                            echo "<td>₹" . number_format($row['new_basic'], 2) . "</td>";
                                            echo "<td>" . date('d-m-Y', strtotime($row['appraisal_date'])) . "</td>";
                                            echo "<td>";
                                            echo "<button class='btn btn-sm btn-warning editAppraisalBtn mr-1' data-appraisal-id='" . htmlspecialchars($row['id']) . "'>";
                                            echo "<i class='fas fa-edit'></i> Edit";
                                            echo "</button>";
                                            echo "<form method='POST' style='display:inline;' onsubmit='return confirm(\"Are you sure you want to delete this appraisal record?\");'>";
                                            echo "<input type='hidden' name='appraisal_id' value='" . htmlspecialchars($row['id']) . "'>";
                                            echo "<button type='submit' name='delete_appraisal' class='btn btn-sm btn-danger'>";
                                            echo "<i class='fas fa-trash'></i> Delete";
                                            echo "</button>";
                                            echo "</form>";
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='7' class='text-center text-muted'>No appraisal records found</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
<!-- Edit Appraisal Modal -->
<div class="modal fade" id="editAppraisalModal" tabindex="-1" role="dialog" aria-labelledby="editAppraisalModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md" role="document">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title" id="editAppraisalModalLabel">Edit Appraisal</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <!-- Content will be loaded via AJAX -->
        <div class="text-center">
          <div class="spinner-border text-warning"></div>
          <p>Loading appraisal details...</p>
        </div>
      </div>
    </div>
  </div>
</div>

            </div>
        </div>
    </div>

    <footer class="main-footer">
        <strong>Copyright &copy; 2025 Pay Slip Project.</strong> All rights reserved.
    </footer>
</div>

<!-- REQUIRED SCRIPTS -->
<script src="adminlte/plugins/jquery/jquery.min.js"></script>
<script src="adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="adminlte/dist/js/adminlte.min.js"></script>

<script>
$(document).ready(function() {

    // Calculate new basic pay on increment change
    $('#increment_percentage').on('keyup change', function() {
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

        modalBody.html(`
            <div class="text-center">
                <div class="spinner-border text-warning"></div>
                <p>Loading appraisal details...</p>
            </div>
        `);
        $('#editAppraisalModal').modal('show');

        $.get('appraisal.php', { action: 'fetch_appraisal', appraisal_id: appraisalId }, function(data) {
            if (data.status === 'success') {
                const appraisal = data.appraisal;
                const previousBasic = parseFloat(appraisal.previous_basic);
                const formHtml = `
                    <form method="POST" id="updateAppraisalForm">
                        <input type="hidden" name="appraisal_id" value="${appraisal.id}">
                        <div class="form-group">
                            <label>Employee</label>
                            <input type="text" class="form-control" value="${appraisal.emp_code} - ${appraisal.name}" readonly>
                        </div>
                        <div class="form-group">
                            <label>Previous Basic Pay</label>
                            <input type="text" class="form-control" value="₹${previousBasic.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2})}" readonly>
                        </div>
                        <div class="form-group">
                            <label>Increment Percentage *</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" name="increment_percentage" class="form-control edit-increment" value="${appraisal.increment_percentage}" required data-prev-basic="${previousBasic}">
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

                // Recalculate on increment change - NOW WORKING CORRECTLY
                const incrementInput = modalBody.find('.edit-increment');
                const newBasicInput = modalBody.find('.edit-new-basic');
                
                incrementInput.on('keyup change', function() {
                    const prevBasic = parseFloat($(this).attr('data-prev-basic'));
                    const increment = parseFloat($(this).val()) || 0;
                    const newBasic = prevBasic * (1 + increment / 100);
                    newBasicInput.val('₹' + newBasic.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
                });

                // Handle form submission
                modalBody.find('#updateAppraisalForm').on('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    formData.append('update_appraisal', '1');

                    $.ajax({
                        url: 'appraisal.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                alert('Appraisal updated successfully!');
                                location.reload();
                            } else {
                                alert('Error: ' + response.message);
                            }
                        },
                        error: function() {
                            alert('Failed to update appraisal');
                        }
                    });
                });

            } else {
                modalBody.html(`<p class="alert alert-danger">Error: ${data.message}</p>`);
            }
        }, 'json').fail(function() {
            modalBody.html('<p class="alert alert-danger">Failed to load appraisal details.</p>');
        });
    });

});
</script>

</body>
</html>
<?php
mysqli_close($conn);
?>
