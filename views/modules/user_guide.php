<?php
// views/modules/user_guide.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');

$role = $user['role'] ?? 'user';
$guideFiles = [
    'super_admin' => 'USER_GUIDE_SUPER_ADMIN.md',
    'admin' => 'USER_GUIDE_ADMIN.md',
    'technician' => 'USER_GUIDE_TECHNICIAN.md',
    'sales' => 'USER_GUIDE.md',
    'user' => 'USER_GUIDE.md'
];
$guideFile = $guideFiles[$role] ?? 'USER_GUIDE.md';
$guideLabel = [
    'super_admin' => 'คู่มือสำหรับผู้ดูแลระบบสูงสุด',
    'admin' => 'คู่มือสำหรับผู้ดูแลระบบ',
    'technician' => 'คู่มือสำหรับช่างเทคนิค',
    'sales' => 'คู่มือการใช้งาน',
    'user' => 'คู่มือการใช้งาน'
][$role] ?? 'คู่มือการใช้งาน';
?>

<div class="space-y-6 pb-20 lg:pb-0">
    <div class="card flex flex-col md:flex-row md:items-center md:justify-between gap-5">
        <div>
            <h2 class="text-3xl font-black text-[var(--c-text-1)] tracking-tight flex items-center gap-3">
                <span class="p-3 rounded-2xl bg-sky-100 text-sky-700"><i data-lucide="book-open" class="w-6 h-6"></i></span>
                <?= htmlspecialchars($guideLabel) ?>
            </h2>
            <p class="text-[var(--c-text-3)] text-sm mt-2">เปิดคู่มือของคุณเพื่อดูคำแนะนำตามบทบาทและฟังก์ชันที่เกี่ยวข้อง</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-3">
            <a href="<?= htmlspecialchars($guideFile) ?>" target="_blank" class="btn-primary flex items-center justify-center bg-sky-600 hover:bg-sky-700 text-white">
                <i data-lucide="download" class="w-4 h-4 mr-2"></i> เปิดคู่มือบทบาทนี้
            </a>
            <a href="index.php?page=home" class="btn-primary bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-300">
                <i data-lucide="home" class="w-4 h-4 mr-2"></i> กลับสู่หน้าแรก
            </a>
        </div>
    </div>

    <div class="card p-6 rounded-3xl bg-slate-50 border border-slate-200">
        <h3 class="text-xl font-black text-[var(--c-text-1)] mb-4">คำแนะนำด่วน</h3>
        <ul class="space-y-3 text-sm text-slate-600">
            <li>ใช้คู่มือบทบาทของคุณเพื่อค้นหาเมนูและฟังก์ชันที่เกี่ยวข้องได้เร็วขึ้น</li>
            <li>หากคุณเป็น `super_admin` ให้ใช้เมนู `ตั้งค่าระบบเว็บไซต์` เพื่อจัดการประกาศหน้าเว็บ</li>
            <li>หากคุณเป็น `admin` ให้ดูเรื่องการจัดการงานและระบบคลังสินค้า</li>
            <li>หากคุณเป็น `technician` ให้ดูวิธีบันทึกเช็คอินและข้อมูลน้ำมัน</li>
        </ul>
    </div>
</div>
