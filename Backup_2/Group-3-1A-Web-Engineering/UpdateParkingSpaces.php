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
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $areaNumber = isset($_POST['areaNumber']) ? trim($_POST['areaNumber']) : '';
    $areaType = isset($_POST['areaType']) ? trim($_POST['areaType']) : '';
    $totalSpaces = isset($_POST['totalSpaces']) ? intval($_POST['totalSpaces']) : 0;
    
    // Validate inputs
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid parking area ID']);
        exit();
    }
    
    if (empty($areaNumber)) {
        echo json_encode(['success' => false, 'message' => 'Area number is required']);
        exit();
    }
    
    if (empty($areaType)) {
        echo json_encode(['success' => false, 'message' => 'Area type is required']);
        exit();
    }
    
    if ($totalSpaces <= 0) {
        echo json_encode(['success' => false, 'message' => 'Total spaces must be at least 1']);
        exit();
    }
    
    // Check if area number already exists (excluding current record)
    $checkQuery = "SELECT ParkingAreaID FROM parkingarea WHERE AreaNumber = ? AND ParkingAreaID != ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("si", $areaNumber, $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Area number already exists']);
        exit();
    }
    
    // Update query
    $query = "UPDATE parkingarea SET 
              AreaNumber = ?, 
              AreaType = ?, 
              TotalSpaces = ? 
              WHERE ParkingAreaID = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssii", $areaNumber, $areaType, $totalSpaces, $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['message'] = "Parking area updated successfully!";
            $_SESSION['message_type'] = "success";
            echo json_encode(['success' => true, 'message' => 'Parking area updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No changes were made']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error updating parking area: ' . $conn->error]);
    }
    
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close();
?>