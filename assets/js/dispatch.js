// assets/js/dispatch.js

let map;
let markers = [];
let polylines = [];
let allJobs = [];
let currentTeams = []; // [{id, team_name}]

// Define column mapping (Initial defaults)
const COL = {
    PLAN_ARRIVAL: 0, ACCESS: 1, CUSTOMER: 2, PHONE: 3, PACKAGE: 4,
    ADDRESS: 5, STATUS: 8, PRODUCT: 9, LAT: 10, LNG: 11,
    ORDER_NO: 12, TASK_ORDER: 15, TASK_TYPE: 23, REMARK: 44
};

const teamColors = [
    '#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
    '#ec4899', '#14b8a6', '#f97316', '#4f46e5', '#06b6d4'
];
function getColor(index) { return teamColors[index % teamColors.length]; }

document.addEventListener('DOMContentLoaded', () => {
    initMap();
    loadJobs();

    if (IS_ADMIN) {
        document.getElementById('jobExcelFile')?.addEventListener('change', handleExcelUpload);
        document.getElementById('addTeamBtn')?.addEventListener('click', handleAddTeam);
        document.getElementById('dispatchModalBtn')?.addEventListener('click', openDispatchModal);
        document.getElementById('confirmDispatchBtn')?.addEventListener('click', runAutoDispatch);
        document.getElementById('optimizeRouteBtn')?.addEventListener('click', runOptimizeRoute);
        document.getElementById('teamFilter')?.addEventListener('change', renderUI);
    }
});

function initMap() {
    map = L.map('map', { zoomControl: false }).setView([13.7563, 100.5018], 6);
    L.control.zoom({ position: 'bottomright' }).addTo(map);
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
            currentTeams = data.teams || [];

            if (IS_ADMIN) {
                const filter = document.getElementById('teamFilter');
                filter.innerHTML = '<option value="all">📍 งานทั้งหมด</option><option value="unassigned">⏳ ยังไม่จ่ายงาน</option>';
                currentTeams.forEach(t => {
                    filter.innerHTML += `<option value="${t.id}">👥 ${t.team_name}</option>`;
                });
                renderTeamList();
            }

            if (!IS_ADMIN && allJobs.length > 0) {
                const link = allJobs.find(j => j.map_link)?.map_link;
                if (link) {
                    const linkBtn = document.getElementById('techMapLink');
                    linkBtn.href = link;
                    linkBtn.classList.remove('hidden');
                }
            }
            renderUI();
        }
    } catch (e) {
        Toast.error('เชื่อมต่อล้มเหลว');
    } finally {
        hideLoader();
    }
}

function renderTeamList() {
    const container = document.getElementById('teamListContainer');
    if (currentTeams.length === 0) {
        container.innerHTML = '<p class="text-xs text-slate-400 font-medium italic ml-2">ยังไม่มีทีมในระบบ กรุณาเพิ่มทีมเพื่อจ่ายงาน...</p>';
        return;
    }
    container.innerHTML = '';
    currentTeams.forEach((t, i) => {
        const div = document.createElement('div');
        div.className = 'flex items-center bg-white border border-slate-100 p-3 rounded-2xl shadow-sm space-x-4 animate__animated animate__bounceIn';
        div.innerHTML = `
            <div class="w-10 h-10 rounded-xl flex items-center justify-center font-black text-white text-sm shadow-sm" style="background-color: ${getColor(i)}">
                ${t.team_name.charAt(0).toUpperCase()}
            </div>
            <div class="flex-1">
                <p class="text-xs font-black text-slate-700 uppercase tracking-tight">${t.team_name}</p>
                <p class="text-[9px] text-slate-400 font-bold">ทีมช่างประจำระบบ</p>
            </div>
            <button onclick="handleDeleteTeam(${t.id})" class="text-slate-300 hover:text-rose-500 transition-colors p-2">✕</button>
        `;
        container.appendChild(div);
    });
}

