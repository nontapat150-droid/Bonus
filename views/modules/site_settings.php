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
            <p class="text-[var(--c-text-3)] text-sm mt-2">จัดการประกาศหน้าเว็บแบบ Popup หรือภาพประกาศสำหรับผู้เข้าชมทุกคน</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-3">
            <button id="refreshAnnouncementBtn" class="btn-primary bg-slate-600 hover:bg-slate-700 text-white">รีเฟรชข้อมูลปัจจุบัน</button>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1.05fr_0.95fr]">
        <section class="card p-6 rounded-3xl bg-slate-50 border border-slate-200">
            <div class="mb-5 flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-xl font-black text-[var(--c-text-1)]">ตัวอย่างประกาศ</h3>
                    <p class="text-sm text-[var(--c-text-3)] mt-1">ประกาศนี้จะแสดงเป็นหน้าต่างลอยเมื่อผู้ใช้เข้าใช้งาน</p>
                </div>
            </div>

            <div id="announcementPreview" class="rounded-3xl overflow-hidden border border-slate-200 bg-white shadow-sm">
                <div class="p-6 text-center text-slate-500">กำลังโหลดตัวอย่าง...</div>
            </div>
        </section>

        <section class="card p-6 rounded-3xl bg-white border border-slate-200">
            <h3 class="text-xl font-black text-[var(--c-text-1)] mb-5">ตั้งค่าประกาศหน้าเว็บ</h3>

            <form id="siteAnnouncementForm" class="space-y-5">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">ข้อความประกาศ</label>
                    <textarea id="announcementMessage" class="w-full rounded-3xl border border-slate-300 px-4 py-4 text-sm text-slate-700 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none" rows="5" placeholder="กรอกข้อความที่ต้องการให้ปรากฏในหน้าต่างประกาศ..."></textarea>
                </div>

                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">รูปประกาศ (ไม่บังคับ)</label>
                    <input id="announcementImage" type="file" accept="image/*" class="w-full rounded-3xl border border-slate-300 p-3 text-sm text-slate-700" />
                    <p class="text-xs text-slate-400 mt-1">รองรับ JPG, PNG, GIF, WEBP ขนาดไม่เกิน 5MB</p>
                </div>

                <div id="announcementImagePreview" class="hidden rounded-3xl overflow-hidden border border-slate-200 bg-slate-50">
                    <img id="announcementImagePreviewImg" src="" alt="ตัวอย่างรูปประกาศ" class="w-full object-cover" />
                    <div class="p-4 text-sm text-slate-600">รูปภาพที่เลือกจะแสดงเป็นแบนเนอร์ในประกาศ</div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">ระยะเวลาที่แสดง</label>
                        <input id="announcementDuration" type="number" min="1" class="w-full rounded-3xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none" placeholder="ตัวเลข" />
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">หน่วยเวลา</label>
                        <select id="announcementDurationUnit" class="w-full rounded-3xl border border-slate-300 px-4 py-3 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none">
                            <option value="never">แสดงจนกว่าจะลบ</option>
                            <option value="minutes">นาที</option>
                            <option value="hours">ชั่วโมง</option>
                            <option value="days">วัน</option>
                        </select>
                    </div>
                </div>

                <input type="hidden" id="existingImageUrl" value="" />

                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <button type="submit" class="btn-primary bg-indigo-600 hover:bg-indigo-700 text-white rounded-3xl px-5 py-3 font-bold">บันทึกประกาศ</button>
                    <button type="button" id="deleteAnnouncementBtn" class="rounded-3xl border border-rose-200 text-rose-600 px-5 py-3 font-bold hover:bg-rose-50">ลบประกาศปัจจุบัน</button>
                </div>
            </form>
        </section>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('siteAnnouncementForm');
    const messageField = document.getElementById('announcementMessage');
    const imageField = document.getElementById('announcementImage');
    const durationField = document.getElementById('announcementDuration');
    const durationUnitField = document.getElementById('announcementDurationUnit');
    const existingImageInput = document.getElementById('existingImageUrl');
    const imagePreviewWrapper = document.getElementById('announcementImagePreview');
    const imagePreviewImg = document.getElementById('announcementImagePreviewImg');
    const previewContainer = document.getElementById('announcementPreview');
    const deleteBtn = document.getElementById('deleteAnnouncementBtn');
    const refreshBtn = document.getElementById('refreshAnnouncementBtn');

    async function loadAnnouncement() {
        previewContainer.innerHTML = '<div class="p-10 text-center text-slate-500">กำลังโหลดข้อมูลประกาศ...</div>';
        try {
            const response = await fetch('api/announcements/manage.php');
            const result = await response.json();
            if (!result.success) {
                previewContainer.innerHTML = '<div class="p-10 text-center text-rose-500">ไม่สามารถโหลดประกาศปัจจุบันได้</div>';
                return;
            }
            renderPreview(result.data || {});
            populateForm(result.data || {});
        } catch (error) {
            previewContainer.innerHTML = '<div class="p-10 text-center text-rose-500">เกิดข้อผิดพลาดขณะโหลดประกาศ</div>';
        }
    }

    function renderPreview(data) {
        const hasImage = data.image_url && data.image_url.trim() !== '';
        const hasMessage = data.message && data.message.trim() !== '';
        const title = hasMessage ? 'ประกาศปัจจุบัน' : 'ยังไม่มีประกาศ';
        const body = hasMessage ? data.message : 'คุณยังไม่ได้ตั้งค่าประกาศหน้าเว็บในตอนนี้';

        previewContainer.innerHTML = `
            <div class="bg-slate-950 text-white p-6">
                <p class="text-xs uppercase tracking-[0.26em] text-slate-400">ตัวอย่างประกาศ</p>
                <h3 class="mt-3 text-2xl font-black">${title}</h3>
            </div>
            ${hasImage ? `<div class="overflow-hidden"><img src="${encodeHTML(data.image_url)}" alt="รูปประกาศ" class="w-full object-cover h-64" /></div>` : ''}
            <div class="p-6 bg-white text-slate-700 text-sm leading-relaxed whitespace-pre-line">${encodeHTML(body)}</div>
        `;
    }

    function populateForm(data) {
        messageField.value = data.message || '';
        durationField.value = '';
        durationUnitField.value = 'never';
        existingImageInput.value = data.image_url || '';

        if (data.image_url) {
            imagePreviewImg.src = data.image_url;
            imagePreviewWrapper.classList.remove('hidden');
        } else {
            imagePreviewWrapper.classList.add('hidden');
            imagePreviewImg.src = '';
        }
    }

    function encodeHTML(value) {
        return String(value || '').replace(/[&<>"]+/g, function (match) {
            const escape = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' };
            return escape[match];
        });
    }

    imageField.addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (!file) return;
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            alert('รองรับเฉพาะไฟล์รูปภาพ JPG, PNG, GIF, WEBP เท่านั้น');
            event.target.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            imagePreviewImg.src = e.target.result;
            imagePreviewWrapper.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    });

    form.addEventListener('submit', async function(event) {
        event.preventDefault();
        const message = messageField.value.trim();
        const durationVal = durationField.value.trim();
        const durationUnit = durationUnitField.value;

        if (!message) {
            Swal.fire('ไม่สามารถบันทึกได้', 'กรุณากรอกข้อความประกาศ', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'save');
        formData.append('message', message);
        formData.append('duration_val', durationVal);
        formData.append('duration_unit', durationUnit);
        formData.append('existing_image_url', existingImageInput.value);

        if (imageField.files[0]) {
            formData.append('image', imageField.files[0]);
        }

        Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        try {
            const response = await fetch('api/announcements/manage.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                Swal.fire('สำเร็จ', 'อัปเดตประกาศเรียบร้อยแล้ว', 'success');
                await loadAnnouncement();
                imageField.value = '';
            } else {
                Swal.fire('ข้อผิดพลาด', result.error || 'ไม่สามารถบันทึกประกาศได้', 'error');
            }
        } catch (error) {
            Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
        }
    });

    deleteBtn.addEventListener('click', async function() {
        const confirmResult = await Swal.fire({
            title: 'ลบประกาศ?',
            text: 'การลบประกาศจะทำให้ข้อความและรูปประกาศทั้งหมดหายไป',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'ลบ',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#dc2626'
        });
        if (!confirmResult.isConfirmed) return;

        const formData = new FormData();
        formData.append('action', 'delete');

        Swal.fire({ title: 'กำลังลบ...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        try {
            const response = await fetch('api/announcements/manage.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                Swal.fire('ลบแล้ว', 'ไม่มีประกาศปัจจุบันในระบบแล้ว', 'success');
                await loadAnnouncement();
                messageField.value = '';
                durationField.value = '';
                durationUnitField.value = 'never';
                existingImageInput.value = '';
                imageField.value = '';
                imagePreviewWrapper.classList.add('hidden');
            } else {
                Swal.fire('ข้อผิดพลาด', result.error || 'ไม่สามารถลบประกาศได้', 'error');
            }
        } catch (error) {
            Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
        }
    });

    refreshBtn.addEventListener('click', loadAnnouncement);
    loadAnnouncement();
});
</script>
