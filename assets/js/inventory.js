// assets/js/inventory.js

// ====================================================
// 1. ตั้งค่าระบบแจ้งเตือน (Toast & Loader)
// ====================================================
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});

// Helper Function เรียกใช้ Toast ให้ง่ายขึ้น
Toast.success = (msg) => Toast.fire({ icon: 'success', title: msg });
Toast.error = (msg) => Toast.fire({ icon: 'error', title: msg });
Toast.info = (msg) => Toast.fire({ icon: 'info', title: msg });
Toast.warning = (msg) => Toast.fire({ icon: 'warning', title: msg });

const Loader = {
    show: () => Swal.fire({ title: 'กำลังประมวลผล...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } }),
    hide: () => Swal.close()
};


// ====================================================
// 2. ระบบนำทาง Tabs
// ====================================================
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
    if (view) {
        view.classList.remove('hidden');
        view.classList.add('block', 'animate__animated', 'animate__fadeIn');
    }

    const activeBtn = document.getElementById(`tab-${tabName}`);
    if (activeBtn) {
        activeBtn.classList.remove('text-gray-500', 'font-medium');
        activeBtn.classList.add('bg-purple-100', 'text-purple-700', 'font-bold', 'shadow-sm');
    }

    if (tabName === 'overview') loadStockOverview();
    if (tabName === 'history') loadHistory();
}

// ====================================================
// 3. TAB 1: คลังสินค้า (Overview)
// ====================================================
let stockData = [];

async function loadStockOverview() {
    try {
        const tbody = document.getElementById('stockTableBody');
        if(tbody) tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-12 text-center text-gray-400"><div class="flex flex-col items-center justify-center"><div class="loader-spinner mb-4 w-8 h-8"></div> กำลังโหลดรายการสินค้า...</div></td></tr>';
        
        const res = await fetch('api/inventory/get_stock.php');
        const data = await res.json();

        if (data.success) {
            stockData = data.data;
            renderStockTable(stockData);
        } else {
            Toast.error('เกิดข้อผิดพลาดในการโหลดคลัง: ' + data.error);
        }
    } catch (e) {
        console.error(e);
        Toast.error('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์เพื่อโหลดสต็อกได้');
    }
}

function renderStockTable(data) {
    const tbody = document.getElementById('stockTableBody');
    if(!tbody) return;
    tbody.innerHTML = '';

    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-12 text-center text-gray-400 italic">ไม่พบข้อมูลสินค้าในคลัง</td></tr>';
        return;
    }

    data.forEach((item, index) => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors animate__animated animate__fadeIn';
        row.style.animationDelay = `${index * 0.03}s`;
        
        let actionContent = '<span class="text-xs text-slate-300 italic">วัสดุสิ้นเปลือง / ไม่มี SN</span>';
        if (item.sn_list) {
            const snsCount = item.sn_list.split(',').length;
            actionContent = `<button onclick="openSnModal(this)" data-pname="${item.product_name}" data-mname="${item.model_name}" data-sns="${item.sn_list}" class="text-indigo-600 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 px-4 py-2 rounded-lg text-xs font-bold transition-colors">🔍 กดดู SN ทั้งหมด (${snsCount})</button>`;
        }

        row.innerHTML = `
            <td class="px-6 py-4 font-mono text-xs text-slate-400">${item.product_code || '-'}</td>
            <td class="px-6 py-4 font-bold text-slate-800">${item.product_name}</td>
            <td class="px-6 py-4 text-slate-600">${item.model_name || 'วัสดุสิ้นเปลือง'}</td>
            <td class="px-6 py-4 text-center">
                <span class="inline-flex items-center justify-center px-4 py-1.5 rounded-full text-sm font-bold ${item.qty > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-rose-50 text-rose-700 border border-rose-100'}">
                    ${item.qty} ${item.qty > 0 ? (item.unit || 'ชิ้น') : 'หมด'}
                </span>
            </td>
            <td class="px-6 py-4 text-center">
                ${actionContent}
            </td>
             <td class="px-6 py-4 text-center">
                 <button onclick="deleteInventoryItem('${item.product_name}', '${item.model_name || ''}')" class="text-red-500 hover:text-red-700" title="ลบสินค้านี้">🗑️</button>
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
        (item.product_code && item.product_code.toLowerCase().includes(term)) ||
        (item.sn_list && item.sn_list.toLowerCase().includes(term))
    );
    renderStockTable(filtered);
});

async function deleteInventoryItem(product_name, model_name) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: `คุณต้องการลบ ${product_name} ${model_name} ออกจากคลังใช่หรือไม่?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'ใช่, ลบเลย!',
        cancelButtonText: 'ยกเลิก'
    }).then(async (result) => {
        if (result.isConfirmed) {
            Loader.show();
            try {
                const res = await fetch('api/inventory/delete_item.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ product_name, model_name })
                });
                const data = await res.json();
                Loader.hide();
                if (data.success) {
                    Toast.success('ลบรายการสินค้าเรียบร้อยแล้ว');
                    loadStockOverview();
                    loadMasterOptions(); // อัปเดต Dropdown ด้วย
                } else {
                    Toast.error('เกิดข้อผิดพลาด: ' + data.error);
                }
            } catch (e) {
                Loader.hide();
                Toast.error('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
            }
        } else {
             Toast.info('ยกเลิกการลบรายการ');
        }
    });
}