function openDispatchModal() {
    const unassignedJobs = allJobs.filter(j => !j.team_id).length;
    if (unassignedJobs === 0) return Toast.error('ไม่มีงานที่รอจ่ายในระบบ');

    document.getElementById('unassignedCount').textContent = unassignedJobs;
    const container = document.getElementById('dispatchTeamList');
    container.innerHTML = '';

    if (currentTeams.length === 0) {
        container.innerHTML = '<p class="text-center text-slate-500 py-4 font-bold">กรุณาเพิ่มทีมในระบบก่อนทำการจ่ายงาน</p>';
        document.getElementById('confirmDispatchBtn').disabled = true;
    } else {
        document.getElementById('confirmDispatchBtn').disabled = false;
        currentTeams.forEach((t, i) => {
            const div = document.createElement('div');
            div.className = 'flex items-center justify-between p-4 bg-slate-50 rounded-2xl mb-3';
            div.innerHTML = `
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-black text-xs" style="background-color: ${getColor(i)}">${t.team_name.charAt(0)}</div>
                    <span class="font-bold text-slate-700">${t.team_name}</span>
                </div>
                <div class="flex items-center space-x-2">
                    <input type="number" id="dist-quota-${t.id}" value="0" min="0" max="${unassignedJobs}" class="w-20 px-3 py-2 rounded-xl border-slate-200 text-center font-black text-indigo-600 focus:ring-indigo-500">
                    <span class="text-xs font-bold text-slate-400">งาน</span>
                </div>
            `;
            container.appendChild(div);
        });
    }

    const modal = document.getElementById('dispatchModal');
    modal.classList.remove('hidden');
    modal.querySelector('div').classList.add('animate__zoomIn');
}

function closeDispatchModal() {
    const modal = document.getElementById('dispatchModal');
    modal.querySelector('div').classList.remove('animate__zoomIn');
    modal.querySelector('div').classList.add('animate__zoomOut');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.querySelector('div').classList.remove('animate__zoomOut');
    }, 300);
}

async function handleAddTeam() {
    const input = document.getElementById('newTeamName');
    const name = input.value.trim();
    if (!name) return Toast.error('กรุณาระบุชื่อทีม');

    Loader.show();
    try {
        const res = await fetch('api/dispatch/teams/save_team.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ team_name: name })
        });
        const data = await res.json();
        if (data.success) {
            Toast.success('เพิ่มทีมใหม่สำเร็จ');
            input.value = '';
            loadJobs();
        } else {
            Toast.error(data.error);
        }
    } catch (e) {
        Toast.error('เชื่อมต่อล้มเหลว');
    } finally {
        Loader.hide();
    }
}

async function handleDeleteTeam(id) {
    if (!confirm('ลบทีมนี้? งานที่จ่ายไปแล้วจะกลับไปรอจ่ายใหม่')) return;
    Loader.show();
    try {
        const res = await fetch('api/dispatch/teams/delete_team.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) {
            Toast.success('ลบทีมเรียบร้อย');
            loadJobs();
        } else {
            Toast.error(data.error);
        }
    } catch (e) {
        Toast.error('เชื่อมต่อล้มเหลว');
    } finally {
        Loader.hide();
    }
}

