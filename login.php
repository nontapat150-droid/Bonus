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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username && $password) {
            // ดึงข้อมูล status มาตรวจสอบด้วย
            $stmt = $pdo->prepare("SELECT id, username, password_hash, role, full_name, status FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] === 'pending') {
                    $error = 'บัญชีของคุณอยู่ระหว่างรอผู้ดูแลระบบอนุมัติ';
                } elseif ($user['status'] === 'rejected') {
                    $error = 'บัญชีของคุณไม่อนุมัติให้ใช้งานระบบ';
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['full_name'] = $user['full_name'];
                    header("Location: index.php");
                    exit;
                }
            } else {
                $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
            }
        } else {
            $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        }
    } elseif ($action === 'register') {
        $reg_username = trim($_POST['reg_username'] ?? '');
        $reg_password = $_POST['reg_password'] ?? '';
        $reg_fullname = trim($_POST['reg_fullname'] ?? '');
        $reg_license = trim($_POST['reg_license'] ?? '');

        if ($reg_username && $reg_password && $reg_fullname && $reg_license) {
            // เช็คว่ามี username นี้หรือยัง
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$reg_username]);
            if ($stmt->fetch()) {
                $error = 'ชื่อผู้ใช้นี้มีในระบบแล้ว กรุณาใช้ชื่ออื่น';
            } else {
                try {
                    $pdo->beginTransaction();

                    // 1. จัดการทีม (ใช้ป้ายทะเบียนเป็นชื่อทีมเพื่อรวมกลุ่ม)
                    $stmt = $pdo->prepare("SELECT id FROM teams WHERE team_name = ?");
                    $stmt->execute([$reg_license]);
                    $team = $stmt->fetch();
                    
                    if ($team) {
                        $team_id = $team['id'];
                    } else {
                        // ถ้ายังไม่มีทีมนี้ ให้สร้างใหม่
                        $stmt = $pdo->prepare("INSERT INTO teams (team_name) VALUES (?)");
                        $stmt->execute([$reg_license]);
                        $team_id = $pdo->lastInsertId();
                    }

                    // 2. จัดการยานพาหนะ (เพิ่มรูปรถเข้าระบบถ้ายังไม่มี)
                    $stmt = $pdo->prepare("INSERT IGNORE INTO vehicles (license_plate) VALUES (?)");
                    $stmt->execute([$reg_license]);

                    // 3. สร้าง User และผูกเข้ากับทีมนี้ โดยตั้งสถานะเป็น pending
                    $hash = password_hash($reg_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, full_name, status, team_id) VALUES (?, ?, 'technician', ?, 'pending', ?)");
                    $stmt->execute([$reg_username, $hash, $reg_fullname, $team_id]);

                    $pdo->commit();
                    $success = 'ลงทะเบียนสำเร็จ! กรุณารอผู้ดูแลระบบอนุมัติการเข้าใช้งาน';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'เกิดข้อผิดพลาดในการลงทะเบียน: ' . $e->getMessage();
                }
            }
        } else {
            $error = 'กรุณากรอกข้อมูลลงทะเบียนให้ครบถ้วน';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ระบบจัดการงาน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .glass { background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(15px); border: 1px solid rgba(255, 255, 255, 0.2); box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.3); }
        .tab-active { border-bottom: 2px solid white; color: white; font-weight: 800; }
        .tab-inactive { color: rgba(255,255,255,0.6); }
    </style>
