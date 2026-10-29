<?php
session_start();


if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}


require_once 'config/connection.php'; 

date_default_timezone_set('Asia/Kolkata');
$page_title = "Manage Pay Components"; 


$message = '';
$alert_type = '';

if (isset($_POST['add_component'])) {
    $component_name = mysqli_real_escape_string($conn, $_POST['component_name']);
    $component_type = mysqli_real_escape_string($conn, $_POST['component_type']);
    $is_percentage = (int)$_POST['is_percentage'];
    $percentage_value = mysqli_real_escape_string($conn, $_POST['percentage_value']);
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? NULL);

    
    $check_sql = "SELECT component_id FROM pay_components WHERE component_name = '$component_name'";
    if (mysqli_num_rows(mysqli_query($conn, $check_sql)) > 0) {
        $message = "Error: Component name **$component_name** already exists.";
        $alert_type = "danger";
    } else {
        $insert_sql = "INSERT INTO pay_components (component_name, component_type, is_percentage, percentage_value, description) 
                         VALUES ('$component_name', '$component_type', '$is_percentage', '$percentage_value', '$description')";

        if (mysqli_query($conn, $insert_sql)) {
            $message = "Success: Component **$component_name** added successfully!";
            $alert_type = "success";
            $_SESSION['message'] = $message;
            $_SESSION['alert_type'] = $alert_type;
            header("Location: pay_components.php");
            exit();
        } else {
            $message = "Error: Could not add component. " . mysqli_error($conn);
            $alert_type = "danger";
        }
    }
}


if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $alert_type = $_SESSION['alert_type'];
    unset($_SESSION['message']);
    unset($_SESSION['alert_type']);
}


$sql = "SELECT * FROM pay_components ORDER BY component_type DESC, component_name ASC";
$res = mysqli_query($conn, $sql);

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

                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">Add New Pay Component</h3>
                    </div>
                    <form method="POST" action="pay_components.php">
                        <div class="card-body">
                            <div class="row">
                                <div class="form-group col-md-6">
                                    <label for="component_name">Component Name</label>
                                    <input type="text" name="component_name" class="form-control" required>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="component_type">Type</label>
                                    <select name="component_type" class="form-control" required>
                                        <option value="earning">Earning (+)</option>
                                        <option value="deduction">Deduction (-)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="form-group col-md-4">
                                    <label for="is_percentage">Calculation Type</label>
                                    <select name="is_percentage" id="is_percentage" class="form-control" required>
                                        <option value="0">Fixed Amount</option>
                                        <option value="1">Percentage of Basic Pay</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="percentage_value">Default Value / %</label>
                                    <input type="number" step="0.01" name="percentage_value" id="percentage_value" class="form-control" value="0.00" required>
                                    <small class="form-text text-muted">Enter a fixed amount or the percentage value (e.g., 10 for 10%).</small>
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="description">Description (Optional)</label>
                                    <input type="text" name="description" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="add_component" class="btn btn-primary"><i class="fas fa-plus"></i> Add Component</button>
                        </div>
                    </form>
                </div>

                <div class="card card-secondary">
                    <div class="card-header">
                        <h3 class="card-title">Existing Pay Components</h3>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Calc. Type</th>
                                    <th>Default Value</th>
                                    <th>Description</th>
                                    </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($res)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['component_id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['component_name']); ?></td>
                                    <td><span class="badge bg-<?php echo ($row['component_type'] == 'earning' ? 'success' : 'danger'); ?>"><?php echo ucfirst($row['component_type']); ?></span></td>
                                    <td><?php echo ($row['is_percentage'] == 1 ? 'Percentage' : 'Fixed Amt'); ?></td>
                                    <td><?php echo htmlspecialchars($row['percentage_value']); ?></td>
                                    <td><?php echo htmlspecialchars($row['description'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
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