<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$error = "";

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    die("Invalid material ID.");
}

// Fetch existing material
$stmt = $pdo->prepare("SELECT * FROM materials WHERE id = :id");
$stmt->execute([':id' => $id]);

$material = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$material) {
    die("Material not found.");
}

// Update material
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $material_name = trim($_POST['material_name'] ?? '');
    $total_quantity = filter_input(INPUT_POST, 'total_quantity', FILTER_VALIDATE_INT);
    $daily_rent = $_POST['daily_rent'] ?? '';
    $description = trim($_POST['description'] ?? '');

    // Preserve entered values if validation fails
    $material['material_name'] = $material_name;
    $material['total_quantity'] = $_POST['total_quantity'] ?? '';
    $material['daily_rent'] = $daily_rent;
    $material['description'] = $description;

    if ($material_name === '') {

        $error = "Material name is required.";

    } elseif (
        $total_quantity === false ||
        $total_quantity === null ||
        $total_quantity <= 0
    ) {

        $error = "Enter a valid quantity greater than zero.";

    } elseif (!is_numeric($daily_rent) || (float)$daily_rent < 0) {

        $error = "Enter a valid daily rental price.";

    } else {

        $sql = "UPDATE materials SET
                material_name = :material_name,
                total_quantity = :total_quantity,
                daily_rent = :daily_rent,
                description = :description
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':material_name' => $material_name,
            ':total_quantity' => $total_quantity,
            ':daily_rent' => $daily_rent,
            ':description' => $description ?: null,
            ':id' => $id
        ]);

        header("Location: view_materials.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Material | Event Rental Management</title>

    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

    <div class="navbar">

        <strong>Event Rental Management</strong>

        <a href="../auth/logout.php" class="logout-link">Logout</a>

    </div>

    <main class="container">

        <a href="view_materials.php">← Back to Materials</a>

        <div class="card">

            <h1>Edit Material</h1>

            <p style="color: var(--muted);">
                Update the material information below.
            </p>

            <?php if ($error): ?>

                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>

            <form method="POST">

                <label for="material_name">
                    Material Name
                </label>

                <input
                    type="text"
                    id="material_name"
                    name="material_name"
                    value="<?= htmlspecialchars($material['material_name']) ?>"
                    placeholder="Enter material name"
                    required
                >

                <label for="total_quantity">
                    Total Quantity
                </label>

                <input
                    type="number"
                    id="total_quantity"
                    name="total_quantity"
                    min="1"
                    value="<?= htmlspecialchars((string)$material['total_quantity']) ?>"
                    required
                >

                <label for="daily_rent">
                    Daily Rental Price (₹)
                </label>

                <input
                    type="number"
                    id="daily_rent"
                    name="daily_rent"
                    min="0"
                    step="0.01"
                    value="<?= htmlspecialchars((string)$material['daily_rent']) ?>"
                    required
                >

                <label for="description">
                    Description
                </label>

                <textarea
                    id="description"
                    name="description"
                    rows="4"
                    placeholder="Enter material description"
                ><?= htmlspecialchars($material['description'] ?? '') ?></textarea>

                <button type="submit">
                    Update Material
                </button>

                <a href="view_materials.php" class="btn"
                   style="background:#64748b;">
                    Cancel
                </a>

            </form>

        </div>

    </main>

</body>
</html>