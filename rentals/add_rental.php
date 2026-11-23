<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Fetch customers
$customerStmt = $pdo->query(
    "SELECT id, name, company_name, phone
     FROM customers
     ORDER BY name ASC"
);
$customers = $customerStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch materials
$materialStmt = $pdo->query(
    "SELECT id, material_name, total_quantity, daily_rent
     FROM materials
     ORDER BY material_name ASC"
);
$materials = $materialStmt->fetchAll(PDO::FETCH_ASSOC);

$error = "";

// Preserve entered values if form submission fails
$selected_customer = $_POST['customer_id'] ?? '';
$issue_date = $_POST['issue_date'] ?? date('Y-m-d');
$expected_return_date = $_POST['expected_return_date'] ?? date('Y-m-d', strtotime('+1 day'));
$security_deposit = $_POST['security_deposit'] ?? '0';
$quantities = $_POST['quantity'] ?? [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $customer_id = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);

    if (!$customer_id || $customer_id <= 0) {

        $error = "Please select a customer.";

    } elseif ($issue_date === '' || $expected_return_date === '') {

        $error = "Please select both dates.";

    } elseif ($expected_return_date < $issue_date) {

        $error = "Expected return date cannot be before issue date.";

    } elseif (!is_numeric($security_deposit) || (float)$security_deposit < 0) {

        $error = "Enter a valid security deposit.";

    } else {

        $material_ids = $_POST['material_id'] ?? [];

        if (
            !is_array($material_ids) ||
            !is_array($quantities) ||
            count($material_ids) === 0 ||
            count($material_ids) !== count($quantities)
        ) {

            $error = "Please select at least one material.";

        } else {

            try {

                $pdo->beginTransaction();

                // Verify customer exists
                $customerCheck = $pdo->prepare(
                    "SELECT id FROM customers WHERE id = :id"
                );

                $customerCheck->execute([':id' => $customer_id]);

                if (!$customerCheck->fetch()) {
                    throw new Exception("Selected customer does not exist.");
                }

                // Validate and combine material quantities
                $requested = [];

                foreach ($material_ids as $index => $material_id) {

                    $material_id = filter_var($material_id, FILTER_VALIDATE_INT);

                    $quantity = filter_var(
                        $quantities[$index] ?? null,
                        FILTER_VALIDATE_INT
                    );

                    if (
                        $material_id === false ||
                        $material_id <= 0 ||
                        $quantity === false ||
                        $quantity < 0
                    ) {
                        throw new Exception("Enter valid materials and quantities.");
                    }

                    // Ignore materials with zero quantity
                    if ($quantity === 0) {
                        continue;
                    }

                    if (!isset($requested[$material_id])) {
                        $requested[$material_id] = 0;
                    }

                    $requested[$material_id] += $quantity;
                }

                if (count($requested) === 0) {
                    throw new Exception(
                        "Please select at least one material with quantity greater than zero."
                    );
                }

                // Lock material rows and check available stock
                $selectedMaterials = [];

                foreach ($requested as $material_id => $quantity) {

                    $stmt = $pdo->prepare(
                        "SELECT id, material_name, total_quantity, daily_rent
                         FROM materials
                         WHERE id = :id
                         FOR UPDATE"
                    );

                    $stmt->execute([':id' => $material_id]);

                    $material = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$material) {
                        throw new Exception("A selected material was not found.");
                    }

                    // Calculate unavailable stock
                    $stockStmt = $pdo->prepare(
                        "SELECT

                        GREATEST(

    COALESCE((
        SELECT SUM(ret.missing_quantity)
        FROM returns ret
        JOIN rental_items ri2
            ON ret.rental_item_id = ri2.id
        WHERE ri2.material_id = :missing_id
        AND ret.return_date <= :missing_date
    ), 0)

    -

    COALESCE((
        SELECT SUM(mr.quantity)
        FROM missing_item_recoveries mr
        JOIN rental_items ri3
            ON mr.rental_item_id = ri3.id
        WHERE ri3.material_id = :recovered_id
        AND mr.recovery_date <= :recovered_date
    ), 0),

    0

) AS missing_total,

                        GREATEST(
                            COALESCE((
                                SELECT SUM(ret.damaged_quantity)
                                FROM returns ret
                                JOIN rental_items ri3
                                    ON ret.rental_item_id = ri3.id
                                WHERE ri3.material_id = :damaged_id
                                AND ret.return_date <= :damaged_date
                            ), 0)
                            -
                            COALESCE((
                                SELECT SUM(sa.quantity)
                                FROM stock_adjustments sa
                                WHERE sa.material_id = :repair_id
                                AND sa.reason = 'repair'
                                AND sa.created_at::date <= :repair_date
                            ), 0),
                            0
                        ) AS damaged_total,

                        COALESCE((
                            SELECT SUM(
                                GREATEST(
                                    ri.issued_quantity -
                                    COALESCE((
                                        SELECT SUM(
                                            ret2.good_quantity +
                                            ret2.damaged_quantity +
                                            ret2.missing_quantity
                                        )
                                        FROM returns ret2
                                        WHERE ret2.rental_item_id = ri.id
                                        AND ret2.return_date <= :return_check_date
                                    ), 0),
                                    0
                                )
                            )
                            FROM rental_items ri
                            JOIN rentals r ON r.id = ri.rental_id
                            WHERE ri.material_id = :rental_id
                            AND r.status = 'active'
                            AND r.issue_date <= :overlap_return_date
                            AND r.expected_return_date >= :overlap_issue_date
                        ), 0) AS outstanding_total"
                    );

                    $stockStmt->execute([
                        ':missing_id' => $material_id,
                        ':missing_date' => $issue_date,
                        ':damaged_id' => $material_id,
                        ':damaged_date' => $issue_date,
                        ':repair_id' => $material_id,
                        ':repair_date' => $issue_date,
                        ':return_check_date' => $issue_date,
                        ':rental_id' => $material_id,
                        ':overlap_return_date' => $expected_return_date,
                        ':overlap_issue_date' => $issue_date,
                        ':recovered_id' => $material_id,
                        ':recovered_date' => $issue_date, 


                    ]);

                    $stockData = $stockStmt->fetch(PDO::FETCH_ASSOC);

                    $missingTotal = (int)$stockData['missing_total'];
                    $damagedTotal = (int)$stockData['damaged_total'];
                    $outstandingTotal = (int)$stockData['outstanding_total'];

                    $available = (int)$material['total_quantity']
                               - $missingTotal
                               - $damagedTotal
                               - $outstandingTotal;

                    if ($quantity > $available) {
                        throw new Exception(
                            "Only $available units of " .
                            $material['material_name'] . " are available."
                        );
                    }

                    // Store selected material details
                    $selectedMaterials[$material_id] = [
                        'material' => $material,
                        'quantity' => $quantity
                    ];
                }

                // Create rental record
                $rentalStmt = $pdo->prepare(
                    "INSERT INTO rentals
                     (customer_id, issued_by, issue_date,
                      expected_return_date, security_deposit)
                     VALUES
                     (:customer_id, :issued_by, :issue_date,
                      :return_date, :deposit)
                     RETURNING id"
                );

                $rentalStmt->execute([
                    ':customer_id' => $customer_id,
                    ':issued_by' => $_SESSION['user_id'],
                    ':issue_date' => $issue_date,
                    ':return_date' => $expected_return_date,
                    ':deposit' => $security_deposit
                ]);

                $rental_id = $rentalStmt->fetchColumn();

                // Add rental items
                $itemStmt = $pdo->prepare(
                    "INSERT INTO rental_items
                     (rental_id, material_id, issued_quantity, daily_rent)
                     VALUES
                     (:rental_id, :material_id, :quantity, :daily_rent)"
                );

                foreach ($selectedMaterials as $material_id => $item) {

                    $itemStmt->execute([
                        ':rental_id' => $rental_id,
                        ':material_id' => $material_id,
                        ':quantity' => $item['quantity'],
                        ':daily_rent' => $item['material']['daily_rent']
                    ]);
                }

                // Record security deposit payment if collected
                if ((float)$security_deposit > 0) {

                    $paymentStmt = $pdo->prepare(
                        "INSERT INTO payments
                         (rental_id, amount, payment_type, notes)
                         VALUES (:rental_id, :amount, 'security_deposit', :notes)"
                    );

                    $paymentStmt->execute([
                        ':rental_id' => $rental_id,
                        ':amount' => $security_deposit,
                        ':notes' => 'Security deposit collected'
                    ]);
                }

                $pdo->commit();

                header("Location: view_rentals.php");
                exit();

            } catch (Exception $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Rental | Event Rental Management</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .rental-form {
            max-width: 850px;
        }

        .form-section {
            margin-bottom: 28px;
        }

        .form-section h2 {
            border-bottom: 1px solid var(--border);
            padding-bottom: 10px;
        }

        .material-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .material-card {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 18px;
        }

        .material-card h3 {
            margin-top: 0;
        }

        .material-card p {
            color: var(--muted);
            margin: 5px 0;
            font-size: 14px;
        }

        .material-card input {
            margin-top: 12px;
            margin-bottom: 0;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        @media (max-width: 600px) {
            .material-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .form-actions button,
            .form-actions a {
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

            <h1>Create New Rental</h1>

            <p style="color: var(--muted);">
                Select a customer, choose rental dates and enter the quantities being issued.
            </p>

            <?php if ($error): ?>

                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>

            <?php if (count($customers) === 0): ?>

                <div class="error-message">
                    No customers found. Please add a customer before creating a rental.
                </div>

                <a href="../customers/add_customer.php" class="btn">
                    Add Customer
                </a>

            <?php elseif (count($materials) === 0): ?>

                <div class="error-message">
                    No materials found. Please add materials before creating a rental.
                </div>

                <a href="../materials/add_material.php" class="btn">
                    Add Material
                </a>

            <?php else: ?>

                <form method="POST" class="rental-form">

                    <div class="form-section">

                        <h2>1. Customer Details</h2>

                        <label for="customer_id">Select Customer</label>

                        <select name="customer_id" id="customer_id" required>

                            <option value="">Choose a customer</option>

                            <?php foreach ($customers as $customer): ?>

                                <option
                                    value="<?= (int)$customer['id'] ?>"
                                    <?= (string)$selected_customer === (string)$customer['id'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($customer['name']) ?>
                                    - <?= htmlspecialchars($customer['phone']) ?>
                                    <?= !empty($customer['company_name']) ? ' (' . htmlspecialchars($customer['company_name']) . ')' : '' ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="form-section">

                        <h2>2. Rental Dates</h2>

                        <label for="issue_date">Issue Date</label>

                        <input
                            type="date"
                            name="issue_date"
                            id="issue_date"
                            value="<?= htmlspecialchars($issue_date) ?>"
                            required
                        >

                        <label for="expected_return_date">Expected Return Date</label>

                        <input
                            type="date"
                            name="expected_return_date"
                            id="expected_return_date"
                            value="<?= htmlspecialchars($expected_return_date) ?>"
                            required
                        >

                    </div>

                    <div class="form-section">

                        <h2>3. Select Materials</h2>

                        <p style="color: var(--muted);">
                            Enter the quantity you are issuing for each material.
                            Leave zero for materials you are not issuing.
                        </p>

                        <div class="material-grid">

                            <?php foreach ($materials as $index => $material): ?>

                                <div class="material-card">

                                    <h3>
                                        <?= htmlspecialchars($material['material_name']) ?>
                                    </h3>

                                    <p>
                                        Daily Rent:
                                        <strong>
                                            ₹<?= number_format((float)$material['daily_rent'], 2) ?>
                                        </strong>
                                    </p>

                                    <p>
                                        Total Stock:
                                        <?= (int)$material['total_quantity'] ?> units
                                    </p>

                                    <label for="quantity_<?= $index ?>">
                                        Quantity to Issue
                                    </label>

                                    <input
                                        type="number"
                                        id="quantity_<?= $index ?>"
                                        name="quantity[]"
                                        min="0"
                                        step="1"
                                        value="<?= htmlspecialchars((string)($quantities[$index] ?? '0')) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="material_id[]"
                                        value="<?= (int)$material['id'] ?>"
                                    >

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>

                    <div class="form-section">

                        <h2>4. Security Deposit</h2>

                        <label for="security_deposit">
                            Security Deposit Amount (₹)
                        </label>

                        <input
                            type="number"
                            name="security_deposit"
                            id="security_deposit"
                            min="0"
                            step="0.01"
                            value="<?= htmlspecialchars((string)$security_deposit) ?>"
                            required
                        >

                        <p style="color: var(--muted); font-size: 13px;">
                            Enter zero if no security deposit is collected.
                        </p>

                    </div>

                    <div class="form-actions">

                        <button type="submit">
                            Create Rental
                        </button>

                        <a href="view_rentals.php" class="btn"
                           style="background:#64748b;">
                            Cancel
                        </a>

                    </div>

                </form>

            <?php endif; ?>

        </div>

    </main>

</body>
</html>