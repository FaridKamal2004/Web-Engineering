<?php
// Start the session
session_start();

// Database connection parameters
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3306);

// Check if database connection failed
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$areaId = $_GET['area_id'] ?? '';

if (empty($areaId)) {
    echo '<div class="alert alert-danger">Error: Area ID is required</div>';
    exit;
}

// Get staff data from database
$staff_id = $_SESSION['user_id'];
$query = "SELECT * FROM staff WHERE staffID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // Staff not found in database
    session_destroy();
    header("Location: Login.php");
    exit();
}
$staff = $result->fetch_assoc();

// Get area details
$stmt = $conn->prepare("SELECT * FROM ParkingArea WHERE ParkingAreaID = ?");
$stmt->bind_param("s", $areaId);
$stmt->execute();
$result = $stmt->get_result();
$area = $result->fetch_assoc();

if (!$area) {
    echo '<div class="alert alert-danger">Error: Parking area not found</div>';
    exit;
}

// Get all spaces for this area
$stmt = $conn->prepare("
    SELECT ps.*, pst.SpaceStatus, pst.DateStatus
    FROM ParkingSpace ps
    LEFT JOIN ParkingStatus pst ON ps.ParkingSpaceID = pst.ParkingSpaceID
    WHERE ps.ParkingAreaID = ?
    ORDER BY CAST(SUBSTRING_INDEX(ps.SpaceNumber, '-', -1) AS UNSIGNED)
");
$stmt->bind_param("s", $areaId);
$stmt->execute();
$result = $stmt->get_result();
$spaces = $result->fetch_all(MYSQLI_ASSOC);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $spaceId = $_POST['space_id'];
        $newStatus = $_POST['new_status'];
        
        $stmt = $conn->prepare("
            UPDATE ParkingStatus 
            SET SpaceStatus = ?, DateStatus = CURDATE()
            WHERE ParkingSpaceID = ?
        ");
        $stmt->bind_param("ss", $newStatus, $spaceId);
        $stmt->execute();
        
        // Refresh the page
        header("Location: ParkingSpaces.php?area_id=" . $areaId);
        exit;
    }
    
    // Handle space type update
    if (isset($_POST['update_type'])) {
        $spaceId = $_POST['space_id'];
        $newType = $_POST['new_type'];
        
        $stmt = $conn->prepare("
            UPDATE ParkingSpace 
            SET SpaceType = ?
            WHERE ParkingSpaceID = ?
        ");
        $stmt->bind_param("ss", $newType, $spaceId);
        
        if ($stmt->execute()) {
            // Success message can be shown here
            $_SESSION['message'] = "Space type updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update space type.";
        }
        
        // Refresh the page
        header("Location: ParkingSpaces.php?area_id=" . $areaId);
        exit;
    }
}

// 60 seconds inactivity timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 60) {
    session_unset();
    session_destroy();
    header("Location: Login.php");
    exit();
}
// Update activity time on every request
$_SESSION['last_activity'] = time();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FK Park System - Parking Spaces - Area <?php echo htmlspecialchars($area['AreaNumber'] ?? ''); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f5f5f5; font-family: 'Roboto', sans-serif; margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; }
        .header { background-color: #d373d3ff; display: flex; justify-content: space-between; align-items: center; padding: 0 20px; position: fixed; width: 100%; height: 120px; box-sizing: border-box; z-index: 1000; top: 0; left: 0; }
        .header-left { display: flex; align-items: center; gap: 20px; padding: 0 35px; }
        .header-right { display: flex; align-items: center; gap: 20px; padding-right: 20px; }
        .logo { display: flex; gap: 20px; align-items: center; padding: 0 60px; }
        .logo img { height: 90px; width: auto; }
        .sidebar { background-color: #c277c2ff; width: 250px; color: black; position: fixed; top: 120px; left: 0; bottom: 0; padding: 20px 0; box-sizing: border-box; transition: all 0.3s ease; z-index: 999; }
        .sidebar.collapsed { transform: translateX(-250px); opacity: 0; width: 0; padding: 0; }
        .sidebartitle { color: white; font-size: 1rem; margin-bottom: 20px; padding: 0 20px; }
        .togglebutton { background-color: #daa5dad7; color: white; border: 1px solid #d890d89c; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; }
        .togglebutton:hover { background-color: #864281ff; }
        .main-container.sidebar-collapsed { margin-left: 0; transition: margin-left 0.3s ease; }
        .menu { display: flex; flex-direction: column; gap: 18px; padding: 0; margin: 0; list-style: none; }
        .menutext { background-color: rgba(255, 255, 255, 0.1); border-radius: 6px; padding: 14px 18px; color: black; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 20px; }
        .menu a { text-decoration: none; color: white; }
        .menutext:hover { background-color: #6a22bdff; color: white;}
        .menutext.active { background-color: #6a22bdff; font-weight: 500; color: white;}
        .profile { background-color: #7405f1ff; color: white; border: 1px solid rgba(0, 0, 0, 0.3); padding: 8px 15px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; transition: all 0.2s; text-decoration: none; }
        .profile:hover { background-color: #2e0c55ff; }
        .logoutbutton { background-color: rgba(255, 0, 0, 0.81); color: white; border: 1px solid rgba(0, 0, 0, 0.3); padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; text-decoration: none; }
        .main-container { margin-left: 250px; margin-top: 120px; padding: 40px; box-sizing: border-box; flex: 1; transition: margin-left 0.3s ease; width: calc(100% - 250px); }
        .main-container.sidebar-collapsed { margin-left: 0; width: 100%; }
        .form-container { background-color: white; padding: 25px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        .btn-add { background: #0066cc; padding: 8px 12px; color: white; text-decoration: none; border-radius: 4px; }
        .action-links a { margin-right: 10px; text-decoration: none; color: #0066cc; }
        .sidebar-toggle { background: none; border: none; color: #6a22bdff; font-size: 24px; cursor: pointer; padding: 10px; border-radius: 4px; transition: all 0.3s ease; }
        footer { background-color: #b8a6ccff; color: white; padding: 15px 0; text-align: center; width: 100%; margin-top: auto; position: relative; bottom: 0; left: 0; transition: margin-left 0.3s ease; }
        footer.sidebar-collapsed { margin-left: 0; }
        .area-card { border: 1px solid #dee2e6; border-radius: 8px; transition: box-shadow 0.3s ease; }
        .area-card:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0,0,0,0.2); }
        .btn-edit { background-color: #ffc107; color: #212529; border: 1px solid #ffc107; }
        .btn-edit:hover { background: #e0a800; border-color: #e0a800; color: #000; }
        .space-card { border-left: 4px solid; transition: all 0.3s; margin-bottom: 15px; }
        .space-card:hover { transform: translateY(-3px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .back-btn { background: #6c757d; color: white; border: none; padding: 8px 15px; border-radius: 5px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
        .back-btn:hover { background: #5a6268; color: white; }
        .hierarchy-indicator { background: white; border-radius: 10px; padding: 15px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .space-card .form-select { flex: 1; }
        .space-card .btn { white-space: nowrap; }
        .qr-code-section { border-top: 1px solid #dee2e6; padding-top: 15px; margin-top: 15px; }
        .space-type-badge { background-color: #6f42c1; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.8rem; }
        .update-form { margin-bottom: 10px; padding: 10px 0; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-left">
            <button class="togglebutton" id="sidebarToggle">
                <i class="fas fa-bars"></i>Menu
            </button>
            <div class="logo">
                <img src="UMPLogo.png" alt="UMPLogo">
            </div>
        </div>
        <div class="header-right">
                <span style="color:white; font-weight:500;">
                    Welcome, <?php echo htmlspecialchars($staff['StaffName']); ?>
                </span>
            <a href="AdminProfile.php" class="profile">
                <i class="fas fa-user-circle"></i> My Profile
            </a>
            <a href="logout.php" class="logoutbutton" onclick="return confirm('Are you sure you want to log out?');">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>
    <nav class="sidebar" id="sidebar">
        <h1 class="sidebartitle">Admin Bar</h1>
        <ul class="menu">
            <li>
                <a href="AdminDashboard.php" class="menutext">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="ManageUser.php" class="menutext">
                    <i class="fas fa-users"></i> Manage User
                </a>
            </li>
            <li>
                <a href="ParkingManagement.php" class="menutext active">
                    <i class="fas fa-parking"></i> Parking Management
                </a>
            </li>
            <li>
                <a href="Report.php" class="menutext">
                    <i class="fas fa-chart-bar"></i> Report
                </a>
            </li>
        </ul>
    </nav>
    <div class="container main-container" id="mainContainer">
        <!-- Hierarchy Navigation -->
        <div class="hierarchy-indicator">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <a href="ParkingArea.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Back to Parking Areas
                    </a>
                    <h4 class="mt-3 mb-0">
                        <i class="fas fa-map-marked-alt"></i> 
                        Parking Spaces - Area <?php echo htmlspecialchars($area['AreaNumber'] ?? ''); ?>
                        <small class="text-muted">(<?php echo htmlspecialchars($area['AreaType'] ?? ''); ?>)</small>
                    </h4>
                    <small class="text-muted">
                        Area ID: <?php echo $area['ParkingAreaID'] ?? ''; ?> | 
                        Total Capacity: <?php echo $area['TotalSpaces'] ?? 0; ?> spaces
                    </small>
                </div>
                <div>
                    <a href="ParkingArea.php" class="btn btn-primary">
                        <i class="fas fa-parking"></i> Parking Areas
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Display messages -->
        <?php if(isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <!-- Area Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo count($spaces); ?></h5>
                        <p class="card-text">Total Spaces</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php 
                            $available = 0;
                            foreach($spaces as $space) {
                                if($space['SpaceStatus'] == 'Available') $available++;
                            }
                            echo $available;
                            ?>
                        </h5>
                        <p class="card-text text-success">Available</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php 
                            $occupied = 0;
                            foreach($spaces as $space) {
                                if($space['SpaceStatus'] == 'Occupied') $occupied++;
                            }
                            echo $occupied;
                            ?>
                        </h5>
                        <p class="card-text text-danger">Occupied</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php 
                            $maintenance = 0;
                            foreach($spaces as $space) {
                                if($space['SpaceStatus'] == 'Maintenance') $maintenance++;
                            }
                            echo $maintenance;
                            ?>
                        </h5>
                        <p class="card-text text-warning">Maintenance</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Spaces Grid -->
        <div class="row">
            <?php if(empty($spaces)): ?>
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle fa-2x mb-2"></i>
                    <h5>No Spaces Found</h5>
                    <p>This parking area doesn't have any spaces yet.</p>
                </div>
            </div>
            <?php else: ?>
                <?php foreach($spaces as $space): 
                    $qrUrl = "http://localhost/FKParkSystem/ParkingSpaceInfo.php?space_id=" . $space['ParkingSpaceID'];
                    $borderColor = $space['SpaceStatus'] == 'Available' ? '#28a745' : 
                                  ($space['SpaceStatus'] == 'Occupied' ? '#dc3545' : '#ffc107');
                    $statusColor = $space['SpaceStatus'] == 'Available' ? 'success' : 
                                  ($space['SpaceStatus'] == 'Occupied' ? 'danger' : 'warning');
                ?>
                <div class="col-md-4 mb-3">
                    <div class="card space-card" style="border-left-color: <?php echo $borderColor; ?>;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title mb-1">
                                        <i class="fas fa-parking"></i> 
                                        <?php echo htmlspecialchars($space['SpaceNumber']); ?>
                                    </h5>
                                    <p class="card-text">
                                        <small class="text-muted">
                                            <span class="space-type-badge">
                                                <?php echo htmlspecialchars($space['SpaceType']); ?>
                                            </span>
                                            <br>
                                            ID: <?php echo $space['ParkingSpaceID']; ?>
                                        </small>
                                    </p>
                                </div>
                                <span class="badge bg-<?php echo $statusColor; ?>">
                                    <?php echo $space['SpaceStatus']; ?>
                                </span>
                            </div>
                            
                            <!-- Space Type Update Form -->
                            <form method="POST" action="" class="update-form">
                                <input type="hidden" name="space_id" value="<?php echo $space['ParkingSpaceID']; ?>">
                                <div class="d-flex align-items-center gap-2">
                                    <select name="new_type" class="form-select form-select-sm">
                                        <option value="Car" <?php echo $space['SpaceType'] == 'Car' ? 'selected' : ''; ?>>
                                            Car
                                        </option>
                                        <option value="Motorcycle" <?php echo $space['SpaceType'] == 'Motorcycle' ? 'selected' : ''; ?>>
                                            Motorcycle
                                        </option>
                                    </select>
                                    <button type="submit" name="update_type" class="btn btn-sm btn-info flex-shrink-0">
                                        <i class="fas fa-edit"></i> Update Type
                                    </button>
                                </div>
                            </form>
                            
                            <!-- Status Update Form -->
                            <form method="POST" action="" class="update-form">
                                <input type="hidden" name="space_id" value="<?php echo $space['ParkingSpaceID']; ?>">
                                <div class="d-flex align-items-center gap-2">
                                    <select name="new_status" class="form-select form-select-sm">
                                        <option value="Available" <?php echo $space['SpaceStatus'] == 'Available' ? 'selected' : ''; ?>>
                                            Available
                                        </option>
                                        <option value="Occupied" <?php echo $space['SpaceStatus'] == 'Occupied' ? 'selected' : ''; ?>>
                                            Occupied
                                        </option>
                                        <option value="Maintenance" <?php echo $space['SpaceStatus'] == 'Maintenance' ? 'selected' : ''; ?>>
                                            Maintenance
                                        </option>
                                    </select>
                                    <button type="submit" name="update_status" class="btn btn-sm btn-primary flex-shrink-0">
                                        <i class="fas fa-sync-alt"></i> Update Status
                                    </button>
                                </div>
                            </form>
                            
                            <!-- QR Code Section -->
                            <div class="qr-code-section text-center">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?php echo urlencode($qrUrl); ?>" 
                                     alt="QR Code for <?php echo htmlspecialchars($space['SpaceNumber']); ?>"
                                     class="img-fluid">
                                <small class="text-muted d-block mt-2">
                                    <i class="fas fa-qrcode"></i> Scan for parking space info
                                </small>
                            </div>
                            
                            <?php if(!empty($space['DateStatus'])): ?>
                                <small class="text-muted mt-3 d-block">
                                    <i class="far fa-calendar-alt"></i> 
                                    Status since: <?php echo date('d/m/Y', strtotime($space['DateStatus'])); ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <footer id="footer">
        <div class="container">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> FK Park System. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
            let timeout = 60;        
    let warningTime = 10; // show warning 10s before timeout
    let countdown;

    function startTimer() {
        clearTimeout(countdown);
    
    countdown = setTimeout(() => {
        let stay = confirm(
            "Your session will expire soon.\n\nClick OK to continue or Cancel to logout."
        );
        
        if (stay) {
            // Ping server to refresh session
            fetch("keep_alive.php")
            .then(() => {
                startTimer(); // restart timer
            });
        } else {
            window.location.href = "Logout.php";
        }
    }, (timeout - warningTime) * 1000);
}
// Restart timer on user activity
["click", "mousemove", "keypress"].forEach(event => {
    document.addEventListener(event, startTimer);
});
// Start timer on page load
startTimer();

        // Sidebar Toggle Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContainer = document.getElementById('mainContainer');
            const footer = document.getElementById('footer');
            
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    // Toggle collapsed class
                    sidebar.classList.toggle('collapsed');
                    mainContainer.classList.toggle('sidebar-collapsed');
                    footer.classList.toggle('sidebar-collapsed');
                    
                    // Save state to localStorage
                    const isCollapsed = sidebar.classList.contains('collapsed');
                    localStorage.setItem('sidebarCollapsed', isCollapsed);
                });
                
                // Load saved state
                const savedState = localStorage.getItem('sidebarCollapsed');
                if (savedState === 'true') {
                    sidebar.classList.add('collapsed');
                    mainContainer.classList.add('sidebar-collapsed');
                    footer.classList.add('sidebar-collapsed');
                }
            }
            
            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
</body>
</html>