// assets/js/oil_report.js

let costChartInstance = null;
let litersChartInstance = null;
let allRecords = [];

// ตัวแปรเก็บรายชื่อ Dropdown สำหรับการแก้ไข
let editUsersList = [];
let editTeamsList = [];

document.addEventListener('DOMContentLoaded', () => {
    // Set default dates (Current month)
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);

    document.getElementById('start_date').value = firstDay.toISOString().split('T')[0];
    document.getElementById('end_date').value = today.toISOString().split('T')[0];

    // Load initial data
    fetchData();
    // โหลดรายชื่อผู้ใช้และทะเบียนรถรอไว้ก่อนเลย
    loadEditOptions();

    // Bind Filter button
    document.getElementById('filterBtn').addEventListener('click', () => {
        Toast.info('กำลังอัปเดตข้อมูลตามวันที่เลือก...');
        fetchData();
    });
});

async function loadEditOptions() {
    try {
        // ดึงรายชื่อช่าง
        const resU = await fetch('api/inventory/get_outbound_targets.php');
        const dataU = await resU.json();
        if(dataU.success) editUsersList = dataU.users;

        // ดึงรายชื่อทะเบียนรถ
        const resT = await fetch('api/oil/get_team_plates.php');
        const dataT = await resT.json();
        if(dataT.success) editTeamsList = dataT.data;
    } catch(e) { console.error('Failed to load edit options', e); }
}

async function fetchData() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;

    document.getElementById('oilTableBody').innerHTML = '<tr><td colspan="9" class="px-6 py-12 text-center text-slate-400"><div class="flex flex-col items-center justify-center"><div class="loader-spinner mb-4 w-8 h-8"></div> กำลังโหลดข้อมูลรายงาน...</div></td></tr>';

    try {
        const response = await fetch(`api/oil/get_records.php?start_date=${startDate}&end_date=${endDate}`);    
        const data = await response.json();

        if (data.success) {
            updateStats(data.stats);
            renderCharts(data.chart);
            renderTable(data.records);
            allRecords = data.records;
            if (data.records.length > 0) {
                Toast.success(`โหลดข้อมูลสำเร็จ พบทั้งหมด ${data.records.length} รายการ`);
            }
        } else {
            Toast.error(`เกิดข้อผิดพลาด: ${data.error}`);
            document.getElementById('oilTableBody').innerHTML = `<tr><td colspan="9" class="px-6 py-4 text-center text-rose-500 font-bold">ไม่สามารถดึงข้อมูลได้: ${data.error}</td></tr>`;
        }
    } catch (error) {
        console.error("Error fetching data:", error);
        Toast.error('ไม่สามารถเชื่อมต่อกับฐานข้อมูลได้');
        document.getElementById('oilTableBody').innerHTML = `<tr><td colspan="9" class="px-6 py-4 text-center text-rose-500 font-bold">ไม่สามารถโหลดข้อมูลได้ กรุณาตรวจสอบคอนโซล</td></tr>`;
    }
}

function updateStats(stats) {
    document.getElementById('stat_total_cost').textContent = stats.total_cost.toLocaleString('th-TH', {minimumFractionDigits: 2});
    document.getElementById('stat_total_liters').textContent = stats.total_liters.toLocaleString('th-TH', {minimumFractionDigits: 2});
    document.getElementById('stat_total_records').textContent = stats.total_records.toLocaleString('th-TH');
    document.getElementById('stat_total_jobs').textContent = stats.total_jobs ? stats.total_jobs.toLocaleString('th-TH') : '0';
}

function renderCharts(chartData) {
    const labels = chartData.map(item => item.record_date);
    const costs = chartData.map(item => parseFloat(item.daily_cost));
    const liters = chartData.map(item => parseFloat(item.daily_liters));

    const ctxCost = document.getElementById('costChart').getContext('2d');
    if (costChartInstance) costChartInstance.destroy();
    costChartInstance = new Chart(ctxCost, {
        type: 'bar',
        data: { labels, datasets: [{ label: 'ค่าใช้จ่าย (บาท)', data: costs, backgroundColor: 'rgba(99, 102, 241, 0.7)', borderColor: 'rgb(99, 102, 241)', borderWidth: 1, borderRadius: 6 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } } }
    });

    const ctxLiters = document.getElementById('litersChart').getContext('2d');
    if (litersChartInstance) litersChartInstance.destroy();
    litersChartInstance = new Chart(ctxLiters, {
        type: 'line',
        data: { labels, datasets: [{ label: 'ปริมาณน้ำมัน (ลิตร)', data: liters, backgroundColor: 'rgba(16, 185, 129, 0.1)', borderColor: '#10b981', borderWidth: 3, tension: 0.4, fill: true, pointBackgroundColor: '#10b981', pointRadius: 4 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } } }
    });
}

