// assets/js/oil_report.js

let costChartInstance = null;
let litersChartInstance = null;
let allRecords = [];
let oilExcelPayload = [];

document.addEventListener('DOMContentLoaded', () => {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);

    document.getElementById('start_date').value = firstDay.toISOString().split('T')[0];
    document.getElementById('end_date').value = today.toISOString().split('T')[0];

    fetchData();

    document.getElementById('filterBtn').addEventListener('click', () => {
        Toast.info('กำลังอัปเดตข้อมูลตามวันที่เลือก...');
        fetchData();
    });
    
    const oilImportBtn = document.getElementById('oilImportBtn');
    const oilExcelImport = document.getElementById('oilExcelImport');
    const oilConfirmExcelBtn = document.getElementById('oilConfirmExcelBtn');

    if (oilImportBtn && oilExcelImport) {
        oilImportBtn.addEventListener('click', () => oilExcelImport.click());
    }

    const oilDeleteAllBtn = document.getElementById('oilDeleteAllBtn');
    oilDeleteAllBtn?.addEventListener('click', handleDeleteAllOilRecords);

    // ระบบอ่านไฟล์ Excel/CSV ที่เสถียรที่สุด
    oilExcelImport?.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;
        Toast.info('กำลังอ่านข้อมูลจากไฟล์...');

        const reader = new FileReader();
        reader.onload = function(evt) {
            try {
                const data = new Uint8Array(evt.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                const worksheet = workbook.Sheets[workbook.SheetNames[0]];
                
                // ดึงข้อมูลออกมาเป็น Array (raw: false จะช่วยแปลงรูปแบบวันที่ให้อ่านง่าย)
                const rows = XLSX.utils.sheet_to_json(worksheet, { header: 1, raw: false, defval: "" });

                oilExcelPayload = [];
                let skipped = 0;

                rows.forEach((row, index) => {
                    // ข้ามแถวที่ 1 (Header)
                    if (index === 0) return; 

                    // อิงตามคอลัมน์ A ถึง I เป๊ะๆ
                    const rawDateVal = String(row[0]).trim(); // A: วันที่
                    const license_plate = String(row[1]).trim(); // B: ทะเบียนรถ
                    const mileage = parseInt(String(row[2]).replace(/,/g, '')) || 0; // C: เลขไมล์ปัจจุบัน
                    const liters = parseFloat(String(row[3]).replace(/,/g, '')) || 0; // D: จำนวนน้ำมัน
                    const total_price = parseFloat(String(row[4]).replace(/,/g, '')) || 0; // E: ยอดเงิน
                    const distance = parseFloat(String(row[5]).replace(/,/g, '')) || 0; // F: ระยะทางที่วิ่ง
                    const baht_per_km = parseFloat(String(row[6]).replace(/,/g, '')) || 0; // G: บาท/กม.
                    const price_per_liter = parseFloat(String(row[7]).replace(/,/g, '')) || 0; // H: ราคา/ลิตร
                    const filler_name = String(row[8]).trim(); // I: ชื่อคนเติม

                    // จัดการรูปแบบวันที่ให้เป็นมาตรฐานเพื่อส่งเข้า Database
                    let date_recorded = null;
                    if (rawDateVal) {
                        if (/^\d{4}-\d{2}-\d{2}/.test(rawDateVal)) {
                            // ถ้ามาเป็น 2026-03-02
                            date_recorded = rawDateVal.substring(0, 10) + ' 12:00:00';
                        } else if (/^\d{1,2}\/\d{1,2}\/\d{4}/.test(rawDateVal)) {
                            // ถ้ามาเป็น DD/MM/YYYY หรือ MM/DD/YYYY
                            let parts = rawDateVal.split('/');
                            // สมมติฐานเป็น DD/MM/YYYY ตามการตั้งค่า Excel ไทย
                            date_recorded = `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')} 12:00:00`;
                        } else {
                            // ถ้าแปลกไปกว่านั้น ลองใช้ Date.parse ของ JS
                            const ts = Date.parse(rawDateVal);
                            if (!Number.isNaN(ts)) {
                                date_recorded = new Date(ts).toISOString().slice(0, 19).replace('T', ' ');
                            } else {
                                date_recorded = new Date().toISOString().slice(0, 19).replace('T', ' '); // Default วันนี้
                            }
                        }
                    }

                    // ป้องกันขยะจากแถวว่าง
                    if (!license_plate || mileage <= 0 || liters <= 0) {
                        skipped++;
                        return;
                    }

                    oilExcelPayload.push({
                        license_plate,
                        liters,
                        mileage,
                        price_per_liter,
                        total_price,
                        distance,
                        baht_per_km,
                        filler_name,
                        date_recorded
                    });
                });

                const preview = document.getElementById('oilExcelPreview');
                const countEl = document.getElementById('oilExcelCount');

                if (oilExcelPayload.length > 0) {
                    countEl.textContent = `ดึงข้อมูลตรงตามคอลัมน์ A-I ได้ ${oilExcelPayload.length} รายการ (ข้ามแถวว่าง ${skipped} แถว)`;
                    preview.classList.remove('hidden');
                    oilConfirmExcelBtn?.classList.remove('hidden');
                    Toast.success('อ่านไฟล์สำเร็จ! กรุณากดยืนยันเพื่อนำเข้า');
                } else {
                    preview.classList.add('hidden');
                    oilConfirmExcelBtn?.classList.add('hidden');
                    Toast.error('ไม่พบข้อมูลที่ถูกต้องในไฟล์ หรือคอลัมน์ไม่ตรง');
                }
            } catch (error) {
                console.error('File parse error:', error);
                Toast.error('ไม่สามารถอ่านไฟล์ได้ โปรดตรวจสอบรูปแบบไฟล์');
            }
        };
        // เริ่มอ่านไฟล์
        reader.readAsArrayBuffer(file);
    });

    oilConfirmExcelBtn?.addEventListener('click', async (e) => {
        if (oilExcelPayload.length === 0) return;
        Loader.show();
        e.target.disabled = true;
        try {
            const res = await fetch('api/oil/import_excel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ records: oilExcelPayload })
            });
            const data = await res.json();
            if (data.success) {
                Toast.success(`นำเข้า ${data.inserted} รายการสำเร็จ`);
                oilExcelPayload = [];
                document.getElementById('oilExcelPreview').classList.add('hidden');
                oilConfirmExcelBtn.classList.add('hidden');
                document.getElementById('oilExcelImport').value = '';
                fetchData();
            } else {
                Toast.error(data.error || 'ไม่สามารถนำเข้าไฟล์ได้');
            }
        } catch (error) {
            console.error('Import error:', error);
            Toast.error('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
        } finally {
            Loader.hide();
            e.target.disabled = false;
        }
    });
});

