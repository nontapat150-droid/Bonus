// assets/js/inventory.js

// View switching logic
function invTab(tabName) {
    document.querySelectorAll('.inv-view').forEach(el => {
        el.classList.add('hidden');
        el.classList.remove('block', 'animate__animated', 'animate__fadeIn');
    });
    
    document.querySelectorAll('.inv-tab').forEach(el => {
        el.classList.remove('bg-purple-100', 'text-purple-700', 'font-bold', 'shadow-sm');
        el.classList.add('text-gray-500', 'font-medium');
    });

    const view = document.getElementById(`view-${tabName}`);
    view.classList.remove('hidden');
    view.classList.add('block', 'animate__animated', 'animate__fadeIn');

    const activeBtn = document.getElementById(`tab-${tabName}`);
    activeBtn.classList.remove('text-gray-500', 'font-medium');
    activeBtn.classList.add('bg-purple-100', 'text-purple-700', 'font-bold', 'shadow-sm');

    if (tabName === 'overview') loadStockOverview();
    if (tabName === 'history') loadHistory();
}

// ----------------------------------------------------
// TAB 1: Stock Overview
// ----------------------------------------------------
let stockData = [];

async function loadStockOverview() {
    try {
        document.getElementById('stockTableBody').innerHTML = '<tr><td colspan="5" class="px-6 py-12 text-center text-gray-400"><div class="flex flex-col items-center justify-center"><div class="loader-spinner mb-4 w-8 h-8"></div> กำลังโหลดรายการสินค้า...</div></td></tr>';
        const res = await fetch('api/inventory/get_stock.php');
        const data = await res.json();

        if (data.success) {
            stockData = data.data;
            renderStockTable(stockData);
        } else {
            Toast.error('เกิดข้อผิดพลาด: ' + data.error);
        }
    } catch (e) {
        console.error(e);
        Toast.error('ไม่สามารถโหลดข้อมูลสต็อกได้');
    }
}

function renderStockTable(data) {
    const tbody = document.getElementById('stockTableBody');
    tbody.innerHTML = '';

    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-12 text-center text-gray-400 italic">ไม่พบข้อมูลสินค้าในคลัง</td></tr>';
        return;
    }

    data.forEach((item, index) => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors animate__animated animate__fadeIn';
        row.style.animationDelay = `${index * 0.03}s`;
        row.innerHTML = `
            <td class="px-6 py-4 font-mono text-xs text-slate-400">${item.product_code || '-'}</td>
            <td class="px-6 py-4 font-bold text-slate-800">${item.product_name}</td>
            <td class="px-6 py-4 text-slate-600">${item.model_name || 'วัสดุสิ้นเปลือง'}</td>
            <td class="px-6 py-4 text-center">
                <span class="inline-flex items-center justify-center px-4 py-1.5 rounded-full text-sm font-bold ${item.qty > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-rose-50 text-rose-700 border border-rose-100'}">
                    ${item.qty} ${item.qty > 0 ? 'ชิ้น' : 'หมด'}
                </span>
            </td>
            <td class="px-6 py-4 text-center">
                <button onclick="Toast.info('ฟีเจอร์ดูรายละเอียด/หมายเลขซีเรียลกำลังจะมาเร็วๆ นี้')" class="text-indigo-600 hover:text-indigo-800 text-sm font-bold transition-colors">🔍 ดูรายละเอียด</button>    
            </td>
        `;
        tbody.appendChild(row);
    });
}

document.getElementById('searchStock')?.addEventListener('input', (e) => {
    const term = e.target.value.toLowerCase();
    const filtered = stockData.filter(item =>
        item.product_name.toLowerCase().includes(term) ||
        (item.model_name && item.model_name.toLowerCase().includes(term)) ||
        (item.product_code && item.product_code.toLowerCase().includes(term))
    );
    renderStockTable(filtered);
});

// ----------------------------------------------------
// TAB 2: Inbound (FAST SCANNER & EXCEL)
// ----------------------------------------------------
let masterOptions = { sn_products: {}, consumables: [] };
let currentInboundMode = 'SN';
let typingTimerIn;

async function loadMasterOptions(selectedProd = '', selectedModel = '') {
    try {
        const res = await fetch('api/inventory/get_master.php');
        const data = await res.json();
        if (data.success) {
            masterOptions = data.data;
            buildMainDropdowns(selectedProd, selectedModel);
        }
    } catch (e) { console.error('Failed to load master options', e); }
}

