// assets/js/system_history.js

let currentType = 'checkin'; // จำหมวดหมู่ปัจจุบันที่กดดูอยู่

document.addEventListener('DOMContentLoaded', () => {
    const filterDate = document.getElementById('filterDate');
    const filterMonth = document.getElementById('filterMonth');

    // ตั้งค่าเริ่มต้นให้โชว์เดือนปัจจุบัน
    const now = new Date();
    filterMonth.value = now.toISOString().slice(0, 7);

    // ถ้าผู้ใช้เลือกวัน ให้ล้างค่าช่องเดือน
    if(filterDate) filterDate.addEventListener('change', () => { if(filterMonth) filterMonth.value = ''; });
    // ถ้าผู้ใช้เลือกเดือน ให้ล้างค่าช่องวัน
    if(filterMonth) filterMonth.addEventListener('change', () => { if(filterDate) filterDate.value = ''; });

    // โหลดครั้งแรก
    loadHistory('checkin');
});

// กดปุ่มค้นหาจะโหลดข้อมูลหมวดหมู่เดิมซ้ำ โดยดึงค่า Filter ใหม่
function applyFilter() {
    loadHistory(currentType);
}

async function loadHistory(type) {
    currentType = type;

    // สลับสีปุ่ม Tabs ให้ชัดเจน
    document.querySelectorAll('.hist-tab').forEach(btn => {
        btn.classList.remove('active-tab', 'bg-indigo-50', 'text-indigo-700');
        btn.classList.add('text-slate-500', 'hover:bg-slate-50');
    });
    const activeBtn = document.getElementById(`tab-${type}`);
    if(activeBtn) {
        activeBtn.classList.add('active-tab', 'bg-indigo-50', 'text-indigo-700');
        activeBtn.classList.remove('text-slate-500', 'hover:bg-slate-50');
    }

    const tHead = document.getElementById('tableHead');
    const tBody = document.getElementById('tableBody');
    const badge = document.getElementById('recordCountBadge');
    
    if(!tBody || !tHead) return;

    tBody.innerHTML = '<tr class="block md:table-row"><td colspan="6" class="text-center py-10 text-slate-400 block md:table-cell"><i data-lucide="loader-2" class="w-6 h-6 animate-spin mx-auto mb-2 text-indigo-500"></i> กำลังดึงข้อมูล...</td></tr>';
    if(badge) badge.textContent = 'โหลด...';
    if(window.lucide) lucide.createIcons();

    // ดึงค่าตัวกรอง
    const fDate = document.getElementById('filterDate') ? document.getElementById('filterDate').value : '';
    const fMonth = document.getElementById('filterMonth') ? document.getElementById('filterMonth').value : '';

    try {
        const res = await fetch(`api/history/get_logs.php?type=${type}&date=${fDate}&month=${fMonth}`);
        const data = await res.json();

        if (data.success) {
            renderTable(type, data.data, tHead, tBody);
            if(badge) badge.textContent = `${data.data.length} รายการ`;
        } else {
            tBody.innerHTML = `<tr class="block md:table-row"><td colspan="6" class="text-center py-10 text-rose-500 block md:table-cell">${data.error}</td></tr>`;
            if(badge) badge.textContent = '0 รายการ';
        }
    } catch (e) {
        tBody.innerHTML = '<tr class="block md:table-row"><td colspan="6" class="text-center py-10 text-rose-500 block md:table-cell">ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้</td></tr>';
        if(badge) badge.textContent = 'Error';
    }
}

