<?php
// Start the session
session_start();

// Database connection parameters
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3306);

// Check if database connection failed
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to only logged-in admin
if (!isset($_SESSION['user_id']) || ($_SESSION['type_user'] ?? '') !== 'Administrator') {
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

// Get filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
$report_type = $_GET['report_type'] ?? 'summary';

// ================== REPORT QUERIES ==================
// 1. SUMMARY REPORT (Default)
if ($report_type == 'summary') {
    // Overall statistics
    $total_areas = $conn->query("SELECT COUNT(*) as count FROM ParkingArea")->fetch_assoc()['count'] ?? 0;
    $total_spaces = $conn->query("SELECT COUNT(*) as count FROM ParkingSpace")->fetch_assoc()['count'] ?? 0;
    $available_spaces = $conn->query("SELECT COUNT(*) as count FROM ParkingStatus WHERE SpaceStatus = 'Available'")->fetch_assoc()['count'] ?? 0;
    
    // Booking statistics for date range
    $booking_stats = $conn->prepare("
        SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN BookingStatus = 'Confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
            SUM(CASE WHEN BookingStatus = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
            SUM(CASE WHEN BookingStatus = 'Completed' THEN 1 ELSE 0 END) as completed_bookings
        FROM Booking 
        WHERE BookingDate BETWEEN ? AND ?
    ");
    $booking_stats->bind_param("ss", $start_date, $end_date);
    $booking_stats->execute();
    $booking_result = $booking_stats->get_result();
    $booking_data = $booking_result->fetch_assoc();
    
    // Traffic summon statistics for date range
    $summon_stats = $conn->prepare("
        SELECT COUNT(*) as total_summons
        FROM TrafficSummon 
        WHERE SummonDate BETWEEN ? AND ?
    ");
    $summon_stats->bind_param("ss", $start_date, $end_date);
    $summon_stats->execute();
    $summon_result = $summon_stats->get_result();
    $summon_data = $summon_result->fetch_assoc();
    
    // User statistics
    $total_students = $conn->query("SELECT COUNT(*) as count FROM student")->fetch_assoc()['count'] ?? 0;
    $total_staff = $conn->query("SELECT COUNT(*) as count FROM staff")->fetch_assoc()['count'] ?? 0;
}

// 2. PARKING UTILIZATION REPORT
if ($report_type == 'parking') {
    // Parking area utilization
    $parking_report = $conn->query("
        SELECT 
            pa.AreaNumber,
            pa.AreaType,
            pa.TotalSpaces,
            COUNT(ps.SpaceNumber) as current_spaces,
            SUM(CASE WHEN pst.SpaceStatus = 'Available' THEN 1 ELSE 0 END) as available_spaces,
            SUM(CASE WHEN pst.SpaceStatus = 'Occupied' THEN 1 ELSE 0 END) as occupied_spaces,
            SUM(CASE WHEN pst.SpaceStatus = 'Maintenance' THEN 1 ELSE 0 END) as maintenance_spaces,
            ROUND((SUM(CASE WHEN pst.SpaceStatus = 'Occupied' THEN 1 ELSE 0 END) / pa.TotalSpaces) * 100, 2) as utilization_rate
        FROM ParkingArea pa
        LEFT JOIN ParkingSpace ps ON pa.ParkingAreaID = ps.ParkingAreaID
        LEFT JOIN ParkingStatus pst ON ps.ParkingSpaceID = pst.ParkingSpaceID
        GROUP BY pa.ParkingAreaID, pa.AreaNumber, pa.AreaType, pa.TotalSpaces
        ORDER BY utilization_rate DESC
    ");
    
    $parking_data = [];
    while($row = $parking_report->fetch_assoc()) {
        $parking_data[] = $row;
    }
}

// 3. BOOKING REPORT - CORRECTED
if ($report_type == 'booking') {
    // Booking details for date range - FIXED QUERY
    $booking_report = $conn->prepare("
        SELECT 
            b.BookingID,
            b.BookingDate,
            b.BookingTime,
            b.BookingStatus,
            s.StudentName,
            v.PlateNumber,
            v.VehicleModel,
            ps.SpaceNumber,
            pa.AreaNumber
        FROM Booking b
        JOIN Student s ON b.StudentID = s.StudentID
        JOIN Vehicle v ON s.StudentID = v.StudentID
        JOIN ParkingSpace ps ON b.ParkingSpaceID = ps.ParkingSpaceID
        JOIN ParkingArea pa ON ps.ParkingAreaID = pa.ParkingAreaID
        WHERE b.BookingDate BETWEEN ? AND ?
        ORDER BY b.BookingDate DESC, b.BookingTime DESC
    ");
    $booking_report->bind_param("ss", $start_date, $end_date);
    $booking_report->execute();
    $booking_result = $booking_report->get_result();
    
    $booking_report_data = [];
    while($row = $booking_result->fetch_assoc()) {
        $booking_report_data[] = $row;
    }
    
    // Booking statistics by day
    $booking_daily = $conn->prepare("
        SELECT 
            DATE(b.BookingDate) as booking_day,
            COUNT(*) as total_bookings,
            SUM(CASE WHEN b.BookingStatus = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN b.BookingStatus = 'Cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN b.BookingStatus = 'Completed' THEN 1 ELSE 0 END) as completed
        FROM Booking b
        WHERE b.BookingDate BETWEEN ? AND ?
        GROUP BY DATE(b.BookingDate)
        ORDER BY booking_day
    ");
    $booking_daily->bind_param("ss", $start_date, $end_date);
    $booking_daily->execute();
    $daily_result = $booking_daily->get_result();
    
    $booking_daily_data = [];
    $booking_daily_labels = [];
    $booking_daily_counts = [];
    while($row = $daily_result->fetch_assoc()) {
        $booking_daily_data[] = $row;
        $booking_daily_labels[] = date('d M', strtotime($row['booking_day']));
        $booking_daily_counts[] = $row['total_bookings'];
    }
}

// 4. TRAFFIC SUMMON REPORT - CORRECTED
if ($report_type == 'summon') {
    // Traffic summon details for date range - FIXED QUERY
    $summon_report = $conn->prepare("
        SELECT 
            ts.SummonID,
            ts.SummonDate,
            ts.SummonTime,
            ts.SummonDescription,
            v.ViolationType,
            s.StudentName,
            ts.FineAmount
        FROM TrafficSummon ts
        JOIN Violation v ON ts.ViolationID = v.ViolationID
        JOIN Student s ON ts.StudentID = s.StudentID
        WHERE ts.SummonDate BETWEEN ? AND ?
        ORDER BY ts.SummonDate DESC, ts.SummonTime DESC
    ");
    $summon_report->bind_param("ss", $start_date, $end_date);
    $summon_report->execute();
    $summon_result = $summon_report->get_result();
    
    $summon_report_data = [];
    while($row = $summon_result->fetch_assoc()) {
        $summon_report_data[] = $row;
    }
    
    // Summon statistics by violation type - FIXED QUERY
    $summon_by_violation = $conn->prepare("
        SELECT 
            v.ViolationType,
            COUNT(ts.SummonID) as count
        FROM TrafficSummon ts
        JOIN Violation v ON ts.ViolationID = v.ViolationID
        WHERE ts.SummonDate BETWEEN ? AND ?
        GROUP BY v.ViolationID, v.ViolationType
        ORDER BY count DESC
    ");
    $summon_by_violation->bind_param("ss", $start_date, $end_date);
    $summon_by_violation->execute();
    $violation_result = $summon_by_violation->get_result();
    
    $violation_data = [];
    $violation_labels = [];
    $violation_counts = [];
    while($row = $violation_result->fetch_assoc()) {
        $violation_data[] = $row;
        $violation_labels[] = $row['ViolationType'];
        $violation_counts[] = $row['count'];
    }
}

// 5. USER ACTIVITY REPORT - CORRECTED
if ($report_type == 'user') {
    // User statistics - SIMPLIFIED VERSION
    $user_stats = $conn->query("
        SELECT 
            'Student' as user_type,
            COUNT(*) as total_users
        FROM student
        
        UNION ALL
        
        SELECT 
            'Staff' as user_type,
            COUNT(*) as total_users
        FROM staff
    ");
    
    $user_data = [];
    while($row = $user_stats->fetch_assoc()) {
        $user_data[] = $row;
    }
    
    // Top users by bookings - SIMPLIFIED VERSION
    $top_users = $conn->prepare("
        SELECT 
            s.StudentName as user_name,
            COUNT(b.BookingID) as total_bookings
        FROM Student s
        LEFT JOIN Booking b ON s.StudentID = b.StudentID 
            AND b.BookingDate BETWEEN ? AND ?
        GROUP BY s.StudentID, s.StudentName
        ORDER BY total_bookings DESC
        LIMIT 10
    ");
    $top_users->bind_param("ss", $start_date, $end_date);
    $top_users->execute();
    $top_users_result = $top_users->get_result();
    
    $top_users_data = [];
    while($row = $top_users_result->fetch_assoc()) {
        $top_users_data[] = array_merge($row, ['user_type' => 'Student']);
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
    <title>FK Park System - Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Your existing CSS styles remain the same */
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
        .main-container.sidebar-collapsed { margin-left: 0; transition: margin-left 0.3s ease; }
        .menu { display: flex; flex-direction: column; gap: 18px; padding: 0; margin: 0; list-style: none; }
        .menutext { background-color: rgba(255, 255, 255, 0.1); border-radius: 6px; padding: 14px 18px; color: black; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 20px; }
        .menu a { text-decoration: none; color: white; }
        .menutext:hover { background-color: #a03198d5; color: white; }
        .menutext.active { background-color: #a03198d5; font-weight: 500; color: white; }
        .profile { background-color: #7405f1ff; color: white; border: 1px solid rgba(0, 0, 0, 0.3); padding: 8px 15px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; transition: all 0.2s; text-decoration: none; }
        .profile:hover { background-color: #2e0c55ff; }
        .logoutbutton { background-color: rgba(255, 0, 0, 0.81); color: white; border: 1px solid rgba(0, 0, 0, 0.3); padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; text-decoration: none; }
        .main-container { margin-left: 250px; margin-top: 120px; padding: 40px; box-sizing: border-box; flex: 1; transition: margin-left 0.3s ease; width: calc(100% - 250px); }
        .main-container.sidebar-collapsed { margin-left: 0; width: 100%; }
        .form-container { background-color: white; padding: 25px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        .btn-add { background: #0066cc; padding: 8px 12px; color: white; text-decoration: none; border-radius: 4px; }
        .action-links a { margin-right: 10px; text-decoration: none; color: #0066cc; }
        footer { background-color: #b8a6ccff; color: white; padding: 15px 0; text-align: center; width: 100%; margin-top: auto; position: relative; bottom: 0; left: 0; transition: margin-left 0.3s ease; }
        footer.sidebar-collapsed { margin-left: 0; }
        .report-card { background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #e8e8e8; }
        .report-title { color: #6a22bdff; border-bottom: 2px solid #6a22bdff; padding-bottom: 10px; margin-bottom: 20px; }
        .stat-card { background: linear-gradient(135deg, #6a22bdff, #3f1174ff); color: white; padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 15px; }
        .stat-card .number { font-size: 2.5rem; font-weight: bold; margin-bottom: 5px; }
        .stat-card .label { font-size: 0.9rem; opacity: 0.9; }
        .chart-container { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .chart-title { font-size: 1.2rem; font-weight: 600; margin-bottom: 15px; color: #333; }
        .chart-wrapper { position: relative; height: 300px; width: 100%; }
        .nav-tabs .nav-link { color: #6a22bdff; font-weight: 500; }
        .nav-tabs .nav-link.active { background-color: #6a22bdff; color: white; border-color: #6a22bdff; }
        .table-responsive { border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .table th { background-color: #6a22bdff; color: white; border: none; }  
        .table td { vertical-align: middle; }
        .badge-confirmed { background-color: #28a745; }
        .badge-cancelled { background-color: #dc3545; }
        .badge-completed { background-color: #17a2b8; }
        .badge-pending { background-color: #ffc107; color: #212529; }
        .badge-paid { background-color: #28a745; }
        .badge-unpaid { background-color: #dc3545; }
        @media (max-width: 768px) { .main-container { margin-left: 0; width: 100%; padding: 20px; } .stat-card .number { font-size: 2rem; } .chart-wrapper { height: 250px; } }
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
                <a href="ParkingManagement.php" class="menutext">
                    <i class="fas fa-parking"></i> Parking Management
                </a>
            </li>
            <li>
                <a href="Report.php" class="menutext active">
                    <i class="fas fa-chart-bar"></i> Report
                </a>
            </li>
        </ul>
    </nav>

    <div class="container main-container" id="mainContainer">
        <h1 class="mb-4"><i class="fas fa-chart-bar"></i> System Reports</h1>
        
        <!-- Filter Form -->
        <div class="form-container">
            <h4 class="mb-4"><i class="fas fa-filter"></i> Filter Reports</h4>
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Report Type</label>
                    <select name="report_type" class="form-select">
                        <option value="summary" <?php echo $report_type == 'summary' ? 'selected' : ''; ?>>Summary Report</option>
                        <option value="parking" <?php echo $report_type == 'parking' ? 'selected' : ''; ?>>Parking Utilization</option>
                        <option value="booking" <?php echo $report_type == 'booking' ? 'selected' : ''; ?>>Booking Report</option>
                        <option value="summon" <?php echo $report_type == 'summon' ? 'selected' : ''; ?>>Traffic Summon Report</option>
                        <option value="user" <?php echo $report_type == 'user' ? 'selected' : ''; ?>>User Activity Report</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Generate Report
                    </button>
                </div>
            </form>
        </div>

        <!-- Report Navigation Tabs -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?php echo $report_type == 'summary' ? 'active' : ''; ?>" href="?report_type=summary&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                    <i class="fas fa-chart-pie"></i> Summary
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $report_type == 'parking' ? 'active' : ''; ?>" href="?report_type=parking&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                    <i class="fas fa-parking"></i> Parking
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $report_type == 'booking' ? 'active' : ''; ?>" href="?report_type=booking&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                    <i class="fas fa-calendar-check"></i> Bookings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $report_type == 'summon' ? 'active' : ''; ?>" href="?report_type=summon&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                    <i class="fas fa-exclamation-triangle"></i> Traffic Summons
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $report_type == 'user' ? 'active' : ''; ?>" href="?report_type=user&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>">
                    <i class="fas fa-users"></i> User Activity
                </a>
            </li>
        </ul>

        <!-- Report Content -->
        <?php if ($report_type == 'summary'): ?>
            <!-- SUMMARY REPORT -->
            <div class="report-card">
                <h3 class="report-title"><i class="fas fa-chart-pie"></i> System Summary Report</h3>
                
                <!-- Overall Statistics -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="number"><?php echo $total_areas; ?></div>
                            <div class="label">Total Parking Areas</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="number"><?php echo $total_spaces; ?></div>
                            <div class="label">Total Parking Spaces</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="number"><?php echo $available_spaces; ?></div>
                            <div class="label">Available Spaces</div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="number"><?php echo $booking_data['total_bookings'] ?? 0; ?></div>
                            <div class="label">Total Bookings</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="number"><?php echo $summon_data['total_summons'] ?? 0; ?></div>
                            <div class="label">Traffic Summons</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <div class="number"><?php echo ($total_students + $total_staff); ?></div>
                            <div class="label">Total Users</div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="chart-container">
                            <div class="chart-title">Booking Status Distribution</div>
                            <div class="chart-wrapper">
                                <canvas id="bookingStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="chart-container">
                            <div class="chart-title">User Distribution</div>
                            <div class="chart-wrapper">
                                <canvas id="userDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        <?php elseif ($report_type == 'parking'): ?>
            <!-- PARKING UTILIZATION REPORT -->
            <div class="report-card">
                <h3 class="report-title"><i class="fas fa-parking"></i> Parking Utilization Report</h3>
                
                <!-- Parking Areas Table -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Area Number</th>
                                <th>Area Type</th>
                                <th>Total Spaces</th>
                                <th>Available</th>
                                <th>Occupied</th>
                                <th>Maintenance</th>
                                <th>Utilization Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($parking_data)): ?>
                                <?php foreach($parking_data as $area): ?>
                                <tr>
                                    <td><?php echo $area['AreaNumber']; ?></td>
                                    <td><?php echo $area['AreaType']; ?></td>
                                    <td><?php echo $area['TotalSpaces']; ?></td>
                                    <td><span class="badge bg-success"><?php echo $area['available_spaces']; ?></span></td>
                                    <td><span class="badge bg-danger"><?php echo $area['occupied_spaces']; ?></span></td>
                                    <td><span class="badge bg-warning text-dark"><?php echo $area['maintenance_spaces']; ?></span></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar <?php echo $area['utilization_rate'] > 80 ? 'bg-danger' : ($area['utilization_rate'] > 60 ? 'bg-warning' : 'bg-success'); ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $area['utilization_rate']; ?>%">
                                                <?php echo $area['utilization_rate']; ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No parking data available</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        <?php elseif ($report_type == 'booking'): ?>
            <!-- BOOKING REPORT - CORRECTED -->
            <div class="report-card">
                <h3 class="report-title"><i class="fas fa-calendar-check"></i> Booking Report</h3>
                
                <!-- Booking Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="number"><?php echo count($booking_report_data); ?></div>
                            <div class="label">Total Bookings</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="number">
                                <?php 
                                    $confirmed = 0;
                                    foreach($booking_report_data as $booking) {
                                        if($booking['BookingStatus'] == 'Confirmed') $confirmed++;
                                    }
                                    echo $confirmed;
                                ?>
                            </div>
                            <div class="label">Confirmed</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="number">
                                <?php 
                                    $completed = 0;
                                    foreach($booking_report_data as $booking) {
                                        if($booking['BookingStatus'] == 'Completed') $completed++;
                                    }
                                    echo $completed;
                                ?>
                            </div>
                            <div class="label">Completed</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="number">
                                <?php 
                                    $cancelled = 0;
                                    foreach($booking_report_data as $booking) {
                                        if($booking['BookingStatus'] == 'Cancelled') $cancelled++;
                                    }
                                    echo $cancelled;
                                ?>
                            </div>
                            <div class="label">Cancelled</div>
                        </div>
                    </div>
                </div>
                
                <!-- Daily Bookings Chart -->
                <?php if(!empty($booking_daily_labels)): ?>
                <div class="chart-container mb-4">
                    <div class="chart-title">Daily Bookings Trend</div>
                    <div class="chart-wrapper">
                        <canvas id="dailyBookingsChart"></canvas>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Bookings Table - CORRECTED -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th>Student</th>
                                <th>Vehicle</th>
                                <th>Parking Space</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($booking_report_data)): ?>
                                <?php foreach($booking_report_data as $booking): ?>
                                <tr>
                                    <td><?php echo $booking['BookingID']; ?></td>
                                    <td><?php echo date('d M Y', strtotime($booking['BookingDate'])) . ' ' . $booking['BookingTime']; ?></td>
                                    <td>
                                        <?php 
                                            $status_class = '';
                                            switch($booking['BookingStatus']) {
                                                case 'Confirmed': $status_class = 'badge-confirmed'; break;
                                                case 'Cancelled': $status_class = 'badge-cancelled'; break;
                                                case 'Completed': $status_class = 'badge-completed'; break;
                                                default: $status_class = 'badge-pending';
                                            }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo $booking['BookingStatus']; ?></span>
                                    </td>
                                    <td><?php echo $booking['StudentName']; ?></td>
                                    <td><?php echo $booking['PlateNumber'] . ' (' . ($booking['VehicleModel'] ?? 'N/A') . ')'; ?></td>
                                    <td><?php echo ($booking['AreaNumber'] ?? 'N/A') . '-' . ($booking['SpaceNumber'] ?? 'N/A'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No booking data found for the selected period</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        <?php elseif ($report_type == 'summon'): ?>
            <!-- TRAFFIC SUMMON REPORT - CORRECTED -->
            <div class="report-card">
                <h3 class="report-title"><i class="fas fa-exclamation-triangle"></i> Traffic Summon Report</h3>
                
                <!-- Summon Statistics -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="stat-card">
                            <div class="number"><?php echo count($summon_report_data); ?></div>
                            <div class="label">Total Summons</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stat-card">
                            <div class="number"><?php echo count($violation_labels); ?></div>
                            <div class="label">Violation Types</div>
                        </div>
                    </div>
                </div>
                
                <!-- Violation Distribution Chart -->
                <?php if(!empty($violation_labels)): ?>
                <div class="chart-container mb-4">
                    <div class="chart-title">Violation Type Distribution</div>
                    <div class="chart-wrapper">
                        <canvas id="violationChart"></canvas>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Summons Table - CORRECTED -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Summon ID</th>
                                <th>Date & Time</th>
                                <th>Violation</th>
                                <th>Description</th>
                                <th>Student</th>
                                <th>Fine Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(!empty($summon_report_data)): ?>
                                <?php foreach($summon_report_data as $summon): ?>
                                <tr>
                                    <td><?php echo $summon['SummonID']; ?></td>
                                    <td><?php echo date('d M Y', strtotime($summon['SummonDate'])) . ' ' . $summon['SummonTime']; ?></td>
                                    <td><?php echo $summon['ViolationType']; ?></td>
                                    <td><?php echo $summon['SummonDescription']; ?></td>
                                    <td><?php echo $summon['StudentName']; ?></td>
                                    <td>RM <?php echo number_format($summon['FineAmount'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">No traffic summon data found for the selected period</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        <?php elseif ($report_type == 'user'): ?>
            <!-- USER ACTIVITY REPORT - CORRECTED -->
            <div class="report-card">
                <h3 class="report-title"><i class="fas fa-users"></i> User Activity Report</h3>
                
                <!-- User Statistics -->
                <div class="row mb-4">
                    <?php if(!empty($user_data)): ?>
                        <?php foreach($user_data as $user): ?>
                        <div class="col-md-6">
                            <div class="stat-card">
                                <div class="number"><?php echo $user['total_users']; ?></div>
                                <div class="label">Total <?php echo $user['user_type']; ?>s</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Top Users -->
                <div class="chart-container mb-4">
                    <div class="chart-title">Top 10 Users by Bookings</div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User Type</th>
                                    <th>User Name</th>
                                    <th>Total Bookings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(!empty($top_users_data)): ?>
                                    <?php foreach($top_users_data as $user): ?>
                                    <tr>
                                        <td><span class="badge <?php echo $user['user_type'] == 'Student' ? 'bg-info' : 'bg-warning'; ?>"><?php echo $user['user_type']; ?></span></td>
                                        <td><?php echo $user['user_name']; ?></td>
                                        <td><?php echo $user['total_bookings']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center">No user activity data found for the selected period</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Export Button -->
        <div class="mt-4 text-end">
            <button class="btn btn-success" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
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
            
            // Initialize Charts
            initializeCharts();
        });
        
        function initializeCharts() {
            // Summary Report Charts
            <?php if ($report_type == 'summary'): ?>
                // Booking Status Chart
                const bookingCtx = document.getElementById('bookingStatusChart');
                if (bookingCtx) {
                    new Chart(bookingCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Confirmed', 'Completed', 'Cancelled'],
                            datasets: [{
                                data: [
                                    <?php echo $booking_data['confirmed_bookings'] ?? 0; ?>,
                                    <?php echo $booking_data['completed_bookings'] ?? 0; ?>,
                                    <?php echo $booking_data['cancelled_bookings'] ?? 0; ?>
                                ],
                                backgroundColor: ['#28a745', '#17a2b8', '#dc3545'],
                                borderWidth: 2
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
                
                // User Distribution Chart
                const userCtx = document.getElementById('userDistributionChart');
                if (userCtx) {
                    new Chart(userCtx, {
                        type: 'pie',
                        data: {
                            labels: ['Students', 'Staff'],
                            datasets: [{
                                data: [
                                    <?php echo $total_students; ?>,
                                    <?php echo $total_staff; ?>
                                ],
                                backgroundColor: ['#6a22bd', '#28a745'],
                                borderWidth: 2
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
            <?php endif; ?>
            
            // Booking Report Chart
            <?php if ($report_type == 'booking' && !empty($booking_daily_labels)): ?>
                const dailyBookingsCtx = document.getElementById('dailyBookingsChart');
                if (dailyBookingsCtx) {
                    new Chart(dailyBookingsCtx, {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode($booking_daily_labels); ?>,
                            datasets: [{
                                label: 'Daily Bookings',
                                data: <?php echo json_encode($booking_daily_counts); ?>,
                                borderColor: '#6a22bd',
                                backgroundColor: 'rgba(106, 34, 189, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4
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
            <?php endif; ?>
            
            // Traffic Summon Chart
            <?php if ($report_type == 'summon' && !empty($violation_labels)): ?>
                const violationCtx = document.getElementById('violationChart');
                if (violationCtx) {
                    new Chart(violationCtx, {
                        type: 'bar',
                        data: {
                            labels: <?php echo json_encode($violation_labels); ?>,
                            datasets: [{
                                label: 'Number of Summons',
                                data: <?php echo json_encode($violation_counts); ?>,
                                backgroundColor: '#dc3545',
                                borderColor: '#c82333',
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
                            }
                        }
                    });
                }
            <?php endif; ?>
        }
    </script>
</body>
</html>