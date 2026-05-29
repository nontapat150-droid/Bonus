// assets/js/start_day.js
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('startDayForm');
    const fileInput = document.getElementById('start_day_images');
    const previewContainer = document.getElementById('imagePreviewContainer');
    const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB

    let selectedFiles = [];

    // โหลดประวัติทันทีเมื่อเข้าหน้านี้
    loadHistory();

    fileInput.addEventListener('change', async (e) => {
        const files = Array.from(e.target.files);
        if (selectedFiles.length + files.length > 10) {
            Toast.error('อัปโหลดได้สูงสุด 10 รูป');
            fileInput.value = ''; 
            return;
        }
        
        for (const file of files) {
            if (!file.type.startsWith('image/')) continue;

            let processedFile = file;
            if (file.size > MAX_IMAGE_SIZE) {
                try {
                    processedFile = await compressImage(file, MAX_IMAGE_SIZE);
                } catch (err) {
                    Toast.error('รูปภาพใหญ่เกินไป บีบอัดไม่สำเร็จ');
                    continue;
                }
            }
            selectedFiles.push(processedFile);
            renderPreview(processedFile, selectedFiles.length - 1);
        }
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

    async function compressImage(file, maxSize) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (event) => {
                const img = new Image();
                img.onload = async () => {
                    let canvas = document.createElement('canvas');
                    let ctx = canvas.getContext('2d');
                    const ratio = Math.min(1, 1920 / Math.max(img.width, img.height));
                    let width = Math.round(img.width * ratio);
                    let height = Math.round(img.height * ratio);
                    let quality = 0.9;
                    canvas.width = width;
                    canvas.height = height;
                    ctx.drawImage(img, 0, 0, width, height);
                    canvas.toBlob(blob => {
                        const name = file.name.replace(/\.[^/.]+$/, '') + '.jpg';
                        resolve(new File([blob], name, { type: 'image/jpeg' }));
                    }, 'image/jpeg', quality);
                };
                img.src = event.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    // ฟังก์ชันโหลดข้อมูลประวัติ
    async function loadHistory() {
        const tbody = document.getElementById('historyTableBody');
        tbody.innerHTML = '<tr class="block md:table-row"><td colspan="5" class="text-center py-8 text-slate-400 block md:table-cell">กำลังโหลดข้อมูล...</td></tr>';
        
        try {
            const res = await fetch('api/start_day/get_history.php');
            const data = await res.json();
            
            if (data.success) {
                renderHistoryTable(data.data);
            } else {
                tbody.innerHTML = `<tr class="block md:table-row"><td colspan="5" class="text-center py-8 text-rose-500 block md:table-cell">${data.error}</td></tr>`;
            }
        } catch (e) {
            tbody.innerHTML = '<tr class="block md:table-row"><td colspan="5" class="text-center py-8 text-rose-500 block md:table-cell">ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้</td></tr>';
        }
    }

    // ฟังก์ชันสร้างตารางประวัติ
    function renderHistoryTable(records) {
        const tbody = document.getElementById('historyTableBody');
        tbody.innerHTML = '';
        
        if (records.length === 0) {
            tbody.innerHTML = '<tr class="block md:table-row"><td colspan="5" class="text-center py-8 text-slate-400 italic block md:table-cell">ยังไม่มีประวัติการบันทึกของคุณ</td></tr>';
            return;
        }

        records.forEach(item => {
            // จัดการสีป้ายสถานะ
            let statusHtml = '';
            if (item.has_initial_fee == 1) {
                statusHtml = '<span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-lg text-xs font-bold border border-emerald-200">✅ มีค่าแรกเข้า</span>';
            } else if (item.has_initial_fee == 2) {
                statusHtml = '<span class="bg-amber-100 text-amber-700 px-3 py-1 rounded-lg text-xs font-bold border border-amber-200">💵 จ่ายหน้างาน</span>';
            } else {
                statusHtml = '<span class="bg-rose-100 text-rose-700 px-3 py-1 rounded-lg text-xs font-bold border border-rose-200">❌ ไม่มี</span>';
            }

            // จัดการรูปภาพ
            let imgHtml = item.evidence_image
                ? `<a href="assets/uploads/start_day/${item.evidence_image}" target="_blank" class="inline-block hover:scale-105 transition-transform"><img src="assets/uploads/start_day/${item.evidence_image}" class="w-12 h-12 object-cover rounded-xl shadow-sm border border-slate-200"></a>`
                : '<div class="w-12 h-12 flex items-center justify-center mx-auto rounded-xl border border-slate-200 bg-slate-100 text-[10px] text-slate-400">ไม่มีรูป</div>';

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
                <td class="flex justify-between md:table-cell px-2 md:px-6 py-3 md:text-center items-center">
                    <span class="md:hidden text-[10px] font-black text-slate-400 uppercase tracking-widest">รูปภาพ</span>
                    <div class="md:flex md:justify-center">${imgHtml}</div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (selectedFiles.length === 0) return Toast.error('กรุณาถ่ายรูปหรือแนบรูปภาพอย่างน้อย 1 รูป');

        const formData = new FormData(form);
        formData.delete('start_day_images[]');
        selectedFiles.forEach(file => formData.append('start_day_images[]', file));

        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerHTML;
        Loader.show();
        submitBtn.disabled = true;

        try {
            const response = await fetch('api/start_day/submit.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                Swal.fire({
                    title: 'สำเร็จ!',
                    text: 'บันทึกข้อมูลเรียบร้อยแล้ว',
                    icon: 'success',
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#059669',
                    customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }
                });
                form.reset();
                selectedFiles = [];
                previewContainer.innerHTML = '';
                
                // สั่งให้รีโหลดตารางประวัติใหม่ทันที
                loadHistory();
            } else {
                Toast.error(result.error);
            }
        } catch (error) {
            Toast.error('เชื่อมต่อเซิร์ฟเวอร์ล้มเหลว');
        } finally {
            Loader.hide();
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });
});