async function deleteAllInventory() {
     Swal.fire({
        title: 'คุณแน่ใจหรือไม่?',
        text: `ต้องการล้างข้อมูลคลังสินค้า "ทั้งหมด" การกระทำนี้ไม่สามารถกู้คืนได้!`,
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'ใช่, ล้างคลังทั้งหมด!',
        cancelButtonText: 'ยกเลิก'
    }).then(async (result) => {
        if (result.isConfirmed) {
            Loader.show();
            try {
                const res = await fetch('api/inventory/delete_all.php', { method: 'POST' });
                const data = await res.json();
                Loader.hide();
                if (data.success) {
                    Toast.success('ล้างคลังสินค้าทั้งหมดเรียบร้อยแล้ว');
                    loadStockOverview();
                    loadMasterOptions();
                } else {
                    Toast.error('เกิดข้อผิดพลาด: ' + data.error);
                }
            } catch (e) {
                Loader.hide();
                Toast.error('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
            }
        } else {
             Toast.info('ยกเลิกการล้างคลังสินค้า');
        }
    });
}


// --- ระบบ SN Modal ---
let currentModalSns = []; 

window.openSnModal = function(btnElement) {
    const pName = btnElement.getAttribute('data-pname');
    const mName = btnElement.getAttribute('data-mname');
    const snString = btnElement.getAttribute('data-sns');

    document.getElementById('snModalProductName').textContent = pName;
    document.getElementById('snModalModelName').textContent = `รุ่น (Model): ${mName || '-'}`;
    
    currentModalSns = snString.split(',').map(sn => sn.trim()).filter(sn => sn !== '');
    
    document.getElementById('searchSnInModal').value = ''; 
    renderSnModalList(currentModalSns); 

    const modal = document.getElementById('snListModal');
    modal.classList.remove('hidden');
};

window.closeSnModal = function() {
    const modal = document.getElementById('snListModal');
    modal.classList.add('hidden');
};

window.renderSnModalList = function(snArray) {
    const container = document.getElementById('snModalListContainer');
    const countLabel = document.getElementById('snModalCount');
    
    container.innerHTML = '';
    countLabel.textContent = `รวม ${snArray.length} รายการ`;

    if (snArray.length === 0) {
        container.innerHTML = '<div class="col-span-2 text-center py-8 text-slate-400 italic">ไม่พบหมายเลข SN ที่ค้นหา</div>';
        return;
    }

    snArray.forEach(sn => {
        const div = document.createElement('div');
        div.className = 'bg-slate-50 border border-slate-200 px-3 py-2.5 rounded-lg text-sm font-mono text-indigo-700 font-bold text-center flex items-center justify-center hover:bg-indigo-50 transition-colors shadow-sm';
        div.textContent = sn;
        container.appendChild(div);
    });
};

document.getElementById('searchSnInModal')?.addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase().trim();
    const filteredSns = currentModalSns.filter(sn => sn.toLowerCase().includes(term));
    renderSnModalList(filteredSns);
});