function renderTable(type, records, tHead, tBody) {
    if (records.length === 0) {
        tHead.innerHTML = '';
        tBody.innerHTML = '<tr class="block md:table-row"><td class="text-center py-12 text-slate-400 italic block md:table-cell">ไม่มีประวัติการทำรายการในช่วงเวลานี้</td></tr>';
        return;
    }

    tBody.innerHTML = '';

    if (type === 'checkin') {
        tHead.innerHTML = `<tr><th class="px-4 py-3">วัน-เวลา</th><th class="px-4 py-3">พนักงาน</th><th class="px-4 py-3">ทีม</th><th class="px-4 py-3 text-center">สถานะ</th><th class="px-4 py-3 text-center">รูปภาพ</th></tr>`;
        records.forEach(item => {
            const date = new Date(item.checkin_time).toLocaleString('th-TH');
            const badge = item.status_code === 'late' ? `<span class="bg-orange-100 text-orange-700 px-2 py-1 rounded-lg text-xs font-bold border border-orange-200">มาสาย</span>` : `<span class="bg-emerald-100 text-emerald-700 px-2 py-1 rounded-lg text-xs font-bold border border-emerald-200">ตรงเวลา</span>`;
            const img = item.image_path ? `<a href="assets/uploads/checkins/${item.image_path}" target="_blank"><img src="assets/uploads/checkins/${item.image_path}" class="w-10 h-10 object-cover rounded-xl shadow-sm border border-slate-200 md:mx-auto"></a>` : '-';
            
            tBody.innerHTML += `
                <tr class="block md:table-row bg-white md:bg-transparent border-b border-slate-100 mb-4 md:mb-0 p-4 md:p-0 hover:bg-slate-50 rounded-xl md:rounded-none shadow-sm md:shadow-none">
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 font-mono text-xs border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">เวลา</span>${date}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 font-bold border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">พนักงาน</span>${item.full_name}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 text-xs border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">ทีม</span>${item.team_name || '-'}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 md:text-center border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">สถานะ</span>${badge}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 md:text-center items-center"><span class="md:hidden font-black text-slate-400">รูปถ่าย</span>${img}</td>
                </tr>`;
        });
    } 
    else if (type === 'start_day') {
        // 🌟 เช็คว่าเป็น Super Admin ไหม เพื่อเพิ่มหัวตารางคอลัมน์ จัดการ
        const isSuperAdmin = (window.USER_ROLE === 'super_admin');
        const manageTh = isSuperAdmin ? '<th class="px-4 py-3 text-center">จัดการ</th>' : '';
        
        tHead.innerHTML = `<tr><th class="px-4 py-3">เวลาทำรายการ</th><th class="px-4 py-3">พนักงาน</th><th class="px-4 py-3">ลูกค้า (Non)</th><th class="px-4 py-3 text-center">สถานะแรกเข้า</th><th class="px-4 py-3 text-center">หลักฐาน</th>${manageTh}</tr>`;
        
        records.forEach(item => {
            const date = new Date(item.created_at).toLocaleString('th-TH');
            let status = '<span class="bg-rose-100 text-rose-700 px-2 py-1 rounded-lg text-xs font-bold border border-rose-200">❌ ไม่มี</span>';
            if(item.has_initial_fee == 1) status = '<span class="bg-emerald-100 text-emerald-700 px-2 py-1 rounded-lg text-xs font-bold border border-emerald-200">✅ มีค่าแรกเข้า</span>';
            if(item.has_initial_fee == 2) status = '<span class="bg-amber-100 text-amber-700 px-2 py-1 rounded-lg text-xs font-bold border border-amber-200">💵 หน้างาน</span>';
            const img = item.evidence_image ? `<a href="assets/uploads/start_day/${item.evidence_image}" target="_blank"><img src="assets/uploads/start_day/${item.evidence_image}" class="w-10 h-10 object-cover rounded-xl shadow-sm border border-slate-200 md:mx-auto"></a>` : '-';
            
            // 🌟 เพิ่มปุ่มลบ เฉพาะ Super Admin
            let manageTd = '';
            if (isSuperAdmin) {
                manageTd = `
                <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 md:text-center items-center ${isSuperAdmin ? '' : 'border-b border-dashed border-slate-100 md:border-none'}">
                    <span class="md:hidden font-black text-slate-400">จัดการ</span>
                    <button type="button" onclick="deleteStartDayRecordGlobal(${item.id})" class="px-3 py-1.5 bg-rose-50 text-rose-600 font-bold hover:bg-rose-100 rounded-lg transition-all text-xs border border-rose-100 shadow-sm inline-flex items-center justify-center md:mx-auto">
                        <i data-lucide="trash-2" class="w-4 h-4 mr-1"></i> ลบ
                    </button>
                </td>`;
            }

            tBody.innerHTML += `
                <tr class="block md:table-row bg-white md:bg-transparent border-b border-slate-100 mb-4 md:mb-0 p-4 md:p-0 hover:bg-slate-50 rounded-xl md:rounded-none shadow-sm md:shadow-none">
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 font-mono text-xs border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">เวลา</span>${date}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 font-bold text-indigo-600 border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">พนักงาน</span>${item.full_name}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">ลูกค้า</span><div class="text-right md:text-left"><div class="font-bold">${item.customer_name}</div><div class="text-xs text-slate-400">Non: ${item.non_number}</div></div></td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 md:text-center border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">สถานะ</span>${status}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 md:text-center items-center ${isSuperAdmin ? 'border-b border-dashed border-slate-100 md:border-none' : ''}"><span class="md:hidden font-black text-slate-400">หลักฐาน</span>${img}</td>
                    ${manageTd}
                </tr>`;
        });
    }
    else if (type === 'oil') {
        tHead.innerHTML = `<tr><th class="px-4 py-3">วันที่บิล</th><th class="px-4 py-3">ผู้บันทึก</th><th class="px-4 py-3">ทะเบียนรถ</th><th class="px-4 py-3">ลิตร/ราคา</th><th class="px-4 py-3 text-right">ยอดรวม</th><th class="px-4 py-3 text-center">บิล</th></tr>`;
        records.forEach(item => {
            const date = new Date(item.date_recorded).toLocaleString('th-TH');
            const img = item.evidence_image ? `<a href="assets/uploads/oil_receipts/${item.evidence_image}" target="_blank"><img src="assets/uploads/oil_receipts/${item.evidence_image}" class="w-10 h-10 object-cover rounded-xl shadow-sm border border-slate-200 md:mx-auto"></a>` : '-';
            
            tBody.innerHTML += `
                <tr class="block md:table-row bg-white md:bg-transparent border-b border-slate-100 mb-4 md:mb-0 p-4 md:p-0 hover:bg-slate-50 rounded-xl md:rounded-none shadow-sm md:shadow-none">
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 font-mono text-xs border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">เวลา</span>${date}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 font-bold text-blue-600 border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">ผู้บันทึก</span>${item.full_name}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 font-bold border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">รถ</span><span class="bg-slate-100 px-2 rounded">${item.license_plate}</span></td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 text-xs border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">รายละเอียด</span><div class="text-right md:text-left">${item.liters} L <br> ฿${item.price_per_liter} / L</div></td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 md:text-right font-black text-rose-600 border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">ยอดรวม</span>฿${parseFloat(item.total_price).toLocaleString()}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 md:text-center items-center"><span class="md:hidden font-black text-slate-400">บิล</span>${img}</td>
                </tr>`;
        });
    }
    else if (type === 'job_close') {
        tHead.innerHTML = `<tr>
            <th class="px-4 py-3">วันที่ปิด</th>
            <th class="px-4 py-3">ช่าง</th>
            <th class="px-4 py-3">ทีม</th>
            <th class="px-4 py-3">ประเภท</th>
            <th class="px-4 py-3">Non</th>
            <th class="px-4 py-3">ลูกค้า</th>
            <th class="px-4 py-3 text-center">จัดการ</th>
        </tr>`;
        records.forEach(item => {
            const date = item.created_at ? new Date(item.created_at).toLocaleString('th-TH') : '-';
            const provider = item.install_provider === 'AIS'
                ? '<span class="bg-green-100 text-green-800 px-2 py-0.5 rounded text-xs font-bold">AIS</span>'
                : '<span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded text-xs font-bold">3BB</span>';
            const editBtn = item.can_edit
                ? `<button type="button" onclick="openEditJobCloseModal(${item.id})" class="text-xs font-bold text-indigo-600 bg-indigo-50 border border-indigo-200 px-3 py-1 rounded-lg">แก้ไข</button>`
                : '';
            const deleteBtn = `<button type="button" onclick="deleteJobCloseRecord(${item.id})" class="text-xs font-bold text-rose-600 bg-rose-50 border border-rose-200 px-3 py-1 rounded-lg">ลบ</button>`;
            const actions = `<div class="flex flex-wrap gap-1 justify-center">${editBtn}${deleteBtn}</div>`;
            tBody.innerHTML += `
                <tr class="block md:table-row bg-white md:bg-transparent border-b border-slate-100 mb-4 md:mb-0 p-4 md:p-0 hover:bg-slate-50">
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 font-mono text-xs"><span class="md:hidden font-black text-slate-400">วันที่ปิด</span>${date}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 font-bold"><span class="md:hidden font-black text-slate-400">ช่าง</span>${item.tech_name}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 text-xs"><span class="md:hidden font-black text-slate-400">ทีม</span>${item.team_name || '-'}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3"><span class="md:hidden font-black text-slate-400">ประเภท</span>${provider}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 font-bold text-indigo-600"><span class="md:hidden font-black text-slate-400">Non</span>${item.close_case_no || item.access_no || '-'}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3"><span class="md:hidden font-black text-slate-400">ลูกค้า</span>${item.customer_name || '-'}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 md:text-center"><span class="md:hidden font-black text-slate-400">จัดการ</span>${actions}</td>
                </tr>`;
        });
    }
    else if (type === 'inventory') {
        tHead.innerHTML = `<tr><th class="px-4 py-3">เวลา</th><th class="px-4 py-3">ผู้ทำรายการ</th><th class="px-4 py-3 text-center">แอคชั่น</th><th class="px-4 py-3">สินค้า (SN)</th><th class="px-4 py-3">เป้าหมาย (รับ)</th></tr>`;
        records.forEach(item => {
            const date = new Date(item.timestamp).toLocaleString('th-TH');
            
            let badge = '';
            if(item.action === 'in') badge = '<span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-lg text-xs font-bold border border-blue-200">รับเข้า</span>';
            else if(item.action === 'out') badge = '<span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-lg text-xs font-bold border border-emerald-200">เบิกออก</span>';
            else badge = '<span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-lg text-xs font-bold border border-purple-200">โอนย้าย</span>';

            tBody.innerHTML += `
                <tr class="block md:table-row bg-white md:bg-transparent border-b border-slate-100 mb-4 md:mb-0 p-4 md:p-0 hover:bg-slate-50 rounded-xl md:rounded-none shadow-sm md:shadow-none">
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 font-mono text-xs border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">เวลา</span>${date}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 font-bold border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">ผู้ทำรายการ</span>${item.admin_name}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 md:text-center border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">แอคชั่น</span>${badge}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">สินค้า</span><div class="text-right md:text-left"><div class="font-bold text-slate-800">${item.product_name || 'ไม่ระบุ'}</div><div class="text-xs text-slate-500">SN: ${item.sn || '-'}</div></div></td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 text-sm"><span class="md:hidden font-black text-slate-400">ผู้รับโอน</span>${item.target_name || '-'}</td>
                </tr>`;
        });
    }

    if(window.lucide) lucide.createIcons();
}