async function runAutoDispatch() {
    const quotas = [];
    currentTeams.forEach(t => {
        const val = parseInt(document.getElementById(`dist-quota-${t.id}`).value) || 0;
        if (val > 0) {
            quotas.push({ team_name: t.team_name, limit: val });
        }
    });

    if (quotas.length === 0) return Toast.error('กรุณาระบุจำนวนงานให้ทีมอย่างน้อย 1 ทีม');

    closeDispatchModal();
    Loader.show();
    try {
        const res = await fetch('api/dispatch/auto_assign.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ quotas })
        });
        const data = await res.json();
        if (data.success) {
            Toast.success(`จ่ายงานสำเร็จทั้งหมด ${data.assigned} รายการ!`);
            loadJobs();
        } else {
            Toast.error(data.error);
        }
    } catch (e) {
        Toast.error('จ่ายงานล้มเหลว');
    } finally {
        Loader.hide();
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
        container.innerHTML = '<div class="text-center text-slate-400 py-12">ไม่พบข้อมูลงาน</div>';
        return;
    }

    const bounds = L.latLngBounds();
    const jobsByTeam = {};

    filteredJobs.forEach((job, index) => {
        const card = createJobCard(job, index);
        container.appendChild(card);

        if (job.team_id && job.lat && job.lng) {
            if (!jobsByTeam[job.team_id]) jobsByTeam[job.team_id] = [];
            jobsByTeam[job.team_id].push(job);
        }

        if (job.lat && job.lng) {
            const latLng = [parseFloat(job.lat), parseFloat(job.lng)];
            bounds.extend(latLng);

            const teamIdx = currentTeams.findIndex(t => t.id == job.team_id);
            const color = job.team_id ? getColor(teamIdx >= 0 ? teamIdx : 0) : '#94a3b8';
            
            const markerHtml = `
                <div style="background-color: ${color}; width: 28px; height: 28px; border-radius: 8px; color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 11px; border: 2px solid white; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);">
                    ${job.seq || '-'}
                </div>
            `;
            const icon = L.divIcon({ html: markerHtml, className: '', iconSize: [28, 28], iconAnchor: [14, 14] });
            L.marker(latLng, { icon }).addTo(map).bindPopup(`<b>${job.access_no}</b><br>${job.customer}<br>📞 ${job.phone || '-'}`);
        }
    });

    currentTeams.forEach((t, i) => {
        if (jobsByTeam[t.id]) {
            jobsByTeam[t.id].sort((a,b) => (a.seq || 999) - (b.seq || 999));
            const latlngs = jobsByTeam[t.id].map(j => [parseFloat(j.lat), parseFloat(j.lng)]);
            if (latlngs.length > 1) {
                L.polyline(latlngs, { color: getColor(i), weight: 4, opacity: 0.6, dashArray: '8, 8' }).addTo(map);
            }
        }
    });

    if (filteredJobs.length > 0 && bounds.isValid()) {
        map.fitBounds(bounds, { padding: [40, 40] });
    }
}

function createJobCard(job, index) {
    const div = document.createElement('div');
    div.className = 'bg-white border border-slate-100 rounded-2xl p-4 shadow-sm hover:shadow-md transition-all relative cursor-pointer animate__animated animate__fadeInLeft';
    div.style.animationDelay = `${index * 0.05}s`;

    let badge = job.team_name 
        ? `<span class="absolute top-4 right-4 bg-indigo-50 text-indigo-600 text-[9px] px-2 py-0.5 rounded-full font-black border border-indigo-100 uppercase">${job.team_name}</span>`
        : `<span class="absolute top-4 right-4 bg-slate-50 text-slate-400 text-[9px] px-2 py-0.5 rounded-full font-black border border-slate-100 italic">รอจ่ายงาน</span>`;

    div.innerHTML = `
        ${badge}
        <div class="flex items-center space-x-3 mb-2">
            <span class="bg-slate-800 text-white w-7 h-7 rounded-lg flex items-center justify-center text-[10px] font-black">${job.seq || '-'}</span>
            <span class="font-extrabold text-slate-800 text-sm tracking-tight">${job.access_no}</span>
        </div>
        <p class="text-xs text-slate-600 font-bold mb-1">${job.customer}</p>
        <p class="text-[10px] text-slate-400 line-clamp-1 mb-2">📍 ${job.address}</p>
        ${job.phone ? `<div class="flex flex-wrap gap-1 mt-2">${job.phone.split(',').map(p => `<span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 rounded text-[9px] font-bold">📞 ${p.trim()}</span>`).join('')}</div>` : ''}
    `;
    
    div.onclick = () => {
        if (job.lat && job.lng) {
            map.flyTo([parseFloat(job.lat), parseFloat(job.lng)], 15);
            Toast.info(`พิกัดงานของ ${job.customer}`);
        }
    };
    return div;
}

function clearMap() {
    map.eachLayer(l => { if (l instanceof L.Marker || l instanceof L.Polyline) map.removeLayer(l); });
}

