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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>เข้าสู่ระบบ - Bonus. Smart Business Suite</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --c-bg:            #ECEEF5;
            --c-surface:       #FFFFFF;
            --c-surface-2:     #F7F8FC;
            --c-primary:       #6C5CE7;
            --c-primary-hover: #5A4BD1;
            --c-primary-faint: #EDE9FF;
            --c-text-1:        #0D0D1A;
            --c-text-2:        #4B4F6A;
            --c-text-3:        #9499B5;
            --c-text-inv:      #FFFFFF;
            --c-border:        #E2E5F0;
            --c-border-focus:  #6C5CE7;
            
            --shadow-1: 0 1px 2px rgba(10,10,30, 0.04), 0 2px 8px rgba(10,10,30, 0.06);
            --shadow-4: 0 8px 16px rgba(10,10,30, 0.06), 0 32px 64px rgba(10,10,30, 0.16), 0 0 0 1px rgba(10,10,30, 0.05);
            --shadow-btn: 0 4px 14px rgba(108,92,231, 0.40);
        }
        body { font-family: 'Inter', 'Sarabun', sans-serif; background-color: var(--c-bg); color: var(--c-text-2); min-height: 100vh; }
        .auth-card { background: var(--c-surface); border-radius: 24px; padding: 40px; box-shadow: var(--shadow-4); border: 1px solid var(--c-border); width: 100%; max-width: 420px; }
        .input { background: var(--c-surface-2); border: 1.5px solid var(--c-border); border-radius: 12px; padding: 12px 16px; font-size: 15px; color: var(--c-text-1); transition: all 0.2s ease; width: 100%; }
        .input:focus { border-color: var(--c-border-focus); box-shadow: 0 0 0 3px rgba(108,92,231, 0.22); outline: none; background: var(--c-surface); }
        .btn-primary { background: var(--c-primary); color: var(--c-text-inv); border-radius: 12px; padding: 14px 20px; font-weight: 700; font-size: 15px; text-align: center; width: 100%; transition: all 0.2s ease; box-shadow: var(--shadow-btn); cursor: pointer; }
        .btn-primary:hover { background: var(--c-primary-hover); transform: translateY(-1px); }
        .tab-btn { font-weight: 700; font-size: 14px; padding-bottom: 12px; transition: all 0.2s ease; border-bottom: 2px solid transparent; color: var(--c-text-3); flex: 1; text-align: center; cursor: pointer; }
        .tab-btn.active { color: var(--c-primary); border-bottom-color: var(--c-primary); }
    </style>
</head>
<body class="flex items-center justify-center p-4 sm:p-6">

    <div class="auth-card">
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-[var(--c-primary)] rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-btn text-white font-black text-3xl">B</div>
            <h1 class="text-2xl font-black text-[var(--c-text-1)] tracking-tight">Bonus<span class="text-[var(--c-primary)]">.</span></h1>
            <p class="text-sm font-medium text-[var(--c-text-3)] mt-1">Smart Business Suite</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-[#FEF2F2] border border-[#F87171] text-[#991B1B] p-3 rounded-xl mb-6 text-center text-xs font-bold">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-[#ECFDF5] border border-[#34D399] text-[#065F46] p-3 rounded-xl mb-6 text-center text-xs font-bold">✅ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="flex mb-8 border-b border-[var(--c-border)]">
            <button onclick="showTab('login')" id="tabLogin" class="tab-btn active">เข้าสู่ระบบ</button>
            <button onclick="showTab('register')" id="tabRegister" class="tab-btn">ลงทะเบียนใหม่</button>
        </div>

        <form id="formLogin" method="POST" action="" class="space-y-5">
            <input type="hidden" name="action" value="login">
            <div>
                <label class="block text-xs font-bold mb-2 text-[var(--c-text-2)] uppercase tracking-wider">ชื่อผู้ใช้ (Username)</label>
                <input class="input" type="text" name="username" placeholder="กรอกชื่อผู้ใช้งาน..." required>
            </div>
            <div>
                <label class="block text-xs font-bold mb-2 text-[var(--c-text-2)] uppercase tracking-wider">รหัสผ่าน (Password)</label>   
                <input class="input" type="password" name="password" placeholder="••••••••" required>
            </div>
            <div class="pt-2">
                <button class="btn-primary" type="submit">ลงชื่อเข้าใช้งาน <i data-lucide="arrow-right" class="w-4 h-4 inline-block ml-1"></i></button>
            </div>
        </form>

        <form id="formRegister" method="POST" action="" class="space-y-5 hidden">
            <input type="hidden" name="action" value="register">
            <div>
                <label class="block text-xs font-bold mb-2 text-[var(--c-text-2)] uppercase tracking-wider">ชื่อ-นามสกุลจริง</label>
                <input class="input" type="text" name="reg_fullname" required>
            </div>
            <div>
                <label class="block text-xs font-bold mb-2 text-[var(--c-text-2)] uppercase tracking-wider">ชื่อผู้ใช้ (สำหรับ Login)</label>
                <input class="input" type="text" name="reg_username" required>
            </div>
            <div>
                <label class="block text-xs font-bold mb-2 text-[var(--c-text-2)] uppercase tracking-wider">รหัสผ่าน</label>
                <input class="input" type="password" name="reg_password" required>
            </div>
            <div>
                <label class="block text-xs font-bold mb-2 text-[#D97706] uppercase tracking-wider">* ทะเบียนรถที่ใช้งาน (ผูกทีมงาน)</label>
                <input class="input !border-[#FCD34D]" type="text" name="reg_license" placeholder="เช่น กท 1234 สุราษฎร์ธานี" required>
                <p class="text-[9px] text-[var(--c-text-3)] mt-1">ทะเบียนรถเดียวกัน จะถูกจัดให้อยู่ทีมเดียวกันอัตโนมัติ</p>
            </div>
            <div class="pt-2">
                <button class="btn-primary" type="submit">ส่งคำขอลงทะเบียน</button>
            </div>
        </form>
    </div>

    <script>
        lucide.createIcons();
        function showTab(tab) {
            const fLogin = document.getElementById('formLogin');
            const fReg = document.getElementById('formRegister');
            const tLogin = document.getElementById('tabLogin');
            const tReg = document.getElementById('tabRegister');

            if (tab === 'login') {
                fLogin.classList.remove('hidden');
                fReg.classList.add('hidden');
                tLogin.classList.add('active');
                tReg.classList.remove('active');
            } else {
                fLogin.classList.add('hidden');
                fReg.classList.remove('hidden');
                tLogin.classList.remove('active');
                tReg.classList.add('active');
            }
        }
    </script>
</body>
</html>