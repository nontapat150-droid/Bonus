// assets/js/oil_report.js

// 🚀 ระบบแจ้งเตือน (Toast) เล็กๆ มุมขวาบน สำหรับสถานะที่ไม่สำคัญมาก
const showToast = (icon, msg) => {
    if (typeof Swal !== 'undefined') {
        Swal.fire({ toast: true, position: 'top-end', icon: icon, title: msg, showConfirmButton: false, timer: 3000, timerProgressBar: true });
    } else alert(msg);
};

const showLoader = () => {
    if (typeof Swal !== 'undefined') Swal.fire({ title: 'กำลังประมวลผล...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
};

const hideLoader = () => {
    if (typeof Swal !== 'undefined') Swal.close();
};

let combinedTrendChartInstance = null;
let litersTrendChartInstance = null;
let distanceTrendChartInstance = null;
let efficiencyChartInstance = null;

let compareCostChartInstance = null;
let compareLitersChartInstance = null;
let compareDistanceChartInstance = null;
let compareJobsChartInstance = null;
let monthlyCompareChartInstance = null;

let allRecords = [];
let monthlyData = [];
let dailyData = []; 
let isCompareMode = false;

let editUsersList = [];
let editTeamsList = [];

document.addEventListener('DOMContentLoaded', () => {
    applyDatePreset('this_month');
    fetchData();
    loadEditOptions();

    document.getElementById('filterBtn')?.addEventListener('click', () => {
        showToast('info', 'กำลังอัปเดตข้อมูลตามวันที่และรถที่เลือก...');
        fetchData();
    });

    const calcManageTotal = () => {
        const liters = parseFloat(document.getElementById('manage_liters').value) || 0;
        const price = parseFloat(document.getElementById('manage_price_per_liter').value) || 0;
        const totalPriceEl = document.getElementById('manage_total_price');
        if (liters && price && totalPriceEl) {
            totalPriceEl.value = Math.round(liters * price);
        }
    };
    document.getElementById('manage_liters')?.addEventListener('input', calcManageTotal);
    document.getElementById('manage_price_per_liter')?.addEventListener('input', calcManageTotal);
});

window.applyDatePreset = function(preset) {
    const startInput = document.getElementById('start_date');
    const endInput = document.getElementById('end_date');
    const today = new Date();
    
    if (preset === 'this_month') {
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        startInput.value = firstDay.toISOString().split('T')[0];
        endInput.value = today.toISOString().split('T')[0];
    } else if (preset === 'last_month') {
        const firstDayLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
        const lastDayLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
        startInput.value = firstDayLastMonth.toISOString().split('T')[0];
        endInput.value = lastDayLastMonth.toISOString().split('T')[0];
    }
};

window.toggleCompareMode = function() {
    isCompareMode = !isCompareMode;
    const section = document.getElementById('compareSection');
    const btn = document.getElementById('compareBtn');
    const btnText = document.getElementById('compareBtnText');
    const selectorWrapper = document.getElementById('vehicleSelectorWrapper');
    
    if (isCompareMode) {
        if(section) section.classList.remove('hidden');
        if(selectorWrapper) selectorWrapper.classList.remove('hidden');
        if(btnText) btnText.textContent = 'ดูรายงานปกติ';
        if(btn) btn.classList.replace('bg-slate-800', 'bg-indigo-700');
        fillVehicleCompareSelector();
        renderComparisonCharts();
        renderMonthlyCompareChart();
    } else {
        if(section) section.classList.add('hidden');
        if(selectorWrapper) selectorWrapper.classList.add('hidden');
        if(btnText) btnText.textContent = 'เปรียบเทียบรถ';
        if(btn) btn.classList.replace('bg-indigo-700', 'bg-slate-800');
    }
    if(window.lucide) lucide.createIcons();
};

function fillVehicleCompareSelector() {
    const selector = document.getElementById('vehicleCompareSelector');
    if(!selector) return;
    const uniqueVehicles = [...new Set(allRecords.map(r => r.team_name || r.license_plate))].sort();
    if (selector.options.length > 0) return;
    selector.innerHTML = '';
    uniqueVehicles.forEach(v => {
        const opt = document.createElement('option');
        opt.value = v;
        opt.textContent = '🚗 ' + v;
        opt.selected = true; 
        selector.appendChild(opt);
    });
}

window.autoFillVehicle = function(techId) {
    const plateInput = document.getElementById('manage_license_plate');
    if(!plateInput) return;
    plateInput.value = ''; 
    if (!techId) return;
    const user = editUsersList.find(u => u.id == techId);
    if (user && user.team_name) {
        plateInput.value = user.team_name;
        showToast('info', `แสดงทะเบียนรถ ${user.team_name} สำหรับ ${user.full_name}`);
    }
};

window.updateChartType = function(chartId, type, datasetIndex = null) {
    let instance = null;
    if (chartId === 'combinedTrendChart') instance = combinedTrendChartInstance;
    else if (chartId === 'litersTrendChart') instance = litersTrendChartInstance;
    else if (chartId === 'distanceTrendChart') instance = distanceTrendChartInstance;
    else if (chartId === 'efficiencyChart') instance = efficiencyChartInstance;
    if (!instance) return;
    const chartType = type === 'area' ? 'line' : type;
    const fill = type === 'area';
    if (datasetIndex !== null) {
        instance.data.datasets[datasetIndex].type = chartType;
        instance.data.datasets[datasetIndex].fill = fill;
    } else {
        instance.config.type = chartType;
        instance.data.datasets.forEach(ds => ds.fill = fill);
    }
    instance.update();
};

async function loadEditOptions() {
    try {
        const resU = await fetch('api/inventory/get_outbound_targets.php');
        const dataU = await resU.json();
        if(dataU.success) editUsersList = dataU.users;
        
        const resT = await fetch('api/oil/get_team_plates.php');
        const dataT = await resT.json();
        if(dataT.success) {
            editTeamsList = dataT.data;
            if (isCompareMode) fillVehicleCompareSelector();
            
            const filterPlate = document.getElementById('filter_license_plate');
            if (filterPlate) {
                const currentVal = filterPlate.value; 
                filterPlate.innerHTML = '<option value="all">-- ทุกคัน --</option>';
                editTeamsList.forEach(t => {
                    filterPlate.innerHTML += `<option value="${t.team_name}">${t.team_name}</option>`;
                });
                if ([...filterPlate.options].some(o => o.value === currentVal)) {
                    filterPlate.value = currentVal;
                }
            }
        }
    } catch(e) { console.error('Failed to load edit options', e); }
}

async function fetchData(silent = false) {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const tbody = document.getElementById('oilTableBody');
    
    const filterPlate = document.getElementById('filter_license_plate');
    const licensePlate = filterPlate ? filterPlate.value : 'all';
    
    if(tbody && !silent) {
        tbody.innerHTML = '<tr><td colspan="11" class="px-6 py-12 text-center text-slate-400"><div class="flex flex-col items-center justify-center"><div class="loader-spinner mb-4 w-8 h-8"></div> กำลังโหลดข้อมูล...</div></td></tr>';
    }

    try {
        const response = await fetch(`api/oil/get_records.php?start_date=${startDate}&end_date=${endDate}&license_plate=${encodeURIComponent(licensePlate)}`);    
        const textData = await response.text(); 
        
        let data;
        try {
            data = JSON.parse(textData);
        } catch (err) {
            if(tbody) tbody.innerHTML = `<tr><td colspan="11" class="px-6 py-4 text-left text-rose-500 bg-rose-50 border border-rose-200"><b>พบข้อผิดพลาดจากฝั่งเซิร์ฟเวอร์ (PHP Error):</b><br><br><code>${textData.substring(0, 800).replace(/</g, "&lt;").replace(/>/g, "&gt;")}</code></td></tr>`;
            return;
        }

        if (data.success) {
            updateStats(data.stats);
            allRecords = data.records;
            monthlyData = data.monthly || [];
            dailyData = data.chart || [];
            
            if (isCompareMode) {
                const selector = document.getElementById('vehicleCompareSelector');
                if(selector) { selector.innerHTML = ''; fillVehicleCompareSelector(); }
            }

            renderAnalyticsCharts();
            if (isCompareMode) { renderComparisonCharts(); renderMonthlyCompareChart(); }
            renderTable(data.records);
            
            if (!silent) {
                if (data.records.length > 0) showToast('success', `โหลดข้อมูลสำเร็จ พบทั้งหมด ${data.records.length} รายการ`);
                else showToast('info', 'ไม่พบข้อมูลในช่วงเวลาและรถที่เลือก');
            }
        } else {
            if(!silent) showToast('error', `เกิดข้อผิดพลาด: ${data.error}`);
            if(tbody) tbody.innerHTML = `<tr><td colspan="11" class="px-6 py-4 text-center text-rose-500 font-bold">ไม่สามารถดึงข้อมูลได้: ${data.error}</td></tr>`;
        }
    } catch (error) {
        console.error("Error Detail:", error);
        if(tbody) tbody.innerHTML = `
            <tr>
                <td colspan="11" class="px-6 py-6 text-left text-rose-600 bg-rose-50 border border-rose-200">
                    <h3 class="font-black text-lg mb-2">🚨 พบข้อผิดพลาด (JS Error)</h3>
                    <pre class="text-xs whitespace-pre-wrap"><code>${error.stack || error.message}</code></pre>
                </td>
            </tr>`;
    }
}

function updateStats(stats) {
    const sc = document.getElementById('stat_total_cost');
    const sl = document.getElementById('stat_total_liters');
    const sr = document.getElementById('stat_total_records');
    const sj = document.getElementById('stat_total_jobs');
    if(sc) sc.textContent = Number(stats.total_cost).toLocaleString('th-TH', {minimumFractionDigits: 2});
    if(sl) sl.textContent = Number(stats.total_liters).toLocaleString('th-TH', {minimumFractionDigits: 2});
    if(sr) sr.textContent = Number(stats.total_records).toLocaleString('th-TH');
    if(sj) sj.textContent = stats.total_jobs ? Number(stats.total_jobs).toLocaleString('th-TH') : '0';
}

function renderAnalyticsCharts() {
    const labels = dailyData.map(item => item.record_date);
    const costs = dailyData.map(item => parseFloat(item.daily_cost));
    const liters = dailyData.map(item => parseFloat(item.daily_liters));

    const dailyDistMap = {};
    const dailyJobMap = {};
    const dailyCostMap = {};
    
    allRecords.forEach(r => {
        const date = r.date_recorded.split(' ')[0];
        dailyDistMap[date] = (dailyDistMap[date] || 0) + parseFloat(r.distance);
        dailyJobMap[date] = (dailyJobMap[date] || 0) + parseInt(r.job_count);
        dailyCostMap[date] = (dailyCostMap[date] || 0) + parseFloat(r.total_price);
    });

    const distances = labels.map(date => dailyDistMap[date] || 0);
    const costPerJob = labels.map(date => {
        const jobs = dailyJobMap[date] || 0;
        return jobs > 0 ? (dailyCostMap[date] / jobs) : 0;
    });

    const ctxCost = document.getElementById('combinedTrendChart')?.getContext('2d');
    if (ctxCost) {
        if (combinedTrendChartInstance) combinedTrendChartInstance.destroy();
        combinedTrendChartInstance = new Chart(ctxCost, {
            type: 'line',
            data: { labels, datasets: [{ label: 'ค่าใช้จ่าย (บาท)', data: costs, borderColor: 'rgb(99, 102, 241)', backgroundColor: 'rgba(99, 102, 241, 0.1)', tension: 0.3, fill: false }] },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
    }

    const ctxLiters = document.getElementById('litersTrendChart')?.getContext('2d');
    if (ctxLiters) {
        if (litersTrendChartInstance) litersTrendChartInstance.destroy();
        litersTrendChartInstance = new Chart(ctxLiters, {
            type: 'line',
            data: { labels, datasets: [{ label: 'ปริมาณ (ลิตร)', data: liters, borderColor: 'rgb(16, 185, 129)', backgroundColor: 'rgba(16, 185, 129, 0.1)', tension: 0.3, fill: false }] },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
    }

    const ctxDist = document.getElementById('distanceTrendChart')?.getContext('2d');
    if (ctxDist) {
        if (distanceTrendChartInstance) distanceTrendChartInstance.destroy();
        distanceTrendChartInstance = new Chart(ctxDist, {
            type: 'bar',
            data: { labels, datasets: [{ label: 'ระยะทางวิ่ง (กม.)', data: distances, backgroundColor: 'rgba(56, 189, 248, 0.7)', borderRadius: 4 }] },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
    }

    const ctxEff = document.getElementById('efficiencyChart')?.getContext('2d');
    if (ctxEff) {
        if (efficiencyChartInstance) efficiencyChartInstance.destroy();
        efficiencyChartInstance = new Chart(ctxEff, {
            type: 'line',
            data: { labels, datasets: [{ label: 'ต้นทุนเฉลี่ยต่อรอบงาน (บาท)', data: costPerJob, borderColor: 'rgb(245, 158, 11)', backgroundColor: 'rgba(245, 158, 11, 0.1)', tension: 0.3, fill: false }] },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
    }
}

function renderComparisonCharts() {
    const selector = document.getElementById('vehicleCompareSelector');
    if (!selector) return;
    const selectedVehicles = Array.from(selector.selectedOptions).map(opt => opt.value);
    
    if (selectedVehicles.length === 0) {
        [compareCostChartInstance, compareLitersChartInstance, compareDistanceChartInstance, compareJobsChartInstance].forEach(inst => inst && inst.destroy());
        return;
    }

    const vehicleStats = {};
    allRecords.forEach(r => {
        const plate = r.team_name || r.license_plate;
        if (!selectedVehicles.includes(plate)) return;
        if (!vehicleStats[plate]) vehicleStats[plate] = { cost: 0, liters: 0, distance: 0, jobs: 0 };
        vehicleStats[plate].cost += parseFloat(r.total_price);
        vehicleStats[plate].liters += parseFloat(r.liters);
        vehicleStats[plate].distance += parseFloat(r.distance);
        vehicleStats[plate].jobs += parseInt(r.job_count);
    });

    const labels = Object.keys(vehicleStats);
    const chartConfigs = [
        { id: 'compareCostChart', label: 'ยอดเงินรวมแต่ละคัน (บาท)', data: labels.map(l => vehicleStats[l].cost), color: '#6366f1' },
        { id: 'compareLitersChart', label: 'ปริมาณน้ำมันรวม (ลิตร)', data: labels.map(l => vehicleStats[l].liters), color: '#10b981' },
        { id: 'compareDistanceChart', label: 'ระยะทางวิ่งรวม (กม.)', data: labels.map(l => vehicleStats[l].distance), color: '#0ea5e9' },
        { id: 'compareJobsChart', label: 'จำนวนเคสงานรวม (รอบ)', data: labels.map(l => vehicleStats[l].jobs), color: '#f59e0b' }
    ];

    chartConfigs.forEach((config, idx) => {
        const canvas = document.getElementById(config.id);
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        if (idx === 0 && compareCostChartInstance) compareCostChartInstance.destroy();
        else if (idx === 1 && compareLitersChartInstance) compareLitersChartInstance.destroy();
        else if (idx === 2 && compareDistanceChartInstance) compareDistanceChartInstance.destroy();
        else if (idx === 3 && compareJobsChartInstance) compareJobsChartInstance.destroy();

        const newChart = new Chart(ctx, {
            type: 'bar',
            data: { labels, datasets: [{ label: config.label, data: config.data, backgroundColor: config.color + 'cc', borderRadius: 8 }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });
        if (idx === 0) compareCostChartInstance = newChart;
        else if (idx === 1) compareLitersChartInstance = newChart;
        else if (idx === 2) compareDistanceChartInstance = newChart;
        else if (idx === 3) compareJobsChartInstance = newChart;
    });
}

function renderMonthlyCompareChart() {
    if (monthlyData.length === 0) return;
    const labels = monthlyData.map(m => {
        const [year, month] = m.month_label.split('-');
        const date = new Date(year, month - 1);
        return date.toLocaleDateString('th-TH', { month: 'short', year: '2-digit' });
    });
    
    let canvas = document.getElementById('monthlyCompareChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (monthlyCompareChartInstance) monthlyCompareChartInstance.destroy();
    monthlyCompareChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'ยอดเงิน (บาท)', data: monthlyData.map(m => parseFloat(m.monthly_cost)), backgroundColor: 'rgba(99, 102, 241, 0.8)', borderRadius: 6 },
                { label: 'น้ำมัน (ลิตร)', data: monthlyData.map(m => parseFloat(m.monthly_liters)), backgroundColor: 'rgba(16, 185, 129, 0.8)', borderRadius: 6 },
                { label: 'เคสงาน (รอบ)', data: monthlyData.map(m => parseInt(m.monthly_jobs)), backgroundColor: 'rgba(245, 158, 11, 0.8)', borderRadius: 6 }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } }, plugins: { legend: { position: 'top' } } }
    });
}

function renderTable(records) {
    const tbody = document.getElementById('oilTableBody');
    if(!tbody) return;
    tbody.innerHTML = '';
    if (records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="11" class="px-6 py-12 text-center text-slate-400">ไม่พบข้อมูลในช่วงเวลาที่เลือก</td></tr>';
        return;
    }
    records.forEach((row, index) => {
        const dateObj = new Date(row.date_recorded);
        const formattedDate = dateObj.toLocaleDateString('th-TH') + ' ' + dateObj.toLocaleTimeString('th-TH', {hour: '2-digit', minute:'2-digit'});
        const teamBadge = row.team_name ? `<span class="bg-amber-50 text-amber-700 border border-amber-200 px-3 py-1 rounded-lg text-xs font-bold">🚗 ${row.team_name}</span>` : `<span class="bg-slate-100 text-slate-800 border border-slate-200 px-3 py-1 rounded-lg text-xs font-bold">${row.license_plate}</span>`;
        const fillerLine = row.filler_name ? `<p class="text-[10px] text-slate-400 mt-1">ผู้บันทึก: ${row.filler_name}</p>` : '';
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition-colors animate__animated animate__fadeIn';
        tr.style.animationDelay = `${index * 0.03}s`;
        
        tr.innerHTML = `
            <td class="px-4 py-4 whitespace-nowrap">${formattedDate}</td>
            <td class="px-4 py-4 font-medium text-slate-800">${row.tech_name}${fillerLine}</td>
            <td class="px-4 py-4">${teamBadge}</td>
            <td class="px-4 py-4 text-center font-bold text-slate-600">${Number(row.mileage).toLocaleString('th-TH')}</td>
            <td class="px-4 py-4 text-center font-bold text-sky-600">${row.distance} กม.</td>
            <td class="px-4 py-4 text-center font-bold text-slate-700">${Number(row.liters_per_km) > 0 ? Number(row.liters_per_km).toLocaleString('th-TH', {minimumFractionDigits:2}) + ' ลิตร/กม.' : '-'}</td>
            <td class="px-4 py-4 text-center"><span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1 rounded-lg text-xs font-black">📋 ${row.job_count} งาน</span></td>
            <td class="px-4 py-4 text-right"><span class="text-xs text-slate-400 block mb-0.5">กม. ละ</span><span class="font-bold text-rose-500">฿${Number(row.cost_per_km).toLocaleString('th-TH', {minimumFractionDigits:2})}</span></td>
            <td class="px-4 py-4 text-right"><span class="text-xs text-slate-400 block mb-0.5">งาน ละ</span><span class="font-bold text-indigo-500">฿${Number(row.cost_per_job).toLocaleString('th-TH', {minimumFractionDigits:2})}</span></td>
            <td class="px-4 py-4 text-right font-bold text-indigo-700 text-base">฿${Number(row.total_price).toLocaleString('th-TH', {minimumFractionDigits:2})}</td>
            <td class="px-4 py-4 text-center whitespace-nowrap">
                ${row.images ? `<button onclick="viewImages(${index})" class="text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-2.5 py-1.5 rounded-xl text-xs font-bold transition-all hover:shadow-sm">📷</button>` : `<span class="text-slate-300 text-xs italic mr-2">-</span>`}
                <button onclick="deleteOilRecord(${row.id})" class="text-rose-600 hover:text-rose-800 bg-rose-50 px-2.5 py-1.5 rounded-xl text-xs font-bold transition-all hover:shadow-sm">🗑️</button>
                <button onclick="openManageOilModal(${index})" class="text-amber-600 hover:text-amber-800 bg-amber-50 px-2.5 py-1.5 rounded-xl text-xs font-bold transition-all hover:shadow-sm ml-1">✏️</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

window.viewImages = function(recordIndex) {
    const record = allRecords[recordIndex];
    if (!record || !record.images) return;
    const images = record.images.split(',');
    const grid = document.getElementById('modalImageGrid');
    if(grid) {
        grid.innerHTML = '';
        images.forEach(img => {
            const url = `assets/uploads/oil_receipts/${img}`;
            grid.innerHTML += `<div class="rounded-xl overflow-hidden border border-slate-200 bg-slate-50 flex items-center justify-center aspect-square shadow-sm hover:shadow-md transition-shadow"><a href="${url}" target="_blank" title="คลิกเพื่อดูรูปขนาดเต็ม"><img src="${url}" class="w-full h-full object-contain hover:scale-105 transition-transform"></a></div>`;
        });
    }
    document.getElementById('imageModal')?.classList.remove('hidden');
};

window.closeImageModal = function() { document.getElementById('imageModal')?.classList.add('hidden'); };

window.openAddOilModal = function() {
    const modal = document.getElementById('manageOilModal');
    if(!modal) return;
    document.getElementById('manageOilModalTitle').innerHTML = '<i data-lucide="plus-circle" class="w-5 h-5 inline-block"></i> เพิ่มข้อมูลค่าน้ำมัน (ย้อนหลัง)';
    document.getElementById('btnSaveManageOil').textContent = 'บันทึกรายการใหม่';
    document.getElementById('manage_record_id').value = '';
    const now = new Date();
    const tzOffset = now.getTimezoneOffset() * 60000;
    document.getElementById('manage_date_recorded').value = (new Date(now - tzOffset)).toISOString().slice(0,16);
    
    const selTech = document.getElementById('manage_tech_id');
    const selPlate = document.getElementById('manage_license_plate');
    if(selTech) {
        selTech.innerHTML = '<option value="">-- เลือกผู้เติมน้ำมัน --</option>';
        editUsersList.filter(u => u.role === 'technician').forEach(u => {
            selTech.innerHTML += `<option value="${u.id}">${u.full_name}</option>`;
        });
    }
    if(selPlate) {
        selPlate.innerHTML = '<option value="">-- เลือกทะเบียนรถ --</option>';
        editTeamsList.forEach(t => selPlate.innerHTML += `<option value="${t.team_name}">${t.team_name}</option>`);
    }
    document.getElementById('manage_mileage').value = '';
    document.getElementById('manage_liters').value = '';
    document.getElementById('manage_price_per_liter').value = '';
    
    const manageTotalPrice = document.getElementById('manage_total_price');
    if (manageTotalPrice) manageTotalPrice.value = '';
    
    const manageDistance = document.getElementById('manage_distance');
    if (manageDistance) manageDistance.value = '';

    document.getElementById('manage_job_count').value = '0';
    document.getElementById('manage_images').value = '';
    document.getElementById('manage_image_section').style.display = 'block';
    modal.classList.remove('hidden');
    if(window.lucide) lucide.createIcons();
};

window.openManageOilModal = function(index) {
    const record = allRecords[index];
    if (!record) return;
    const modal = document.getElementById('manageOilModal');
    if(!modal) return;
    document.getElementById('manageOilModalTitle').innerHTML = '<i data-lucide="edit-2" class="w-5 h-5 inline-block"></i> แก้ไขข้อมูลน้ำมัน';
    document.getElementById('btnSaveManageOil').textContent = 'บันทึกการแก้ไข';
    
    const selTech = document.getElementById('manage_tech_id');
    const selPlate = document.getElementById('manage_license_plate');
    if(selTech) {
        selTech.innerHTML = '<option value="">-- เลือกผู้เติมน้ำมัน --</option>';
        editUsersList.filter(u => u.role === 'technician').forEach(u => {
            selTech.innerHTML += `<option value="${u.id}">${u.full_name}</option>`;
        });
        selTech.value = record.tech_id;
    }
    if(selPlate) {
        selPlate.innerHTML = '<option value="">-- เลือกทะเบียนรถ --</option>';
        editTeamsList.forEach(t => selPlate.innerHTML += `<option value="${t.team_name}">${t.team_name}</option>`);
        selPlate.value = record.license_plate;
    }
    
    document.getElementById('manage_record_id').value = record.id;
    const d = new Date(record.date_recorded);
    const tzOffset = d.getTimezoneOffset() * 60000;
    document.getElementById('manage_date_recorded').value = (new Date(d - tzOffset)).toISOString().slice(0,16);
    document.getElementById('manage_mileage').value = record.mileage;
    document.getElementById('manage_liters').value = record.liters;
    document.getElementById('manage_price_per_liter').value = record.price_per_liter;
    
    const manageTotalPrice = document.getElementById('manage_total_price');
    if (manageTotalPrice) manageTotalPrice.value = Math.round(record.total_price);
    
    const manageDistance = document.getElementById('manage_distance');
    if (manageDistance) manageDistance.value = record.distance ? record.distance + ' กม.' : '';

    document.getElementById('manage_job_count').value = record.job_count;
    document.getElementById('manage_images').value = '';
    document.getElementById('manage_image_section').style.display = 'none';
    modal.classList.remove('hidden');
    if(window.lucide) lucide.createIcons();
};

window.closeManageOilModal = function() { document.getElementById('manageOilModal')?.classList.add('hidden'); };

window.saveManageOil = async function() {
    const id = document.getElementById('manage_record_id').value;
    const tech_id = document.getElementById('manage_tech_id').value;
    const license_plate = document.getElementById('manage_license_plate').value;
    const date_recorded = document.getElementById('manage_date_recorded').value;
    const mileage = document.getElementById('manage_mileage').value;
    const liters = document.getElementById('manage_liters').value;
    const price_per_liter = document.getElementById('manage_price_per_liter').value;
    
    let total_price = '';
    const totalPriceEl = document.getElementById('manage_total_price');
    if(totalPriceEl) total_price = totalPriceEl.value;

    const job_count = document.getElementById('manage_job_count').value;

    if (!tech_id || !license_plate || !date_recorded || !mileage || !liters || !price_per_liter) {
        // 🚨 บังคับให้รอกด 'ตกลง' เมื่อกรอกข้อมูลไม่ครบ
        return Swal.fire({
            icon: 'warning',
            title: 'ข้อมูลไม่ครบถ้วน',
            text: 'กรุณากรอกข้อมูลสำคัญให้ครบถ้วน (ที่มีเครื่องหมาย *)',
            showConfirmButton: true,
            confirmButtonText: 'ตกลง',
            confirmButtonColor: '#f59e0b'
        });
    }

    showLoader();
    try {
        let isSuccess = false;
        let successMessage = '';

        if (id) {
            const res = await fetch('api/oil/edit_record.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id, tech_id, license_plate, date_recorded, mileage, liters, price_per_liter, total_price, job_count })
            });
            const data = await res.json();
            if(data.success) { 
                isSuccess = true;
                successMessage = 'แก้ไขข้อมูลและคำนวณระยะทางใหม่เรียบร้อยแล้ว';
            } else { 
                // 🚨 กรณีเกิดข้อผิดพลาด (เช่นข้อมูลซ้ำ) ระบบจะค้างรอให้กดยืนยันเสมอ
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: data.error,
                    showConfirmButton: true,
                    confirmButtonText: 'รับทราบ',
                    confirmButtonColor: '#ef4444'
                }); 
            }
        } else {
            const formData = new FormData();
            formData.append('tech_id', tech_id);
            formData.append('license_plate', license_plate);
            formData.append('date_recorded', date_recorded);
            formData.append('mileage', mileage);
            formData.append('liters', liters);
            formData.append('price_per_liter', price_per_liter);
            formData.append('total_price', total_price);
            formData.append('job_count', job_count);
            
            const fileInput = document.getElementById('manage_images');
            if (fileInput && fileInput.files.length > 0) {
                for (let i = 0; i < fileInput.files.length; i++) { formData.append('oil_images[]', fileInput.files[i]); }
            }
            const res = await fetch('api/oil/submit_record.php', { method: 'POST', body: formData });
            const data = await res.json();
            if(data.success) { 
                isSuccess = true;
                successMessage = 'เพิ่มข้อมูลและคำนวณระยะทางเรียบร้อยแล้ว';
            } else { 
                // 🚨 กรณีพบข้อมูลซ้ำ (Error จาก PHP) ให้บังคับรอกดยืนยัน
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: data.error,
                    showConfirmButton: true,
                    confirmButtonText: 'รับทราบ',
                    confirmButtonColor: '#ef4444'
                }); 
            }
        }

        // 🚨 กรณีบันทึกสำเร็จ: โชว์ POP-UP ค้างไว้ให้กดตกลง -> จากนั้นหน่วงเวลา 0.25 วินาที -> แล้วค่อยปิดและรีเฟรชตาราง
        if (isSuccess) {
            Swal.fire({
                icon: 'success',
                title: 'บันทึกสำเร็จ!',
                text: successMessage,
                showConfirmButton: true,
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#10b981'
            }).then(() => {
                // หน่วงเวลา (Delay) 0.25 วินาที (250 ms) ค่อยทำงานต่อ
                setTimeout(() => {
                    closeManageOilModal();
                    const recordDate = new Date(date_recorded);
                    const startInput = document.getElementById('start_date');
                    const endInput = document.getElementById('end_date');
                    
                    if (startInput && endInput) {
                        const currentStart = new Date(startInput.value);
                        const currentEnd = new Date(endInput.value);
                        const recordDateStr = recordDate.toISOString().split('T')[0];
                        
                        if (recordDate < currentStart) startInput.value = recordDateStr;
                        if (recordDate > currentEnd) endInput.value = recordDateStr;
                    }

                    fetchData(true); 
                }, 250); 
            });
        }
    } catch(e) { 
        Swal.fire({
            icon: 'error',
            title: 'ข้อผิดพลาดเครือข่าย',
            text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้',
            showConfirmButton: true,
            confirmButtonText: 'รับทราบ',
            confirmButtonColor: '#ef4444'
        });
    } finally { 
        if (!document.querySelector('.swal2-container')) hideLoader(); 
    }
};

window.deleteOilRecord = function(id) {
    Swal.fire({
        title: 'ยืนยันการลบข้อมูล?', 
        text: "คุณแน่ใจหรือไม่ที่จะลบรายการค่าน้ำมันนี้? การลบจะไม่สามารถกู้คืนได้", 
        icon: 'warning', 
        showCancelButton: true, 
        confirmButtonColor: '#ef4444', 
        cancelButtonColor: '#94a3b8', 
        confirmButtonText: 'ลบข้อมูล', 
        cancelButtonText: 'ยกเลิก', 
        reverseButtons: true
    }).then(async (result) => {
        if (result.isConfirmed) {
            showLoader();
            try {
                const res = await fetch('api/oil/delete_record.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ id }) });
                const data = await res.json();
                if(data.success) { 
                    Swal.fire({ 
                        icon: 'success', 
                        title: 'ลบข้อมูลสำเร็จ', 
                        showConfirmButton: true,
                        confirmButtonText: 'ตกลง',
                        confirmButtonColor: '#10b981'
                    }).then(() => {
                        setTimeout(() => fetchData(true), 250);
                    });
                } else { 
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: data.error,
                        showConfirmButton: true,
                        confirmButtonText: 'รับทราบ',
                        confirmButtonColor: '#ef4444'
                    }); 
                }
            } catch(e) { 
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาดเครือข่าย',
                    text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้',
                    showConfirmButton: true,
                    confirmButtonText: 'รับทราบ',
                    confirmButtonColor: '#ef4444'
                });
            } finally { 
                if (!document.querySelector('.swal2-container')) hideLoader(); 
            }
        }
    });
};

window.exportOilExcel = async function() {
    let vehicleOptions = '<option value="all">-- รวมทุกคัน --</option>';
    let vehiclesToShow = editTeamsList.map(t => t.team_name);
    if (vehiclesToShow.length === 0) {
        vehiclesToShow = [...new Set(allRecords.map(r => r.team_name || r.license_plate))].sort();
    }
    vehiclesToShow.forEach(v => {
        if(v) vehicleOptions += `<option value="${v}">${v}</option>`;
    });

    const today = new Date();
    const currentMonth = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`;

    const { value: formValues } = await Swal.fire({
        title: 'ส่งออกรายงาน (Excel)',
        html: `
            <div class="text-left mt-4">
                <label class="block text-sm font-bold text-slate-700 mb-2">เลือกรถ (ทีม):</label>
                <select id="swal_export_vehicle" class="w-full p-3 border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 transition-all">${vehicleOptions}</select>
            </div>
            <div class="text-left mt-4 mb-2">
                <label class="block text-sm font-bold text-slate-700 mb-2">เลือกเดือนที่ต้องการส่งออก:</label>
                <input type="month" id="swal_export_month" class="w-full p-3 border border-slate-300 rounded-xl bg-slate-50 focus:ring-2 focus:ring-indigo-500 transition-all" value="${currentMonth}">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'ดาวน์โหลด Excel',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#94a3b8',
        preConfirm: () => {
            const vehicle = document.getElementById('swal_export_vehicle').value;
            const month = document.getElementById('swal_export_month').value;
            if (!month) {
                Swal.showValidationMessage('กรุณาเลือกเดือนที่ต้องการส่งออก');
                return false;
            }
            return { vehicle, month };
        }
    });

    if (formValues) {
        showLoader();
        try {
            const [year, month] = formValues.month.split('-');
            const startDate = `${year}-${month}-01`;
            const endDate = new Date(year, month, 0).toISOString().split('T')[0];

            const response = await fetch(`api/oil/get_records.php?start_date=${startDate}&end_date=${endDate}`);
            const data = await response.json();

            if (!data.success) {
                hideLoader();
                return Swal.fire({
                    icon: 'error',
                    title: 'ดึงข้อมูลล้มเหลว',
                    text: data.error,
                    showConfirmButton: true,
                    confirmButtonText: 'รับทราบ',
                    confirmButtonColor: '#ef4444'
                });
            }

            let recordsToExport = data.records;
            if (formValues.vehicle !== 'all') {
                recordsToExport = recordsToExport.filter(r => (r.team_name || r.license_plate) === formValues.vehicle);
            }

            if (recordsToExport.length === 0) {
                hideLoader();
                let vName = formValues.vehicle === 'all' ? 'ทุกคัน' : `รถทะเบียน ${formValues.vehicle}`;
                return Swal.fire({
                    icon: 'info',
                    title: 'ไม่พบข้อมูล',
                    text: `ไม่มีประวัติการเติมน้ำมันของ ${vName} ในเดือน ${formValues.month}`,
                    showConfirmButton: true,
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#3b82f6'
                });
            }

            showToast('info', 'กำลังสร้างไฟล์ Excel พร้อมคำนวณยอดรวม...');
            let sortedRecords = [...recordsToExport].sort((a, b) => new Date(a.date_recorded) - new Date(b.date_recorded));
            
            let totalLiters = 0;
            let totalPrice = 0;
            let totalDistance = 0;
            let totalJobs = 0;
            
            let exportData = sortedRecords.map(row => {
                totalLiters += Number(row.liters);
                totalPrice += Number(row.total_price);
                totalDistance += Number(row.distance);
                totalJobs += Number(row.job_count);
                
                const d = new Date(row.date_recorded);
                return {
                    "วันที่": d.toLocaleDateString('en-GB') + ' ' + d.toLocaleTimeString('en-GB', {hour: '2-digit', minute:'2-digit'}),
                    "ทะเบียนรถ (ทีม)": row.team_name || row.license_plate,
                    "ชื่อผู้เติม": row.tech_name,
                    "เลขไมล์": parseInt(row.mileage),
                    "ลิตร": Number(row.liters),
                    "ยอดเงิน(บาท)": Number(row.total_price),
                    "ระยะทางวิ่ง(กม.)": row.distance,
                    "จำนวนเคสงาน(รอบ)": row.job_count,
                    "ต้นทุน/กม.(บาท)": row.cost_per_km,
                    "ต้นทุน/งาน(บาท)": row.cost_per_job
                };
            });

            let mileageIncreaseText = totalDistance;
            if (formValues.vehicle !== 'all' && sortedRecords.length > 0) {
                let validMileages = sortedRecords.map(r => parseInt(r.mileage)).filter(m => m > 0);
                if (validMileages.length > 0) {
                    let minMile = Math.min(...validMileages);
                    let maxMile = Math.max(...validMileages);
                    if (maxMile >= minMile) {
                        mileageIncreaseText = maxMile - minMile;
                    }
                }
            }

            exportData.push({}); 
            exportData.push({ "วันที่": "========== สรุปยอดรวมประจำเดือน ==========" });
            
            exportData.push({
                "วันที่": "เลขไมล์เดือนนี้เพิ่มขึ้น",
                "ทะเบียนรถ (ทีม)": formValues.vehicle !== 'all' ? (mileageIncreaseText.toLocaleString('th-TH') + " กม.") : "ดูที่ 'รวมระยะทางที่ใช้รถ' (เลือกทุกคัน)"
            });

            exportData.push({
                "วันที่": "จำนวนลิตรที่เติมไปเดือนนี้",
                "ทะเบียนรถ (ทีม)": totalLiters.toLocaleString('th-TH', {minimumFractionDigits: 2}) + " ลิตร"
            });
            
            exportData.push({
                "วันที่": "ยอดเงินที่ใช้ไปของเดือนนี้",
                "ทะเบียนรถ (ทีม)": totalPrice.toLocaleString('th-TH', {minimumFractionDigits: 2}) + " บาท"
            });
            
            exportData.push({
                "วันที่": "รวมระยะทางเท่าไหร่ที่ใช้รถ",
                "ทะเบียนรถ (ทีม)": totalDistance.toLocaleString('th-TH') + " กม."
            });
            
            let avgPricePerLiter = totalLiters > 0 ? (totalPrice / totalLiters) : 0;
            exportData.push({
                "วันที่": "ราคา/ลิตร โดยเฉลี่ยเดือนนี้",
                "ทะเบียนรถ (ทีม)": avgPricePerLiter.toLocaleString('th-TH', {minimumFractionDigits: 2}) + " บาท/ลิตร"
            });
            
            if (totalJobs > 0) {
                let avgCostPerJob = totalPrice / totalJobs;
                exportData.push({
                    "วันที่": "เฉลี่ยต่อเคส (ต้นทุน)",
                    "ทะเบียนรถ (ทีม)": avgCostPerJob.toLocaleString('th-TH', {minimumFractionDigits: 2}) + " บาท/เคส"
                });
            } else {
                exportData.push({
                    "วันที่": "เฉลี่ยต่อเคส (ต้นทุน)",
                    "ทะเบียนรถ (ทีม)": "ไม่มีประวัติการเข้าเคสงาน"
                });
            }

            const ws = XLSX.utils.json_to_sheet(exportData);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "ต้นทุนค่าน้ำมัน");
            
            let safeVehicleName = formValues.vehicle === 'all' ? 'รวมทุกคัน' : formValues.vehicle;
            XLSX.writeFile(wb, `รายงานน้ำมัน_${safeVehicleName}_${formValues.month}.xlsx`);
            
            hideLoader();
            Swal.fire({
                icon: 'success',
                title: 'ส่งออกสำเร็จ!',
                text: 'ดาวน์โหลดไฟล์ Excel เรียบร้อยแล้ว',
                showConfirmButton: true,
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#10b981'
            });
        } catch (error) {
            hideLoader();
            console.error("Export Error:", error);
            Swal.fire({
                icon: 'error',
                title: 'เกิดข้อผิดพลาด',
                text: 'มีข้อผิดพลาดในการสร้างไฟล์ Excel',
                showConfirmButton: true,
                confirmButtonText: 'รับทราบ',
                confirmButtonColor: '#ef4444'
            });
        }
    }
};

document.getElementById('importOilExcel')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    Swal.fire({ title: 'กำลังอ่านไฟล์ Excel...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    const reader = new FileReader();
    reader.onload = async function(event) {
        try {
            const data = new Uint8Array(event.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const jsonData = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]], { defval: "" });
            if (jsonData.length === 0) {
                return Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาด',
                    text: 'ไม่พบข้อมูลในไฟล์ Excel',
                    showConfirmButton: true,
                    confirmButtonText: 'รับทราบ',
                    confirmButtonColor: '#ef4444'
                });
            }
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
            const res = await fetch('api/oil/import_records.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ records: payload }) });
            const result = await res.json();
            if (result.success) { 
                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ!',
                    text: `นำเข้าข้อมูลน้ำมันสำเร็จ ${result.imported} รายการ`,
                    showConfirmButton: true,
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#10b981'
                }).then(() => {
                    setTimeout(() => fetchData(true), 250);
                });
            } else { 
                Swal.fire({
                    icon: 'error',
                    title: 'เกิดข้อผิดพลาด',
                    text: result.error,
                    showConfirmButton: true,
                    confirmButtonText: 'รับทราบ',
                    confirmButtonColor: '#ef4444'
                }); 
            }
        } catch (err) { 
            Swal.fire({
                icon: 'error',
                title: 'ข้อผิดพลาด',
                text: 'ไฟล์ Excel รูปแบบไม่ถูกต้อง',
                showConfirmButton: true,
                confirmButtonText: 'รับทราบ',
                confirmButtonColor: '#ef4444'
            }); 
        } finally { 
            document.getElementById('importOilExcel').value = ''; 
        }
    };
    reader.readAsArrayBuffer(file);
});

function formatExcelDate(excelDate) {
    if (!excelDate) return null;
    if (typeof excelDate === 'number') {
        const date = new Date((excelDate - (25567 + 2)) * 86400 * 1000);
        return date.toISOString().split('T')[0] + ' 12:00:00';
    } else if (typeof excelDate === 'string') {
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
                if(time.length === 5) time += ':00';
            }
            return `${year}-${month}-${day} ${time}`;
        }
    }
    return excelDate;
}

window.recalculateAllMileage = function() {
    Swal.fire({
        title: 'จัดเรียงและคำนวณไมล์ใหม่?',
        text: "ระบบจะทำการเรียงลำดับวันที่การเติมน้ำมันของรถทุกคันใหม่ และคำนวณระยะทางจากเลขไมล์ให้สอดคล้องกันทั้งหมด คุณต้องการดำเนินการต่อหรือไม่?",
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#0ea5e9',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ใช่, คำนวณใหม่เลย',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true
    }).then(async (result) => {
        if (result.isConfirmed) {
            showLoader();
            try {
                const res = await fetch('api/oil/recalculate.php');
                const data = await res.json();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ!',
                        text: 'เรียงวันที่และคำนวณระยะทางใหม่เรียบร้อยแล้ว!',
                        showConfirmButton: true,
                        confirmButtonText: 'ตกลง',
                        confirmButtonColor: '#10b981'
                    }).then(() => {
                        setTimeout(() => fetchData(true), 250);
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: data.error,
                        showConfirmButton: true,
                        confirmButtonText: 'รับทราบ',
                        confirmButtonColor: '#ef4444'
                    });
                }
            } catch (e) {
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาดเครือข่าย',
                    text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้',
                    showConfirmButton: true,
                    confirmButtonText: 'รับทราบ',
                    confirmButtonColor: '#ef4444'
                });
            } finally {
                if (!document.querySelector('.swal2-container')) hideLoader(); 
            }
        }
    });
};