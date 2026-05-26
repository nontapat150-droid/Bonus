<?php
// login.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'config/auth.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT id, username, password_hash, role, full_name FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            
            header("Location: index.php");
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Business Suite</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .glass {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">

    <div class="glass w-full max-w-md p-8 rounded-2xl text-white">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold mb-2">Smart Business Suite</h1>
            <p class="text-gray-200">Sign in to continue</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-500 bg-opacity-50 text-white p-3 rounded mb-4 text-center">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1" for="username">Username</label>
                <input class="w-full px-4 py-2 rounded bg-white bg-opacity-20 border border-gray-300 border-opacity-30 focus:outline-none focus:ring-2 focus:ring-white placeholder-gray-300 text-white" type="text" id="username" name="username" required placeholder="Enter your username">
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium mb-1" for="password">Password</label>
                <input class="w-full px-4 py-2 rounded bg-white bg-opacity-20 border border-gray-300 border-opacity-30 focus:outline-none focus:ring-2 focus:ring-white placeholder-gray-300 text-white" type="password" id="password" name="password" required placeholder="Enter your password">
            </div>
            <button class="w-full py-2 px-4 bg-white text-purple-700 font-bold rounded hover:bg-gray-100 transition duration-300" type="submit">
                Sign In
            </button>
        </form>

        <div class="mt-6 text-center text-sm text-gray-300">
            <p>Default Accounts:</p>
            <p>superadmin / password123</p>
            <p>admin / password123</p>
            <p>tech1 / password123</p>
        </div>
    </div>

</body>
</html>
