<?php
// views/components/user_manual.php

// ฟังก์ชันช่วยเหลือสำหรับตรวจสอบสิทธิ์แบบเซฟโหมด (หากยังไม่มีประกาศ)
if (!function_exists('hasRole')) {
    function hasRole($roles) {
        $userRoles = $_SESSION['roles'] ?? [$_SESSION['role'] ?? 'user'];
        if (!is_array($roles)) $roles = [$roles];
        if (in_array('super_admin', $userRoles)) return true;
        foreach ($roles as $role) {
            if (in_array($role, $userRoles)) return true;
        }
        return false;
    }
}

$isSuperAdmin = hasRole('super_admin');
$isAdmin      = hasRole('admin') || $isSuperAdmin;
$isTech       = hasRole('technician');
$isMa         = hasRole('ma_technician');
$isSales      = hasRole('sales');
$isIntern     = hasRole('intern');

// กำหนดธีมตามสิทธิ์สูงสุด (Visual Theme)
$manualThemeColor = 'from-indigo-600 to-indigo-500';
$manualTitle      = 'ผู้ใช้งานทั่วไป';
$manualIcon       = '📋';

if ($isSuperAdmin) {
    $manualThemeColor = 'from-violet-600 to-purple-500';
    $manualTitle = 'ผู้ดูแลระบบสูงสุด (Super Admin)';
    $manualIcon = '👑';
} elseif ($isAdmin) {
    $manualThemeColor = 'from-blue-700 to-blue-500';
    $manualTitle = 'ผู้ดูแลระบบ (Admin)';
    $manualIcon = '⚙️';
} elseif ($isTech || $isMa) {
    $manualThemeColor = 'from-emerald-700 to-emerald-500';
    $manualTitle = 'ทีมช่าง (Technician / MA)';
    $manualIcon = '🔧';
} elseif ($isSales) {
    $manualThemeColor = 'from-amber-600 to-amber-400';
    $manualTitle = 'ฝ่ายขาย (Sales)';
    $manualIcon = '📈';
} elseif ($isIntern) {
    $manualThemeColor = 'from-pink-600 to-rose-400';
    $manualTitle = 'นักศึกษาฝึกงาน (Intern)';
    $manualIcon = '🎓';
}
?>

