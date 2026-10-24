
<?php
session_start();
require_once "includes/db.php";

// Only administrators can access revenue reports.
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "admin") {
    http_response_code(403);
    exit("Access denied. Only administrators can view revenue reports.");
}

// Calculate revenue from successful transactions only.
$stmt = $pdo->query("
    SELECT
        COALESCE(SUM(CASE
            WHEN status = 'successful' THEN amount
            ELSE 0
        END), 0) AS total_revenue,

        COUNT(CASE
            WHEN status = 'successful' THEN 1
        END) AS successful_transactions,

        COUNT(CASE
            WHEN status = 'pending' THEN 1
        END) AS pending_transactions,

        COUNT(CASE
            WHEN status = 'failed' THEN 1
        END) AS failed_transactions,

        COUNT(*) AS total_transactions
    FROM transactions
");
$summary = $stmt->fetch();

// Show recent transactions with passenger and route information.
$stmt = $pdo->query("
    SELECT
        tr.id,
        tr.transaction_reference,
        tr.amount,
        tr.status,
        tr.created_at,
        b.booking_reference,
        u.full_name,
        t.origin,
        t.destination
    FROM transactions tr
    JOIN bookings b ON b.id = tr.booking_id
    JOIN users u ON u.id = b.passenger_id
    JOIN trips t ON t.id = b.trip_id
    ORDER BY tr.created_at DESC
    LIMIT 100
");
$transactions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenue Tracking</title>

    <style>
        * { box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #243247;
            margin: 0;
            padding: 25px;
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px #00000010;
        }

        h1 { color: #164e83; }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }

        .card {
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            padding: 18px;
            background: #f8fafc;
        }

        .card h3 {
            margin: 0 0 12px;
            font-size: 14px;
            color: #64748b;
        }

        .number {
            font-size: 25px;
            font-weight: bold;
            color: #164e83;
            overflow-wrap: anywhere;
        }

        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        th { background: #164e83; color: white; }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .successful { background: #dcfce7; color: #166534; }
        .pending { background: #fef3c7; color: #92400e; }
        .failed { background: #fee2e2; color: #991b1b; }
        .refunded { background: #e2e8f0; color: #334155; }

        .back, .print {
            display: inline-block;
            margin: 15px 8px 0 0;
            padding: 11px 15px;
            border: none;
            border-radius: 6px;
            background: #164e83;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }

        .note {
            color: #64748b;
            font-size: 13px;
            margin-top: 20px;
        }

        @media print {
            body { background: white; padding: 0; }
            .container { box-shadow: none; }
            .back, .print { display: none; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Revenue Tracking</h1>
    <p>Review transaction activity and recorded revenue.</p>

    <div class="cards">
        <div class="card">
            <h3>Total Recorded Revenue</h3>
            <div class="number">
                ₦<?= number_format((float)$summary["total_revenue"], 2) ?>
            </div>
        </div>

        <div class="card">
            <h3>Successful Transactions</h3>
            <div class="number">
                <?= (int)$summary["successful_transactions"] ?>
            </div>
        </div>

        <div class="card">
            <h3>Pending Transactions</h3>
            <div class="number">
                <?= (int)$summary["pending_transactions"] ?>
            </div>
        </div>

        <div class="card">
            <h3>Failed Transactions</h3>
            <div class="number">
                <?= (int)$summary["failed_transactions"] ?>
            </div>
        </div>
    </div>

    <h2>Recent Transactions</h2>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Transaction Reference</th>
                    <th>Passenger</th>
                    <th>Route</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($transactions) > 0): ?>
                <?php foreach ($transactions as $transaction): ?>
                    <?php
                    $status = strtolower($transaction["status"]);
                    $statusClass = in_array(
                        $status,
                        ["successful", "pending", "failed", "refunded"],
                        true
                    ) ? $status : "pending";
                    ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($transaction["created_at"], ENT_QUOTES, "UTF-8") ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($transaction["transaction_reference"], ENT_QUOTES, "UTF-8") ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($transaction["full_name"], ENT_QUOTES, "UTF-8") ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($transaction["origin"], ENT_QUOTES, "UTF-8") ?>
                            to
                            <?= htmlspecialchars($transaction["destination"], ENT_QUOTES, "UTF-8") ?>
                        </td>
                        <td>
                            ₦<?= number_format((float)$transaction["amount"], 2) ?>
                        </td>
                        <td>
                            <span class="status <?= $statusClass ?>">
                                <?= htmlspecialchars(ucfirst($status), ENT_QUOTES, "UTF-8") ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6">No transactions recorded yet.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <p class="note">
        Revenue includes successful transactions recorded in this local project.
        Demo payment confirmations are simulated and do not represent real money received.
        The table displays up to 100 recent transactions.
    </p>

    <button class="print" onclick="window.print()">Print Report</button>
    <a class="back" href="dashboard.php">Back to Dashboard</a>
</div>
</body>
</html>
