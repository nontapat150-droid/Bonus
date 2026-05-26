// assets/js/dispatch.js

let map;
let markers = [];
let polylines = [];
let allJobs = [];
let quotas = []; // [{team_name, limit}]

// Define column mapping based on requirement
const COL = {
    PLAN_ARRIVAL: 0,
    ACCESS: 1,
    CUSTOMER: 2,
    PHONE: 3,
    PACKAGE: 4,
    ADDRESS: 5,
    ORIGINAL_G: 6,
    STATUS: 8,
    PRODUCT: 9,
    LAT: 10,
    LNG: 11,
    ORDER_NO: 12,
    TASK_ORDER: 15,
    TASK_TYPE: 23,
    REMARK: 44
};

// Team colors for map lines
const teamColors = [
    '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', 
    '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#06b6d4'
];
function getColor(index) { return teamColors[index % teamColors.length]; }

document.addEventListener('DOMContentLoaded', () => {
    initMap();
    loadJobs();

    if (IS_ADMIN) {
        document.getElementById('jobExcelFile')?.addEventListener('change', handleExcelUpload);
        document.getElementById('addQuotaBtn')?.addEventListener('click', addQuota);
        document.getElementById('autoDispatchBtn')?.addEventListener('click', runAutoDispatch);
        document.getElementById('optimizeRouteBtn')?.addEventListener('click', runOptimizeRoute);
        document.getElementById('teamFilter')?.addEventListener('change', renderUI);
    }
});

function initMap() {
    // Default to Thailand center
    map = L.map('map').setView([13.7563, 100.5018], 6);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
}

function showLoader() { document.getElementById('mapLoader').classList.remove('hidden'); }
function hideLoader() { document.getElementById('mapLoader').classList.add('hidden'); }

async function loadJobs() {
    showLoader();
    try {
        const res = await fetch('api/dispatch/get_jobs.php');
        const data = await res.json();
        
        if (data.success) {
            allJobs = data.data;
            
            if (IS_ADMIN && data.teams) {
                const filter = document.getElementById('teamFilter');
                // Keep 'all' and 'unassigned', remove old team options
                filter.innerHTML = '<option value="all">ทุกทีม</option><option value="unassigned">ยังไม่จ่ายงาน</option>';
                data.teams.forEach(t => {
                    filter.innerHTML += `<option value="${t.id}">${t.team_name}</option>`;
                });
            }

            // For technician, update map link if available
            if (!IS_ADMIN && allJobs.length > 0) {
                const link = allJobs.find(j => j.map_link)?.map_link;
                if (link) {
                    const linkBtn = document.getElementById('techMapLink');
                    linkBtn.href = link;
                    linkBtn.classList.remove('hidden');
                }
            }

            renderUI();
        } else {
            alert('Error loading jobs: ' + data.error);
        }
    } catch (e) {
        console.error(e);
        alert('Failed to connect to server.');
    } finally {
        hideLoader();
    }
}

