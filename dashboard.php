<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | Event Rental Management</title>

    <link rel="stylesheet" href="assets/css/style.css">

</head>

<body>

    <div class="navbar">

        <strong>Event Rental Management</strong>

        <a href="auth/logout.php" class="logout-link">
            Logout
        </a>

    </div>

    <main class="container">

        <div class="dashboard-header">

            <h1>Dashboard</h1>

            <h3>
                Welcome, <?= htmlspecialchars($_SESSION['name']) ?>
            </h3>

            <p>
                Manage your event rental business from one place.
            </p>

        </div>

        <h2>Business Management</h2>

        <div class="dashboard-grid">

            <div class="module-card">

                <h3>Customers</h3>

                <p>
                    Add new customers and manage customer information.
                </p>

                <a href="customers/add_customer.php" class="btn">
                    Add Customer
                </a>

                <a href="customers/view_customers.php">
                    View Customers
                </a>

            </div>

            <div class="module-card">

                <h3>Materials</h3>

                <p>
                    Manage rental materials, quantities and daily rent.
                </p>

                <a href="materials/add_material.php" class="btn">
                    Add Material
                </a>

                <a href="materials/view_materials.php">
                    View Materials
                </a>

            </div>

            <div class="module-card">

                <h3>Rentals</h3>

                <p>
                    Create new rentals and check existing rental records.
                </p>

                <a href="rentals/add_rental.php" class="btn">
                    Create Rental
                </a>

                <a href="rentals/view_rentals.php">
                    View Rentals
                </a>

            </div>

            <div class="module-card">

                <h3>Returns</h3>

                <p>
                    Record returned materials and check return details.
                </p>

                <a href="returns/index.php" class="btn">
                    Record Return
                </a>

            </div>

            <div class="module-card">

                <h3>Payments</h3>

                <p>
                    Record rent, deposits, late fees and refunds.
                </p>

                <a href="payments/add_payment.php" class="btn">
                    Add Payment
                </a>

                <a href="payments/view_payments.php">
                    View Payments
                </a>

            </div>

            <div class="module-card">

                <h3>Reports</h3>

                <p>
                    View business statistics and financial summaries.
                </p>

                <a href="reports/view_reports.php" class="btn">
                    View Reports
                </a>

            </div>

        </div>

        <div class="dashboard-footer">

            Event Rental Management System

        </div>

    </main>

</body>
</html>