async function fetchData() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const columnCount = window.IS_ADMIN ? 11 : 10;

    document.getElementById('oilTableBody').innerHTML = `<tr><td colspan="${columnCount}" class="px-6 py-12 text-center text-slate-400"><div class="flex flex-col items-center justify-center"><div class="loader-spinner mb-4 w-8 h-8"></div> กำลังโหลดข้อมูลรายงาน...</div></td></tr>`;

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
            document.getElementById('oilTableBody').innerHTML = `<tr><td colspan="${columnCount}" class="px-6 py-4 text-center text-rose-500 font-bold">ไม่สามารถดึงข้อมูลได้: ${data.error}</td></tr>`;
        }
    } catch (error) {
        console.error("Error fetching data:", error);
        Toast.error('ไม่สามารถเชื่อมต่อกับฐานข้อมูลได้');
        document.getElementById('oilTableBody').innerHTML = `<tr><td colspan="${columnCount}" class="px-6 py-4 text-center text-rose-500 font-bold">ไม่สามารถโหลดข้อมูลได้ กรุณาตรวจสอบคอนโซล</td></tr>`;
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
        data: {
            labels: labels,
            datasets: [{
                label: 'ค่าใช้จ่าย (บาท)',
                data: costs,
                backgroundColor: 'rgba(99, 102, 241, 0.7)', 
                borderColor: 'rgb(99, 102, 241)',
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } }
        }
    });

    const ctxLiters = document.getElementById('litersChart').getContext('2d');
    if (litersChartInstance) litersChartInstance.destroy();
    litersChartInstance = new Chart(ctxLiters, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'ปริมาณน้ำมัน (ลิตร)',
                data: liters,
                backgroundColor: 'rgba(16, 185, 129, 0.1)', 
                borderColor: '#10b981',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#10b981',
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, grid: { color: '#f1f5f9' } }, x: { grid: { display: false } } }
        }
    });
}

