// assets/js/checkin.js
let checkinData = [];

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('checkinForm');
    const fileInput = document.getElementById('checkin_image');
    const imagePreview = document.getElementById('imagePreview');
    const uploadPrompt = document.getElementById('uploadPrompt');
    const timeDisplay = document.getElementById('currentTime');
    const submitBtn = document.getElementById('submitBtn');

    // ตั้งค่าเดือนปัจจุบัน
    const now = new Date();
    const currMonth = now.toISOString().slice(0, 7);
    if(document.getElementById('filterMonth')) {
        document.getElementById('filterMonth').value = currMonth;
    }

    loadSettings();
    loadCheckinHistory();

    if(timeDisplay) {
        setInterval(() => {
            timeDisplay.textContent = new Date().toLocaleTimeString('th-TH');
        }, 1000);
    }

    // ฟังก์ชันโชว์รูปเวลาจะกดเช็คอิน
    if(fileInput) {
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                if (!file.type.startsWith('image/')) {
                    Toast.error('กรุณาเลือกไฟล์รูปภาพเท่านั้น');
                    fileInput.value = ''; return;
                }
                const reader = new FileReader();
                reader.onload = (e) => {
                    imagePreview.src = e.target.result;
                    imagePreview.classList.remove('hidden');
                    uploadPrompt.classList.add('hidden');
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // กดยืนยันเช็คอินเข้างาน
    if(form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!fileInput.files[0]) return Toast.error('กรุณาถ่ายรูปเช็คอิน');

            Loader.show();
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'กำลังบันทึก...';
            const formData = new FormData(form);

            try {
                const response = await fetch('api/checkin/submit.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    Toast.success(result.message);
                    form.reset();
                    imagePreview.src = '';
                    imagePreview.classList.add('hidden');
                    uploadPrompt.classList.remove('hidden');
                    loadCheckinHistory(); // โหลดตารางใหม่
                } else {
                    Toast.error(result.error);
                }
            } catch (error) {
                Toast.error('เชื่อมต่อเซิร์ฟเวอร์ล้มเหลว');
            } finally {
                Loader.hide();
                submitBtn.disabled = false;
                submitBtn.innerHTML = '✅ ยืนยันการเช็คอิน';
            }
        });
    }

    // ดูตัวอย่างรูปในหน้าต่างแก้ไข
    const editImageInput = document.getElementById('edit_checkin_image');
    if (editImageInput) {
        editImageInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            const preview = document.getElementById('editImagePreview');
            const placeholder = document.getElementById('editImagePlaceholder');

            if (!file) {
                if(preview) { preview.src = ''; preview.classList.add('hidden'); }
                if(placeholder) placeholder.classList.remove('hidden');
                return;
            }
            if (!file.type.startsWith('image/')) {
                Toast.error('กรุณาเลือกไฟล์รูปภาพเท่านั้น');
                editImageInput.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = (event) => {
                if(preview) {
                    preview.src = event.target.result;
                    preview.classList.remove('hidden');
                }
                if(placeholder) placeholder.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        });
    }

    // ตัวกรองเวลา
    if(document.getElementById('filterDate')) {
        document.getElementById('filterDate').addEventListener('change', function() { document.getElementById('filterMonth').value = ''; });
    }
    if(document.getElementById('filterMonth')) {
        document.getElementById('filterMonth').addEventListener('change', function() { document.getElementById('filterDate').value = ''; });
    }
});

// โหลดข้อมูลตาราง
async function loadCheckinHistory() {
    const fDate = document.getElementById('filterDate').value;
    const fMonth = document.getElementById('filterMonth').value;
    
    const dashLabel = document.getElementById('dashLabel');
    if(fDate) dashLabel.textContent = `วันที่ ${new Date(fDate).toLocaleDateString('th-TH')}`;
    else if(fMonth) {
        const d = new Date(fMonth + '-01');
        dashLabel.textContent = `เดือน ${d.toLocaleString('th-TH', {month:'long', year:'numeric'})}`;
    } else dashLabel.textContent = 'ทั้งหมด';

    document.getElementById('historyTableBody').innerHTML = '<tr class="block md:table-row"><td colspan="5" class="text-center py-8 block md:table-cell">กำลังโหลดข้อมูล...</td></tr>';
    
    try {
        const res = await fetch(`api/checkin/get_history.php?date=${fDate}&month=${fMonth}`);
        const data = await res.json();
        
        if(data.success) {
            checkinData = data.records;
            renderTable(checkinData);
            
            document.getElementById('dashTotal').textContent = data.dashboard.total;
            document.getElementById('dashOntime').textContent = data.dashboard.on_time;
            document.getElementById('dashLate').textContent = data.dashboard.late;
        } else {
            Toast.error(data.error);
        }
    } catch(e) {
        document.getElementById('historyTableBody').innerHTML = '<tr class="block md:table-row"><td colspan="5" class="text-center py-8 text-red-500 block md:table-cell">โหลดข้อมูลล้มเหลว</td></tr>';
    }
}

