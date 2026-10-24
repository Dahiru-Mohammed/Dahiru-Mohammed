
<?php
session_start();
require_once "includes/db.php";

// Only logged-in administrators can confirm demo payments.
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    http_response_code(403);
    exit("Access denied. Only administrators can confirm payments.");
}

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $booking_id = filter_input(INPUT_POST, "booking_id", FILTER_VALIDATE_INT);

    if (!$booking_id) {
        $error = "Invalid booking selected.";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, payment_status
                FROM bookings
                WHERE id = ?
                FOR UPDATE
            ");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch();

            if (!$booking) {
                throw new Exception("Booking not found.");
            }

            if ($booking["payment_status"] !== "pending") {
                throw new Exception("This booking is not awaiting payment.");
            }

            $stmt = $pdo->prepare("
                UPDATE bookings
                SET payment_status = 'paid'
                WHERE id = ?
            ");
            $stmt->execute([$booking_id]);

            $stmt = $pdo->prepare("
                UPDATE transactions
                SET status = 'successful'
                WHERE booking_id = ? AND status = 'pending'
            ");
            $stmt->execute([$booking_id]);

            if ($stmt->rowCount() !== 1) {
                throw new Exception(
                    "A pending transaction was not found. No payment was confirmed."
                );
            }

            $pdo->commit();
            $message = "Demo payment confirmed successfully. The passenger can now view the e-ticket.";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e instanceof PDOException
                ? "A database error occurred. Please check the database setup."
                : $e->getMessage();
        }
    }
}

// Show bookings awaiting demo payment confirmation.
$stmt = $pdo->query("
    SELECT
        b.id,
        b.booking_reference,
        b.total_amount,
        b.seat_count,
        u.full_name,
        t.origin,
        t.destination,
        t.departure_time
    FROM bookings b
    JOIN users u ON b.passenger_id = u.id
    JOIN trips t ON b.trip_id = t.id
    WHERE b.payment_status = 'pending'
    ORDER BY b.created_at ASC
");
$bookings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Demo Payments</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f3f6fa;
            margin: 0;
            padding: 25px;
            color: #243247;
        }
        .container {
            max-width: 1100px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px #00000012;
        }
        h1 { color: #164e83; }
        .notice {
            background: #fff4d6;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .success {
            background: #d8f5df;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        .error {
            background: #ffe0e0;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        th { background: #164e83; color: white; }
        button {
            background: #198754;
            color: white;
            padding: 9px 12px;
            border: 0;
            border-radius: 5px;
            cursor: pointer;
        }
        .back { display: inline-block; margin-top: 20px; }
        @media (max-width: 750px) {
            .container { overflow-x: auto; }
            table { min-width: 750px; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Admin: Confirm Demo Payments</h1>

    <div class="notice">
        <strong>Classroom demonstration only:</strong>
        Clicking the button records a simulated payment. It does not process real money.
    </div>

    <?php if ($message): ?>
        <div class="success">
            <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
        </div>
    <?php endif; ?>

    <?php if (count($bookings) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Passenger</th>
                    <th>Booking Reference</th>
                    <th>Route</th>
                    <th>Seats</th>
                    <th>Amount</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bookings as $booking): ?>
                <tr>
                    <td><?= htmlspecialchars($booking["full_name"], ENT_QUOTES, "UTF-8") ?></td>
                    <td><?= htmlspecialchars($booking["booking_reference"], ENT_QUOTES, "UTF-8") ?></td>
                    <td>
                        <?= htmlspecialchars($booking["origin"], ENT_QUOTES, "UTF-8") ?>
                        to
                        <?= htmlspecialchars($booking["destination"], ENT_QUOTES, "UTF-8") ?>
                    </td>
                    <td><?= (int)$booking["seat_count"] ?></td>
                    <td>₦<?= number_format((float)$booking["total_amount"], 2) ?></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Confirm this simulated payment for the school project?');">
                            <input type="hidden" name="booking_id"
                                   value="<?= (int)$booking["id"] ?>">
                            <button type="submit">Confirm Demo Payment</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>There are no bookings awaiting payment confirmation.</p>
    <?php endif; ?>

    <a class="back" href="dashboard.php">Back to Dashboard</a>
</div>
</body>
</html>
