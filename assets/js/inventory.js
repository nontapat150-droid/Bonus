// assets/js/inventory.js

let outboundTargetsList = [];
let currentLoggedUserId = null;

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

// -----------------------------------------
// แสดงตารางและปุ่มเปิดดู SN Modal
// -----------------------------------------
function renderStockTable(data) {
    const tbody = document.getElementById('stockTableBody');
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
            const snsCount = item.sn_list.split('|').length;
            actionContent = `<button onclick="openSnModal(this)" data-pname="${item.product_name}" data-mname="${item.model_name}" data-sns="${item.sn_list}" class="text-indigo-600 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 px-4 py-2 rounded-lg text-xs font-bold transition-colors">🔍 กดดู SN ทั้งหมด (${snsCount})</button>`;
        }

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
                ${actionContent}
            </td>
            <td class="px-6 py-4 text-center">
                <button onclick="deleteModel(${item.model_id}, '${item.product_name} - ${item.model_name}')" class="p-2 text-rose-400 hover:bg-rose-50 rounded-xl transition-all" title="ลบรายการนี้">
                    🗑️
                </button>
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

// -----------------------------------------
// ระบบ SN Modal
// -----------------------------------------
let currentModalSns = []; 

window.openSnModal = function(btnElement) {
    const pName = btnElement.getAttribute('data-pname');
    const mName = btnElement.getAttribute('data-mname');
    const snString = btnElement.getAttribute('data-sns');

    document.getElementById('snModalProductName').textContent = pName;
    document.getElementById('snModalModelName').textContent = `รุ่น (Model): ${mName || '-'}`;

    // snString is "id:sn|id:sn"
    currentModalSns = snString.split('|').map(pair => {
        const [id, sn] = pair.split(':');
        return { id, sn };
    }).filter(item => item.sn);

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

    snArray.forEach(item => {
        const div = document.createElement('div');
        div.className = 'bg-slate-50 border border-slate-200 px-3 py-2.5 rounded-lg text-sm font-mono text-indigo-700 font-bold flex items-center justify-between hover:bg-indigo-50 transition-colors shadow-sm';
        div.innerHTML = `
            <span>${item.sn}</span>
            <button onclick="deleteSnItem(${item.id}, '${item.sn}')" class="text-rose-400 hover:text-rose-600 transition-colors" title="ลบ SN นี้">✕</button>
        `;
        container.appendChild(div);
    });
};

document.getElementById('searchSnInModal')?.addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase().trim();
    const filteredSns = currentModalSns.filter(item => item.sn.toLowerCase().includes(term));
    renderSnModalList(filteredSns);
});

window.deleteSnItem = async function(id, sn) {
    const result = await Swal.fire({
        title: 'ยืนยันการลบ SN?',
        text: `คุณต้องการลบหมายเลขซีเรียล "${sn}" ใช่หรือไม่?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'ใช่, ลบเลย',
        cancelButtonText: 'ยกเลิก'
    });

    if (result.isConfirmed) {
        try {
            const res = await fetch('api/inventory/delete_item.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            const data = await res.json();
            if (data.success) {
                Toast.success(data.message);
                // นำออกจากลิสต์ใน Modal
                currentModalSns = currentModalSns.filter(item => item.id != id);
                renderSnModalList(currentModalSns);
                // โหลดตารางหลักใหม่
                loadStockOverview();
            } else {
                Swal.fire('ข้อผิดพลาด', data.error, 'error');
            }
        } catch (e) {
            Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อล้มเหลว', 'error');
        }
    }
};

window.deleteModel = async function(id, name) {
    const result = await Swal.fire({
        title: 'ลบรุ่นสินค้า?',
        text: `ยืนยันการลบ "${name}"? ข้อมูลหมายเลขซีเรียลทั้งหมดภายใต้รุ่นนี้จะถูกลบถาวร!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'ใช่, ลบทั้งหมด',
        cancelButtonText: 'ยกเลิก'
    });

    if (result.isConfirmed) {
        Loader.show();
        try {
            const res = await fetch('api/inventory/delete_model.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            const data = await res.json();
            if (data.success) {
                Swal.fire('สำเร็จ', data.message, 'success');
                loadStockOverview();
                loadMasterOptions();
            } else {
                Swal.fire('ข้อผิดพลาด', data.error, 'error');
            }
        } catch (e) {
            Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อล้มเหลว', 'error');
        } finally {
            Loader.hide();
        }
    }
};
// ====================================================
// TAB 2: Inbound (SN, QTY, EXCEL)
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
    
    // Reset buttons
    [btnSn, btnQty].forEach(btn => {
        btn.className = "px-4 py-2 rounded-full text-sm font-medium text-gray-500 hover:text-gray-700 transition-all flex items-center";
    });
    
    // Hide areas
    [areaSn, areaQty].forEach(area => area?.classList.add('hidden'));

    if (mode === 'SN') {
        btnSn.className = "px-4 py-2 rounded-full text-sm font-bold bg-white text-emerald-600 shadow-sm transition-all flex items-center";
        areaSn?.classList.remove('hidden');
        areaModel?.classList.remove('hidden');
    } else if (mode === 'QTY') {
        btnQty.className = "px-4 py-2 rounded-full text-sm font-bold bg-white text-yellow-600 shadow-sm transition-all flex items-center";
        areaQty?.classList.remove('hidden');
        areaModel?.classList.add('hidden');
    }
    
    buildMainDropdowns();
};