// เรนเดอร์ตาราง
function renderTable(records) {
    const tbody = document.getElementById('historyTableBody');
    tbody.innerHTML = '';
    
    if(records.length === 0) {
        tbody.innerHTML = '<tr class="block md:table-row"><td colspan="5" class="text-center py-8 text-gray-400 italic block md:table-cell">ไม่พบประวัติการเข้างานในช่วงเวลานี้</td></tr>';
        return;
    }

    records.forEach((item) => {
        const dateObj = new Date(item.checkin_time);
        const tr = document.createElement('tr');
        
        tr.className = 'block md:table-row bg-white border border-slate-100 md:border-b md:border-x-0 md:border-t-0 rounded-[1.5rem] md:rounded-none shadow-sm md:shadow-none mb-4 md:mb-0 hover:bg-slate-50 transition-all p-4 md:p-0';
        
        const badge = item.status_code === 'late' 
            ? `<span class="bg-orange-100 text-orange-700 px-3 py-1 rounded-lg text-xs font-bold border border-orange-200">มาสาย</span>`
            : `<span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-lg text-xs font-bold border border-emerald-200">ตรงเวลา</span>`;

        const imageCell = item.image_path
            ? `<a href="assets/uploads/checkins/${item.image_path}" target="_blank" class="inline-block hover:scale-105 transition-transform"><img src="assets/uploads/checkins/${item.image_path}" class="w-12 h-12 md:w-10 md:h-10 object-cover rounded-xl shadow-sm border border-slate-200" alt="Evidence"></a>`
            : `<div class="w-12 h-12 md:w-10 md:h-10 flex items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-[10px] text-slate-400">ไม่มีรูป</div>`;

        const canEdit = ['super_admin', 'admin', 'technician', 'sales'].includes(window.USER_ROLE);
        const canDelete = window.USER_ROLE === 'super_admin';

        let actionHtml = `<div class="flex justify-end md:justify-center gap-2">`;
        if (canEdit) {
            // ปรับตรงนี้ให้ส่ง item.id แทนลำดับ Index ของ Array กันข้อมูลคลาดเคลื่อน
            actionHtml += `<button onclick="openEditCheckin(${item.id})" class="px-3 py-1.5 bg-indigo-50 text-indigo-600 font-bold hover:bg-indigo-100 rounded-lg transition-all text-xs border border-indigo-100">🖼️ แก้ไขรูป</button>`;
        }
        if (canDelete) {
            actionHtml += `<button onclick="deleteCheckin(${item.id})" class="px-3 py-1.5 bg-rose-50 text-rose-600 font-bold hover:bg-rose-100 rounded-lg transition-all text-xs border border-rose-100">🗑️ ลบข้อมูล</button>`;
        }
        if (!canEdit && !canDelete) {
            actionHtml += `<span class="text-slate-300 text-xs italic">-</span>`;
        }
        actionHtml += `</div>`;

        tr.innerHTML = `
            <td class="flex justify-between items-center md:table-cell px-2 md:px-4 py-3 border-b border-dashed border-slate-100 md:border-none">
                <span class="md:hidden text-[10px] font-black text-slate-400 uppercase tracking-widest">วันที่/เวลา</span>
                <div class="text-right md:text-left">
                    <span class="text-slate-800 font-bold">${dateObj.toLocaleDateString('th-TH')}</span>
                    <span class="text-xs text-indigo-600 font-mono bg-indigo-50 px-2 py-0.5 rounded-md md:ml-2 ml-1 font-bold">${dateObj.toLocaleTimeString('th-TH')}</span>
                </div>
            </td>
            <td class="flex justify-between items-center md:table-cell px-2 md:px-4 py-3 border-b border-dashed border-slate-100 md:border-none md:text-center">
                <span class="md:hidden text-[10px] font-black text-slate-400 uppercase tracking-widest">รูปถ่าย</span>
                ${imageCell}
            </td>
            <td class="flex justify-between items-center md:table-cell px-2 md:px-4 py-3 border-b border-dashed border-slate-100 md:border-none">
                <span class="md:hidden text-[10px] font-black text-slate-400 uppercase tracking-widest">พนักงาน</span>
                <div class="text-right md:text-left">
                    <p class="font-bold text-slate-800">${item.full_name}</p>
                    <p class="text-[10px] font-bold text-slate-400 bg-slate-100 inline-block px-2 py-0.5 rounded mt-1">${item.team_name || 'ไม่มีทีม'}</p>
                </div>
            </td>
            <td class="flex justify-between items-center md:table-cell px-2 md:px-4 py-3 border-b border-dashed border-slate-100 md:border-none md:text-center">
                <span class="md:hidden text-[10px] font-black text-slate-400 uppercase tracking-widest">สถานะ</span>
                ${badge}
            </td>
            <td class="flex justify-between items-center md:table-cell px-2 md:px-4 py-3 pt-4 md:text-center">
                <span class="md:hidden text-[10px] font-black text-slate-400 uppercase tracking-widest">จัดการ</span>
                ${actionHtml}
            </td>
        `;
        tbody.appendChild(tr);
    });
}

