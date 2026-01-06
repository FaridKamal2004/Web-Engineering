<?php
// Start the session
session_start();

// Single place to define login redirect header
define('LOGIN_REDIRECT', 'Location: Login.php');

// Database connection parameters
$conn = new mysqli("localhost", "root", "", "fkparksystem", 3306);

// Check if database connection failed
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to only logged-in student
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'student') {
    header(LOGIN_REDIRECT);
    exit();
}

// Get student data from database
$student_id = $_SESSION['user_id'];
$query = "SELECT * FROM student WHERE studentID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    // Student not found in database
    session_destroy();
    header(LOGIN_REDIRECT);
    exit();
}
$student = $result->fetch_assoc();

// GET DATA FOR CARDS AND CHARTS
$total_available_park = 0;
$total_booking = 0;

// 1. Total Available Park (count of parking spots that are currently available)
$available_query = "SELECT COUNT(*) as total FROM ParkingStatus WHERE SpaceStatus = 'available'";
$available_result = $conn->query($available_query);
if ($available_result) {
    $available_data = $available_result->fetch_assoc();
    $total_available_park = $available_data['total'];
}

// 2. Total Booking (count of bookings for this student)
$booking_query = "SELECT COUNT(*) as total FROM booking WHERE StudentID = ?";
$booking_stmt = $conn->prepare($booking_query);
$booking_stmt->bind_param("s", $student_id);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();
if ($booking_result) {
    $booking_data = $booking_result->fetch_assoc();
    $total_booking = $booking_data['total'];
}

// 3. Data for Parking Usage Daily Chart (last 7 days)
$daily_usage_data = [];
$daily_labels = [];
$usage_query = "
    SELECT 
        DATE(BookingDate) as booking_date,
        COUNT(*) as booking_count
    FROM booking 
    WHERE BookingDate >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(BookingDate)
    ORDER BY booking_date
";

$usage_result = $conn->query($usage_query);
if ($usage_result) {
    while ($row = $usage_result->fetch_assoc()) {
        $daily_labels[] = date('M d', strtotime($row['booking_date']));
        $daily_usage_data[] = $row['booking_count'];
    }
}

// Ensure we have 7 days of data (fill missing days with 0)
$last_7_days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('M d', strtotime("-$i days"));
    $last_7_days[$date] = 0;
}

// Merge with actual data
foreach ($daily_labels as $index => $label) {
    if (isset($last_7_days[$label])) {
        $last_7_days[$label] = $daily_usage_data[$index];
    }
}

$daily_labels = array_keys($last_7_days);
$daily_usage_data = array_values($last_7_days);

// 4. Data for Booking Status Chart
$status_data = [];
$status_labels = ['Pending', 'Approved', 'Cancelled'];
$status_colors = ['#FFCE56', '#36A2EB', '#FF6384'];

$status_query = "
    SELECT 
        BookingStatus,
        COUNT(*) as status_count
    FROM booking 
    WHERE StudentID = ?
    GROUP BY BookingStatus
";

$status_stmt = $conn->prepare($status_query);
$status_stmt->bind_param("s", $student_id);
$status_stmt->execute();
$status_result = $status_stmt->get_result();

// Initialize all statuses to 0
foreach ($status_labels as $status) {
    $status_data[$status] = 0;
}

if ($status_result) {
    while ($row = $status_result->fetch_assoc()) {
        $status = ucfirst(strtolower($row['BookingStatus']));
        $status_data[$status] = $row['status_count'];
    }
}

// Prepare data for chart
$booking_status_counts = [];
foreach ($status_labels as $status) {
    $booking_status_counts[] = $status_data[$status];
}

// SEARCH FUNCTIONALITY
$search_results = [];

if (isset($_GET['fsrch']) && $_GET['fsrch'] !== "") {
    $search = "%" . htmlspecialchars($_GET['fsrch']) . "%";

    $sql = "
        /* VEHICLE SEARCH */
        SELECT 'Vehicle' AS Type,
               VehicleID AS ID,
               CONCAT('Plate: ', PlateNumber, ', Model: ', VehicleModel) AS info
        FROM Vehicle
        WHERE StudentID = ? AND VehicleID LIKE ?

        UNION

        /* BOOKING SEARCH */
        SELECT 'Booking' AS Type,
               BookingID AS ID,
               CONCAT('Date: ', BookingDate, ', Status: ', BookingStatus) AS info
        FROM Booking
        WHERE StudentID = ? AND BookingID LIKE ?

        UNION

        /* MERIT SEARCH */
        SELECT 'StudentMerit' AS Type,
               MeritID AS ID,
               CONCAT('Merit: ', MeritPoint, ', Demerit: ', DemeritPoint,
                      ', Total: ') AS info
        FROM StudentMerit
        WHERE StudentID = ? AND MeritID LIKE ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss",
        $student_id, $search,     // Vehicle
        $student_id, $search,     // Booking
        $student_id, $search      // Merit
    );
    $stmt->execute();
    $search_results = $stmt->get_result();
}

