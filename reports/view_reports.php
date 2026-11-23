<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Total customers
$customerCount = $pdo->query(
    "SELECT COUNT(*) FROM customers"
)->fetchColumn();

// Total materials
$materialCount = $pdo->query(
    "SELECT COUNT(*) FROM materials"
)->fetchColumn();

// Total rentals
$rentalCount = $pdo->query(
    "SELECT COUNT(*) FROM rentals"
)->fetchColumn();

// Active rentals
$activeRentals = $pdo->query(
    "SELECT COUNT(*) FROM rentals WHERE status = 'active'"
)->fetchColumn();

// Completed rentals
$completedRentals = $pdo->query(
    "SELECT COUNT(*) FROM rentals WHERE status = 'completed'"
)->fetchColumn();

// Total rent collected
$rentCollected = $pdo->query(
    "SELECT COALESCE(SUM(amount), 0)
     FROM payments
     WHERE payment_type = 'rent'"
)->fetchColumn();

// Total security deposits collected
$depositsCollected = $pdo->query(
    "SELECT COALESCE(SUM(amount), 0)
     FROM payments
     WHERE payment_type = 'security_deposit'"
)->fetchColumn();

// Total payments received (excluding refunds)
$totalPayments = $pdo->query(
    "SELECT COALESCE(SUM(amount), 0)
     FROM payments
     WHERE payment_type != 'refund'"
)->fetchColumn();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Business Reports | Event Rental Management</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .report-description {
            color: var(--muted);
            margin-bottom: 25px;
        }

        .report-section {
            margin-top: 30px;
        }

        .report-section h2 {
            margin-bottom: 18px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
        }

        .stat-card {
            padding: 20px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
        }

        .financial-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .financial-card {
            padding: 22px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface);
        }

        .financial-card.highlight {
            background: var(--background);
        }

        .financial-label {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 10px;
        }

        .financial-value {
            font-size: 25px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .report-table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th,
        .report-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .report-table th {
            background: var(--background);
        }

        .report-actions {
            margin-top: 25px;
        }

        @media (max-width: 750px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 500px) {
            .stats-grid,
            .financial-grid {
                grid-template-columns: 1fr;
            }

            .stat-value,
            .financial-value {
                font-size: 24px;
            }

            .report-table {
                min-width: 450px;
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

            <h1>Business Reports</h1>

            <p class="report-description">
                View an overview of your rental business and financial performance.
            </p>

            <!-- General Statistics -->

            <section class="report-section">

                <h2>General Statistics</h2>

                <div class="stats-grid">

                    <div class="stat-card">
                        <div class="stat-label">Total Customers</div>
                        <div class="stat-value">
                            <?= htmlspecialchars((string)$customerCount) ?>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-label">Total Materials</div>
                        <div class="stat-value">
                            <?= htmlspecialchars((string)$materialCount) ?>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-label">Total Rentals</div>
                        <div class="stat-value">
                            <?= htmlspecialchars((string)$rentalCount) ?>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-label">Active Rentals</div>
                        <div class="stat-value">
                            <?= htmlspecialchars((string)$activeRentals) ?>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-label">Completed Rentals</div>
                        <div class="stat-value">
                            <?= htmlspecialchars((string)$completedRentals) ?>
                        </div>
                    </div>

                </div>

            </section>

            <!-- Financial Reports -->

            <section class="report-section">

                <h2>Financial Reports</h2>

                <div class="financial-grid">

                    <div class="financial-card">
                        <div class="financial-label">Rent Collected</div>
                        <div class="financial-value">
                            ₹<?= number_format((float)$rentCollected, 2) ?>
                        </div>
                    </div>

                    <div class="financial-card">
                        <div class="financial-label">Security Deposits Collected</div>
                        <div class="financial-value">
                            ₹<?= number_format((float)$depositsCollected, 2) ?>
                        </div>
                    </div>

                    <div class="financial-card highlight">
                        <div class="financial-label">
                            Total Payments Received (excluding refunds)
                        </div>
                        <div class="financial-value">
                            ₹<?= number_format((float)$totalPayments, 2) ?>
                        </div>
                    </div>

                </div>

            </section>

            <!-- General Statistics Table -->

            <section class="report-section">

                <h2>Detailed Business Summary</h2>

                <div class="report-table-wrapper">

                    <table class="report-table">

                        <thead>
                            <tr>
                                <th>Report</th>
                                <th>Total</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td>Total Customers</td>
                                <td><?= htmlspecialchars((string)$customerCount) ?></td>
                            </tr>

                            <tr>
                                <td>Total Materials</td>
                                <td><?= htmlspecialchars((string)$materialCount) ?></td>
                            </tr>

                            <tr>
                                <td>Total Rentals</td>
                                <td><?= htmlspecialchars((string)$rentalCount) ?></td>
                            </tr>

                            <tr>
                                <td>Active Rentals</td>
                                <td><?= htmlspecialchars((string)$activeRentals) ?></td>
                            </tr>

                            <tr>
                                <td>Completed Rentals</td>
                                <td><?= htmlspecialchars((string)$completedRentals) ?></td>
                            </tr>
                        </tbody>

                    </table>

                </div>

            </section>

            <div class="report-actions">
                <a href="../dashboard.php" class="btn">
                    Back to Dashboard
                </a>
            </div>

        </div>

    </main>

</body>
</html>