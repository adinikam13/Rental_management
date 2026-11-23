<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$error = "";
$success = "";

// Fetch rentals
$rentalStmt = $pdo->query(
    "SELECT
        r.id,
        c.name AS customer_name,
        r.status
     FROM rentals r
     JOIN customers c ON r.customer_id = c.id
     ORDER BY r.id DESC"
);

$rentals = $rentalStmt->fetchAll();

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $rental_id = filter_input(INPUT_POST, 'rental_id', FILTER_VALIDATE_INT);
    $amount = $_POST['amount'] ?? '';
    $payment_type = $_POST['payment_type'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    $allowedTypes = [
        'rent',
        'security_deposit',
        'late_fee',
        'refund'
    ];

    $allowedMethods = [
        'cash',
        'upi',
        'bank_transfer',
        'card'
    ];

    if (!$rental_id || $rental_id <= 0) {
        $error = "Please select a rental.";
    } elseif (!is_numeric($amount) || (float)$amount <= 0) {
        $error = "Enter a valid payment amount.";
    } elseif (!in_array($payment_type, $allowedTypes, true)) {
        $error = "Select a valid payment type.";
    } elseif (!in_array($payment_method, $allowedMethods, true)) {
        $error = "Select a valid payment method.";
    } else {

        try {

            $checkStmt = $pdo->prepare(
                "SELECT id FROM rentals WHERE id = :id"
            );

            $checkStmt->execute([':id' => $rental_id]);

            if (!$checkStmt->fetch()) {
                throw new Exception("Selected rental does not exist.");
            }

            $insertStmt = $pdo->prepare(
                "INSERT INTO payments
                 (rental_id, amount, payment_type, payment_method, notes)
                 VALUES
                 (:rental_id, :amount, :payment_type, :payment_method, :notes)"
            );

            $insertStmt->execute([
                ':rental_id' => $rental_id,
                ':amount' => $amount,
                ':payment_type' => $payment_type,
                ':payment_method' => $payment_method,
                ':notes' => $notes
            ]);

            $success = "Payment recorded successfully!";

        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Record Payment | Event Rental Management</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .payment-description {
            color: var(--muted);
            margin-bottom: 25px;
        }

        .payment-form {
            max-width: 750px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 9px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            background: var(--surface);
            box-sizing: border-box;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 110px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 28px;
        }

        .message {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .error-message {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .success-message {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
        }

        @media (max-width: 600px) {
            .form-actions {
                flex-direction: column;
            }

            .form-actions button,
            .form-actions a {
                width: 100%;
                text-align: center;
                box-sizing: border-box;
            }
        }
    </style>
</head>

<body>

    <div class="navbar">
        <strong>Event Rental Management</strong>
        <a href="../auth/logout.php" class="logout-link">Logout</a>
    </div>

    <main class="container">

        <a href="../dashboard.php" class="back-link">
            ← Back to Dashboard
        </a>

        <div class="card payment-form">

            <h1>Record Payment</h1>

            <p class="payment-description">
                Enter the payment details received from the customer.
            </p>

            <?php if ($error): ?>
                <div class="message error-message">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="message success-message">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <div class="form-group">
                    <label>Select Rental</label>

                    <select name="rental_id" required>
                        <option value="">Choose Rental</option>

                        <?php foreach ($rentals as $rental): ?>
                            <option
                                value="<?= $rental['id'] ?>"
                                <?= (isset($rental_id) && $rental_id == $rental['id']) ? 'selected' : '' ?>
                            >
                                Rental #<?= $rental['id'] ?>
                                - <?= htmlspecialchars($rental['customer_name']) ?>
                                (<?= htmlspecialchars($rental['status']) ?>)
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <div class="form-group">
                    <label>Amount (₹)</label>

                    <input
                        type="number"
                        name="amount"
                        min="0.01"
                        step="0.01"
                        value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>"
                        placeholder="Enter payment amount"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Payment Type</label>

                    <select name="payment_type" required>
                        <option value="">Choose Payment Type</option>
                        <option value="rent" <?= (($_POST['payment_type'] ?? '') === 'rent') ? 'selected' : '' ?>>Rent</option>
                        <option value="security_deposit" <?= (($_POST['payment_type'] ?? '') === 'security_deposit') ? 'selected' : '' ?>>Security Deposit</option>
                        <option value="late_fee" <?= (($_POST['payment_type'] ?? '') === 'late_fee') ? 'selected' : '' ?>>Late Fee</option>
                        <option value="refund" <?= (($_POST['payment_type'] ?? '') === 'refund') ? 'selected' : '' ?>>Refund</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Payment Method</label>

                    <select name="payment_method" required>
                        <option value="">Choose Payment Method</option>
                        <option value="cash" <?= (($_POST['payment_method'] ?? '') === 'cash') ? 'selected' : '' ?>>Cash</option>
                        <option value="upi" <?= (($_POST['payment_method'] ?? '') === 'upi') ? 'selected' : '' ?>>UPI</option>
                        <option value="bank_transfer" <?= (($_POST['payment_method'] ?? '') === 'bank_transfer') ? 'selected' : '' ?>>Bank Transfer</option>
                        <option value="card" <?= (($_POST['payment_method'] ?? '') === 'card') ? 'selected' : '' ?>>Card</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Notes (Optional)</label>

                    <textarea
                        name="notes"
                        placeholder="Enter any additional payment details"
                    ><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn">
                        Save Payment
                    </button>

                    <a href="../dashboard.php" class="btn">
                        Cancel
                    </a>
                </div>

            </form>

        </div>

    </main>

</body>
</html>