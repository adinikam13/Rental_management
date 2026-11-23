<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$error = "";
$success = "";

// Recover missing items
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $rental_item_id = filter_input(
        INPUT_POST,
        'rental_item_id',
        FILTER_VALIDATE_INT
    );

    $quantity = filter_input(
        INPUT_POST,
        'quantity',
        FILTER_VALIDATE_INT
    );

    $recovery_date = $_POST['recovery_date'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    if (!$rental_item_id || $rental_item_id <= 0) {

        $error = "Please select a rental item.";

    } elseif ($quantity === false || $quantity === null || $quantity <= 0) {

        $error = "Enter a valid recovery quantity.";

    } elseif ($recovery_date === '') {

        $error = "Please select the recovery date.";

    } elseif ($recovery_date > date('Y-m-d')) {

        $error = "Recovery date cannot be in the future.";

    } else {

        try {

            $pdo->beginTransaction();

            // Lock rental item
            $itemStmt = $pdo->prepare(
                "SELECT id
                 FROM rental_items
                 WHERE id = :id
                 FOR UPDATE"
            );

            $itemStmt->execute([
                ':id' => $rental_item_id
            ]);

            if (!$itemStmt->fetch()) {
                throw new Exception("Rental item not found.");
            }

            // Calculate missing and already recovered quantities
            $checkStmt = $pdo->prepare(
                "SELECT

                    COALESCE((
                        SELECT SUM(ret.missing_quantity)
                        FROM returns ret
                        WHERE ret.rental_item_id = ri.id
                        AND ret.return_date <= :recovery_date
                    ), 0) AS missing_quantity,

                    COALESCE((
                        SELECT SUM(mr.quantity)
                        FROM missing_item_recoveries mr
                        WHERE mr.rental_item_id = ri.id
                        AND mr.recovery_date <= :recovery_date
                    ), 0) AS recovered_quantity

                 FROM rental_items ri
                 WHERE ri.id = :id"
            );

            $checkStmt->execute([
                ':recovery_date' => $recovery_date,
                ':id' => $rental_item_id
            ]);

            $stock = $checkStmt->fetch(PDO::FETCH_ASSOC);

            $pending = (int)$stock['missing_quantity']
                     - (int)$stock['recovered_quantity'];

            if ($quantity > $pending) {

                throw new Exception(
                    "Only $pending missing units are available for recovery."
                );

            }

            // Save recovery record
            $insertStmt = $pdo->prepare(
                "INSERT INTO missing_item_recoveries
                 (rental_item_id, quantity, recovered_by,
                  recovery_date, notes)
                 VALUES
                 (:item_id, :quantity, :user_id,
                  :recovery_date, :notes)"
            );

            $insertStmt->execute([
                ':item_id' => $rental_item_id,
                ':quantity' => $quantity,
                ':user_id' => $_SESSION['user_id'],
                ':recovery_date' => $recovery_date,
                ':notes' => $notes ?: null
            ]);

            $pdo->commit();

            $success = "Missing items recovered successfully.";

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();
        }
    }
}

// Fetch items with pending missing quantities
$listStmt = $pdo->query(
    "SELECT
        ri.id AS rental_item_id,
        r.id AS rental_id,
        c.name AS customer_name,
        m.material_name,
        ri.issued_quantity,

        COALESCE((
            SELECT SUM(ret.missing_quantity)
            FROM returns ret
            WHERE ret.rental_item_id = ri.id
        ), 0) AS missing_quantity,

        COALESCE((
            SELECT SUM(mr.quantity)
            FROM missing_item_recoveries mr
            WHERE mr.rental_item_id = ri.id
        ), 0) AS recovered_quantity

     FROM rental_items ri

     JOIN rentals r ON r.id = ri.rental_id
     JOIN customers c ON c.id = r.customer_id
     JOIN materials m ON m.id = ri.material_id

     ORDER BY r.id DESC, ri.id DESC"
);

$items = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$pendingItems = [];

foreach ($items as $item) {

    $pending = (int)$item['missing_quantity']
             - (int)$item['recovered_quantity'];

    if ($pending > 0) {
        $item['pending_quantity'] = $pending;
        $pendingItems[] = $item;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Recover Missing Items</title>

    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

    <div class="navbar">

        <strong>Event Rental Management</strong>

        <a href="../auth/logout.php" class="logout-link">Logout</a>

    </div>

    <main class="container">

        <a href="../dashboard.php">← Back to Dashboard</a>

        <div class="card">

            <h1>Missing Item Recovery</h1>

            <p style="color:var(--muted);">
                Record items that customers previously reported missing
                but have now returned.
            </p>

            <?php if ($success): ?>

                <div class="success-message">
                    <?= htmlspecialchars($success) ?>
                </div>

            <?php endif; ?>

            <?php if ($error): ?>

                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>

            <?php if (count($pendingItems) > 0): ?>

                <form method="POST">

                    <label for="rental_item_id">
                        Select Missing Rental Item
                    </label>

                    <select name="rental_item_id" id="rental_item_id" required>

                        <option value="">Choose rental item</option>

                        <?php foreach ($pendingItems as $item): ?>

                            <option value="<?= (int)$item['rental_item_id'] ?>">

                                Rental #<?= (int)$item['rental_id'] ?>
                                -
                                <?= htmlspecialchars($item['customer_name']) ?>
                                -
                                <?= htmlspecialchars($item['material_name']) ?>
                                (<?= (int)$item['pending_quantity'] ?> missing)

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <label for="quantity">
                        Quantity Recovered
                    </label>

                    <input
                        type="number"
                        name="quantity"
                        id="quantity"
                        min="1"
                        step="1"
                        required
                    >

                    <label for="recovery_date">
                        Recovery Date
                    </label>

                    <input
                        type="date"
                        name="recovery_date"
                        id="recovery_date"
                        value="<?= date('Y-m-d') ?>"
                        max="<?= date('Y-m-d') ?>"
                        required
                    >

                    <label for="notes">
                        Notes (Optional)
                    </label>

                    <textarea
                        name="notes"
                        id="notes"
                        rows="3"
                        placeholder="Enter recovery details"
                    ></textarea>

                    <button type="submit">
                        Record Recovery
                    </button>

                </form>

            <?php else: ?>

                <div class="success-message">
                    No pending missing items are available for recovery.
                </div>

            <?php endif; ?>

        </div>

        <div class="card">

            <h2>Pending Missing Items</h2>

            <?php if (count($pendingItems) > 0): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>
                            <tr>
                                <th>Rental</th>
                                <th>Customer</th>
                                <th>Material</th>
                                <th>Missing</th>
                                <th>Recovered</th>
                                <th>Still Missing</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($pendingItems as $item): ?>

                                <tr>

                                    <td>
                                        #<?= (int)$item['rental_id'] ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($item['customer_name']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($item['material_name']) ?>
                                    </td>

                                    <td>
                                        <?= (int)$item['missing_quantity'] ?>
                                    </td>

                                    <td>
                                        <?= (int)$item['recovered_quantity'] ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= (int)$item['pending_quantity'] ?>
                                        </strong>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <p>No outstanding missing items.</p>

            <?php endif; ?>

        </div>

    </main>

</body>

</html>