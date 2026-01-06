<?php
// Start the session
session_start();

// Single place to define login redirect header
define('LOGIN_REDIRECT', 'Location: Login.php');

// Database connection
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3306);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to logged-in student only
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'student') {
    header(LOGIN_REDIRECT);
    exit();
}

$student_id = $_SESSION['user_id'];
$success = "";
$error = "";

// Get student data for header
$student_query = "SELECT StudentName FROM student WHERE studentID = ?";
$student_stmt = $conn->prepare($student_query);
$student_stmt->bind_param("s", $student_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();
$student = $student_result->fetch_assoc();

// HANDLE VEHICLE REGISTRATION
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $vehicleID     = uniqid("V"); // auto-generate ID
    $vehicleType   = $_POST['vehicleType'];
    $plateNumber   = $_POST['plateNumber'];
    $vehicleModel  = $_POST['vehicleModel'];
    $vehicleColour = $_POST['vehicleColour'];

    // Handle vehicle grant upload
    $vehicleGrant = null;
    if (!empty($_FILES['vehicleGrant']['tmp_name'])) {
        $vehicleGrant = file_get_contents($_FILES['vehicleGrant']['tmp_name']);
    }

    $stmt = $conn->prepare("
        INSERT INTO Vehicle
        (VehicleID, StudentID, VehicleType, PlateNumber, VehicleModel, VehicleColour, VehicleGrant, VehicleApproval)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')
    ");

    $stmt->bind_param(
        "sssssss",
        $vehicleID,
        $student_id,
        $vehicleType,
        $plateNumber,
        $vehicleModel,
        $vehicleColour,
        $vehicleGrant
    );

    if ($stmt->execute()) {
        $success = "Vehicle registered successfully. Awaiting approval.";
    } else {
        $error = "Error registering vehicle.";
    }

    $stmt->close();
}

// FETCH STUDENT VEHICLES
$stmt = $conn->prepare("
    SELECT VehicleType, PlateNumber, VehicleModel, VehicleColour, VehicleApproval
    FROM Vehicle
    WHERE studentID = ?
");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();

// 20 seconds inactivity timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 60) {
    session_unset();
    session_destroy();
    header(LOGIN_REDIRECT);
    exit();
}

// Update activity time on every request
$_SESSION['last_activity'] = time();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Vehicle Registration</title>
    <meta name="description" content="Vehicle Registration">
    <meta name="author" content="Group1A3">
    <!-- Include FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* KEEP ORIGINAL VEHICLE REGISTRATION STYLES */
        body {
            font-family: Arial;
            background: #f4f6f8;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 800px;
            margin: auto;
            background: #fff;
            padding: 20px;
        }
        h2 {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: center;
        }
        th {
            background: #eee;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }

        /* ADD ONLY THE HEADER AND SIDEBAR STYLES FROM STUDENTDASHBOARD */
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
            top: 0;
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

        .sidebar.collapsed {
            transform: translateX(-100%);
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
            background-color: rgba(46, 204, 113, 0.3);
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

        .logoutbutton:hover {
            background-color: rgba(255, 0, 0, 0.3);
        }

        /* Adjust maincontent to account for sidebar */
        .maincontent {
            margin-left: 250px;
            margin-top: 120px;
            padding: 40px;
            box-sizing: border-box;
            transition: margin-left 0.3s ease;
            min-height: calc(100vh - 120px);
        }

        .maincontent.collapsed {
            margin-left: 0;
        }

        footer {
            background-color: #80cab1;
            color: white;
            padding: 15px 0;
            text-align: center;
            margin-top: 40px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .header {
                height: 100px;
                padding: 0 10px;
            }
            
            .sidebar {
                top: 100px;
                width: 280px;
                transform: translateX(-100%);
            }
            
            .sidebar:not(.collapsed) {
                transform: translateX(0);
            }
            
            .maincontent {
                margin-top: 100px;
                padding: 20px;
                margin-left: 0;
            }
            
            .maincontent.collapsed {
                margin-left: 0;
            }
            
            .logo img {
                height: 70px;
            }
            
            .container {
                width: 95%;
                padding: 15px;
            }
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
            <a href="StudentDashboard.php" class="menutext">
                <span class="menu-icon"><i class="fas fa-tachometer-alt"></i></span>
                Dashboard
            </a>
        </li>
        <li>
            <a href="VehicleRegistration.php" class="menutext active">
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

<!-- MAIN CONTENT STAYS EXACTLY THE SAME AS ORIGINAL -->
<div class="maincontent">
    <div class="container">
        <h2>Register Vehicle</h2>

        <?php if (isset($success)) echo "<p class='success'>$success</p>"; ?>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>

        <form method="POST" enctype="multipart/form-data">
            <label>Vehicle Type</label><br>
            <select name="vehicleType" required>
                <option value="">-- Select --</option>
                <option value="Car">Car</option>
                <option value="Motorcycle">Motorcycle</option>
            </select><br><br>

            <label>Plate Number</label><br>
            <input type="text" name="plateNumber" required><br><br>

            <label>Vehicle Model</label><br>
            <input type="text" name="vehicleModel" required><br><br>

            <label>Vehicle Colour</label><br>
            <input type="text" name="vehicleColour" required><br><br>

            <label>Vehicle Grant (PDF/Image)</label><br>
            <input type="file" name="vehicleGrant" accept=".pdf,image/*" required><br><br>

            <button type="submit">Register Vehicle</button>
        </form>

        <h2>My Registered Vehicles</h2>

        <table>
            <tr>
                <th>Type</th>
                <th>Plate</th>
                <th>Model</th>
                <th>Colour</th>
                <th>Status</th>
            </tr>

            <?php while ($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?= htmlspecialchars($row['VehicleType']) ?></td>
                    <td><?= htmlspecialchars($row['PlateNumber']) ?></td>
                    <td><?= htmlspecialchars($row['VehicleModel']) ?></td>
                    <td><?= htmlspecialchars($row['VehicleColour']) ?></td>
                    <td><?= htmlspecialchars($row['VehicleApproval']) ?></td>
                </tr>
            <?php } ?>
        </table>
    </div>
</div>

<footer>
    <center><p><i class="far fa-copyright"></i> 2025 FKPark System</p></center>
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