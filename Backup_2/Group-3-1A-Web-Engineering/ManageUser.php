<?php
// Start the session
session_start();

// Database connection parameters
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3306);

// Check if database connection failed
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Restrict access to only logged-in admin
if (!isset($_SESSION['user_id']) || ($_SESSION['type_user'] ?? '') !== 'Administrator') {
    header("Location: Login.php");
    exit();
}

// Get admin data from database
$admin_id = $_SESSION['user_id'];
$query = "SELECT * FROM staff WHERE StaffID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    session_destroy();
    header("Location: Login.php");
    exit();
}

$staff = $result->fetch_assoc();

// Feedback message (stored in session for redirect-based flash message)
if (!isset($_SESSION['message'])) {
    $_SESSION['message'] = "";
}

// Handle deletion
if (isset($_GET['delete_student'])) {
    $delID = $_GET['delete_student'];
    $sql = "DELETE FROM student WHERE StudentID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $delID);
    if ($stmt->execute()) {
        $_SESSION['message'] = "<div class='alert alert-success'>Student deleted successfully!</div>";
    } else {
        $_SESSION['message'] = "<div class='alert alert-danger'>Error deleting student: {$stmt->error}</div>";
    }
    header("Location: ManageUser.php");
    exit();
}

if (isset($_GET['delete_staff'])) {
    $delID = $_GET['delete_staff'];
    $sql = "DELETE FROM staff WHERE StaffID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $delID);
    if ($stmt->execute()) {
        $_SESSION['message'] = "<div class='alert alert-success'>Staff deleted successfully!</div>";
    } else {
        $_SESSION['message'] = "<div class='alert alert-danger'>Error deleting staff: {$stmt->error}</div>";
    }
    header("Location: ManageUser.php");
    exit();
}

// Handle edit (Student)
if (isset($_POST['edit_student_submit'])) {
    $sid = $_POST['edit_student_id'];
    $sname = $_POST['edit_student_name'];
    $scontact = $_POST['edit_student_contact'];
    $semail = $_POST['edit_student_email'];
    $sql = "UPDATE student SET StudentName=?, StudentContact=?, StudentEmail=? WHERE StudentID=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $sname, $scontact, $semail, $sid);
    if ($stmt->execute()) {
        $_SESSION['message'] = "<div class='alert alert-success'>Student updated successfully!</div>";
    } else {
        $_SESSION['message'] = "<div class='alert alert-danger'>Error updating student: {$stmt->error}</div>";
    }
    header("Location: ManageUser.php");
    exit();
}

// Handle edit (Staff)
if (isset($_POST['edit_staff_submit'])) {
    $sid = $_POST['edit_staff_id'];
    $sname = $_POST['edit_staff_name'];
    $scontact = $_POST['edit_staff_contact'];
    $semail = $_POST['edit_staff_email'];
    $position = $_POST['edit_staff_position'];
    $sql = "UPDATE staff SET StaffName=?, StaffContact=?, StaffEmail=?, Roles=? WHERE StaffID=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $sname, $scontact, $semail, $position, $sid);
    if ($stmt->execute()) {
        $_SESSION['message'] = "<div class='alert alert-success'>Staff updated successfully!</div>";
    } else {
        $_SESSION['message'] = "<div class='alert alert-danger'>Error updating staff: {$stmt->error}</div>";
    }
    header("Location: ManageUser.php");
    exit();
}

