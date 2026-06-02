// assets/js/system_history.js

let currentType = 'checkin'; // จำหมวดหมู่ปัจจุบันที่กดดูอยู่

document.addEventListener('DOMContentLoaded', () => {
    const filterDate = document.getElementById('filterDate');
    const filterMonth = document.getElementById('filterMonth');

    // ไม่ตั้งค่าเริ่มต้นให้กรองเดือนหรือวัน เพื่อแสดงข้อมูลทั้งหมดจากเริ่มต้น
    // ผู้ใช้สามารถเลือกตัวกรองได้เองเมื่อต้องการ
    if (filterMonth) filterMonth.value = '';
    if (filterDate) filterDate.value = '';
    
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
            Swal.fire({
                title: 'ข้อผิดพลาด',
                text: data.error || 'ไม่สามารถดึงข้อมูลได้',
                icon: 'error',
                confirmButtonColor: '#ef4444'
            });
            // keep existing table unchanged
            if(badge) badge.textContent = '0 รายการ';
        }
    } catch (e) {
        Swal.fire({
            title: 'ข้อผิดพลาด',
            text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้',
            icon: 'error',
            confirmButtonColor: '#ef4444'
        });
        // keep existing table unchanged
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
        tHead.innerHTML = `<tr><th class="px-4 py-3">วัน-เวลา</th><th class="px-4 py-3">พนักงาน</th><th class="px-4 py-3">ทีม</th><th class="px-4 py-3 text-center">สถานะ</th><th class="px-4 py-3 text-center">รูปภาพ</th><th class="px-4 py-3 text-center">จัดการ</th></tr>`;
        records.forEach(item => {
            const date = new Date(item.checkin_time).toLocaleString('th-TH');
            const badge = item.status_code === 'late' ? `<span class="bg-orange-100 text-orange-700 px-2 py-1 rounded-lg text-xs font-bold border border-orange-200">มาสาย</span>` : `<span class="bg-emerald-100 text-emerald-700 px-2 py-1 rounded-lg text-xs font-bold border border-emerald-200">ตรงเวลา</span>`;
            const img = item.image_path ? `<a href="assets/uploads/checkins/${item.image_path}" target="_blank"><img src="assets/uploads/checkins/${item.image_path}" class="w-10 h-10 object-cover rounded-xl shadow-sm border border-slate-200 md:mx-auto"></a>` : '-';
            const deleteBtn = `<button type="button" onclick="deleteHistoryRecord('checkin', ${item.id})" class="text-xs font-bold text-rose-600 bg-rose-50 border border-rose-200 px-3 py-1 rounded-lg">ลบ</button>`;

            tBody.innerHTML += `
                <tr class="block md:table-row bg-white md:bg-transparent border-b border-slate-100 mb-4 md:mb-0 p-4 md:p-0 hover:bg-slate-50 rounded-xl md:rounded-none shadow-sm md:shadow-none">
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 font-mono text-xs border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">เวลา</span>${date}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 font-bold border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">พนักงาน</span>${item.full_name}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 text-xs border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">ทีม</span>${item.team_name || '-'}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 md:text-center border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">สถานะ</span>${badge}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 md:text-center items-center"><span class="md:hidden font-black text-slate-400">รูปถ่าย</span>${img}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 md:text-center items-center border-t md:border-none mt-2 md:mt-0 pt-2 md:pt-3"><span class="md:hidden font-black text-slate-400">จัดการ</span>${deleteBtn}</td>
                </tr>`;
        });
    } 
    else if (type === 'start_day') {
        // 🌟 1. จำข้อมูลประวัติไว้
        window.startDayRecords = records;

        const role = window.USER_ROLE;
        const isAdmin = (role === 'admin' || role === 'super_admin');
        
        tHead.innerHTML = `<tr><th class="px-4 py-3">เวลาทำรายการ</th><th class="px-4 py-3">พนักงาน</th><th class="px-4 py-3">ลูกค้า (Non)</th><th class="px-4 py-3 text-center">สถานะแรกเข้า</th><th class="px-4 py-3 text-center">หลักฐาน</th><th class="px-4 py-3 text-center">จัดการ</th></tr>`;
        
        records.forEach(item => {
            const date = new Date(item.created_at).toLocaleString('th-TH');
            let status = '<span class="bg-rose-100 text-rose-700 px-2 py-1 rounded-lg text-xs font-bold border border-rose-200">❌ ไม่มี</span>';
            if(item.has_initial_fee == 1) status = '<span class="bg-emerald-100 text-emerald-700 px-2 py-1 rounded-lg text-xs font-bold border border-emerald-200">✅ มีค่าแรกเข้า</span>';
            if(item.has_initial_fee == 2) status = '<span class="bg-amber-100 text-amber-700 px-2 py-1 rounded-lg text-xs font-bold border border-amber-200">💵 หน้างาน</span>';
            const img = item.evidence_image ? `<a href="assets/uploads/start_day/${item.evidence_image}" target="_blank"><img src="assets/uploads/start_day/${item.evidence_image}" class="w-10 h-10 object-cover rounded-xl shadow-sm border border-slate-200 md:mx-auto"></a>` : '-';
            
            // 🌟 2. ปุ่มจัดการ สำหรับกดแก้ไข/ลบ
            let deleteBtn = isAdmin ? `<button type="button" onclick="deleteStartDayRecordGlobal(${item.id})" class="px-2 py-1 bg-rose-50 text-rose-600 font-bold hover:bg-rose-100 rounded-lg transition-all text-xs border border-rose-100 shadow-sm inline-flex items-center justify-center">ลบ</button>` : '';

            let manageTd = `
            <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 md:text-center items-center border-b border-dashed border-slate-100 md:border-none">
                <span class="md:hidden font-black text-slate-400">จัดการ</span>
                <div class="flex gap-1 justify-end md:justify-center w-full">
                    <button type="button" onclick="openEditStartDayModal(${item.id})" class="px-2 py-1 bg-indigo-50 text-indigo-600 font-bold hover:bg-indigo-100 rounded-lg transition-all text-xs border border-indigo-100 shadow-sm inline-flex items-center justify-center">แก้ไข</button>
                    ${deleteBtn}
                </div>
            </td>`;

            tBody.innerHTML += `
                <tr class="block md:table-row bg-white md:bg-transparent border-b border-slate-100 mb-4 md:mb-0 p-4 md:p-0 hover:bg-slate-50 rounded-xl md:rounded-none shadow-sm md:shadow-none">
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 font-mono text-xs border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">เวลา</span>${date}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 font-bold text-indigo-600 border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">พนักงาน</span>${item.full_name}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">ลูกค้า</span><div class="text-right md:text-left"><div class="font-bold">${item.customer_name}</div><div class="text-xs text-slate-400">Non: ${item.non_number}</div></div></td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 md:text-center border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">สถานะ</span>${status}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 md:text-center items-center border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">หลักฐาน</span>${img}</td>
                    ${manageTd}
                </tr>`;
        });
    }
    else if (type === 'oil') {
        tHead.innerHTML = `<tr><th class="px-4 py-3">วันที่บิล</th><th class="px-4 py-3">ผู้บันทึก</th><th class="px-4 py-3">ทะเบียนรถ</th><th class="px-4 py-3">ลิตร/ราคา</th><th class="px-4 py-3 text-right">ยอดรวม</th><th class="px-4 py-3 text-center">บิล</th><th class="px-4 py-3 text-center">จัดการ</th></tr>`;
        records.forEach(item => {
            const date = new Date(item.date_recorded).toLocaleString('th-TH');
            const img = item.evidence_image ? `<a href="assets/uploads/oil_receipts/${item.evidence_image}" target="_blank"><img src="assets/uploads/oil_receipts/${item.evidence_image}" class="w-10 h-10 object-cover rounded-xl shadow-sm border border-slate-200 md:mx-auto"></a>` : '-';
            const deleteBtn = `<button type="button" onclick="deleteHistoryRecord('oil', ${item.id})" class="text-xs font-bold text-rose-600 bg-rose-50 border border-rose-200 px-3 py-1 rounded-lg">ลบ</button>`;

            tBody.innerHTML += `
                <tr class="block md:table-row bg-white md:bg-transparent border-b border-slate-100 mb-4 md:mb-0 p-4 md:p-0 hover:bg-slate-50 rounded-xl md:rounded-none shadow-sm md:shadow-none">
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 font-mono text-xs border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">เวลา</span>${date}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 font-bold text-blue-600 border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">ผู้บันทึก</span>${item.full_name}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 font-bold border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">รถ</span><span class="bg-slate-100 px-2 rounded">${item.license_plate}</span></td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 text-xs border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">รายละเอียด</span><div class="text-right md:text-left">${item.liters} L <br> ฿${item.price_per_liter} / L</div></td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 md:text-right font-black text-rose-600 border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">ยอดรวม</span>฿${parseFloat(item.total_price).toLocaleString()}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 md:text-center items-center"><span class="md:hidden font-black text-slate-400">บิล</span>${img}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 md:text-center items-center border-t md:border-none mt-2 md:mt-0 pt-2 md:pt-3"><span class="md:hidden font-black text-slate-400">จัดการ</span>${deleteBtn}</td>
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
        tHead.innerHTML = `<tr><th class="px-4 py-3">เวลา</th><th class="px-4 py-3">ผู้ทำรายการ</th><th class="px-4 py-3 text-center">แอคชั่น</th><th class="px-4 py-3">สินค้า (SN)</th><th class="px-4 py-3">เป้าหมาย (รับ)</th><th class="px-4 py-3 text-center">จัดการ</th></tr>`;
        records.forEach(item => {
            const date = new Date(item.timestamp).toLocaleString('th-TH');
            
            let badge = '';
            if(item.action === 'in') badge = '<span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-lg text-xs font-bold border border-blue-200">รับเข้า</span>';
            else if(item.action === 'out') badge = '<span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-lg text-xs font-bold border border-emerald-200">เบิกออก</span>';
            else badge = '<span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-lg text-xs font-bold border border-purple-200">โอนย้าย</span>';

            // สำหรับประวัติคลัง ถ้าเป็น consumable จะมี type เป็น 'consumable'
            const isConsumable = (item.sn === '-' && item.product_name && !item.target_name && !item.receiver_name); // simplistic check or backend must return type.
            // จริงๆ backend ต้องส่ง type มาให้ด้วย (item.is_consumable) ให้ชัวร์ ตอนนี้ส่งไปทั้ง id และ type ใน api
            const delType = item.type || 'item'; // 'item' or 'consumable'
            const deleteBtn = `<button type="button" onclick="deleteHistoryRecord('inventory', ${item.id}, '${delType}')" class="text-xs font-bold text-rose-600 bg-rose-50 border border-rose-200 px-3 py-1 rounded-lg">ลบ</button>`;

            tBody.innerHTML += `
                <tr class="block md:table-row bg-white md:bg-transparent border-b border-slate-100 mb-4 md:mb-0 p-4 md:p-0 hover:bg-slate-50 rounded-xl md:rounded-none shadow-sm md:shadow-none">
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 font-mono text-xs border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">เวลา</span>${date}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 font-bold border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">ผู้ทำรายการ</span>${item.admin_name}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 md:text-center border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">แอคชั่น</span>${badge}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">สินค้า</span><div class="text-right md:text-left"><div class="font-bold text-slate-800">${item.product_name || 'ไม่ระบุ'}</div><div class="text-xs text-slate-500">SN: ${item.sn || '-'}</div></div></td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 text-sm border-b border-dashed border-slate-100 md:border-none"><span class="md:hidden font-black text-slate-400">ผู้รับโอน</span>${item.target_name || '-'}</td>
                    <td class="flex justify-between md:table-cell px-2 md:px-4 py-3 md:text-center items-center border-t md:border-none mt-2 md:mt-0 pt-2 md:pt-3"><span class="md:hidden font-black text-slate-400">จัดการ</span>${deleteBtn}</td>
                </tr>`;
        });
    }

    if(window.lucide) lucide.createIcons();
}

// 🌟 ฟังก์ชันลบข้อมูลทั่วไปแบบ Single Item
window.deleteHistoryRecord = async function(type, id, extraParam = null) {
    let title = 'ยืนยันการลบข้อมูล?';
    let text = 'ประวัตินี้จะถูกลบออกจากระบบ';
    let url = '';
    let bodyData = { id: id };
    
    if (type === 'checkin') { url = 'api/history/delete_checkin.php'; text = 'ประวัติการเช็คอินนี้จะถูกลบทิ้ง'; }
    else if (type === 'oil') { url = 'api/history/delete_oil.php'; text = 'ประวัติการเติมน้ำมันและยอดค่าใช้จ่ายจะถูกลบ'; }
    else if (type === 'inventory') { 
        url = 'api/inventory/delete_history.php'; 
        text = 'ประวัติการทำรายการนี้จะถูกลบ (อาจมีการคืนยอดกลับเข้าคลัง)'; 
        bodyData.type = extraParam; 
    }

    Swal.fire({
        title: title,
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ใช่, ลบข้อมูล',
        cancelButtonText: 'ยกเลิก',
        customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md', cancelButton: 'rounded-xl px-6 py-2.5 font-bold' }
    }).then(async (result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'กำลังลบ...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            try {
                const res = await fetch(url, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(bodyData) });
                const data = await res.json();
                if (data.success) {
                    Swal.fire({ title: 'สำเร็จ!', text: 'ลบข้อมูลเรียบร้อยแล้ว', icon: 'success', customClass: { popup: 'rounded-3xl' } });
                    loadHistory(currentType);
                } else {
                    Swal.fire('ข้อผิดพลาด', data.error || 'ลบข้อมูลไม่สำเร็จ', 'error');
                }
            } catch(e) {
                Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้', 'error');
            }
        }
    });
};

// 🌟 ฟังก์ชันล้างประวัติทั้งหมด (Clear All)
window.clearHistory = async function() {
    // Determine context based on current tab
    const tabNames = {
        'checkin': 'เช็คอิน',
        'start_day': 'ค่าแรกเข้า',
        'oil': 'เติมน้ำมัน',
        'inventory': 'คลังสินค้า',
        'job_close': 'ปิดงาน'
    };
    const tName = tabNames[currentType];
    
    // Check if filtering by Date/Month
    const fDate = document.getElementById('filterDate') ? document.getElementById('filterDate').value : '';
    const fMonth = document.getElementById('filterMonth') ? document.getElementById('filterMonth').value : '';
    let scopeText = "ทั้งหมดในระบบ";
    if (fDate) scopeText = `เฉพาะวันที่ ${fDate}`;
    else if (fMonth) scopeText = `เฉพาะเดือน ${fMonth}`;

    Swal.fire({
        title: `ล้างประวัติ${tName}?`,
        html: `คุณกำลังจะลบประวัติ <b>${tName}</b> <br><span class="text-rose-500 font-bold">${scopeText}</span><br>ข้อมูลที่ถูกลบจะไม่สามารถกู้คืนได้ และอาจมีการคืนค่ายอดต่างๆ กลับสู่ระบบ!`,
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ใช่, ล้างประวัติถาวร',
        cancelButtonText: 'ยกเลิก',
        customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md', cancelButton: 'rounded-xl px-6 py-2.5 font-bold' }
    }).then(async (result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'กำลังดำเนินการ...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
            try {
                const res = await fetch('api/history/clear_all.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ type: currentType, date: fDate, month: fMonth })
                });
                const data = await res.json();
                if (data.success) {
                    Swal.fire({ title: 'ล้างข้อมูลสำเร็จ!', text: `ลบข้อมูล ${data.deleted_count} รายการเรียบร้อยแล้ว`, icon: 'success', customClass: { popup: 'rounded-3xl' } });
                    loadHistory(currentType);
                } else {
                    Swal.fire('ข้อผิดพลาด', data.error || 'ล้างประวัติไม่สำเร็จ', 'error');
                }
            } catch(e) {
                Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้', 'error');
            }
        }
    });
};

// 🌟 ฟังก์ชันลบข้อมูลค่าแรกเข้า (ยิง API ตัวเดียวกับหน้าส่วนตัว)
window.deleteStartDayRecordGlobal = async function(id) {

// ==========================================
// 🌟 ระบบแก้ไขข้อมูล (รองรับทุกสิทธิ์)
// ==========================================
window.startDayRecords = []; // เก็บตัวแปรส่วนกลาง
};

window.openEditStartDayModal = function(id) {
    const record = window.startDayRecords.find(r => r.id == id);
    if(!record) return;

    document.getElementById('edit_sd_id').value = record.id;
    document.getElementById('edit_sd_customer').value = record.customer_name;
    document.getElementById('edit_sd_non').value = record.non_number;
    document.getElementById('edit_sd_fee').value = record.has_initial_fee;
    document.getElementById('edit_sd_images').value = '';

    const previewContainer = document.getElementById('editImagePreviewContainer');
    if (previewContainer) previewContainer.innerHTML = '';

    const role = window.USER_ROLE;
    const canEditAll = (role === 'admin' || role === 'super_admin');
    
    const customerInput = document.getElementById('edit_sd_customer');
    const nonInput = document.getElementById('edit_sd_non');
    const feeInput = document.getElementById('edit_sd_fee');

    // ถ้าไม่ใช่แอดมิน ให้ล็อกการแก้ไขข้อความ (ช่างแก้ได้แค่รูป)
    if (!canEditAll) {
        customerInput.readOnly = true;
        nonInput.readOnly = true;
        customerInput.classList.add('bg-slate-100', 'text-slate-400');
        nonInput.classList.add('bg-slate-100', 'text-slate-400');
        feeInput.disabled = true;
        feeInput.classList.add('bg-slate-100', 'text-slate-400');
    } else {
        customerInput.readOnly = false;
        nonInput.readOnly = false;
        customerInput.classList.remove('bg-slate-100', 'text-slate-400');
        nonInput.classList.remove('bg-slate-100', 'text-slate-400');
        feeInput.disabled = false;
        feeInput.classList.remove('bg-slate-100', 'text-slate-400');
    }

    document.getElementById('editStartDayModal').classList.remove('hidden');
};

window.closeEditStartDayModal = function() {
    document.getElementById('editStartDayModal').classList.add('hidden');
};

document.getElementById('edit_sd_images')?.addEventListener('change', function(e) {
    const files = Array.from(e.target.files);
    const previewContainer = document.getElementById('editImagePreviewContainer');
    if (!previewContainer) return;
    
    previewContainer.innerHTML = '';
    
    if (files.length > 10) {
        Swal.fire('แจ้งเตือน', 'อัปโหลดได้สูงสุด 10 รูป', 'warning');
        this.value = '';
        return;
    }

    files.forEach((file) => {
        if (!file.type.startsWith('image/')) return;
        
        const reader = new FileReader();
        reader.onload = (e) => {
            const div = document.createElement('div');
            div.className = 'relative group rounded-lg overflow-hidden border border-gray-200 aspect-square shadow-sm';
            div.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
            previewContainer.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
});

// ย่อรูปภาพก่อนส่ง
window.compressImageGlobal = async function(file) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = (event) => {
            const img = new Image();
            img.onload = () => {
                let canvas = document.createElement('canvas');
                let ctx = canvas.getContext('2d');
                const ratio = Math.min(1, 1280 / Math.max(img.width, img.height));
                canvas.width = Math.round(img.width * ratio);
                canvas.height = Math.round(img.height * ratio);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                canvas.toBlob(blob => {
                    resolve(new File([blob], file.name.replace(/\.[^/.]+$/, '') + '.jpg', { type: 'image/jpeg' }));
                }, 'image/jpeg', 0.75);
            };
            img.src = event.target.result;
        };
        reader.readAsDataURL(file);
    });
};

document.getElementById('editStartDayForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const role = window.USER_ROLE;
    const canEditAll = (role === 'admin' || role === 'super_admin');
    const nonVal = document.getElementById('edit_sd_non').value.trim();
    
    if (canEditAll && nonVal.length !== 10) {
        return Swal.fire('แจ้งเตือน', `เลข Non ต้องมี 10 ตัวพอดี (คุณกรอกมา ${nonVal.length} ตัว)`, 'warning');
    }

    const formData = new FormData(this);
    // ถอดข้อมูลข้อความออกถ้าเป็นแค่ช่าง (กันแฮกยิง API เข้ามา)
    if (!canEditAll) {
        formData.delete('customer_name');
        formData.delete('non_number');
        formData.delete('has_initial_fee');
    }

    const fileInput = document.getElementById('edit_sd_images');
    if (fileInput.files.length > 0) {
        Swal.fire({ title: 'กำลังบีบอัดรูปภาพ...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        formData.delete('start_day_images[]');
        for (let i = 0; i < fileInput.files.length; i++) {
            let file = fileInput.files[i];
            if (file.size > 500 * 1024) file = await window.compressImageGlobal(file);
            formData.append('start_day_images[]', file);
        }
    }

    Swal.fire({ title: 'กำลังบันทึกข้อมูล...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
    try {
        const res = await fetch('api/start_day/edit.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            Swal.fire('สำเร็จ', 'บันทึกการแก้ไขเรียบร้อย', 'success');
            closeEditStartDayModal();
            // เช็คว่าอยู่หน้าไหน แล้วโหลดตารางนั้นใหม่
            if (typeof applyFilter === 'function' && document.getElementById('filterDate')) applyFilter();
            else if (typeof loadHistory === 'function') loadHistory();
        } else {
            Swal.fire('ข้อผิดพลาด', data.error, 'error');
        }
    } catch (err) {
        Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อเซิร์ฟเวอร์ล้มเหลว', 'error');
    }
});