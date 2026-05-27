// assets/js/oil_report.js

let costChartInstance = null;
let litersChartInstance = null;
let allRecords = [];
let oilExcelPayload = [];

// Convert Excel serial date (number) to JS Date.
// Excel uses 1900 date system by default in many files; this converts serial to UTC Date.
function excelDateToJSDate(serial) {
    // Protect against unexpected values
    serial = Number(serial);
    if (Number.isNaN(serial)) return new Date(NaN);
    // Excel epoch: 1899-12-31 -> serial 1 is 1900-01-01, but Excel wrongly treats 1900 as leap year.
    // Using common conversion: (serial - 25569) days since Unix epoch
    const milliseconds = (serial - 25569) * 86400 * 1000;
    return new Date(milliseconds);
}

document.addEventListener('DOMContentLoaded', () => {
    // Set default dates (Current month)
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);

    document.getElementById('start_date').value = firstDay.toISOString().split('T')[0];
    document.getElementById('end_date').value = today.toISOString().split('T')[0];

    // Load initial data
    fetchData();

    // Bind Filter button
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

    oilExcelImport?.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        Toast.info('กำลังอ่านข้อมูลจากไฟล์ Excel...');

        try {
            const data = await file.arrayBuffer();
            const workbook = XLSX.read(new Uint8Array(data), { type: 'array' });
            const worksheet = workbook.Sheets[workbook.SheetNames[0]];
            const rows = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

            oilExcelPayload = [];
            let skipped = 0;
            const headerRow = rows[0] ? rows[0].map(h => String(h || '').toLowerCase().replace(/\s/g, '')) : [];
            const plateI = headerRow.findIndex(h => h.includes('license') || h.includes('plate') || h.includes('ป้าย'));
            const litersI = headerRow.findIndex(h => h.includes('liters') || h.includes('ลิตร'));
            const mileageI = headerRow.findIndex(h => h.includes('mileage') || h.includes('ไมล์') || h.includes('เลขไมล์'));
            const priceI = headerRow.findIndex(h => h.includes('price') || h.includes('ราคา'));
            const dateI = headerRow.findIndex(h => h.includes('date') || h.includes('วันที่') || h.includes('time'));

            const fallbackPlate = plateI === -1 ? 0 : plateI;
            const fallbackLiters = litersI === -1 ? 1 : litersI;
            const fallbackMileage = mileageI === -1 ? 2 : mileageI;
            const fallbackPrice = priceI === -1 ? 3 : priceI;
            const fallbackDate = dateI === -1 ? 4 : dateI;

            rows.forEach((row, index) => {
                if (index === 0) return;
                const license_plate = String(row[plateI !== -1 ? plateI : fallbackPlate] || '').trim();
                const liters = parseFloat(row[litersI !== -1 ? litersI : fallbackLiters]) || 0;
                const mileage = parseInt(row[mileageI !== -1 ? mileageI : fallbackMileage]) || 0;
                const price_per_liter = parseFloat(row[priceI !== -1 ? priceI : fallbackPrice]) || 0;
                const rawDateVal = row[dateI !== -1 ? dateI : fallbackDate];
                let date_recorded = null;
                if (rawDateVal !== undefined && rawDateVal !== null && String(rawDateVal).trim() !== '') {
                    // If cell is numeric (Excel serial), convert to JS Date
                    const isNumeric = typeof rawDateVal === 'number' || /^\d+(?:\.\d+)?$/.test(String(rawDateVal).trim());
                    if (isNumeric) {
                        const serial = Number(rawDateVal);
                        try {
                            const d = excelDateToJSDate(serial);
                            if (!Number.isNaN(d.getTime())) {
                                date_recorded = d.toISOString().slice(0, 19).replace('T', ' ');
                            }
                        } catch (err) {
                            console.warn('Excel date conversion failed for', rawDateVal, err);
                        }
                    } else {
                        const ts = Date.parse(String(rawDateVal).trim());
                        if (!Number.isNaN(ts)) {
                            date_recorded = new Date(ts).toISOString().slice(0, 19).replace('T', ' ');
                        }
                    }
                }

                if (!license_plate || liters <= 0 || mileage < 0 || price_per_liter <= 0) {
                    skipped++;
                    return;
                }

                oilExcelPayload.push({
                    license_plate,
                    liters,
                    mileage,
                    price_per_liter,
                    date_recorded
                });
            });

            const preview = document.getElementById('oilExcelPreview');
            const countEl = document.getElementById('oilExcelCount');

            if (oilExcelPayload.length > 0) {
                countEl.textContent = `เตรียมข้อมูลได้ ${oilExcelPayload.length} รายการ (ข้าม ${skipped} แถว)`;
                preview.classList.remove('hidden');
                oilConfirmExcelBtn?.classList.remove('hidden');
                Toast.success('อ่านไฟล์ Excel สำเร็จ! กรุณากดยืนยันเพื่อนำเข้า');
            } else {
                preview.classList.add('hidden');
                oilConfirmExcelBtn?.classList.add('hidden');
                Toast.error('ไม่พบข้อมูลที่ถูกต้องในไฟล์ Excel');
            }
        } catch (error) {
            console.error('Excel parse error:', error);
            Toast.error('ไม่สามารถอ่านไฟล์ Excel ได้');
        }
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
    const columnCount = window.IS_ADMIN ? 10 : 9;

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
    // Animate numbers if possible, or just set text
    document.getElementById('stat_total_cost').textContent = stats.total_cost.toLocaleString('th-TH', {minimumFractionDigits: 2});
    document.getElementById('stat_total_liters').textContent = stats.total_liters.toLocaleString('th-TH', {minimumFractionDigits: 2});
    document.getElementById('stat_total_records').textContent = stats.total_records.toLocaleString('th-TH');
    document.getElementById('stat_total_jobs').textContent = stats.total_jobs ? stats.total_jobs.toLocaleString('th-TH') : '0';
}

