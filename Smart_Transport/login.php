
<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

require_once __DIR__ . '/includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, full_name, email, password, role
                 FROM users
                 WHERE email = :email
                 LIMIT 1"
            );

            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];

                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please check your database connection.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Transportation Ticketing Platform</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #0f172a, #2563eb);
        }

        .login-box {
            width: 100%;
            max-width: 420px;
            padding: 35px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.25);
        }

        .logo {
            text-align: center;
            font-size: 45px;
            margin-bottom: 10px;
        }

        h1 {
            margin-bottom: 10px;
            text-align: center;
            color: #0f172a;
            font-size: 25px;
        }

        .subtitle {
            margin-bottom: 25px;
            text-align: center;
            color: #64748b;
            line-height: 1.5;
        }

        label {
            display: block;
            margin: 15px 0 7px;
            font-weight: bold;
            color: #334155;
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 15px;
        }

        input:focus {
            outline: 2px solid #bfdbfe;
            border-color: #2563eb;
        }

        button {
            width: 100%;
            padding: 13px;
            margin-top: 22px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .error {
            padding: 12px;
            margin-bottom: 15px;
            color: #991b1b;
            background: #fee2e2;
            border-radius: 7px;
        }

        .register {
            margin-top: 22px;
            text-align: center;
            color: #64748b;
            font-size: 14px;
        }

        .register a {
            color: #2563eb;
            font-weight: bold;
            text-decoration: none;
        }

        footer {
            margin-top: 25px;
            text-align: center;
            color: #94a3b8;
            font-size: 12px;
        }
    </style>
</head>
<body>

<div class="login-box">
    <div class="logo">🚌</div>

    <h1>Welcome Back</h1>

    <p class="subtitle">
        Smart Transportation Ticketing Platform<br>
        Sign in to continue.
    </p>

    <?php if ($error !== ''): ?>
        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <label for="email">Email Address</label>
        <input
            type="email"
            id="email"
            name="email"
            placeholder="Enter your email"
            autocomplete="email"
            required
        >

        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            placeholder="Enter your password"
            autocomplete="current-password"
            required
        >

        <button type="submit">Sign In</button>
    </form>

    <div class="register">
        Don't have an account?
        <a href="register.php">Create Account</a>
    </div>

    <footer>
        &copy; <?= date('Y') ?> Smart Transportation Ticketing Platform
    </footer>
</div>

</body>
</html>
