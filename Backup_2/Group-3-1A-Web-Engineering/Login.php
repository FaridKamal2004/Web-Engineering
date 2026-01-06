<?php
// Start the session
session_start();

// Database connection parameters
$conn = new mysqli("localhost", "root", "", "FKParkSystem", 3306);

// Check if database connection failed
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize error message variable
$error = '';

// Handle login form submission when POST request is received
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'], $_POST['type_user'])) {
    // Sanitize user inputs
    $userType = $conn->real_escape_string($_POST['type_user']);
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password']; // Password not escaped because we hash/verify it

    // Validate that required fields aren't empty
    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        // Prepare different SQL queries based on user type
        if ($userType === 'student') {
            $query = "SELECT * FROM student WHERE StudentID = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $username);
        } elseif ($userType === 'Administrator') {
            $query = "SELECT * FROM staff WHERE StaffID = ? AND Roles = 'Administrator'";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $username);
        } elseif ($userType === 'SecurityStaff') {
            $query = "SELECT * FROM staff WHERE StaffID = ? AND Roles = 'SecurityStaff'";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $username);
        } else {
            $error = "Invalid user type selected";
        }

        if (empty($error)) {
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Get correct password field
                $hashedPassword = ($userType === 'student') ? $user['StudentPassword'] : $user['StaffPassword'];

                // Check if password is already hashed (bcrypt starts with $2y$)
                $isHashed = strpos($hashedPassword, '$2y$') === 0;

                // Verify password
                if (
                    ($isHashed && password_verify($password, $hashedPassword)) ||
                    (!$isHashed && $password === $hashedPassword)
                ) {
                    // If password was plaintext, hash it now and update DB
                    if (!$isHashed) {
                        $newHashedPassword = password_hash($password, PASSWORD_DEFAULT);

                        if ($userType === 'student') {
                            $updateStmt = $conn->prepare("UPDATE student SET StudentPassword = ? WHERE StudentID = ?");
                        } else {
                            $updateStmt = $conn->prepare("UPDATE staff SET StaffPassword = ? WHERE StaffID = ?");
                        }

                        $updateStmt->bind_param("ss", $newHashedPassword, $username);
                        $updateStmt->execute();
                        $updateStmt->close();
                    }

                    // Store user data in session
                    $_SESSION['user_id'] = $username;
                    $_SESSION['type_user'] = $userType;
                    $_SESSION['user_data'] = $user;
                    $_SESSION['last_activity'] = time();

                    // Redirect
                    switch ($userType) {
                        case 'student':
                            header("Location: StudentDashboard.php");
                            break;
                        case 'Administrator':
                            header("Location: AdminDashboard.php");
                            break;
                        case 'SecurityStaff':
                            header("Location: SecurityStaffDashboard.php");
                            break;
                    }
                    exit();
                } else {
                    $error = "Incorrect password.";
                }
            } else {
                $error = "User not found or invalid credentials.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>FKParkSystem Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: url("FKPark.png") no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            width: 320px;
        }
        .login-box h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #005b96;
        }
        .login-box input[type="text"],
        .login-box input[type="password"] {
            width: 100%;
            padding: 12px 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .login-box button {
            width: 100%;
            padding: 12px;
            background: #005b96;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        .login-box button:hover {
            background: #004080;
        }
        .login-box select {
            width: 100%;
            padding: 12px 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .error-message {
            color: red;
            text-align: center;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Login to FK Park System</h2>
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="" method="POST">
            <input type="text" name="username" placeholder="User ID" required>
            <input type="password" name="password" placeholder="Password" required>
            <select name="type_user" required>
                <option value="" disabled selected>Select Roles</option>
                <option value="student">Student</option>
                <option value="Administrator">Administrator</option>
                <option value="SecurityStaff">Security Management Unit Staff</option>
            </select>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>