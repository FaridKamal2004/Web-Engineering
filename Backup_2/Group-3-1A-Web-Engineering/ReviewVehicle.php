<?php
session_start();
$conn = new mysqli("localhost", "root", "", "fkparksystem", 3306);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'SecurityStaff') {
    header("Location: Login.php");
    exit();
}

// Get staff data from database
$staff_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM staff WHERE staffID = ?");
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

$vehicleID = isset($_GET['id']) ? $_GET['id'] : null;
if (!$vehicleID) {
    echo "<script>alert('No vehicle ID provided.'); window.location.href='VehicleApproval.php';</script>";
    exit();
}

$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $status = ($_POST['action'] === 'approve') ? 'Approved' : 'Rejected';
    $remark = mysqli_real_escape_string($conn, $_POST['remark']);

    $updateSql = "UPDATE Vehicle SET VehicleApproval = '$status' WHERE VehicleID = '$vehicleID'";
    if ($conn->query($updateSql) === TRUE) {
        $message = "Vehicle registration has been updated to: " . $status;
    } else { 
        $message = "Error updating record: " . $conn->error;
    }
}

$sql = "SELECT v.*, s.StudentName 
        FROM Vehicle v 
        LEFT JOIN Student s ON v.StudentID = s.StudentID 
        WHERE v.VehicleID = '$vehicleID'";

$result = $conn->query($sql);
$vehicle = $result->fetch_assoc();

if (!$vehicle) {
    echo "Vehicle not found.";
    exit();
}