function renderUI() {
    clearMap();
    const container = document.getElementById('jobListContainer');
    container.innerHTML = '';

    let filterVal = 'all';
    if (IS_ADMIN) filterVal = document.getElementById('teamFilter').value;

    let filteredJobs = allJobs;
    if (filterVal === 'unassigned') {
        filteredJobs = allJobs.filter(j => !j.team_id);
    } else if (filterVal !== 'all') {
        filteredJobs = allJobs.filter(j => j.team_id == filterVal);
    }

    document.getElementById('jobCountBadge').textContent = filteredJobs.length;

    if (filteredJobs.length === 0) {
        container.innerHTML = '<div class="text-center text-gray-500 py-8">ไม่มีข้อมูลงาน</div>';
        return;
    }

    const bounds = L.latLngBounds();
    const jobsByTeam = {};

    filteredJobs.forEach(job => {
        // Build Job Card
        const card = createJobCard(job);
        container.appendChild(card);

        // Group for map lines (only assigned jobs)
        if (job.team_id && job.lat && job.lng) {
            if (!jobsByTeam[job.team_id]) jobsByTeam[job.team_id] = [];
            jobsByTeam[job.team_id].push(job);
        }

        // Map Marker
        if (job.lat && job.lng) {
            const latLng = [parseFloat(job.lat), parseFloat(job.lng)];
            bounds.extend(latLng);

            // Marker icon (gray if unassigned, otherwise blue)
            const color = job.team_id ? '#3b82f6' : '#9ca3af';
            const seqText = job.seq ? job.seq : '-';

            const markerHtml = `
                <div style="background-color: ${color}; width: 24px; height: 24px; border-radius: 50%; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 12px; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">
                    ${seqText}
                </div>
            `;
            const icon = L.divIcon({ html: markerHtml, className: '', iconSize: [24, 24], iconAnchor: [12, 12] });
            
            const marker = L.marker(latLng, { icon }).bindPopup(`
                <b>${job.access_no}</b><br>
                ${job.customer}<br>
                ทีม: ${job.team_name || 'ยังไม่จ่ายงาน'}
            `).addTo(map);
            
            markers.push(marker);
        }
    });

    // Draw lines per team
    let teamIndex = 0;
    for (const tId in jobsByTeam) {
        // Sort by seq to draw lines correctly
        jobsByTeam[tId].sort((a, b) => (a.seq || 9999) - (b.seq || 9999));
        
        const latlngs = jobsByTeam[tId].map(j => [parseFloat(j.lat), parseFloat(j.lng)]);
        if (latlngs.length > 1) {
            const polyline = L.polyline(latlngs, { color: getColor(teamIndex), weight: 3, opacity: 0.7, dashArray: '5, 5' }).addTo(map);
            polylines.push(polyline);
        }
        teamIndex++;
    }

    if (markers.length > 0) {
        map.fitBounds(bounds, { padding: [30, 30] });
    }
}

function createJobCard(job) {
    const div = document.createElement('div');
    div.className = 'bg-white border border-gray-200 rounded-lg p-3 shadow-sm hover:shadow-md transition relative';
    
    let badge = '';
    if (job.status === 'Finish') {
        badge = '<span class="absolute top-2 right-2 bg-green-100 text-green-800 text-xs px-2 py-0.5 rounded font-bold">Finish</span>';
    } else if (job.team_name) {
        badge = `<span class="absolute top-2 right-2 bg-indigo-100 text-indigo-800 text-xs px-2 py-0.5 rounded font-bold">ทีม: ${job.team_name}</span>`;
    } else {
        badge = '<span class="absolute top-2 right-2 bg-gray-100 text-gray-800 text-xs px-2 py-0.5 rounded font-bold">Unassigned</span>';
    }

    // Phone parsing (split by comma)
    let phoneHtml = '';
    if (job.phone) {
        const phones = job.phone.split(',').map(p => p.trim()).filter(p => p);
        phoneHtml = '<div class="mt-2 flex flex-wrap gap-2">';
        phones.forEach(p => {
            phoneHtml += `<a href="tel:${p}" class="inline-flex items-center px-2 py-1 bg-green-50 text-green-700 border border-green-200 rounded text-xs font-bold hover:bg-green-100"><span class="mr-1">📞</span> ${p}</a>`;
        });
        phoneHtml += '</div>';
    }

    div.innerHTML = `
        ${badge}
        <div class="flex items-center space-x-2 mb-1">
            <span class="bg-gray-800 text-white w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold">${job.seq || '-'}</span>
            <span class="font-bold text-gray-800 text-sm">${job.access_no}</span>
        </div>
        <p class="text-xs text-gray-600 font-medium">${job.customer}</p>
        <p class="text-xs text-gray-500 truncate mt-1" title="${job.address}">📍 ${job.address}</p>
        ${phoneHtml}
    `;
    return div;
}

function clearMap() {
    markers.forEach(m => map.removeLayer(m));
    polylines.forEach(p => map.removeLayer(p));
    markers = [];
    polylines = [];
}

// -----------------------------------------
// ADMIN FUNCTIONS (Import, Quota, Dispatch)
// -----------------------------------------

