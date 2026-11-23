<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$sql = "SELECT
            r.id,
            c.name AS customer_name,
            c.phone,
            u.name AS issued_by_name,
            r.issue_date,
            r.expected_return_date,
            r.security_deposit,
            r.status,
            COALESCE(SUM(ri.issued_quantity), 0) AS total_items
        FROM rentals r
        JOIN customers c ON r.customer_id = c.id
        JOIN users u ON r.issued_by = u.id
        LEFT JOIN rental_items ri ON r.id = ri.rental_id
        GROUP BY
            r.id, c.name, c.phone, u.name,
            r.issue_date, r.expected_return_date,
            r.security_deposit, r.status
        ORDER BY r.id DESC";

$stmt = $pdo->query($sql);
$rentals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Rental Records | Event Rental Management</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .page-description {
            color: var(--muted);
            margin-bottom: 22px;
        }

        .rental-count {
            margin-bottom: 18px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: capitalize;
            white-space: nowrap;
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

        .details-link {
            font-weight: 600;
            white-space: nowrap;
        }

        .rental-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 24px;
        }

        .empty-state {
            padding: 30px 15px;
            text-align: center;
        }

        @media (max-width: 600px) {
            .rental-actions {
                flex-direction: column;
            }

            .rental-actions a {
                width: 100%;
                text-align: center;
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

            <h1>Rental Management</h1>

            <p class="page-description">
                View all rental records, customer details and rental status.
            </p>

            <p class="rental-count">
                Total rental records:
                <strong><?= count($rentals) ?></strong>
            </p>

            <?php if (count($rentals) > 0): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>
                            <tr>
                                <th>Rental ID</th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Materials Quantity</th>
                                <th>Issue Date</th>
                                <th>Expected Return</th>
                                <th>Security Deposit</th>
                                <th>Issued By</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($rentals as $rental): ?>

                                <?php
                                $status = strtolower($rental['status']);

                                $statusClass = 'status-active';

                                if ($status === 'completed') {
                                    $statusClass = 'status-completed';
                                } elseif ($status === 'cancelled') {
                                    $statusClass = 'status-cancelled';
                                }
                                ?>

                                <tr>

                                    <td>
                                        <strong>
                                            #<?= (int)$rental['id'] ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($rental['customer_name']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($rental['phone'] ?? '') ?>
                                    </td>

                                    <td>
                                        <?= (int)$rental['total_items'] ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($rental['issue_date']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($rental['expected_return_date']) ?>
                                    </td>

                                    <td>
                                        ₹<?= number_format((float)$rental['security_deposit'], 2) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($rental['issued_by_name']) ?>
                                    </td>

                                    <td>
                                        <span class="status-badge <?= $statusClass ?>">
                                            <?= htmlspecialchars($rental['status']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <a
                                            href="rental_details.php?id=<?= (int)$rental['id'] ?>"
                                            class="details-link"
                                        >
                                            View Details →
                                        </a>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">
                    <h3>No rental records found</h3>
                    <p>Your rental records will appear here when materials are issued.</p>
                </div>

            <?php endif; ?>

            <div class="rental-actions">

                <a href="add_rental.php" class="btn">
                    + Issue New Materials
                </a>

                <a href="../dashboard.php" class="btn">
                    Back to Dashboard
                </a>

            </div>

        </div>

    </main>

</body>
</html>