window.setInboundMode = function(mode) {
    currentInboundMode = mode;
    const btnSn = document.getElementById('btnModeSn');
    const btnQty = document.getElementById('btnModeQty');
    
    // UI Update
    if (mode === 'SN') {
        btnSn.className = "px-4 py-2 rounded-full text-sm font-bold bg-white text-emerald-600 shadow-sm transition-all flex items-center";
        btnQty.className = "px-4 py-2 rounded-full text-sm font-medium text-gray-500 hover:text-gray-700 transition-all flex items-center";
        document.getElementById('areaInputSn')?.classList.remove('hidden');
        document.getElementById('areaModelSelect')?.classList.remove('hidden');
        document.getElementById('areaInputQty')?.classList.add('hidden');
    } else {
        btnQty.className = "px-4 py-2 rounded-full text-sm font-bold bg-white text-yellow-600 shadow-sm transition-all flex items-center";
        btnSn.className = "px-4 py-2 rounded-full text-sm font-medium text-gray-500 hover:text-gray-700 transition-all flex items-center";
        document.getElementById('areaInputQty')?.classList.remove('hidden');
        document.getElementById('areaModelSelect')?.classList.add('hidden');
        document.getElementById('areaInputSn')?.classList.add('hidden');
    }
    buildMainDropdowns();
};

window.buildMainDropdowns = function(selectedProd = '', selectedModel = '') {
    const pSelect = document.getElementById('mainProductSelect');
    if (!pSelect) return;
    
    let html = `<option value="">-- เลือกสินค้า --</option>`;
    
    if (currentInboundMode === 'SN') {
        Object.keys(masterOptions.sn_products).forEach(p => {
            html += `<option value="${p}" ${p === selectedProd ? 'selected' : ''}>${p}</option>`;
        });
        html += `<option value="_NEW_" class="text-emerald-600 font-bold">+ เพิ่มชื่อสินค้าใหม่</option>`;
    } else {
        masterOptions.consumables.forEach(c => {
            html += `<option value="${c.name}" data-unit="${c.unit}" ${c.name === selectedProd ? 'selected' : ''}>${c.name}</option>`;
        });
        html += `<option value="_NEW_" class="text-yellow-600 font-bold">+ เพิ่มวัสดุใหม่</option>`;
    }
    
    pSelect.innerHTML = html;
    handleMainProductChange();
    
    if (selectedModel && currentInboundMode === 'SN') {
        setTimeout(() => { document.getElementById('mainModelSelect').value = selectedModel; checkScanReady(); }, 50);
    }
};

window.handleMainProductChange = function() {
    const val = document.getElementById('mainProductSelect').value;
    const pInput = document.getElementById('mainProductInput');
    const mSelect = document.getElementById('mainModelSelect');
    const mInput = document.getElementById('mainModelInput');
    
    if (val === '_NEW_') {
        pInput.classList.remove('hidden'); pInput.value = '';
        if (currentInboundMode === 'SN') {
            mSelect.innerHTML = `<option value="_NEW_">+ สร้างรุ่นใหม่</option>`;
            mInput.classList.remove('hidden'); mInput.value = '';
        }
        document.getElementById('inboundUnit').value = 'ชิ้น';
    } else {
        pInput.classList.add('hidden');
        if (currentInboundMode === 'SN' && val) {
            let mHtml = `<option value="">-- เลือกรุ่น --</option>`;
            masterOptions.sn_products[val].forEach(m => mHtml += `<option value="${m}">${m}</option>`);
            mHtml += `<option value="_NEW_" class="text-emerald-600 font-bold">+ เพิ่มรุ่นใหม่</option>`;
            mSelect.innerHTML = mHtml;
        }
        
        // Auto-fill unit for consumables
        if (currentInboundMode === 'QTY' && val) {
            const opt = document.getElementById('mainProductSelect').options[document.getElementById('mainProductSelect').selectedIndex];
            document.getElementById('inboundUnit').value = opt.getAttribute('data-unit') || 'ชิ้น';
        }
    }
    handleMainModelChange();
};

