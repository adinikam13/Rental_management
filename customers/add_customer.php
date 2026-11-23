<?php
session_start();
require_once '../config/database.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$error = "";

$name = "";
$company_name = "";
$phone = "";
$email = "";
$address = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST['name'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '' || $phone === '') {

        $error = "Customer name and phone number are required.";

    } else {

        try {

            $sql = "INSERT INTO customers
                    (name, company_name, phone, email, address)
                    VALUES
                    (:name, :company_name, :phone, :email, :address)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':name' => $name,
                ':company_name' => $company_name ?: null,
                ':phone' => $phone,
                ':email' => $email ?: null,
                ':address' => $address ?: null
            ]);

            $message = "Customer added successfully!";

            // Clear fields after successful submission
            $name = "";
            $company_name = "";
            $phone = "";
            $email = "";
            $address = "";

        } catch (PDOException $e) {

            $error = "Unable to add customer. Please try again.";

        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Customer | Event Rental Management</title>

    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

    <div class="navbar">

        <strong>Event Rental Management</strong>

        <a href="../auth/logout.php" class="logout-link">Logout</a>

    </div>

    <main class="container">

        <a href="../dashboard.php">← Back to Dashboard</a>

        <div class="card">

            <h1>Add New Customer</h1>

            <p style="color: var(--muted);">
                Enter the customer's details below.
            </p>

            <?php if ($message): ?>

                <div class="success-message">
                    <?= htmlspecialchars($message) ?>
                </div>

            <?php endif; ?>

            <?php if ($error): ?>

                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>

            <form method="POST">

                <label>Customer Name *</label>

                <input
                    type="text"
                    name="name"
                    placeholder="Enter customer name"
                    value="<?= htmlspecialchars($name) ?>"
                    required
                >

                <label>Company Name</label>

                <input
                    type="text"
                    name="company_name"
                    placeholder="Enter company name (optional)"
                    value="<?= htmlspecialchars($company_name) ?>"
                >

                <label>Phone Number *</label>

                <input
                    type="tel"
                    name="phone"
                    placeholder="Enter phone number"
                    value="<?= htmlspecialchars($phone) ?>"
                    required
                >

                <label>Email Address</label>

                <input
                    type="email"
                    name="email"
                    placeholder="Enter email address (optional)"
                    value="<?= htmlspecialchars($email) ?>"
                >

                <label>Address</label>

                <textarea
                    name="address"
                    rows="4"
                    placeholder="Enter customer address (optional)"
                ><?= htmlspecialchars($address) ?></textarea>

                <button type="submit">
                    Save Customer
                </button>

            </form>

        </div>

        <a href="view_customers.php">View All Customers</a>

    </main>

</body>
</html>