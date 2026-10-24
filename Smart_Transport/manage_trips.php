
<?php
session_start();
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Access denied. Administrator access is required.');
}

date_default_timezone_set('Africa/Lagos');

$message = '';
$message_type = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $origin = trim($_POST['origin'] ?? '');
    $destination = trim($_POST['destination'] ?? '');
    $departure = trim($_POST['departure_time'] ?? '');
    $fare_input = $_POST['fare'] ?? '';
    $seats_input = $_POST['available_seats'] ?? '';

    $fare = filter_var($fare_input, FILTER_VALIDATE_FLOAT);
    $seats = filter_var($seats_input, FILTER_VALIDATE_INT);

    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d\TH:i',
        $departure,
        new DateTimeZone('Africa/Lagos')
    );

    $date_errors = DateTimeImmutable::getLastErrors();

    $valid_date = $date !== false
        && $date->format('Y-m-d\TH:i') === $departure
        && ($date_errors === false ||
            ($date_errors['warning_count'] === 0 &&
             $date_errors['error_count'] === 0));

    $now = new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos'));

    if ($origin === '' || $destination === '') {
        $message = 'Enter both the origin and destination.';
    } elseif (!$valid_date) {
        $message = 'Please select a valid departure date and time.';
    } elseif ($date->getTimestamp() <= $now->getTimestamp()) {
        $message = 'Departure time must be in the future. Current server time: '
            . $now->format('d M Y, h:i A');
    } elseif ($fare === false || $fare <= 0) {
        $message = 'Enter a fare greater than zero.';
    } elseif ($seats === false || $seats < 1 || $seats > 1000) {
        $message = 'Enter between 1 and 1000 available seats.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO trips
                (origin, destination, departure_time, fare, available_seats)
                VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $origin,
                $destination,
                $date->format('Y-m-d H:i:s'),
                $fare,
                $seats
            ]);

            $message = 'Trip added successfully!';
            $message_type = 'success';
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $message = 'Could not save the trip. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Transportation Trips</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f2f5fa;
    margin: 0;
    padding: 25px;
    color: #243449;
}
main {
    max-width: 600px;
    margin: 20px auto;
    background: white;
    padding: 28px;
    border-radius: 12px;
    box-shadow: 0 4px 16px #00000010;
}
h2 { color: #103b63; }
label { display: block; margin-top: 14px; }
input, button {
    box-sizing: border-box;
    width: 100%;
    padding: 12px;
    margin-top: 7px;
    border-radius: 6px;
}
input { border: 1px solid #ccd5df; }
button {
    margin-top: 22px;
    background: #0878d1;
    color: white;
    border: 0;
    cursor: pointer;
}
.message { padding: 12px; border-radius: 6px; }
.success { background: #e7f7ed; color: #176b43; }
.error { background: #fff0ef; color: #b42318; }
a { color: #0878d1; }
</style>
</head>
<body>
<main>
<h2>Add a Transportation Trip</h2>

<?php if ($message !== ''): ?>
<p class="message <?= $message_type ?>">
<?= htmlspecialchars($message) ?>
</p>
<?php endif; ?>

<form method="post">
<label for="origin">Origin</label>
<input id="origin" name="origin"
value="<?= htmlspecialchars($_POST['origin'] ?? '') ?>" required>

<label for="destination">Destination</label>
<input id="destination" name="destination"
value="<?= htmlspecialchars($_POST['destination'] ?? '') ?>" required>

<label for="departure_time">Departure Date and Time</label>
<input id="departure_time" type="datetime-local"
name="departure_time"
value="<?= htmlspecialchars($_POST['departure_time'] ?? '') ?>"
required>

<label for="fare">Ticket Fare (₦)</label>
<input id="fare" type="number" name="fare"
min="0.01" step="0.01"
value="<?= htmlspecialchars($_POST['fare'] ?? '') ?>" required>

<label for="available_seats">Available Seats</label>
<input id="available_seats" type="number"
name="available_seats" min="1" max="1000"
value="<?= htmlspecialchars($_POST['available_seats'] ?? '40') ?>"
required>

<button type="submit">Add Trip</button>
</form>

<p><a href="dashboard.php">Back to Dashboard</a></p>
</main>
</body>
</html>
