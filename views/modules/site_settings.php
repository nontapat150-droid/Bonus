<?php
// views/modules/site_settings.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');

if (!hasRole('super_admin')) {
    echo "<div class='p-12 text-center'><h2 class='text-2xl font-bold text-rose-500'>ไม่มีสิทธิ์เข้าถึงหน้านี้</h2><p class='text-slate-500 mt-2'>เฉพาะผู้ดูแลระบบสูงสุดเท่านั้นที่สามารถตั้งค่าระบบเว็บไซต์ได้</p></div>";
    exit;
}
?>

<div class="space-y-6 pb-20 lg:pb-0">
    <div class="card flex flex-col md:flex-row md:items-center md:justify-between gap-5">
        <div>
            <h2 class="text-3xl font-black text-[var(--c-text-1)] tracking-tight flex items-center gap-3">
                <span class="p-3 rounded-2xl bg-indigo-100 text-indigo-700"><i data-lucide="monitor" class="w-6 h-6"></i></span>
                ตั้งค่าระบบเว็บไซต์
            </h2>
            <p class="text-[var(--c-text-3)] text-sm mt-2">จัดการประกาศหน้าเว็บ (ป๊อปอัปและป้ายวิ่ง) ได้จากที่เดียว</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-3">
            <button id="refreshAnnouncementBtn" class="btn-primary bg-slate-600 hover:bg-slate-700 text-white">รีเฟรชข้อมูลปัจจุบัน</button>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <!-- 1. ป๊อปอัปประกาศ (Popup Announcement) -->
        <section class="card p-6 rounded-3xl bg-slate-50 border border-slate-200 space-y-6">
            <div>
                <h3 class="text-xl font-black text-[var(--c-text-1)] flex items-center gap-2"><i data-lucide="image" class="w-5 h-5 text-indigo-600"></i> ป๊อปอัปประกาศกลางจอ</h3>
                <p class="text-sm text-[var(--c-text-3)] mt-1">ประกาศจะแสดงเป็นหน้าต่างให้ผู้เข้าชมเห็นเมื่อเข้าสู่ระบบ</p>
            </div>
            
            <div id="popupPreview" class="rounded-3xl overflow-hidden border border-slate-200 bg-white shadow-sm">
                <div class="p-6 text-center text-slate-500">กำลังโหลดตัวอย่าง...</div>
            </div>

            <form id="popupAnnouncementForm" class="space-y-5">
                <input type="hidden" name="type" value="popup">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">หัวข้อประกาศ <span class="text-rose-500">*</span></label>
                    <input id="popupTitle" type="text" name="title" class="w-full rounded-3xl border border-slate-300 px-4 py-3 text-sm text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none" placeholder="กรอกหัวข้อประกาศ..." required />
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">ข้อความประกาศ <span class="text-rose-500">*</span></label>
                    <textarea id="popupMessage" name="message" class="w-full rounded-3xl border border-slate-300 px-4 py-4 text-sm text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none" rows="4" placeholder="กรอกข้อความที่ต้องการให้ปรากฏ..." required></textarea>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">รูปประกาศ (ไม่บังคับ)</label>
                    <input id="popupImage" type="file" name="image" accept="image/*" class="w-full rounded-3xl border border-slate-300 p-3 text-sm text-slate-700" />
                    <p class="text-xs text-slate-400 mt-1">รองรับ JPG, PNG, GIF, WEBP ขนาดไม่เกิน 5MB</p>
                </div>
                <div id="popupImagePreview" class="hidden rounded-3xl overflow-hidden border border-slate-200 bg-slate-50">
                    <img id="popupImagePreviewImg" src="" alt="ตัวอย่างรูปประกาศ" class="w-full object-cover" />
                    <div class="p-4 text-sm text-slate-600">รูปภาพแบนเนอร์ปัจจุบัน</div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">ระยะเวลาที่แสดง</label>
                        <input id="popupDuration" type="number" name="duration_val" min="1" class="w-full rounded-3xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none" placeholder="ตัวเลข" />
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">หน่วยเวลา</label>
                        <select id="popupDurationUnit" name="duration_unit" class="w-full rounded-3xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none">
                            <option value="never">แสดงจนกว่าจะลบ</option>
                            <option value="minutes">นาที</option>
                            <option value="hours">ชั่วโมง</option>
                            <option value="days">วัน</option>
                        </select>
                    </div>
                </div>
                <input type="hidden" id="popupExistingImage" name="existing_image_url" value="" />
                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <button type="submit" class="btn-primary bg-indigo-600 hover:bg-indigo-700 text-white rounded-3xl px-5 py-3 font-bold">บันทึกป๊อปอัป</button>
                    <button type="button" onclick="deleteAnnouncement('popup')" class="rounded-3xl border border-rose-200 text-rose-600 px-5 py-3 font-bold hover:bg-rose-50">ลบป๊อปอัป</button>
                </div>
            </form>
        </section>

        <!-- 2. ป้ายวิ่งประกาศ (Marquee Announcement) -->
        <section class="card p-6 rounded-3xl bg-slate-50 border border-slate-200 space-y-6">
            <div>
                <h3 class="text-xl font-black text-[var(--c-text-1)] flex items-center gap-2"><i data-lucide="megaphone" class="w-5 h-5 text-amber-500"></i> ป้ายวิ่งประกาศ (Marquee)</h3>
                <p class="text-sm text-[var(--c-text-3)] mt-1">ข้อความวิ่งแถบสีด้านบนในหน้าแรกของระบบ (ไม่มีรูปภาพ)</p>
            </div>

            <div id="marqueePreview" class="rounded-3xl overflow-hidden border border-slate-200 bg-white shadow-sm p-4">
                <div class="p-6 text-center text-slate-500">กำลังโหลดตัวอย่าง...</div>
            </div>

            <form id="marqueeAnnouncementForm" class="space-y-5">
                <input type="hidden" name="type" value="marquee">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">ข้อความป้ายวิ่ง <span class="text-rose-500">*</span></label>
                    <textarea id="marqueeMessage" name="message" class="w-full rounded-3xl border border-slate-300 px-4 py-4 text-sm text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none" rows="4" placeholder="พิมพ์ข้อความที่ต้องการให้วิ่งบนหน้าเว็บ..." required></textarea>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">ระยะเวลาที่แสดง</label>
                        <input id="marqueeDuration" type="number" name="duration_val" min="1" class="w-full rounded-3xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none" placeholder="ตัวเลข" />
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">หน่วยเวลา</label>
                        <select id="marqueeDurationUnit" name="duration_unit" class="w-full rounded-3xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none">
                            <option value="never">ตลอดไป (จนกว่าจะกดลบ)</option>
                            <option value="minutes">นาที</option>
                            <option value="hours">ชั่วโมง</option>
                            <option value="days">วัน</option>
                        </select>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <button type="submit" class="btn-primary bg-amber-500 hover:bg-amber-600 text-white rounded-3xl px-5 py-3 font-bold">บันทึกป้ายวิ่ง</button>
                    <button type="button" onclick="deleteAnnouncement('marquee')" class="rounded-3xl border border-rose-200 text-rose-600 px-5 py-3 font-bold hover:bg-rose-50">ลบป้ายวิ่ง</button>
                </div>
            </form>
        </section>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const refreshBtn = document.getElementById('refreshAnnouncementBtn');
    
    // Popup Elements
    const popupPreviewContainer = document.getElementById('popupPreview');
    const popupForm = document.getElementById('popupAnnouncementForm');
    const popupImageField = document.getElementById('popupImage');
    const popupImagePreviewWrapper = document.getElementById('popupImagePreview');
    const popupImagePreviewImg = document.getElementById('popupImagePreviewImg');

    // Marquee Elements
    const marqueePreviewContainer = document.getElementById('marqueePreview');
    const marqueeForm = document.getElementById('marqueeAnnouncementForm');

    async function loadAnnouncements() {
        popupPreviewContainer.innerHTML = '<div class="p-10 text-center text-slate-500">กำลังโหลด...</div>';
        marqueePreviewContainer.innerHTML = '<div class="p-10 text-center text-slate-500">กำลังโหลด...</div>';
        
        try {
            const response = await fetch('api/announcements/manage.php');
            const result = await response.json();
            
            if (!result.success) throw new Error(result.error);
            
            const data = result.data || {};
            const popupData = data.popup || {};
            const marqueeData = data.marquee || {};

            renderPopupPreview(popupData);
            populatePopupForm(popupData);

            renderMarqueePreview(marqueeData);
            populateMarqueeForm(marqueeData);

        } catch (error) {
            popupPreviewContainer.innerHTML = '<div class="p-10 text-center text-rose-500">เกิดข้อผิดพลาด</div>';
            marqueePreviewContainer.innerHTML = '<div class="p-10 text-center text-rose-500">เกิดข้อผิดพลาด</div>';
            console.error(error);
        }
    }

    function renderPopupPreview(data) {
        const hasTitle = data.title && data.title.trim() !== '';
        const hasMessage = data.message && data.message.trim() !== '';
        const hasImage = data.image_url && data.image_url.trim() !== '';

        if (!hasMessage && !hasImage) {
            popupPreviewContainer.innerHTML = '<div class="p-10 text-center text-slate-500 font-bold">ยังไม่มีป๊อปอัปประกาศในขณะนี้</div>';
            return;
        }

        const title = hasTitle ? data.title : 'ประกาศ';
        popupPreviewContainer.innerHTML = `
            <div class="bg-slate-900 text-white p-5">
                <p class="text-[10px] uppercase tracking-widest text-slate-400">ตัวอย่าง</p>
                <h3 class="mt-2 text-xl font-black">${encodeHTML(title)}</h3>
            </div>
            ${hasImage ? `<div class="w-full bg-slate-100 flex justify-center"><img src="${encodeHTML(data.image_url)}" class="max-h-48 object-contain"></div>` : ''}
            <div class="p-5 bg-white text-slate-700 text-sm whitespace-pre-line">${encodeHTML(data.message)}</div>
        `;
    }

    function renderMarqueePreview(data) {
        if (!data.message || data.message.trim() === '') {
            marqueePreviewContainer.innerHTML = '<div class="py-6 text-center text-slate-500 font-bold">ยังไม่มีป้ายวิ่งประกาศในขณะนี้</div>';
            return;
        }
        marqueePreviewContainer.innerHTML = `
            <div class="marquee-wrapper animate__animated animate__fadeIn relative flex items-center overflow-hidden bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl shadow-md h-12">
                <div class="absolute left-0 top-0 bottom-0 z-10 bg-indigo-700 px-4 flex items-center font-bold text-sm shadow-[4px_0_12px_rgba(0,0,0,0.15)]">
                    <i data-lucide="megaphone" class="w-4 h-4 mr-2 text-yellow-300"></i> ประกาศ
                </div>
                <div class="marquee-content whitespace-nowrap pl-[100%] font-semibold text-sm animate-[marqueeScroll_20s_linear_infinite]">
                    ${encodeHTML(data.message)}
                </div>
            </div>
        `;
        if (window.lucide) window.lucide.createIcons();
    }

    function populatePopupForm(data) {
        document.getElementById('popupTitle').value = data.title || '';
        document.getElementById('popupMessage').value = data.message || '';
        document.getElementById('popupExistingImage').value = data.image_url || '';
        document.getElementById('popupDuration').value = '';
        document.getElementById('popupDurationUnit').value = 'never';
        popupImageField.value = '';

        if (data.image_url) {
            popupImagePreviewImg.src = data.image_url;
            popupImagePreviewWrapper.classList.remove('hidden');
        } else {
            popupImagePreviewWrapper.classList.add('hidden');
            popupImagePreviewImg.src = '';
        }
    }

    function populateMarqueeForm(data) {
        document.getElementById('marqueeMessage').value = data.message || '';
        document.getElementById('marqueeDuration').value = '';
        document.getElementById('marqueeDurationUnit').value = 'never';
    }

    function encodeHTML(value) {
        return String(value || '').replace(/[&<>"]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[m]));
    }

    // --- Real-time Preview Logic ---
    function updateLivePopupPreview() {
        const currentTitle = document.getElementById('popupTitle').value;
        const currentMessage = document.getElementById('popupMessage').value;
        let currentImageUrl = document.getElementById('popupExistingImage').value;
        
        if (!popupImagePreviewWrapper.classList.contains('hidden') && popupImagePreviewImg.src) {
            currentImageUrl = popupImagePreviewImg.src;
        }

        renderPopupPreview({
            title: currentTitle,
            message: currentMessage,
            image_url: currentImageUrl
        });
    }

    function updateLiveMarqueePreview() {
        renderMarqueePreview({
            message: document.getElementById('marqueeMessage').value
        });
    }

    document.getElementById('popupTitle').addEventListener('input', updateLivePopupPreview);
    document.getElementById('popupMessage').addEventListener('input', updateLivePopupPreview);
    document.getElementById('marqueeMessage').addEventListener('input', updateLiveMarqueePreview);

    // Image Preview Logic
    popupImageField.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) {
            // Revert to existing if canceled
            if (document.getElementById('popupExistingImage').value) {
                popupImagePreviewImg.src = document.getElementById('popupExistingImage').value;
            } else {
                popupImagePreviewWrapper.classList.add('hidden');
                popupImagePreviewImg.src = '';
            }
            updateLivePopupPreview();
            return;
        }
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            Swal.fire('ข้อผิดพลาด', 'รองรับเฉพาะไฟล์รูปภาพ JPG, PNG, GIF, WEBP เท่านั้น', 'warning');
            e.target.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = e => {
            popupImagePreviewImg.src = e.target.result;
            popupImagePreviewWrapper.classList.remove('hidden');
            updateLivePopupPreview();
        };
        reader.readAsDataURL(file);
    });

    // Form Submission Handler
    async function handleFormSubmit(event, formElement) {
        event.preventDefault();
        const formData = new FormData(formElement);
        formData.append('action', 'save');

        Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        try {
            const response = await fetch('api/announcements/manage.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                Swal.fire('สำเร็จ', 'บันทึกข้อมูลเรียบร้อยแล้ว', 'success');
                await loadAnnouncements();
            } else {
                Swal.fire('ข้อผิดพลาด', result.error || 'ไม่สามารถบันทึกได้', 'error');
            }
        } catch (error) {
            Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
        }
    }

    popupForm.addEventListener('submit', e => handleFormSubmit(e, popupForm));
    marqueeForm.addEventListener('submit', e => handleFormSubmit(e, marqueeForm));

    // Global Delete Handler
    window.deleteAnnouncement = async function(type) {
        const typeName = type === 'popup' ? 'ป๊อปอัปกลางจอ' : 'ป้ายวิ่ง';
        const confirmResult = await Swal.fire({
            title: \`ลบ\${typeName}?\`,
            text: 'การดำเนินการนี้ไม่สามารถเรียกคืนได้',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'ลบ',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#dc2626'
        });
        
        if (!confirmResult.isConfirmed) return;

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('type', type);

        Swal.fire({ title: 'กำลังลบ...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        try {
            const response = await fetch('api/announcements/manage.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                Swal.fire('ลบแล้ว', 'ลบประกาศเรียบร้อยแล้ว', 'success');
                await loadAnnouncements();
            } else {
                Swal.fire('ข้อผิดพลาด', result.error || 'ไม่สามารถลบได้', 'error');
            }
        } catch (error) {
            Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
        }
    };

    refreshBtn.addEventListener('click', loadAnnouncements);
    
    // Initial Load
    loadAnnouncements();
});
</script>
