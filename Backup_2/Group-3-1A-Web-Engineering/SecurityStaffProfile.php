<?php
// Start session
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3306);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to only logged-in staff
if (!isset($_SESSION['user_id']) || $_SESSION['type_user'] !== 'SecurityStaff') {
    header("Location: Login.php");
    exit();
}

$staff_id = $_SESSION['user_id'];

// FETCH STAFF DATA
$stmt = $conn->prepare("SELECT * FROM staff WHERE staffID = ?");
$stmt->bind_param("s", $staff_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    session_destroy();
    header("Location: Login.php");
    exit();
}

$staff = $result->fetch_assoc();

// Handle messages
$update_success = false;
$update_error = false;
$password_success = false;
$password_error = false;
$password_error_msg = "";

// UPDATE PROFILE
if (isset($_POST['update_profile'])) {
    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);

    if ($name === "" || $email === "") {
        $update_error = true;
    } else {
        $stmt = $conn->prepare("
            UPDATE staff 
            SET StaffName = ?, StaffEmail = ?, StaffContact = ?
            WHERE staffID = ?
        ");
        $stmt->bind_param("ssss", $name, $email, $contact, $staff_id);

        if ($stmt->execute()) {
            $update_success = true;
            // Refresh data
            $staff['StaffName']  = $name;
            $staff['StaffEmail'] = $email;
            $staff['StaffContact'] = $contact;
        } else {
            $update_error = true;
        }
    }
}

// CHANGE PASSWORD
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (!empty($current_password) && $new_password === $confirm_password && strlen($new_password) >= 8) {
        // Verify current password
        if (password_verify($current_password, $staff['StaffPassword'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE staff SET StaffPassword = ? WHERE staffID = ?");
            $stmt->bind_param("ss", $hashed_password, $staff_id);
            
            if ($stmt->execute()) {
                $password_success = true;
                $staff['StaffPassword'] = $hashed_password;
            } else {
                $password_error = true;
                $password_error_msg = "Database error. Please try again.";
            }
        } else {
            $password_error = true;
            $password_error_msg = "Current password is incorrect.";
        }
    } else {
        $password_error = true;
        if (empty($current_password)) {
            $password_error_msg = "Please enter your current password.";
        } elseif ($new_password !== $confirm_password) {
            $password_error_msg = "New password and confirm password do not match.";
        } elseif (strlen($new_password) < 8) {
            $password_error_msg = "Password must be at least 8 characters long.";
        }
    }
}

// UPLOAD PROFILE PICTURE
if (isset($_POST['upload_picture'])) {
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $file = $_FILES['profile_picture'];
        
        if ($file['size'] > 5 * 1024 * 1024) {
            $picture_error = "File size too large. Maximum size is 5MB.";
        } else {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            $file_type = mime_content_type($file['tmp_name']);
            
            if (!in_array($file_type, $allowed_types)) {
                $picture_error = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
            } else {
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = strtolower(str_replace(' ', '_', $staff['StaffName'])) . '_' . time() . '.' . $file_extension;
                
                $upload_dir = 'uploads/profile_pictures/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    $stmt = $conn->prepare("UPDATE staff SET StaffPic = ? WHERE staffID = ?");
                    $stmt->bind_param("ss", $new_filename, $staff_id);
                    
                    if ($stmt->execute()) {
                        $picture_success = true;
                        $staff['StaffPic'] = $new_filename;
                    } else {
                        $picture_error = "Failed to update database.";
                    }
                } else {
                    $picture_error = "Failed to upload file.";
                }
            }
        }
    }
}