window.buildMainDropdowns = function(selectedProd = '', selectedModel = '') {
    const pDataList = document.getElementById('productList');
    const pInput = document.getElementById('mainProductInput');
    if (!pDataList || !pInput) return;
    
    let html = '';
    if (currentInboundMode === 'SN') {
        Object.keys(masterOptions.sn_products).forEach(p => {
            html += `<option value="${p}">`;
        });
    } else {
        masterOptions.consumables.forEach(c => {
            html += `<option value="${c.name}">`;
        });
    }
    pDataList.innerHTML = html;
    
    if (selectedProd) pInput.value = selectedProd;
    handleMainProductChange();
    
    if (selectedModel && currentInboundMode === 'SN') {
        setTimeout(() => { document.getElementById('mainModelInput').value = selectedModel; checkScanReady(); }, 50);
    }
};

window.handleMainProductChange = function() {
    const val = document.getElementById('mainProductInput').value.trim();
    const mDataList = document.getElementById('modelList');
    const mInput = document.getElementById('mainModelInput');
    
    if (currentInboundMode === 'SN' && val && masterOptions.sn_products[val]) {
        let mHtml = '';
        masterOptions.sn_products[val].forEach(m => {
            mHtml += `<option value="${m}">`;
        });
        if (mDataList) mDataList.innerHTML = mHtml;
    } else {
        if (mDataList) mDataList.innerHTML = '';
    }
    
    if (currentInboundMode === 'QTY' && val) {
        const consumable = masterOptions.consumables.find(c => c.name === val);
        if (consumable) {
            document.getElementById('inboundUnit').value = consumable.unit;
        } else {
            document.getElementById('inboundUnit').value = 'ชิ้น';
        }
    }
    checkScanReady();
};

