<?php
// Start the session
session_start();

// Database connection parameters
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3306);

// Check if database connection failed
if ($conn->connect_error) {
    // For AJAX requests, return JSON error
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    } else {
        die("Connection failed: " . $conn->connect_error);
    }
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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's an AJAX request
    $isAjax = isset($_POST['ajax']);
    
    if ($isAjax) {
        // Set header to JSON
        header('Content-Type: application/json');
        
        // Function to send consistent JSON response
        function sendJsonResponse($success, $message, $area_id = null) {
            $response = ['success' => $success];
            if ($success) {
                $response['message'] = $message;
                if ($area_id) {
                    $response['area_id'] = $area_id;
                }
            } else {
                $response['error'] = $message;
            }
            echo json_encode($response);
            exit;
        }
        
        if ($_POST['ajax'] === 'add_area') {
            // Handle add area
            $areaType = $_POST['area_type'] ?? '';
            $areaNumber = $_POST['area_number'] ?? '';
            $totalSpaces = (int)($_POST['total_spaces'] ?? 0);
            
            if (empty($areaType) || empty($areaNumber) || $totalSpaces < 1) {
                sendJsonResponse(false, 'Please fill all required fields');
            }
            
            try {
                // Start transaction
                $conn->begin_transaction();
                
                // 1. Generate ParkingAreaID
                $result = $conn->query("SELECT ParkingAreaID FROM ParkingArea ORDER BY ParkingAreaID DESC LIMIT 1");
                
                $parkingAreaID = 'PA001'; // Default
                
                if ($result && $result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $lastId = $row['ParkingAreaID'];
                    
                    // Extract number from PA001 format
                    if (preg_match('/PA(\d+)/', $lastId, $matches)) {
                        $nextId = intval($matches[1]) + 1;
                    } else {
                        $nextId = 1;
                    }
                    $parkingAreaID = 'PA' . str_pad($nextId, 2, '0', STR_PAD_LEFT);
                }
                
                // Check if AreaNumber already exists
                $checkStmt = $conn->prepare("SELECT ParkingAreaID FROM ParkingArea WHERE AreaNumber = ?");
                $checkStmt->bind_param("s", $areaNumber);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows > 0) {
                    throw new Exception("Parking Area '{$areaNumber}' already exists!");
                }
                
                // 2. Insert ParkingArea
                $stmt = $conn->prepare("INSERT INTO ParkingArea (ParkingAreaID, AreaType, AreaNumber, TotalSpaces) VALUES (?, ?, ?, ?)");
                
                if (!$stmt) {
                    throw new Exception("Failed to prepare statement: " . $conn->error);
                }
                
                $stmt->bind_param("sssi", $parkingAreaID, $areaType, $areaNumber, $totalSpaces);
                
                if (!$stmt->execute()) {
                    if ($conn->errno == 1062) {
                        throw new Exception("Parking Area ID '{$parkingAreaID}' already exists!");
                    }
                    throw new Exception("Failed to insert parking area: " . $stmt->error);
                }
                
                // 3. Get next ParkingSpaceID
                $spaceResult = $conn->query("SELECT ParkingSpaceID FROM ParkingSpace ORDER BY ParkingSpaceID DESC LIMIT 1");
                $nextSpaceId = 1;
                
                if ($spaceResult && $spaceResult->num_rows > 0) {
                    $spaceRow = $spaceResult->fetch_assoc();
                    $lastSpaceId = $spaceRow['ParkingSpaceID'];
                    
                    if (preg_match('/PS(\d+)/', $lastSpaceId, $matches)) {
                        $nextSpaceId = intval($matches[1]) + 1;
                    }
                }
                
                // 4. Generate Parking Spaces
                for ($i = 1; $i <= $totalSpaces; $i++) {
                    $parkingSpaceID = 'PS' . str_pad($nextSpaceId, 2, '0', STR_PAD_LEFT);
                    $spaceNumber = $areaNumber . '-' . $i;
                    
                    // Insert ParkingSpace
                    $spaceStmt = $conn->prepare("INSERT INTO ParkingSpace (ParkingSpaceID, ParkingAreaID, SpaceNumber, SpaceType) VALUES (?, ?, ?, ?)");
                    
                    if (!$spaceStmt) {
                        throw new Exception("Failed to prepare space statement: " . $conn->error);
                    }
                    
                    $spaceStmt->bind_param("ssss", $parkingSpaceID, $parkingAreaID, $spaceNumber, $areaType);
                    
                    if (!$spaceStmt->execute()) {
                        if ($conn->errno == 1062) {
                            throw new Exception("Parking Space ID '{$parkingSpaceID}' already exists!");
                        }
                        throw new Exception("Failed to insert parking space: " . $spaceStmt->error);
                    }
                    
                    // Initialize ParkingStatus
                    $statusStmt = $conn->prepare("INSERT INTO ParkingStatus (ParkingSpaceID, ParkingAreaID, SpaceStatus, DateStatus) VALUES (?, ?, 'Available', CURDATE())");
                    
                    if (!$statusStmt) {
                        throw new Exception("Failed to prepare status statement: " . $conn->error);
                    }
                    
                    $statusStmt->bind_param("ss", $parkingSpaceID, $parkingAreaID);
                    
                    if (!$statusStmt->execute()) {
                        throw new Exception("Failed to insert parking status: " . $statusStmt->error);
                    }
                    
                    $nextSpaceId++; // Increment for next space
                }
                
                $conn->commit();
                
                sendJsonResponse(true, "Parking Area '{$areaNumber}' with {$totalSpaces} spaces created successfully!", $parkingAreaID);
                
            } catch (Exception $e) {
                $conn->rollback();
                sendJsonResponse(false, $e->getMessage());
            }
            
        } elseif ($_POST['ajax'] === 'edit_area') {
            // Handle edit area
            $areaId = $_POST['area_id'] ?? '';
            $areaType = $_POST['area_type'] ?? '';
            $areaNumber = $_POST['area_number'] ?? '';
            $newTotalSpaces = (int)($_POST['total_spaces'] ?? 0);
            
            if (empty($areaId) || empty($areaType) || empty($areaNumber) || $newTotalSpaces < 1) {
                sendJsonResponse(false, 'Please fill all required fields');
            }
            
            try {
                // Start transaction
                $conn->begin_transaction();
                
                // 1. Get current area information
                $stmt = $conn->prepare("SELECT AreaType, AreaNumber, TotalSpaces FROM ParkingArea WHERE ParkingAreaID = ?");
                if (!$stmt) {
                    throw new Exception("Failed to prepare statement: " . $conn->error);
                }
                $stmt->bind_param("s", $areaId);
                $stmt->execute();
                $result = $stmt->get_result();
                $currentArea = $result->fetch_assoc();
                
                if (!$currentArea) {
                    throw new Exception("Parking area not found!");
                }
                
                $currentTotalSpaces = $currentArea['TotalSpaces'];
                
                // Check if AreaNumber already exists (excluding current area)
                $checkStmt = $conn->prepare("SELECT ParkingAreaID FROM ParkingArea WHERE AreaNumber = ? AND ParkingAreaID != ?");
                if (!$checkStmt) {
                    throw new Exception("Failed to prepare check statement: " . $conn->error);
                }
                $checkStmt->bind_param("ss", $areaNumber, $areaId);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows > 0) {
                    throw new Exception("Parking Area '{$areaNumber}' already exists!");
                }
                
                // 2. Update ParkingArea information
                $updateStmt = $conn->prepare("UPDATE ParkingArea SET AreaType = ?, AreaNumber = ?, TotalSpaces = ? WHERE ParkingAreaID = ?");
                if (!$updateStmt) {
                    throw new Exception("Failed to prepare update statement: " . $conn->error);
                }
                $updateStmt->bind_param("ssis", $areaType, $areaNumber, $newTotalSpaces, $areaId);
                
                if (!$updateStmt->execute()) {
                    throw new Exception("Failed to update parking area: " . $updateStmt->error);
                }
                
                // 3. Check if we need to add or remove spaces
                if ($newTotalSpaces > $currentTotalSpaces) {
                    // Need to add more spaces
                    $spacesToAdd = $newTotalSpaces - $currentTotalSpaces;
                    
                    // Get next ParkingSpaceID
                    $spaceResult = $conn->query("SELECT ParkingSpaceID FROM ParkingSpace ORDER BY ParkingSpaceID DESC LIMIT 1");
                    $nextSpaceId = 1;
                    
                    if ($spaceResult && $spaceResult->num_rows > 0) {
                        $spaceRow = $spaceResult->fetch_assoc();
                        $lastSpaceId = $spaceRow['ParkingSpaceID'];
                        
                        if (preg_match('/PS(\d+)/', $lastSpaceId, $matches)) {
                            $nextSpaceId = intval($matches[1]) + 1;
                        }
                    }
                    
                    // Add new spaces
                    for ($i = 1; $i <= $spacesToAdd; $i++) {
                        $spaceIndex = $currentTotalSpaces + $i;
                        $parkingSpaceID = 'PS' . str_pad($nextSpaceId, 2, '0', STR_PAD_LEFT);
                        $spaceNumber = $areaNumber . '-' . $spaceIndex;
                        
                        // Insert ParkingSpace
                        $spaceStmt = $conn->prepare("INSERT INTO ParkingSpace (ParkingSpaceID, ParkingAreaID, SpaceNumber, SpaceType) VALUES (?, ?, ?, ?)");
                        if (!$spaceStmt) {
                            throw new Exception("Failed to prepare space insert statement: " . $conn->error);
                        }
                        $spaceStmt->bind_param("ssss", $parkingSpaceID, $areaId, $spaceNumber, $areaType);
                        
                        if (!$spaceStmt->execute()) {
                            throw new Exception("Failed to add new parking space: " . $spaceStmt->error);
                        }
                        
                        // Initialize ParkingStatus
                        $statusStmt = $conn->prepare("INSERT INTO ParkingStatus (ParkingSpaceID, ParkingAreaID, SpaceStatus, DateStatus) VALUES (?, ?, 'Available', CURDATE())");
                        if (!$statusStmt) {
                            throw new Exception("Failed to prepare status statement: " . $conn->error);
                        }
                        $statusStmt->bind_param("ss", $parkingSpaceID, $areaId);
                        
                        if (!$statusStmt->execute()) {
                            throw new Exception("Failed to add parking status: " . $statusStmt->error);
                        }
                        
                        $nextSpaceId++;
                    }
                    
                } elseif ($newTotalSpaces < $currentTotalSpaces) {
                    // Need to remove spaces (remove from the end)
                    $spacesToRemove = $currentTotalSpaces - $newTotalSpaces;
                    
                    // Get the last X spaces to remove
                    $getSpacesStmt = $conn->prepare("
                        SELECT ParkingSpaceID 
                        FROM ParkingSpace 
                        WHERE ParkingAreaID = ? 
                        ORDER BY CAST(SUBSTRING_INDEX(SpaceNumber, '-', -1) AS UNSIGNED) DESC 
                        LIMIT ?
                    ");
                    if (!$getSpacesStmt) {
                        throw new Exception("Failed to prepare get spaces statement: " . $conn->error);
                    }
                    $getSpacesStmt->bind_param("si", $areaId, $spacesToRemove);
                    $getSpacesStmt->execute();
                    $spacesResult = $getSpacesStmt->get_result();
                    
                    while ($spaceRow = $spacesResult->fetch_assoc()) {
                        $spaceId = $spaceRow['ParkingSpaceID'];
                        
                        // Delete from ParkingStatus first
                        $deleteStatusStmt = $conn->prepare("DELETE FROM ParkingStatus WHERE ParkingSpaceID = ?");
                        if (!$deleteStatusStmt) {
                            throw new Exception("Failed to prepare delete status statement: " . $conn->error);
                        }
                        $deleteStatusStmt->bind_param("s", $spaceId);
                        $deleteStatusStmt->execute();
                        
                        // Delete from ParkingSpace
                        $deleteSpaceStmt = $conn->prepare("DELETE FROM ParkingSpace WHERE ParkingSpaceID = ?");
                        if (!$deleteSpaceStmt) {
                            throw new Exception("Failed to prepare delete space statement: " . $conn->error);
                        }
                        $deleteSpaceStmt->bind_param("s", $spaceId);
                        $deleteSpaceStmt->execute();
                    }
                }
                
                // 4. If AreaNumber changed, update all existing space numbers
                if ($currentArea['AreaNumber'] !== $areaNumber) {
                    // Get all existing spaces
                    $getAllSpacesStmt = $conn->prepare("
                        SELECT ParkingSpaceID, SpaceNumber 
                        FROM ParkingSpace 
                        WHERE ParkingAreaID = ? 
                        ORDER BY CAST(SUBSTRING_INDEX(SpaceNumber, '-', -1) AS UNSIGNED)
                    ");
                    if (!$getAllSpacesStmt) {
                        throw new Exception("Failed to prepare get all spaces statement: " . $conn->error);
                    }
                    $getAllSpacesStmt->bind_param("s", $areaId);
                    $getAllSpacesStmt->execute();
                    $allSpacesResult = $getAllSpacesStmt->get_result();
                    
                    $index = 1;
                    while ($spaceRow = $allSpacesResult->fetch_assoc()) {
                        $newSpaceNumber = $areaNumber . '-' . $index;
                        
                        // Update space number
                        $updateSpaceStmt = $conn->prepare("UPDATE ParkingSpace SET SpaceNumber = ? WHERE ParkingSpaceID = ?");
                        if (!$updateSpaceStmt) {
                            throw new Exception("Failed to prepare update space statement: " . $conn->error);
                        }
                        $updateSpaceStmt->bind_param("ss", $newSpaceNumber, $spaceRow['ParkingSpaceID']);
                        $updateSpaceStmt->execute();
                        
                        $index++;
                    }
                }
                
                $conn->commit();
                
                sendJsonResponse(true, "Parking Area '{$areaNumber}' updated successfully!", $areaId);
                
            } catch (Exception $e) {
                $conn->rollback();
                sendJsonResponse(false, $e->getMessage());
            }
        }
        
        // If we reach here, it means the AJAX action wasn't recognized
        sendJsonResponse(false, 'Invalid AJAX action');
    }
}