window.handleMainModelChange = function() {
    const val = document.getElementById('mainModelSelect').value;
    const mInput = document.getElementById('mainModelInput');
    if (val === '_NEW_') { mInput.classList.remove('hidden'); mInput.value = ''; } 
    else { mInput.classList.add('hidden'); }
    checkScanReady();
};

window.checkScanReady = function() {
    const pVal = document.getElementById('mainProductSelect').value;
    const pReady = pVal === '_NEW_' ? document.getElementById('mainProductInput').value.trim() !== '' : pVal !== '';
    
    let isReady = false;
    if (currentInboundMode === 'SN') {
        const mVal = document.getElementById('mainModelSelect').value;
        const mReady = mVal === '_NEW_' ? document.getElementById('mainModelInput').value.trim() !== '' : mVal !== '';
        isReady = pReady && mReady;
    } else {
        isReady = pReady;
    }

    const scanInput = document.getElementById('scanInput');
    if (!scanInput) return;

    if (isReady && currentInboundMode === 'SN') {
        scanInput.disabled = false;
        scanInput.classList.replace('border-gray-300', 'border-emerald-500');
        scanInput.placeholder = 'พร้อมสแกนบาร์โค้ด...';
        scanInput.focus();
    } else {
        scanInput.disabled = true;
        scanInput.classList.replace('border-emerald-500', 'border-gray-300');
        scanInput.placeholder = 'เลือกข้อมูลให้ครบก่อนสแกน';
        scanInput.value = '';
    }
};

document.getElementById('mainProductInput')?.addEventListener('input', checkScanReady);
document.getElementById('mainModelInput')?.addEventListener('input', checkScanReady);

// Fast Scan Trigger
document.getElementById('scanInput')?.addEventListener('input', function(e) {
    clearTimeout(typingTimerIn);
    const sn = this.value.trim();
    if (sn.length > 5) {
        typingTimerIn = setTimeout(() => validateAndSaveSN(sn), 800);
    }
});

document.getElementById('scanInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault(); 
        clearTimeout(typingTimerIn);
        const sn = this.value.trim();
        if (sn) validateAndSaveSN(sn);
    }
});

async function validateAndSaveSN(sn) {
    const scanInput = document.getElementById('scanInput');
    scanInput.value = ''; // Clear instantly for next scan
    
    const pSel = document.getElementById('mainProductSelect').value;
    const pName = pSel === '_NEW_' ? document.getElementById('mainProductInput').value.trim() : pSel;
    const mSel = document.getElementById('mainModelSelect').value;
    const mName = mSel === '_NEW_' ? document.getElementById('mainModelInput').value.trim() : mSel;

    try {
        const res = await fetch('api/inventory/add_sn_fast.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ product_name: pName, model_name: mName, sn: sn })
        });
        const data = await res.json();
        
        if (data.success) {
            Toast.success(`เพิ่ม SN: ${sn} สำเร็จ!`);
            if (pSel === '_NEW_' || mSel === '_NEW_') loadMasterOptions(pName, mName);
            loadStockOverview(); // Refresh background
        } else if (data.status === 'duplicate') {
            Toast.error(`SN: ${sn} ซ้ำในระบบ!`);
        } else {
            Toast.error('Error: ' + data.error);
        }
    } catch (e) { Toast.error('การเชื่อมต่อล้มเหลว'); }
    
    setTimeout(() => scanInput.focus(), 50); // Refocus
}

window.saveInboundQty = async function() {
    const pSel = document.getElementById('mainProductSelect').value;
    const pName = pSel === '_NEW_' ? document.getElementById('mainProductInput').value.trim() : pSel;
    const qty = document.getElementById('inboundQty').value;
    const unit = document.getElementById('inboundUnit').value || 'ชิ้น';

    if (!pName || !qty || qty <= 0) return Toast.error('ระบุข้อมูลให้ครบถ้วน');

    try {
        const res = await fetch('api/inventory/add_qty.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ name: pName, qty: qty, unit: unit })
        });
        const data = await res.json();
        
        if (data.success) {
            Toast.success(`เพิ่ม ${pName} จำนวน ${qty} ${unit} สำเร็จ!`);
            document.getElementById('inboundQty').value = '';
            if (pSel === '_NEW_') loadMasterOptions(pName);
            loadStockOverview(); // Refresh background
        } else { Toast.error(data.error); }
    } catch (e) { Toast.error('การเชื่อมต่อล้มเหลว'); }
};