<style>
    /* Styling สำหรับหน้าต่างคู่มือ */
    #guideModal { transition: opacity 0.3s ease; }
    #guideModalInner { transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
    #guideModal.show { opacity: 1; pointer-events: all; }
    #guideModal.show #guideModalInner { transform: scale(1) translateY(0); }
    
    .manual-scrollbar::-webkit-scrollbar { width: 6px; }
    .manual-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .manual-scrollbar::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 99px; }
    .manual-scrollbar::-webkit-scrollbar-thumb:hover { background: #94A3B8; }

    .toc-link {
        display: flex; align-items: center; gap: 8px;
        padding: 8px 12px; border-radius: 10px;
        font-size: 13px; font-weight: 600; color: #64748B;
        cursor: pointer; transition: all 0.2s ease;
        text-decoration: none; border: none; background: transparent; text-align: left;
    }
    .toc-link:hover { background: #F1F5F9; color: #1E293B; }
    .toc-link.active { background: #EEF2FF; color: #4F46E5; font-weight: 700; }
    .toc-link.active .toc-dot { background: #4F46E5; transform: scale(1.5); }
    .toc-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; flex-shrink: 0; opacity: 0.7; transition: transform 0.2s ease; }

    .manual-section { scroll-margin-top: 24px; }
    .manual-card {
        background: white; border: 1px solid #E2E8F0; border-radius: 16px;
        padding: 20px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-bottom: 20px;
    }
    .manual-card h4 { color: #1E293B; font-size: 16px; font-weight: 800; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
    .manual-card p, .manual-card li { color: #475569; font-size: 14px; line-height: 1.6; }
    .manual-card ul { list-style-type: disc; margin-left: 20px; margin-top: 8px; margin-bottom: 8px; }
    
    .alert-box {
        border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; display: flex; align-items: flex-start; gap: 12px; margin-top: 12px;
    }
    .alert-warning { background: #FFFBEB; border: 1px solid #FEF3C7; color: #92400E; }
    .alert-info { background: #EFF6FF; border: 1px solid #DBEAFE; color: #1E40AF; }
    .alert-success { background: #F0FDF4; border: 1px solid #DCFCE7; color: #166534; }
</style>

<div id="guideModal" class="fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-sm p-3 sm:p-6 flex items-center justify-center opacity-0 pointer-events-none">
    <div id="guideModalInner" class="w-full max-w-5xl bg-slate-50 rounded-[2rem] shadow-2xl overflow-hidden flex flex-col transform scale-95 translate-y-4" style="max-height: 92vh;">
        
        <!-- Header -->
        <div class="relative bg-gradient-to-br <?= $manualThemeColor ?> px-6 py-5 shrink-0 overflow-hidden shadow-sm">
            <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 85% 10%, rgba(255,255,255,0.6) 0%, transparent 50%);"></div>
            <div class="relative flex items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1.5">
                        <span class="bg-white/20 text-white px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest backdrop-blur-sm">คู่มือการใช้งานระบบ</span>
                    </div>
                    <h2 class="text-white text-2xl font-black leading-tight flex items-center gap-2">
                        <?= $manualIcon ?> <?= htmlspecialchars($manualTitle) ?>
                    </h2>
                    <p class="text-white/80 text-xs mt-1 font-medium">เนื้อหาแสดงเฉพาะส่วนที่เกี่ยวข้องกับสิทธิ์ของคุณเท่านั้น (Personalized Manual)</p>
                </div>
                <button id="closeGuideModal" class="shrink-0 w-9 h-9 rounded-full bg-white/10 hover:bg-white/25 text-white flex items-center justify-center transition-all text-xl font-bold border border-white/10">&times;</button>
            </div>
            
            <!-- Progress Bar -->
            <div class="absolute bottom-0 left-0 right-0 h-1 bg-black/10">
                <div id="guideProgressBar" class="h-full bg-white rounded-r-full shadow-[0_0_10px_rgba(255,255,255,0.5)] transition-all duration-150 ease-out" style="width: 0%;"></div>
            </div>
        </div>

        <!-- Body -->
        <div class="flex flex-1 overflow-hidden bg-slate-50 relative">
            
            <!-- Sidebar TOC (Desktop) -->
            <div class="hidden md:flex flex-col w-64 shrink-0 bg-white border-r border-slate-200 p-4 overflow-y-auto manual-scrollbar z-10 shadow-[4px_0_15px_-3px_rgba(0,0,0,0.03)]">
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-3 px-2">สารบัญ</p>
                <div id="manualTocContainer" class="flex flex-col gap-1">
                    <!-- สร้างอัตโนมัติด้วย JS -->
                </div>
            </div>

            <!-- Content Area -->
            <div id="manualContentArea" class="flex-1 overflow-y-auto p-4 md:p-8 manual-scrollbar scroll-smooth">
                <div class="max-w-3xl mx-auto space-y-8 pb-12">
                    
                    <!-- ส่วนที่ 1: ทั่วไป (ทุกคนเห็น) -->
                    <div class="manual-section" id="sec-general" data-title="การใช้งานเบื้องต้น">
                        <h3 class="text-2xl font-black text-slate-800 mb-5 flex items-center gap-2 border-b border-slate-200 pb-3">
                            <span class="bg-indigo-100 text-indigo-600 p-2 rounded-xl"><i data-lucide="power" class="w-5 h-5"></i></span> 
                            การใช้งานเบื้องต้น
                        </h3>
                        
                        <div class="manual-card">
                            <h4><i data-lucide="log-in" class="w-4 h-4 text-sky-500"></i> การเข้าสู่ระบบและการใช้งานเมนู</h4>
                            <p>เมื่อท่านเข้าสู่ระบบสำเร็จ ท่านจะพบกับหน้าแดชบอร์ดหรือหน้าแรกที่กำหนดไว้ตามสิทธิ์การใช้งานของท่าน</p>
                            <ul>
                                <li><strong>สำหรับคอมพิวเตอร์ (PC):</strong> เมนูหลักจะอยู่ทางด้านซ้ายมือ (Sidebar) สามารถคลิกเพื่อเข้าถึงฟีเจอร์ต่างๆ</li>
                                <li><strong>สำหรับมือถือ (Mobile):</strong> เมนูจะแบ่งเป็นสองส่วนคือ <strong>แถบเมนูด้านล่าง (Bottom Nav)</strong> สำหรับฟีเจอร์ที่ใช้บ่อย และ <strong>เมนูด้านข้าง (Drawer)</strong> ที่กดปุ่มขีดสามขีดมุมซ้ายบนเพื่อเปิดดูเมนูทั้งหมด</li>
                            </ul>
                        </div>
                        
                        <div class="manual-card">
                            <h4><i data-lucide="camera" class="w-4 h-4 text-emerald-500"></i> การเช็คอินเข้างานทั่วไป</h4>
                            <p>พนักงานทุกท่านต้องทำการเช็คอินทุกเช้าเพื่อบันทึกเวลาเข้างาน</p>
                            <ul class="list-decimal">
                                <li>ไปที่เมนู <strong>"เช็คอิน (Check-in)"</strong></li>
                                <li>ในมือถือให้กดที่กรอบสี่เหลี่ยม หรือปุ่มถ่ายรูป ระบบจะเปิดกล้องของมือถือขึ้นมา</li>
                                <li>ถ่ายรูปหน้างานหรือสถานที่ที่ท่านอยู่</li>
                                <li>กดปุ่ม <strong>"ยืนยันการเช็คอิน"</strong></li>
                            </ul>
                            <div class="alert-box alert-warning">
                                <i data-lucide="alert-triangle" class="w-5 h-5 shrink-0 mt-0.5"></i>
                                <div><strong>หมายเหตุ:</strong> ระบบจะบันทึกว่าท่านมาสายหรือไม่ตามเวลาที่กำหนด หากระบบแจ้งเตือนมาสาย โปรดตรวจสอบเวลาเข้างานของบริษัท</div>
                            </div>
                        </div>
                    </div>

                    <!-- ส่วนที่ 2: Admin / Super Admin -->
                    <?php if ($isAdmin): ?>
                    <div class="manual-section" id="sec-admin" data-title="สำหรับผู้ดูแลระบบ (Admin)">
                        <h3 class="text-2xl font-black text-slate-800 mb-5 flex items-center gap-2 border-b border-slate-200 pb-3 mt-8">
                            <span class="bg-blue-100 text-blue-600 p-2 rounded-xl"><i data-lucide="settings" class="w-5 h-5"></i></span> 
                            การจัดการระบบสำหรับ Admin
                        </h3>
                        
                        <div class="manual-card">
                            <h4><i data-lucide="users" class="w-4 h-4 text-indigo-500"></i> การจัดการผู้ใช้งาน (Users)</h4>
                            <p>ผู้ดูแลระบบสามารถเพิ่ม แก้ไข สิทธิ์การใช้งาน และลบพนักงานออกจากระบบได้</p>
                            <ul>
                                <li>ไปที่เมนู <strong>พนักงาน (Users)</strong></li>
                                <li>คลิกที่ปุ่มเพิ่มพนักงานใหม่ เพื่อสร้างบัญชี</li>
                                <li>การกำหนด <strong>สิทธิ์การใช้งาน (Role)</strong> จะเป็นตัวกำหนดว่าพนักงานคนนั้นสามารถเห็นเมนูอะไรได้บ้าง</li>
                            </ul>
                        </div>

                        <div class="manual-card">
                            <h4><i data-lucide="box" class="w-4 h-4 text-amber-500"></i> ระบบคลังสินค้าและการเบิกจ่าย (Inventory)</h4>
                            <p>ควบคุมสต๊อกสินค้าคงคลัง และอนุมัติการเบิกจ่าย</p>
                            <ul class="list-decimal">
                                <li><strong>จัดการคลังสินค้า:</strong> สามารถดูรายการสินค้าคงเหลือ ยอดรับเข้าและยอดจ่ายออก</li>
                                <li><strong>เพิ่มสินค้า:</strong> ใช้สำหรับการนำเข้าสินค้าใหม่เข้าระบบ</li>
                                <li><strong>อนุมัติการเบิก (ถ้ามี):</strong> ตรวจสอบรายการที่ช่างทำการเบิก (Tech Bag)</li>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ส่วนที่ 3: Technician -->
                    <?php if ($isTech || $isAdmin): ?>
                    <div class="manual-section" id="sec-tech" data-title="งานช่างซ่อม (Technician)">
                        <h3 class="text-2xl font-black text-slate-800 mb-5 flex items-center gap-2 border-b border-slate-200 pb-3 mt-8">
                            <span class="bg-emerald-100 text-emerald-600 p-2 rounded-xl"><i data-lucide="tool" class="w-5 h-5"></i></span> 
                            การปฏิบัติงานสำหรับช่าง (Technician)
                        </h3>
                        
                        <div class="manual-card">
                            <h4><i data-lucide="clipboard-list" class="w-4 h-4 text-sky-500"></i> การดูและจัดการใบงาน (Jobs)</h4>
                            <p>ช่างเทคนิคจะเห็นรายการงานซ่อมที่ได้รับมอบหมายในเมนู <strong>Jobs (ใบงาน)</strong></p>
                            <ul>
                                <li>คลิกที่รายการงานเพื่อดูรายละเอียดลูกค้า อาการเสีย และสถานที่</li>
                                <li>เมื่อปฏิบัติงานเสร็จสิ้น ต้องแนบ <strong>ภาพถ่ายก่อนซ่อม/หลังซ่อม</strong> และกรอกรายละเอียดการแก้ไข</li>
                                <li>กดปุ่มปิดงาน (Complete) เพื่อส่งข้อมูลเข้าระบบ</li>
                            </ul>
                        </div>

                        <div class="manual-card">
                            <h4><i data-lucide="briefcase" class="w-4 h-4 text-amber-600"></i> กระเป๋าช่าง (Tech Bag)</h4>
                            <p>ระบบจัดการอุปกรณ์ที่ช่างเบิกออกไปใช้งานประจำตัว</p>
                            <ul>
                                <li>ไปที่เมนู <strong>กระเป๋าช่าง (Tech Bag)</strong> เพื่อดูอุปกรณ์ที่ท่านถือครองอยู่</li>
                                <li>สามารถทำรายการเบิกอุปกรณ์เพิ่มเติม หรือโอนย้ายคืนคลังได้จากหน้านี้</li>
                                <li>เมื่อมีการใช้อุปกรณ์ในงานซ่อม ระบบอาจทำการตัดสต๊อกอัตโนมัติ (ขึ้นอยู่กับการตั้งค่า)</li>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ส่วนที่ 4: MA Technician -->
                    <?php if ($isMa || $isAdmin): ?>
                    <div class="manual-section" id="sec-ma" data-title="งานช่าง MA (MA Tech)">
                        <h3 class="text-2xl font-black text-slate-800 mb-5 flex items-center gap-2 border-b border-slate-200 pb-3 mt-8">
                            <span class="bg-violet-100 text-violet-600 p-2 rounded-xl"><i data-lucide="wrench" class="w-5 h-5"></i></span> 
                            การปฏิบัติงานสำหรับช่าง MA
                        </h3>
                        
                        <div class="manual-card">
                            <h4><i data-lucide="camera" class="w-4 h-4 text-violet-500"></i> การเช็คอิน MA</h4>
                            <p>ช่าง MA จะมีระบบเช็คอินแยกเฉพาะสำหรับการเข้าบำรุงรักษา</p>
                            <ul>
                                <li>ในหน้า <strong>เช็คอิน</strong> จะมีแท็บ <strong>"เช็คอิน MA"</strong> (สีม่วง)</li>
                                <li>ให้กดที่แท็บนี้ และถ่ายรูปหน้างานเพื่อยืนยันเวลาการเข้าบำรุงรักษาตามปกติ</li>
                            </ul>
                            <div class="alert-box alert-info">
                                <i data-lucide="info" class="w-5 h-5 shrink-0 mt-0.5"></i>
                                <div>ในมือถือ หากปุ่มกดไม่ติด ให้ลองรีเฟรชหน้าเว็บ 1 ครั้งเพื่อให้เบราว์เซอร์ล้างข้อมูลเก่า</div>
                            </div>
                        </div>

                        <div class="manual-card">
                            <h4><i data-lucide="calendar-check" class="w-4 h-4 text-indigo-500"></i> การดูตารางงาน MA (MA Jobs)</h4>
                            <p>สามารถดูรายการบำรุงรักษาประจำเดือนได้ที่เมนู <strong>MA Jobs</strong></p>
                            <ul>
                                <li>ระบบจะแสดงรายการเครื่องจักรหรือลูกค้าที่ถึงกำหนดบำรุงรักษา</li>
                                <li>สามารถระบุสถานะว่า เข้าทำแล้ว, เลื่อน, หรือยกเลิก ได้จากหน้านี้</li>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ส่วนที่ 5: Intern -->
                    <?php if ($isIntern || $isAdmin): ?>
                    <div class="manual-section" id="sec-intern" data-title="เด็กฝึกงาน (Intern)">
                        <h3 class="text-2xl font-black text-slate-800 mb-5 flex items-center gap-2 border-b border-slate-200 pb-3 mt-8">
                            <span class="bg-pink-100 text-pink-600 p-2 rounded-xl"><i data-lucide="graduation-cap" class="w-5 h-5"></i></span> 
                            สำหรับเด็กฝึกงาน (Intern)
                        </h3>
                        
                        <div class="manual-card">
                            <h4><i data-lucide="file-text" class="w-4 h-4 text-pink-500"></i> การบันทึกรายงานการทำงาน (Work Records)</h4>
                            <p>เด็กฝึกงานจะต้องส่งรายงานสรุปการทำงานประจำวัน</p>
                            <ul>
                                <li>ไปที่เมนู <strong>บันทึกการทำงาน</strong></li>
                                <li>กดปุ่ม <strong>"เพิ่มบันทึกใหม่"</strong> และเลือกวันที่ทำการ</li>
                                <li>กรอกหัวข้อและรายละเอียดงานที่ได้ปฏิบัติในวันนั้นๆ อย่างละเอียด</li>
                                <li>ข้อมูลเหล่านี้จะถูกส่งให้หัวหน้าหรือแอดมินตรวจประเมินผลต่อไป</li>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ส่วนที่ 6: FAQ -->
                    <div class="manual-section" id="sec-faq" data-title="ปัญหาที่พบบ่อย (FAQ)">
                        <h3 class="text-2xl font-black text-slate-800 mb-5 flex items-center gap-2 border-b border-slate-200 pb-3 mt-8">
                            <span class="bg-rose-100 text-rose-600 p-2 rounded-xl"><i data-lucide="help-circle" class="w-5 h-5"></i></span> 
                            ปัญหาที่พบบ่อย (FAQ)
                        </h3>
                        
                        <div class="manual-card">
                            <h4><i data-lucide="alert-circle" class="w-4 h-4 text-rose-500"></i> 1. กดปุ่มเช็คอินแล้วกล้องไม่ทำงาน (ในมือถือ)</h4>
                            <p><strong>สาเหตุ:</strong> เบราว์เซอร์อาจไม่ได้รับอนุญาตให้เข้าถึงกล้องถ่ายรูป หรือเครื่องจำข้อมูลเก่า (Cache)</p>
                            <p><strong>วิธีแก้:</strong></p>
                            <ul class="list-decimal">
                                <li>ตรวจสอบว่าตอนที่เด้งปุ่มขออนุญาตเข้าถึงกล้อง (Permission) ได้กด <strong>"อนุญาต (Allow)"</strong> หรือไม่</li>
                                <li>สำหรับ iOS (Safari) ให้ไปที่ Settings > Safari > Camera แล้วตั้งค่าเป็น Allow</li>
                                <li><strong>ลองรีเฟรชหน้าเว็บ (ปัดจอลง)</strong> 1-2 ครั้งเพื่อให้หน้าเว็บโหลดใหม่</li>
                            </ul>
                        </div>

                        <div class="manual-card">
                            <h4><i data-lucide="map-pin-off" class="w-4 h-4 text-rose-500"></i> 2. ไม่สามารถบันทึกพิกัด GPS ตอนเริ่มวันได้</h4>
                            <p><strong>วิธีแก้:</strong> ตรวจสอบว่าเปิดระบบ Location Service (GPS) บนมือถือแล้ว และอนุญาตให้เบราว์เซอร์เข้าถึงตำแหน่งของท่าน</p>
                        </div>

                        <div class="manual-card">
                            <h4><i data-lucide="image-off" class="w-4 h-4 text-rose-500"></i> 3. อัปโหลดภาพตอนปิดงานซ่อมไม่ได้ ระบบแจ้ง Error</h4>
                            <p><strong>สาเหตุ:</strong> ไฟล์รูปภาพอาจมีขนาดใหญ่เกินไป หรือนามสกุลไฟล์ไม่รองรับ</p>
                            <p><strong>วิธีแก้:</strong> ระบบรองรับไฟล์รูปภาพ (JPG, PNG) เป็นหลัก แนะนำให้ถ่ายรูปผ่านระบบโดยตรงแทนการอัปโหลดไฟล์ขนาดใหญ่จากอัลบั้ม</p>
                        </div>
                        
                        <div class="mt-8 text-center bg-slate-100/50 rounded-3xl p-8 border border-slate-200 shadow-inner">
                            <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm border border-slate-100">
                                <i data-lucide="life-buoy" class="w-8 h-8 text-indigo-400"></i>
                            </div>
                            <h5 class="text-xl font-black text-slate-800">ต้องการความช่วยเหลือเพิ่มเติม?</h5>
                            <p class="text-sm text-slate-500 mb-6 mt-2 max-w-sm mx-auto">หากพบปัญหาอื่นๆ นอกเหนือจากนี้ สามารถรายงานปัญหาให้ทีมดูแลระบบ (Support) ทราบได้ทันที</p>
                            <button onclick="document.getElementById('closeGuideModal').click(); if(document.getElementById('openIssueReportBtnMobile')){ document.getElementById('openIssueReportBtnMobile').click(); } else if(document.getElementById('openIssueReportBtn')){ document.getElementById('openIssueReportBtn').click(); }" class="bg-slate-800 hover:bg-slate-900 text-white font-bold py-3 px-8 rounded-2xl transition-all shadow-lg hover:shadow-xl hover:-translate-y-1 flex items-center gap-2 mx-auto">
                                <i data-lucide="message-square" class="w-4 h-4"></i> แจ้งปัญหาระบบ
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // 1. ตัวแปร DOM
    const guideBtn = document.getElementById('guideModalBtn');
    const guideModal = document.getElementById('guideModal');
    const closeBtn = document.getElementById('closeGuideModal');
    const tocContainer = document.getElementById('manualTocContainer');
    const contentArea = document.getElementById('manualContentArea');
    const progressBar = document.getElementById('guideProgressBar');
    const sections = document.querySelectorAll('.manual-section');

    if (!guideModal || !guideBtn) return;

    // 2. สร้างสารบัญอัตโนมัติ
    if (tocContainer && sections.length > 0) {
        tocContainer.innerHTML = '';
        sections.forEach((sec, index) => {
            const title = sec.getAttribute('data-title') || `ส่วนที่ ${index+1}`;
            const btn = document.createElement('button');
            btn.className = 'toc-link w-full';
            if (index === 0) btn.classList.add('active');
            
            btn.innerHTML = `<span class="toc-dot"></span> <span class="truncate">${title}</span>`;
            
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                // Scroll ไปยัง section นั้น
                contentArea.scrollTo({
                    top: sec.offsetTop - contentArea.offsetTop - 10,
                    behavior: 'smooth'
                });
                
                // อัปเดต Active
                document.querySelectorAll('.toc-link').forEach(l => l.classList.remove('active'));
                btn.classList.add('active');
            });
            tocContainer.appendChild(btn);
        });
    }

    // 3. จัดการเปิด/ปิด Modal
    guideBtn.addEventListener('click', () => {
        guideModal.classList.add('show');
        // Re-initialize lucide icons for elements that just became visible
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
        // รีเซ็ต Scroll
        setTimeout(() => {
            if (contentArea) contentArea.scrollTop = 0;
            updateScrollSpy();
        }, 300);
    });

    const closeModal = () => {
        guideModal.classList.remove('show');
    };

    closeBtn.addEventListener('click', closeModal);
    
    // คลิกพื้นหลังเพื่อปิด
    guideModal.addEventListener('click', (e) => {
        if (e.target === guideModal) closeModal();
    });

    // 4. Scroll Spy & Progress Bar
    const updateScrollSpy = () => {
        if (!contentArea) return;
        
        // คำนวณ Progress Bar
        const scrollTop = contentArea.scrollTop;
        const scrollHeight = contentArea.scrollHeight - contentArea.clientHeight;
        const progress = scrollHeight > 0 ? (scrollTop / scrollHeight) * 100 : 0;
        if (progressBar) progressBar.style.width = `${Math.min(100, Math.max(0, progress))}%`;

        // คำนวณ Scroll Spy
        let currentSecId = '';
        const offset = 50; // ชดเชยความสูง
        
        sections.forEach(sec => {
            const secTop = sec.offsetTop - contentArea.offsetTop - offset;
            if (scrollTop >= secTop) {
                currentSecId = sec.id;
            }
        });

        // อัปเดตเมนูซ้าย
        if (currentSecId && tocContainer) {
            const links = tocContainer.querySelectorAll('.toc-link');
            links.forEach((link, idx) => {
                if (sections[idx].id === currentSecId) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        }
    };

    if (contentArea) {
        contentArea.addEventListener('scroll', () => {
            // ใช้ requestAnimationFrame เพื่อลดอาการกระตุกเวลา Scroll เร็วๆ
            window.requestAnimationFrame(updateScrollSpy);
        });
    }
});
</script>
