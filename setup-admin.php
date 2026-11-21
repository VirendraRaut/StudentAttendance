
<?php

session_start();

require_once __DIR__ . '/config/database.php';

// Check whether an admin account already exists
$stmt = $pdo->query("SELECT COUNT(*) FROM admins");
$adminExists = (int) $stmt->fetchColumn() > 0;

// Generate CSRF token
if (empty($_SESSION['setup_csrf'])) {
    $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($adminExists) {
        $message = "Admin account already exists. Setup is locked.";
        $messageType = "error";

    } elseif (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['setup_csrf'], $_POST['csrf_token'])
    ) {
        $message = "Invalid request. Please refresh the page.";
        $messageType = "error";

    } else {

        $fullName = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (
            $fullName === '' ||
            $username === '' ||
            $email === '' ||
            $password === '' ||
            $confirmPassword === ''
        ) {
            $message = "All fields are required.";
            $messageType = "error";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
            $messageType = "error";

        } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
            $message = "Username must be 3-50 characters and contain only letters, numbers, dots, underscores or hyphens.";
            $messageType = "error";

        } elseif (strlen($password) < 10) {
            $message = "Password must contain at least 10 characters.";
            $messageType = "error";

        } elseif ($password !== $confirmPassword) {
            $message = "Passwords do not match.";
            $messageType = "error";

        } else {

            try {
                // Check for duplicate username or email
                $check = $pdo->prepare(
                    "SELECT id FROM admins
                     WHERE username = :username OR email = :email
                     LIMIT 1"
                );

                $check->execute([
                    ':username' => $username,
                    ':email' => $email
                ]);

                if ($check->fetch()) {
                    $message = "Username or email already exists.";
                    $messageType = "error";

                } else {

                    // Hash password securely
                    $hashedPassword = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    // Create initial Admin account
                    $insert = $pdo->prepare(
                        "INSERT INTO admins
                        (username, email, password, full_name, status)
                        VALUES
                        (:username, :email, :password, :full_name, 'Active')"
                    );

                    $insert->execute([
                        ':username' => $username,
                        ':email' => $email,
                        ':password' => $hashedPassword,
                        ':full_name' => $fullName
                    ]);

                    // Lock setup after successful creation
                    unset($_SESSION['setup_csrf']);
                    $adminExists = true;

                    $message = "Admin account created successfully. You can now log in.";
                    $messageType = "success";
                }

            } catch (PDOException $e) {
                error_log("Admin Setup Error: " . $e->getMessage());

                $message = "Unable to create the account. Please check the server logs.";
                $messageType = "error";
            }
        }
    }
}

function escapeOutput($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Initial Admin Setup</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 460px;
            background: #ffffff;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }

        h2 {
            text-align: center;
            margin-bottom: 10px;
            color: #1e293b;
        }

        .subtitle {
            text-align: center;
            color: #64748b;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 17px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: bold;
            color: #334155;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 14px;
            outline: none;
        }

        input:focus {
            border-color: #2563eb;
        }

        button {
            width: 100%;
            padding: 13px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .message {
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .locked {
            text-align: center;
            color: #166534;
            background: #dcfce7;
            padding: 18px;
            border-radius: 8px;
            line-height: 1.6;
        }

        .login-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #2563eb;
            text-decoration: none;
        }
    </style>
</head>

<body>

<div class="container">

    <h2>Admin Account Setup</h2>

    <p class="subtitle">
        Create the initial administrator account
    </p>

    <?php if ($message !== ''): ?>
        <div class="message <?= $messageType ?>">
            <?= escapeOutput($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($adminExists): ?>

        <div class="locked">
            <strong>Setup is locked.</strong><br>
            An Admin account already exists.
            No additional account can be created through this page.
        </div>

        <a class="login-link" href="index.php">
            Go to Admin Login
        </a>

    <?php else: ?>

        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= escapeOutput($_SESSION['setup_csrf']) ?>"
            >

            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    maxlength="150"
                    required
                >
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    minlength="3"
                    maxlength="50"
                    pattern="[a-zA-Z0-9_.-]+"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="150"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="10"
                    required
                >
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    minlength="10"
                    required
                >
            </div>

            <button type="submit">
                Create Admin Account
            </button>

        </form>

    <?php endif; ?>

</div>

</body>
</html>