// DELETE ACCOUNT
if (isset($_POST['delete_account'])) {
    $stmt = $conn->prepare("DELETE FROM staff WHERE staffID = ?");
    $stmt->bind_param("s", $staff_id);

    if ($stmt->execute()) {
        session_destroy();
        header("Location: Login.php");
        exit();
    } else {
        $delete_error = true;
    }
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
    <title>FK Park System - Security Staff Profile</title>
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
            background-color: #ebaa5fff;
            color: white;
            border: 1px solid #ebaa5fff;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .togglebutton:hover {
            background-color: #6d4e2aff;
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
        .sidebar{
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
        .menutext:hover {
            background-color: #6d4e2aff;
            color: white;
        }   
        .menutext.active {
            background-color: #6d4e2aff;
            color: white;
            font-weight: 500;
        }
        .profile{
            background-color: #f19c1dff;
            color: white;
            border: 1px solid rgba(0, 0, 0, 0.3);
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
            background-color: #6d4e2aff;
        }
        .logoutbutton {
            background-color: rgba(255, 0, 0, 0.81);
            color: white;
            border: 1px solid rgba(0, 0, 0, 0.3);
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .main-container {
            margin-left: 250px;
            margin-top: 120px;
            padding: 40px;
            box-sizing: border-box;
            flex: 1;
            transition: margin-left 0.3s ease;
            width: calc(100% - 250px);
        }
        .main-container.sidebar-collapsed {
            margin-left: 0;
            width: 100%;
        }
        footer {
            background-color: #f7b973ff;
            color: white;
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
        
        /* Profile Container */
        .profile-container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            display: flex;
            gap: 40px;
        }

        /* Left Column - Profile Picture */
        .profile-left {
            flex: 0 0 250px;
            text-align: center;
        }

        .profile-picture {
            width: 200px;
            height: 200px;
            background: linear-gradient(135deg, #ebaa5fff, #eb9d43ff);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 64px;
            font-weight: bold;
            border: 5px solid #f0f0f0;
            position: relative;
            overflow: hidden;
        }

        .profile-picture img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .edit-picture-form {
            margin-top: 10px;
        }

        .edit-picture-btn {
            background: #ebaa5fff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
            width: 100%;
            justify-content: center;
        }

        .edit-picture-btn:hover {
            background: #eb9d43ff;
        }

        .file-input {
            display: none;
        }

        /* Right Column - Profile Info */
        .profile-right {
            flex: 1;
        }

        .profile-title {
            color: #ebaa5fff;
            font-size: 24px;
            margin-bottom: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .profile-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-item {
            padding: 15px;
            background: #fef5eb;
            border-radius: 6px;
            border-left: 3px solid #ebaa5fff;
        }

        .info-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 16px;
            color: #333;
            font-weight: 500;
        }

        /* Form Sections */
        .form-section {
            background: #fef5eb;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ebaa5fff;
        }

        .form-section-title {
            color: #ebaa5fff;
            font-size: 20px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Password Form Specific Styles */
        .password-form {
            background: #fef5eb;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ebaa5fff;
        }

        .password-title {
            color: #ebaa5fff;
            font-size: 20px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .password-form-group {
            margin-bottom: 20px;
        }

        .password-label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }

        .password-input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border 0.3s;
            max-width: 400px;
        }

        .password-input:focus {
            outline: none;
            border-color: #ebaa5fff;
            box-shadow: 0 0 0 2px rgba(235, 170, 95, 0.2);
        }

        .password-requirements {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px dashed #ccc;
        }

        .requirements-title {
            color: #666;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .requirements-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .requirements-list li {
            color: #666;
            font-size: 13px;
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
        }

        .requirements-list li:before {
            content: "•";
            position: absolute;
            left: 0;
            color: #ebaa5fff;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #ebaa5fff;
            box-shadow: 0 0 0 2px rgba(235, 170, 95, 0.2);
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: #ebaa5fff;
            color: white;
        }

        .btn-primary:hover {
            background: #eb9d43ff;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-danger {
            background: #cc0000;
            color: white;
        }

        .btn-danger:hover {
            background: #aa0000;
        }

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-staff {
            background: #fff3cd;
            color: #856404;
        }

        /* Divider */
        .divider {
            height: 1px;
            background: #e0e0e0;
            margin: 20px 0;
            border: none;
        }

        /* Delete Account Section */
        .danger-zone {
            background: #fff5f5;
            padding: 25px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid #cc0000;
        }

        .danger-zone-title {
            color: #cc0000;
            font-size: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .danger-zone-text {
            color: #666;
            margin-bottom: 20px;
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .profile-container {
                flex-direction: column;
            }
            
            .profile-left {
                flex: none;
            }
            
            .profile-info-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .password-input {
                max-width: 100%;
            }
        }

        @media (max-width: 768px) {
            .main-container {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }
            .profile-container {
                padding: 15px;
            }
            .form-section, .password-form, .danger-zone {
                padding: 15px;
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
                <img src="UMPLogo.png" alt="UMP Logo">
            </div>
        </div>
        <div class="header-right">
            <a href="SecurityStaffDashboard.php" class="profile">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="logout.php" class="logoutbutton" onclick="return confirm('Are you sure you want to log out?');">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>
    <div class="container main-container" id="mainContainer">
        <h1 class="mb-4"><i class="fas fa-user-shield"></i> Security Staff Profile</h1>
        
        <!-- Success/Error Messages -->
        <?php if ($update_success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Profile updated successfully!
            </div>
        <?php endif; ?>
        
        <?php if ($update_error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                Error updating profile. Please try again.
            </div>
        <?php endif; ?>

        <?php if ($password_success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Password changed successfully!
            </div>
        <?php endif; ?>
        
        <?php if ($password_error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($password_error_msg); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($picture_success) && $picture_success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Profile picture updated successfully!
            </div>
        <?php endif; ?>
        
        <?php if (isset($picture_error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($picture_error); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($delete_error) && $delete_error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                Error deleting account. Please try again.
            </div>
        <?php endif; ?>

        <div class="profile-container">
            <!-- Left Column - Profile Picture -->
            <div class="profile-left">
                <div class="profile-picture" id="profileAvatar">
                    <?php
                    // Display profile picture or initials
                    if (!empty($staff['StaffPic'])) {
                        $image_path = 'uploads/profile_pictures/' . $staff['StaffPic'];
                        if (file_exists($image_path)) {
                            echo '<img src="' . htmlspecialchars($image_path) . '" alt="Profile Picture">';
                        } else {
                            // Fallback to initials if image doesn't exist
                            $name = $staff['StaffName'] ?? 'Staff';
                            $initials = '';
                            $name_parts = explode(' ', $name);
                            foreach ($name_parts as $part) {
                                if (strlen($part) > 0) {
                                    $initials .= strtoupper($part[0]);
                                }
                            }
                            echo substr($initials, 0, 2);
                        }
                    } else {
                        // Display initials if no picture
                        $name = $staff['StaffName'] ?? 'Staff';
                        $initials = '';
                        $name_parts = explode(' ', $name);
                        foreach ($name_parts as $part) {
                            if (strlen($part) > 0) {
                                $initials .= strtoupper($part[0]);
                            }
                        }
                        echo substr($initials, 0, 2);
                    }
                    ?>
                </div>
                
                <!-- Profile Picture Upload Form -->
                <form method="POST" action="" enctype="multipart/form-data" class="edit-picture-form">
                    <input type="file" id="profile_picture" name="profile_picture" accept="image/*" class="file-input" onchange="this.form.submit()">
                    <button type="button" class="edit-picture-btn" onclick="document.getElementById('profile_picture').click()">
                        <i class="fas fa-camera"></i> Edit Picture
                    </button>
                    <input type="hidden" name="upload_picture" value="1">
                </form>
                
                <div style="margin-top: 20px;">
                    <h4 style="color: #ebaa5fff; margin-bottom: 10px;">Role</h4>
                    <span class="status-badge status-staff">Security Staff</span>
                </div>
            </div>

            <!-- Right Column - Profile Information -->
            <div class="profile-right">
                <h2 class="profile-title">
                    <i class="fas fa-user-shield"></i> Staff Profile
                </h2>

                <!-- Profile Information Grid -->
                <div class="profile-info-grid">
                    <div class="info-item">
                        <div class="info-label">Staff ID</div>
                        <div class="info-value"><?php echo htmlspecialchars($staff['StaffID'] ?? 'N/A'); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Full Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($staff['StaffName'] ?? 'N/A'); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Email Address</div>
                        <div class="info-value"><?php echo htmlspecialchars($staff['StaffEmail'] ?? 'N/A'); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Phone Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($staff['StaffContact'] ?? 'N/A'); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Profile Picture</div>
                        <div class="info-value"><?php echo !empty($staff['StaffPic']) ? htmlspecialchars($staff['StaffPic']) : 'Not set'; ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Last Login</div>
                        <div class="info-value"><?php echo date('Y-m-d H:i:s'); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Account Status</div>
                        <div class="info-value" style="color: #28a745; font-weight: bold;">Active</div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">Role Type</div>
                        <div class="info-value">Security Staff</div>
                    </div>
                </div>

                <!-- Edit Profile Form -->
                <form method="POST" action="">
                    <div class="form-section">
                        <h4 class="form-section-title">
                            <i class="fas fa-edit"></i> Edit Personal Information
                        </h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($staff['StaffName'] ?? ''); ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($staff['StaffEmail'] ?? ''); ?>" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="contact">Phone Number</label>
                                <input type="tel" id="contact" name="contact" 
                                       value="<?php echo htmlspecialchars($staff['StaffContact'] ?? ''); ?>"
                                       pattern="[0-9]{3}-[0-9]{7,8}" 
                                       placeholder="012-3456789">
                                <small style="color: #666; font-size: 12px;">Format: 012-3456789</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="staff_id">Staff ID</label>
                                <input type="text" id="staff_id" name="staff_id" 
                                       value="<?php echo htmlspecialchars($staff['StaffID'] ?? ''); ?>" 
                                       readonly style="background-color: #f0f0f0;">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Change Password Form -->
                <form method="POST" action="" onsubmit="return validatePassword()" class="password-form">
                    <h3 class="password-title">Change Password</h3>
                    
                    <div class="password-form-group">
                        <label class="password-label" for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" 
                               class="password-input" required>
                    </div>
                    
                    <div class="password-form-group">
                        <label class="password-label" for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" 
                               class="password-input" required>
                    </div>
                    
                    <div class="password-form-group">
                        <label class="password-label" for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               class="password-input" required>
                    </div>
                    
                    <!-- Horizontal Divider -->
                    <hr class="divider">
                    
                    <!-- Password Requirements Section -->
                    <div class="password-requirements">
                        <div class="requirements-title">Password Requirements:</div>
                        <ul class="requirements-list">
                            <li>At least 8 characters long</li>
                            <li>Contains uppercase and lowercase letters</li>
                            <li>Contains at least one number</li>
                        </ul>
                    </div>
                    
                    <!-- Change Password Button -->
                    <div class="form-actions">
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </form>

                <!-- Danger Zone - Delete Account -->
                <div class="danger-zone">
                    <h3 class="danger-zone-title">
                        <i class="fas fa-exclamation-triangle"></i> Danger Zone
                    </h3>
                    <p class="danger-zone-text">
                        Once you delete your account, there is no going back. Please be certain.
                    </p>
                    <form method="POST" action="" onsubmit="return confirm('Are you absolutely sure you want to delete your account? This action cannot be undone!');">
                        <button type="submit" name="delete_account" class="btn btn-danger">
                            <i class="fas fa-trash-alt"></i> Delete Account
                        </button>
                    </form>
                </div>
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
        // Sidebar Toggle Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContainer = document.getElementById('mainContainer');
            const footer = document.getElementById('footer');
            
            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    mainContainer.classList.toggle('sidebar-collapsed');
                    footer.classList.toggle('sidebar-collapsed');
                    
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

        // Password Validation
        function validatePassword() {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (!currentPassword) {
                alert('Please enter your current password.');
                return false;
            }
            
            if (newPassword.length < 8) {
                alert('Password must be at least 8 characters long.');
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                alert('New password and confirm password do not match.');
                return false;
            }
            
            const hasUpperCase = /[A-Z]/.test(newPassword);
            const hasLowerCase = /[a-z]/.test(newPassword);
            const hasNumbers = /\d/.test(newPassword);
            
            if (!hasUpperCase || !hasLowerCase || !hasNumbers) {
                alert('Password must contain uppercase letters, lowercase letters, and at least one number.');
                return false;
            }
            
            return true;
        }

        // Get staff initials
        function getStaffInitials(name) {
            let initials = '';
            const nameParts = name.split(' ');
            nameParts.forEach(part => {
                if (part.length > 0) {
                    initials += part[0].toUpperCase();
                }
            });
            return initials.substring(0, 2);
        }

        // Update avatar when name changes
        document.getElementById('name').addEventListener('input', function() {
            const name = this.value;
            if (name.trim().length > 0) {
                const initials = getStaffInitials(name);
                const avatar = document.getElementById('profileAvatar');
                if (!avatar.querySelector('img')) {
                    avatar.textContent = initials;
                }
            }
        });

        // Password strength indicator
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            
            if (password.length >= 8) {
                this.style.borderColor = '#28a745';
            } else {
                this.style.borderColor = '#dc3545';
            }
            
            const confirmField = document.getElementById('confirm_password');
            if (confirmField.value && password !== confirmField.value) {
                confirmField.style.borderColor = '#dc3545';
            } else if (confirmField.value) {
                confirmField.style.borderColor = '#28a745';
            }
        });

        // Confirm password validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = '#dc3545';
            } else if (confirmPassword) {
                this.style.borderColor = '#28a745';
            }
        });

        // Phone number format validation
        document.getElementById('contact').addEventListener('input', function() {
            const phone = this.value;
            const phonePattern = /^[0-9]{3}-[0-9]{7,8}$/;
            
            if (phone && !phonePattern.test(phone)) {
                this.style.borderColor = '#dc3545';
            } else if (phone) {
                this.style.borderColor = '#28a745';
            }
        });
    </script>
</body>
</html>