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

if (isset($_GET['delete_id'])) {
    $deleteID = $_GET['delete_id'];
    $deleteSql = "DELETE FROM Vehicle WHERE VehicleID = '$deleteID'";
    if ($conn->query($deleteSql) === TRUE) {
        echo "<script>alert('Vehicle deleted successfully.'); window.location.href='VehicleApproval.php';</script>";
    } else {
        echo "<script>alert('Error deleting vehicle: " . $conn->error . "');</script>";
    }
}

$search = "";
if (isset($_GET['search'])) {
    $search = $_GET['search'];
}

$sql = "SELECT * FROM Vehicle";

if ($search != "") {
    $sql = "SELECT * FROM Vehicle WHERE StudentID LIKE '%$search%' OR PlateNumber LIKE '%$search%'";
}

$result = $conn->query($sql);

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
    <title>FK Park System - Vehicle Approval</title>
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
        
        /* Vehicle Approval Specific Styles */
        .searchbar {
            margin-bottom: 25px;
            display: flex;
            gap: 10px;
            max-width: 500px;
        }
        .searchbar input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        .searchbar button {
            background-color: #eb9d43ff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .searchbar button:hover {
            background-color: #6d4e2aff;
        }
        .table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table thead {
            background-color: #eb9d43ff;
            color: white;
        }
        table th, table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        table tbody tr:hover {
            background-color: #f9f9f9;
        }
        .review {
            background-color: #eb9d43ff;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: background 0.3s;
        }
        .review:hover {
            background-color: #6d4e2aff;
        }
        .delete-btn {
            background-color: #dc3545;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: background 0.3s;
            margin-left: 8px;
        }
        .delete-btn:hover {
            background-color: #c82333;
        }
        .status-pending {
            color: #ffc107;
            font-weight: 500;
        }
        .status-approved {
            color: #28a745;
            font-weight: 500;
        }
        .status-rejected {
            color: #dc3545;
            font-weight: 500;
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
        <h1 class="mb-4"><i class="fas fa-car"></i> Vehicle Approval</h1>
        
        <form action="VehicleApproval.php" method="get" class="searchbar">
            <input type="text" name="search" placeholder="Search by Student ID or Plate Number..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit"><i class="fas fa-search"></i> Search</button>
        </form>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Vehicle ID</th>
                        <th>Student ID</th>
                        <th>Type</th>
                        <th>Plate No.</th>
                        <th>Model</th>
                        <th>Colour</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            // Determine status class
                            $statusClass = 'status-' . strtolower($row["VehicleApproval"]);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row["VehicleID"]); ?></td>
                                <td><?php echo htmlspecialchars($row["StudentID"]); ?></td>
                                <td><?php echo htmlspecialchars($row["VehicleType"]); ?></td>
                                <td><?php echo htmlspecialchars($row["PlateNumber"]); ?></td>
                                <td><?php echo htmlspecialchars($row["VehicleModel"]); ?></td>
                                <td><?php echo htmlspecialchars($row["VehicleColour"]); ?></td>
                                <td class="<?php echo $statusClass; ?>">
                                    <?php echo htmlspecialchars($row["VehicleApproval"]); ?>
                                </td>
                                <td>
                                    <a href="ReviewVehicle.php?id=<?php echo $row['VehicleID']; ?>" class="review">
                                        <i class="fas fa-eye"></i> Review
                                    </a>
                                    <a href="VehicleApproval.php?delete_id=<?php echo $row['VehicleID']; ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete this vehicle application?');">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo '<tr><td colspan="8" style="text-align:center; padding:30px;">No vehicles found</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
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