// ---------------- ระบบแก้ไข (สำหรับรูปภาพ) และ ลบ ----------------

// เปิดหน้าต่าง Modal อย่างปลอดภัยด้วย ID
window.openEditCheckin = function(id) {
    try {
        // ค้นหาข้อมูลจาก ID แทน Index ป้องกันปัญหาตารางคลาดเคลื่อน
        const item = checkinData.find(r => r.id == id);
        
        if(!item) {
            Toast.error('ไม่พบข้อมูล กรุณารีเฟรชหน้าเว็บ');
            return;
        }

        const idInput = document.getElementById('edit_checkin_id');
        const editInput = document.getElementById('edit_checkin_image');
        const preview = document.getElementById('editImagePreview');
        const placeholder = document.getElementById('editImagePlaceholder');
        const delBtn = document.getElementById('deleteImageBtn');
        const modal = document.getElementById('editCheckinModal');

        if(idInput) idInput.value = item.id;
        if(editInput) editInput.value = '';

        if (item.image_path) {
            if(preview) {
                preview.src = `assets/uploads/checkins/${item.image_path}`;
                preview.classList.remove('hidden');
            }
            if(placeholder) placeholder.classList.add('hidden');
            
            if (delBtn) {
                if (window.USER_ROLE === 'super_admin') {
                    delBtn.classList.remove('hidden');
                } else {
                    delBtn.classList.add('hidden');
                }
            }
        } else {
            if(preview) {
                preview.src = '';
                preview.classList.add('hidden');
            }
            if(placeholder) placeholder.classList.remove('hidden');
            if(delBtn) delBtn.classList.add('hidden');
        }

        if(modal) {
            modal.classList.remove('hidden');
        }
    } catch (err) {
        console.error("Error modal:", err);
        alert('เกิดข้อผิดพลาดในการเปิดหน้าต่างแก้ไข');
    }
};

// ปิดหน้าต่าง Modal
window.closeEditCheckinModal = function() {
    const modal = document.getElementById('editCheckinModal');
    if(modal) modal.classList.add('hidden');
    
    const preview = document.getElementById('editImagePreview');
    if (preview) { preview.src = ''; preview.classList.add('hidden'); }
    
    const placeholder = document.getElementById('editImagePlaceholder');
    if (placeholder) placeholder.classList.remove('hidden');
    
    const editInput = document.getElementById('edit_checkin_image');
    if (editInput) editInput.value = '';
    
    const delBtn = document.getElementById('deleteImageBtn');
    if (delBtn) delBtn.classList.add('hidden');
};

