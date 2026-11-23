<?php

session_start();

require_once '../config/database.php';

// Allow only admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $username === '' || $password === '') {

        $error = "All fields are required.";

    } elseif (strlen($password) < 8) {

        $error = "Password must contain at least 8 characters.";

    } else {

        // Check whether username already exists
        $check = $pdo->prepare(
            "SELECT id FROM users WHERE username = :username"
        );

        $check->execute([
            ':username' => $username
        ]);

        if ($check->fetch()) {

            $error = "Username already exists.";

        } else {

            // Securely hash password
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // Insert employee
            $sql = "INSERT INTO users
                    (name, username, password, role)
                    VALUES (:name, :username, :password, 'employee')";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':name' => $name,
                ':username' => $username,
                ':password' => $hashedPassword
            ]);

            $message = "Employee added successfully!";
        }
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Employee</title>
</head>

<body>

    <h1>Add New Employee</h1>

    <?php if ($message): ?>
        <p style="color:green;">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p style="color:red;">
            <?= htmlspecialchars($error) ?>
        </p>
    <?php endif; ?>

    <form method="POST">

        <label>Employee Name</label><br>
        <input type="text" name="name" required>

        <br><br>

        <label>Username</label><br>
        <input type="text" name="username" required>

        <br><br>

        <label>Password</label><br>
        <input type="password" name="password" required>

        <br><br>

        <button type="submit">Add Employee</button>

    </form>

    <br>

    <a href="../dashboard.php">Back to Dashboard</a>

</body>
</html>