function renderTable(records) {
    const tbody = document.getElementById('oilTableBody');
    tbody.innerHTML = '';

    if (records.length === 0) {
        const columnCount = window.IS_ADMIN ? 11 : 10;
        tbody.innerHTML = `<tr><td colspan="${columnCount}" class="px-6 py-12 text-center text-slate-400">ไม่พบข้อมูลในช่วงเวลาที่เลือก</td></tr>`;
        return;
    }

    records.forEach((row, index) => {
        const dateObj = new Date(row.date_recorded);
        
        const day = String(dateObj.getDate()).padStart(2, '0');
        const month = String(dateObj.getMonth() + 1).padStart(2, '0');
        const year = dateObj.getFullYear();
        const formattedDate = `${day}/${month}/${year}`;

        const hasImages = row.images ? true : false;
        
        const teamBadge = row.team_name 
            ? `<span class="bg-amber-50 text-amber-700 border border-amber-200 px-3 py-1 rounded-lg text-xs font-bold">🚗 ${row.team_name}</span>` 
            : `<span class="bg-slate-100 text-slate-800 border border-slate-200 px-3 py-1 rounded-lg text-xs font-bold">${row.license_plate}</span>`;
            
        // ดึงจากค่าที่อ่านได้จาก Excel ตรงๆ
        const distance = parseFloat(row.distance) || 0;
        const bahtPerKm = parseFloat(row.baht_per_km) || 0;
        const techName = row.filler_name ? row.filler_name : row.tech_name;

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition-colors animate__animated animate__fadeIn';
        tr.style.animationDelay = `${index * 0.05}s`;
        
        tr.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap">${formattedDate}</td>
            <td class="px-6 py-4">${teamBadge}</td>
            <td class="px-6 py-4 text-right">${parseInt(row.mileage).toLocaleString('th-TH')}</td>
            <td class="px-6 py-4 text-right">${parseFloat(row.liters).toFixed(2)}</td>
            <td class="px-6 py-4 text-right font-bold text-indigo-600">฿${parseFloat(row.total_price).toLocaleString('th-TH', {minimumFractionDigits:2})}</td>
            <td class="px-6 py-4 text-right">${distance.toLocaleString('th-TH')}</td>
            <td class="px-6 py-4 text-right">${bahtPerKm.toFixed(2)}</td>
            <td class="px-6 py-4 text-right font-mono">฿${parseFloat(row.price_per_liter).toFixed(2)}</td>
            <td class="px-6 py-4 font-medium text-slate-800 text-center">${techName}</td>
            <td class="px-6 py-4 text-center">
                ${hasImages ?
                  `<button onclick="viewImages(${index})" class="text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-4 py-1.5 rounded-full text-xs font-bold transition-all hover:shadow-sm">📷 รูป</button>` :
                  `<span class="text-slate-300 text-[10px] italic">ไม่มีหลักฐาน</span>`
                }
            </td>
            ${window.IS_ADMIN ? `
            <td class="px-6 py-4 text-center">
                <button onclick="confirmDelete(${row.id})" class="bg-rose-600 hover:bg-rose-700 text-white px-3 py-1 rounded-lg text-xs">ลบ</button>
            </td>
            ` : ''}
        `;
        tbody.appendChild(tr);
    });
}

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
                    <img src="${url}" class="w-full h-full object-contain hover:scale-105 transition-transform" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\\\'http://www.w3.org/2000/svg\\\' fill=\\\'none\\\' viewBox=\\\'0 0 24 24\\\' stroke=\\\'%23ccc\\\'><path stroke-linecap=\\\'round\\\' stroke-linejoin=\\\'round\\\' stroke-width=\\\'2\\\' d=\\\'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z\\\'/></svg>'">
                </a>
            </div>
        `;
    });

    const modal = document.getElementById('imageModal');
    modal.classList.remove('hidden');
    modal.querySelector('div').classList.add('animate__animated', 'animate__zoomIn');
};

window.closeImageModal = function() {
    const modal = document.getElementById('imageModal');
    modal.querySelector('div').classList.remove('animate__zoomIn');
    modal.querySelector('div').classList.add('animate__zoomOut');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.querySelector('div').classList.remove('animate__zoomOut');
    }, 300);
};

document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeImageModal();
    }
});

window.confirmDelete = function(id) {
    if (!window.IS_ADMIN) return Toast.error('ไม่มีสิทธิ์');
    if (!confirm('ยืนยันการลบรายการนี้? การกระทำนี้ไม่สามารถย้อนกลับได้')) return;
    deleteRecord(id);
}

async function deleteRecord(id) {
    try {
        Loader.show();
        const res = await fetch('api/oil/delete_record.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) {
            Toast.success(`ลบ ${data.deleted} รายการเรียบร้อย`);
            fetchData();
        } else {
            Toast.error(data.error || 'ไม่สามารถลบรายการได้');
        }
    } catch (err) {
        Toast.error('การเชื่อมต่อล้มเหลว');
    } finally {
        Loader.hide();
    }
}

async function handleDeleteAllOilRecords() {
    if (!window.IS_ADMIN) return Toast.error('ไม่มีสิทธิ์');
    const confirmation = prompt('พิมพ์ DELETE เพื่อยืนยันการลบข้อมูลน้ำมันทั้งหมด (ไม่สามารถกู้คืนได้):');
    if (confirmation !== 'DELETE') {
        Toast.error('คำยืนยันไม่ถูกต้อง');
        return;
    }

    try {
        Loader.show();
        const res = await fetch('api/oil/delete_all.php', { method: 'POST' });
        const data = await res.json();
        if (data.success) {
            Toast.success(data.message || 'ลบข้อมูลทั้งหมดเรียบร้อยแล้ว');
            fetchData();
        } else {
            Toast.error(data.error || 'ไม่สามารถลบข้อมูลทั้งหมดได้');
        }
    } catch (err) {
        Toast.error('การเชื่อมต่อล้มเหลว');
    } finally {
        Loader.hide();
    }
}