// Handle add new user 
if (isset($_POST['add_user_submit'])) {
    $usertype = $_POST['add_user_type'];
    
    if ($usertype == 'student') {
        $sid = $_POST['add_student_id'];
        $sname = $_POST['add_student_name'];
        $scontact = $_POST['add_student_contact'];
        $semail = $_POST['add_student_email'];
        $spass = $_POST['add_student_password'];
        
        $check = $conn->prepare("SELECT * FROM student WHERE StudentID=?");
        $check->bind_param("s", $sid);
        $check->execute();
        $res = $check->get_result();
        
        if ($res->num_rows > 0) {
            $_SESSION['message'] = "<div class='alert alert-danger'>Student ID already exists!</div>";
        } else {
            // Hash the password before storing
            $hashed_password = password_hash($spass, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO student (StudentID, StudentName, StudentContact, StudentEmail, StudentPassword) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssss", $sid, $sname, $scontact, $semail, $spass);
            if ($stmt->execute()) {
                $_SESSION['message'] = "<div class='alert alert-success'>New student added successfully!</div>";
            } else {
                $_SESSION['message'] = "<div class='alert alert-danger'>Error adding student: {$stmt->error}</div>";
            }
        }
    } else if ($usertype == 'securitystaff') {
        $sid = $_POST['add_staff_id'];
        $sname = $_POST['add_staff_name'];
        $scontact = $_POST['add_staff_contact'];
        $semail = $_POST['add_staff_email'];
        $spass = $_POST['add_staff_password'];
        $roles = "SecurityStaff";
        
        $check = $conn->prepare("SELECT * FROM staff WHERE StaffID=?");
        $check->bind_param("s", $sid);
        $check->execute();
        $res = $check->get_result();
        
        if ($res->num_rows > 0) {
            $_SESSION['message'] = "<div class='alert alert-danger'>Staff ID already exists!</div>";
        } else {
            // Hash the password before storing
            $hashed_password = password_hash($spass, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO staff (StaffID, StaffName, StaffContact, StaffEmail, StaffPassword, Roles) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssss", $sid, $sname, $scontact, $semail, $hashed_password, $roles);
            if ($stmt->execute()) {
                $_SESSION['message'] = "<div class='alert alert-success'>New Security Staff added successfully!</div>";
            } else {
                $_SESSION['message'] = "<div class='alert alert-danger'>Error adding Security Staff: {$stmt->error}</div>";
            }
        }
    } else if ($usertype == 'administrator') {
        $sid = $_POST['add_staff_id'];
        $sname = $_POST['add_staff_name'];
        $scontact = $_POST['add_staff_contact'];
        $semail = $_POST['add_staff_email'];
        $spass = $_POST['add_staff_password'];
        $roles = "Administrator";
        
        $check = $conn->prepare("SELECT * FROM staff WHERE StaffID=?");
        $check->bind_param("s", $sid);
        $check->execute();
        $res = $check->get_result();
        
        if ($res->num_rows > 0) {
            $_SESSION['message'] = "<div class='alert alert-danger'>Staff ID already exists!</div>";
        } else {
            // Hash the password before storing
            $hashed_password = password_hash($spass, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO staff (StaffID, StaffName, StaffContact, StaffEmail, StaffPassword, Roles) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssss", $sid, $sname, $scontact, $semail, $hashed_password, $roles);
            if ($stmt->execute()) {
                $_SESSION['message'] = "<div class='alert alert-success'>New Administrator added successfully!</div>";
            } else {
                $_SESSION['message'] = "<div class='alert alert-danger'>Error adding Administrator: {$stmt->error}</div>";
            }
        }
    }
    
    header("Location: ManageUser.php");
    exit();
}

// Fetch all students
$students = [];
$result = $conn->query("SELECT * FROM student");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
}

// Fetch staff with Security Staff Roles
$security_staff = [];
$result2 = $conn->query("SELECT * FROM staff WHERE Roles = 'SecurityStaff'");
if ($result2) {
    while ($row = $result2->fetch_assoc()) {
        $security_staff[] = $row;
    }
}

// Fetch staff with Administrator Roles
$admin_staff = [];
$result3 = $conn->query("SELECT * FROM staff WHERE Roles = 'Administrator'");
if ($result3) {
    while ($row = $result3->fetch_assoc()) {
        $admin_staff[] = $row;
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


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
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
        .menu { display: flex; flex-direction: column; gap: 18px; padding: 0; margin: 0; list-style: none; }
        .menutext { background-color: rgba(255, 255, 255, 0.1); border-radius: 6px; padding: 14px 18px; color: white; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 20px; }
        .menu a { text-decoration: none; color: inherit; }
        .menutext:hover { background-color: #a03198d5; color: white;}
        .menutext.active { background-color: #a03198d5; font-weight: 500; color: white;}
        .profile { background-color: #7405f1ff; color: white; border: 1px solid rgba(0, 0, 0, 0.3); padding: 8px 15px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; transition: all 0.2s; text-decoration: none; }
        .profile:hover { background-color: #2e0c55ff; }
        .logoutbutton { background-color: rgba(255, 0, 0, 0.81); color: white; border: 1px solid rgba(0, 0, 0, 0.3); padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; gap: 8px; text-decoration: none; }
        .main-container { margin-left: 250px; margin-top: 120px; padding: 40px; box-sizing: border-box; flex: 1; transition: margin-left 0.3s ease; width: calc(100% - 250px); }
        .main-container.sidebar-collapsed { margin-left: 0; width: 100%; }
        .content-card { background-color: white; padding: 25px; border-radius: 8px; margin-bottom: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.05); }
        table { width: 100%; background: #fafafa; margin-bottom: 20px; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #8ecae6; color: #222; font-weight: 600; }
        tr:nth-child(even) { background: #f1f8fc; }
        .btn-action { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; margin-right: 5px; }
        .btn-edit { background-color: #3498db; color: white; }
        .btn-edit:hover { background-color: #2980b9; color: white; }
        .btn-delete { background-color: #e74c3c; color: white; }
        .btn-delete:hover { background-color: #c0392b; color: white; }
        .btn-add { background-color: #008CCF; color: white; padding: 12px 30px; border: none; border-radius: 5px; font-weight: 500; cursor: pointer; display: inline-block; text-decoration: none; font-size: 1.1rem; }
        .btn-add:hover { background-color: #005f8f; color: white; }
        .no-data { text-align: center; color: #888; padding: 20px; font-style: italic; }
        footer { background-color: #b8a6ccff; color: white; padding: 15px 0; text-align: center; width: 100%; margin-top: auto; position: relative; bottom: 0; left: 0; }
        footer.sidebar-collapsed { margin-left: 0; }
        .modal-content { border-radius: 8px; border: none; box-shadow: 0 5px 20px rgba(0,0,0,0.2); }
        .modal-header { background-color: #6a22bdff; color: white; border-radius: 8px 8px 0 0; }
        .modal-title { font-weight: 600; }
        .btn-primary { background-color: #6a22bdff; border-color: #6a22bdff; }
        .btn-primary:hover { background-color: #4e1692; border-color: #4e1692; }
        @media (max-width: 768px) { .main-container { margin-left: 0; width: 100%; padding: 20px; } table { display: block; overflow-x: auto; } }
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
        <h1 class="sidebartitle"><strong>Admin</strong></h1>
        <ul class="menu">
            <li>
                <a href="AdminDashboard.php" class="menutext">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="ManageUser.php" class="menutext active">
                    <i class="fas fa-users"></i> Manage User
                </a>
            </li>
            <li>
                <a href="ParkingManagement.php" class="menutext">
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
        
        <!-- Flash Message -->
        <?php if (!empty($_SESSION['message'])): ?>
            <div class="mb-4"><?php echo $_SESSION['message']; $_SESSION['message'] = ""; ?></div>
        <?php endif; ?>
        
        <!-- Student Table -->
        <div class="content-card">
            <h4 class="mb-4"><i class="fas fa-user-graduate"></i> Student List</h4>
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Student Contact</th>
                        <th>Student Email</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="5" class="no-data">No students found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $stud): ?>
                        <tr>
                            <td><?= htmlspecialchars($stud['StudentID']) ?></td>
                            <td><?= htmlspecialchars($stud['StudentName']) ?></td>
                            <td><?= htmlspecialchars($stud['StudentContact']) ?></td>
                            <td><?= htmlspecialchars($stud['StudentEmail']) ?></td>
                            <td>
                                <button class="btn-action btn-edit" onclick="openEditStudentModal('<?=htmlspecialchars($stud['StudentID'])?>','<?=htmlspecialchars(addslashes($stud['StudentName']))?>','<?=htmlspecialchars($stud['StudentContact'])?>','<?=htmlspecialchars($stud['StudentEmail'])?>')">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="?delete_student=<?= urlencode($stud['StudentID']) ?>" class="btn-action btn-delete" onclick="return confirm('Are you sure you want to delete this student?');">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Security Staff Table -->
        <div class="content-card">
            <h4 class="mb-4"><i class="fas fa-shield-alt"></i> Security Staff List</h4>
            <table>
                <thead>
                    <tr>
                        <th>Staff ID</th>
                        <th>Staff Name</th>
                        <th>Staff Contact</th>
                        <th>Staff Email</th>
                        <th>Roles</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($security_staff)): ?>
                        <tr>
                            <td colspan="6" class="no-data">No Security Staff found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($security_staff as $stf): ?>
                        <tr>
                            <td><?= htmlspecialchars($stf['StaffID']) ?></td>
                            <td><?= htmlspecialchars($stf['StaffName']) ?></td>
                            <td><?= htmlspecialchars($stf['StaffContact']) ?></td>
                            <td><?= htmlspecialchars($stf['StaffEmail']) ?></td>
                            <td><?= htmlspecialchars($stf['Roles']) ?></td>
                            <td>
                                <button class="btn-action btn-edit" onclick="openEditStaffModal('<?=htmlspecialchars($stf['StaffID'])?>','<?=htmlspecialchars(addslashes($stf['StaffName']))?>','<?=htmlspecialchars($stf['StaffContact'])?>','<?=htmlspecialchars($stf['StaffEmail'])?>','<?=htmlspecialchars($stf['Roles'])?>')">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="?delete_staff=<?= urlencode($stf['StaffID']) ?>" class="btn-action btn-delete" onclick="return confirm('Are you sure you want to delete this staff member?');">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Admin Staff Table -->
        <div class="content-card">
            <h4 class="mb-4"><i class="fas fa-user-tie"></i> Administrator List</h4>
            <table>
                <thead>
                    <tr>
                        <th>Staff ID</th>
                        <th>Staff Name</th>
                        <th>Staff Contact</th>
                        <th>Staff Email</th>
                        <th>Roles</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($admin_staff)): ?>
                        <tr>
                            <td colspan="6" class="no-data">No Administrators found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($admin_staff as $stf): ?>
                        <tr>
                            <td><?= htmlspecialchars($stf['StaffID']) ?></td>
                            <td><?= htmlspecialchars($stf['StaffName']) ?></td>
                            <td><?= htmlspecialchars($stf['StaffContact']) ?></td>
                            <td><?= htmlspecialchars($stf['StaffEmail']) ?></td>
                            <td><?= htmlspecialchars($stf['Roles']) ?></td>
                            <td>
                                <button class="btn-action btn-edit" onclick="openEditStaffModal('<?=htmlspecialchars($stf['StaffID'])?>','<?=htmlspecialchars(addslashes($stf['StaffName']))?>','<?=htmlspecialchars($stf['StaffContact'])?>','<?=htmlspecialchars($stf['StaffEmail'])?>','<?=htmlspecialchars($stf['Roles'])?>')">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="?delete_staff=<?= urlencode($stf['StaffID']) ?>" class="btn-action btn-delete" onclick="return confirm('Are you sure you want to delete this administrator?');">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Add User Button -->
        <div class="text-center mt-4">
            <button class="btn-add" onclick="openAddUserModal()">
                <i class="fas fa-plus-circle"></i> Add New User
            </button>
        </div>
    </div>

    <!-- Add User Modal (Bootstrap Modal) -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" id="addUserForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">User Type *</label>
                            <select name="add_user_type" id="add_user_type" class="form-select" required onchange="toggleAddUserFields()">
                                <option value="">Select Type</option>
                                <option value="student">Student</option>
                                <option value="securitystaff">Security Staff</option>
                                <option value="administrator">Administrator</option>
                            </select>
                        </div>
                        <div id="studentFields" style="display:none;">
                            <div class="mb-3">
                                <label class="form-label">Student ID *</label>
                                <input type="text" name="add_student_id" id="add_student_id" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Name *</label>
                                <input type="text" name="add_student_name" id="add_student_name" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contact *</label>
                                <input type="text" name="add_student_contact" id="add_student_contact" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" name="add_student_email" id="add_student_email" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password *</label>
                                <input type="text" name="add_student_password" id="add_student_password" class="form-control" placeholder="Enter password">
                                <small class="text-muted">Password will be hashed for security</small>
                            </div>
                        </div>
                        <div id="staffFields" style="display:none;">
                            <div class="mb-3">
                                <label class="form-label">Staff ID *</label>
                                <input type="text" name="add_staff_id" id="add_staff_id" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Name *</label>
                                <input type="text" name="add_staff_name" id="add_staff_name" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contact *</label>
                                <input type="text" name="add_staff_contact" id="add_staff_contact" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" name="add_staff_email" id="add_staff_email" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password *</label>
                                <input type="text" name="add_staff_password" id="add_staff_password" class="form-control" placeholder="Enter password">
                                <small class="text-muted">Password will be hashed for security</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_user_submit" class="btn btn-primary">Add User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Student Modal (Bootstrap Modal) -->
    <div class="modal fade" id="editStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Student ID</label>
                            <input type="text" name="edit_student_id" id="edit_student_id" class="form-control" readonly style="background-color: #e9ecef;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" name="edit_student_name" id="edit_student_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact *</label>
                            <input type="text" name="edit_student_contact" id="edit_student_contact" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="edit_student_email" id="edit_student_email" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_student_submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Staff Modal (Bootstrap Modal) -->
    <div class="modal fade" id="editStaffModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Staff</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Staff ID</label>
                            <input type="text" name="edit_staff_id" id="edit_staff_id" class="form-control" readonly style="background-color: #e9ecef;">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" name="edit_staff_name" id="edit_staff_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact *</label>
                            <input type="text" name="edit_staff_contact" id="edit_staff_contact" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="edit_staff_email" id="edit_staff_email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Position</label>
                            <input type="text" name="edit_staff_position" id="edit_staff_position" class="form-control" readonly style="background-color: #e9ecef;">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_staff_submit" class="btn btn-primary">Save Changes</button>
                    </div>
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

        // Sidebar Toggle Functionality (same as ParkingArea.php)
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

        // Modal Functions
        function toggleAddUserFields() {
            const type = document.getElementById('add_user_type').value;
            document.getElementById('studentFields').style.display = type === 'student' ? 'block' : 'none';
            document.getElementById('staffFields').style.display = (type === 'securitystaff' || type === 'administrator') ? 'block' : 'none';
        }

        function openAddUserModal() {
            const modal = new bootstrap.Modal(document.getElementById('addUserModal'));
            modal.show();
            // Reset form
            document.getElementById('add_user_type').value = '';
            toggleAddUserFields();
        }

        function openEditStudentModal(id, name, contact, email) {
            document.getElementById('edit_student_id').value = id;
            document.getElementById('edit_student_name').value = name;
            document.getElementById('edit_student_contact').value = contact;
            document.getElementById('edit_student_email').value = email;
            const modal = new bootstrap.Modal(document.getElementById('editStudentModal'));
            modal.show();
        }

        function openEditStaffModal(id, name, contact, email, position) {
            document.getElementById('edit_staff_id').value = id;
            document.getElementById('edit_staff_name').value = name;
            document.getElementById('edit_staff_contact').value = contact;
            document.getElementById('edit_staff_email').value = email;
            document.getElementById('edit_staff_position').value = position;
            const modal = new bootstrap.Modal(document.getElementById('editStaffModal'));
            modal.show();
        }
    </script>
</body>
</html>