// ====================================================
// Excel Inbound (Smart Detect)
// ====================================================
let excelDataPayload = [];

document.getElementById('excelImport')?.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;

    Toast.info('กำลังอ่านข้อมูลจากไฟล์ Excel...');
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, {type: 'array'});
            const worksheet = workbook.Sheets[workbook.SheetNames[0]];
            const json = XLSX.utils.sheet_to_json(worksheet, {header: 1});

            excelDataPayload = [];
            let skipped = 0;

            const headerRow = json[0] ? json[0].map(h => String(h).toLowerCase().replace(/\s/g, '')) : [];
            
            // ค้นหาตำแหน่งคอลัมน์จากชื่อ Header อัตโนมัติ (Smart Detect)
            let cI = headerRow.findIndex(h => h.includes('code') || h.includes('รหัส'));
            let nI = headerRow.findIndex(h => h.includes('name') || h.includes('ชื่อ'));
            let mI = headerRow.findIndex(h => h.includes('model') || h.includes('รุ่น'));
            let sI = headerRow.findIndex(h => h.includes('sn') || h.includes('ซีเรียล') || h.includes('serial'));

            // ถ้าหา Header ไม่เจอเลย ให้เดาตำแหน่งตามลำดับ (Code, Name, Model, SN)
            if (cI === -1 && nI === -1 && mI === -1) {
                cI = 0; nI = 1; mI = 2; sI = 3;
            }
            
            // สำรอง: รองรับไฟล์ Excel แบบเก่าที่มีแค่ 3 คอลัมน์ (Name, Model, SN)
            if (cI === 0 && nI === -1 && json[0][0] && String(json[0][0]).toLowerCase().includes('name')) {
                cI = -1; nI = 0; mI = 1; sI = 2;
            }

            json.forEach((row, index) => {
                if (index === 0) return; // ข้ามบรรทัดหัวตาราง

                const pCode = cI !== -1 ? row[cI] : '';
                const pName = nI !== -1 ? row[nI] : '';
                const mName = mI !== -1 ? row[mI] : '';
                const sn = sI !== -1 ? row[sI] : '';

                if (pName && mName) {
                    excelDataPayload.push({
                        product_code: pCode ? String(pCode).trim() : '',
                        product_name: String(pName).trim(),
                        model_name: String(mName).trim(),
                        sn: sn ? String(sn).trim() : ''
                    });
                } else {
                    skipped++;
                }
            });

            const previewDiv = document.getElementById('excelPreview');
            const countP = document.getElementById('excelCount');

            if (excelDataPayload.length > 0) {
                countP.textContent = `พบข้อมูลพร้อมนำเข้า ${excelDataPayload.length} รายการ (ข้ามข้อมูลไม่สมบูรณ์ ${skipped} แถว)`;
                previewDiv.classList.remove('hidden');
                previewDiv.classList.add('animate__animated', 'animate__bounceIn');
                Toast.success('โหลดไฟล์สำเร็จ! กรุณากดปุ่มยืนยัน');
            } else {
                Toast.error('ไม่พบข้อมูลที่ถูกต้องในไฟล์');
                previewDiv.classList.add('hidden');
            }
        } catch (err) {
            Toast.error('ไฟล์ Excel รูปแบบไม่ถูกต้อง');
        }
    };
    reader.readAsArrayBuffer(file);
});

document.getElementById('confirmExcelBtn')?.addEventListener('click', async (e) => {
    if (excelDataPayload.length === 0) return;

    Loader.show();
    const btn = e.target;
    btn.disabled = true;

    try {
        const res = await fetch('api/inventory/import_inbound.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items: excelDataPayload })
        });
        const data = await res.json();

        if (data.success) {
            Toast.success(`นำเข้าสินค้าสำเร็จทั้งหมด ${data.imported} รายการ`);
            document.getElementById('excelPreview').classList.add('hidden');
            document.getElementById('excelImport').value = '';
            excelDataPayload = [];
            loadStockOverview(); // โหลดตารางใหม่
        } else {
            Toast.error('เกิดข้อผิดพลาด: ' + data.error);
        }
    } catch (err) {
        Toast.error('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
    } finally {
        Loader.hide();
        btn.disabled = false;
    }
});

// ----------------------------------------------------
// TAB 3: Outbound
// ----------------------------------------------------
let stagedOutbound = [];

