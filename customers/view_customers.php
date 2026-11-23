<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Search functionality
$search = trim($_GET['search'] ?? '');

if ($search !== '') {

    $sql = "SELECT * FROM customers
            WHERE name ILIKE :search
            OR company_name ILIKE :search
            OR phone ILIKE :search
            OR email ILIKE :search
            ORDER BY id DESC";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':search' => '%' . $search . '%'
    ]);

} else {

    $sql = "SELECT * FROM customers ORDER BY id DESC";

    $stmt = $pdo->query($sql);
}

$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>View Customers | Event Rental Management</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .search-form {
            display: flex;
            gap: 10px;
            align-items: center;
            max-width: 650px;
            margin-bottom: 20px;
        }

        .search-form input {
            margin-bottom: 0;
        }

        .action-links {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .delete-link {
            color: var(--danger);
        }

        @media (max-width: 600px) {
            .search-form {
                flex-direction: column;
                align-items: stretch;
            }

            .search-form button {
                width: 100%;
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

            <h1>Customer Management</h1>

            <p style="color: var(--muted);">
                View and manage all registered customers.
            </p>

            <form method="GET" class="search-form">

                <input
                    type="text"
                    name="search"
                    placeholder="Search name, company, phone or email..."
                    value="<?= htmlspecialchars($search) ?>"
                >

                <button type="submit">Search</button>

                <a href="view_customers.php" class="btn">
                    Clear
                </a>

            </form>

            <p>
                Total customers found:
                <strong><?= count($customers) ?></strong>
            </p>

            <?php if (count($customers) > 0): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Company</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Address</th>
                                <th>Registered On</th>
                                <th>Actions</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($customers as $customer): ?>

                                <tr>

                                    <td>
                                        <?= htmlspecialchars((string)$customer['id']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($customer['name']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($customer['company_name'] ?? '') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($customer['phone']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($customer['email'] ?? '') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($customer['address'] ?? '') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($customer['created_at']) ?>
                                    </td>

                                    <td>

                                        <div class="action-links">

                                            <a href="edit_customer.php?id=<?= (int)$customer['id'] ?>">
                                                Edit
                                            </a>

                                            <a
                                                href="delete_customer.php?id=<?= (int)$customer['id'] ?>"
                                                class="delete-link"
                                                onclick="return confirm('Are you sure you want to delete this customer?')"
                                            >
                                                Delete
                                            </a>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="card">

                    <h3>No customers found</h3>

                    <p>
                        Try another search or add a new customer.
                    </p>

                </div>

            <?php endif; ?>

            <br>

            <a href="add_customer.php" class="btn">
                Add New Customer
            </a>

        </div>

    </main>

</body>
</html>