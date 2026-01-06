<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3306);

// Check if database connection failed
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

// Fetch parking areas
$areaResult = $conn->query("SELECT * FROM ParkingArea");

// Search logic
$spaces = [];
if (isset($_GET['search'])) {

    $area = $_GET['area'];
    $date = $_GET['date'];
    $time = $_GET['time'];

    $stmt = $conn->prepare("
        SELECT * FROM ParkingSpace
        WHERE ParkingAreaID = ?
        AND ParkingSpaceID NOT IN (
            SELECT ParkingSpaceID FROM Booking
            WHERE BookingDate = ? AND BookingTime = ?
        )
    ");

    // FIXED: all are strings
    $stmt->bind_param("sss", $area, $date, $time);
    $stmt->execute();
    $spaces = $stmt->get_result();
}

?>

<!DOCTYPE html>
<html>
    <head>
        <title>BookingDashboard</title>
        <meta name="desription" content="BookingDashboard">
        <meta name="author" content="Group1A3">
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

            .header-title{
                color: white;
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

            .table{
                border: 1px solid black;
                border-collapse: collapse;
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
               margin-top: 100px;
               padding: 40px;
               box-sizing: border-box;
            }

            .content {
              background-color: white;
              padding: 15px;
              border-radius: 8px;
              margin-bottom: 15px;
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
                align-items: center;
                text-align: center;
                background: #ffffffff;
                color: #130358ff;
                border-radius: 10px;
                padding: 0.8em 0.7em 0.8em 0.7em;
                min-width: 140px;
                min-height: 74px;
                box-shadow: 0 2px 9px rgba(0, 0, 0, 0.09);
                flex: 1 1 160px;
            }

            .card {
                background: #b2e9e9ff;
                padding: 50px;
                border: 1px solid #ccc;
                width: 180px;
                text-align: center;
                border-radius: 5px;
                font-weight: bold;
            }

            /* Charts */
            .charts {
               display: flex;
               gap: 20px;
            }

            .chart {
              flex: 1;
              background: white;
              padding: 30px;
              border: 1px solid #ccc;
              height: 200px;
              text-align: center;
              border-radius: 5px;
              font-weight: bold;
            }

            footer {
               background-color: #80cab1ff;
               color: white;
               padding: 15px 0;
            }

            .available-space-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            }

            .available-space-table th,
            .available-space-table td {
                border: 1px solid black;
                padding: 12px;
                text-align: center;
            }

            .available-space-table th {
                background-color: #e6f2f2;
                font-weight: bold;
            }
            .toggle-btn {
                display: flex;
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

            .toggle-btn:hover {
                background: rgba(255, 255, 255, 0.3);
            }

        </style>
    </head>
    <body>
        <header class="header">
                <div class="header-left">
            <button class="toggle-btn" onclick="toggleSidebar()" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>
            </button>
                <div class="logo">
                    <img src="UMPLogo.png" alt="UMPLogo">
                </div>
            </div>
            <div class="header-right">
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
                    <a href="StudentDashboard.php" class="menutext">Dashboard</a>
                </li>
                <li>
                    <a href="VehicleRegistration.php" class="menutext">Vehicle Registration</a>
                </li>
                <li>
                    <a href="BookingDashboard.php" class="menutext active">Book Parking</a>
                </li>
                <li>
                    <a href="MeritStatus.php" class="menutext">Merit status</a>
                </li>
            </ul>
        </nav>

        <div class="maincontent">
            <div class="content">
                <center><h2>Book Parking</h2></center>

                <form method="GET">
                    <table border="1" cellpadding="10" width="100%">
                        <tr>
                            <!-- MAP / IMAGE -->
                            <th rowspan="3" style="width:30%;">
                                <!-- Map placeholder -->
                                <img src="Park.jpeg" alt="Parking Map" width="100%">
                            </th>

                            <!-- AREA -->
                            <td>
                                <label for="area">Select Areas:</label><br>
                                <select name="area" required>
                                    <option value="">-- Select Area --</option>
                                    <?php while ($row = $areaResult->fetch_assoc()): ?>
                                        <option value="<?= $row['ParkingAreaID'] ?>">
                                            <?= $row['AreaType'] ?> (<?= $row['AreaNumber'] ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <!-- DATE -->
                            <td>
                                <label>Select Date:</label><br>
                                <input type="date" name="date" required>
                            </td>
                        </tr>

                        <tr>
                            <!-- TIME -->
                            <td>
                                <label>Select Time:</label><br>
                                <input type="time" name="time" required>
                            </td>
                        </tr>

                        <tr>
                            <td colspan="2" align="right">
                                <button type="submit" name="search">Search</button>
                            </td>
                        </tr>
                    </table>
                </form>

            </div>

            <?php if (isset($_GET['search'])): ?>
            <div class="seccontent">
                <h3>Available Space</h3>

                <table class="available-space-table">
                    <tr>
                        <th>Space No</th>
                        <th>Status</th>
                        <th>Type</th>
                        <th>Action</th>
                    </tr>

                    <?php if ($spaces->num_rows > 0): ?>
                    <?php while ($row = $spaces->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['SpaceNumber'] ?></td>
                        <td>Available</td>
                        <td><?= $row['SpaceType'] ?></td>
                        <td>
                            <a href="BookingConfirm.php?space=<?= $row['ParkingSpaceID'] ?>&date=<?= $_GET['date'] ?>&time=<?= $_GET['time'] ?>"><button>Book</button></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">No available parking space</td>
                        </tr>
                    <?php endif; ?>

                </table>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <footer>
            <center><p> © 2025 FKPark System</p></center>
        </footer>
        <script>
// Toggle sidebar function - MATCHING StudentDashboard.php
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.maincontent');
    
    sidebar.classList.toggle('collapsed');
    mainContent.classList.toggle('collapsed');
}

// Close sidebar when clicking on a menu item (mobile)
document.querySelectorAll('.menutext').forEach(item => {
    item.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.maincontent');
            
            if (!sidebar.classList.contains('collapsed')) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('collapsed');
            }
        }
    });
});

// Restart timer on user activity for session timeout
let timeout = 60;
let warningTime = 10;
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
</script>
</body>
</html>

<?php
$stmt->close();
$student_stmt->close();
$conn->close();
?>