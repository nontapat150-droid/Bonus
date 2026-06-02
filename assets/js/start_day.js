// assets/js/start_day.js
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('startDayForm');
    const fileInput = document.getElementById('start_day_images');
    const previewContainer = document.getElementById('imagePreviewContainer');
    
    // 🌟 1. ปรับเกณฑ์การบีบอัดให้ทำงานเมื่อไฟล์ใหญ่กว่า 500KB (จากเดิม 5MB)
    const COMPRESS_THRESHOLD = 500 * 1024; 

    const tabFormBtn = document.getElementById('tabFormBtn');
    const tabHistBtn = document.getElementById('tabHistBtn');
    const formSection = document.getElementById('formSection');
    const historySection = document.getElementById('historySection');

    if (tabFormBtn && tabHistBtn) {
        tabFormBtn.addEventListener('click', () => {
            formSection.classList.remove('hidden');
            historySection.classList.add('hidden');
            tabFormBtn.className = "px-6 py-2.5 text-sm font-bold rounded-xl transition-all bg-emerald-50 text-emerald-600 shadow-sm";
            tabHistBtn.className = "px-6 py-2.5 text-sm font-bold rounded-xl transition-all text-slate-500 hover:bg-slate-50 hover:text-slate-700";
        });

        tabHistBtn.addEventListener('click', () => {
            formSection.classList.add('hidden');
            historySection.classList.remove('hidden');
            tabHistBtn.className = "px-6 py-2.5 text-sm font-bold rounded-xl transition-all bg-indigo-50 text-indigo-600 shadow-sm";
            tabFormBtn.className = "px-6 py-2.5 text-sm font-bold rounded-xl transition-all text-slate-500 hover:bg-slate-50 hover:text-slate-700";
            
            loadHistory(); 
        });
    }

    let selectedFiles = [];

    fileInput.addEventListener('change', async (e) => {
        const files = Array.from(e.target.files);
        if (selectedFiles.length + files.length > 10) {
            Swal.fire('แจ้งเตือน', 'อัปโหลดได้สูงสุด 10 รูป', 'warning');
            fileInput.value = ''; 
            return;
        }
        
        // 🌟 แสดง Loader ตอนเลือกรูปภาพ เผื่อเครื่องมือถือประมวลผลช้า
        if (files.length > 0) {
            Swal.fire({ title: 'กำลังเตรียมรูปภาพ...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        }

        for (const file of files) {
            if (!file.type.startsWith('image/')) continue;

            let processedFile = file;
            // 🌟 2. บีบอัดรูปภาพทุกรูปที่ใหญ่กว่า 500KB
            if (file.size > COMPRESS_THRESHOLD) {
                try {
                    processedFile = await compressImage(file);
                } catch (err) {
                    console.error('บีบอัดรูปภาพล้มเหลว', err);
                }
            }
            selectedFiles.push(processedFile);
            renderPreview(processedFile, selectedFiles.length - 1);
        }
        
        if (files.length > 0) Swal.close(); // ปิด Loader ทันทีที่ประมวลผลรูปเสร็จ
        
        fileInput.value = '';
    });

    function renderPreview(file, index) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const div = document.createElement('div');
            div.className = 'relative group rounded-lg overflow-hidden border border-gray-200 aspect-square';
            div.innerHTML = `
                <img src="${e.target.result}" class="w-full h-full object-cover">
                <button type="button" onclick="removeImage(${index})" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity shadow-md">✕</button>
            `;
            previewContainer.appendChild(div);
        };
        reader.readAsDataURL(file);
    }

    window.removeImage = (index) => {
        selectedFiles.splice(index, 1);
        previewContainer.innerHTML = '';
        selectedFiles.forEach((f, i) => renderPreview(f, i));
    };

    // 🌟 3. ปรับฟังก์ชันให้ลดขนาดพิกเซลและคุณภาพลง (จะทำให้ส่งข้อมูลไวขึ้น 10 เท่า)
    async function compressImage(file) {
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.onload = (event) => {
                const img = new Image();
                img.onload = () => {
                    let canvas = document.createElement('canvas');
                    let ctx = canvas.getContext('2d');
                    
                    // ปรับขนาดภาพสูงสุดไม่เกิน 1280px (จากเดิม 1920px)
                    const MAX_DIMENSION = 1280;
                    const ratio = Math.min(1, MAX_DIMENSION / Math.max(img.width, img.height));
                    let width = Math.round(img.width * ratio);
                    let height = Math.round(img.height * ratio);
                    
                    canvas.width = width;
                    canvas.height = height;
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    // ลดคุณภาพ (Quality) ลงเหลือ 75% ทำให้ไฟล์เล็กมาก และไวต่อการอัปโหลด
                    canvas.toBlob(blob => {
                        const name = file.name.replace(/\.[^/.]+$/, '') + '.jpg';
                        resolve(new File([blob], name, { type: 'image/jpeg' }));
                    }, 'image/jpeg', 0.75);
                };
                img.src = event.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    window.loadHistory = async function() {
        const tbody = document.getElementById('historyTableBody');
        tbody.innerHTML = '<tr class="block md:table-row"><td colspan="6" class="text-center py-8 text-slate-400 block md:table-cell">กำลังโหลดข้อมูล...</td></tr>';
        
        try {
            const res = await fetch('api/start_day/get_history.php');
            const text = await res.text();
            
            let data;
            try {
                data = JSON.parse(text);
            } catch(e) {
                console.error("เซิร์ฟเวอร์ส่งกลับมาผิดปกติ:", text);
                tbody.innerHTML = '<tr class="block md:table-row"><td colspan="6" class="text-center py-8 text-rose-500 block md:table-cell">เซิร์ฟเวอร์ทำงานผิดพลาด (กด F12 ดู Console)</td></tr>';
                return;
            }
            
            if (data.success) {
                renderHistoryTable(data.data, data.is_super_admin);
            } else {
                tbody.innerHTML = `<tr class="block md:table-row"><td colspan="6" class="text-center py-8 text-rose-500 block md:table-cell">${data.error}</td></tr>`;
            }
        } catch (e) {
            tbody.innerHTML = '<tr class="block md:table-row"><td colspan="6" class="text-center py-8 text-rose-500 block md:table-cell">ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้</td></tr>';
        }
    }

    function renderHistoryTable(records, isSuperAdmin = false) {
        const tbody = document.getElementById('historyTableBody');
        tbody.innerHTML = '';
        
        if (records.length === 0) {
            tbody.innerHTML = '<tr class="block md:table-row"><td colspan="6" class="text-center py-8 text-slate-400 italic block md:table-cell">ยังไม่มีประวัติการบันทึกของคุณ</td></tr>';
            return;
        }

        records.forEach(item => {
            let statusHtml = '';
            if (item.has_initial_fee == 1) {
                statusHtml = '<span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-lg text-xs font-bold border border-emerald-200">✅ มีค่าแรกเข้า</span>';
            } else if (item.has_initial_fee == 2) {
                statusHtml = '<span class="bg-amber-100 text-amber-700 px-3 py-1 rounded-lg text-xs font-bold border border-amber-200">💵 จ่ายหน้างาน</span>';
            } else {
                statusHtml = '<span class="bg-rose-100 text-rose-700 px-3 py-1 rounded-lg text-xs font-bold border border-rose-200">❌ ไม่มี</span>';
            }

            let imgHtml = item.evidence_image
                ? `<a href="assets/uploads/start_day/${item.evidence_image}" target="_blank" class="inline-block hover:scale-105 transition-transform"><img src="assets/uploads/start_day/${item.evidence_image}" class="w-12 h-12 object-cover rounded-xl shadow-sm border border-slate-200"></a>`
                : '<div class="w-12 h-12 flex items-center justify-center mx-auto rounded-xl border border-slate-200 bg-slate-100 text-[10px] text-slate-400">ไม่มีรูป</div>';

            let manageColumn = '';
            if (isSuperAdmin) {
                manageColumn = `
                    <td class="flex justify-between md:table-cell px-2 md:px-6 py-3 md:text-center items-center">
                        <span class="md:hidden text-[10px] font-black text-slate-400 uppercase tracking-widest">จัดการ</span>
                        <button type="button" onclick="deleteStartDayRecord(${item.id})" class="px-3 py-1.5 bg-rose-50 text-rose-600 font-bold hover:bg-rose-100 rounded-lg transition-all text-xs border border-rose-100 shadow-sm inline-flex items-center justify-center mx-auto">
                            ลบข้อมูล
                        </button>
                    </td>
                `;
            }

            const tr = document.createElement('tr');
            tr.className = 'block md:table-row bg-white md:bg-transparent border-b border-slate-100 mb-4 md:mb-0 p-4 md:p-0 hover:bg-slate-50 transition-colors rounded-xl md:rounded-none shadow-sm md:shadow-none';
            
            tr.innerHTML = `
                <td class="flex justify-between md:table-cell px-2 md:px-6 py-3 border-b border-dashed border-slate-100 md:border-none">
                    <span class="md:hidden text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1">เวลา</span>
                    <div class="text-right md:text-left">
                        <span class="font-bold text-slate-700">${item.date_str}</span>
                        <span class="text-xs font-mono text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded ml-1 font-bold">${item.time_str}</span>
                    </div>
                </td>
                <td class="flex justify-between md:table-cell px-2 md:px-6 py-3 border-b border-dashed border-slate-100 md:border-none">
                    <span class="md:hidden text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1">ชื่อลูกค้า</span>
                    <span class="font-bold text-slate-800">${item.customer_name}</span>
                </td>
                <td class="flex justify-between md:table-cell px-2 md:px-6 py-3 border-b border-dashed border-slate-100 md:border-none">
                    <span class="md:hidden text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1">เลข Non</span>
                    <span class="font-mono text-indigo-600 font-bold bg-indigo-50 px-2 py-1 rounded">${item.non_number}</span>
                </td>
                <td class="flex justify-between md:table-cell px-2 md:px-6 py-3 border-b border-dashed border-slate-100 md:border-none md:text-center">
                    <span class="md:hidden text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1">สถานะ</span>
                    ${statusHtml}
                </td>
                <td class="flex justify-between md:table-cell px-2 md:px-6 py-3 md:text-center items-center ${isSuperAdmin ? 'border-b border-dashed border-slate-100 md:border-none' : ''}">
                    <span class="md:hidden text-[10px] font-black text-slate-400 uppercase tracking-widest">รูปภาพ</span>
                    <div class="md:flex md:justify-center">${imgHtml}</div>
                </td>
                ${manageColumn}
            `;
            tbody.appendChild(tr);
        });
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const nonInput = form.querySelector('[name="non_number"]');
        if (nonInput) {
            const nonVal = nonInput.value.trim();
            if (nonVal.length !== 10) {
                return Swal.fire('แจ้งเตือน', `เลข Non ต้องมี 10 ตัวพอดี (คุณกรอกมา ${nonVal.length} ตัว)`, 'warning');
            }
        }
        
        if (selectedFiles.length === 0) {
            return Swal.fire('แจ้งเตือน', 'กรุณาถ่ายรูปหรือแนบรูปภาพอย่างน้อย 1 รูป ก่อนกดบันทึก', 'warning');
        }

        const confirmResult = await Swal.fire({
            title: 'ยืนยันการบันทึก?',
            text: "ตรวจสอบข้อมูลชื่อลูกค้าและเลข Non ให้ถูกต้องก่อนยืนยัน",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#059669', 
            cancelButtonColor: '#94a3b8',
            confirmButtonText: '✅ ยืนยันบันทึก',
            cancelButtonText: 'ยกเลิก',
            customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md', cancelButton: 'rounded-xl px-6 py-2.5 font-bold' }
        });

        if (!confirmResult.isConfirmed) return;

        const formData = new FormData(form);
        formData.delete('start_day_images[]');
        selectedFiles.forEach(file => formData.append('start_day_images[]', file));

        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerHTML;
        
        Swal.fire({ title: 'กำลังอัปโหลดข้อมูล...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        submitBtn.disabled = true;

        try {
            const response = await fetch('api/start_day/submit.php', { method: 'POST', body: formData });
            const text = await response.text(); 
            
            let result;
            try {
                result = JSON.parse(text);
            } catch(e) {
                console.error(text);
                Swal.fire('ข้อผิดพลาด', 'เซิร์ฟเวอร์ทำงานผิดพลาด (กด F12 เพื่อดู Console)', 'error');
                submitBtn.disabled = false;
                return;
            }

            if (result.success) {
                Swal.fire({
                    title: 'สำเร็จ!',
                    text: 'บันทึกข้อมูลเรียบร้อยแล้ว (ระบบบันทึกเวลาให้คุณอัตโนมัติ)',
                    icon: 'success',
                    confirmButtonText: 'ดูประวัติของฉัน',
                    confirmButtonColor: '#059669',
                    customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }
                }).then(() => {
                    if(tabHistBtn) tabHistBtn.click();
                });
                
                form.reset();
                selectedFiles = [];
                previewContainer.innerHTML = '';
            } else {
                Swal.fire('แจ้งเตือน', result.error, 'warning');
            }
        } catch (error) {
            Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อเซิร์ฟเวอร์ล้มเหลว', 'error');
        } finally {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });
});

window.deleteStartDayRecord = async function(id) {
    Swal.fire({
        title: 'ยืนยันการลบข้อมูล?',
        text: "ประวัติค่าแรกเข้านี้จะถูกลบออกจากระบบอย่างถาวร!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#