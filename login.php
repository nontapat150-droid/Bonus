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
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
    } else {
        $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ระบบบริหารจัดการธุรกิจอัจฉริยะ</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            visibility: hidden;
        }
        body.ready {
            visibility: visible;
        }
        .glass {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.3);
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            document.body.classList.add('ready');
        });
    </script>
</head>
<body class="flex items-center justify-center p-6">

    <div class="glass w-full max-w-md p-10 rounded-3xl text-white">
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-white bg-opacity-20 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <span class="text-3xl">🚀</span>
            </div>
            <h1 class="text-2xl font-black mb-1">สมาร์ทสูท</h1>
            <p class="text-indigo-100 text-sm opacity-70">เข้าสู่ระบบเพื่อจัดการธุรกิจของคุณ</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-500 bg-opacity-40 border border-rose-400 text-white p-3 rounded-xl mb-6 text-center text-xs font-bold">
                ⚠️ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-4">
            <div>
                <label class="block text-[10px] font-bold mb-1.5 uppercase tracking-widest text-indigo-100" for="username">ชื่อผู้ใช้</label>
                <input class="w-full px-4 py-2.5 rounded-xl bg-white bg-opacity-10 border border-white border-opacity-20 focus:outline-none focus:ring-1 focus:ring-white placeholder-white placeholder-opacity-30 text-white text-sm transition-all" type="text" id="username" name="username" required placeholder="ชื่อผู้ใช้">
            </div>
            <div>
                <label class="block text-[10px] font-bold mb-1.5 uppercase tracking-widest text-indigo-100" for="password">รหัสผ่าน</label>   
                <input class="w-full px-4 py-2.5 rounded-xl bg-white bg-opacity-10 border border-white border-opacity-20 focus:outline-none focus:ring-1 focus:ring-white placeholder-white placeholder-opacity-30 text-white text-sm transition-all" type="password" id="password" name="password" required placeholder="รหัสผ่าน">
            </div>
            <button class="w-full py-3 px-4 bg-white text-indigo-700 font-black rounded-xl hover:bg-opacity-90 transform transition-all active:scale-95 mt-2 shadow-lg" type="submit">
                เข้าสู่ระบบ
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-white border-opacity-10 text-center">
            <p class="text-[9px] font-bold text-indigo-200 uppercase tracking-widest mb-3">บัญชีทดสอบ</p>
            <div class="flex justify-center space-x-1.5 text-[10px]">
                <span class="bg-white bg-opacity-10 px-2.5 py-1 rounded-lg">superadmin</span>
                <span class="bg-white bg-opacity-10 px-2.5 py-1 rounded-lg">admin</span>
                <span class="bg-white bg-opacity-10 px-2.5 py-1 rounded-lg">tech1</span>
            </div>
        </div>
    </div>

</body>
</html>