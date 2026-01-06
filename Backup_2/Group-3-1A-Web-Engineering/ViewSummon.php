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

$staff_id = $_SESSION['user_id'];

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

$summonID = $_GET['id'];

$sql = "SELECT 
            ts.SummonID, 
            ts.SummonDate, 
            ts.SummonTime, 
            ts.SummonDescription, 
            ts.FineAmount, 
            ts.QRCodeID,
            ts.DemeritPointSnapshot,
            ts.EnforcementStatusSnapshot,
            s.StudentID, 
            s.StudentName, 
            s.StudentContact,
            veh.PlateNumber,
            v.ViolationType, 
            v.ViolationPoint as ViolationPoints,
            q.Image_URL
        FROM TrafficSummon ts
        JOIN Student s ON ts.StudentID = s.StudentID
        LEFT JOIN Vehicle veh ON ts.StudentID = veh.StudentID
        JOIN Violation v ON ts.ViolationID = v.ViolationID
        LEFT JOIN QRCode q ON ts.QRCodeID = q.QRCodeID
        WHERE ts.SummonID = '$summonID'
        LIMIT 1";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
} else {
    echo "Summon not found.";
    exit();
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
    <title>FK Park System - View Summon Details</title>
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
        
        /* View Summon Specific Styles */
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
        .qr-section {
            text-align: center;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #eee;
        }
        .qr-image {
            width: 180px;
            height: 180px;
            margin: 15px auto;
            border: 1px solid #ddd;
            padding: 8px;
            border-radius: 4px;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .btn-print {
            background-color: #eb9d43ff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        .btn-print:hover {
            background-color: #6d4e2aff;
            color: white;
            text-decoration: none;
        }
        .fine-amount {
            font-size: 1.3rem;
            color: #dc3545;
            font-weight: bold;
        }
        .demerit-points {
            color: #dc3545;
            font-weight: bold;
        }
        .enforcement-status {
            color: #e67e22;
            font-weight: 500;
        }
        .section-title {
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: #444;
            font-size: 1.2rem;
        }
        .badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 8px;
        }
        .badge-legacy {
            background-color: #6c757d;
            color: white;
        }
        /* Print Styles */
        @media print {
            body * {
                visibility: hidden;
            }
            .main-container, .main-container * {
                visibility: visible;
            }
            .main-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                padding: 20px;
                box-shadow: none;
            }
            .back-link, .btn-print, .sidebar, .header, footer {
                display: none !important;
            }
            .details-container {
                box-shadow: none;
                border: 1px solid #ddd;
            }
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
                <a href="VehicleApproval.php" class="menutext">
                    <i class="fas fa-car"></i> Vehicle Approval
                </a>
            </li>
            <li>
                <a href="TrafficSummon.php" class="menutext active">
                    <i class="fas fa-exclamation-triangle"></i> Traffic Summon
                </a>
            </li>
        </ul>
    </nav>

    <div class="container main-container" id="mainContainer">
        <a href="TrafficSummon.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Summon List
        </a>
        
        <div class="details-container">
            <div class="header-section">
                <div>
                    <h2><i class="fas fa-exclamation-triangle"></i> Traffic Summon Receipt</h2>
                    <span style="color: #666; font-size: 0.9rem;">Summon ID: <?php echo htmlspecialchars($row['SummonID']); ?></span>
                </div>
                <button onclick="window.print()" class="btn-print">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
            </div>

            <div class="info-grid">
                <div>
                    <h3 class="section-title">Student Details</h3>
                    <div class="info-group">
                        <label><i class="fas fa-user"></i> Student Name</label>
                        <div class="value"><?php echo htmlspecialchars($row['StudentName']); ?></div>
                    </div>
                    <div class="info-group">
                        <label><i class="fas fa-id-card"></i> Student ID</label>
                        <div class="value"><?php echo htmlspecialchars($row['StudentID']); ?></div>
                    </div>
                    <div class="info-group">
                        <label><i class="fas fa-phone"></i> Contact Number</label>
                        <div class="value"><?php echo htmlspecialchars($row['StudentContact']); ?></div>
                    </div>
                    <div class="info-group">
                        <label><i class="fas fa-tag"></i> Vehicle Plate No.</label>
                        <div class="value"><?php echo htmlspecialchars($row['PlateNumber'] ? $row['PlateNumber'] : 'N/A'); ?></div>
                    </div>
                    <div class="info-group">
                        <label><i class="fas fa-chart-line"></i> Total Demerit Points (At time of summon)</label>
                        <div class="value">
                            <?php 
                            if ($row['DemeritPointSnapshot'] !== null) {
                                echo htmlspecialchars($row['DemeritPointSnapshot']) . ' Points';
                            } else {
                                echo 'N/A <span class="badge badge-legacy">Legacy Record</span>';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="info-group">
                        <label><i class="fas fa-shield-alt"></i> Enforcement Status (At time of summon)</label>
                        <div class="value enforcement-status">
                            <?php 
                            if ($row['EnforcementStatusSnapshot']) {
                                echo htmlspecialchars($row['EnforcementStatusSnapshot']);
                            } else {
                                echo 'N/A <span class="badge badge-legacy">Legacy Record</span>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="section-title">Violation Details</h3>
                    <div class="info-group">
                        <label><i class="fas fa-exclamation-circle"></i> Violation Type</label>
                        <div class="value"><?php echo htmlspecialchars($row['ViolationType']); ?></div>
                    </div>
                    <div class="info-group">
                        <label><i class="fas fa-minus-circle"></i> Demerit Points for this Violation</label>
                        <div class="value demerit-points">
                            <?php echo htmlspecialchars($row['ViolationPoints']); ?> Points
                        </div>
                    </div>
                    <div class="info-group">
                        <label><i class="fas fa-calendar"></i> Date</label>
                        <div class="value"><?php echo htmlspecialchars($row['SummonDate']); ?></div>
                    </div>
                    <div class="info-group">
                        <label><i class="fas fa-clock"></i> Time</label>
                        <div class="value"><?php echo htmlspecialchars($row['SummonTime']); ?></div>
                    </div>
                    <div class="info-group">
                        <label><i class="fas fa-file-alt"></i> Description</label>
                        <div class="value"><?php echo htmlspecialchars($row['SummonDescription']); ?></div>
                    </div>
                    <div class="info-group">
                        <label><i class="fas fa-money-bill-wave"></i> Fine Amount</label>
                        <div class="value fine-amount">
                            RM <?php echo number_format($row['FineAmount'], 2); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="qr-section">
                <h3 class="section-title"><i class="fas fa-qrcode"></i> Summon QR Code</h3>
                <?php if (!empty($row['Image_URL'])): ?>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?php echo urlencode($row['Image_URL']); ?>" 
                         alt="Summon QR Code" 
                         class="qr-image">
                    <p style="color: #666; font-size: 0.9rem; margin-top: 10px;">Scan this QR code to view summon details</p>
                <?php else: ?>
                    <div style="padding: 30px; color: #999; font-style: italic; background: #f9f9f9; border-radius: 4px;">
                        <i class="fas fa-qrcode fa-3x mb-3"></i><br>
                        No QR code generated for this summon.
                    </div>
                <?php endif; ?>
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