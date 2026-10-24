
<?php
session_start();
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

date_default_timezone_set('Africa/Lagos');

$stmt = $pdo->query(
    "SELECT id, origin, destination, departure_time,
            fare, available_seats
     FROM trips
     WHERE status = 'scheduled'
       AND departure_time > NOW()
       AND available_seats > 0
     ORDER BY departure_time ASC"
);

$trips = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Available Trips | Smart Transport</title>
<style>
* { box-sizing: border-box; }
body {
    font-family: Arial, sans-serif;
    margin: 0;
    background: #f2f5fa;
    color: #263448;
}
header {
    background: #103b63;
    color: white;
    padding: 20px 5%;
}
header a { color: white; text-decoration: none; }
main { max-width: 1050px; margin: 30px auto; padding: 0 20px; }
.trip-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 20px;
}
.trip {
    background: white;
    padding: 22px;
    border-radius: 12px;
    box-shadow: 0 3px 12px #0000000c;
}
.route { font-size: 20px; font-weight: bold; color: #103b63; }
.fare { font-size: 24px; font-weight: bold; color: #0878d1; }
button {
    width: 100%;
    background: #0878d1;
    color: white;
    padding: 12px;
    border: 0;
    border-radius: 7px;
    cursor: pointer;
}
a.back { display: inline-block; margin-bottom: 20px; }
.empty {
    background: white;
    padding: 20px;
    border-radius: 10px;
}
</style>
</head>
<body>
<header>
<h2>Smart Transportation Ticketing Platform</h2>
<a href="dashboard.php">← Back to Dashboard</a>
</header>

<main>
<h1>Available Trips</h1>
<p>Choose your destination and review the departure details.</p>

<?php if (!$trips): ?>
<div class="empty">
<p>No future trips are currently available.</p>
<p>Please check again later or contact the administrator.</p>
</div>
<?php else: ?>
<div class="trip-list">
<?php foreach ($trips as $trip): ?>
<div class="trip">
<div class="route">
<?= htmlspecialchars($trip['origin']) ?>
→
<?= htmlspecialchars($trip['destination']) ?>
</div>

<p><strong>Departure:</strong><br>
<?= htmlspecialchars(
    date('d M Y, h:i A', strtotime($trip['departure_time']))
) ?></p>

<p class="fare">
₦<?= number_format((float)$trip['fare'], 2) ?>
</p>

<p><strong>Available seats:</strong>
<?= (int)$trip['available_seats'] ?></p>

<form method="get" action="book_ticket.php">
<input type="hidden" name="trip_id"
value="<?= (int)$trip['id'] ?>">
<button type="submit">Book Ticket</button>
</form>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

</main>
</body>
</html>