// ====================================================
// 4. TAB 2: นำเข้า (Inbound)
// ====================================================
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
            
            // Populate Consumables ในช่องหน้า Outbound ด้วย
            const outConsSelect = document.getElementById('outboundConsumableSelect');
            if (outConsSelect) {
                let html = '<option value="">-- เลือกวัสดุ --</option>';
                masterOptions.consumables.forEach(c => {
                    html += `<option value="${c.consumable_id}" data-qty="${c.qty}" data-unit="${c.unit}">${c.name} (คงเหลือ: ${c.qty} ${c.unit})</option>`;
                });
                outConsSelect.innerHTML = html;
            }
        }
    } catch (e) { console.error('Failed to load master options', e); }
}

window.setInboundMode = function(mode) {
    currentInboundMode = mode;
    const btnSn = document.getElementById('btnModeSn');
    const btnQty = document.getElementById('btnModeQty');
    
    const areaSn = document.getElementById('areaInputSn');
    const areaQty = document.getElementById('areaInputQty');
    const areaModel = document.getElementById('areaModelSelect');
    
    if(btnSn && btnQty) {
        [btnSn, btnQty].forEach(btn => {
            btn.className = "px-4 py-2 rounded-full text-sm font-medium text-gray-500 hover:text-gray-700 transition-all flex items-center";
        });
    }
    
    [areaSn, areaQty].forEach(area => area?.classList.add('hidden'));

    if (mode === 'SN' && btnSn) {
        btnSn.className = "px-4 py-2 rounded-full text-sm font-bold bg-white text-emerald-600 shadow-sm transition-all flex items-center";
        areaSn?.classList.remove('hidden');
        areaModel?.classList.remove('hidden');
    } else if (mode === 'QTY' && btnQty) {
        btnQty.className = "px-4 py-2 rounded-full text-sm font-bold bg-white text-yellow-600 shadow-sm transition-all flex items-center";
        areaQty?.classList.remove('hidden');
        areaModel?.classList.add('hidden');
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
        setTimeout(() => { 
            const mSelect = document.getElementById('mainModelSelect');
            if(mSelect) mSelect.value = selectedModel; 
            checkScanReady(); 
        }, 50);
    }
};

window.handleMainProductChange = function() {
    const val = document.getElementById('mainProductSelect').value;
    const pInput = document.getElementById('mainProductInput');
    const mSelect = document.getElementById('mainModelSelect');
    const mInput = document.getElementById('mainModelInput');
    
    if (val === '_NEW_') {
        pInput?.classList.remove('hidden'); 
        if(pInput) pInput.value = '';
        
        if (currentInboundMode === 'SN' && mSelect) {
            mSelect.innerHTML = `<option value="_NEW_">+ สร้างรุ่นใหม่</option>`;
            mInput?.classList.remove('hidden'); 
            if(mInput) mInput.value = '';
        }
        const unitInput = document.getElementById('inboundUnit');
        if(unitInput) unitInput.value = 'ชิ้น';
    } else {
        pInput?.classList.add('hidden');
        if (currentInboundMode === 'SN' && val && mSelect) {
            let mHtml = `<option value="">-- เลือกรุ่น --</option>`;
            if(masterOptions.sn_products[val]) {
                masterOptions.sn_products[val].forEach(m => mHtml += `<option value="${m}">${m}</option>`);
            }
            mHtml += `<option value="_NEW_" class="text-emerald-600 font-bold">+ เพิ่มรุ่นใหม่</option>`;
            mSelect.innerHTML = mHtml;
        }
        
        if (currentInboundMode === 'QTY' && val) {
            const pSel = document.getElementById('mainProductSelect');
            const opt = pSel.options[pSel.selectedIndex];
            const unitInput = document.getElementById('inboundUnit');
            if(unitInput) unitInput.value = opt.getAttribute('data-unit') || 'ชิ้น';
        }
    }
    handleMainModelChange();
};