document.getElementById('out_sn')?.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('addOutboundBtn').click();
    }
});

document.getElementById('addOutboundBtn')?.addEventListener('click', async () => {
    const snInput = document.getElementById('out_sn');
    const sn = snInput.value.trim();
    if (!sn) return;

    if (stagedOutbound.some(item => item.sn === sn)) {
        Toast.error('รายการนี้ถูกสแกนไว้ในคิวแล้ว');
        snInput.value = '';
        return;
    }

    try {
        const res = await fetch(`api/inventory/check_outbound.php?sn=${encodeURIComponent(sn)}`);
        const data = await res.json();

        if (data.success) {
            stagedOutbound.push(data.data);
            Toast.success(`เพิ่ม ${data.data.product_name} เข้าคิวเบิก`);
            renderStaging();
            snInput.value = '';
            snInput.focus();
        } else {
            Toast.error(data.error);
            snInput.select();
        }
    } catch (e) {
        Toast.error('เกิดข้อผิดพลาดในการตรวจสอบ');
    }
});

function renderStaging() {
    const tbody = document.getElementById('stagingTableBody');
    const badge = document.getElementById('outboundBadge');
    const confirmBtn = document.getElementById('confirmOutboundBtn');

    tbody.innerHTML = '';

    if (stagedOutbound.length === 0) {
        tbody.innerHTML = '<tr id="emptyStaging"><td colspan="3" class="px-6 py-12 text-center text-slate-400 italic">ยังไม่มีรายการสแกน รอการเบิกออก</td></tr>';
        badge.classList.add('hidden');
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = 'ยืนยันการเบิกออก (0 รายการ)';
        return;
    }

    badge.textContent = stagedOutbound.length;
    badge.classList.remove('hidden');
    confirmBtn.disabled = false;
    confirmBtn.innerHTML = `<span class="mr-2">📑</span> เปิดบิลและยืนยันการเบิก (${stagedOutbound.length} รายการ)`;

    stagedOutbound.forEach((item, index) => {
        const row = document.createElement('tr');
        row.className = 'animate__animated animate__fadeInRight';
        row.innerHTML = `
            <td class="px-6 py-3 font-mono font-bold text-indigo-600">${item.sn}</td>
            <td class="px-6 py-3 font-medium text-slate-700">${item.product_name} - ${item.model_name}</td>
            <td class="px-6 py-3 text-center">
                <button onclick="removeStaged(${index})" class="text-rose-500 hover:text-rose-700 font-bold transition-transform hover:scale-125">✕</button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

window.removeStaged = function(index) {
    stagedOutbound.splice(index, 1);
    Toast.info('ลบรายการออกจากคิวเรียบร้อย');
    renderStaging();
};

document.getElementById('confirmOutboundBtn')?.addEventListener('click', () => {
    const modal = document.getElementById('outboundModal');
    const billBody = document.getElementById('billTableBody');

    document.getElementById('billDate').textContent = new Date().toLocaleString('th-TH');
    document.getElementById('billTotal').textContent = `${stagedOutbound.length} รายการ`;

    billBody.innerHTML = '';
    stagedOutbound.forEach((item, i) => {
        billBody.innerHTML += `
            <tr class="border-b border-slate-100">
                <td class="py-3 text-slate-400 text-xs">${i+1}</td>
                <td class="py-3 font-medium text-slate-700">${item.product_name} <span class="text-slate-400 text-xs block">${item.model_name}</span></td>
                <td class="py-3 text-right font-mono text-sm text-indigo-600">${item.sn}</td>
            </tr>
        `;
    });

    modal.classList.remove('hidden');
    modal.querySelector('div').classList.add('animate__animated', 'animate__fadeInUp');
});

window.closeOutboundModal = function() {
    const modal = document.getElementById('outboundModal');
    modal.querySelector('div').classList.remove('animate__fadeInUp');
    modal.querySelector('div').classList.add('animate__fadeOutDown');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.querySelector('div').classList.remove('animate__fadeOutDown');
    }, 300);
};

document.getElementById('finalSubmitOutbound')?.addEventListener('click', async (e) => {
    if (stagedOutbound.length === 0) return;

    Loader.show();
    const btn = e.target;
    btn.disabled = true;

    const sns = stagedOutbound.map(i => i.sn);

    try {
        const res = await fetch('api/inventory/confirm_outbound.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sns })
        });
        const data = await res.json();

        if (data.success) {
            Toast.success(`ตัดสต็อกสำเร็จ ${data.processed} รายการ! ข้อมูลถูกบันทึกลงประวัติแล้ว`);
            stagedOutbound = [];
            renderStaging();
            closeOutboundModal();
            loadStockOverview();
        } else {
            Toast.error('เกิดข้อผิดพลาด: ' + data.error);
        }
    } catch (err) {
        Toast.error('ไม่สามารถติดต่อเซิร์ฟเวอร์ได้');
    } finally {
        Loader.hide();
        btn.disabled = false;
    }
});

// ----------------------------------------------------
// TAB 4: History
// ----------------------------------------------------
let historyData = [];

async function loadHistory() {
    try {
        document.getElementById('historyTableBody').innerHTML = '<tr><td colspan="5" class="px-6 py-12 text-center text-gray-400"><div class="flex flex-col items-center justify-center"><div class="loader-spinner mb-4 w-8 h-8"></div> กำลังโหลดประวัติ...</div></td></tr>';
        const res = await fetch('api/inventory/get_history.php');
        const data = await res.json();

        if (data.success) {
            historyData = data.data;
            renderHistoryTable();
        } else {
            Toast.error('โหลดประวัติล้มเหลว: ' + data.error);
        }
    } catch (e) {
        console.error(e);
    }
}

function renderHistoryTable() {
    const tbody = document.getElementById('historyTableBody');
    tbody.innerHTML = '';

    if (historyData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-12 text-center text-gray-400 italic">ยังไม่มีประวัติการทำรายการ</td></tr>';
        return;
    }

    historyData.forEach((item, index) => {
        const dateObj = new Date(item.timestamp);
        const formattedDate = dateObj.toLocaleDateString('th-TH') + ' ' + dateObj.toLocaleTimeString('th-TH');  
        const actionBadge = item.action === 'in'
            ? '<span class="px-3 py-1 bg-emerald-50 text-emerald-700 text-xs rounded-full font-bold border border-emerald-100">📥 รับเข้า</span>'
            : '<span class="px-3 py-1 bg-rose-50 text-rose-700 text-xs rounded-full font-bold border border-rose-100">📤 เบิกออก</span>';

        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors animate__animated animate__fadeIn';
        row.style.animationDelay = `${index * 0.02}s`;
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap text-slate-500 text-sm">${formattedDate}</td>
            <td class="px-6 py-4">${actionBadge}</td>
            <td class="px-6 py-4 font-mono text-xs font-bold text-indigo-600">${item.sn || '-'}</td>
            <td class="px-6 py-4 font-medium text-slate-700">${item.product_name} <span class="text-slate-400 font-normal">(${item.model_name || 'วัสดุสิ้นเปลือง'})</span></td>
            <td class="px-6 py-4 text-slate-600 text-sm">${item.admin_name || 'System'}</td>
        `;
        tbody.appendChild(row);
    });
}

document.getElementById('exportHistoryBtn')?.addEventListener('click', () => {
    if (historyData.length === 0) return Toast.error('ไม่มีข้อมูลสำหรับส่งออก');   

    Toast.info('กำลังเตรียมไฟล์ Excel...');
    const exportData = historyData.map(item => ({
        "วันที่/เวลา": new Date(item.timestamp).toLocaleString('th-TH'),
        "ประเภท": item.action === 'in' ? 'รับเข้า' : 'เบิกออก',
        "หมายเลขซีเรียล": item.sn || '-',
        "ชื่อสินค้า": item.product_name,
        "รุ่น": item.model_name || 'วัสดุสิ้นเปลือง',
        "ผู้ทำรายการ": item.admin_name || 'System'
    }));

    const worksheet = XLSX.utils.json_to_sheet(exportData);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "ประวัติ");
    XLSX.writeFile(workbook, `ประวัติคลังสินค้า_${new Date().getTime()}.xlsx`);
    Toast.success('ดาวน์โหลดไฟล์ประวัติเรียบร้อยแล้ว');
});

// INIT
document.addEventListener('DOMContentLoaded', () => {
    loadStockOverview();
    loadMasterOptions(); // โหลด Dropdown ล่วงหน้าให้พร้อมใช้
});