// Regular page load - get all areas
$areas = [];
$result = $conn->query("
    SELECT 
        pa.*,
        COALESCE(space_counts.total_spaces, 0) as current_spaces,
        COALESCE(available_counts.available_spaces, 0) as available_spaces
    FROM ParkingArea pa
    LEFT JOIN (
        SELECT ParkingAreaID, COUNT(*) as total_spaces
        FROM ParkingSpace
        GROUP BY ParkingAreaID
    ) space_counts ON pa.ParkingAreaID = space_counts.ParkingAreaID
    LEFT JOIN (
        SELECT 
            pspace.ParkingAreaID,
            COUNT(*) as available_spaces
        FROM ParkingStatus ps
        JOIN ParkingSpace pspace ON ps.ParkingSpaceID = pspace.ParkingSpaceID
        WHERE ps.SpaceStatus = 'Available'
        GROUP BY pspace.ParkingAreaID
    ) available_counts ON pa.ParkingAreaID = available_counts.ParkingAreaID
    ORDER BY pa.AreaNumber
");

if ($result) {
    $areas = $result->fetch_all(MYSQLI_ASSOC);
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
    <title>Parking Area</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f5f5f5; font-family: 'Roboto', sans-serif; margin: 0; padding: 0; display: flex; flex-direction: column; min-height: 100vh; }
        .header { background-color: #d373d3ff; display: flex; justify-content: space-between; align-items: center; padding: 0 20px; position: fixed; width: 100%; height: 120px; box-sizing: border-box; z-index: 1000; top: 0; left: 0; }
        .header-left { display: flex; align-items: center; gap: 20px; padding: 0 35px; }
        .header-right { display: flex; align-items: center; gap: 20px; padding-right: 20px; }
        .togglebutton { background-color: #daa5dad7; color: white; border: 1px solid #d890d89c; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; }
        .togglebutton:hover { background-color: #864281ff; }
        .logo { display: flex; gap: 20px; align-items: center; padding: 0 60px; }
        .logo img { height: 90px; width: auto; }
        .sidebar { background-color: #c277c2ff; width: 250px; color: white; position: fixed; top: 120px; left: 0; bottom: 0; padding: 20px 0; box-sizing: border-box; transition: all 0.3s ease; z-index: 999; }
        .sidebar.collapsed { transform: translateX(-250px); opacity: 0; width: 0; padding: 0; }
        .sidebartitle { color: white; font-size: 1rem; margin-bottom: 20px; padding: 0 20px; }
        .main-container.sidebar-collapsed { margin-left: 0; transition: margin-left 0.3s ease; }
        .menu { display: flex; flex-direction: column; gap: 18px; padding: 0; margin: 0; list-style: none; }
        .menutext { background-color: rgba(255, 255, 255, 0.1); border-radius: 6px; padding: 14px 18px; color: black; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 20px; }
        .menu a { text-decoration: none; color: white; }
        .menutext:hover { background-color: #a03198d5; }
        .menutext.active { background-color: #a03198d5; font-weight: 500; }
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
        .area-card { border: 1px solid #dee2e6; border-radius: 8px; transition: box-shadow 0.3s ease; }
        .area-card:hover { transform: translateY(-5px); box-shadow: 0 8px 15px rgba(0,0,0,0.2); }
        .btn-edit { background-color: #ffc107; color: #212529; border: 1px solid #ffc107; }
        .btn-edit:hover { background: #e0a800; border-color: #e0a800; color: #000; }
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
        <h1 class="sidebartitle"><strong >Admin</strong></h1>
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
                <a href="ParkingManagement.php" class="menutext active">
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
        <h1 class="mb-4"><i class="fas fa-parking"></i> List of Parking Area</h1>
        
        <!-- Add New Area Form -->
        <div class="form-container">
            <h4 class="mb-4"><i class="fas fa-plus-circle"></i> Add New Parking Area</h4>
            <form id="addAreaForm">
                <input type="hidden" name="ajax" value="add_area">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Area Type *</label>
                        <select name="area_type" class="form-select" required>
                            <option value="">Select Type</option>
                            <option value="Student">Student</option>
                            <option value="Staff">Staff</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Area Number/Letter *</label>
                        <input type="text" name="area_number" class="form-control" 
                               placeholder="e.g., A1, B1" required>
                        <small class="text-muted">This will create spaces like: A1-1, A1-2, etc.</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Total Spaces *</label>
                        <input type="number" name="total_spaces" class="form-control" 
                               min="1" max="50" value="3" required>
                        <small class="text-muted">Number of spaces to generate automatically</small>
                    </div>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Preview:</strong> 
                    <span id="spacePreview">A1-1, A1-2, A1-3</span>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Parking Area
                </button>
                <div id="formMessage" class="mt-3"></div>
            </form>
        </div>

        <!-- Parking Areas List -->
        <h4 class="mb-3"><i class="fas fa-list"></i> Parking Areas</h4>
        <div class="row" id="areasContainer">
            <?php if(empty($areas)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle fa-2x mb-2"></i>
                        <h5>No Parking Areas Found</h5>
                        <p>Create your first parking area using the form above.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach($areas as $area): 
                    $availablePercent = $area['TotalSpaces'] > 0 ? 
                        round(($area['available_spaces'] / $area['TotalSpaces']) * 100) : 0;
                ?>
                <div class="col-md-4 mb-4">
                    <div class="card area-card h-100">
                        <div class="card-header bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-map-marker-alt"></i> 
                                    Area <?php echo htmlspecialchars($area['AreaNumber']); ?>
                                </h5>
                                <span class="badge bg-light text-dark">
                                    <?php echo htmlspecialchars($area['AreaType']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <small class="text-muted d-block">Area ID: <?php echo $area['ParkingAreaID']; ?></small>
                                <small class="text-muted">Total Capacity: <?php echo $area['TotalSpaces']; ?> spaces</small>
                            </div>
                            
                            <!-- Availability Progress -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small>Availability</small>
                                    <small><?php echo $area['available_spaces']; ?>/<?php echo $area['TotalSpaces']; ?></small>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar 
                                        <?php echo $availablePercent >= 50 ? 'bg-success' : 
                                              ($availablePercent >= 20 ? 'bg-warning' : 'bg-danger'); ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo $availablePercent; ?>%">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <a href="ParkingSpaces.php?area_id=<?php echo $area['ParkingAreaID']; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> View Spaces
                                    </a>
                                    <button class="btn btn-sm btn-edit" 
                                            onclick="editArea('<?php echo $area['ParkingAreaID']; ?>', '<?php echo htmlspecialchars($area['AreaType']); ?>', '<?php echo htmlspecialchars($area['AreaNumber']); ?>', <?php echo $area['TotalSpaces']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                </div>
                                <button class="btn btn-sm btn-outline-danger" 
                                        onclick="deleteArea('<?php echo $area['ParkingAreaID']; ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Area Modal -->
    <div class="modal fade" id="editAreaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Parking Area</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editAreaForm">
                    <input type="hidden" name="ajax" value="edit_area">
                    <input type="hidden" name="area_id" id="editAreaId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Area Type *</label>
                            <select name="area_type" id="editAreaType" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="Student">Student</option>
                                <option value="Staff">Staff</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Area Number/Letter *</label>
                            <input type="text" name="area_number" id="editAreaNumber" class="form-control" required>
                            <small class="text-muted">Changing this will update all space numbers</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Total Spaces *</label>
                            <input type="number" name="total_spaces" id="editTotalSpaces" class="form-control" min="1" max="50" required>
                            <small class="text-muted" id="editSpaceInfo">
                                Current: <span id="currentSpaces">0</span> spaces. 
                                <span id="spaceChangeInfo"></span>
                            </small>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Note:</strong> 
                            <span id="editSpacePreview"></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Parking Area</button>
                    </div>
                </form>
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

    // Global variable to store modal instance
    let editModalInstance = null;

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
            });
            
            // Load saved state
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true') {
                sidebar.classList.add('collapsed');
                mainContainer.classList.add('sidebar-collapsed');
                footer.classList.add('sidebar-collapsed');
            }
        }

        // Update space preview for add form
        function updateSpacePreview() {
            const areaNumber = document.querySelector('[name="area_number"]').value || 'A1';
            const totalSpaces = document.querySelector('[name="total_spaces"]').value;
            
            let preview = '';
            const maxPreview = 10;
            
            for (let i = 1; i <= Math.min(totalSpaces, maxPreview); i++) {
                preview += areaNumber + '-' + i + ', ';
            }
            
            if (totalSpaces > maxPreview) {
                preview += '... ' + areaNumber + '-' + totalSpaces;
            } else {
                preview = preview.slice(0, -2); // Remove last comma
            }
            
            document.getElementById('spacePreview').textContent = preview;
        }
        
        // Update space preview for edit form
        function updateEditSpacePreview() {
            const areaNumber = document.getElementById('editAreaNumber').value || '';
            const totalSpaces = document.getElementById('editTotalSpaces').value;
            const currentSpaces = parseInt(document.getElementById('currentSpaces').textContent);
            
            let preview = '';
            if (areaNumber && totalSpaces) {
                const maxPreview = 10;
                
                for (let i = 1; i <= Math.min(totalSpaces, maxPreview); i++) {
                    preview += areaNumber + '-' + i + ', ';
                }
                
                if (totalSpaces > maxPreview) {
                    preview += '... ' + areaNumber + '-' + totalSpaces;
                } else {
                    preview = preview.slice(0, -2); // Remove last comma
                }
            }
            
            document.getElementById('editSpacePreview').textContent = preview;
            
            // Update space change info
            const newTotal = parseInt(totalSpaces);
            if (newTotal > currentSpaces) {
                const diff = newTotal - currentSpaces;
                document.getElementById('spaceChangeInfo').textContent = 
                    `Will add ${diff} new space(s).`;
                document.getElementById('spaceChangeInfo').className = 'text-success';
            } else if (newTotal < currentSpaces) {
                const diff = currentSpaces - newTotal;
                document.getElementById('spaceChangeInfo').textContent = 
                    `Will remove ${diff} space(s) from the end.`;
                document.getElementById('spaceChangeInfo').className = 'text-danger';
            } else {
                document.getElementById('spaceChangeInfo').textContent = 
                    'No change in number of spaces.';
                document.getElementById('spaceChangeInfo').className = 'text-muted';
            }
        }
        
        // Handle add form submission with AJAX
        document.getElementById('addAreaForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = this;
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            const formMessage = document.getElementById('formMessage');
            
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
            submitBtn.disabled = true;
            formMessage.innerHTML = '';
            
            // Create FormData object
            const formData = new FormData(form);
            
            // Use current URL for AJAX call
            const currentUrl = window.location.href;
            
            // Send AJAX request
            fetch(currentUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // First check if response is OK
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // If not JSON, get text to debug
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text);
                        throw new Error('Server returned non-JSON response');
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    // Show success message
                    formMessage.innerHTML = `
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> ${data.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `;
                    
                    // Reset form
                    form.reset();
                    updateSpacePreview();
                    
                    // Reload page to show new area
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                    
                } else {
                    // Show error message
                    formMessage.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> ${data.error}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                formMessage.innerHTML = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> ${error.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
            })
            .finally(() => {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
        
        // Handle edit form submission - FIXED VERSION with better error handling
        document.getElementById('editAreaForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = this;
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            submitBtn.disabled = true;
            
            // Create FormData object
            const formData = new FormData(form);
            
            // Send AJAX request
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // First check if response is OK
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // If not JSON, get text to debug
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text);
                        throw new Error('Server returned non-JSON response. Please check for PHP errors.');
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert(data.message);
                    // Hide modal using stored instance
                    if (editModalInstance) {
                        editModalInstance.hide();
                    }
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                alert('An error occurred: ' + error.message + '\nPlease check the browser console for more details.');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
        
        // Initialize space preview for add form
        const areaNumberInput = document.querySelector('[name="area_number"]');
        const totalSpacesInput = document.querySelector('[name="total_spaces"]');
        
        if (areaNumberInput) {
            areaNumberInput.addEventListener('input', updateSpacePreview);
        }
        if (totalSpacesInput) {
            totalSpacesInput.addEventListener('input', updateSpacePreview);
        }
        updateSpacePreview();
        
        // Initialize event listeners for edit form
        const editAreaNumber = document.getElementById('editAreaNumber');
        const editTotalSpaces = document.getElementById('editTotalSpaces');
        
        if (editAreaNumber) {
            editAreaNumber.addEventListener('input', updateEditSpacePreview);
        }
        if (editTotalSpaces) {
            editTotalSpaces.addEventListener('input', updateEditSpacePreview);
        }
    });
    
    // Edit area function - FIXED VERSION
    function editArea(areaId, areaType, areaNumber, totalSpaces) {
        document.getElementById('editAreaId').value = areaId;
        document.getElementById('editAreaType').value = areaType;
        document.getElementById('editAreaNumber').value = areaNumber;
        document.getElementById('editTotalSpaces').value = totalSpaces;
        document.getElementById('currentSpaces').textContent = totalSpaces;
        
        // Call update preview function
        if (typeof updateEditSpacePreview === 'function') {
            updateEditSpacePreview();
        }
        
        // Create or get modal instance
        const modalElement = document.getElementById('editAreaModal');
        editModalInstance = bootstrap.Modal.getInstance(modalElement);
        if (!editModalInstance) {
            editModalInstance = new bootstrap.Modal(modalElement);
        }
        editModalInstance.show();
    }
    
    // Delete area function
    function deleteArea(areaId) {
        if (confirm('Are you sure you want to delete this parking area and all its spaces?')) {
            fetch(`delete_area.php?area_id=${areaId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Parking area deleted successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the area.');
                });
        }
    }
</script>
</body>
</html>