$imageData = null;
if (!empty($vehicle['VehicleGrant'])) {
    $base64Image = base64_encode($vehicle['VehicleGrant']);
    $imageData = 'data:image/png;base64,' . $base64Image;
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

// Existing security check (keep this if you already have it)
if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FK Park System - Review Vehicle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        /* HEADER - Original Security Dashboard colors */
        .header {
            background-color: #ebaa5fff; 
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            position: fixed;
            width: 100%;
            height: 120px;
            box-sizing: border-box;
            z-index: 1000;
            top: 0;
            left: 0;
        }
        
        /* SIDEBAR - Original Security Dashboard colors */
        .sidebar {
            background-color: #eb9d43ff;
            width: 250px;
            color: white;
            position: fixed;
            top: 120px;
            left: 0;
            bottom: 0;
            padding: 20px 0;
            box-sizing: border-box;
            transition: all 0.3s ease;
            z-index: 999;
        }
        
        /* SIDEBAR - Keep original hover/active colors */
        .menutext:hover {
            background-color: #6d4e2aff;
        }   
        .menutext.active {
            background-color: #6d4e2aff;
            font-weight: 500;
        }
        
        /* BUTTONS - Original Security Dashboard colors */
        .togglebutton {
            background-color: #ebaa5fff;
            color: white;
            border: 1px solid #ebaa5fff;
        }
        .togglebutton:hover {
            background-color: #6d4e2aff;
        }
        .profile {
            background-color: #f19c1dff;
            color: white;
        }
        .profile:hover {
            background-color: #6d4e2aff;
        }
        .logoutbutton {
            background-color: rgba(255, 0, 0, 0.81);
            color: white;
        }
        
        footer {
            background-color: #f7b973ff;
            color: white;
        }
        
        /* STRUCTURAL STYLES */
        .header-left{
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 0 35px;
        }
        .header-right{
            display: flex;
            align-items: center;
            gap: 20px;
            padding-right: 20px;
        }
        .togglebutton {
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(0, 0, 0, 0.3);
        }
        .logo{
            display: flex;
            gap: 20px;
            align-items: center;
            padding: 0 60px;
        }
        .logo img{
            height: 90px;
            width: auto;
        }
        .sidebar.collapsed {
            transform: translateX(-250px);
            opacity: 0;
            width: 0;
            padding: 0;
        }
        .sidebartitle{
            color: white;
            font-size: 1rem;
            margin-bottom: 20px;
            padding: 0 20px;
        }
        .menu{
            display: flex;
            flex-direction: column;
            gap: 18px;
            padding: 0;
            margin: 0;
            list-style: none;
        }
        .menutext{
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            padding: 14px 18px;
            color: black;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .menu a {
            text-decoration: none;
            color: inherit;
        }  
        .profile{
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
            border: 1px solid rgba(0, 0, 0, 0.3);
        }
        .logoutbutton {
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border: 1px solid rgba(0, 0, 0, 0.3);
        }
        .main-container {
            margin-left: 250px;
            margin-top: 120px;
            padding: 40px;
            box-sizing: border-box;
            flex: 1;
            transition: margin-left 0.3s ease;
            width: calc(100% - 250px);
            min-height: calc(100vh - 120px);
        }
        .main-container.sidebar-collapsed {
            margin-left: 0;
            width: 100%;
        }
        footer {
            padding: 15px 0;
            text-align: center;
            width: 100%;
            margin-top: auto;
            position: relative;
            bottom: 0;
            left: 0;
            transition: margin-left 0.3s ease;
        }
        footer.sidebar-collapsed {
            margin-left: 0;
        }
        
        /* Review Vehicle Specific Styles */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 25px;
            color: #666;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 15px;
            background: white;
            border-radius: 4px;
            border: 1px solid #ddd;
            transition: all 0.2s;
        }
        .back-link:hover {
            color: #333;
            background: #f8f9fa;
            text-decoration: none;
            border-color: #bbb;
        }
        .details-container {
            background-color: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            max-width: 900px;
            margin: 0 auto;
            position: relative;
        }
        .header-section {
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header-section h2 {
            margin: 0;
            color: #333;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
        .info-group {
            margin-bottom: 20px;
        }
        .info-group label {
            display: block;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .info-group .value {
            font-size: 1.1rem;
            font-weight: 500;
            color: #333;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #eee;
        }
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
            display: inline-block;
        }
        .Pending { 
            background-color: #fff3cd; 
            color: #856404; 
            border: 1px solid #ffeaa7;
        }
        .Approved { 
            background-color: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb;
        }
        .Rejected { 
            background-color: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb;
        }
        .grant-section {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #eee;
            border-radius: 8px;
            background: #f8f9fa;
        }
        .grant-photo {
            max-width: 100%;
            max-height: 400px;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
            margin-top: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            background: white;
        }
        .no-photo {
            padding: 40px 20px;
            color: #6c757d;
            font-style: italic;
            background: white;
            border-radius: 4px;
            border: 1px dashed #ddd;
            text-align: center;
        }
        .action-container {
            margin-top: 30px;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .action-container label {
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
            display: block;
            font-size: 1.1rem;
        }
        .action-container textarea {
            width: 100%;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            box-sizing: border-box;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
            font-size: 1rem;
        }
        .btn-group {
            display: flex;
            gap: 15px;
        }
        @media (max-width: 576px) {
            .btn-group {
                flex-direction: column;
            }
        }
        .btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            color: white;
            text-transform: uppercase;
            font-size: 0.9rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn:hover {
            opacity: 0.9; 
            transform: translateY(-1px);
        }
        .btn-approve {
            background-color: #28a745;
        }
        .btn-reject {
            background-color: #dc3545;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 4px;
            margin-bottom: 25px;
            text-align: center;
            border: 1px solid transparent;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        .section-title {
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: #444;
            font-size: 1.2rem;
        }
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
            <a href="SecurityStaffProfile.php" class="profile">
                <i class="fas fa-user-circle"></i> My Profile
            </a>
            <a href="logout.php" class="logoutbutton" onclick="return confirm('Are you sure you want to log out?');">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>
    
    <nav class="sidebar" id="sidebar">
        <h1 class="sidebartitle"><strong>Security Staff</strong></h1>
        <ul class="menu">
            <li>
                <a href="SecurityStaffDashboard.php" class="menutext">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="VehicleApproval.php" class="menutext active">
                    <i class="fas fa-car"></i> Vehicle Approval
                </a>
            </li>
            <li>
                <a href="TrafficSummon.php" class="menutext">
                    <i class="fas fa-exclamation-triangle"></i> Traffic Summon
                </a>
            </li>
        </ul>
    </nav>

    <div class="container main-container" id="mainContainer">
        <a href="VehicleApproval.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Vehicle List
        </a>

        <div class="details-container">
            <?php if ($message != ""): ?>
                <div class="alert <?php echo strpos($message, 'Error') === false ? 'alert-success' : 'alert-error'; ?>">
                    <i class="fas <?php echo strpos($message, 'Error') === false ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="header-section">
                <div>
                    <h2><i class="fas fa-car"></i> Vehicle Registration Details</h2>
                    <span style="color: #666; font-size: 0.9rem;">Reference ID: <?php echo htmlspecialchars($vehicle['VehicleID']); ?></span>
                </div>
                <span class="status-badge <?php echo htmlspecialchars($vehicle['VehicleApproval']); ?>">
                    <?php echo htmlspecialchars($vehicle['VehicleApproval']); ?>
                </span>
            </div>

            <div class="info-grid">
                <div>
                    <h3 class="section-title">Applicant Details</h3>
                    <div class="info-group">
                        <label><i class="fas fa-user"></i> Student Name</label>
                        <div class="value"><?php echo htmlspecialchars($vehicle['StudentName'] ? $vehicle['StudentName'] : 'N/A'); ?></div>
                    </div>
                    <div class="info-group">
                        <label><i class="fas fa-id-card"></i> Student ID</label>
                        <div class="value"><?php echo htmlspecialchars($vehicle['StudentID']); ?></div>
                    </div>
                </div>

                <div>
                    <h3 class="section-title">Vehicle Details</h3>
                    <div class="info-group">
                        <label><i class="fas fa-tag"></i> Plate Number</label>
                        <div class="value"><?php echo htmlspecialchars($vehicle['PlateNumber']); ?></div>
                    </div>
                    <div class="info-group">
                        <label><i class="fas fa-car-side"></i> Vehicle Type</label>
                        <div class="value"><?php echo htmlspecialchars($vehicle['VehicleType']); ?></div>
                    </div>
                    <div class="info-group">
                        <label><i class="fas fa-palette"></i> Model & Color</label>
                        <div class="value"><?php echo htmlspecialchars($vehicle['VehicleModel']) . " (" . htmlspecialchars($vehicle['VehicleColour']) . ")"; ?></div>
                    </div>
                </div>
            </div>

            <div class="grant-section">
                <h3 class="section-title"><i class="fas fa-file-image"></i> Vehicle Grant Document</h3>
                <?php if ($imageData): ?>
                    <img src="<?php echo $imageData; ?>" alt="Vehicle Grant" class="grant-photo">
                    <p style="text-align: center; margin-top: 10px; color: #666; font-size: 0.9rem;">Uploaded vehicle grant document</p>
                <?php else: ?>
                    <div class="no-photo">
                        <i class="fas fa-file-image fa-3x mb-3"></i><br>
                        No document image found in database.
                    </div>
                <?php endif; ?>
            </div>

            <div class="action-container">
                <form action="ReviewVehicle.php?id=<?php echo $vehicleID; ?>" method="POST">
                    <label for="remark"><i class="fas fa-pen"></i> Review Notes / Reason</label>
                    <textarea name="remark" id="remark" placeholder="Enter reason for approval or rejection..."><?php echo isset($_POST['remark']) ? htmlspecialchars($_POST['remark']) : ''; ?></textarea>
                    
                    <div class="btn-group">
                        <button type="submit" name="action" value="approve" class="btn btn-approve" onclick="return confirm('Are you sure you want to APPROVE this vehicle registration?');">
                            <i class="fas fa-check"></i> Approve Registration
                        </button>
                        <button type="submit" name="action" value="reject" class="btn btn-reject" onclick="return confirm('Are you sure you want to REJECT this vehicle registration?');">
                            <i class="fas fa-times"></i> Reject Registration
                        </button>
                    </div>
                </form>
            </div>
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
        // Sidebar Toggle 
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
        });
    </script>
</body>
</html>