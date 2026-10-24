
<?php
session_start();
require_once "includes/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (!in_array($_SESSION["role"], ["admin", "staff"], true)) {
    http_response_code(403);
    exit("Access denied. Only authorised staff can verify tickets.");
}

$message = "";
$resultType = "";
$scanToken = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $scanToken = trim($_POST["qr_token"] ?? "");

    if ($scanToken === "" || strlen($scanToken) > 200) {
        $message = "Please enter a valid QR token.";
        $resultType = "error";
    } else {
        try {
            $pdo->beginTransaction();

            // Lock the booking row so two staff members cannot use
            // the same valid ticket at the same time.
            $stmt = $pdo->prepare("
                SELECT
                    b.id,
                    b.booking_reference,
                    b.payment_status,
                    b.ticket_status,
                    t.origin,
                    t.destination,
                    t.departure_time,
                    t.status AS trip_status
                FROM bookings b
                JOIN trips t ON t.id = b.trip_id
                WHERE b.qr_token = ?
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([$scanToken]);
            $booking = $stmt->fetch();

            if (!$booking) {
                $message = "REJECTED: Ticket QR code was not found.";
                $resultType = "error";

                // Record rejected scan. booking_id is nullable only
                // if your database schema permits it; unknown codes
                // are not logged here to avoid a foreign-key error.
            } else {
                $valid = $booking["payment_status"] === "paid"
                    && $booking["ticket_status"] === "valid"
                    && $booking["trip_status"] === "scheduled";

                if ($valid) {
                    $stmt = $pdo->prepare("
                        UPDATE bookings
                        SET ticket_status = 'used'
                        WHERE id = ? AND ticket_status = 'valid'
                    ");
                    $stmt->execute([$booking["id"]]);

                    if ($stmt->rowCount() === 1) {
                        $stmt = $pdo->prepare("
                            INSERT INTO ticket_scans (booking_id, staff_id, result)
                            VALUES (?, ?, 'accepted')
                        ");
                        $stmt->execute([
                            $booking["id"],
                            $_SESSION["user_id"]
                        ]);

                        $message = "ACCEPTED: Ticket verified successfully. Booking reference: "
                            . $booking["booking_reference"]
                            . ". Route: " . $booking["origin"]
                            . " to " . $booking["destination"]
                            . ". The ticket is now marked as used.";
                        $resultType = "success";
                    } else {
                        $message = "REJECTED: This ticket has already been used.";
                        $resultType = "error";
                    }
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO ticket_scans (booking_id, staff_id, result)
                        VALUES (?, ?, 'rejected')
                    ");
                    $stmt->execute([
                        $booking["id"],
                        $_SESSION["user_id"]
                    ]);

                    if ($booking["payment_status"] !== "paid") {
                        $message = "REJECTED: Payment has not been confirmed.";
                    } elseif ($booking["ticket_status"] !== "valid") {
                        $message = "REJECTED: Ticket is cancelled or has already been used.";
                    } else {
                        $message = "REJECTED: The trip is not scheduled.";
                    }

                    $resultType = "error";
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());
            $message = "Verification could not be completed. Check the database configuration and try again.";
            $resultType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Ticket Verification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #eef3f8;
            margin: 0;
            padding: 25px;
            color: #243247;
        }
        .container {
            max-width: 650px;
            margin: 30px auto;
            background: white;
            padding: 28px;
            border-radius: 12px;
            box-shadow: 0 4px 16px #00000012;
        }
        h1 { color: #164e83; }
        label { display: block; margin: 18px 0 8px; font-weight: bold; }
        input {
            box-sizing: border-box;
            width: 100%;
            padding: 12px;
            border: 1px solid #b9c5d3;
            border-radius: 6px;
        }
        button, a {
            display: inline-block;
            margin-top: 15px;
            padding: 11px 15px;
            border: none;
            border-radius: 6px;
            background: #164e83;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 7px;
            overflow-wrap: anywhere;
        }
        .success { background: #d8f5df; color: #14532d; }
        .error { background: #ffe0e0; color: #8a1c1c; }
        .hint { color: #68778a; font-size: 14px; }
    </style>
</head>
<body>
<div class="container">
    <h1>QR Ticket Verification</h1>
    <p>Enter the token from a passenger's QR code to verify the ticket.</p>
    <p class="hint">
        This version accepts a pasted QR token. Camera scanning can be added later.
    </p>

    <form method="POST">
        <label for="qr_token">QR Token</label>
        <input
            type="text"
            id="qr_token"
            name="qr_token"
            maxlength="200"
            required
            autocomplete="off"
            placeholder="Paste the QR token here"
        >
        <button type="submit">Verify Ticket</button>
    </form>

    <?php if ($message !== ""): ?>
        <div class="result <?= htmlspecialchars($resultType, ENT_QUOTES, "UTF-8") ?>">
            <strong><?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?></strong>
        </div>
    <?php endif; ?>

    <a href="dashboard.php">Back to Dashboard</a>
</div>
</body>
</html>
