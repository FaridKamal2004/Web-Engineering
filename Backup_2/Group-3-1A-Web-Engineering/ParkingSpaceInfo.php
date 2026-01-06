<?php
// Start session
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3306);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get space ID from QR
$spaceId = $_GET['space_id'] ?? '';

if (empty($spaceId)) {
    die("<h3>Invalid parking space</h3>");
}

// Get parking space + area + status info
$stmt = $conn->prepare("
    SELECT 
        ps.SpaceNumber,
        ps.SpaceType,
        pa.AreaNumber,
        pa.AreaType,
        pst.SpaceStatus,
        pst.DateStatus
    FROM ParkingSpace ps
    JOIN ParkingArea pa ON ps.ParkingAreaID = pa.ParkingAreaID
    LEFT JOIN ParkingStatus pst ON ps.ParkingSpaceID = pst.ParkingSpaceID
    WHERE ps.ParkingSpaceID = ?
");
$stmt->bind_param("s", $spaceId);
$stmt->execute();
$result = $stmt->get_result();
$space = $result->fetch_assoc();

if (!$space) {
    die("<h3>Parking space not found</h3>");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Parking Space Information</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="card shadow">
        <div class="card-header bg-primary text-Black">
            <h4 class="mb-0">
                <i class="fas fa-parking"></i> Parking Space Information
            </h4>
        </div>

        <div class="card-body">
            <table class="table table-bordered">
                <tr>
                    <th>Parking Area</th>
                    <td><?php echo htmlspecialchars($space['AreaNumber']); ?> (<?php echo htmlspecialchars($space['AreaType']); ?>)</td>
                </tr>
                <tr>
                    <th>Parking Space</th>
                    <td><?php echo htmlspecialchars($space['SpaceNumber']); ?></td>
                </tr>
                <tr>
                    <th>Space Type</th>
                    <td><?php echo htmlspecialchars($space['SpaceType']); ?></td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>
                        <?php
                        $status = $space['SpaceStatus'] ?? 'Unknown';
                        $badge = $status === 'Available' ? 'success' :
                                 ($status === 'Occupied' ? 'danger' : 'warning');
                        ?>
                        <span class="badge bg-<?php echo $badge; ?>">
                            <?php echo $status; ?>
                        </span>
                    </td>
                </tr>
                <?php if (!empty($space['DateStatus'])): ?>
                <tr>
                    <th>Status Since</th>
                    <td><?php echo date('d/m/Y', strtotime($space['DateStatus'])); ?></td>
                </tr>
                <?php endif; ?>
            </table>

            <div class="alert alert-info mt-3">
                <strong>Note:</strong>  
                This QR code is used to identify the parking space. 
            </div>
        </div>

        <div class="card-footer text-center">
            <small class="text-muted">
                FK Park System &copy; <?php echo date('Y'); ?>
            </small>
        </div>
    </div>
</div>

<script src="https://kit.fontawesome.com/a076d05399.js"></script>
</body>
</html>