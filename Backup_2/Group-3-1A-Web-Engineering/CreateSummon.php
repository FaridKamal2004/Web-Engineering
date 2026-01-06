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

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $studentID = $_POST['studentID'];
    $violationID = $_POST['violationID'];
    $summonDate = $_POST['summonDate'];
    $summonTime = $_POST['summonTime'];
    $summonDesc = $_POST['summonDesc'];
    $fineAmount = $_POST['fineAmount'];

    $checkStudent = $conn->query("SELECT * FROM Student WHERE StudentID = '$studentID'");
    
    if ($checkStudent->num_rows == 0) {
        $error = "Student ID not found.";
    } else {
        // Get points based on violation
        $vResult = $conn->query("SELECT ViolationPoint FROM Violation WHERE ViolationID = $violationID");
        $vRow = $vResult->fetch_assoc();
        $pointsToAdd = intval($vRow['ViolationPoint']);
        // Check current points for the student from StudentMerit
        $mResult = $conn->query("SELECT * FROM StudentMerit WHERE StudentID = '$studentID'");
        $currentDemerit = 0; 
        
        if ($mResult->num_rows > 0) {
            $mRow = $mResult->fetch_assoc();
            $currentDemerit = intval($mRow['DemeritPoint']);
        }

        // Calculate new total
        $newDemerit = $currentDemerit + $pointsToAdd;
        $enforcementStatus = "None";
        if ($newDemerit < 20) {
            $enforcementStatus = "Warning given";
        } elseif ($newDemerit < 50) {
            $enforcementStatus = "Revoke of in campus vehicle permission for 1 semester";
        } elseif ($newDemerit < 80) {
            $enforcementStatus = "Revoke of in campus vehicle permission for 2 semesters";
        } else {
            $enforcementStatus = "Revoke of in campus vehicle permission for the entire study duration";
        }

        // 2. Insert Placeholder QRCode
        $insertQR = "INSERT INTO QRCode (Image_URL, QR_Description) VALUES ('Placeholder', 'Pending')";
        if ($conn->query($insertQR) === TRUE) {
            $newQRCodeID = $conn->insert_id;
            // 3. Insert Summon WITH SNAPSHOT of Points and Status
            $insertSummon = "INSERT INTO TrafficSummon (StudentID, ViolationID, QRCodeID, SummonDescription, SummonDate, SummonTime, FineAmount, DemeritPointSnapshot, EnforcementStatusSnapshot) 
                             VALUES ('$studentID', $violationID, $newQRCodeID, '$summonDesc', '$summonDate', '$summonTime', '$fineAmount', '$newDemerit', '$enforcementStatus')";
            
            if ($conn->query($insertSummon) === TRUE) {
                $newSummonID = $conn->insert_id;

                // 4. Update QR Code details
                $targetUrl = "http://localhost/fkparksystem/StudentViewSummon.php?summonID=" . $newSummonID;
                $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($targetUrl);
                $qrDesc = "QR code for Summon " . $newSummonID;
                
                $updateQR = "UPDATE QRCode SET Image_URL = '$qrUrl', QR_Description = '$qrDesc' WHERE QRCodeID = $newQRCodeID";
                $conn->query($updateQR);

                // 5. Update StudentMerit (Live Table)
                if ($mResult->num_rows > 0) {
                    $updateMerit = "UPDATE StudentMerit 
                                    SET DemeritPoint = $newDemerit, 
                                        EnforcementStatus = '$enforcementStatus', 
                                        Date = '$summonDate' 
                                    WHERE StudentID = '$studentID'";
                    $conn->query($updateMerit);
                } else {
                    $insertMerit = "INSERT INTO StudentMerit (StudentID, DemeritPoint, EnforcementStatus, Date) 
                                    VALUES ('$studentID', $newDemerit, '$enforcementStatus', '$summonDate')";
                    $conn->query($insertMerit);
                }

                $message = "Summon ID $newSummonID created successfully.";
            } else {
                $error = "Error creating Summon: " . $conn->error;
            }
        } else {
            $error = "Error creating QR Code: " . $conn->error;
        }
    }
}