window.handleMainModelChange = function() {
    const mSelect = document.getElementById('mainModelSelect');
    const mInput = document.getElementById('mainModelInput');
    if(!mSelect || !mInput) return;
    
    const val = mSelect.value;
    if (val === '_NEW_') { 
        mInput.classList.remove('hidden'); 
        mInput.value = ''; 
    } else { 
        mInput.classList.add('hidden'); 
    }
    checkScanReady();
};

window.checkScanReady = function() {
    const pSel = document.getElementById('mainProductSelect');
    if(!pSel) return;
    
    const pVal = pSel.value;
    const pReady = pVal === '_NEW_' ? document.getElementById('mainProductInput').value.trim() !== '' : pVal !== '';
    
    let isReady = false;
    if (currentInboundMode === 'SN') {
        const mSel = document.getElementById('mainModelSelect');
        const mVal = mSel ? mSel.value : '';
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

document.getElementById('scanInput')?.addEventListener('input', function(e) {
    clearTimeout(typingTimerIn);
    const sn = this.value.trim();
    if (sn.length > 5 && currentInboundMode === 'SN') {
        typingTimerIn = setTimeout(() => validateAndSaveSN(sn), 800);
    }
});

document.getElementById('scanInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && currentInboundMode === 'SN') {
        e.preventDefault(); 
        clearTimeout(typingTimerIn);
        const sn = this.value.trim();
        if (sn) validateAndSaveSN(sn);
    }
});

async function validateAndSaveSN(sn) {
    const scanInput = document.getElementById('scanInput');
    scanInput.value = '';
    
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
            loadStockOverview();
        } else if (data.status === 'duplicate') {
            Toast.error(`SN: ${sn} ซ้ำในระบบ!`);
        } else {
            Toast.error('Error: ' + data.error);
        }
    } catch (e) { Toast.error('การเชื่อมต่อล้มเหลว'); }
    
    setTimeout(() => scanInput.focus(), 50);
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
            loadStockOverview();
        } else { Toast.error(data.error); }
    } catch (e) { Toast.error('การเชื่อมต่อล้มเหลว'); }
};

// --- นำเข้า Excel ---
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
            let cI = headerRow.findIndex(h => h.includes('code') || h.includes('รหัส'));
            let nI = headerRow.findIndex(h => h.includes('name') || h.includes('ชื่อ'));
            let mI = headerRow.findIndex(h => h.includes('model') || h.includes('รุ่น'));
            let sI = headerRow.findIndex(h => h.includes('sn') || h.includes('ซีเรียล') || h.includes('serial'));

            if (cI === -1 && nI === -1 && mI === -1) { cI = 0; nI = 1; mI = 2; sI = 3; }
            if (cI === 0 && nI === -1 && json[0][0] && String(json[0][0]).toLowerCase().includes('name')) { cI = -1; nI = 0; mI = 1; sI = 2; }

            json.forEach((row, index) => {
                if (index === 0) return;

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
            loadStockOverview();
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


// ====================================================
// 5. TAB 3: เบิกออก (Outbound) - อัปเดตล่าสุด
// ====================================================
let stagedOutbound = [];

// 5.1 โหลดรายชื่อผู้รับของ และผูกทีม
async function loadOutboundTargets() {
    try {
        const res = await fetch('api/inventory/get_outbound_targets.php');
        const data = await res.json();
        if (data.success) {
            let html = '<option value="">-- เลือกช่างผู้รับของ --</option>';
            data.users.forEach(u => {
                const teamInfo = u.team_name ? `(ทีม: ${u.team_name})` : '(ไม่มีสังกัดทีม)';
                html += `<option value="${u.id}">${u.full_name} ${teamInfo}</option>`;
            });
            
            const outSelect = document.getElementById('outboundTargetSelect');
            if (outSelect) outSelect.innerHTML = html;
            
            const trSelect = document.getElementById('transferTargetSelect');
            if (trSelect) trSelect.innerHTML = html;
        }
    } catch (e) {
        console.error('Failed to load targets', e);
    }
}

// 5.2 การสแกนเบิกออกแบบ SN
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

    if (stagedOutbound.some(item => item.type === 'sn' && item.sn === sn)) {
        Toast.warning('รายการสินค้านี้ถูกสแกนไว้ในคิวแล้ว!');
        snInput.value = '';
        return;
    }

    try {
        const res = await fetch(`api/inventory/check_outbound.php?sn=${encodeURIComponent(sn)}`);
        const data = await res.json();

        if (data.success) {
            stagedOutbound.push({
                type: 'sn',
                sn: data.data.sn,
                product_name: data.data.product_name,
                model_name: data.data.model_name
            });
            Toast.success(`เพิ่ม ${data.data.product_name} เข้าคิวเบิกเรียบร้อย`);
            renderStaging();
            snInput.value = '';
            snInput.focus();
        } else {
            Toast.error('สแกนไม่สำเร็จ: ' + data.error);
            snInput.select();
        }
    } catch (e) {
        Toast.error('เกิดข้อผิดพลาดในการตรวจสอบ SN');
    }
});

