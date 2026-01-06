<?php
session_start();

// DB connection
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3306);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$bookingID = $_GET['booking_id'] ?? null;
if (!$bookingID) {
    die("Invalid booking.");
}

// Get booking info
$stmt = $conn->prepare("
    SELECT b.BookingID, ps.SpaceNumber, pa.AreaType, b.BookingDate, b.BookingTime
    FROM booking b
    JOIN parkingspace ps ON ps.ParkingSpaceID = ps.ParkingSpaceID
    JOIN parkingarea pa ON ps.ParkingAreaID = pa.ParkingAreaID
    WHERE b.BookingID = ?
");
$stmt->bind_param("s", $bookingID);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

// QR content
$qrData = "Booking ID: {$data['BookingID']}
Area: {$data['AreaType']}
Space: {$data['SpaceNumber']}
Date: {$data['BookingDate']}
Time: {$data['BookingTime']}";

// Create QR image
//$qrFile = "qrcodes/booking_" . $bookingID . ".png";
//QRcode::png($qrData, $qrFile, QR_ECLEVEL_L, 5);
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
    <div class="content" style="text-align:center;">
        <h2>Booking Successful!</h2>
        <p>Scan this QR Code at the parking space</p>

        <img src="<?= $qrFile ?>" alt="Booking QR Code" width="250">

        <p><strong>Area:</strong> <?= $data['AreaType'] ?></p>
        <p><strong>Space:</strong> <?= $data['SpaceNumber'] ?></p>
        <p><strong>Date:</strong> <?= $data['BookingDate'] ?></p>
        <p><strong>Time:</strong> <?= $data['BookingTime'] ?></p>
    </div>
    </div>


    <!-- FOOTER -->
    <footer>
        <center><p> © 2025 FKPark System</p></center>
    </footer>

</body>
</html>