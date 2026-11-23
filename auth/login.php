<?php

session_start();

require_once '../config/database.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {

        $error = "Please enter username and password.";

    } else {

        $sql = "SELECT * FROM users WHERE username = :username";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':username' => $username
        ]);

        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];

            header("Location: ../dashboard.php");
            exit();

        } else {

            $error = "Invalid username or password.";

        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Event Rental Management</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
            box-sizing: border-box;
        }

        .login-wrapper {
            width: 100%;
            max-width: 430px;
        }

        .login-heading {
            text-align: center;
            margin-bottom: 28px;
        }

        .login-icon {
            width: 65px;
            height: 65px;
            margin: 0 auto 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--background);
            border-radius: 16px;
            font-size: 30px;
        }

        .login-heading h1 {
            font-size: 25px;
            margin-bottom: 8px;
        }

        .login-heading p {
            color: var(--muted);
            margin: 0;
        }

        .login-card {
            width: 100%;
            box-sizing: border-box;
        }

        .login-card h2 {
            margin-top: 0;
            margin-bottom: 8px;
        }

        .login-description {
            color: var(--muted);
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 9px;
        }

        .form-group input {
            width: 100%;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            background: var(--surface);
        }

        .login-button {
            width: 100%;
            padding: 14px;
            font-size: 16px;
            margin-top: 8px;
            cursor: pointer;
        }

        .error-message {
            padding: 13px;
            margin-bottom: 20px;
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            border-radius: 8px;
        }

        .login-footer {
            text-align: center;
            color: var(--muted);
            font-size: 13px;
            margin-top: 25px;
        }

        @media (max-width: 480px) {
            .login-heading h1 {
                font-size: 21px;
            }

            .login-card {
                padding: 22px;
            }
        }
    </style>
</head>

<body>

    <div class="login-wrapper">

        <div class="login-heading">

            <div class="login-icon">📦</div>

            <h1>PARTY LAND</h1>

            <p>Manage your rental business with ease.</p>

        </div>

        <div class="card login-card">

            <h2>Welcome Back</h2>

            <p class="login-description">
                Please login to continue.
            </p>

            <?php if ($error): ?>

                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>

            <form method="POST">

                <div class="form-group">

                    <label>Username</label>

                    <input
                        type="text"
                        name="username"
                        placeholder="Enter your username"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        autocomplete="username"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>Password</label>

                    <input
                        type="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >

                </div>

                <button type="submit" class="btn login-button">
                    Login
                </button>

            </form>

        </div>

        <div class="login-footer">
            Event Rental Management System
        </div>

    </div>

</body>
</html>