
<?php
require_once __DIR__ . '/includes/db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($full_name === '' ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($password) < 12) {
        $message = 'Enter a name, valid email, and password of at least 12 characters.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                "INSERT INTO users (full_name, email, password, role)
                 VALUES (?, ?, ?, 'admin')"
            );

            $stmt->execute([$full_name, $email, $hash]);

            $message = 'Administrator created successfully. Delete create_admin.php now.';
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $message = 'That email is already registered. Use a different email.';
            } else {
                error_log($e->getMessage());
                $message = 'Could not create administrator.';
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
<title>Create Administrator</title>
<style>
body{font-family:Arial;background:#eef3f8;display:grid;place-items:center;
min-height:100vh;margin:0}
form{background:white;padding:28px;border-radius:12px;width:min(380px,85%)}
input,button{box-sizing:border-box;width:100%;padding:12px;margin:9px 0}
button{background:#103b63;color:white;border:0;cursor:pointer}
</style>
</head>
<body>
<form method="post">
<h2>Create Administrator</h2>
<p><?= htmlspecialchars($message) ?></p>
<label>Full Name</label>
<input name="full_name" required>
<label>Email Address</label>
<input type="email" name="email" required>
<label>Password (minimum 12 characters)</label>
<input type="password" name="password" minlength="12" required>
<button type="submit">Create Administrator</button>
</form>
</body>
</html>
