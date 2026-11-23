<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$error = "";
$success = "";

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    $error = "Invalid material ID.";
} else {

    // Check whether material exists
    $checkMaterial = $pdo->prepare(
        "SELECT id, material_name FROM materials WHERE id = :id"
    );

    $checkMaterial->execute([':id' => $id]);

    $material = $checkMaterial->fetch(PDO::FETCH_ASSOC);

    if (!$material) {

        $error = "Material not found.";

    } else {

        // Check whether material has rental history
        $check = $pdo->prepare(
            "SELECT id FROM rental_items WHERE material_id = :id LIMIT 1"
        );

        $check->execute([':id' => $id]);

        if ($check->fetch()) {

            $error = "This material cannot be deleted because rental records exist.";

        } else {

            // Delete material
            $stmt = $pdo->prepare(
                "DELETE FROM materials WHERE id = :id"
            );

            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {

                $success = "Material deleted successfully.";

            } else {

                $error = "Unable to delete this material.";

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

    <title>Delete Material | Event Rental Management</title>

    <link rel="stylesheet" href="../assets/css/style.css">

</head>

<body>

    <div class="navbar">

        <strong>Event Rental Management</strong>

        <a href="../auth/logout.php" class="logout-link">Logout</a>

    </div>

    <main class="container">

        <div class="card">

            <?php if ($success): ?>

                <h1>Material Deleted</h1>

                <div class="success-message">
                    <?= htmlspecialchars($success) ?>
                </div>

            <?php else: ?>

                <h1>Unable to Delete Material</h1>

                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>

                <p>
                    Materials with existing rental records cannot be deleted.
                    This protects your business records.
                </p>

            <?php endif; ?>

            <a href="view_materials.php" class="btn">
                Back to Materials
            </a>

        </div>

    </main>

</body>

</html>