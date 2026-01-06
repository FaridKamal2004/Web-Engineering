<?php
// delete_area.php
header('Content-Type: application/json');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3306);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$areaId = $_GET['area_id'] ?? '';

if (!$areaId) {
    echo json_encode(['success' => false, 'error' => 'Area ID is required']);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Get all ParkingSpaceIDs for this area
    $spaceStmt = $conn->prepare("SELECT ParkingSpaceID FROM ParkingSpace WHERE ParkingAreaID = ?");
    $spaceStmt->bind_param("s", $areaId);
    $spaceStmt->execute();
    $spaceResult = $spaceStmt->get_result();
    
    $spaceIds = [];
    while ($row = $spaceResult->fetch_assoc()) {
        $spaceIds[] = $row['ParkingSpaceID'];
    }
    
    // If there are spaces, delete related records in proper order
    if (!empty($spaceIds)) {
        // Create placeholders for the IN clause
        $placeholders = implode(',', array_fill(0, count($spaceIds), '?'));
        
        // 1. Delete from Booking table first (references ParkingSpaceID)
        $bookingStmt = $conn->prepare("DELETE FROM Booking WHERE ParkingSpaceID IN ($placeholders)");
        $types = str_repeat('s', count($spaceIds));
        $bookingStmt->bind_param($types, ...$spaceIds);
        $bookingStmt->execute();
        
        // 2. Delete from StudentMerit if it references Booking (if applicable)
        // Check if your StudentMerit table references Booking
        // If yes, delete those records first
        
        // 3. Delete from TrafficSummon if it references Booking (if applicable)
        // Check if your TrafficSummon table references Booking
        // If yes, delete those records first
    }
    
    // 4. Delete from ParkingStatus
    $statusStmt = $conn->prepare("DELETE FROM ParkingStatus WHERE ParkingAreaID = ?");
    $statusStmt->bind_param("s", $areaId);
    $statusStmt->execute();
    
    // 5. Delete from ParkingSpace
    $spaceDeleteStmt = $conn->prepare("DELETE FROM ParkingSpace WHERE ParkingAreaID = ?");
    $spaceDeleteStmt->bind_param("s", $areaId);
    $spaceDeleteStmt->execute();
    
    // 6. Delete from ParkingArea
    $areaStmt = $conn->prepare("DELETE FROM ParkingArea WHERE ParkingAreaID = ?");
    $areaStmt->bind_param("s", $areaId);
    $areaStmt->execute();
    
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Area deleted successfully']);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>