window.checkScanReady = function() {
    const pVal = document.getElementById('mainProductInput').value.trim();
    const pReady = pVal !== '';
    
    let isReady = false;
    if (currentInboundMode === 'SN') {
        const mVal = document.getElementById('mainModelInput').value.trim();
        const mReady = mVal !== '';
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

document.getElementById('mainProductInput')?.addEventListener('input', handleMainProductChange);
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
    
    const pName = document.getElementById('mainProductInput').value.trim();
    const mName = document.getElementById('mainModelInput').value.trim();

    try {
        const res = await fetch('api/inventory/add_sn_fast.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ product_name: pName, model_name: mName, sn: sn })
        });
        const data = await res.json();
        
        if (data.success) {
            Toast.success(`เพิ่ม SN: ${sn} สำเร็จ!`);
            
            // Check if it was a new product or model by looking at current masterOptions
            const isNewProd = !masterOptions.sn_products[pName];
            const isNewModel = isNewProd || !masterOptions.sn_products[pName].includes(mName);
            
            if (isNewProd || isNewModel) {
                loadMasterOptions(pName, mName);
            }
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
    const pName = document.getElementById('mainProductInput').value.trim();
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
            
            const isNewProd = !masterOptions.consumables.some(c => c.name === pName);
            if (isNewProd) loadMasterOptions(pName);
            
            loadStockOverview();
        } else { Toast.error(data.error); }
    } catch (e) { Toast.error('การเชื่อมต่อล้มเหลว'); }
};

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

                const pName = row[0];
                const mName = row[1];
                const sn = row[2];

                if (pName && mName) {
                    excelDataPayload.push({
                        product_code: '', // ไม่ต้องใช้รหัสสินค้าตามเงื่อนไขใหม่
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
            const stats = data.stats;
            
            let html = `
                <div class="text-left text-sm space-y-3 mt-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                    <div class="flex justify-between items-center border-b border-slate-200 pb-2">
                        <span class="text-slate-500">รวมแถวที่ประมวลผล:</span> 
                        <span class="font-bold text-indigo-600 text-lg">${excelDataPayload.length} แถว</span>
                    </div>
                    <div class="flex justify-between items-center border-b border-slate-200 pb-2">
                        <span class="text-slate-500">พบสินค้าที่แตกต่างกัน:</span> 
                        <span class="font-bold text-slate-700">${stats.products_count} สินค้า</span>
                    </div>
                    <div class="flex justify-between items-center border-b border-slate-200 pb-2">
                        <span class="text-slate-500">พบรุ่นที่แตกต่างกัน:</span> 
                        <span class="font-bold text-slate-700">${stats.models_count} รุ่น</span>
                    </div>
                    <div class="flex justify-between items-center border-b border-slate-200 pb-2">
                        <span class="text-slate-500">ซีเรียล (SN) ที่นำเข้าสำเร็จ:</span> 
                        <span class="font-bold text-emerald-600 text-lg">${stats.imported_sns} ชิ้น</span>
                    </div>
                    <div class="flex justify-between items-center pt-1">
                        <span class="text-slate-500">ซีเรียล (SN) ที่ซ้ำ/ข้ามไป:</span> 
                        <span class="font-bold ${stats.duplicate_sns > 0 ? 'text-rose-500' : 'text-slate-400'} text-lg">${stats.duplicate_sns} ชิ้น</span>
                    </div>
                </div>
            `;

            if (data.errors && data.errors.length > 0) {
                html += `
                <div class="mt-4 p-4 bg-rose-50 text-rose-600 text-xs rounded-xl max-h-40 overflow-y-auto custom-scrollbar text-left border border-rose-100">
                    <p class="font-bold mb-2 text-sm flex items-center"><span class="mr-1">⚠️</span> รายการที่พบปัญหา:</p>
                    <ul class="list-disc pl-5 space-y-1">
                        ${data.errors.slice(0, 10).map(e => `<li>${e}</li>`).join('')}
                        ${data.errors.length > 10 ? `<li class="italic font-bold mt-2 text-rose-800">... และอีก ${data.errors.length - 10} รายการที่พบปัญหา</li>` : ''}
                    </ul>
                </div>`;
            }

            Swal.fire({
                title: 'สรุปผลการนำเข้า',
                html: html,
                icon: data.errors && data.errors.length > 0 && stats.imported_sns > 0 ? 'warning' : 'success',
                confirmButtonColor: '#10b981',
                confirmButtonText: 'รับทราบ',
                customClass: {
                    popup: 'rounded-2xl',
                    confirmButton: 'px-8 py-3 rounded-xl font-bold uppercase tracking-wider'
                }
            });

            document.getElementById('excelPreview').classList.add('hidden');
            document.getElementById('excelImport').value = '';
            excelDataPayload = [];
            loadStockOverview();
            loadMasterOptions(); // Reload options in case new products/models were added
        } else {
            Swal.fire('เกิดข้อผิดพลาด', data.error || 'ไม่สามารถนำเข้าข้อมูลได้', 'error');
        }
    } catch (err) {
        Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
    } finally {
        Loader.hide();
        btn.disabled = false;
    }
});

// ====================================================
// TAB 3: Outbound (ค้นหาผู้รับ / ทีม)
// ====================================================
let stagedOutbound = [];

