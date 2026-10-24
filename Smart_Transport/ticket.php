
<?php
session_start();
require_once "includes/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$reference = trim($_GET["ref"] ?? "");

if ($reference === "") {
    exit("Ticket reference is missing.");
}

$stmt = $pdo->prepare("
    SELECT
        b.id,
        b.booking_reference,
        b.qr_token,
        b.seat_count,
        b.total_amount,
        b.payment_status,
        b.ticket_status,
        b.created_at,
        u.full_name,
        t.origin,
        t.destination,
        t.departure_time
    FROM bookings b
    JOIN users u ON u.id = b.passenger_id
    JOIN trips t ON t.id = b.trip_id
    WHERE b.booking_reference = ?
      AND b.passenger_id = ?
    LIMIT 1
");

$stmt->execute([$reference, $_SESSION["user_id"]]);
$ticket = $stmt->fetch();

if (!$ticket) {
    http_response_code(404);
    exit("Ticket not found.");
}

if ($ticket["payment_status"] !== "paid" ||
    $ticket["ticket_status"] !== "valid") {
    exit("This ticket is not available. Payment must be confirmed and the ticket must be valid.");
}

$qrData = $ticket["qr_token"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Electronic Bus Ticket</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #eef3f8;
            padding: 25px;
            color: #243247;
        }
        .ticket {
            max-width: 650px;
            margin: auto;
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 18px #00000018;
        }
        .header {
            background: #164e83;
            color: white;
            padding: 25px;
            text-align: center;
        }
        .body { padding: 25px; }
        .reference {
            background: #e8f2ff;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            overflow-wrap: anywhere;
        }
        .details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin: 25px 0;
        }
        .label { color: #68778a; font-size: 13px; }
        .value { font-weight: bold; margin-top: 6px; }
        .qr {
            text-align: center;
            padding: 20px;
            border-top: 1px dashed #aaa;
        }
        #qrcode {
            display: inline-block;
            margin: 12px auto;
        }
        .note { font-size: 13px; color: #68778a; }
        .actions { text-align: center; margin: 20px; }
        a, button {
            display: inline-block;
            border: 0;
            border-radius: 6px;
            padding: 11px 15px;
            margin: 5px;
            background: #164e83;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }
        @media print {
            body { background: white; padding: 0; }
            .actions { display: none; }
            .ticket { box-shadow: none; }
        }
    </style>
</head>
<body>
<div class="ticket">
    <div class="header">
        <h1>Smart Transport</h1>
        <p>Electronic Passenger Ticket</p>
    </div>

    <div class="body">
        <div class="reference">
            <div class="label">BOOKING REFERENCE</div>
            <h2><?= htmlspecialchars($ticket["booking_reference"], ENT_QUOTES, "UTF-8") ?></h2>
        </div>

        <div class="details">
            <div>
                <div class="label">Passenger</div>
                <div class="value"><?= htmlspecialchars($ticket["full_name"], ENT_QUOTES, "UTF-8") ?></div>
            </div>
            <div>
                <div class="label">Route</div>
                <div class="value">
                    <?= htmlspecialchars($ticket["origin"], ENT_QUOTES, "UTF-8") ?>
                    to
                    <?= htmlspecialchars($ticket["destination"], ENT_QUOTES, "UTF-8") ?>
                </div>
            </div>
            <div>
                <div class="label">Departure</div>
                <div class="value"><?= htmlspecialchars($ticket["departure_time"], ENT_QUOTES, "UTF-8") ?></div>
            </div>
            <div>
                <div class="label">Number of Seats</div>
                <div class="value"><?= (int)$ticket["seat_count"] ?></div>
            </div>
            <div>
                <div class="label">Total Fare</div>
                <div class="value">₦<?= number_format((float)$ticket["total_amount"], 2) ?></div>
            </div>
            <div>
                <div class="label">Payment Status</div>
                <div class="value">PAID</div>
            </div>
        </div>

        <div class="qr">
            <h3>Ticket QR Code</h3>
            <div id="qrcode"></div>
            <p class="note">
                Present this QR code to authorised transport staff for verification.
            </p>
            <p class="note">
                A QR image alone does not prove validity. Staff must verify it against the system database.
            </p>
        </div>
    </div>
</div>

<div class="actions">
    <button onclick="window.print()">Print Ticket</button>
    <a href="my_bookings.php">Back to My Bookings</a>
</div>

<!-- This library is loaded from the internet. An internet connection is
     required to generate the QR code with this version. -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    const qrData = <?= json_encode($qrData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    if (typeof QRCode !== "undefined") {
        new QRCode(document.getElementById("qrcode"), {
            text: qrData,
            width: 200,
            height: 200,
            correctLevel: QRCode.CorrectLevel.M
        });
    } else {
        document.getElementById("qrcode").textContent =
            "QR library could not load. Check your internet connection.";
    }
</script>
</body>
</html>