</head>
<body class="flex items-center justify-center p-6">

    <div class="glass w-full max-w-md p-8 rounded-[2rem] text-white">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-white bg-opacity-20 rounded-2xl flex items-center justify-center mx-auto mb-4"><span class="text-3xl">🚀</span></div>
            <h1 class="text-2xl font-black mb-1">ระบบจัดการงาน</h1>
        </div>

        <?php if ($error): ?>
            <div class="bg-rose-500/40 border border-rose-400 text-white p-3 rounded-xl mb-4 text-center text-xs font-bold">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-emerald-500/40 border border-emerald-400 text-white p-3 rounded-xl mb-4 text-center text-xs font-bold">✅ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="flex mb-6 border-b border-white/20">
            <button onclick="showTab('login')" id="tabLogin" class="flex-1 pb-2 tab-active transition-all">เข้าสู่ระบบ</button>
            <button onclick="showTab('register')" id="tabRegister" class="flex-1 pb-2 tab-inactive transition-all">ลงทะเบียนใหม่</button>
        </div>

        <form id="formLogin" method="POST" action="" class="space-y-4">
            <input type="hidden" name="action" value="login">
            <div>
                <label class="block text-[10px] font-bold mb-1 uppercase tracking-widest text-indigo-100">ชื่อผู้ใช้</label>
                <input class="w-full px-4 py-2.5 rounded-xl bg-white/10 border border-white/20 focus:outline-none focus:ring-1 focus:ring-white text-sm" type="text" name="username" required>
            </div>
            <div>
                <label class="block text-[10px] font-bold mb-1 uppercase tracking-widest text-indigo-100">รหัสผ่าน</label>   
                <input class="w-full px-4 py-2.5 rounded-xl bg-white/10 border border-white/20 focus:outline-none focus:ring-1 focus:ring-white text-sm" type="password" name="password" required>
            </div>
            <button class="w-full py-3 bg-white text-indigo-700 font-black rounded-xl hover:bg-opacity-90 mt-2 shadow-lg" type="submit">เข้าสู่ระบบ</button>
        </form>

        <form id="formRegister" method="POST" action="" class="space-y-4 hidden">
            <input type="hidden" name="action" value="register">
            <div>
                <label class="block text-[10px] font-bold mb-1 uppercase text-indigo-100">ชื่อ-นามสกุลจริง</label>
                <input class="w-full px-4 py-2.5 rounded-xl bg-white/10 border border-white/20 focus:outline-none text-sm" type="text" name="reg_fullname" required>
            </div>
            <div>
                <label class="block text-[10px] font-bold mb-1 uppercase text-indigo-100">ชื่อผู้ใช้ (สำหรับ Login)</label>
                <input class="w-full px-4 py-2.5 rounded-xl bg-white/10 border border-white/20 focus:outline-none text-sm" type="text" name="reg_username" required>
            </div>
            <div>
                <label class="block text-[10px] font-bold mb-1 uppercase text-indigo-100">รหัสผ่าน</label>
                <input class="w-full px-4 py-2.5 rounded-xl bg-white/10 border border-white/20 focus:outline-none text-sm" type="password" name="reg_password" required>
            </div>
            <div>
                <label class="block text-[10px] font-bold mb-1 uppercase text-amber-300">* ทะเบียนรถที่ใช้งาน (ผูกทีมงาน)</label>
                <input class="w-full px-4 py-2.5 rounded-xl bg-white/10 border border-amber-300/50 focus:border-amber-300 focus:outline-none text-sm placeholder-white/30" type="text" name="reg_license" placeholder="เช่น กท 1234 สุราษฎร์ธานี" required>
                <p class="text-[9px] text-amber-200 mt-1">ทะเบียนรถเดียวกัน จะถูกจัดให้อยู่ทีมเดียวกันอัตโนมัติ</p>
            </div>
            <button class="w-full py-3 bg-indigo-500 text-white font-black rounded-xl hover:bg-indigo-600 mt-2 shadow-lg" type="submit">ส่งคำขอลงทะเบียน</button>
        </form>

    </div>

    <script>
        function showTab(tab) {
            document.getElementById('formLogin').classList.add('hidden');
            document.getElementById('formRegister').classList.add('hidden');
            document.getElementById('tabLogin').className = 'flex-1 pb-2 tab-inactive transition-all';
            document.getElementById('tabRegister').className = 'flex-1 pb-2 tab-inactive transition-all';
            
            if (tab === 'login') {
                document.getElementById('formLogin').classList.remove('hidden');
                document.getElementById('tabLogin').className = 'flex-1 pb-2 tab-active transition-all';
            } else {
                document.getElementById('formRegister').classList.remove('hidden');
                document.getElementById('tabRegister').className = 'flex-1 pb-2 tab-active transition-all';
            }
        }
    </script>
</body>
</html>