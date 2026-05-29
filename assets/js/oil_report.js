// assets/js/oil_report.js

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
        Toast.info('กำลังอัปเดตข้อมูลตามวันที่เลือก...');
        fetchData();
    });
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
        section?.classList.remove('hidden');
        selectorWrapper?.classList.remove('hidden');
        if(btnText) btnText.textContent = 'ดูรายงานปกติ';
        
        fillVehicleCompareSelector();
        renderComparisonCharts();
        renderMonthlyCompareChart();
    } else {
        section?.classList.add('hidden');
        selectorWrapper?.classList.add('hidden');
        if(btnText) btnText.textContent = 'เปรียบเทียบรถ';
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
    if (!techId) return;
    const user = editUsersList.find(u => u.id == techId);
    if (user && user.team_name) {
        const plateSelector = document.getElementById('manage_license_plate');
        if(!plateSelector) return;
        const exists = Array.from(plateSelector.options).some(opt => opt.value === user.team_name);
        if (exists) {
            plateSelector.value = user.team_name;
            Toast.info(`เลือกทะเบียนรถ ${user.team_name} ให้อัตโนมัติสำหรับ ${user.full_name}`);
        }
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
        }
    } catch(e) { console.error('Failed to load edit options', e); }
}

async function fetchData() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;
    const tbody = document.getElementById('oilTableBody');
    if(tbody) tbody.innerHTML = '<tr><td colspan="9" class="px-6 py-12 text-center text-slate-400"><div class="flex flex-col items-center justify-center"><div class="loader-spinner mb-4 w-8 h-8"></div> กำลังโหลดข้อมูลรายงาน...</div></td></tr>';

    try {
        const response = await fetch(`api/oil/get_records.php?start_date=${startDate}&end_date=${endDate}`);    
        const data = await response.json();

        if (data.success) {
            updateStats(data.stats);
            allRecords = data.records;
            monthlyData = data.monthly || [];
            dailyData = data.chart || [];
            
            if (isCompareMode) {
                const selector = document.getElementById('vehicleCompareSelector');
                if(selector) {
                    selector.innerHTML = ''; 
                    fillVehicleCompareSelector();
                }
            }

            renderAnalyticsCharts();
            if (isCompareMode) {
                renderComparisonCharts();
                renderMonthlyCompareChart();
            }
            renderTable(data.records);
            
            if (data.records.length > 0) {
                Toast.success(`โหลดข้อมูลสำเร็จ พบทั้งหมด ${data.records.length} รายการ`);
            }
        } else {
            Toast.error(`เกิดข้อผิดพลาด: ${data.error}`);
            if(tbody) tbody.innerHTML = `<tr><td colspan="9" class="px-6 py-4 text-center text-rose-500 font-bold">ไม่สามารถดึงข้อมูลได้: ${data.error}</td></tr>`;
        }
    } catch (error) {
        console.error("Error fetching data:", error);
        Toast.error('ไม่สามารถเชื่อมต่อกับฐานข้อมูลได้');
        if(tbody) tbody.innerHTML = `<tr><td colspan="9" class="px-6 py-4 text-center text-rose-500 font-bold">ไม่สามารถโหลดข้อมูลได้ กรุณาตรวจสอบคอนโซล</td></tr>`;
    }
}

