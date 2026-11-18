<?php
require_once __DIR__ . '/config/database.php';

$stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
$stmt->execute(['admin']);

if (!$stmt->fetch()) {
    $hash = password_hash('Admin@12345', PASSWORD_DEFAULT);

    $pdo->prepare("
        INSERT INTO users (name, username, password_hash, role)
        VALUES (?, ?, ?, 'admin')
    ")->execute(['Store Owner', 'admin', $hash]);

    $message = 'Demo admin account created.';
} else {
    $message = 'Admin account already exists; no changes made.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup complete</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="setup-page">
    <div class="auth-card">
        <div class="brand-mark large">T</div>
        <h1>Initial setup</h1>

        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>

        <div class="setup-credentials">
            <strong>Demo login</strong><br>
            Username: <code>admin</code><br>
            Password: <code>Admin@12345</code>
        </div>

        <p class="muted">
            Set a unique password for the store owner. Once the owner can sign in,
            delete setup.php from the project folder.
        </p>

        <a class="btn primary full" href="login.php">Go to login</a>
    </div>
</body>
</html>