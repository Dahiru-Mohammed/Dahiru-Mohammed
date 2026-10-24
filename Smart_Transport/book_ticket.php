
<?php
session_start();
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

date_default_timezone_set('Africa/Lagos');

$error = '';
$trip = null;
$trip_id = filter_input(INPUT_GET, 'trip_id', FILTER_VALIDATE_INT);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $trip_id = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);
}

if (!$trip_id || $trip_id < 1) {
    exit('Invalid trip selected. Please return to Available Trips.');
}

$stmt = $pdo->prepare(
    "SELECT id, origin, destination, departure_time, fare,
            available_seats
     FROM trips
     WHERE id = ? AND status = 'scheduled'
       AND departure_time > NOW() AND available_seats > 0"
);
$stmt->execute([$trip_id]);
$trip = $stmt->fetch();

if (!$trip) {
    exit('This trip is no longer available. Please return to Available Trips.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seats = filter_input(INPUT_POST, 'seat_count', FILTER_VALIDATE_INT);
    $seats = $seats === false || $seats === null ? 0 : $seats;

    if ($seats < 1 || $seats > 10) {
        $error = 'Choose between 1 and 10 seats.';
    } else {
        try {
            $pdo->beginTransaction();

            // Lock the trip row to prevent overselling seats.
            $stmt = $pdo->prepare(
                "SELECT fare, available_seats
                 FROM trips
                 WHERE id = ? AND status = 'scheduled'
                   AND departure_time > NOW()
                 FOR UPDATE"
            );
            $stmt->execute([$trip_id]);
            $locked_trip = $stmt->fetch();

            if (!$locked_trip ||
                (int)$locked_trip['available_seats'] < $seats) {
                throw new RuntimeException(
                    'Not enough seats remain on this trip.'
                );
            }

            $total = (float)$locked_trip['fare'] * $seats;

            // Generate unguessable ticket credentials.
            $reference = 'ST-' . strtoupper(bin2hex(random_bytes(8)));
            $qr_token = bin2hex(random_bytes(32));
            $transaction_ref = 'TX-' . strtoupper(bin2hex(random_bytes(8)));

            $stmt = $pdo->prepare(
                "INSERT INTO bookings
                 (booking_reference, passenger_id, trip_id, seat_count,
                  total_amount, payment_status, ticket_status, qr_token)
                 VALUES (?, ?, ?, ?, ?, 'pending', 'valid', ?)"
            );

            $stmt->execute([
                $reference,
                $_SESSION['user_id'],
                $trip_id,
                $seats,
                $total,
                $qr_token
            ]);

            $booking_id = (int)$pdo->lastInsertId();

            // Record a pending transaction. No payment is claimed yet.
            $stmt = $pdo->prepare(
                "INSERT INTO transactions
                 (booking_id, transaction_reference, amount, status)
                 VALUES (?, ?, ?, 'pending')"
            );
            $stmt->execute([$booking_id, $transaction_ref, $total]);

            $stmt = $pdo->prepare(
                "UPDATE trips
                 SET available_seats = available_seats - ?
                 WHERE id = ? AND available_seats >= ?"
            );
            $stmt->execute([$seats, $trip_id, $seats]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'Seat availability changed. Please try again.'
                );
            }

            $pdo->commit();

            header(
                'Location: my_bookings.php?booking=' .
                rawurlencode($reference)
            );
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($e instanceof RuntimeException) {
                $error = $e->getMessage();
            } else {
                error_log($e->getMessage());
                $error = 'Booking could not be completed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Book Ticket | Smart Transport</title>
<style>
body{font-family:Arial,sans-serif;background:#f2f5fa;margin:0;padding:25px;color:#263448}
main{max-width:520px;margin:20px auto;background:white;padding:28px;border-radius:12px;box-shadow:0 3px 15px #0001}
h2{color:#103b63}
input,button{box-sizing:border-box;width:100%;padding:12px;margin:9px 0;border-radius:6px}
input{border:1px solid #ccd5df}
button{background:#0878d1;color:white;border:0;cursor:pointer}
.error{color:#b42318;background:#fff0ef;padding:10px}
a{color:#0878d1}
</style>
</head>
<body>
<main>
<h2>Book Your Ticket</h2>

<p><strong>Route:</strong>
<?= htmlspecialchars($trip['origin']) ?> →
<?= htmlspecialchars($trip['destination']) ?></p>

<p><strong>Departure:</strong>
<?= htmlspecialchars(date('d M Y, h:i A',
    strtotime($trip['departure_time']))) ?></p>

<p><strong>Fare per seat:</strong>
₦<?= number_format((float)$trip['fare'], 2) ?></p>

<p><strong>Available seats:</strong>
<?= (int)$trip['available_seats'] ?></p>

<?php if ($error): ?>
<p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<form method="post">
<input type="hidden" name="trip_id"
value="<?= (int)$trip['id'] ?>">

<label for="seat_count">Number of seats (maximum 10)</label>
<input id="seat_count" type="number" name="seat_count"
min="1" max="<?= min(10, (int)$trip['available_seats']) ?>"
value="1" required>

<button type="submit">Confirm Booking</button>
</form>

<p><a href="trips.php">← Back to Available Trips</a></p>
<p><a href="dashboard.php">Back to Dashboard</a></p>
</main>
</body>
</html>
