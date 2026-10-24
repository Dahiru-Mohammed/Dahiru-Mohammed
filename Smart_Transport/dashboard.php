
<?php
session_start();
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'passenger';
$user_id = (int) $_SESSION['user_id'];

// Count scheduled trips.
$stmt = $pdo->query(
    "SELECT COUNT(*) AS total
     FROM trips
     WHERE status = 'scheduled'"
);
$total_trips = $stmt->fetch()['total'];

// Count bookings belonging to the logged-in passenger.
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS total
     FROM bookings
     WHERE passenger_id = ?"
);
$stmt->execute([$user_id]);
$my_bookings = $stmt->fetch()['total'];

// Count paid tickets belonging to this passenger.
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS total
     FROM bookings
     WHERE passenger_id = ?
       AND payment_status = 'paid'"
);
$stmt->execute([$user_id]);
$my_paid_tickets = $stmt->fetch()['total'];

// Administrator statistics.
$total_revenue = 0;
$total_passengers = 0;
$pending_payments = 0;

if ($role === 'admin') {
    $stmt = $pdo->query(
        "SELECT COALESCE(SUM(amount), 0) AS revenue
         FROM transactions
         WHERE status = 'successful'"
    );
    $total_revenue = $stmt->fetch()['revenue'];

    $stmt = $pdo->query(
        "SELECT COUNT(*) AS total
         FROM users
         WHERE role = 'passenger'"
    );
    $total_passengers = $stmt->fetch()['total'];

    $stmt = $pdo->query(
        "SELECT COUNT(*) AS total
         FROM bookings
         WHERE payment_status = 'pending'"
    );
    $pending_payments = $stmt->fetch()['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Smart Transport Dashboard</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f2f5fa;
    color: #263448;
}

header {
    background: #103b63;
    color: white;
    padding: 20px 5%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

header h2 {
    margin: 0;
}

header a {
    color: white;
    text-decoration: none;
    background: #24577f;
    padding: 10px 14px;
    border-radius: 6px;
}

.container {
    max-width: 1150px;
    margin: 30px auto;
    padding: 0 20px;
}

.welcome {
    margin-bottom: 25px;
}

.welcome h1 {
    margin-bottom: 8px;
}

.cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 18px;
}

.card {
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 3px 12px #0000000c;
}

.card h3 {
    font-size: 15px;
    color: #68778a;
    margin-top: 0;
}

.number {
    font-size: 27px;
    font-weight: bold;
    color: #0878d1;
    overflow-wrap: anywhere;
}

.section-title {
    margin-top: 32px;
    margin-bottom: 15px;
}

.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 20px;
}

.btn {
    display: inline-block;
    background: #0878d1;
    color: white;
    padding: 13px 18px;
    border-radius: 7px;
    text-decoration: none;
}

.btn:hover {
    opacity: 0.88;
}

.btn.secondary {
    background: #103b63;
}

.btn.green {
    background: #198754;
}

.btn.orange {
    background: #b96b08;
}

footer {
    text-align: center;
    padding: 25px;
    color: #68778a;
}

@media (max-width: 600px) {
    header {
        padding: 16px 5%;
    }

    .container {
        margin-top: 22px;
        padding: 0 14px;
    }

    .card {
        padding: 19px;
    }
}
</style>
</head>

<body>

<header>
    <h2>Smart Transport</h2>
    <a href="logout.php">Logout</a>
</header>

<div class="container">

    <div class="welcome">
        <h1>
            Welcome, <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>!
        </h1>
        <p>
            Smart Transportation Ticketing Platform —
            <?= htmlspecialchars(ucfirst($role), ENT_QUOTES, 'UTF-8') ?> Dashboard
        </p>
    </div>

    <div class="cards">

        <div class="card">
            <h3>Scheduled Trips</h3>
            <div class="number"><?= (int) $total_trips ?></div>
        </div>

        <div class="card">
            <h3>My Bookings</h3>
            <div class="number"><?= (int) $my_bookings ?></div>
        </div>

        <div class="card">
            <h3>My Paid Tickets</h3>
            <div class="number"><?= (int) $my_paid_tickets ?></div>
        </div>

        <?php if ($role === 'admin'): ?>
        <div class="card">
            <h3>Recorded Revenue</h3>
            <div class="number">
                ₦<?= number_format((float) $total_revenue, 2) ?>
            </div>
        </div>

        <div class="card">
            <h3>Registered Passengers</h3>
            <div class="number"><?= (int) $total_passengers ?></div>
        </div>

        <div class="card">
            <h3>Pending Payments</h3>
            <div class="number"><?= (int) $pending_payments ?></div>
        </div>
        <?php endif; ?>

    </div>

    <h2 class="section-title">Ticketing Services</h2>

    <div class="actions">
        <a class="btn" href="trips.php">View Available Trips</a>
        <a class="btn secondary" href="my_bookings.php">My Bookings and Tickets</a>
    </div>

    <?php if ($role === 'admin'): ?>

    <h2 class="section-title">Administration</h2>

    <div class="actions">
        <a class="btn secondary" href="manage_trips.php">Manage Trips</a>
        <a class="btn secondary" href="passengers.php">Passenger Management</a>
        <a class="btn secondary" href="confirm_payment.php">Confirm Demo Payments</a>
        <a class="btn secondary" href="revenue.php">Revenue Reports</a>
        <a class="btn green" href="verify_ticket.php">QR Ticket Verification</a>
    </div>

    <?php elseif ($role === 'staff'): ?>

    <h2 class="section-title">Staff Services</h2>

    <div class="actions">
        <a class="btn green" href="verify_ticket.php">QR Ticket Verification</a>
    </div>

    <?php endif; ?>

</div>

<footer>
    Smart Transportation Ticketing Platform
</footer>

</body>
</html>
