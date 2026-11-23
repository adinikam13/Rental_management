<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Search materials
$search = trim($_GET['search'] ?? '');

$sql = "
WITH stock AS (
    SELECT
        m.*,

        -- Quantity currently with customers
        COALESCE((
            SELECT SUM(
                GREATEST(
                    ri.issued_quantity - COALESCE((
                        SELECT SUM(
                            r.good_quantity +
                            r.damaged_quantity +
                            r.missing_quantity
                        )
                        FROM returns r
                        WHERE r.rental_item_id = ri.id
                    ), 0),
                    0
                )
            )
            FROM rental_items ri
            JOIN rentals rent ON rent.id = ri.rental_id
            WHERE ri.material_id = m.id
              AND rent.status = 'active'
              AND rent.issue_date <= CURRENT_DATE
        ), 0) AS rented_quantity,

        -- Missing items not yet recovered, plus damaged items
        GREATEST(
            COALESCE((
                SELECT SUM(r.missing_quantity)
                FROM returns r
                JOIN rental_items ri ON ri.id = r.rental_item_id
                WHERE ri.material_id = m.id
            ), 0)
            -
            COALESCE((
                SELECT SUM(mr.quantity)
                FROM missing_item_recoveries mr
                JOIN rental_items ri ON ri.id = mr.rental_item_id
                WHERE ri.material_id = m.id
            ), 0),
            0
        )
        +
        GREATEST(
            COALESCE((
                SELECT SUM(r.damaged_quantity)
                FROM returns r
                JOIN rental_items ri ON ri.id = r.rental_item_id
                WHERE ri.material_id = m.id
            ), 0)
            -
            COALESCE((
                SELECT SUM(sa.quantity)
                FROM stock_adjustments sa
                WHERE sa.material_id = m.id
                  AND sa.reason = 'repair'
            ), 0),
            0
        ) AS unavailable_quantity

    FROM materials m
    WHERE (
        :search = ''
        OR m.material_name ILIKE :search_pattern
        OR COALESCE(m.description, '') ILIKE :search_pattern
    )
)

SELECT *,
    GREATEST(
        total_quantity - rented_quantity - unavailable_quantity,
        0
    ) AS available_quantity
FROM stock
ORDER BY id DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':search' => $search,
    ':search_pattern' => '%' . $search . '%'
]);

$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>View Materials | Event Rental Management</title>

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

        .stock-number {
            font-weight: bold;
            white-space: nowrap;
        }

        .available-stock {
            color: var(--success);
        }

        .unavailable-stock {
            color: var(--danger);
        }

        .stock-note {
            color: var(--muted);
            font-size: 13px;
            margin-top: 15px;
        }

        @media (max-width: 600px) {
            .search-form {
                flex-direction: column;
                align-items: stretch;
            }

            .search-form button,
            .search-form .btn {
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

            <h1>Material Management</h1>

            <p style="color: var(--muted);">
                View your materials and check available inventory.
            </p>

            <form method="GET" class="search-form">

                <input
                    type="text"
                    name="search"
                    placeholder="Search by material name or description..."
                    value="<?= htmlspecialchars($search) ?>"
                >

                <button type="submit">Search</button>

                <a href="view_materials.php" class="btn">Clear</a>

            </form>

            <p>
                Total materials found:
                <strong><?= count($materials) ?></strong>
            </p>

            <?php if (count($materials) > 0): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Material Name</th>
                                <th>Total Stock</th>
                                <th>Currently Rented</th>
                                <th>Unavailable</th>
                                <th>Available Stock</th>
                                <th>Daily Rent</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($materials as $material): ?>

                                <tr>

                                    <td>
                                        <?= (int)$material['id'] ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= htmlspecialchars($material['material_name']) ?>
                                        </strong>
                                    </td>

                                    <td class="stock-number">
                                        <?= (int)$material['total_quantity'] ?>
                                    </td>

                                    <td class="stock-number">
                                        <?= (int)$material['rented_quantity'] ?>
                                    </td>

                                    <td class="stock-number unavailable-stock">
                                        <?= (int)$material['unavailable_quantity'] ?>
                                    </td>

                                    <td class="stock-number available-stock">
                                        <?= (int)$material['available_quantity'] ?>
                                    </td>

                                    <td>
                                        ₹<?= number_format((float)$material['daily_rent'], 2) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($material['description'] ?? '') ?>
                                    </td>

                                    <td>
                                        <div class="action-links">

                                            <a href="edit_material.php?id=<?= (int)$material['id'] ?>">
                                                Edit
                                            </a>

                                            <a
                                                href="delete_material.php?id=<?= (int)$material['id'] ?>"
                                                class="delete-link"
                                                onclick="return confirm('Are you sure you want to delete this material?')"
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

                <p class="stock-note">
                    Available Stock = Total Stock − Currently Rented − Unavailable.
                    Unavailable includes unrecovered missing items and unrepaired damaged items.
                </p>

            <?php else: ?>

                <div class="card">
                    <h3>No materials found</h3>
                    <p>Try another search or add a new material.</p>
                </div>

            <?php endif; ?>

            <br>

            <a href="add_material.php" class="btn">
                Add New Material
            </a>

        </div>

    </main>

</body>
</html>