// 5.3 การเบิกวัสดุสิ้นเปลือง (QTY)
document.getElementById('addOutboundConsumableBtn')?.addEventListener('click', () => {
    const select = document.getElementById('outboundConsumableSelect');
    const qtyInput = document.getElementById('outboundConsumableQty');
    const consumable_id = select.value;
    const qty = parseFloat(qtyInput.value);

    if (!consumable_id || isNaN(qty) || qty <= 0) {
        Toast.warning('กรุณาเลือกวัสดุและระบุจำนวนให้ถูกต้อง');
        return;
    }

    const selectedOption = select.options[select.selectedIndex];
    const product_name = selectedOption.text.split(' (คงเหลือ')[0]; 
    const unit = selectedOption.getAttribute('data-unit') || 'ชิ้น';
    const maxQty = parseFloat(selectedOption.getAttribute('data-qty') || 0);

    if (qty > maxQty) {
        Toast.error(`จำนวนในคลังมีไม่พอ (เหลือเพียง ${maxQty} ${unit})`);
        return;
    }

    const existingIdx = stagedOutbound.findIndex(item => item.type === 'consumable' && item.consumable_id === consumable_id);
    
    if (existingIdx !== -1) {
        const newQty = stagedOutbound[existingIdx].qty + qty;
        if (newQty > maxQty) {
            Toast.error(`รวมกับในคิวแล้วเกินจำนวนที่มีในคลัง!`);
            return;
        }
        stagedOutbound[existingIdx].qty = newQty;
        Toast.success(`อัปเดตจำนวน ${product_name} เป็น ${newQty} ${unit} เรียบร้อย`);
    } else {
        stagedOutbound.push({
            type: 'consumable',
            consumable_id,
            product_name,
            qty,
            unit
        });
        Toast.success(`เพิ่ม ${product_name} จำนวน ${qty} ${unit} เข้าคิวเบิกแล้ว`);
    }
    
    renderStaging();
    qtyInput.value = '';
});

