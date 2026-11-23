<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$message = "";
$error = "";

$material_name = "";
$total_quantity = "";
$daily_rent = "";
$description = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $material_name = trim($_POST['material_name'] ?? '');
    $total_quantity = filter_input(INPUT_POST, 'total_quantity', FILTER_VALIDATE_INT);
    $daily_rent = $_POST['daily_rent'] ?? '';
    $description = trim($_POST['description'] ?? '');

    if ($material_name === '') {

        $error = "Material name is required.";

    } elseif ($total_quantity === false || $total_quantity === null || $total_quantity <= 0) {

        $error = "Enter a valid quantity greater than zero.";

    } elseif (!is_numeric($daily_rent) || (float)$daily_rent < 0) {

        $error = "Enter a valid daily rental price.";

    } else {

        try {

            $sql = "INSERT INTO materials
                    (material_name, total_quantity, daily_rent, description)
                    VALUES
                    (:material_name, :total_quantity, :daily_rent, :description)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':material_name' => $material_name,
                ':total_quantity' => $total_quantity,
                ':daily_rent' => $daily_rent,
                ':description' => $description ?: null
            ]);

            $message = "Material added successfully!";

            // Clear fields after successful submission
            $material_name = "";
            $total_quantity = "";
            $daily_rent = "";
            $description = "";

        } catch (PDOException $e) {

            $error = "Unable to add material. Please try again.";

        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Material | Event Rental Management</title>

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

            <h1>Add New Material</h1>

            <p style="color: var(--muted);">
                Enter the rental material details below.
            </p>

            <?php if ($message): ?>

                <div class="success-message">
                    <?= htmlspecialchars($message) ?>
                </div>

            <?php endif; ?>

            <?php if ($error): ?>

                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>

            <form method="POST">

                <label>Material Name *</label>

                <input
                    type="text"
                    name="material_name"
                    placeholder="Example: LED Par Light"
                    value="<?= htmlspecialchars($material_name) ?>"
                    required
                >

                <label>Total Quantity *</label>

                <input
                    type="number"
                    name="total_quantity"
                    placeholder="Enter available stock quantity"
                    min="1"
                    value="<?= htmlspecialchars((string)$total_quantity) ?>"
                    required
                >

                <label>Daily Rental Price (₹) *</label>

                <input
                    type="number"
                    name="daily_rent"
                    placeholder="Enter price per day"
                    min="0"
                    step="0.01"
                    value="<?= htmlspecialchars((string)$daily_rent) ?>"
                    required
                >

                <label>Description</label>

                <textarea
                    name="description"
                    rows="4"
                    placeholder="Enter material details (optional)"
                ><?= htmlspecialchars($description) ?></textarea>

                <button type="submit">
                    Save Material
                </button>

            </form>

        </div>

        <a href="view_materials.php">View All Materials</a>

    </main>

</body>
</html>