function handleExcelUpload(e) {
    const file = e.target.files[0];
    if (!file) return;

    showLoader();
    const reader = new FileReader();
    reader.onload = async function(e) {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, {type: 'array'});
        const worksheet = workbook.Sheets[workbook.SheetNames[0]];
        const rows = XLSX.utils.sheet_to_json(worksheet, {header: 1});

        const parsedJobs = [];
        // Skip header row (index 0)
        for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            if (!row || row.length === 0) continue;
            
            const accessNo = row[COL.ACCESS];
            const lat = row[COL.LAT];
            const lng = row[COL.LNG];

            if (accessNo && lat && lng) {
                // Basic cleanup
                const cleanLat = String(lat).replace(/[^0-9.-]/g, '');
                const cleanLng = String(lng).replace(/[^0-9.-]/g, '');
                
                parsedJobs.push({
                    plan_arrival: row[COL.PLAN_ARRIVAL],
                    access_no: accessNo,
                    customer: row[COL.CUSTOMER],
                    phone: row[COL.PHONE],
                    package: row[COL.PACKAGE],
                    address: row[COL.ADDRESS],
                    status: row[COL.STATUS],
                    product: row[COL.PRODUCT],
                    lat: cleanLat,
                    lng: cleanLng,
                    order_no: row[COL.ORDER_NO],
                    task_order: row[COL.TASK_ORDER],
                    task_type: row[COL.TASK_TYPE],
                    remark: row[COL.REMARK]
                });
            }
        }

        if (parsedJobs.length === 0) {
            alert('ไม่พบข้อมูลที่มีพิกัด Lat/Lng หรือ Access No.');
            hideLoader();
            return;
        }

        if (confirm(`พบข้อมูลพร้อมพิกัด ${parsedJobs.length} รายการ\nการนำเข้าจะล้างข้อมูลงานเก่าทั้งหมด ต้องการดำเนินการต่อหรือไม่?`)) {
            try {
                const res = await fetch('api/dispatch/upload_jobs.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ jobs: parsedJobs })
                });
                const rData = await res.json();
                if (rData.success) {
                    alert(`นำเข้าสำเร็จ ${rData.imported} รายการ`);
                    loadJobs();
                } else {
                    alert('Error: ' + rData.error);
                }
            } catch (err) {
                alert('Connection error');
            }
        }
        hideLoader();
        document.getElementById('jobExcelFile').value = '';
    };
    reader.readAsArrayBuffer(file);
}

function addQuota() {
    const nameInput = document.getElementById('teamQuotaName');
    const limitInput = document.getElementById('teamQuotaLimit');
    const name = nameInput.value.trim();
    const limit = parseInt(limitInput.value);

    if (!name || isNaN(limit) || limit < 1) return alert('กรอกข้อมูลโควตาให้ถูกต้อง');

    const existing = quotas.find(q => q.team_name === name);
    if (existing) {
        existing.limit = limit;
    } else {
        quotas.push({ team_name: name, limit: limit });
    }

    nameInput.value = '';
    limitInput.value = '';
    renderQuotas();
}

function renderQuotas() {
    const area = document.getElementById('quotaArea');
    if (quotas.length === 0) {
        area.classList.add('hidden');
        return;
    }
    
    area.classList.remove('hidden');
    area.innerHTML = '<span class="text-sm font-bold text-gray-600 mr-2 flex items-center">โควตา:</span>';
    
    quotas.forEach((q, i) => {
        area.innerHTML += `
            <span class="inline-flex items-center px-2 py-1 rounded-md text-sm bg-gray-100 border border-gray-200">
                <span class="font-bold mr-1">${q.team_name}</span> 
                <span class="text-blue-600 font-bold">${q.limit}</span>
                <button onclick="removeQuota(${i})" class="ml-2 text-red-500 hover:text-red-700 font-bold">&times;</button>
            </span>
        `;
    });
}

window.removeQuota = function(index) {
    quotas.splice(index, 1);
    renderQuotas();
};

async function runAutoDispatch() {
    if (quotas.length === 0) return alert('กรุณาตั้งค่าโควตาให้ทีมช่างก่อน');
    
    const unassignedCount = allJobs.filter(j => !j.team_id).length;
    if (unassignedCount === 0) return alert('ไม่มีงานที่รอจ่ายในระบบ');

    showLoader();
    try {
        const res = await fetch('api/dispatch/auto_assign.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ quotas })
        });
        const data = await res.json();
        if (data.success) {
            alert(`จ่ายงานอัตโนมัติสำเร็จ ${data.assigned} รายการ`);
            loadJobs();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (e) {
        alert('Connection error');
    } finally {
        hideLoader();
    }
}

async function runOptimizeRoute() {
    showLoader();
    try {
        const res = await fetch('api/dispatch/optimize_route.php');
        const data = await res.json();
        if (data.success) {
            alert(`จัดเรียงเส้นทางสำเร็จสำหรับ ${data.processed_teams} ทีม`);
            loadJobs();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (e) {
        alert('Connection error');
    } finally {
        hideLoader();
    }
}
