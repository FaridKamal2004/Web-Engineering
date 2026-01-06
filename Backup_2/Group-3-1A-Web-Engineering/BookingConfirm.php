<?php
session_start();

// DB connection
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3306);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// User must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: Login.php");
    exit();
}

$studentID = $_SESSION['user_id'];
$spaceID = $_GET['space'] ?? null;
$date = $_GET['date'] ?? null;
$time = $_GET['time'] ?? null;

// Fetch space + area info
$spaceData = [];
if ($spaceID) {
    $stmt = $conn->prepare("
        SELECT ps.SpaceNumber, pa.AreaType
        FROM ParkingSpace ps
        JOIN ParkingArea pa ON ps.ParkingAreaID = pa.ParkingAreaID
        WHERE ps.ParkingSpaceID = ?
    ");
    $stmt->bind_param("s", $spaceID);
    $stmt->execute();
    $spaceData = $stmt->get_result()->fetch_assoc();
}

// CONFIRM BOOKING
if (isset($_POST['confirm'])) {

    // Generate BookingID (example: B007)
    $result = $conn->query("SELECT COUNT(*) AS total FROM Booking");
    $count = $result->fetch_assoc()['total'] + 1;
    $bookingID = "B" . str_pad($count, 3, "0", STR_PAD_LEFT);

    $stmt = $conn->prepare("
    INSERT INTO booking 
    (BookingID, StudentID, ParkingSpaceID, BookingDate, BookingTime, BookingStatus)
    VALUES (?, ?, ?, ?, ?, 'Confirmed')
");

    $bookingID = uniqid("B"); // e.g. B65fa1c3
    $studentID = $_SESSION['StudentID'];

    $stmt->bind_param("sssss", $bookingID, $studentID, $spaceID, $date, $time);
    $stmt->execute();

    header("Location: BookingQRCode.php?booking_id=" . $bookingID);
    exit();
}

// CANCEL
if (isset($_POST['cancel'])) {
    header("Location: BookingDashboard.php");
exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Confirmation Booking</title>
    <meta charset="UTF-8">

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
        }

        /* HEADER */
        .header {
            background-color: #008080;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            color: white;
            position: fixed;
            width: 100%;
            top: 0;
        }

        .header_title{
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
            padding-right: 50px;
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

        /* SIDEBAR */
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

        /* MAIN CONTENT */
        .main {
            margin-left: 250px;
            margin-top: 120px;
            padding: 40px;
        }

        .content {
            background-color: white;
            padding: 40px;
            border-radius: 8px;
        }

        h2 {
            margin-bottom: 30px;
        }

        /* CONFIRMATION DETAILS */
        .details p {
            font-size: 18px;
            margin: 15px 0;
        }

        /* BUTTONS */
        .buttons {
            margin-top: 40px;
            display: flex;
            gap: 20px;
        }

        .buttons button {
            padding: 12px 30px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .confirm {
            background-color: #008080;
            color: white;
        }

        .cancel {
            background-color: #ccc;
        }

        /* FOOTER */
        footer {
            background-color: #80cab1ff;
            color: white;
            padding: 15px 0;
        }
    </style>
</head>

<body>

    <!-- HEADER -->
    <header class="header">
            <div class="header_left">
                <div class="logo">
                <img src="UMPLogo.png" alt="UMPLogo">
                </div>
            </div>
            <div class="header_title">
                <center><h1>Confirmation Booking</h1><center>
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

    <!-- SIDEBAR -->
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
                    <a href="BookingDashboard.php" class="menutext">Book Parking</a>
                </li>
                <li>
                    <a href="DemeritStatus.php" class="menutext">Demerit status</a>
                </li>
            </ul>
        </nav>

    <!-- MAIN CONTENT -->
    <div class="main">
        <div class="content">
            <h2>Confirmation Booking Details</h2>

            <div class="details">
                <p><strong>Area:</strong> <?= $spaceData['AreaType'] ?? '-' ?></p>
                <p><strong>Space No:</strong> <?= $spaceData['SpaceNumber'] ?? '-' ?></p>
                <p><strong>Date:</strong> <?= $date ?></p>
                <p><strong>Time:</strong> <?= $time ?></p>
            </div>

            <form method="POST" class="buttons">
                <button type="submit" name="confirm" class="confirm">Confirm Booking</button>
                <button type="submit" name="cancel" class="cancel">Cancel</button>
            </form>

        </div>
    </div>

    <!-- FOOTER -->
    <footer>
        <center><p> © 2025 FKPark System</p></center>
    </footer>

</body>
</html>