// 60 seconds inactivity timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 60) {
    session_unset();
    session_destroy();
    header(LOGIN_REDIRECT);
    exit();
}

// Update activity time on every request
$_SESSION['last_activity'] = time();

// Existing security check (keep this if you already have it)
if (!isset($_SESSION['user_id'])) {
    header(LOGIN_REDIRECT);
    exit();
}

?>

<!DOCTYPE html>
<html>
    <head>
        <title>Student Dashboard</title>
        <meta name="desription" content="StudentDashboard">
        <meta name="author" content="Group1A3">
        <link rel="stylesheet" type="text/css"  href=".css">
        <!-- Include FontAwesome for icons -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <!-- Include Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

            .header{
                background-color: #008080; 
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0 20px;
                position: fixed;
                width: 100%;
                height: 120px;
                box-sizing: border-box;
                z-index: 1000;
            }

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

            /* Toggle Button for Mobile */
            .toggle-btn {
                display: none;
                background: none;
                border: none;
                color: white;
                font-size: 24px;
                cursor: pointer;
                padding: 10px;
                margin-left: 20px;
            }

            .sidebar{
                background-color: #008080;
                width: 250px;
                color: white;
                position: fixed;
                top: 120px;
                left: 0;
                bottom: 0;
                padding: 20px 0;
                box-sizing: border-box;
                transition: transform 0.3s ease;
                z-index: 999;
            }

            /* Toggle Button - Make it ALWAYS visible */
.toggle-btn {
    display: flex; /* Change from 'none' to 'flex' */
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    padding: 12px 15px;
    border-radius: 5px;
    transition: all 0.3s ease;
    z-index: 1001;
    align-items: center;
    justify-content: center;
}

/* Remove or comment out the mobile media query for toggle-btn */
/* @media (max-width: 768px) {
    .toggle-btn {
        display: flex; <-- Remove this line from media query
    }
} */

/* Adjust main content margin for collapsed sidebar */
.maincontent {
    margin-left: 250px; /* Default with sidebar open */
    margin-top: 120px;
    padding: 40px;
    box-sizing: border-box;
    transition: margin-left 0.3s ease;
    min-height: calc(100vh - 120px);
}

/* Add this class for when sidebar is collapsed */
.maincontent.collapsed {
    margin-left: 0; /* When sidebar is collapsed */
}

