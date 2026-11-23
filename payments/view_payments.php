<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch all payment records
$stmt = $pdo->query(
    "SELECT
        p.id,
        p.rental_id,
        c.name AS customer_name,
        p.amount,
        p.payment_type,
        p.payment_method,
        p.payment_date,
        p.notes
     FROM payments p
     JOIN rentals r ON p.rental_id = r.id
     JOIN customers c ON r.customer_id = c.id
     ORDER BY p.payment_date DESC"
);

$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format payment labels
function formatPaymentLabel($value) {
    return ucwords(str_replace('_', ' ', $value ?? ''));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Payment History | Event Rental Management</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .page-description {
            color: var(--muted);
            margin-bottom: 22px;
        }

        .payment-summary {
            margin-bottom: 22px;
        }

        .summary-label {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 6px;
        }

        .summary-value {
            font-size: 25px;
            font-weight: 700;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 10px;
        }

        .payment-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 950px;
        }

        .payment-table th,
        .payment-table td {
            padding: 15px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .payment-table th {
            background: var(--background);
            font-weight: 600;
            white-space: nowrap;
        }

        .payment-table tbody tr:last-child td {
            border-bottom: none;
        }

        .payment-table tbody tr:hover {
            background: var(--background);
        }

        .payment-type {
            display: inline-block;
            padding: 6px 10px;
            background: #eff6ff;
            color: #1d4ed8;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        .payment-amount {
            font-weight: 700;
            white-space: nowrap;
        }

        .empty-state {
            text-align: center;
            padding: 35px 15px;
            color: var(--muted);
        }

        .page-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 22px;
        }

        @media (max-width: 600px) {
            .page-actions {
                flex-direction: column;
            }

            .page-actions a {
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

        <a href="../dashboard.php">← Back to Dashboard</a>

        <div class="card">

            <h1>Payment History</h1>

            <p class="page-description">
                View all payments recorded for your rental customers.
            </p>

            <div class="payment-summary">
                <div class="summary-label">Total Payment Records</div>
                <div class="summary-value">
                    <?= count($payments) ?>
                </div>
            </div>

            <?php if (count($payments) > 0): ?>

                <div class="table-wrapper">

                    <table class="payment-table">

                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Customer</th>
                                <th>Rental ID</th>
                                <th>Amount</th>
                                <th>Payment Type</th>
                                <th>Payment Method</th>
                                <th>Payment Date</th>
                                <th>Notes</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($payments as $payment): ?>

                                <tr>

                                    <td>
                                        #<?= htmlspecialchars($payment['id']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($payment['customer_name']) ?>
                                    </td>

                                    <td>
                                        #<?= htmlspecialchars($payment['rental_id']) ?>
                                    </td>

                                    <td class="payment-amount">
                                        ₹<?= htmlspecialchars($payment['amount']) ?>
                                    </td>

                                    <td>
                                        <span class="payment-type">
                                            <?= htmlspecialchars(formatPaymentLabel($payment['payment_type'])) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(formatPaymentLabel($payment['payment_method'])) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($payment['payment_date']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($payment['notes'] ?? '') ?: '—' ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">
                    <h3>No Payment Records Found</h3>
                    <p>Payments will appear here after they are recorded.</p>
                </div>

            <?php endif; ?>

            <div class="page-actions">

                <a href="add_payment.php" class="btn">
                    + Add New Payment
                </a>

                <a href="../dashboard.php" class="btn">
                    Back to Dashboard
                </a>

            </div>

        </div>

    </main>

</body>
</html>