// 5.4 สร้างตารางคิวเบิก (Staging)
function renderStaging() {
    const tbody = document.getElementById('stagingTableBody');
    const badge = document.getElementById('outboundBadge');
    const confirmBtn = document.getElementById('confirmOutboundBtn');

    if(!tbody) return;
    tbody.innerHTML = '';

    if (stagedOutbound.length === 0) {
        tbody.innerHTML = '<tr id="emptyStaging"><td colspan="4" class="px-6 py-12 text-center text-slate-400 italic">ยังไม่มีรายการสแกน รอการเบิกออก</td></tr>';
        badge?.classList.add('hidden');
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = 'ยืนยันการเบิกออก (0 รายการ)';
        return;
    }

    if(badge) {
        badge.textContent = stagedOutbound.length;
        badge.classList.remove('hidden');
    }
    confirmBtn.disabled = false;
    confirmBtn.innerHTML = `<span class="mr-2">📑</span> เปิดบิลและยืนยันการเบิก (${stagedOutbound.length} รายการ)`;

    stagedOutbound.forEach((item, index) => {
        const row = document.createElement('tr');
        row.className = 'animate__animated animate__fadeInRight';
        
        if (item.type === 'sn') {
            row.innerHTML = `
                <td class="px-6 py-3 font-mono font-bold text-indigo-600">${item.sn}</td>
                <td class="px-6 py-3 font-medium text-slate-700">${item.product_name} - ${item.model_name}</td>
                <td class="px-6 py-3 text-center">-</td>
                <td class="px-6 py-3 text-center">
                    <button onclick="removeStaged(${index})" class="text-rose-500 hover:text-rose-700 font-bold transition-transform hover:scale-125" title="ลบออก">✕</button>
                </td>
            `;
        } else {
            row.innerHTML = `
                <td class="px-6 py-3 font-mono font-bold text-yellow-600">วัสดุสิ้นเปลือง</td>
                <td class="px-6 py-3 font-medium text-slate-700">${item.product_name}</td>
                <td class="px-6 py-3 text-center">${item.qty} ${item.unit}</td>
                <td class="px-6 py-3 text-center">
                    <button onclick="removeStaged(${index})" class="text-rose-500 hover:text-rose-700 font-bold transition-transform hover:scale-125" title="ลบออก">✕</button>
                </td>
            `;
        }
        tbody.appendChild(row);
    });
}

// 5.5 ลบรายการออกจากคิว (พร้อมแจ้งเตือน)
window.removeStaged = function(index) {
    const removedItem = stagedOutbound[index];
    stagedOutbound.splice(index, 1);
    
    // แจ้งเตือนว่าลบอะไรออกไป
    const itemName = removedItem.type === 'sn' ? removedItem.sn : removedItem.product_name;
    Toast.info(`ยกเลิกรายการ: ${itemName} ออกจากคิวเรียบร้อย`);
    
    renderStaging();
};

// 5.6 เปิด Modal ยืนยัน
document.getElementById('confirmOutboundBtn')?.addEventListener('click', () => {
    const modal = document.getElementById('outboundModal');
    const billBody = document.getElementById('billTableBody');

    document.getElementById('billDate').textContent = new Date().toLocaleString('th-TH');
    document.getElementById('billTotal').textContent = `${stagedOutbound.length} รายการ`;

    billBody.innerHTML = '';
    stagedOutbound.forEach((item, i) => {
        if (item.type === 'sn') {
            billBody.innerHTML += `
                <tr class="border-b border-slate-100">
                    <td class="py-3 text-slate-400 text-xs">${i+1}</td>
                    <td class="py-3 font-medium text-slate-700">${item.product_name} <span class="text-slate-400 text-xs block">${item.model_name}</span></td>
                    <td class="py-3 text-right font-mono text-sm text-indigo-600">${item.sn}</td>
                </tr>
            `;
        } else {
            billBody.innerHTML += `
                <tr class="border-b border-slate-100">
                    <td class="py-3 text-slate-400 text-xs">${i+1}</td>
                    <td class="py-3 font-medium text-slate-700">${item.product_name}</td>
                    <td class="py-3 text-right font-mono text-sm text-yellow-600">${item.qty} ${item.unit}</td>
                </tr>
            `;
        }
    });

    modal.classList.remove('hidden');
    modal.querySelector('div').classList.add('animate__animated', 'animate__fadeInUp');
});

window.closeOutboundModal = function() {
    const modal = document.getElementById('outboundModal');
    modal.querySelector('div').classList.remove('animate__fadeInUp');
    modal.querySelector('div').classList.add('animate__fadeOutDown');
    
    // แจ้งเตือนเมื่อปิดหน้าต่างโดยยังไม่บันทึก
    Toast.warning('ยกเลิกการยืนยันเบิกออก (คิวยังคงอยู่)');

    setTimeout(() => {
        modal.classList.add('hidden');
        modal.querySelector('div').classList.remove('animate__fadeOutDown');
    }, 300);
};