/* Adjust sidebar for collapsed state */
.sidebar.collapsed {
    transform: translateX(-100%);
}

            /* Mobile sidebar style */
            @media (max-width: 768px) {
                .sidebar {
                    transform: translateX(-100%);
                }
                
                .sidebar.active {
                    transform: translateX(0);
                }
                
                .toggle-btn {
                    display: block;
                }
                
                .maincontent {
                    margin-left: 0;
                }
                
                .maincontent.shifted {
                    margin-left: 250px;
                }
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
                color: white;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                gap: 20px;
            }

            .menu-icon {
                width: 20px;
                text-align: center;
            }

            .menu a {
                text-decoration: none;
                color: inherit;
            }
            
            .menutext:hover {
                background-color: #044747ff;
            }
            
            .menutext.active {
                background-color: #016161ff;
                font-weight: 500;
            }

            .profile{
                background-color: rgba(46, 204, 113, 0.2);
                color: white;
                border: 1px solid rgba(46, 204, 113, 0.3);
                padding: 8px 15px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 1rem;
                display: flex;
                align-items: center;
                gap: 8px;
                transition: all 0.2s;
                text-decoration: none;
            }

            .profile:hover {
                background-color: rgba(52, 152, 219, 0.3);
            }

            .logoutbutton {
               background-color: rgba(255, 0, 0, 0.2);
               color: white;
               border: 1px solid rgba(255, 0, 0, 0.3);
               padding: 8px 12px;
               border-radius: 4px;
               cursor: pointer;
               font-size: 1rem;
               display: flex;
               align-items: center;
               gap: 8px;
               text-decoration: none;
            }

            .maincontent{
               margin-left: 250px;
               margin-top: 120px;
               padding: 40px;
               box-sizing: border-box;
               transition: margin-left 0.3s ease;
            }

            .content {
              background-color: white;
              padding: 25px;
              border-radius: 8px;
              margin-bottom: 25px;
              box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            }

            .searchbar { 
                display: flex; 
                gap: 10px; 
                margin-top: 20px; 
            }

            .searchbar input {
                padding:10px 20px;
                border: 1px solid #ccc;
                border-radius: 5px;
                font-size: 1em;
                flex: 1;
            }

            .searchbar button {
                background: #229ee6;
                color: white;
                border: none;
                border-radius: 5px;
                padding: 10px 18px;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .search-results {
                margin-top: 20px;
                background: #fff5e9;
                border-radius: 7px;
                padding: 18px 22px;
                box-shadow: 0 2px 9px rgba(255,170,60,0.08);
            }

            .seccontent {
                background-color: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            }

            /* Cards */
            .cards {
                display: flex;
                gap: 20px;
                margin-bottom: 30px;
            }

            .card {
                background: #b2e9e9ff;
                padding: 25px 20px;
                border: 1px solid #ccc;
                text-align: center;
                border-radius: 5px;
                font-weight: bold;
                flex: 1;
                min-height: 100px;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
                box-shadow: 0 2px 9px rgba(0, 0, 0, 0.09);
            }

            .card h3 {
                margin: 0 0 10px 0;
                color: #008080;
                font-size: 1.1em;
            }

            .card .number {
                font-size: 2em;
                color: #130358;
                font-weight: bold;
            }

            /* Charts */
            .charts {
               display: flex;
               gap: 20px;
               margin-top: 30px;
            }

            .chart-container {
              flex: 1;
              background: white;
              padding: 20px;
              border: 1px solid #ccc;
              border-radius: 5px;
              box-shadow: 0 2px 9px rgba(0, 0, 0, 0.09);
            }

            .chart-title {
                text-align: center;
                margin-bottom: 15px;
                color: #008080;
                font-weight: bold;
            }

            canvas {
                width: 100% !important;
                height: 250px !important;
            }

            footer {
               background-color: #80cab1ff;
               color: white;
               padding: 15px 0;
               margin-top: 40px;
            }

            /* Responsive adjustments */
            @media (max-width: 1024px) {
                .charts {
                    flex-direction: column;
                }
                
                .cards {
                    flex-wrap: wrap;
                }
                
                .card {
                    flex: 1 1 calc(50% - 20px);
                    min-width: 200px;
                }
            }
            
            @media (max-width: 768px) {
                .header {
                    height: 100px;
                }
                
                .sidebar {
                    top: 100px;
                }
                
                .maincontent {
                    margin-top: 100px;
                    padding: 20px;
                }
                
                .card {
                    flex: 1 1 100%;
                }
            }
        </style>
    </head>
    <script>
    let timeout = 60;          // must match PHP
    let warningTime = 10;      // show warning 10s before timeout
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

    // Toggle sidebar function
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.maincontent');
        sidebar.classList.toggle('active');
        mainContent.classList.toggle('shifted');
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.querySelector('.sidebar');
        const toggleBtn = document.querySelector('.toggle-btn');
        const mainContent = document.querySelector('.maincontent');
        
        if (window.innerWidth <= 768 && 
            !sidebar.contains(event.target) && 
            !toggleBtn.contains(event.target) && 
            sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            mainContent.classList.remove('shifted');
        }
    });

    // Restart timer on user activity
    ["click", "mousemove", "keypress"].forEach(event => {
        document.addEventListener(event, startTimer);
    });

    // Start timer on page load
    startTimer();

    // Initialize charts when page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Daily Usage Chart (Line Chart)
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        const dailyChart = new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($daily_labels); ?>,
                datasets: [{
                    label: 'Daily Bookings',
                    data: <?php echo json_encode($daily_usage_data); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Bookings'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                }
            }
        });

        // Booking Status Chart (Doughnut Chart)
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($status_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($booking_status_counts); ?>,
                    backgroundColor: <?php echo json_encode($status_colors); ?>,
                    borderWidth: 1,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                label += context.raw;
                                return label;
                            }
                        }
                    }
                }
            }
        });
    });
    </script>
    <body>