// 🌟 ฟังก์ชันลบข้อมูลค่าแรกเข้า (ยิง API ตัวเดียวกับหน้าส่วนตัว)
window.deleteStartDayRecordGlobal = async function(id) {
    Swal.fire({
        title: 'ยืนยันการลบข้อมูล?',
        text: "ประวัติค่าแรกเข้านี้จะถูกลบออกจากระบบอย่างถาวร!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ใช่, ลบข้อมูล',
        cancelButtonText: 'ยกเลิก',
        customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md', cancelButton: 'rounded-xl px-6 py-2.5 font-bold' }
    }).then(async (result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'กำลังลบข้อมูล...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            
            try {
                // เรียกใช้ API ลบตัวเดิมได้เลย เพราะมันเช็คสิทธิ์ super_admin ไว้อยู่แล้ว
                const res = await fetch('api/start_day/delete.php', { 
                    method: 'POST', 
                    headers: {'Content-Type': 'application/json'}, 
                    body: JSON.stringify({ id: id }) 
                });
                const data = await res.json();
                
                if (data.success) {
                    Swal.fire({ title: 'สำเร็จ!', text: 'ลบข้อมูลเรียบร้อยแล้ว', icon: 'success', confirmButtonText: 'ตกลง', confirmButtonColor: '#10b981', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold' } });
                    
                    // 🌟 ลบเสร็จให้โหลดตารางค่าแรกเข้าใหม่ทันที
                    loadHistory('start_day');
                } else {
                    Swal.fire('ข้อผิดพลาด', data.error || 'ลบข้อมูลไม่สำเร็จ', 'error');
                }
            } catch(e) {
                Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
            }
        }
    });
};