function handleExcelUpload(e) {
    const file = e.target.files[0];
    if (!file) return;

    Toast.info('กำลังประมวลผลไฟล์ Excel...');
    Loader.show();
    const reader = new FileReader();
    reader.onload = async function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, {type: 'array'});
            const worksheet = workbook.Sheets[workbook.SheetNames[0]];
            const rows = XLSX.utils.sheet_to_json(worksheet, {header: 1});

            if (rows.length < 2) throw new Error('ไฟล์ว่างเปล่า');

            const headerRow = rows[0].map(h => String(h).toLowerCase().replace(/\s/g, ''));
            const findCol = (keys) => headerRow.findIndex(h => keys.some(k => h.includes(k)));

            // Improved phone search: find all columns that might contain phone numbers
            const phoneCols = [];
            headerRow.forEach((h, idx) => {
                if (h.includes('phone') || h.includes('tel') || h.includes('เบอร์') || h.includes('mobile')) {
                    phoneCols.push(idx);
                }
            });

            const accessIdx = findCol(['access', 'รหัสงาน']);
            const latIdx = findCol(['lat', 'ละติจูด']);
            const lngIdx = findCol(['lng', 'ลองจิจูด']);
            const custIdx = findCol(['customer', 'ชื่อลูกค้า']);
            const addrIdx = findCol(['address', 'ที่อยู่']);

            if (accessIdx === -1 || latIdx === -1 || lngIdx === -1) {
                throw new Error('ไม่พบหัวคอลัมน์ที่จำเป็น (Access No, Lat, Lng)');
            }

            const parsedJobs = [];
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const accessNo = row[accessIdx];
                const lat = row[latIdx];
                const lng = row[lngIdx];

                if (accessNo && lat && lng) {
                    // Combine all phone numbers from identified columns
                    let phones = [];
                    phoneCols.forEach(pIdx => {
                        if (row[pIdx]) phones.push(String(row[pIdx]).trim());
                    });
                    
                    // Also check for common delimiters within a single cell
                    let combinedPhone = phones.join(',');
                    let cleanPhone = combinedPhone.split(/[\/,|\s]+/).filter(p => p.length > 5).join(',');

                    parsedJobs.push({
                        access_no: String(accessNo),
                        customer: row[custIdx] || 'ไม่ระบุชื่อ',
                        phone: cleanPhone,
                        address: row[addrIdx] || '-',
                        lat: String(lat).replace(/[^0-9.-]/g, ''), 
                        lng: String(lng).replace(/[^0-9.-]/g, ''),
                        status: 'Pending'
                    });
                }
            }

            if (parsedJobs.length === 0) throw new Error('ไม่พบข้อมูลงานที่มีพิกัดถูกต้อง');

            if (confirm(`พบข้อมูลพร้อมพิกัด ${parsedJobs.length} รายการ\nนำเข้าข้อมูลใหม่และล้างข้อมูลเดิม?`)) {
                const res = await fetch('api/dispatch/upload_jobs.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ jobs: parsedJobs })
                });
                const rData = await res.json();
                if (rData.success) {
                    Toast.success(`นำเข้าสำเร็จ ${rData.imported} งาน!`);
                    loadJobs();
                } else {
                    Toast.error(rData.error);
                }
            }
        } catch (err) {
            Toast.error(err.message);
        } finally {
            Loader.hide();
            document.getElementById('jobExcelFile').value = '';
        }
    };
    reader.readAsArrayBuffer(file);
}

async function runOptimizeRoute() {
    Loader.show();
    try {
        const res = await fetch('api/dispatch/optimize_route.php');
        const data = await res.json();
        if (data.success) {
            Toast.success('จัดลำดับเส้นทางอัจฉริยะเรียบร้อย!');
            loadJobs();
        } else {
            Toast.error(data.error);
        }
    } catch (e) {
        Toast.error('เชื่อมต่อล้มเหลว');
    } finally {
        Loader.hide();
    }
}