// 5.7 บันทึกการเบิกออก (ตัดสต็อก)
document.getElementById('finalSubmitOutbound')?.addEventListener('click', async (e) => {
    if (stagedOutbound.length === 0) return;

    const targetUserId = document.getElementById('outboundTargetSelect')?.value;
    if (!targetUserId) {
        Toast.error('กรุณาระบุช่างผู้รับของ ในช่องตัวเลือกด้านบน');
        return;
    }

    Loader.show();
    const btn = e.target;
    btn.disabled = true;

    const sns = stagedOutbound.filter(i => i.type === 'sn').map(i => i.sn);
    const consumables = stagedOutbound.filter(i => i.type === 'consumable').map(i => ({
        consumable_id: i.consumable_id,
        qty: i.qty
    }));

    try {
        let processedSN = 0;
        let processedCons = 0;

        // 3.1 ตัดสต็อก SN
        if (sns.length > 0) {
            const res = await fetch('api/inventory/confirm_outbound.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ sns, target_user_id: targetUserId })
            });
            const data = await res.json();
            if (data.success) {
                processedSN = data.processed;
            } else {
                Toast.error('ข้อผิดพลาดการเบิก SN: ' + data.error);
            }
        }

        // 3.2 ตัดสต็อกวัสดุสิ้นเปลือง
        if (consumables.length > 0) {
            const res = await fetch('api/inventory/outbound_qty.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ items: consumables, target_user_id: targetUserId })
            });
            const data = await res.json();
            if (data.success) {
                processedCons = data.processed;
            } else {
                Toast.error('ข้อผิดพลาดการเบิกวัสดุฯ: ' + data.error);
            }
        }

        // แจ้งเตือนความสำเร็จ
        Toast.success(`ตัดสต็อกสำเร็จ! (มี SN: ${processedSN} รายการ, วัสดุ: ${processedCons} รายการ)`);
        
        // เคลียร์คิว
        stagedOutbound = [];
        renderStaging();
        
        // ปิด Modal (แบบเงียบๆ ไม่ต้องแสดง Toast ยกเลิก)
        const modal = document.getElementById('outboundModal');
        modal.classList.add('hidden');
        
        loadStockOverview();
        loadMasterOptions(); // รีเฟรชยอดคงเหลือ

        const histView = document.getElementById('view-history');
        if(histView && !histView.classList.contains('hidden')) loadHistory();

    } catch (err) {
        Toast.error('ไม่สามารถติดต่อเซิร์ฟเวอร์เพื่อเบิกสินค้าได้');
    } finally {
        Loader.hide();
        btn.disabled = false;
    }
});


// ====================================================
// 6. TAB 4: History
// ====================================================
let historyData = [];

async function loadHistory() {
    try {
        const tbody = document.getElementById('historyTableBody');
        if(tbody) tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-12 text-center text-gray-400"><div class="flex flex-col items-center justify-center"><div class="loader-spinner mb-4 w-8 h-8"></div> กำลังโหลดประวัติ...</div></td></tr>';
        
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
        Toast.error('ไม่สามารถโหลดประวัติการทำรายการได้');
    }
}

