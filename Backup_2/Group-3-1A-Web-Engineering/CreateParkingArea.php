<?php
session_start();
header('Content-Type: application/json');

// Database connection
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3306);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Check if POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and validate input
    $areaNumber = isset($_POST['areaNumber']) ? trim($_POST['areaNumber']) : '';
    $areaType = isset($_POST['areaType']) ? trim($_POST['areaType']) : '';
    $totalSpaces = isset($_POST['totalSpaces']) ? intval($_POST['totalSpaces']) : 0;
    
    // Validate inputs
    if (empty($areaNumber)) {
        echo json_encode(['success' => false, 'message' => 'Area number is required']);
        exit();
    }
    
    if (empty($areaType)) {
        echo json_encode(['success' => false, 'message' => 'Area type is required']);
        exit();
    }
    
    if ($totalSpaces <= 0 || $totalSpaces > 100) {
        echo json_encode(['success' => false, 'message' => 'Total spaces must be between 1 and 100']);
        exit();
    }
    
    // Check if area number already exists
    $checkQuery = "SELECT ParkingAreaID FROM parkingarea WHERE AreaNumber = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("s", $areaNumber);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Area number already exists']);
        exit();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Insert parking area
        $query = "INSERT INTO parkingarea (AreaNumber, AreaType, TotalSpaces) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssi", $areaNumber, $areaType, $totalSpaces);
        
        if (!$stmt->execute()) {
            throw new Exception("Error creating parking area: " . $stmt->error);
        }
        
        $newAreaId = $stmt->insert_id;
        
        // 2. Generate parking spaces (1 to totalSpaces)
        $spaceQuery = "INSERT INTO parkingspace (ParkingAreaID, SpaceNumber, SpaceType, Status) VALUES (?, ?, ?, ?)";
        $spaceStmt = $conn->prepare($spaceQuery);
        
        $defaultSpaceType = $areaType; // Use area type as default space type
        $defaultStatus = 'Available';
        
        for ($i = 1; $i <= $totalSpaces; $i++) {
            $spaceNumber = $areaNumber . "-" . str_pad($i, 3, '0', STR_PAD_LEFT); // e.g., A1-001, A1-002
            $spaceStmt->bind_param("isss", $newAreaId, $spaceNumber, $defaultSpaceType, $defaultStatus);
            
            if (!$spaceStmt->execute()) {
                throw new Exception("Error creating parking space $i: " . $spaceStmt->error);
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        // Prepare response with area data
        $areaData = [
            'ParkingAreaID' => $newAreaId,
            'AreaNumber' => $areaNumber,
            'AreaType' => $areaType,
            'TotalSpaces' => $totalSpaces
        ];
        
        $_SESSION['message'] = "Parking area created with $totalSpaces spaces!";
        $_SESSION['message_type'] = "success";
        
        echo json_encode([
            'success' => true, 
            'message' => "Parking area '$areaNumber' created successfully with $totalSpaces spaces.",
            'area' => $areaData
        ]);
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        echo json_encode([
            'success' => false, 
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
    
    $stmt->close();
    if (isset($spaceStmt)) $spaceStmt->close();
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>