// สร้างฟังก์ชันโหลดรายชื่อ
async function fetchOutboundTargets() {
    try {
        const res = await fetch('api/inventory/get_outbound_targets.php');
        const data = await res.json();
        if (data.success) {
            outboundTargetsList = data.users;
            currentLoggedUserId = data.current_user_id;
        }
    } catch (e) {
        console.error('Failed to load targets', e);
    }
}

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
    const targetSelect = document.getElementById('outboundTargetSelect');

    // วาด Dropdown
    targetSelect.innerHTML = '<option value="">-- กรุณาเลือกผู้รับของ (คลิก) --</option>';
    outboundTargetsList.forEach(u => {
        const teamStr = u.team_name ? `[ทีม: ${u.team_name}]` : `[ไม่มีทีม]`;
        const isSelf = u.id === currentLoggedUserId ? ' 👈 (เบิกให้ตัวเอง)' : '';
        targetSelect.innerHTML += `<option value="${u.id}">${teamStr} ${u.full_name}${isSelf}</option>`;
    });

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

    // ตรวจสอบว่าเลือกคนรับหรือยัง
    const targetUserId = document.getElementById('outboundTargetSelect').value;
    if (!targetUserId) {
        Toast.error('กรุณาระบุว่ากำลังเบิกของให้ใคร หรือทีมไหน');
        document.getElementById('outboundTargetSelect').focus();
        return;
    }

    Loader.show();
    const btn = e.target;
    btn.disabled = true;

    const sns = stagedOutbound.map(i => i.sn);

    try {
        const res = await fetch('api/inventory/confirm_outbound.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sns: sns, target_user_id: targetUserId }) 
        });
        const data = await res.json();

        if (data.success) {
            Toast.success(`เบิกของสำเร็จ ${data.processed} รายการ! ข้อมูลบันทึกลงประวัติแล้ว`);
            stagedOutbound = [];
            renderStaging();
            closeOutboundModal();
            loadStockOverview();
            if(!document.getElementById('view-history').classList.contains('hidden')) loadHistory();
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

// ====================================================
// TAB 4: History (แสดงชื่อทีม / ผู้รับ)
// ====================================================
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
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-12 text-center text-gray-400 italic">ยังไม่มีประวัติการทำรายการ</td></tr>';
        return;
    }

    historyData.forEach((item, index) => {
        const dateObj = new Date(item.timestamp);
        const formattedDate = dateObj.toLocaleDateString('th-TH') + ' ' + dateObj.toLocaleTimeString('th-TH');  
        const actionBadge = item.action === 'in'
            ? '<span class="px-3 py-1 bg-emerald-50 text-emerald-700 text-xs rounded-full font-bold border border-emerald-100">📥 รับเข้าสต็อก</span>'
            : '<span class="px-3 py-1 bg-rose-50 text-rose-700 text-xs rounded-full font-bold border border-rose-100">📤 จ่ายออกให้ทีม</span>';

        // วาดส่วนของผู้เบิกและผู้รับ
        const adminTeam = item.admin_team ? `(${item.admin_team})` : '';
        let personHtml = `<div class="text-xs font-medium text-slate-500 mb-1">คนยิงจ่าย: <span class="text-slate-800 font-bold">${item.admin_name || 'System'}</span> ${adminTeam}</div>`;
        
        if (item.action === 'out' && item.target_name) {
            const targetTeam = item.target_team ? `[ทีม: ${item.target_team}]` : '';
            personHtml += `<div class="text-sm font-bold text-blue-700 bg-blue-50 px-2 py-1 rounded-md inline-block border border-blue-100 mt-1">🎯 ผู้รับ: ${item.target_name} <span class="text-xs font-normal text-blue-500">${targetTeam}</span></div>`;
        }

        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors animate__animated animate__fadeIn';
        row.style.animationDelay = `${index * 0.02}s`;
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap text-slate-500 text-sm">${formattedDate}</td>
            <td class="px-6 py-4">${actionBadge}</td>
            <td class="px-6 py-4 font-mono text-xs font-bold text-indigo-600">${item.sn || '-'}</td>
            <td class="px-6 py-4 font-medium text-slate-700">${item.product_name} <br><span class="text-slate-400 font-normal text-xs">รุ่น: ${item.model_name || 'วัสดุสิ้นเปลือง'}</span></td>
            <td class="px-6 py-4">${personHtml}</td>
            <td class="px-6 py-4 text-center">
                <button onclick="deleteHistory(${item.id})" class="text-rose-400 hover:text-rose-600 font-bold" title="ลบประวัตินี้">🗑️</button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

window.deleteHistory = async function(id) {
    const result = await Swal.fire({
        title: 'ลบรายการประวัติ?',
        text: 'การลบประวัติจะไม่ส่งผลกระทบต่อจำนวนสินค้าในสต็อก ยืนยันการลบหรือไม่?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'ใช่, ลบเลย',
        cancelButtonText: 'ยกเลิก'
    });

    if (result.isConfirmed) {
        try {
            const res = await fetch('api/inventory/delete_history.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            const data = await res.json();
            if (data.success) {
                Toast.success(data.message);
                loadHistory();
            } else {
                Swal.fire('ข้อผิดพลาด', data.error, 'error');
            }
        } catch (e) {
            Swal.fire('ข้อผิดพลาด', 'เชื่อมต่อล้มเหลว', 'error');
        }
    }
};

document.getElementById('exportHistoryBtn')?.addEventListener('click', () => {
    if (historyData.length === 0) return Toast.error('ไม่มีข้อมูลสำหรับส่งออก');   

    Toast.info('กำลังเตรียมไฟล์ Excel...');
    const exportData = historyData.map(item => {
        let adminText = item.admin_name || 'System';
        if (item.admin_team) adminText += ` (${item.admin_team})`;
        
        let targetText = '-';
        if (item.action === 'out' && item.target_name) {
            targetText = item.target_name;
            if (item.target_team) targetText += ` (${item.target_team})`;
        }

        return {
            "วันที่/เวลา": new Date(item.timestamp).toLocaleString('th-TH'),
            "ประเภท": item.action === 'in' ? 'รับเข้าสต็อก' : 'จ่ายออกให้ทีม',
            "หมายเลขซีเรียล": item.sn || '-',
            "ชื่อสินค้า": item.product_name,
            "รุ่น": item.model_name || 'วัสดุสิ้นเปลือง',
            "ผู้ทำรายการ (คนยิง)": adminText,
            "ผู้รับของ (ทีม)": targetText
        };
    });

    const worksheet = XLSX.utils.json_to_sheet(exportData);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "ประวัติ");
    XLSX.writeFile(workbook, `ประวัติคลังสินค้า_${new Date().getTime()}.xlsx`);
    Toast.success('ดาวน์โหลดไฟล์ประวัติเรียบร้อยแล้ว');
});

// ====================================================
// ฟังก์ชันล้างข้อมูลคลังสินค้าทั้งหมด (Danger Zone)
// ====================================================
window.deleteAllInventory = async function() {
    const result = await Swal.fire({
        title: '⚠️ คำเตือนร้ายแรง!',
        html: 'คุณแน่ใจหรือไม่ที่จะ "ล้างข้อมูลคลังสินค้าและประวัติทั้งหมด"?<br><b class="text-red-600">การกระทำนี้เมื่อทำแล้วจะไม่สามารถกู้คืนข้อมูลกลับมาได้อีก!</b>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'ใช่, ฉันต้องการล้างข้อมูลทั้งหมด',
        cancelButtonText: 'ยกเลิก',
        input: 'text',
        inputLabel: 'เพื่อยืนยันการลบ โปรดพิมพ์คำว่า "DELETE" เป็นภาษาอังกฤษตัวพิมพ์ใหญ่:',
        inputPlaceholder: 'พิมพ์ DELETE ตรงนี้...',
        inputValidator: (value) => {
            if (value !== 'DELETE') {
                return 'คำยืนยันไม่ถูกต้อง (ต้องเป็นตัวพิมพ์ใหญ่)';
            }
        }
    });

    if (result.isConfirmed) {
        Loader.show();
        try {
            const res = await fetch('api/inventory/delete_all.php', {
                method: 'POST'
            });
            
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('Server response was not JSON:', text);
                throw new Error('เซิร์ฟเวอร์ตอบกลับไม่ถูกต้อง (ไม่ใช่ JSON)');
            }
            
            if (data.success) {
                Swal.fire('สำเร็จ', data.message, 'success');
                loadStockOverview();
                loadMasterOptions();
                if (!document.getElementById('view-history').classList.contains('hidden')) {
                    loadHistory();
                }
            } else {
                Swal.fire('เกิดข้อผิดพลาด', data.error || 'ไม่ทราบสาเหตุ', 'error');
            }
        } catch (e) {
            console.error(e);
            Swal.fire('ข้อผิดพลาด', e.message || 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้', 'error');
        } finally {
            Loader.hide();
        }
    }
};

// INIT
document.addEventListener('DOMContentLoaded', () => {
    loadStockOverview();
    loadMasterOptions();
    fetchOutboundTargets(); // โหลดรายชื่อผู้รับตอนเข้าหน้าเว็บ
});