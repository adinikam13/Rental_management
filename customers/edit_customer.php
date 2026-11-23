<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$error = "";

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    die("Invalid customer ID.");
}

// Fetch existing customer
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = :id");
$stmt->execute([':id' => $id]);

$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    die("Customer not found.");
}

// Update customer
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST['name'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // Preserve submitted values if validation fails
    $customer['name'] = $name;
    $customer['company_name'] = $company_name;
    $customer['phone'] = $phone;
    $customer['email'] = $email;
    $customer['address'] = $address;

    if ($name === '' || $phone === '') {

        $error = "Customer name and phone number are required.";

    } else {

        try {

            $sql = "UPDATE customers SET
                    name = :name,
                    company_name = :company_name,
                    phone = :phone,
                    email = :email,
                    address = :address
                    WHERE id = :id";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':name' => $name,
                ':company_name' => $company_name ?: null,
                ':phone' => $phone,
                ':email' => $email ?: null,
                ':address' => $address ?: null,
                ':id' => $id
            ]);

            header("Location: view_customers.php");
            exit();

        } catch (PDOException $e) {

            $error = "Unable to update customer. Please try again.";

        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Customer | Event Rental Management</title>

    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

    <div class="navbar">

        <strong>Event Rental Management</strong>

        <a href="../auth/logout.php" class="logout-link">Logout</a>

    </div>

    <main class="container">

        <a href="view_customers.php">← Back to Customers</a>

        <div class="card">

            <h1>Edit Customer</h1>

            <p style="color: var(--muted);">
                Update the customer's information below.
            </p>

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
                    value="<?= htmlspecialchars($customer['name']) ?>"
                    required
                >

                <label>Company Name</label>

                <input
                    type="text"
                    name="company_name"
                    value="<?= htmlspecialchars($customer['company_name'] ?? '') ?>"
                >

                <label>Phone Number *</label>

                <input
                    type="tel"
                    name="phone"
                    value="<?= htmlspecialchars($customer['phone']) ?>"
                    required
                >

                <label>Email Address</label>

                <input
                    type="email"
                    name="email"
                    value="<?= htmlspecialchars($customer['email'] ?? '') ?>"
                >

                <label>Address</label>

                <textarea name="address" rows="4"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>

                <button type="submit">
                    Update Customer
                </button>

            </form>

        </div>

    </main>

</body>
</html>