function renderHistoryTable() {
    const tbody = document.getElementById('historyTableBody');
    if(!tbody) return;
    tbody.innerHTML = '';

    if (historyData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-12 text-center text-gray-400 italic">ยังไม่มีประวัติการทำรายการ</td></tr>';
        return;
    }

    historyData.forEach((item, index) => {
        const dateObj = new Date(item.timestamp);
        const formattedDate = dateObj.toLocaleDateString('th-TH') + ' ' + dateObj.toLocaleTimeString('th-TH');  
        
        let actionBadge = '';
        if(item.action === 'in') {
            actionBadge = '<span class="px-3 py-1 bg-emerald-50 text-emerald-700 text-xs rounded-full font-bold border border-emerald-100">📥 รับเข้า</span>';
        } else if (item.action === 'out') {
            actionBadge = '<span class="px-3 py-1 bg-rose-50 text-rose-700 text-xs rounded-full font-bold border border-rose-100">📤 เบิกออก</span>';
        } else if (item.action === 'transfer') {
             actionBadge = '<span class="px-3 py-1 bg-blue-50 text-blue-700 text-xs rounded-full font-bold border border-blue-100">🔄 โอนย้าย</span>';
        }

        const adminName = item.admin_name || 'System';
        const targetName = item.target_user_name ? `<br><span class="text-xs text-blue-500 font-bold">ไปยัง: ${item.target_user_name}</span>` : '';
        const qtyDisplay = item.qty ? ` <span class="text-xs font-bold text-yellow-600">(${item.qty} ชิ้น)</span>` : '';

        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors animate__animated animate__fadeIn';
        row.style.animationDelay = `${index * 0.02}s`;
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap text-slate-500 text-sm">${formattedDate}</td>
            <td class="px-6 py-4">${actionBadge}</td>
            <td class="px-6 py-4 font-mono text-xs font-bold text-indigo-600">${item.sn || '-'}</td>
            <td class="px-6 py-4 font-medium text-slate-700">${item.product_name} <span class="text-slate-400 font-normal">(${item.model_name || 'วัสดุสิ้นเปลือง'})</span> ${qtyDisplay}</td>
            <td class="px-6 py-4 text-slate-600 text-sm">ทำโดย: ${adminName} ${targetName}</td>
             <td class="px-6 py-4 text-center">
                 <button onclick="deleteHistory(${item.id})" class="text-red-400 hover:text-red-600" title="ลบประวัตินี้">🗑️</button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

async function deleteHistory(logId) {
    Swal.fire({
        title: 'ยืนยันการลบประวัติ?',
        text: "คุณต้องการลบประวัติการทำรายการนี้ใช่หรือไม่?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'ใช่, ลบเลย!',
        cancelButtonText: 'ยกเลิก'
    }).then(async (result) => {
        if (result.isConfirmed) {
            Loader.show();
            try {
                const res = await fetch('api/inventory/delete_history.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: logId })
                });
                const data = await res.json();
                Loader.hide();
                if (data.success) {
                    Toast.success('ลบประวัติการทำรายการเรียบร้อยแล้ว');
                    loadHistory();
                } else {
                    Toast.error('เกิดข้อผิดพลาด: ' + data.error);
                }
            } catch (e) {
                Loader.hide();
                Toast.error('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
            }
        } else {
            Toast.info('ยกเลิกการลบประวัติ');
        }
    });
}

document.getElementById('exportHistoryBtn')?.addEventListener('click', () => {
    if (historyData.length === 0) return Toast.error('ไม่มีข้อมูลสำหรับส่งออก');   

    Toast.info('กำลังเตรียมไฟล์ Excel...');
    const exportData = historyData.map(item => ({
        "วันที่/เวลา": new Date(item.timestamp).toLocaleString('th-TH'),
        "ประเภท": item.action === 'in' ? 'รับเข้า' : (item.action === 'out' ? 'เบิกออก' : 'โอนย้าย'),
        "หมายเลขซีเรียล": item.sn || '-',
        "ชื่อสินค้า": item.product_name,
        "รุ่น": item.model_name || 'วัสดุสิ้นเปลือง',
        "จำนวน": item.qty || 1,
        "ผู้ทำรายการ (Admin)": item.admin_name || 'System',
        "ช่างที่รับของ": item.target_user_name || '-'
    }));

    const worksheet = XLSX.utils.json_to_sheet(exportData);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "ประวัติ");
    XLSX.writeFile(workbook, `ประวัติคลังสินค้า_${new Date().getTime()}.xlsx`);
    Toast.success('ดาวน์โหลดไฟล์ประวัติเรียบร้อยแล้ว');
});

// INIT ตอนโหลดหน้า
document.addEventListener('DOMContentLoaded', () => {
    loadStockOverview();
    loadMasterOptions();
    loadOutboundTargets();
});