function renderCharts(chartData) {
    const labels = chartData.map(item => item.record_date);
    const costs = chartData.map(item => parseFloat(item.daily_cost));
    const liters = chartData.map(item => parseFloat(item.daily_liters));

    // 1. Cost Bar Chart
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

    // 2. Liters Line Chart
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
        const columnCount = window.IS_ADMIN ? 10 : 9;
        tbody.innerHTML = `<tr><td colspan="${columnCount}" class="px-6 py-12 text-center text-slate-400">ไม่พบข้อมูลในช่วงเวลาที่เลือก</td></tr>`;
        return;
    }

    records.forEach((row, index) => {
        const dateObj = new Date(row.date_recorded);
        const formattedDate = dateObj.toLocaleDateString('th-TH') + ' ' + dateObj.toLocaleTimeString('th-TH', {hour: '2-digit', minute:'2-digit'});

        const hasImages = row.images ? true : false;
        const teamBadge = row.team_name 
            ? `<span class="bg-amber-50 text-amber-700 border border-amber-200 px-3 py-1 rounded-lg text-xs font-bold">🚗 ${row.team_name}</span>` 
            : `<span class="bg-slate-100 text-slate-800 border border-slate-200 px-3 py-1 rounded-lg text-xs font-bold">${row.license_plate}</span>`;
        const jobCount = row.team_job_count || 0;

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition-colors animate__animated animate__fadeIn';
        tr.style.animationDelay = `${index * 0.05}s`;
        tr.innerHTML = `
            <td class="px-6 py-4">${formattedDate}</td>
            <td class="px-6 py-4 font-medium text-slate-800">${row.tech_name}</td>
            <td class="px-6 py-4">${teamBadge}</td>
            <td class="px-6 py-4 text-center"><span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1 rounded-lg text-xs font-black">📋 ${jobCount}</span></td>
            <td class="px-6 py-4 text-right">${parseInt(row.mileage).toLocaleString('th-TH')}</td>
            <td class="px-6 py-4 text-right">${parseFloat(row.liters).toFixed(2)}</td>
            <td class="px-6 py-4 text-right font-mono">฿${parseFloat(row.price_per_liter).toFixed(2)}</td>
            <td class="px-6 py-4 text-right font-bold text-indigo-600">฿${parseFloat(row.total_price).toLocaleString('th-TH', {minimumFractionDigits:2})}</td>
            <td class="px-6 py-4 text-center">
                ${hasImages ?
                  `<button onclick="viewImages(${index})" class="text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-4 py-1.5 rounded-full text-xs font-bold transition-all hover:shadow-sm">📷 ดูรูปภาพ</button>` :
                  `<span class="text-slate-300 text-xs italic">ไม่มีหลักฐาน</span>`
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

// Close modal on outside click
document.getElementById('imageModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeImageModal();
    }
});

// Delete helpers (admin only)
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
            if (data.log) console.info('Log:', data.log);
        }
    } catch (err) {
        console.error('Delete error', err);
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
        const res = await fetch('api/oil/delete_all.php', {
            method: 'POST'
        });
        const data = await res.json();
        if (data.success) {
            Toast.success(data.message || 'ลบข้อมูลทั้งหมดเรียบร้อยแล้ว');
            fetchData();
        } else {
            Toast.error(data.error || 'ไม่สามารถลบข้อมูลทั้งหมดได้');
            if (data.log) console.info('Log:', data.log);
        }
    } catch (err) {
        console.error('Delete all error', err);
        Toast.error('การเชื่อมต่อล้มเหลว');
    } finally {
        Loader.hide();
    }
}