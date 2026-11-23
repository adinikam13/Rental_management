<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    die("Invalid customer ID.");
}

// Check whether customer has rental records
$check = $pdo->prepare(
    "SELECT id FROM rentals WHERE customer_id = :id LIMIT 1"
);
$check->execute([':id' => $id]);

if ($check->fetch()) {
    die("This customer cannot be deleted because rental records exist.");
}

// Delete customer
$stmt = $pdo->prepare(
    "DELETE FROM customers WHERE id = :id"
);
$stmt->execute([':id' => $id]);

if ($stmt->rowCount() > 0) {
    header("Location: view_customers.php");
    exit();
} else {
    die("Customer not found.");
}
?>