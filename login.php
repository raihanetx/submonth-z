<?php
session_start();

// --- Configuration ---
$config_file_path = 'config.json';
if (!file_exists($config_file_path)) {
    // Create a default config if it doesn't exist. The password will be 'password123'
    $hashed_password = password_hash('password123', PASSWORD_DEFAULT);
    $default_config = [
        "admin_password" => $hashed_password
        // other default settings can go here
    ];
    file_put_contents($config_file_path, json_encode($default_config, JSON_PRETTY_PRINT));
}
$config = json_decode(file_get_contents($config_file_path), true);
$ADMIN_PASSWORD_HASH = $config['admin_password'] ?? '$2y$10$abcdefghijklmnopqrstuv'; // A dummy hash

$error_message = '';

// If already logged in, redirect to the admin panel
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: admin.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_is_correct = false;
    $submitted_password = $_POST['password'] ?? '';
    $stored_password_value = $config['admin_password'] ?? '';

    // 1. Check if the stored password is a valid hash and verify against it
    if (password_verify($submitted_password, $stored_password_value)) {
        $password_is_correct = true;
    }
    // 2. Legacy check for plaintext password from config.
    else if (!empty($submitted_password) && $submitted_password === $stored_password_value) {
        $password_is_correct = true;
        // --- Security Upgrade ---
        // Upgrade the plaintext password to a secure hash.
        $config['admin_password'] = password_hash($submitted_password, PASSWORD_DEFAULT);
        // Save the updated config with the new hash.
        file_put_contents($config_file_path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    if ($password_is_correct) {
        // Password is correct, set session variable and regenerate session ID
        session_regenerate_id(true);
        $_SESSION['loggedin'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $error_message = 'Invalid password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #6D28D9; --primary-color-darker: #5B21B6; }
        body { font-family: 'Inter', sans-serif; }
        .form-input:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px #E9D5FF; outline: none; }
        .btn-primary { background-color: var(--primary-color); }
        .btn-primary:hover { background-color: var(--primary-color-darker); }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-xl shadow-lg">
        <div class="text-center">
            <h1 class="text-3xl font-bold text-gray-900">Admin Panel</h1>
            <p class="mt-2 text-gray-600">Please enter your password to access the dashboard.</p>
        </div>
        <form method="POST" action="login.php" class="space-y-6">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                    <i class="fa-solid fa-lock text-gray-400"></i>
                </span>
                <input id="password" name="password" type="password" required
                       class="w-full pl-10 pr-4 py-3 text-gray-800 bg-gray-50 border border-gray-300 rounded-lg shadow-sm form-input focus:outline-none transition"
                       placeholder="Password">
            </div>
            <?php if ($error_message): ?>
                <p class="text-sm text-center text-red-600 font-medium"><?= htmlspecialchars($error_message) ?></p>
            <?php endif; ?>
            <div>
                <button type="submit" class="w-full px-4 py-3 font-semibold text-white transition-colors duration-200 transform rounded-lg btn-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                    Login
                </button>
            </div>
        </form>
    </div>
</body>
</html>