function updateStats(stats) {
    const sc = document.getElementById('stat_total_cost');
    const sl = document.getElementById('stat_total_liters');
    const sr = document.getElementById('stat_total_records');
    const sj = document.getElementById('stat_total_jobs');
    if(sc) sc.textContent = stats.total_cost.toLocaleString('th-TH', {minimumFractionDigits: 2});
    if(sl) sl.textContent = stats.total_liters.toLocaleString('th-TH', {minimumFractionDigits: 2});
    if(sr) sr.textContent = stats.total_records.toLocaleString('th-TH');
    if(sj) sj.textContent = stats.total_jobs ? stats.total_jobs.toLocaleString('th-TH') : '0';
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
            data: {
                labels,
                datasets: [{ label: 'ค่าใช้จ่าย (บาท)', data: costs, borderColor: 'rgb(99, 102, 241)', backgroundColor: 'rgba(99, 102, 241, 0.1)', tension: 0.3, fill: false }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
    }

    const ctxLiters = document.getElementById('litersTrendChart')?.getContext('2d');
    if (ctxLiters) {
        if (litersTrendChartInstance) litersTrendChartInstance.destroy();
        litersTrendChartInstance = new Chart(ctxLiters, {
            type: 'line',
            data: {
                labels,
                datasets: [{ label: 'ปริมาณ (ลิตร)', data: liters, borderColor: 'rgb(16, 185, 129)', backgroundColor: 'rgba(16, 185, 129, 0.1)', tension: 0.3, fill: false }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
    }

    const ctxDist = document.getElementById('distanceTrendChart')?.getContext('2d');
    if (ctxDist) {
        if (distanceTrendChartInstance) distanceTrendChartInstance.destroy();
        distanceTrendChartInstance = new Chart(ctxDist, {
            type: 'bar',
            data: {
                labels,
                datasets: [{ label: 'ระยะทางวิ่ง (กม.)', data: distances, backgroundColor: 'rgba(56, 189, 248, 0.7)', borderRadius: 4 }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
        });
    }

    const ctxEff = document.getElementById('efficiencyChart')?.getContext('2d');
    if (ctxEff) {
        if (efficiencyChartInstance) efficiencyChartInstance.destroy();
        efficiencyChartInstance = new Chart(ctxEff, {
            type: 'line',
            data: {
                labels,
                datasets: [{ label: 'ต้นทุนเฉลี่ยต่อรอบงาน (บาท)', data: costPerJob, borderColor: 'rgb(245, 158, 11)', backgroundColor: 'rgba(245, 158, 11, 0.1)', tension: 0.3, fill: false }]
            },
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
    if (!canvas) {
        const container = document.createElement('div');
        container.className = 'card mt-6';
        container.innerHTML = `<h3 class="font-bold text-[var(--c-text-1)] mb-4 flex items-center"><i data-lucide="calendar" class="w-5 h-5 mr-2 text-rose-500"></i>สรุปยอดเปรียบเทียบระหว่างเดือน (ปีปัจจุบัน)</h3><div class="h-80"><canvas id="monthlyCompareChart"></canvas></div>`;
        document.getElementById('compareSection')?.appendChild(container);
        canvas = document.getElementById('monthlyCompareChart');
        if(window.lucide) lucide.createIcons();
    }
    if(!canvas) return;
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
        tbody.innerHTML = '<tr><td colspan="9" class="px-6 py-12 text-center text-slate-400">ไม่พบข้อมูลในช่วงเวลาที่เลือก</td></tr>';
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
            <td class="px-4 py-4 text-center font-bold text-sky-600">${row.distance} กม.</td>
            <td class="px-4 py-4 text-center"><span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1 rounded-lg text-xs font-black">📋 ${row.job_count} งาน</span></td>
            <td class="px-4 py-4 text-right"><span class="text-xs text-slate-400 block mb-0.5">กม. ละ</span><span class="font-bold text-rose-500">฿${row.cost_per_km.toLocaleString('th-TH', {minimumFractionDigits:2})}</span></td>
            <td class="px-4 py-4 text-right"><span class="text-xs text-slate-400 block mb-0.5">งาน ละ</span><span class="font-bold text-indigo-500">฿${row.cost_per_job.toLocaleString('th-TH', {minimumFractionDigits:2})}</span></td>
            <td class="px-4 py-4 text-right font-bold text-indigo-700 text-base">฿${parseFloat(row.total_price).toLocaleString('th-TH', {minimumFractionDigits:2})}</td>
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
        editUsersList.forEach(u => selTech.innerHTML += `<option value="${u.id}">${u.full_name}</option>`);
    }
    if(selPlate) {
        selPlate.innerHTML = '<option value="">-- เลือกทะเบียนรถ --</option>';
        editTeamsList.forEach(t => selPlate.innerHTML += `<option value="${t.team_name}">${t.team_name}</option>`);
    }
    document.getElementById('manage_mileage').value = '';
    document.getElementById('manage_liters').value = '';
    document.getElementById('manage_price_per_liter').value = '';
    document.getElementById('manage_distance').value = '';
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
        editUsersList.forEach(u => selTech.innerHTML += `<option value="${u.id}">${u.full_name}</option>`);
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
    document.getElementById('manage_distance').value = record.distance;
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
    const distance = document.getElementById('manage_distance').value;
    const job_count = document.getElementById('manage_job_count').value;

    if (!tech_id || !license_plate || !date_recorded || !mileage || !liters || !price_per_liter) {
        return Toast.error('กรุณากรอกข้อมูลสำคัญให้ครบถ้วน (ที่มีเครื่องหมาย *)');
    }

    Loader.show();
    try {
        if (id) {
            const res = await fetch('api/oil/edit_record.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id, tech_id, license_plate, date_recorded, mileage, liters, price_per_liter, distance, job_count })
            });
            const data = await res.json();
            if(data.success) { Toast.success('แก้ไขข้อมูลน้ำมันเรียบร้อย'); closeManageOilModal(); fetchData(); }
            else { Toast.error('เกิดข้อผิดพลาด: ' + data.error); }
        } else {
            const formData = new FormData();
            formData.append('tech_id', tech_id);
            formData.append('license_plate', license_plate);
            formData.append('date_recorded', date_recorded);
            formData.append('mileage', mileage);
            formData.append('liters', liters);
            formData.append('price_per_liter', price_per_liter);
            formData.append('distance', distance);
            formData.append('job_count', job_count);
            const fileInput = document.getElementById('manage_images');
            if (fileInput && fileInput.files.length > 0) {
                for (let i = 0; i < fileInput.files.length; i++) { formData.append('oil_images[]', fileInput.files[i]); }
            }
            const res = await fetch('api/oil/submit_record.php', { method: 'POST', body: formData });
            const data = await res.json();
            if(data.success) { Toast.success('เพิ่มข้อมูลน้ำมันย้อนหลังเรียบร้อย'); closeManageOilModal(); fetchData(); }
            else { Toast.error('เกิดข้อผิดพลาด: ' + data.error); }
        }
    } catch(e) { Toast.error('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้'); } finally { Loader.hide(); }
};

window.deleteOilRecord = function(id) {
    Swal.fire({
        title: 'ยืนยันการลบข้อมูล?', text: "คุณแน่ใจหรือไม่ที่จะลบรายการค่าน้ำมันนี้? การลบจะไม่สามารถกู้คืนได้", icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', cancelButtonColor: '#94a3b8', confirmButtonText: 'ลบข้อมูล', cancelButtonText: 'ยกเลิก', reverseButtons: true
    }).then(async (result) => {
        if (result.isConfirmed) {
            Loader.show();
            try {
                const res = await fetch('api/oil/delete_record.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ id }) });
                const data = await res.json();
                if(data.success) { Toast.success('ลบข้อมูลเรียบร้อยแล้ว'); fetchData(); }
                else { Toast.error('ลบข้อมูลไม่สำเร็จ: ' + data.error); }
            } catch(e) { Toast.error('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้'); } finally { Loader.hide(); }
        }
    });
};

window.exportOilExcel = function() {
    if (allRecords.length === 0) return Toast.warning('ไม่มีข้อมูลสำหรับส่งออก');
    Toast.info('กำลังเตรียมไฟล์ Excel ต้นทุนรายรอบ...');
    let sortedRecords = [...allRecords].sort((a, b) => new Date(a.date_recorded) - new Date(b.date_recorded));
    let exportData = sortedRecords.map(row => {
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
    const ws = XLSX.utils.json_to_sheet(exportData);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "ต้นทุนค่าน้ำมัน");
    XLSX.writeFile(wb, `รายงานต้นทุนน้ำมัน_${new Date().toISOString().split('T')[0]}.xlsx`);
    Toast.success('ดาวน์โหลดไฟล์ Excel เรียบร้อย!');
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
            if (jsonData.length === 0) return Swal.fire('ข้อผิดพลาด', 'ไม่พบข้อมูลในไฟล์ Excel', 'error');
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
            if (result.success) { Swal.fire('สำเร็จ!', `นำเข้าข้อมูลน้ำมันสำเร็จ ${result.imported} รายการ`, 'success'); fetchData(); }
            else { Swal.fire('เกิดข้อผิดพลาด', result.error, 'error'); }
        } catch (err) { Swal.fire('ข้อผิดพลาด', 'ไฟล์ Excel รูปแบบไม่ถูกต้อง', 'error'); }
        finally { document.getElementById('importOilExcel').value = ''; }
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
