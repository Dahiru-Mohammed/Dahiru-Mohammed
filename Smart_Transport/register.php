
<?php
session_start();
require_once __DIR__ . '/includes/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($full_name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($password) < 8) {
        $error = "Enter your name, a valid email, and a password of at least 8 characters.";
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                "INSERT INTO users (full_name, email, password, phone, role)
                 VALUES (?, ?, ?, ?, 'passenger')"
            );
            $stmt->execute([$full_name, $email, $hash, $phone]);

            $success = "Registration successful! You can now log in.";
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = "This email is already registered.";
            } else {
                error_log($e->getMessage());
                $error = "Registration failed. Please try again.";
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
<title>Passenger Registration</title>
<style>
body{font-family:Arial;background:#eef3f8;margin:0;display:grid;
place-items:center;min-height:100vh}
.card{background:white;padding:30px;border-radius:12px;width:min(380px,85%);
box-shadow:0 5px 20px #0001}
h2{color:#123b63;text-align:center}
input,button{box-sizing:border-box;width:100%;padding:12px;margin:8px 0;
border-radius:6px}
input{border:1px solid #ccd5df}
button{background:#0878d1;color:white;border:0;cursor:pointer}
.error{color:#b42318}.success{color:#167647}
a{color:#0878d1}
</style>
</head>
<body>
<div class="card">
<h2>Create Passenger Account</h2>

<?php if ($error): ?>
<p class="error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<?php if ($success): ?>
<p class="success"><?= htmlspecialchars($success) ?></p>
<p><a href="login.php">Proceed to login</a></p>
<?php else: ?>
<form method="post">
<input name="full_name" placeholder="Full name" required>
<input type="email" name="email" placeholder="Email address" required>
<input name="phone" placeholder="Phone number">
<input type="password" name="password"
placeholder="Password (minimum 8 characters)"
minlength="8" required>
<button type="submit">Register</button>
</form>
<?php endif; ?>

<p>Already registered? <a href="login.php">Login here</a></p>
</div>
</body>
</html>