function renderTable(records) {
    const tbody = document.getElementById('oilTableBody');
    tbody.innerHTML = '';

    if (records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="px-6 py-12 text-center text-slate-400">ไม่พบข้อมูลในช่วงเวลาที่เลือก</td></tr>';
        return;
    }

    records.forEach((row, index) => {
        const dateObj = new Date(row.date_recorded);
        const formattedDate = dateObj.toLocaleDateString('th-TH') + ' ' + dateObj.toLocaleTimeString('th-TH', {hour: '2-digit', minute:'2-digit'});

        const hasImages = row.images ? true : false;
        const teamBadge = row.team_name 
            ? `<span class="bg-amber-50 text-amber-700 border border-amber-200 px-3 py-1 rounded-lg text-xs font-bold">🚗 ${row.team_name}</span>` 
            : `<span class="bg-slate-100 text-slate-800 border border-slate-200 px-3 py-1 rounded-lg text-xs font-bold">${row.license_plate}</span>`;

        const fillerLine = row.filler_name ? `<p class="text-[10px] text-slate-400 mt-1">ผู้บันทึก: ${row.filler_name}</p>` : '';
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition-colors animate__animated animate__fadeIn';
        tr.style.animationDelay = `${index * 0.03}s`;
        tr.innerHTML = `
            <td class="px-4 py-4 whitespace-nowrap">${formattedDate}</td>
            <td class="px-4 py-4 font-medium text-slate-800">${row.tech_name}${fillerLine}</td>
            <td class="px-4 py-4">${teamBadge}</td>
            <td class="px-4 py-4 text-center font-bold text-sky-600">${row.distance} กม.</td>
            <td class="px-4 py-4 text-center"><span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1 rounded-lg text-xs font-black">📋 ${row.job_count} งาน</span></td>
            
            <td class="px-4 py-4 text-right">
                <span class="text-xs text-slate-400 block mb-0.5">กม. ละ</span>
                <span class="font-bold text-rose-500">฿${row.cost_per_km.toLocaleString('th-TH', {minimumFractionDigits:2})}</span>
            </td>
            <td class="px-4 py-4 text-right">
                <span class="text-xs text-slate-400 block mb-0.5">งาน ละ</span>
                <span class="font-bold text-indigo-500">฿${row.cost_per_job.toLocaleString('th-TH', {minimumFractionDigits:2})}</span>
            </td>
            
            <td class="px-4 py-4 text-right font-bold text-indigo-700 text-base">฿${parseFloat(row.total_price).toLocaleString('th-TH', {minimumFractionDigits:2})}</td>
            <td class="px-4 py-4 text-center whitespace-nowrap">
                ${hasImages ?
                  `<button onclick="viewImages(${index})" class="text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-2.5 py-1.5 rounded-xl text-xs font-bold transition-all hover:shadow-sm">📷</button>` :
                  `<span class="text-slate-300 text-xs italic mr-2">-</span>`
                }
                <button onclick="openEditOilModal(${index})" class="text-amber-600 hover:text-amber-800 bg-amber-50 px-2.5 py-1.5 rounded-xl text-xs font-bold transition-all hover:shadow-sm ml-1">✏️</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

// ----------------------------------------------------
// จัดการ Modal รูปภาพ
// ----------------------------------------------------
window.viewImages = function(recordIndex) {
    const record = allRecords[recordIndex];
    if (!record || !record.images) return;

    const images = record.images.split(',');
    const grid = document.getElementById('modalImageGrid');
    grid.innerHTML = '';

    images.forEach(img => {
        const url = `assets/uploads/oil_receipts/${img}`;
        grid.innerHTML += `
            <div class="rounded-xl overflow-hidden border border-slate-200 bg-slate-50 flex items-center justify-center aspect-square shadow-sm hover:shadow-md transition-shadow">
                <a href="${url}" target="_blank" title="คลิกเพื่อดูรูปขนาดเต็ม">
                    <img src="${url}" class="w-full h-full object-contain hover:scale-105 transition-transform">
                </a>
            </div>
        `;
    });

    const modal = document.getElementById('imageModal');
    modal.classList.remove('hidden');
};

window.closeImageModal = function() { document.getElementById('imageModal').classList.add('hidden'); };

// ----------------------------------------------------
// ระบบแก้ไขข้อมูลผู้เติม / ทะเบียนรถ
// ----------------------------------------------------
window.openEditOilModal = function(index) {
    const record = allRecords[index];
    if (!record) return;

    // เติมข้อมูลลง Dropdown
    const selTech = document.getElementById('edit_tech_id');
    const selPlate = document.getElementById('edit_license_plate');
    
    selTech.innerHTML = '<option value="">-- เลือกผู้เติมน้ำมัน --</option>';
    editUsersList.forEach(u => selTech.innerHTML += `<option value="${u.id}">${u.full_name}</option>`);
    
    selPlate.innerHTML = '<option value="">-- เลือกทะเบียนรถ --</option>';
    editTeamsList.forEach(t => selPlate.innerHTML += `<option value="${t.team_name}">${t.team_name}</option>`);

    // Set ค่าปัจจุบัน
    document.getElementById('edit_record_id').value = record.id;
    selTech.value = record.tech_id;
    selPlate.value = record.license_plate;

    const modal = document.getElementById('editOilModal');
    modal.classList.remove('hidden');
};

window.closeEditOilModal = function() { document.getElementById('editOilModal').classList.add('hidden'); };

window.saveEditOil = async function() {
    const id = document.getElementById('edit_record_id').value;
    const tech_id = document.getElementById('edit_tech_id').value;
    const license_plate = document.getElementById('edit_license_plate').value;

    if (!tech_id || !license_plate) return Toast.error('กรุณาเลือกข้อมูลให้ครบถ้วน');

    Loader.show();
    try {
        const res = await fetch('api/oil/edit_record.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id, tech_id, license_plate })
        });
        const data = await res.json();
        if(data.success) {
            Toast.success('แก้ไขข้อมูลผู้เติมและทะเบียนเรียบร้อย');
            closeEditOilModal();
            fetchData(); // รีเฟรชตารางใหม่
        } else { Toast.error('เกิดข้อผิดพลาด: ' + data.error); }
    } catch(e) { Toast.error('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้'); } finally { Loader.hide(); }
};

// ----------------------------------------------------
// ระบบคำนวณและ Export ออกเป็น Excel
// ----------------------------------------------------
window.exportOilExcel = function() {
    if (allRecords.length === 0) return Toast.warning('ไม่มีข้อมูลสำหรับส่งออก');

    Toast.info('กำลังเตรียมไฟล์ Excel ต้นทุนรายรอบ...');

    // เรียงกลับจากเก่าไปใหม่ เพื่อให้ข้อมูลใน Excel อ่านง่าย
    let sortedRecords = [...allRecords].sort((a, b) => new Date(a.date_recorded) - new Date(b.date_recorded));
    let exportData = [];

    sortedRecords.forEach(row => {
        const d = new Date(row.date_recorded);
        const dateStr = d.toLocaleDateString('en-GB') + ' ' + d.toLocaleTimeString('en-GB', {hour: '2-digit', minute:'2-digit'});

        exportData.push({
            "วันที่": dateStr,
            "ทะเบียนรถ (ทีม)": row.team_name || row.license_plate,
            "ชื่อผู้เติม": row.tech_name,
            "เลขไมล์": parseInt(row.mileage),
            "ลิตร": Number(row.liters),
            "ยอดเงิน(บาท)": Number(row.total_price),
            "ระยะทางวิ่ง(กม.)": row.distance,
            "จำนวนเคสงาน(รอบ)": row.job_count,
            "ต้นทุน/กม.(บาท)": row.cost_per_km,
            "ต้นทุน/งาน(บาท)": row.cost_per_job,
            "_timestamp": d.getTime()
        });
    });

    exportData.sort((a, b) => a._timestamp - b._timestamp);
    exportData.forEach(item => delete item._timestamp);

    const ws = XLSX.utils.json_to_sheet(exportData);
    
    ws['!cols'] = [
        {wch: 16}, {wch: 18}, {wch: 25}, {wch: 10}, 
        {wch: 10}, {wch: 15}, {wch: 15}, {wch: 15}, 
        {wch: 15}, {wch: 15}
    ];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "ต้นทุนค่าน้ำมัน");
    
    const startDate = document.getElementById('start_date').value;
    XLSX.writeFile(wb, `รายงานต้นทุนน้ำมัน_${startDate}.xlsx`);
    
    Toast.success('ดาวน์โหลดไฟล์ Excel เรียบร้อย!');
};
// ====================================================
// ระบบนำเข้าข้อมูลน้ำมัน (Import Excel)
// ====================================================
document.getElementById('importOilExcel')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    Swal.fire({
        title: 'กำลังอ่านไฟล์ Excel...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    const reader = new FileReader();
    reader.onload = async function(event) {
        try {
            const data = new Uint8Array(event.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const worksheet = workbook.Sheets[workbook.SheetNames[0]];
            const jsonData = XLSX.utils.sheet_to_json(worksheet, { defval: "" });

            if (jsonData.length === 0) {
                return Swal.fire('ข้อผิดพลาด', 'ไม่พบข้อมูลในไฟล์ Excel', 'error');
            }

            // แมปชื่อคอลัมน์จาก Excel ให้ตรงกับฐานข้อมูล (รองรับไฟล์ที่เกิดจาก Export ด้านบน)
            const payload = jsonData.map(row => {
                let dateVal = row['วันที่'] || row['Date'] || '';
                return {
                    date: formatExcelDate(dateVal),
                    license_plate: String(row['ทะเบียนรถ'] || '').trim(),
                    tech_name: String(row['ชื่อผู้เติม'] || '').trim(),
                    mileage: parseInt(row['เลขไมล์']) || 0,
                    liters: parseFloat(row['ลิตร']) || 0,
                    price_per_liter: parseFloat(row['ราคา/ลิตร']) || 0,
                    total_price: parseFloat(row['ยอดเงิน(บาท)']) || (parseFloat(row['ลิตร']) * parseFloat(row['ราคา/ลิตร'])) || 0
                };
            }).filter(item => item.license_plate !== '' && item.liters > 0);

            if (payload.length === 0) {
                return Swal.fire('ข้อผิดพลาด', 'รูปแบบคอลัมน์ไม่ถูกต้อง (ต้องมีคอลัมน์ ทะเบียนรถ และ ลิตร)', 'error');
            }

            // ส่งข้อมูลไปยัง Backend
            const res = await fetch('api/oil/import_records.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ records: payload })
            });
            
            const result = await res.json();
            
            if (result.success) {
                Swal.fire('สำเร็จ!', `นำเข้าข้อมูลน้ำมันสำเร็จ ${result.imported} รายการ`, 'success');
                fetchData(); // รีเฟรชตารางใหม่
            } else {
                Swal.fire('เกิดข้อผิดพลาด', result.error, 'error');
            }
        } catch (err) {
            Swal.fire('ข้อผิดพลาด', 'ไฟล์ Excel รูปแบบไม่ถูกต้อง', 'error');
            console.error(err);
        } finally {
            document.getElementById('importOilExcel').value = ''; // เคลียร์ช่อง input
        }
    };
    reader.readAsArrayBuffer(file);
});

// ฟังก์ชันแปลงวันที่จาก Excel ให้เป็นระบบหลังบ้านอ่านได้
function formatExcelDate(excelDate) {
    if (!excelDate) return null;
    
    // กรณีที่ Excel เป็นรูปแบบตัวเลข (Serial Date)
    if (typeof excelDate === 'number') {
        const date = new Date((excelDate - (25567 + 2)) * 86400 * 1000);
        return date.toISOString().split('T')[0] + ' 12:00:00';
    } 
    // กรณีเก็บเป็น Text
    else if (typeof excelDate === 'string') {
        let parts = excelDate.split(/[\/\- ]/);
        if (parts.length >= 3) {
            const day = parts[0].padStart(2, '0');
            const month = parts[1].padStart(2, '0');
            let year = parts[2];
            if (parseInt(year) > 2500) year = (parseInt(year) - 543).toString();
            
            let time = '12:00:00'; 
            if (excelDate.includes(':')) {
                const timeMatch = excelDate.match(/\d{2}:\d{2}(:\d{2})?/);
                if(timeMatch) time = timeMatch[0];
                if(time.length === 5) time += ':00'; // ทำให้เป็น HH:mm:ss
            }
            return `${year}-${month}-${day} ${time}`;
        }
    }
    return excelDate;
}