<?php
session_start();

// Single place to define login redirect header
define('LOGIN_REDIRECT', 'Location: Login.php');

/* ================= SESSION PROTECTION ================= */
if (!isset($_SESSION['user_id']) || ($_SESSION['type_user'] ?? '') !== 'student') {
    header(LOGIN_REDIRECT);
    exit();
}

/* ================= DB CONNECTION ================= */
$conn = new mysqli("localhost", "root", "", "fkparksystem", 3307);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$studentID = $_SESSION['user_id'];

// Get student data from database
$query = "SELECT * FROM student WHERE studentID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $studentID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    session_destroy();
    header(LOGIN_REDIRECT);
    exit();
}
$student = $result->fetch_assoc();

/* ================= FETCH LATEST MERIT SUMMARY ================= */
$meritStmt = $conn->prepare("
    SELECT MeritPoint, DemeritPoint, Date
    FROM StudentMerit
    WHERE StudentID = ?
    ORDER BY Date DESC
    LIMIT 1
");
$merit = $demerit = $total = 0;
$date = '-';
if ($meritStmt) {
    $meritStmt->bind_param("s", $studentID);
    $meritStmt->execute();
    $res = $meritStmt->get_result();
    if ($data = $res->fetch_assoc()) {
        $merit   = $data['MeritPoint'] ?? 0;
        $demerit = $data['DemeritPoint'] ?? 0;
        $total   = $merit - $demerit;
        $date    = $data['Date'] ?? '-';
    }
    $meritStmt->close();
}

/* ================= ENFORCEMENT LOGIC (TABLE A) ================= */
if ($total < 20) {
    $status = "Warning Given";
} elseif ($total < 50) {
    $status = "Vehicle Permission Revoked (1 Semester)";
} elseif ($total < 80) {
    $status = "Vehicle Permission Revoked (2 Semesters)";
} else {
    $status = "Vehicle Permission Revoked (Entire Study)";
}

/* ================= FETCH STUDENT'S SUMMONS ================= */
$summonList = [];
$listSql = "
    SELECT 
        ts.SummonID,
        ts.SummonDate,
        ts.SummonTime,
        ts.SummonDescription,
        ts.FineAmount,
        v.ViolationType,
        v.ViolationPoint,
        qr.Image_URL,
        qr.QR_Description
    FROM TrafficSummon ts
    LEFT JOIN Violation v ON ts.ViolationID = v.ViolationID
    LEFT JOIN QRCode qr ON ts.QRCodeID = qr.QRCodeID
    WHERE ts.StudentID = ?
    ORDER BY ts.SummonDate DESC, ts.SummonTime DESC
";
if ($stmt = $conn->prepare($listSql)) {
    $stmt->bind_param("s", $studentID);
    $stmt->execute();
    $listRes = $stmt->get_result();
    while ($r = $listRes->fetch_assoc()) {
        $summonList[] = $r;
    }
    $stmt->close();
}

/* Helper for safe output */
function e($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

// 60 seconds inactivity timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 60) {
    session_unset();
    session_destroy();
    header(LOGIN_REDIRECT);
    exit();
}
$_SESSION['last_activity'] = time();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Merit Status</title>
    <meta name="description" content="Merit Status">
    <meta name="author" content="Group1A3">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        /* Toggle Button */
        .toggle-btn {
            display: flex;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 1rem;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 4px;
            transition: all 0.3s ease;
            align-items: center;
            justify-content: center;
            gap: 8px;
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

        .content {
          background-color: white;
          padding: 25px;
          border-radius: 8px;
          margin-bottom: 25px;
          box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }

        .seccontent {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
        }

        h2 {
            color: #2d3748;
            font-size: 28px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        h3 {
            color: #2d3748;
            font-size: 22px;
            margin-bottom: 15px;
            font-weight: 600;
        }

        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        th, td { 
            padding: 14px 18px; 
            text-align: left; 
            vertical-align: middle; 
        }

        th { 
            background-color: #008080;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
        }

        tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        tr:hover {
            background-color: #e9ecef;
            transition: background-color 0.3s ease;
        }

        td {
            border-bottom: 1px solid #e9ecef;
            color: #4a5568;
            font-size: 14px;
        }

        .status { 
            margin-top: 20px; 
            padding: 15px 25px; 
            border-radius: 8px; 
            background-color: #fff3cd;
            color: #856404;
            font-weight: 600; 
            display: inline-block;
            font-size: 16px;
            border: 1px solid #ffeaa7;
        }

        .btn { 
            display: inline-block; 
            padding: 8px 16px; 
            border-radius: 6px; 
            background-color: #008080;
            color: #fff; 
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn:hover { 
            background-color: #006666;
        }

        .muted { 
            color: #a0aec0;
            text-align: center;
            padding: 40px;
            font-size: 16px;
        }

        .small { 
            font-size: 13px; 
            color: #718096; 
        }

        .summary-table th {
            background-color: #008080;
        }

        .summary-table td {
            font-size: 16px;
            font-weight: 600;
        }

        footer {
           background-color: #80cab1ff;
           color: white;
           padding: 15px 0;
           margin-top: 40px;
           text-align: center;
        }
        
        /* QR Code Modal Styles */
        .qr-code-container {
            text-align: center;
            padding: 20px;
        }
        
        .qr-image {
            max-width: 300px;
            height: auto;
            margin: 20px auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 10px;
            background: white;
        }
        
        .summon-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: bold;
            width: 120px;
            color: #555;
        }
        
        .detail-value {
            flex: 1;
            color: #333;
        }

        /* Responsive adjustments */
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
        }
    </style>
</head>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let timeout = 60;
let warningTime = 10;
let countdown;

function startTimer() {
    clearTimeout(countdown);
    
    countdown = setTimeout(() => {
        let stay = confirm("Your session will expire soon.\n\nClick OK to continue or Cancel to logout.");
        
        if (stay) {
            fetch("keep_alive.php").then(() => {
                startTimer();
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
    sidebar.classList.toggle('collapsed');
    mainContent.classList.toggle('collapsed');
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

// Function to show summon details modal
function showSummonDetails(summonId, summonData, qrImage, qrDescription) {
    // Fill modal with data
    document.getElementById('modalSummonId').textContent = summonData.summonId;
    document.getElementById('modalDate').textContent = summonData.date;
    document.getElementById('modalTime').textContent = summonData.time;
    document.getElementById('modalViolation').textContent = summonData.violation;
    document.getElementById('modalDescription').textContent = summonData.description;
    document.getElementById('modalFine').textContent = 'RM ' + summonData.fine;
    document.getElementById('modalPoints').textContent = summonData.points;
    
    // Set QR code image
    const qrImg = document.getElementById('qrCodeImage');
    if (qrImage) {
        qrImg.src = qrImage;
        qrImg.style.display = 'block';
        document.getElementById('qrDescription').textContent = qrDescription || 'QR Code for summon payment';
    } else {
        qrImg.style.display = 'none';
        document.getElementById('qrDescription').textContent = 'No QR Code available';
    }
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('summonDetailsModal'));
    modal.show();
}

// Extract summon data from table row
function getSummonData(row) {
    const cells = row.querySelectorAll('td');
    return {
        summonId: cells[0].textContent.trim(),
        date: cells[1].querySelector('br') ? cells[1].textContent.split('\n')[0].trim() : 'N/A',
        time: cells[1].querySelector('br') ? cells[1].querySelector('br').nextSibling.textContent.trim() : '',
        violation: cells[2].textContent.trim(),
        fine: cells[3].textContent.replace('RM ', '').trim(),
        description: row.getAttribute('data-description') || 'No description provided',
        points: row.getAttribute('data-points') || '0'
    };
}

// Add click event to View Details buttons
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.view-details-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const row = this.closest('tr');
            const summonId = this.getAttribute('data-summon-id');
            const qrImage = this.getAttribute('data-qr-image');
            const qrDescription = this.getAttribute('data-qr-description');
            const summonData = getSummonData(row);
            
            showSummonDetails(summonId, summonData, qrImage, qrDescription);
        });
    });
});
</script>
<body>
    <header class="header">
        <div class="header-left">
            <!-- Toggle button for sidebar -->
            <button class="toggle-btn" onclick="toggleSidebar()" aria-label="Toggle navigation">
                <i class="fas fa-bars"></i>Menu
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
                <a href="MeritStatus.php" class="menutext active">
                    <span class="menu-icon"><i class="fas fa-star"></i></span>
                    Merit status
                </a>
            </li>
        </ul>
    </nav>

    <div class="maincontent">
        <div class="content">
            <h2>🎓 My Merit Status</h2>

            <table class="summary-table">
                <tr>
                    <th>Merit Point</th>
                    <th>Demerit Point</th>
                    <th>Total Point</th>
                    <th>Last Updated</th>
                </tr>
                <tr>
                    <td><?= e($merit) ?></td>
                    <td><?= e($demerit) ?></td>
                    <td><?= e($total) ?></td>
                    <td><?= e($date) ?></td>
                </tr>
            </table>

            <div class="status">
                🚨 Enforcement Status: <strong><?= e($status) ?></strong>
            </div>
        </div>

        <div class="seccontent">
            <h3>📋 Your Traffic Summons</h3>

            <?php if (!empty($summonList)): ?>
                <table aria-label="List of your summons">
                    <thead>
                        <tr>
                            <th>Summon ID</th>
                            <th>Date & Time</th>
                            <th>Violation</th>
                            <th>Fine Amount</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summonList as $s): ?>
                            <tr data-description="<?= e($s['SummonDescription']) ?>" 
                                data-points="<?= e($s['ViolationPoint']) ?>">
                                <td><strong><?= e($s['SummonID']) ?></strong></td>
                                <td class="small">
                                    <?= $s['SummonDate'] ? e(date('d M Y', strtotime($s['SummonDate']))) : 'N/A' ?>
                                    <br>
                                    <?= $s['SummonTime'] ? e($s['SummonTime']) : '' ?>
                                </td>
                                <td>
                                    <strong><?= e($s['ViolationType'] ?? 'N/A') ?></strong>
                                    <?php if (!empty($s['ViolationType'])): ?>
                                        <br><span class="small"><?= e($s['ViolationType']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><strong>RM <?= e(number_format($s['FineAmount'] ?? 0, 2)) ?></strong></td>
                                <td>
                                    <button class="btn view-details-btn" 
                                            data-summon-id="<?= e($s['SummonID']) ?>"
                                            data-qr-image="<?= e($s['Image_URL'] ?? '') ?>"
                                            data-qr-description="<?= e($s['QR_Description'] ?? '') ?>">
                                        View Details
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="muted">✅ You have no summons recorded. Keep up the good work!</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summon Details Modal -->
    <div class="modal fade" id="summonDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-file-invoice"></i> Summon Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="qr-code-container">
                        <h4><i class="fas fa-qrcode"></i> Payment QR Code</h4>
                        <img id="qrCodeImage" class="qr-image" src="" alt="QR Code">
                        <p id="qrDescription" class="text-muted"></p>
                    </div>
                    
                    <div class="summon-details">
                        <h5><i class="fas fa-info-circle"></i> Summon Information</h5>
                        <div class="detail-row">
                            <div class="detail-label">Summon ID:</div>
                            <div class="detail-value" id="modalSummonId"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Date:</div>
                            <div class="detail-value" id="modalDate"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Time:</div>
                            <div class="detail-value" id="modalTime"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Violation:</div>
                            <div class="detail-value" id="modalViolation"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Description:</div>
                            <div class="detail-value" id="modalDescription"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Fine Amount:</div>
                            <div class="detail-value" id="modalFine"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Violation Points:</div>
                            <div class="detail-value" id="modalPoints"></div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>Payment Instructions:</strong> Scan the QR code above to pay the fine. 
                        Payment must be made within 14 days to avoid additional penalties.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p><i class="far fa-copyright"></i> 2025 FKPark System</p>
    </footer>
</body>
</html>
<?php
$conn->close();
?>