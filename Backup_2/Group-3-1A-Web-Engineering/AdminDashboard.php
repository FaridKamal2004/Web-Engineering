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
if (!isset($_SESSION['user_id']) || ($_SESSION['type_user'] ?? '') !== 'Administrator') {
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

// DASHBOARD STATISTICS
// Use a single query for multiple counts to reduce database calls
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM ParkingArea) as total_areas,
        (SELECT COUNT(*) FROM ParkingSpace) as total_spaces,
        (SELECT COUNT(*) FROM ParkingStatus WHERE SpaceStatus = 'Available') as available_spaces,
        (SELECT COUNT(*) FROM student) as total_students,
        (SELECT COUNT(*) FROM staff) as total_staff,
        (SELECT COUNT(*) FROM TrafficSummon WHERE MONTH(SummonDate) = MONTH(CURDATE())) as month_summons
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Assign values with defaults
$total_areas = $stats['total_areas'] ?? 0;
$total_spaces = $stats['total_spaces'] ?? 0;
$available_spaces = $stats['available_spaces'] ?? 0;
$total_students = $stats['total_students'] ?? 0;
$total_staff = $stats['total_staff'] ?? 0;
$month_summons = $stats['month_summons'] ?? 0;
$total_users = $total_students + $total_staff;

// Today's bookings (prepared statement for security)
$today = date('Y-m-d');
$booking_stmt = $conn->prepare("SELECT COUNT(*) as today_bookings FROM Booking WHERE DATE(BookingDate) = ?");
$booking_stmt->bind_param("s", $today);
$booking_stmt->execute();
$booking_result = $booking_stmt->get_result();
$today_bookings = $booking_result->fetch_assoc()['today_bookings'] ?? 0;

// Function to fetch chart data
function fetchChartData($conn, $query) {
    $data = ['labels' => [], 'counts' => []];
    $result = $conn->query($query);
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $data['labels'][] = $row['label'] ?? $row['month'] ?? $row['SpaceStatus'] ?? $row['AreaType'] ?? $row['ViolationType'] ?? '';
            $data['counts'][] = $row['count'] ?? 0;
        }
    }
    return $data;
}

