<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$error = "";
$message = "";

$rental_id = filter_input(INPUT_GET, 'rental_id', FILTER_VALIDATE_INT);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $rental_id = filter_input(INPUT_POST, 'rental_id', FILTER_VALIDATE_INT);
}

$return_date = $_POST['return_date'] ?? date('Y-m-d');

// Fetch active rentals
$rentalStmt = $pdo->query(
    "SELECT r.id, c.name AS customer_name
     FROM rentals r
     JOIN customers c ON r.customer_id = c.id
     WHERE r.status = 'active'
     ORDER BY r.id DESC"
);
$rentals = $rentalStmt->fetchAll(PDO::FETCH_ASSOC);

$rental = null;
$items = [];

if ($rental_id && $rental_id > 0) {

    $stmt = $pdo->prepare(
        "SELECT r.id, c.name AS customer_name
         FROM rentals r
         JOIN customers c ON r.customer_id = c.id
         WHERE r.id = :id AND r.status = 'active'"
    );

    $stmt->execute([':id' => $rental_id]);
    $rental = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rental) {

        $itemStmt = $pdo->prepare(
            "SELECT
                ri.id AS rental_item_id,
                ri.issued_quantity,
                m.material_name,
                COALESCE(SUM(ret.good_quantity), 0) AS returned_good,
                COALESCE(SUM(ret.damaged_quantity), 0) AS returned_damaged,
                COALESCE(SUM(ret.missing_quantity), 0) AS returned_missing
             FROM rental_items ri
             JOIN materials m ON ri.material_id = m.id
             LEFT JOIN returns ret ON ret.rental_item_id = ri.id
             WHERE ri.rental_id = :id
             GROUP BY ri.id, m.material_name
             ORDER BY ri.id"
        );

        $itemStmt->execute([':id' => $rental_id]);
        $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Process return
if ($_SERVER["REQUEST_METHOD"] === "POST" && $rental) {

    $return_date = $_POST['return_date'] ?? '';
    $good = $_POST['good_quantity'] ?? [];
    $damaged = $_POST['damaged_quantity'] ?? [];
    $missing = $_POST['missing_quantity'] ?? [];

    if (!is_array($good) || !is_array($damaged) || !is_array($missing)) {
        $error = "Invalid return quantities.";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $return_date) ||
              !checkdate(
                  (int)substr($return_date, 5, 2),
                  (int)substr($return_date, 8, 2),
                  (int)substr($return_date, 0, 4)
              )) {
        $error = "Please enter a valid return date.";
    } else {

        try {
            $pdo->beginTransaction();

            $hasReturn = false;

            $lockStmt = $pdo->prepare(
                "SELECT id, issued_quantity
                 FROM rental_items
                 WHERE rental_id = :id
                 FOR UPDATE"
            );
            $lockStmt->execute([':id' => $rental_id]);
            $lockedItems = $lockStmt->fetchAll(PDO::FETCH_ASSOC);

            $insertStmt = $pdo->prepare(
                "INSERT INTO returns
                 (rental_item_id, received_by, good_quantity,
                  damaged_quantity, missing_quantity, return_date)
                 VALUES
                 (:item_id, :received_by, :good, :damaged, :missing, :return_date)"
            );

            foreach ($lockedItems as $lockedItem) {

                $item_id = (int)$lockedItem['id'];

                $goodQty = filter_var(
                    $good[$item_id] ?? '0',
                    FILTER_VALIDATE_INT
                );

                $damagedQty = filter_var(
                    $damaged[$item_id] ?? '0',
                    FILTER_VALIDATE_INT
                );

                $missingQty = filter_var(
                    $missing[$item_id] ?? '0',
                    FILTER_VALIDATE_INT
                );

                if ($goodQty === false || $goodQty === null ||
                    $damagedQty === false || $damagedQty === null ||
                    $missingQty === false || $missingQty === null ||
                    $goodQty < 0 || $damagedQty < 0 || $missingQty < 0) {

                    throw new Exception("Enter valid non-negative quantities.");
                }

                $totalReturned = $goodQty + $damagedQty + $missingQty;

                if ($totalReturned === 0) {
                    continue;
                }

                $previousStmt = $pdo->prepare(
                    "SELECT COALESCE(
                        SUM(good_quantity + damaged_quantity + missing_quantity), 0
                     )
                     FROM returns
                     WHERE rental_item_id = :id"
                );

                $previousStmt->execute([':id' => $item_id]);
                $previousQuantity = (int)$previousStmt->fetchColumn();

                $outstanding =
                    (int)$lockedItem['issued_quantity'] - $previousQuantity;

                if ($totalReturned > $outstanding) {
                    throw new Exception(
                        "Return quantity exceeds outstanding quantity for rental item ID $item_id."
                    );
                }

                $insertStmt->execute([
                    ':item_id' => $item_id,
                    ':received_by' => $_SESSION['user_id'],
                    ':good' => $goodQty,
                    ':damaged' => $damagedQty,
                    ':missing' => $missingQty,
                    ':return_date' => $return_date
                ]);

                $hasReturn = true;
            }

            if (!$hasReturn) {
                throw new Exception("Enter at least one return quantity.");
            }

            $remainingStmt = $pdo->prepare(
                "SELECT COUNT(*)
                 FROM rental_items ri
                 WHERE ri.rental_id = :id
                 AND ri.issued_quantity > (
                    SELECT COALESCE(
                        SUM(ret.good_quantity + ret.damaged_quantity + ret.missing_quantity), 0
                    )
                    FROM returns ret
                    WHERE ret.rental_item_id = ri.id
                 )"
            );

            $remainingStmt->execute([':id' => $rental_id]);

            $remainingItems = (int)$remainingStmt->fetchColumn();

            if ($remainingItems === 0) {
                $updateStmt = $pdo->prepare(
                    "UPDATE rentals
                     SET status = 'completed'
                     WHERE id = :id"
                );
                $updateStmt->execute([':id' => $rental_id]);
            }

            $pdo->commit();

            header("Location: ../rentals/rental_details.php?id=" . $rental_id);
            exit();

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

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

    <title>Record Material Return | Event Rental Management</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .page-description {
            color: var(--muted);
            margin-bottom: 22px;
        }

        .rental-selector {
            display: flex;
            align-items: end;
            gap: 12px;
            max-width: 650px;
            margin-bottom: 25px;
        }

        .rental-selector .field {
            flex: 1;
        }

        .rental-selector select {
            margin-bottom: 0;
        }

        .rental-info {
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px;
            margin: 22px 0;
        }

        .rental-info p {
            margin: 5px 0;
        }

        .return-date-field {
            max-width: 350px;
            margin-bottom: 25px;
        }

        .return-date-field input {
            margin-bottom: 0;
        }

        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .quantity-input {
            width: 85px;
            min-width: 75px;
            text-align: center;
            margin: 0;
        }

        .good-heading {
            color: var(--success);
        }

        .damaged-heading,
        .missing-heading {
            color: var(--danger);
        }

        .outstanding-value {
            font-weight: 700;
        }

        .return-note {
            color: var(--muted);
            font-size: 13px;
            margin: 15px 0;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .error-message {
            padding: 14px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 9px;
            margin-bottom: 20px;
        }

        .empty-state {
            text-align: center;
            padding: 30px 15px;
        }

        @media (max-width: 600px) {
            .rental-selector {
                flex-direction: column;
                align-items: stretch;
            }

            .rental-selector button {
                width: 100%;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions button,
            .form-actions a {
                width: 100%;
                text-align: center;
            }

            .quantity-input {
                width: 70px;
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

            <h1>Record Material Return</h1>

            <p class="page-description">
                Select a rental and enter the quantities returned by the customer.
            </p>

            <?php if ($error): ?>

                <div class="error-message">
                    <strong>Please check:</strong>
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>

            <?php if (count($rentals) === 0 && !$rental): ?>

                <div class="empty-state">
                    <h3>No Active Rentals</h3>
                    <p>There are currently no active rentals available for return.</p>
                </div>

            <?php else: ?>

                <form method="GET" class="rental-selector">

                    <div class="field">

                        <label for="rental_id">Select Rental</label>

                        <select name="rental_id" id="rental_id" required>

                            <option value="">Choose a rental</option>

                            <?php foreach ($rentals as $r): ?>

                                <option
                                    value="<?= (int)$r['id'] ?>"
                                    <?= ($rental_id == $r['id']) ? 'selected' : '' ?>
                                >
                                    Rental #<?= (int)$r['id'] ?> -
                                    <?= htmlspecialchars($r['customer_name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <button type="submit">Load Rental</button>

                </form>

                <?php if ($rental): ?>

                    <div class="rental-info">

                        <h2>Rental #<?= (int)$rental['id'] ?></h2>

                        <p>
                            <strong>Customer:</strong>
                            <?= htmlspecialchars($rental['customer_name']) ?>
                        </p>

                    </div>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="rental_id"
                            value="<?= (int)$rental['id'] ?>"
                        >

                        <div class="return-date-field">

                            <label for="return_date">Return Date</label>

                            <input
                                type="date"
                                id="return_date"
                                name="return_date"
                                value="<?= htmlspecialchars($return_date) ?>"
                                required
                            >

                        </div>

                        <h2>Returned Materials</h2>

                        <p class="page-description">
                            Enter the number of good, damaged and missing items for each material.
                        </p>

                        <?php if (count($items) > 0): ?>

                            <div class="table-wrapper">

                                <table>

                                    <thead>

                                        <tr>
                                            <th>Material</th>
                                            <th>Issued</th>
                                            <th class="good-heading">Good</th>
                                            <th class="damaged-heading">Damaged</th>
                                            <th class="missing-heading">Missing</th>
                                            <th>Outstanding</th>
                                        </tr>

                                    </thead>

                                    <tbody>

                                        <?php foreach ($items as $item):

                                            $resolved = (int)$item['returned_good']
                                                      + (int)$item['returned_damaged']
                                                      + (int)$item['returned_missing'];

                                            $outstanding = max(
                                                0,
                                                (int)$item['issued_quantity'] - $resolved
                                            );

                                        ?>

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
                                                    <input
                                                        class="quantity-input"
                                                        type="number"
                                                        name="good_quantity[<?= (int)$item['rental_item_id'] ?>]"
                                                        min="0"
                                                        max="<?= $outstanding ?>"
                                                        value="0"
                                                        aria-label="Good quantity for <?= htmlspecialchars($item['material_name']) ?>"
                                                    >
                                                </td>

                                                <td>
                                                    <input
                                                        class="quantity-input"
                                                        type="number"
                                                        name="damaged_quantity[<?= (int)$item['rental_item_id'] ?>]"
                                                        min="0"
                                                        max="<?= $outstanding ?>"
                                                        value="0"
                                                        aria-label="Damaged quantity for <?= htmlspecialchars($item['material_name']) ?>"
                                                    >
                                                </td>

                                                <td>
                                                    <input
                                                        class="quantity-input"
                                                        type="number"
                                                        name="missing_quantity[<?= (int)$item['rental_item_id'] ?>]"
                                                        min="0"
                                                        max="<?= $outstanding ?>"
                                                        value="0"
                                                        aria-label="Missing quantity for <?= htmlspecialchars($item['material_name']) ?>"
                                                    >
                                                </td>

                                                <td class="outstanding-value">
                                                    <?= $outstanding ?>
                                                </td>

                                            </tr>

                                        <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                            <p class="return-note">
                                You can record partial returns. The outstanding quantity shows how many
                                items are still unresolved. The combined good, damaged and missing
                                quantities cannot exceed the outstanding quantity.
                            </p>

                            <div class="form-actions">

                                <button type="submit">
                                    Save Return
                                </button>

                                <a href="../rentals/view_rentals.php" class="btn">
                                    Cancel
                                </a>

                            </div>

                        <?php else: ?>

                            <p class="empty-state">No materials found for this rental.</p>

                        <?php endif; ?>

                    </form>

                <?php endif; ?>

            <?php endif; ?>

        </div>

    </main>

</body>
</html>