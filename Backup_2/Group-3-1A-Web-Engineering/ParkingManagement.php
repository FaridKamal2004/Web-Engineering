<?php
// Start the session
session_start();

// Database connection parameters
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3306);

// Check if database connection failed
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to only logged-in staff
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'Administrator') {
    header("Location: Login.php");
    exit();
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

// 60 seconds inactivity timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 60) {
    session_unset();
    session_destroy();
    header("Location: Login.php");
    exit();
}
// Update activity time on every request
$_SESSION['last_activity'] = time();

// Existing security check
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
    <title>FK Park System - Parking Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
 body { background-color: #f5f5f5; font-family: 'Roboto', sans-serif; margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; }
.header { background-color: #d373d3ff; display: flex; justify-content: space-between; align-items: center; padding: 0 20px; position: fixed; width: 100%; height: 120px; box-sizing: border-box; z-index: 1000; top: 0; left: 0; }
.header-left { display: flex; align-items: center; gap: 20px; padding: 0 35px; }
.header-right { display: flex; align-items: center; gap: 20px; padding-right: 20px; }
.togglebutton { background-color: #daa5dad7; color: white; border: 1px solid #d890d89c; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; }
.togglebutton:hover { background-color: #864281ff; }
.logo { display: flex; gap: 20px; align-items: center; padding: 0 60px; }
.logo img { height: 90px; width: auto; }
.sidebar { background-color: #c277c2ff; width: 250px; color: black; position: fixed; top: 120px; left: 0; bottom: 0; padding: 20px 0; box-sizing: border-box; transition: all 0.3s ease; z-index: 999; }
.sidebar.collapsed { transform: translateX(-250px); opacity: 0; width: 0; padding: 0; }
.sidebartitle { color: white; font-size: 1rem; margin-bottom: 20px; padding: 0 20px; }
.menu { display: flex; flex-direction: column; gap: 18px; padding: 0; margin: 0; list-style: none; }
.menutext { background-color: rgba(255, 255, 255, 0.1); border-radius: 6px; padding: 14px 18px; color: white; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 20px; }
.menu a { text-decoration: none; color: white; }
.menutext:hover { background-color: #a03198d5; color: white;}
.menutext.active { background-color: #a03198d5; font-weight: 500; color: white;}
.profile { background-color: #7405f1ff; color: white; border: 1px solid rgba(0, 0, 0, 0.3); padding: 8px 15px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; transition: all 0.2s; text-decoration: none; }
.profile:hover { background-color: #2e0c55ff; }
.logoutbutton { background-color: rgba(255, 0, 0, 0.81); color: white; border: 1px solid rgba(0, 0, 0, 0.3); padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; text-decoration: none; }
.main-container { margin-left: 250px; margin-top: 120px; padding: 40px; box-sizing: border-box; flex: 1; transition: margin-left 0.3s ease; width: calc(100% - 250px); }
.main-container.sidebar-collapsed { margin-left: 0; width: 100%; }
.content { background-color: white; padding: 25px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
.map-center { display: flex; justify-content: center; }
.map-wrapper { display: flex; align-items: flex-start; gap: 25px; }
.map-column { width: 500px; }
.map-box { width: 500px; height: 420px; border: 2px solid #000; position: relative; }
.map-box img { width: 100%; height: 100%; object-fit: cover; }
.map-legend { padding-top: 20px; min-width: 120px; }
.legend-item { display: flex; align-items: center; margin-bottom: 15px; font-size: 16px; font-weight: 500; }
.legend-box { width: 22px; height: 22px; line-height: 22px; text-align: center; color: white; font-weight: bold; margin-right: 10px; border-radius: 3px; }
.staff { background-color: #28a745; }
.student { background-color: #007bff; }
.go-parking-wrapper { margin-top: 25px; text-align: center; }
.go-parking-btn { display: inline-block; background-color: #6a22bdff; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-size: 16px; font-weight: 500; transition: background-color 0.2s ease, transform 0.2s ease; }
.go-parking-btn:hover { background-color: #4e1692; transform: translateY(-2px); color: white; }
.go-parking-wrapper { margin-top: 15px; }
.go-parking-btn { display: block; width: 100%; text-align: center; background-color: #6a22bdff; color: white; padding: 12px 0; border-radius: 6px; text-decoration: none; font-size: 16px; font-weight: 500; transition: background-color 0.2s ease, transform 0.2s ease; }
.go-parking-btn:hover { background-color: #4e1692; transform: translateY(-2px); }
.modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); z-index: 2000; justify-content: center; align-items: center; }
.modal-content { background-color: white; border-radius: 10px; width: 90%; max-width: 800px; max-height: 90vh; overflow: hidden; box-shadow: 0 5px 25px rgba(0,0,0,0.3); }
.modal-header { background-color: #6a22bdff; color: white; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { margin: 0; }
.close-btn { background: none; border: none; color: white; font-size: 24px; cursor: pointer; padding: 0; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; border-radius: 50%; }
.close-btn:hover { background-color: rgba(255, 255, 255, 0.2); }
.modal-body { padding: 20px; }
.modal-body iframe { width: 100%; height: 400px; border: none; border-radius: 5px; }
.fa-map-marker-alt { cursor: pointer; transition: transform 0.2s ease; }
.fa-map-marker-alt:hover { transform: scale(1.5); }
footer { background-color: #b8a6ccff; color: white; padding: 15px 0; text-align: center; width: 100%; margin-top: auto; position: relative; bottom: 0; left: 0; transition: margin-left 0.3s ease; }
footer.sidebar-collapsed { margin-left: 0; }
@media (max-width: 768px) { .map-wrapper { flex-direction: column; align-items: center; } .map-column { width: 100%; } .map-box { width: 100%; height: 300px; } .map-legend { width: 100%; padding-top: 0; margin-top: 20px; } }
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
        <h1 class="sidebartitle"><strong>Admin</strong></h1>
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

    <!-- Updated main container to match ParkingArea.php structure -->
    <div class="container main-container" id="mainContainer">
        <div class="content">
            <center><h2 style="font-family: 'Courier New', monospace; font-size: 30px;">
                <i class='fas fa-car-side' style="margin-right: 25px; font-size:30px;color:black"></i>Parking Overview
            </h2></center>
            <div class="map-center">
                <div class="map-wrapper">
                    <!-- LEFT COLUMN (MAP + BUTTON) -->
                    <div class="map-column">
                        <div class="map-box">
                            <img src="Park.jpeg" alt="Parking Map">
                        </div>
                        <div class="go-parking-wrapper">
                            <a href="ParkingArea.php" class="go-parking-btn">
                                Go to Parking Area Management
                                <i class="fa fa-arrow-right" style="margin-left: 25px;"></i>
                            </a>
                        </div>
                    </div>
                    <!-- RIGHT COLUMN (LEGEND) -->
                    <div class="map-legend">
                        <div class="legend-item" style="cursor: pointer;" onclick="openMapModal()">
                            <span><i class="fas fa-map-marker-alt" style="margin-right: 10px;font-size:30px;color:red"></i></span> 
                            View Maps
                        </div>
                        <div class="legend-item">
                            <span class="legend-box staff">A</span> Staff Parking
                        </div>
                        <div class="legend-item">
                            <span class="legend-box student">B</span> Student Parking
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Map Modal -->
    <div id="mapModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3>FK Building Location</h3>
                <button class="close-btn" onclick="closeMapModal()">&times;</button>
            </div>
            <div class="modal-body">
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1160.4801145607137!2d103.42757311595004!3d3.546544319044424!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31cf51cbedb78381%3A0xb0d216592799a9!2sFakulti%20Komputeran%20(FK)%2C%20UMP%20Kampus%20Pekan!5e1!3m2!1sms!2smy!4v1766492940396!5m2!1sms!2smy" 
                    width="100%" 
                    height="400" 
                    style="border:0;" 
                    allowfullscreen 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
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
        // Sidebar Toggle Functionality (same as ParkingArea.php)
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

        // Map Modal Functions
        function openMapModal() {
            const mapModal = document.getElementById('mapModal');
            if (mapModal) {
                mapModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeMapModal() {
            const mapModal = document.getElementById('mapModal');
            if (mapModal) {
                mapModal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // Close modal when clicking outside the modal content
        document.addEventListener('click', function(event) {
            const mapModal = document.getElementById('mapModal');
            if (mapModal && event.target === mapModal) {
                closeMapModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            const mapModal = document.getElementById('mapModal');
            if (event.key === 'Escape' && mapModal && mapModal.style.display === 'flex') {
                closeMapModal();
            }
        });
    </script>
</body>
</html>