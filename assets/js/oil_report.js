// assets/js/oil_report.js

let costChartInstance = null;
let litersChartInstance = null;
let allRecords = [];

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
});

async function fetchData() {
    const startDate = document.getElementById('start_date').value;
    const endDate = document.getElementById('end_date').value;

    document.getElementById('oilTableBody').innerHTML = '<tr><td colspan="8" class="px-6 py-12 text-center text-slate-400"><div class="flex flex-col items-center justify-center"><div class="loader-spinner mb-4 w-8 h-8"></div> กำลังโหลดข้อมูลรายงาน...</div></td></tr>';

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
            document.getElementById('oilTableBody').innerHTML = `<tr><td colspan="8" class="px-6 py-4 text-center text-rose-500 font-bold">ไม่สามารถดึงข้อมูลได้: ${data.error}</td></tr>`;
        }
    } catch (error) {
        console.error("Error fetching data:", error);
        Toast.error('ไม่สามารถเชื่อมต่อกับฐานข้อมูลได้');
        document.getElementById('oilTableBody').innerHTML = `<tr><td colspan="8" class="px-6 py-4 text-center text-rose-500 font-bold">ไม่สามารถโหลดข้อมูลได้ กรุณาตรวจสอบคอนโซล</td></tr>`;
    }
}

function updateStats(stats) {
    // Animate numbers if possible, or just set text
    document.getElementById('stat_total_cost').textContent = stats.total_cost.toLocaleString('th-TH', {minimumFractionDigits: 2});
    document.getElementById('stat_total_liters').textContent = stats.total_liters.toLocaleString('th-TH', {minimumFractionDigits: 2});
    document.getElementById('stat_total_records').textContent = stats.total_records.toLocaleString('th-TH');    
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
        tbody.innerHTML = '<tr><td colspan="8" class="px-6 py-12 text-center text-slate-400">ไม่พบข้อมูลในช่วงเวลาที่เลือก</td></tr>';
        return;
    }

    records.forEach((row, index) => {
        const dateObj = new Date(row.date_recorded);
        const formattedDate = dateObj.toLocaleDateString('th-TH') + ' ' + dateObj.toLocaleTimeString('th-TH', {hour: '2-digit', minute:'2-digit'});

        const hasImages = row.images ? true : false;

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition-colors animate__animated animate__fadeIn';
        tr.style.animationDelay = `${index * 0.05}s`;
        tr.innerHTML = `
            <td class="px-6 py-4">${formattedDate}</td>
            <td class="px-6 py-4 font-medium text-slate-800">${row.tech_name}</td>
            <td class="px-6 py-4"><span class="bg-slate-100 text-slate-800 border border-slate-200 px-3 py-1 rounded-lg text-xs font-bold">${row.license_plate}</span></td>
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