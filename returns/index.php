<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returns Management</title>

    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        .page-description {
            color: var(--muted);
            margin-bottom: 25px;
        }

        .return-option {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 22px;
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 16px;
            background: var(--surface);
        }

        .return-option-icon {
            width: 55px;
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--background);
            border-radius: 12px;
            font-size: 25px;
            flex-shrink: 0;
        }

        .return-option-content {
            flex: 1;
        }

        .return-option-content h3 {
            margin: 0 0 7px;
        }

        .return-option-content p {
            margin: 0;
            color: var(--muted);
        }

        .return-option a {
            white-space: nowrap;
        }

        @media (max-width: 600px) {
            .return-option {
                flex-direction: column;
                align-items: flex-start;
            }

            .return-option a {
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

            <h1>Returns Management</h1>

            <p class="page-description">
                Manage customer returns and recover previously missing materials.
            </p>

            <div class="return-option">
                <div class="return-option-icon">📦</div>

                <div class="return-option-content">
                    <h3>Record Material Return</h3>
                    <p>Record good, damaged and missing materials returned by customers.</p>
                </div>

                <a href="add_return.php" class="btn">Record Return</a>
            </div>

            <div class="return-option">
                <div class="return-option-icon">🔄</div>

                <div class="return-option-content">
                    <h3>Recover Missing Items</h3>
                    <p>Record missing materials that customers return later.</p>
                </div>

                <a href="recover_missing.php" class="btn">Recover Items</a>
            </div>

        </div>
    </main>

</body>
</html>