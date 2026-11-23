<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    die("Invalid rental ID.");
}

// Fetch rental and customer details
$sql = "SELECT
            r.*,
            c.name AS customer_name,
            c.company_name,
            c.phone,
            c.email,
            c.address,
            u.name AS issued_by_name
        FROM rentals r
        JOIN customers c ON r.customer_id = c.id
        JOIN users u ON r.issued_by = u.id
        WHERE r.id = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$rental = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rental) {
    die("Rental record not found.");
}

// Fetch issued materials
$itemSql = "SELECT
                ri.id AS rental_item_id,
                ri.issued_quantity,
                ri.daily_rent,
                m.material_name
            FROM rental_items ri
            JOIN materials m ON ri.material_id = m.id
            WHERE ri.rental_id = :id";

$itemStmt = $pdo->prepare($itemSql);
$itemStmt->execute([':id' => $id]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch return history and outstanding quantities
$returnSql = "SELECT
                  ri.id AS rental_item_id,
                  m.material_name,
                  ri.issued_quantity,
                  COALESCE(SUM(ret.good_quantity), 0) AS good_quantity,
                  COALESCE(SUM(ret.damaged_quantity), 0) AS damaged_quantity,
                  COALESCE(SUM(ret.missing_quantity), 0) AS missing_quantity,
                  GREATEST(
                      ri.issued_quantity -
                      COALESCE(SUM(
                          ret.good_quantity +
                          ret.damaged_quantity +
                          ret.missing_quantity
                      ), 0),
                      0
                  ) AS outstanding_quantity
              FROM rental_items ri
              JOIN materials m ON ri.material_id = m.id
              LEFT JOIN returns ret ON ret.rental_item_id = ri.id
              WHERE ri.rental_id = :id
              GROUP BY ri.id, m.material_name, ri.issued_quantity
              ORDER BY ri.id";

$returnStmt = $pdo->prepare($returnSql);
$returnStmt->execute([':id' => $id]);
$returnHistory = $returnStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch payment details
$paymentStmt = $pdo->prepare(
    "SELECT amount, payment_type, payment_date, payment_method
     FROM payments
     WHERE rental_id = :id
     ORDER BY id ASC"
);
$paymentStmt->execute([':id' => $id]);
$payments = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

$status = strtolower($rental['status']);

$statusClass = 'status-active';

if ($status === 'completed') {
    $statusClass = 'status-completed';
} elseif ($status === 'cancelled') {
    $statusClass = 'status-cancelled';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Rental Details | Event Rental Management</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .page-description {
            color: var(--muted);
            margin-bottom: 22px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }

        .detail-item {
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 15px;
            overflow-wrap: anywhere;
        }

        .detail-label {
            display: block;
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 6px;
        }

        .detail-value {
            font-weight: 600;
        }

        .section-heading {
            margin-top: 30px;
            margin-bottom: 16px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-completed {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .empty-message {
            padding: 18px;
            background: var(--background);
            border-radius: 10px;
            color: var(--muted);
        }

        .back-button {
            margin-top: 25px;
        }

        @media (max-width: 600px) {
            .details-grid {
                grid-template-columns: 1fr;
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

        <a href="view_rentals.php">← Back to Rentals</a>

        <div class="card">

            <h1>Rental Details #<?= (int)$rental['id'] ?></h1>

            <p class="page-description">
                Complete information about this rental transaction.
            </p>

            <span class="status-badge <?= $statusClass ?>">
                <?= htmlspecialchars($rental['status']) ?>
            </span>

            <!-- Customer Information -->

            <h2 class="section-heading">Customer Information</h2>

            <div class="details-grid">

                <div class="detail-item">
                    <span class="detail-label">Customer Name</span>
                    <span class="detail-value">
                        <?= htmlspecialchars($rental['customer_name']) ?>
                    </span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Company</span>
                    <span class="detail-value">
                        <?= htmlspecialchars($rental['company_name'] ?? 'Not provided') ?>
                    </span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Phone</span>
                    <span class="detail-value">
                        <?= htmlspecialchars($rental['phone'] ?? '') ?>
                    </span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Email</span>
                    <span class="detail-value">
                        <?= htmlspecialchars($rental['email'] ?? 'Not provided') ?>
                    </span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Address</span>
                    <span class="detail-value">
                        <?= htmlspecialchars($rental['address'] ?? 'Not provided') ?>
                    </span>
                </div>

            </div>

            <!-- Rental Information -->

            <h2 class="section-heading">Rental Information</h2>

            <div class="details-grid">

                <div class="detail-item">
                    <span class="detail-label">Rental ID</span>
                    <span class="detail-value">
                        #<?= (int)$rental['id'] ?>
                    </span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Issue Date</span>
                    <span class="detail-value">
                        <?= htmlspecialchars($rental['issue_date']) ?>
                    </span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Expected Return Date</span>
                    <span class="detail-value">
                        <?= htmlspecialchars($rental['expected_return_date']) ?>
                    </span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Security Deposit</span>
                    <span class="detail-value">
                        ₹<?= number_format((float)$rental['security_deposit'], 2) ?>
                    </span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Issued By</span>
                    <span class="detail-value">
                        <?= htmlspecialchars($rental['issued_by_name']) ?>
                    </span>
                </div>

            </div>

            <!-- Issued Materials -->

            <h2 class="section-heading">Issued Materials</h2>

            <?php if (count($items) > 0): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Quantity</th>
                                <th>Daily Rent</th>
                                <th>Daily Total</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($items as $item): ?>

                                <tr>

                                    <td>
                                        <strong>
                                            <?= htmlspecialchars($item['material_name']) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= (int)$item['issued_quantity'] ?>
                                    </td>

                                    <td>
                                        ₹<?= number_format((float)$item['daily_rent'], 2) ?>
                                    </td>

                                    <td>
                                        ₹<?= number_format(
                                            $item['issued_quantity'] * $item['daily_rent'],
                                            2
                                        ) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <p class="empty-message">No materials found for this rental.</p>

            <?php endif; ?>

            <!-- Return History -->

            <h2 class="section-heading">Return History</h2>

            <?php if (count($returnHistory) > 0): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Issued</th>
                                <th>Good</th>
                                <th>Damaged</th>
                                <th>Missing</th>
                                <th>Outstanding</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($returnHistory as $return): ?>

                                <tr>

                                    <td>
                                        <?= htmlspecialchars($return['material_name']) ?>
                                    </td>

                                    <td><?= (int)$return['issued_quantity'] ?></td>

                                    <td><?= (int)$return['good_quantity'] ?></td>

                                    <td><?= (int)$return['damaged_quantity'] ?></td>

                                    <td><?= (int)$return['missing_quantity'] ?></td>

                                    <td>
                                        <strong>
                                            <?= (int)$return['outstanding_quantity'] ?>
                                        </strong>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <p class="empty-message">No return records found.</p>

            <?php endif; ?>

            <!-- Payment Records -->

            <h2 class="section-heading">Payment Records</h2>

            <?php if (count($payments) > 0): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>
                            <tr>
                                <th>Amount</th>
                                <th>Payment Type</th>
                                <th>Payment Date</th>
                                <th>Payment Method</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($payments as $payment): ?>

                                <tr>

                                    <td>
                                        <strong>
                                            ₹<?= number_format((float)$payment['amount'], 2) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($payment['payment_type']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($payment['payment_date']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($payment['payment_method'] ?? '') ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <p class="empty-message">No payment records found.</p>

            <?php endif; ?>

            <div class="back-button">
                <a href="view_rentals.php" class="btn">
                    Back to Rentals
                </a>
            </div>

        </div>

    </main>

</body>
</html>