<header class="header">
    <div class="header-left">
        <!-- This toggle button will now be visible on ALL screens -->
        <button class="toggle-btn" onclick="toggleSidebar()" aria-label="Toggle navigation">
            <i class="fas fa-bars"></i>
        </button>
        <div class="logo">
            <img src="UMPLogo.png" alt="UMPLogo">
        </div>
    </div>
            <div class="header-right">
                <span style="color:white; font-weight:500;">
                    Welcome, <?php echo htmlspecialchars($student['StudentName']); ?>
                </span>
                <a href="StudentProfile.php" class="profile">
                    <i class="fas fa-user-circle"></i> My Profile
                </a>
                <a href="logout.php" class="logoutbutton" onclick="return confirm('Are you sure you want to log out?');">
                   <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>
        
        <nav class="sidebar">
            <h1 class="sidebartitle">Student Bar</h1>
            <ul class="menu">
                <li>
                    <a href="StudentDashboard.php" class="menutext active">
                        <span class="menu-icon"><i class="fas fa-tachometer-alt"></i></span>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="VehicleRegistration.php" class="menutext">
                        <span class="menu-icon"><i class="fas fa-car"></i></span>
                        Vehicle Registration
                    </a>
                </li>
                <li>
                    <a href="Booking.php" class="menutext">
                        <span class="menu-icon"><i class="fas fa-calendar-check"></i></span>
                        Book Parking
                    </a>
                </li>
                <li>
                    <a href="MeritStatus.php" class="menutext">
                        <span class="menu-icon"><i class="fas fa-star"></i></span>
                        Merit status
                    </a>
                </li>
            </ul>
        </nav>

        <div class="maincontent">
            <div class="content">
                <center><h2>Welcome to FK Parking Management System</h2></center>
                <form class="searchbar" method="GET" action="">
                    <input name="fsrch" id="fsrch" placeholder="Type Search">
                    <button type="submit">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
                
                <?php if (!empty($_GET['fsrch'])): ?>
                <div class="search-results">
                    <h3><i class="fas fa-search"></i> Search Results:</h3>

                    <?php if ($search_results->num_rows > 0): ?>
                        <ul>
                            <?php while ($row = $search_results->fetch_assoc()): ?>
                                <li>
                                    <strong>
                                        <?php if($row['Type'] == 'Vehicle'): ?>
                                            <i class="fas fa-car"></i>
                                        <?php elseif($row['Type'] == 'Booking'): ?>
                                            <i class="fas fa-calendar-check"></i>
                                        <?php elseif($row['Type'] == 'StudentMerit'): ?>
                                            <i class="fas fa-star"></i>
                                        <?php endif; ?>
                                        <?php echo $row['Type']; ?>:
                                    </strong>
                                    ID: <?php echo $row['ID']; ?> —
                                    Info: <?php echo $row['info']; ?>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p><i class="fas fa-exclamation-circle"></i> No results found.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="seccontent">
                <div class="cards">
                   <div class="card">
                       <h3><i class="fas fa-parking"></i> Total Available Parking Spots</h3>
                       <div class="number"><?php echo $total_available_park; ?></div>
                   </div>
                   <div class="card">
                       <h3><i class="fas fa-book"></i> Total Bookings</h3>
                       <div class="number"><?php echo $total_booking; ?></div>
                   </div>
                </div>

               <div class="charts">
                   <div class="chart-container">
                       <div class="chart-title"><i class="fas fa-chart-line"></i> Parking Usage (Last 7 Days)</div>
                       <canvas id="dailyChart"></canvas>
                   </div>
                   <div class="chart-container">
                       <div class="chart-title"><i class="fas fa-chart-pie"></i> Booking Status Distribution</div>
                       <canvas id="statusChart"></canvas>
                   </div>
               </div>
           </div>
        </div>
        <footer>
            <center><p><i class="far fa-copyright"></i> 2026 FKPark System</p></center>
        </footer>
        <script>
            // Toggle sidebar function
    function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const mainContent = document.querySelector('.maincontent');
    
    sidebar.classList.toggle('collapsed');
    mainContent.classList.toggle('collapsed');
    
    // If on mobile, also use the overlay
    if (window.innerWidth <= 768) {
        overlay.classList.toggle('active');
    }
}
        </script>
    </body>
</html>