$violations = $conn->query("SELECT * FROM Violation");

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
    <title>FK Park System - Create Traffic Summon</title>
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
        
        /* Form Specific Styles */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #666;
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 0.95rem;
            transition: color 0.3s;
        }
        
        .back-link:hover {
            color: #333;
        }
        
        .form-card {
            background-color: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            max-width: 800px;
            margin: 0 auto;
        }

        .form-header {
            text-align: center;
            margin-bottom: 35px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eb9d43ff;
        }

        .form-header h2 {
            color: #333;
            font-size: 1.8rem;
            margin: 0;
            font-weight: 600;
        }

        .form-header p {
            color: #666;
            margin-top: 10px;
            font-size: 0.95rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }

        .form-group-full {
            grid-column: 1 / -1;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 0.95rem;
        }

        .form-group label i {
            color: #eb9d43ff;
            margin-right: 5px;
        }

        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-group input:focus, 
        .form-group select:focus, 
        .form-group textarea:focus {
            outline: none;
            border-color: #eb9d43ff;
            box-shadow: 0 0 0 3px rgba(235, 157, 67, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 35px;
            padding-top: 25px;
            border-top: 1px solid #e0e0e0;
        }

        .btn-submit {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 6px;
            cursor: pointer;
            flex: 1;
            font-size: 1.05rem;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-submit:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        .btn-cancel {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1.05rem;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-cancel:hover {
            background-color: #5a6268;
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 25px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
        }
        
        .alert i {
            font-size: 1.2rem;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .required {
            color: #dc3545;
            margin-left: 3px;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-card {
                padding: 25px;
            }

            .form-actions {
                flex-direction: column;
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

        <div class="form-card">
            <div class="form-header">
                <h2><i class="fas fa-file-invoice"></i> Issue New Traffic Summon</h2>
                <p>Complete the form below to create a new traffic violation summon</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <form action="CreateSummon.php" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="studentID">
                            <i class="fas fa-id-card"></i> Student ID<span class="required">*</span>
                        </label>
                        <input type="text" id="studentID" name="studentID" placeholder="e.g., CB23067" required>
                    </div>

                    <div class="form-group">
                        <label for="violationID">
                            <i class="fas fa-exclamation-triangle"></i> Violation Type<span class="required">*</span>
                        </label>
                        <select id="violationID" name="violationID" required>
                            <option value="">-- Select Violation --</option>
                            <?php while($v = $violations->fetch_assoc()): ?>
                                <option value="<?php echo $v['ViolationID']; ?>">
                                    <?php echo $v['ViolationType'] . " (" . $v['ViolationPoint'] . " Points)"; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fineAmount">
                            <i class="fas fa-money-bill-wave"></i> Fine Amount (RM)<span class="required">*</span>
                        </label>
                        <input type="number" id="fineAmount" name="fineAmount" step="0.01" placeholder="e.g., 50.00" required>
                    </div>

                    <div class="form-group">
                        <label for="summonDate">
                            <i class="fas fa-calendar-day"></i> Date<span class="required">*</span>
                        </label>
                        <input type="date" id="summonDate" name="summonDate" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group form-group-full">
                        <label for="summonTime">
                            <i class="fas fa-clock"></i> Time<span class="required">*</span>
                        </label>
                        <input type="time" id="summonTime" name="summonTime" value="<?php echo date('H:i'); ?>" required>
                    </div>

                    <div class="form-group form-group-full">
                        <label for="summonDesc">
                            <i class="fas fa-file-alt"></i> Violation Description<span class="required">*</span>
                        </label>
                        <textarea id="summonDesc" name="summonDesc" placeholder="Enter detailed description of the violation..." required></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="TrafficSummon.php" class="btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Issue Summon
                    </button>
                </div>
            </form>
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

        // Auto-dismiss success message after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(function() {
                    successAlert.style.opacity = '0';
                    setTimeout(function() {
                        successAlert.style.display = 'none';
                    }, 300);
                }, 5000);
            }
        });
    </script>
</body>
</html>