// บันทึกการอัปเดตรูปภาพ
window.saveEditCheckin = async function() {
    const idInput = document.getElementById('edit_checkin_id');
    const editInput = document.getElementById('edit_checkin_image');

    if (!idInput || !idInput.value) {
        return Toast.error('ไม่พบ ID ข้อมูล');
    }

    if (!editInput || !editInput.files || editInput.files.length === 0) {
        return Toast.error('กรุณาเลือกรูปภาพใหม่ก่อนทำการบันทึก');
    }

    const formData = new FormData();
    formData.append('id', idInput.value);
    formData.append('checkin_image', editInput.files[0]);

    Loader.show();
    try {
        const res = await fetch('api/checkin/edit.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        
        if(data.success) {
            Toast.success('อัปเดตรูปภาพเรียบร้อยแล้ว');
            closeEditCheckinModal();
            loadCheckinHistory(); 
        } else {
            Toast.error(data.error);
        }
    } catch(e) {
        Toast.error('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
    } finally {
        Loader.hide();
    }
};

// ระบบลบเฉพาะรูปภาพอย่างเดียว
window.deleteCheckinImage = async function() {
    const idInput = document.getElementById('edit_checkin_id');
    if (!idInput || !idInput.value) return Toast.error('ไม่พบข้อมูลที่ต้องการลบรูป');

    Swal.fire({
        title: 'ยืนยันการลบรูปภาพ?',
        text: 'รูปภาพจะถูกลบออกจากระบบ แต่ข้อมูลบันทึกเวลาเช็คอินจะยังอยู่',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ใช่, ลบรูป',
        cancelButtonText: 'ยกเลิก'
    }).then(async (result) => {
        if (result.isConfirmed) {
            Loader.show();
            try {
                const res = await fetch('api/checkin/delete_image.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: idInput.value })
                });
                const data = await res.json();
                if (data.success) {
                    Toast.success('ลบรูปภาพเรียบร้อยแล้ว');
                    closeEditCheckinModal();
                    loadCheckinHistory();
                } else {
                    Toast.error(data.error);
                }
            } catch(e) {
                Toast.error('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
            } finally {
                Loader.hide();
            }
        }
    });
};

// ระบบลบข้อมูลเช็คอินทั้งรายการ
window.deleteCheckin = async function(id) {
    Swal.fire({
        title: 'ยืนยันการลบข้อมูล?',
        text: "ข้อมูลการเช็คอินนี้และรูปภาพจะถูกลบออกจากระบบอย่างถาวร!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ใช่, ลบข้อมูลเลย',
        cancelButtonText: 'ยกเลิก'
    }).then(async (result) => {
        if (result.isConfirmed) {
            Loader.show();
            try {
                const res = await fetch('api/checkin/delete.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: id })
                });
                const data = await res.json();
                
                if (data.success) {
                    Toast.success('ลบข้อมูลเช็คอินเรียบร้อยแล้ว');
                    loadCheckinHistory();
                } else {
                    Toast.error(data.error);
                }
            } catch(e) {
                Toast.error('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
            } finally {
                Loader.hide();
            }
        }
    });
};

// ---------------- Settings & Export (Admin) ----------------
async function loadSettings() {
    const input = document.getElementById('lateTimeInput');
    if(!input) return;
    try {
        const res = await fetch('api/checkin/settings.php');
        const data = await res.json();
        if(data.success) input.value = data.late_time;
    } catch(e) {}
}

window.saveSettings = async function() {
    const time = document.getElementById('lateTimeInput').value;
    if(!time) return Toast.error('กรุณาระบุเวลา');
    Loader.show();
    try {
        const res = await fetch('api/checkin/settings.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({late_time: time})
        });
        const data = await res.json();
        if(data.success) {
            Toast.success('อัปเดตเวลาเข้างานสำเร็จ');
            loadCheckinHistory(); 
        } else Toast.error(data.error);
    } catch(e) { Toast.error('ล้มเหลว'); } finally { Loader.hide(); }
};

window.exportCheckin = function() {
    if(checkinData.length === 0) return Toast.error('ไม่มีข้อมูลให้ Export');
    Toast.info('กำลังสร้างไฟล์ Excel...');
    
    const exportArr = checkinData.map((r, i) => {
        const d = new Date(r.checkin_time);
        return {
            "ลำดับ": i + 1,
            "วันที่": d.toLocaleDateString('th-TH'),
            "เวลา": d.toLocaleTimeString('th-TH'),
            "สถานะ": r.status_text,
            "ชื่อ-นามสกุล": r.full_name,
            "สังกัด/ทีม": r.team_name || '-',
            "ไฟล์หลักฐาน": r.image_path
        };
    });

    const worksheet = XLSX.utils.json_to_sheet(exportArr);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "ประวัติการเช็คอิน");
    XLSX.writeFile(workbook, `เช็คอิน_${new Date().getTime()}.xlsx`);
    Toast.success('ดาวน์โหลดสำเร็จ');
};