// 1. Parking Space Status Distribution
$space_status_data = fetchChartData($conn, "
    SELECT SpaceStatus as label, COUNT(*) as count 
    FROM ParkingStatus 
    GROUP BY SpaceStatus
    ORDER BY FIELD(SpaceStatus, 'Available', 'Occupied', 'Maintenance')
");

// 2. Parking Areas by Type
$area_type_data = fetchChartData($conn, "
    SELECT AreaType as label, COUNT(*) as count 
    FROM ParkingArea 
    GROUP BY AreaType
");

// 3. Traffic Summons (Last 6 months)
$summon_month_data = fetchChartData($conn, "
    SELECT 
        DATE_FORMAT(SummonDate, '%b') as month,
        COUNT(*) as count
    FROM TrafficSummon 
    WHERE SummonDate >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY DATE_FORMAT(SummonDate, '%Y-%m')
    ORDER BY MIN(SummonDate) LIMIT 6
");

// If no data, show placeholder
if(empty($summon_month_data['labels'])) {
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
    $current_month = date('n') - 1;
    $summon_month_data = ['labels' => [], 'counts' => []];
    
    for($i = 5; $i >= 0; $i--) {
        $month_index = ($current_month - $i + 12) % 12;
        $summon_month_data['labels'][] = $months[$month_index];
        $summon_month_data['counts'][] = 0;
    }
}

// 4. Violation Types Distribution
$violation_data = fetchChartData($conn, "
    SELECT ViolationType as label, COUNT(*) as count 
    FROM Violation 
    GROUP BY ViolationType
    ORDER BY count DESC 
    LIMIT 5
");

//Search Function
$search_results = null;
$search_term = '';

if (isset($_GET['fsrch']) && !empty(trim($_GET['fsrch']))) {
    $search_term = trim($_GET['fsrch']);
    
    // Validate search term length
    if (strlen($search_term) >= 2 && strlen($search_term) <= 100) {
        $search = "%" . $conn->real_escape_string($search_term) . "%";
        
        $sql = "
            /* VEHICLE */
            SELECT 'Vehicle' AS Type,
                   VehicleID AS ID,
                   CONCAT('Plate: ', PlateNumber, ', Model: ', VehicleModel) AS info
            FROM Vehicle
            WHERE VehicleID LIKE ? OR PlateNumber LIKE ? OR VehicleModel LIKE ? OR VehicleType LIKE ?

            UNION ALL

            /* BOOKING */
            SELECT 'Booking' AS Type,
                   BookingID AS ID,
                   CONCAT('Date: ', BookingDate, ', Status: ', BookingStatus) AS info
            FROM Booking
            WHERE BookingID LIKE ? OR BookingStatus LIKE ? OR BookingDate LIKE ?

            UNION ALL

            /* MERIT */
            SELECT 'StudentMerit' AS Type,
                   MeritID AS ID,
                   CONCAT('Merit: ', MeritPoint, ', Demerit: ', DemeritPoint) AS info
            FROM StudentMerit
            WHERE MeritID LIKE ? OR MeritPoint LIKE ? OR DemeritPoint LIKE ?

            UNION ALL

            /* TRAFFIC SUMMON */
            SELECT 'TrafficSummon' AS Type,
                   SummonID AS ID,
                   CONCAT('Violation: ', ViolationID, ', Date: ', DATE_FORMAT(SummonDate, '%Y-%m-%d')) AS info
            FROM TrafficSummon
            WHERE SummonID LIKE ? OR ViolationID LIKE ? OR SummonDescription LIKE ?

            UNION ALL

            /* VIOLATION */
            SELECT 'Violation' AS Type,
                   ViolationID AS ID,
                   CONCAT('Type: ', ViolationType, ', Points: ', ViolationPoint) AS info
            FROM Violation
            WHERE ViolationID LIKE ? OR ViolationType LIKE ? OR ViolationPoint LIKE ?

            UNION ALL

            /* PARKING SPACE */
            SELECT 'ParkingSpace' AS Type,
                   ParkingSpaceID AS ID,
                   CONCAT('Space: ', SpaceNumber, ', Type: ', SpaceType) AS info
            FROM ParkingSpace
            WHERE ParkingSpaceID LIKE ? OR SpaceNumber LIKE ? OR SpaceType LIKE ?

            UNION ALL

            /* PARKING AREA */
            SELECT 'ParkingArea' AS Type,
                   ParkingAreaID AS ID,
                   CONCAT('Area: ', AreaType, ', No: ', AreaNumber) AS info
            FROM ParkingArea
            WHERE ParkingAreaID LIKE ? OR AreaType LIKE ? OR AreaNumber LIKE ?
        ";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $param_types = str_repeat('s', 22); // 22 parameters total
            $params = array_fill(0, 22, $search);
            $stmt->bind_param($param_types, ...$params);
            $stmt->execute();
            $search_results = $stmt->get_result();
        }
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

// Calculate derived statistics
$occupied_spaces = $total_spaces - $available_spaces;
$utilization = $total_spaces > 0 ? round(($occupied_spaces / $total_spaces) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* CSS remains exactly the same */
        body { background-color: #f5f5f5; font-family: 'Roboto', sans-serif; margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; }
        .header{ background-color: #d373d3ff; display: flex; justify-content: space-between; align-items: center; padding: 0 20px; position: fixed; width: 100%; height: 120px; box-sizing: border-box; z-index: 1000; top: 0; left: 0; }
        .header-left{ display: flex; align-items: center; gap: 20px; padding: 0 35px; }
        .header-right{ display: flex; align-items: center; gap: 20px; padding-right: 20px; }
        .togglebutton { background-color: #daa5dad7; color: white; border: 1px solid #d890d89c; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; }
        .togglebutton:hover { background-color: #864281ff; }
        .logo{ display: flex; gap: 20px; align-items: center; padding: 0 60px; }
        .logo img{ height: 90px; width: auto; }
        .sidebar{ background-color: #c277c2ff; width: 250px; color: black; position: fixed; top: 120px; left: 0; bottom: 0; padding: 20px 0; box-sizing: border-box; transition: all 0.3s ease; z-index: 999; overflow-y: auto; }
        .sidebar.collapsed { transform: translateX(-250px); opacity: 0; width: 0; padding: 0; }
        .sidebartitle{ color: white; font-size: 1rem; margin-bottom: 20px; padding: 0 20px; }
        .menu{ display: flex; flex-direction: column; gap: 18px; padding: 0; margin: 0; list-style: none; }
        .menutext{ background-color: rgba(255, 255, 255, 0.1); border-radius: 6px; padding: 14px 18px; color: white; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 20px; text-decoration: none; }
        .menutext:hover { background-color: #a03198d5; color: white; }   
        .menutext.active { background-color: #a03198d5; font-weight: 500; color: white; }
        .profile{ background-color: #7405f1ff; color: white; border: 1px solid rgba(0, 0, 0, 0.3); padding: 8px 15px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; transition: all 0.2s; text-decoration: none; }
        .profile:hover { background-color: #2e0c55ff; }
        .logoutbutton { background-color: rgba(255, 0, 0, 0.81); color: white; border: 1px solid rgba(0, 0, 0, 0.3); padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; text-decoration: none; }
        .logoutbutton:hover { background-color: rgba(200, 0, 0, 0.81); }
        .main-container { margin-left: 250px; margin-top: 120px; padding: 40px; box-sizing: border-box; flex: 1; transition: margin-left 0.3s ease; width: calc(100% - 250px); }
        .main-container.sidebar-collapsed { margin-left: 0; width: 100%; }
        .content { background-color: white; padding: 25px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        .cards-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { background: linear-gradient(135deg, #6a22bdff, #3f1174ff); color: white; padding: 25px; text-align: center; border-radius: 10px; min-height: 120px; display: flex; flex-direction: column; justify-content: center; align-items: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1); transition: all 0.3s ease; border: none; position: relative; overflow: hidden; }
        .card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: rgba(255,255,255,0.3); }
        .card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
        .card h3 { margin: 0; font-size: 2.8rem; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.2); line-height: 1; }
        .card p { margin: 10px 0 0 0; font-size: 1rem; opacity: 0.9; display: flex; align-items: center; gap: 8px; justify-content: center; }
        .card i { font-size: 1.1rem; }
        .charts-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 25px; margin-top: 30px; }
        .chart-container { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 3px 10px rgba(0,0,0,0.08); border: 1px solid #e8e8e8; transition: transform 0.3s ease; }
        .chart-container:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .chart-title { font-size: 1.3rem; font-weight: 600; margin-bottom: 15px; color: #333; text-align: center; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
        .chart-wrapper { position: relative; height: 300px; width: 100%; }
        .searchbar { display: flex; gap: 10px; margin: 0; }
        .searchbar input { padding:10px 20px; border: 1px solid #ccc; border-radius: 5px; font-size: 1em; flex: 1; }
        .searchbar button { background: #572096ff; color: white; border: none; border-radius: 5px; padding: 10px 18px; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .search-results { margin-top: 20px; background: #fff5e9; border-radius: 7px; padding: 18px 22px; box-shadow: 0 2px 9px rgba(255,170,60,0.08); }
        footer { background-color: #b8a6ccff; color: white; padding: 15px 0; text-align: center; width: 100%; margin-top: auto; position: relative; bottom: 0; left: 0; }
        footer.sidebar-collapsed { margin-left: 0; }
        @media (max-width: 992px) { .cards-container { grid-template-columns: repeat(2, 1fr); } .charts-container { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .cards-container { grid-template-columns: 1fr; } .chart-container { padding: 15px; } .chart-wrapper { height: 250px; } .main-container { margin-left: 0; width: 100%; padding: 20px; } .card h3 { font-size: 2.5rem; } }
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
                <a href="AdminDashboard.php" class="menutext active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="ManageUser.php" class="menutext">
                    <i class="fas fa-users"></i> Manage User
                </a>
            </li>
            <li>
                <a href="ParkingManagement.php" class="menutext">
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
        <!-- Search Section - Placed before Welcome -->
        <div class="content mb-4">
             <h2 class="text-center mb-3">Welcome to FK Parking Management System</h2>
            <!-- Search Form -->
            <form class="searchbar" method="GET" action="">
                <input name="fsrch" id="fsrch" placeholder="Type Search... (Vehicle, Booking, Summon, etc.)" 
                       value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
            
            <?php if (!empty($search_term)): ?>
            <div class="search-results">
                <h3>Search Results for "<?php echo htmlspecialchars($search_term); ?>":</h3>
                <?php if ($search_results && $search_results->num_rows > 0): ?>
                    <ul class="list-group">
                        <?php while ($row = $search_results->fetch_assoc()): ?>
                            <li class="list-group-item">
                                <strong><?php echo htmlspecialchars($row['Type']); ?>:</strong>
                                ID: <?php echo htmlspecialchars($row['ID']); ?> —
                                Info: <?php echo htmlspecialchars($row['info']); ?>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">No results found.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="content">
            <!-- Statistics Cards -->
            <div class="cards-container">
                <?php 
                $card_data = [
                    ['value' => $total_areas, 'label' => 'Parking Areas', 'icon' => 'fa-map-marked-alt'],
                    ['value' => $total_spaces, 'label' => 'Total Spaces', 'icon' => 'fa-parking'],
                    ['value' => $available_spaces, 'label' => 'Available Now', 'icon' => 'fa-car'],
                    ['value' => $total_users, 'label' => 'Total Users', 'icon' => 'fa-users'],
                    ['value' => $today_bookings, 'label' => "Today's Bookings", 'icon' => 'fa-calendar-check'],
                    ['value' => $month_summons, 'label' => 'Monthly Summons', 'icon' => 'fa-exclamation-triangle']
                ];
                foreach ($card_data as $card): ?>
                <div class="card">
                    <h3><?php echo $card['value']; ?></h3>
                    <p><i class="fas <?php echo $card['icon']; ?>"></i> <?php echo $card['label']; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        <!-- Charts Section -->
        <div class="content">
            <h3 class="mb-4"><i class="fas fa-chart-line"></i> System Analytics</h3>
            
            <div class="charts-container">
                <!-- Chart 1: Parking Space Status -->
                <div class="chart-container">
                    <div class="chart-title">Parking Space Status Distribution</div>
                    <div class="chart-wrapper">
                        <canvas id="spaceStatusChart"></canvas>
                    </div>
                </div>

                <!-- Chart 2: Traffic Summons by Month -->
                <div class="chart-container">
                    <div class="chart-title">Traffic Summons (Last 6 Months)</div>
                    <div class="chart-wrapper">
                        <canvas id="summonChart"></canvas>
                    </div>
                </div>

                <!-- Chart 3: Parking Areas by Type -->
                <div class="chart-container">
                    <div class="chart-title">Parking Areas by Type</div>
                    <div class="chart-wrapper">
                        <canvas id="areaTypeChart"></canvas>
                    </div>
                </div>

                <!-- Chart 4: Top Violations -->
                <div class="chart-container">
                    <div class="chart-title">Top 5 Violation Types</div>
                    <div class="chart-wrapper">
                        <canvas id="violationChart"></canvas>
                    </div>
                </div>
            </div>
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

     // Sidebar Toggle Functionality
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContainer = document.getElementById('mainContainer');
        const footer = document.getElementById('footer');
            
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContainer.classList.toggle('sidebar-collapsed');
            footer.classList.toggle('sidebar-collapsed');
                    
            const isCollapsed = sidebar.classList.contains('collapsed');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        });        
            const savedState = localStorage.getItem('sidebarCollapsed');
                if (savedState === 'true') {
                    sidebar.classList.add('collapsed');
                    mainContainer.classList.add('sidebar-collapsed');
                    footer.classList.add('sidebar-collapsed');
                }
            }
            
    // Initialize Charts
    initializeCharts();
});        
    function initializeCharts() {
        // Chart 1: Parking Space Status (Doughnut Chart)
        const spaceStatusCtx = document.getElementById('spaceStatusChart');
            if (spaceStatusCtx) {
                new Chart(spaceStatusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($space_status_data['labels']); ?>,
                        datasets: [{
                            data: <?php echo json_encode($space_status_data['counts']); ?>,
                            backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }

            // Chart 2: Traffic Summons (Line Chart)
            const summonCtx = document.getElementById('summonChart');
            if (summonCtx) {
                new Chart(summonCtx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($summon_month_data['labels']); ?>,
                        datasets: [{
                            label: 'Traffic Summons',
                            data: <?php echo json_encode($summon_month_data['counts']); ?>,
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#dc3545',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            // Chart 3: Parking Areas by Type (Bar Chart)
            const areaTypeCtx = document.getElementById('areaTypeChart');
            if (areaTypeCtx) {
                new Chart(areaTypeCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($area_type_data['labels']); ?>,
                        datasets: [{
                            label: 'Number of Areas',
                            data: <?php echo json_encode($area_type_data['counts']); ?>,
                            backgroundColor: '#6a22bd',
                            borderColor: '#4a1899',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });
            }

            // Chart 4: Violation Types (Bar Chart)
            const violationCtx = document.getElementById('violationChart');
            if (violationCtx) {
                new Chart(violationCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($violation_data['labels']); ?>,
                        datasets: [{
                            label: 'Number of Occurrences',
                            data: <?php echo json_encode($violation_data['counts']); ?>,
                            backgroundColor: '#ffc107',
                            borderColor: '#e0a800',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                beginAtZero: true
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
        }
    </script>
</body>
</html>