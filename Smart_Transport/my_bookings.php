
<?php
session_start();
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT b.booking_reference, b.seat_count, b.total_amount,
            b.payment_status, b.ticket_status, b.created_at,
            t.origin, t.destination, t.departure_time
     FROM bookings b
     INNER JOIN trips t ON b.trip_id = t.id
     WHERE b.passenger_id = ?
     ORDER BY b.created_at DESC"
);
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Tickets | Smart Transport</title>
<style>
body{font-family:Arial,sans-serif;background:#f2f5fa;margin:0;color:#263448}
header{background:#103b63;color:white;padding:20px 5%}
header a{color:white}
main{max-width:1000px;margin:30px auto;padding:0 18px}
.ticket{background:white;padding:22px;margin:18px 0;border-radius:12px;
box-shadow:0 3px 12px #0000000c}
.route{font-size:21px;font-weight:bold;color:#103b63}
.reference{color:#0878d1;font-weight:bold;overflow-wrap:anywhere}
.status{display:inline-block;background:#fff0cf;color:#825500;
padding:6px 10px;border-radius:5px}
a.button{display:inline-block;background:#0878d1;color:white;
padding:11px 15px;border-radius:6px;text-decoration:none}
.empty{background:white;padding:20px;border-radius:10px}
</style>
</head>
<body>
<header>
<h2>Smart Transportation Ticketing Platform</h2>
<a href="dashboard.php">← Dashboard</a>
</header>

<main>
<h1>My Tickets and Bookings</h1>

<?php if (!$bookings): ?>
<div class="empty">
<p>You have no bookings yet.</p>
<a class="button" href="trips.php">Browse Available Trips</a>
</div>
<?php else: ?>

<?php foreach ($bookings as $b): ?>
<div class="ticket">
<div class="route">
<?= htmlspecialchars($b['origin']) ?>
→
<?= htmlspecialchars($b['destination']) ?>
</div>

<p><strong>Ticket Reference:</strong>
<span class="reference">
<?= htmlspecialchars($b['booking_reference']) ?>
</span></p>

<p><strong>Departure:</strong>
<?= htmlspecialchars(date('d M Y, h:i A',
    strtotime($b['departure_time']))) ?></p>

<p><strong>Seats:</strong>
<?= (int)$b['seat_count'] ?></p>

<p><strong>Total:</strong>
₦<?= number_format((float)$b['total_amount'], 2) ?></p>

<p><strong>Payment Status:</strong>
<?= htmlspecialchars(ucfirst($b['payment_status'])) ?></p>

<p><strong>Ticket Status:</strong>
<?= htmlspecialchars(ucfirst($b['ticket_status'])) ?></p>

<?php if ($b['payment_status'] === 'paid'
    && $b['ticket_status'] === 'valid'): ?>
<a class="button"
href="ticket.php?ref=<?= rawurlencode($b['booking_reference']) ?>">
View E-Ticket
</a>
<?php elseif ($b['payment_status'] === 'pending'): ?>
<p class="status">Awaiting payment confirmation</p>
<?php endif; ?>

</div>
<?php endforeach; ?>

<?php endif; ?>
</main>
</body>
</html>
