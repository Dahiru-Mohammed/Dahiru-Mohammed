
<?php
session_start();
require_once "includes/db.php";

// Only administrators can view passenger records.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "admin") {
    http_response_code(403);
    exit("Access denied. Only administrators can manage passengers.");
}

// Search passengers by name, email or phone number.
$search = trim($_GET["search"] ?? "");

if ($search !== "") {
    $stmt = $pdo->prepare("
        SELECT id, full_name, email, phone, created_at
        FROM users
        WHERE role = 'passenger'
          AND (
              full_name LIKE ?
              OR email LIKE ?
              OR phone LIKE ?
          )
        ORDER BY created_at DESC
    ");

    $term = "%" . $search . "%";
    $stmt->execute([$term, $term, $term]);
} else {
    $stmt = $pdo->query("
        SELECT id, full_name, email, phone, created_at
        FROM users
        WHERE role = 'passenger'
        ORDER BY created_at DESC
    ");
}

$passengers = $stmt->fetchAll();
$totalPassengers = count($passengers);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passenger Management</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #243247;
            margin: 0;
            padding: 25px;
        }

        .container {
            max-width: 1150px;
            margin: 20px auto;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px #00000010;
        }

        h1 {
            color: #164e83;
            margin-top: 0;
        }

        .summary {
            background: #e8f2ff;
            border-left: 5px solid #164e83;
            padding: 16px;
            border-radius: 6px;
            margin: 20px 0;
        }

        .search-form {
            display: flex;
            gap: 10px;
            margin: 20px 0;
        }

        input[type="search"] {
            flex: 1;
            min-width: 0;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
        }

        button, .back, .clear {
            display: inline-block;
            padding: 11px 15px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        button {
            background: #164e83;
            color: white;
        }

        .back {
            background: #164e83;
            color: white;
        }

        .clear {
            background: #e2e8f0;
            color: #243247;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        th {
            background: #164e83;
            color: white;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .empty {
            padding: 20px;
            text-align: center;
            color: #64748b;
        }

        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            .container {
                padding: 15px;
            }

            .search-form {
                flex-wrap: wrap;
            }
        }
    </style>
</head>

<body>
<div class="container">
    <h1>Passenger Management</h1>
    <p>View and search registered passengers.</p>

    <div class="summary">
        <strong>Passengers found:</strong>
        <?= (int)$totalPassengers ?>
    </div>

    <form class="search-form" method="GET">
        <input
            type="search"
            name="search"
            placeholder="Search by passenger name, email or phone..."
            value="<?= htmlspecialchars($search, ENT_QUOTES, "UTF-8") ?>"
        >

        <button type="submit">Search</button>

        <?php if ($search !== ""): ?>
            <a class="clear" href="passengers.php">Clear</a>
        <?php endif; ?>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Full Name</th>
                    <th>Email Address</th>
                    <th>Phone Number</th>
                    <th>Registration Date</th>
                </tr>
            </thead>

            <tbody>
            <?php if (count($passengers) > 0): ?>
                <?php foreach ($passengers as $passenger): ?>
                    <tr>
                        <td><?= (int)$passenger["id"] ?></td>

                        <td>
                            <?= htmlspecialchars(
                                $passenger["full_name"] ?? "",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $passenger["email"] ?? "",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $passenger["phone"] ?? "Not provided",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $passenger["created_at"] ?? "",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="empty">
                        No passengers found.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p>
        <a class="back" href="dashboard.php">Back to Dashboard</a>
    </p>
</div>
</body>
</html>
