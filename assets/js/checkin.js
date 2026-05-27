// assets/js/checkin.js
let checkinData = [];

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('checkinForm');
    const fileInput = document.getElementById('checkin_image');
    const imagePreview = document.getElementById('imagePreview');
    const uploadPrompt = document.getElementById('uploadPrompt');
    const timeDisplay = document.getElementById('currentTime');
    const submitBtn = document.getElementById('submitBtn');

    // Default Filter to Current Month
    const now = new Date();
    const currMonth = now.toISOString().slice(0, 7);
    document.getElementById('filterMonth').value = currMonth;

    // Load Data
    loadSettings();
    loadCheckinHistory();

    // 1. นาฬิกา Real-time
    setInterval(() => {
        timeDisplay.textContent = new Date().toLocaleTimeString('th-TH');
    }, 1000);

    // 2. แสดงตัวอย่างรูปภาพ
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

    // 3. ส่งข้อมูลไปที่ API
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
                loadCheckinHistory(); // รีโหลดประวัติทันที
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

    // ล้างช่อง Date เมื่อเลือก Month และสลับกัน
    document.getElementById('filterDate').addEventListener('change', function() { document.getElementById('filterMonth').value = ''; });
    document.getElementById('filterMonth').addEventListener('change', function() { document.getElementById('filterDate').value = ''; });
});

// ดึงประวัติ + อัปเดต Dashboard
async function loadCheckinHistory() {
    const fDate = document.getElementById('filterDate').value;
    const fMonth = document.getElementById('filterMonth').value;
    
    // อัปเดต Label
    const dashLabel = document.getElementById('dashLabel');
    if(fDate) dashLabel.textContent = `วันที่ ${new Date(fDate).toLocaleDateString('th-TH')}`;
    else if(fMonth) {
        const d = new Date(fMonth + '-01');
        dashLabel.textContent = `เดือน ${d.toLocaleString('th-TH', {month:'long', year:'numeric'})}`;
    } else dashLabel.textContent = 'ทั้งหมด';

    document.getElementById('historyTableBody').innerHTML = '<tr><td colspan="4" class="text-center py-8">กำลังโหลดข้อมูล...</td></tr>';
    
    try {
        const res = await fetch(`api/checkin/get_history.php?date=${fDate}&month=${fMonth}`);
        const data = await res.json();
        
        if(data.success) {
            checkinData = data.records;
            renderTable(checkinData);
            
            // Update Dashboard
            document.getElementById('dashTotal').textContent = data.dashboard.total;
            document.getElementById('dashOntime').textContent = data.dashboard.on_time;
            document.getElementById('dashLate').textContent = data.dashboard.late;
        } else {
            Toast.error(data.error);
        }
    } catch(e) {
        document.getElementById('historyTableBody').innerHTML = '<tr><td colspan="4" class="text-center py-8 text-red-500">โหลดข้อมูลล้มเหลว</td></tr>';
    }
}

function renderTable(records) {
    const tbody = document.getElementById('historyTableBody');
    tbody.innerHTML = '';
    
    if(records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-8 text-gray-400 italic">ไม่พบประวัติการเข้างานในช่างเวลานี้</td></tr>';
        return;
    }

    records.forEach(item => {
        const dateObj = new Date(item.checkin_time);
        const tr = document.createElement('tr');
        tr.className = 'border-b border-slate-50 hover:bg-slate-50 transition-colors';
        
        // แจ้งเตือนสถานะ สีเขียว/สีส้ม
        const badge = item.status_code === 'late' 
            ? `<span class="bg-orange-100 text-orange-700 px-3 py-1 rounded-full text-xs font-bold border border-orange-200">มาสาย</span>`
            : `<span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-xs font-bold border border-emerald-200">ตรงเวลา</span>`;

        tr.innerHTML = `
            <td class="px-4 py-3 text-slate-600 font-medium whitespace-nowrap">
                ${dateObj.toLocaleDateString('th-TH')} <br>
                <span class="text-xs text-slate-400 font-mono">${dateObj.toLocaleTimeString('th-TH')}</span>
            </td>
            <td class="px-4 py-3 text-center">
                <a href="assets/uploads/checkins/${item.image_path}" target="_blank" class="inline-block hover:opacity-80 transition-opacity">
                    <img src="assets/uploads/checkins/${item.image_path}" class="w-10 h-10 object-cover rounded-lg shadow-sm border border-slate-200" alt="Evidence">
                </a>
            </td>
            <td class="px-4 py-3">
                <p class="font-bold text-slate-700">${item.full_name}</p>
                <p class="text-xs text-slate-400">${item.team_name || '-'}</p>
            </td>
            <td class="px-4 py-3 text-center">${badge}</td>
        `;
        tbody.appendChild(tr);
    });
}

// ---------------- Settings (Admin) ----------------
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
            loadCheckinHistory(); // รีเฟรชเพื่อคำนวณสถานะสายใหม่
        } else Toast.error(data.error);
    } catch(e) { Toast.error('ล้มเหลว'); } finally { Loader.hide(); }
};

// ---------------- Export (Admin) ----------------
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