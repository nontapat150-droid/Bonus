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
    if (tabName === 'transfer') loadMyTodayItems();
}

let stockData = [];

async function loadStockOverview() {
    try {
        document.getElementById('stockTableBody').innerHTML = '<tr><td colspan="6" class="px-6 py-12 text-center text-gray-400"><div class="flex flex-col items-center justify-center"><div class="loader-spinner mb-4 w-8 h-8"></div> กำลังโหลดรายการสินค้า...</div></td></tr>';
        const res = await fetch('api/inventory/get_stock.php');
        const data = await res.json();

        if (data.success) {
            stockData = data.data || [];
            if (data.consumables) {
                const conData = data.consumables.map(c => ({
                    product_code: c.consumable_id,
                    product_name: c.product_name,
                    model_id: c.consumable_id,
                    model_name: 'วัสดุสิ้นเปลือง',
                    qty: parseFloat(c.qty),
                    sn_list: '',
                    unit: c.unit,
                    is_consumable: true
                }));
                stockData = stockData.concat(conData);
            }
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

        const deleteBtn = item.is_consumable ? 
            `<button onclick="deleteConsumable('${item.consumable_id || item.product_code}', '${item.product_name}')" class="p-2 text-rose-400 hover:bg-rose-50 rounded-xl transition-all" title="ลบรายการนี้">🗑️</button>` : 
            `<button onclick="deleteModel(${item.model_id}, '${item.product_name} - ${item.model_name}')" class="p-2 text-rose-400 hover:bg-rose-50 rounded-xl transition-all" title="ลบรายการนี้">🗑️</button>`;

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
                ${deleteBtn}
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
            if (typeof populateOutboundConsumableSelect === 'function') {
                populateOutboundConsumableSelect();
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
    const pInput = document.getElementById('mainProductInput');
    const mInput = document.getElementById('mainModelInput');
    if (!pInput || !mInput) return;
    
    if (currentInboundMode === 'SN') {
        const availableProds = Object.keys(masterOptions.sn_products || {});
        
        // Auto-fill Product if empty
        if (selectedProd) {
            pInput.value = selectedProd;
        } else if (pInput.value.trim() === '' && availableProds.length > 0) {
            pInput.value = availableProds[0];
        }
        
        handleMainProductChange(false); // don't trigger checkScanReady yet
        
        // Auto-fill Model if empty
        if (selectedModel) {
            mInput.value = selectedModel;
        } else if (mInput.value.trim() === '') {
            const currentP = pInput.value.trim();
            if (currentP && masterOptions.sn_products[currentP] && masterOptions.sn_products[currentP].length > 0) {
                mInput.value = masterOptions.sn_products[currentP][0];
            }
        }
    } else {
        const availableConsumables = masterOptions.consumables || [];
        if (selectedProd) {
            pInput.value = selectedProd;
        } else if (pInput.value.trim() === '' && availableConsumables.length > 0) {
            pInput.value = availableConsumables[0].name;
        }
        handleMainProductChange(false);
    }
    
    checkScanReady();
};

// --- Custom Autocomplete Dropdown Logic ---
function setupAutocomplete(inputId, dropdownId, getOptionsCallback, onSelectCallback, labelAddNew = 'เพิ่มรายการใหม่') {
    const input = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    if (!input || !dropdown) return;

    function renderDropdown(showAll = false) {
        const options = getOptionsCallback();
        const currentText = input.value.trim();
        const filterText = showAll ? '' : currentText.toLowerCase();
        
        const filtered = options.filter(o => o.toLowerCase().includes(filterText));
        
        dropdown.innerHTML = '';
        
        // 1. แสดงรายการที่มีอยู่ในระบบ (ถ้ามี)
        if (filtered.length > 0) {
            filtered.forEach(opt => {
                const div = document.createElement('div');
                div.className = 'px-4 py-3 hover:bg-emerald-50 cursor-pointer text-sm font-bold text-gray-700 transition-colors border-b border-gray-50';
                div.textContent = opt;
                div.onmousedown = (e) => {
                    e.preventDefault(); // ป้องกัน Input เสีย Focus เวลากดเลือก
                    input.value = opt;
                    dropdown.classList.add('hidden');
                    if (onSelectCallback) onSelectCallback();
                };
                dropdown.appendChild(div);
            });
        } else if (!showAll) {
            const emptyDiv = document.createElement('div');
            emptyDiv.className = 'px-4 py-3 text-sm text-gray-400 italic';
            emptyDiv.textContent = 'ไม่พบข้อมูลที่ตรงกัน';
            dropdown.appendChild(emptyDiv);
        }

        // 2. ปุ่มสำหรับ "เพิ่มรายการใหม่"
        const exactMatch = options.find(o => o.toLowerCase() === currentText.toLowerCase());
        const addDiv = document.createElement('div');
        addDiv.className = 'px-4 py-3 bg-slate-50 hover:bg-emerald-100 text-emerald-700 cursor-pointer text-sm font-bold transition-colors shadow-inner sticky bottom-0 border-t border-emerald-100';
        
        if (currentText && !exactMatch) {
            // ถ้าพิมพ์คำใหม่ที่ไม่มีในระบบ ให้ขึ้นปุ่มกดยืนยันคำนั้น
            addDiv.innerHTML = `<span class="mr-2">➕</span> ใช้ชื่อใหม่: <b>"${currentText}"</b>`;
            addDiv.onmousedown = (e) => {
                e.preventDefault();
                input.value = currentText;
                dropdown.classList.add('hidden');
                if (onSelectCallback) onSelectCallback();
            };
            dropdown.appendChild(addDiv);
        } else {
            // ถ้าช่องว่างเปล่า หรือคำตรงกันเป๊ะแล้ว ให้แสดงปุ่มบอกให้พิมพ์เพิ่ม
             addDiv.innerHTML = `<span class="mr-2">➕</span> ${labelAddNew} <span class="text-xs font-normal text-slate-400 ml-1">(พิมพ์ลงในช่องได้เลย)</span>`;
             addDiv.onmousedown = (e) => {
                e.preventDefault();
                input.focus();
                input.select();
             };
             dropdown.appendChild(addDiv);
        }
        
        dropdown.classList.remove('hidden');
    }

    // เมื่อคลิกหรือ Focus ให้แสดง "ทุกรายการที่มีในคลัง" (showAll = true)
    input.addEventListener('focus', () => { 
        input.select(); // คลุมดำข้อความเดิมให้พิมพ์ทับง่ายๆ
        renderDropdown(true); 
    });
    input.addEventListener('click', () => { 
        renderDropdown(true); 
    });
    
    // เมื่อเริ่มพิมพ์ ให้ค้นหา (showAll = false)
    input.addEventListener('input', () => {
        renderDropdown(false);
        if (onSelectCallback) onSelectCallback(); 
    });
    
    input.addEventListener('blur', () => {
        setTimeout(() => dropdown.classList.add('hidden'), 200);
    });
    
    dropdown.addEventListener('mousedown', (e) => {
        if (e.target === dropdown) e.preventDefault();
    });
}

// Setup Product Autocomplete
setupAutocomplete('mainProductInput', 'productDropdown', () => {
    if (currentInboundMode === 'SN') return Object.keys(masterOptions.sn_products || {});
    return (masterOptions.consumables || []).map(c => c.name);
}, () => handleMainProductChange(true), 'ต้องการเพิ่มชื่อสินค้าใหม่?');

// Setup Model Autocomplete
setupAutocomplete('mainModelInput', 'modelDropdown', () => {
    const pName = document.getElementById('mainProductInput').value.trim();
    if (currentInboundMode === 'SN' && pName && masterOptions.sn_products && masterOptions.sn_products[pName]) {
        return masterOptions.sn_products[pName];
    }
    return [];
}, () => checkScanReady(), 'ต้องการสร้างรุ่นใหม่?');

window.handleMainProductChange = function(triggerCheck = true) {
    const val = document.getElementById('mainProductInput').value.trim();
    const mInput = document.getElementById('mainModelInput');
    
    if (currentInboundMode === 'SN' && val && masterOptions.sn_products && masterOptions.sn_products[val]) {
        // ถ้าเปลี่ยน Product ให้ดึง Model ตัวแรกของ Product นั้นมาใส่ให้เลยอัตโนมัติ
        const currentM = mInput.value.trim();
        if (!masterOptions.sn_products[val].includes(currentM)) {
            mInput.value = masterOptions.sn_products[val][0] || '';
        }
    }
    
    if (currentInboundMode === 'QTY' && val) {
        const consumable = (masterOptions.consumables || []).find(c => c.name === val);
        if (consumable) {
            document.getElementById('inboundUnit').value = consumable.unit;
        } else {
            document.getElementById('inboundUnit').value = 'ชิ้น';
        }
    }
    
    if (triggerCheck) checkScanReady();
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
        // รักษา Focus ไว้ที่ช่องสแกนหากพร้อมแล้ว (แต่ไม่แย่ง focus ถ้าผู้ใช้อยู่ที่ช่องอื่น)
        if (document.activeElement !== document.getElementById('mainProductInput') && 
            document.activeElement !== document.getElementById('mainModelInput')) {
            scanInput.focus();
        }
    } else {
        scanInput.disabled = true;
        scanInput.classList.replace('border-emerald-500', 'border-gray-300');
        scanInput.placeholder = 'เลือกข้อมูลให้ครบก่อนสแกน';
        // ไม่เคลียร์ค่า scanInput.value ตรงนี้เพื่อป้องกันการขัดจังหวะเวลายิงบาร์โค้ดรัวๆ
    }
};

document.getElementById('mainProductInput')?.addEventListener('input', handleMainProductChange);
document.getElementById('mainModelInput')?.addEventListener('input', checkScanReady);

// ลบระบบหน่วงเวลา (Delay) ออก เพื่อให้เครื่องสแกนบาร์โค้ดยิงได้ทันทีไม่มีสะดุด
document.getElementById('scanInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter' && currentInboundMode === 'SN') {
        e.preventDefault(); 
        const sn = this.value.trim();
        if (sn) {
            this.value = ''; // เคลียร์ช่องทันทีที่กด Enter เพื่อรอรับชิ้นต่อไป
            validateAndSaveSN(sn);
        }
    }
});

async function validateAndSaveSN(sn) {
    const scanInput = document.getElementById('scanInput');
    
    const pName = document.getElementById('mainProductInput').value.trim();
    const mName = document.getElementById('mainModelInput').value.trim();

    try {
        // ยิง Request ไปหลังบ้านแบบ Asynchronous (ไม่บล็อกหน้าจอ)
        const res = await fetch('api/inventory/add_sn_fast.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ product_name: pName, model_name: mName, sn: sn })
        });
        const data = await res.json();
        
        if (data.success) {
            Toast.success(`เพิ่ม SN: ${sn} สำเร็จ!`);
            
            // ตรวจสอบว่าเป็นสินค้า/รุ่นใหม่หรือไม่
            const isNewProd = !masterOptions.sn_products[pName];
            const isNewModel = isNewProd || !masterOptions.sn_products[pName].includes(mName);
            
            if (isNewProd || isNewModel) {
                // ถ้าเป็นของใหม่ ให้ดึงข้อมูล Master ใหม่ (ชื่อสินค้าและรุ่นจะไม่หายไป)
                loadMasterOptions(pName, mName);
            }
            loadStockOverview();
        } else if (data.status === 'duplicate') {
            Toast.error(`SN: ${sn} ซ้ำในระบบ!`);
        } else {
            Toast.error('Error: ' + data.error);
        }
    } catch (e) { Toast.error(`การเชื่อมต่อล้มเหลว (SN: ${sn})`); }
    
    // โฟกัสกลับมาที่ช่องสแกนเพื่อให้ยิงชิ้นต่อไปได้เลย
    scanInput.focus();
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
            
            // นำไปสร้างตัวเลือกให้ dropdown ในประวัติและโอนย้าย
            const teamFilter = document.getElementById('filterHistoryTeam');
            const userFilter = document.getElementById('filterHistoryUser');
            const transferTargetSelect = document.getElementById('transferTargetSelect');
            
            if (teamFilter && userFilter) {
                const teams = new Map();
                outboundTargetsList.forEach(u => {
                    if (u.team_id) teams.set(u.team_id, u.team_name);
                });
                
                let teamHtml = '<option value="">ทุกทีม</option>';
                teams.forEach((name, id) => {
                    teamHtml += `<option value="${id}">${name}</option>`;
                });
                teamFilter.innerHTML = teamHtml;
                
                let userHtml = '<option value="">ทุกคน</option>';
                outboundTargetsList.forEach(u => {
                    const teamStr = u.team_name ? `[ทีม: ${u.team_name}]` : `[ไม่มีทีม]`;
                    userHtml += `<option value="${u.id}">${teamStr} ${u.full_name}</option>`;
                });
                userFilter.innerHTML = userHtml;
            }

            if (transferTargetSelect) {
                let transferHtml = '<option value="">-- กรุณาเลือกช่างรับของ (คลิก) --</option>';
                outboundTargetsList.forEach(u => {
                    if (u.id !== currentLoggedUserId) {
                        const teamStr = u.team_name ? `[ทีม: ${u.team_name}]` : `[ไม่มีทีม]`;
                        transferHtml += `<option value="${u.id}">${teamStr} ${u.full_name}</option>`;
                    }
                });
                transferTargetSelect.innerHTML = transferHtml;
            }
        }
    } catch (e) {
        console.error('Failed to load targets', e);
    }
}

document.getElementById('filterHistoryTeam')?.addEventListener('change', loadHistory);
document.getElementById('filterHistoryUser')?.addEventListener('change', loadHistory);

// ====================================================
// TAB 3.5: Transfer (โอนย้ายระหว่างช่าง)
// ====================================================
let myTodayItems = [];
let myConsumables = []; // เก็บสต็อกวัสดุของตัวเอง

window.loadMyTodayItems = async function() {
    const tbody = document.getElementById('transferItemsTableBody');
    const conBody = document.getElementById('transferConsumablesTableBody');
    const confirmBtn = document.getElementById('confirmTransferBtn');
    
    if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-8 text-center text-gray-400"><div class="flex flex-col items-center justify-center"><div class="loader-spinner mb-4 w-8 h-8"></div> กำลังโหลดรายการ...</div></td></tr>';
    if (conBody) conBody.innerHTML = '<tr><td colspan="3" class="px-6 py-8 text-center text-gray-400">กำลังโหลดสต็อกวัสดุ...</td></tr>';
    
    if(confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = 'ยืนยันการโอนย้าย (0 รายการ)';
    }

    try {
        const res = await fetch('api/inventory/get_my_today_items.php');
        const data = await res.json();
        if (data.success) {
            myTodayItems = data.data || [];
            myConsumables = data.consumables || [];
            renderTransferTable();
            renderTransferConsumablesTable();
        } else {
            Toast.error('โหลดข้อมูลผิดพลาด: ' + data.error);
        }
    } catch (e) {
        console.error(e);
        Toast.error('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
    }
};

function renderTransferTable() {
    const tbody = document.getElementById('transferItemsTableBody');
    if (!tbody) return;
    
    document.getElementById('selectAllTransfer').checked = false;
    tbody.innerHTML = '';

    if (myTodayItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-8 text-center text-slate-400 italic">วันนี้คุณยังไม่ได้เบิกหรือรับโอนสินค้า (SN) ใดๆ</td></tr>';
        updateTransferConfirmButton();
        return;
    }

    myTodayItems.forEach((item, index) => {
        const dateObj = new Date(item.timestamp);
        const timeStr = dateObj.toLocaleTimeString('th-TH');

        const row = document.createElement('tr');
        row.className = 'hover:bg-blue-50 transition-colors cursor-pointer';
        row.onclick = function(e) {
            if(e.target.tagName !== 'INPUT') {
                const cb = document.getElementById(`chk_transfer_${index}`);
                cb.checked = !cb.checked;
                updateTransferConfirmButton();
            }
        };

        row.innerHTML = `
            <td class="px-6 py-3 text-center">
                <input type="checkbox" id="chk_transfer_${index}" value="${item.sn}" class="transfer-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-4 h-4 cursor-pointer" onchange="updateTransferConfirmButton()">
            </td>
            <td class="px-6 py-3 font-mono font-bold text-blue-600">${item.sn}</td>
            <td class="px-6 py-3 font-medium text-slate-700">${item.product_name} <span class="text-xs text-slate-400">(${item.model_name})</span></td>
            <td class="px-6 py-3 text-slate-500">${timeStr}</td>
        `;
        tbody.appendChild(row);
    });
    
    updateTransferConfirmButton();
}

function renderTransferConsumablesTable() {
    const tbody = document.getElementById('transferConsumablesTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';

    if (myConsumables.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="px-6 py-8 text-center text-slate-400 italic">คุณไม่มีสต็อกวัสดุสิ้นเปลืองในขณะนี้</td></tr>';
        return;
    }

    myConsumables.forEach((item, index) => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-blue-50 transition-colors';
        row.innerHTML = `
            <td class="px-6 py-3 font-medium text-slate-700">${item.product_name}</td>
            <td class="px-6 py-3 text-center font-bold text-blue-600">${item.qty} ${item.unit}</td>
            <td class="px-6 py-3 text-center">
                <div class="flex items-center justify-center gap-2">
                    <input type="number" id="transfer_qty_${item.consumable_id}" placeholder="0" min="0" max="${item.qty}" step="0.1" class="transfer-qty-input w-24 px-2 py-1 border border-blue-300 rounded text-center focus:ring-blue-500" oninput="updateTransferConfirmButton()">
                    <span class="text-xs text-slate-500">${item.unit}</span>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}

window.toggleAllTransfer = function(checkbox) {
    const isChecked = checkbox.checked;
    const checkboxes = document.querySelectorAll('.transfer-checkbox');
    checkboxes.forEach(cb => cb.checked = isChecked);
    updateTransferConfirmButton();
};

window.updateTransferConfirmButton = function() {
    const checkboxes = document.querySelectorAll('.transfer-checkbox:checked');
    const snCount = checkboxes.length;
    
    let conCount = 0;
    const qtyInputs = document.querySelectorAll('.transfer-qty-input');
    qtyInputs.forEach(input => {
        const val = parseFloat(input.value);
        if (val > 0) conCount++;
    });
    
    const totalCount = snCount + conCount;
    const confirmBtn = document.getElementById('confirmTransferBtn');
    
    const allCheckboxes = document.querySelectorAll('.transfer-checkbox');
    const selectAllCb = document.getElementById('selectAllTransfer');
    if (allCheckboxes.length > 0 && selectAllCb) {
        selectAllCb.checked = (snCount === allCheckboxes.length);
    }
    
    if (confirmBtn) {
        if (totalCount > 0) {
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = `<span class="mr-2">🔄</span> ยืนยันการโอนย้าย (${totalCount} รายการ)`;
        } else {
            confirmBtn.disabled = true;
            confirmBtn.innerHTML = 'ยืนยันการโอนย้าย (0 รายการ)';
        }
    }
};

document.getElementById('confirmTransferBtn')?.addEventListener('click', async (e) => {
    const targetUserId = document.getElementById('transferTargetSelect').value;
    if (!targetUserId) {
        Toast.error('กรุณาระบุช่างที่จะรับโอนของ');
        document.getElementById('transferTargetSelect').focus();
        return;
    }

    const sns = Array.from(document.querySelectorAll('.transfer-checkbox:checked')).map(cb => cb.value);
    
    const cons = [];
    document.querySelectorAll('.transfer-qty-input').forEach(input => {
        const qty = parseFloat(input.value);
        if (qty > 0) {
            const consumable_id = input.id.replace('transfer_qty_', '');
            cons.push({ consumable_id: consumable_id, qty: qty });
        }
    });

    if (sns.length === 0 && cons.length === 0) return;

    Loader.show();
    const btn = e.target;
    btn.disabled = true;

    try {
        let totalProcessed = 0;
        
        // 1. โอน SN
        if (sns.length > 0) {
            const resSN = await fetch('api/inventory/submit_transfer.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ sns: sns, target_user_id: targetUserId }) 
            });
            const dataSN = await resSN.json();
            if (!dataSN.success) throw new Error(dataSN.error);
            totalProcessed += dataSN.processed;
        }
        
        // 2. โอน Consumables
        if (cons.length > 0) {
            const resCon = await fetch('api/inventory/transfer_qty.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ items: cons, target_user_id: targetUserId }) 
            });
            const dataCon = await resCon.json();
            if (!dataCon.success) throw new Error(dataCon.error);
            totalProcessed += dataCon.processed;
        }

        Swal.fire({
            title: 'โอนย้ายสำเร็จ!',
            text: `ทำการโอนย้ายสินค้าและวัสดุรวม ${totalProcessed} รายการ บันทึกลงประวัติเรียบร้อยแล้ว`,
            icon: 'success',
            confirmButtonColor: '#3b82f6',
            confirmButtonText: 'ตกลง',
            customClass: { popup: 'rounded-2xl', confirmButton: 'px-8 py-2 rounded-xl font-bold' }
        });
        
        document.getElementById('transferTargetSelect').value = '';
        loadMyTodayItems(); // โหลดข้อมูลใหม่เพื่ออัปเดตสต็อกที่เหลือ
        
        if(!document.getElementById('view-history').classList.contains('hidden')) loadHistory();
        
    } catch (err) {
        Toast.error(err.message || 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้');
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
        const teamFilter = document.getElementById('filterHistoryTeam');
        const userFilter = document.getElementById('filterHistoryUser');
        
        let url = 'api/inventory/get_history.php';
        let queryParams = [];
        if (teamFilter && teamFilter.value) queryParams.push(`team_id=${teamFilter.value}`);
        if (userFilter && userFilter.value) queryParams.push(`user_id=${userFilter.value}`);
        
        if (queryParams.length > 0) {
            url += '?' + queryParams.join('&');
        }

        document.getElementById('historyTableBody').innerHTML = '<tr><td colspan="6" class="px-6 py-12 text-center text-gray-400"><div class="flex flex-col items-center justify-center"><div class="loader-spinner mb-4 w-8 h-8"></div> กำลังโหลดประวัติ...</div></td></tr>';
        const res = await fetch(url);
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
        
        let actionBadge = '';
        if (item.action === 'in') {
            actionBadge = '<span class="px-3 py-1 bg-emerald-50 text-emerald-700 text-xs rounded-full font-bold border border-emerald-100">📥 รับเข้าสต็อก</span>';
        } else if (item.action === 'out') {
            actionBadge = '<span class="px-3 py-1 bg-rose-50 text-rose-700 text-xs rounded-full font-bold border border-rose-100">📤 จ่ายออกให้ทีม</span>';
        } else if (item.action === 'transfer') {
            actionBadge = '<span class="px-3 py-1 bg-blue-50 text-blue-700 text-xs rounded-full font-bold border border-blue-100">🔄 โอนย้าย (ยืมของ)</span>';
        }

        // วาดส่วนของผู้เบิกและผู้รับ
        const adminTeam = item.admin_team ? `(${item.admin_team})` : '';
        let personHtml = `<div class="text-xs font-medium text-slate-500 mb-1">ผู้ทำรายการ: <span class="text-slate-800 font-bold">${item.admin_name || 'System'}</span> ${adminTeam}</div>`;
        
        if ((item.action === 'out' || item.action === 'transfer') && item.target_name) {
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
                <button onclick="deleteHistory(${item.id}, '${item.log_type}')" class="text-rose-400 hover:text-rose-600 font-bold" title="ลบประวัตินี้">🗑️</button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

window.deleteHistory = async function(id, log_type) {
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
                body: JSON.stringify({ id: id, log_type: log_type })
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
        if ((item.action === 'out' || item.action === 'transfer') && item.target_name) {
            targetText = item.target_name;
            if (item.target_team) targetText += ` (${item.target_team})`;
        }
        
        let actionTypeStr = '';
        if (item.action === 'in') actionTypeStr = 'รับเข้าสต็อก';
        else if (item.action === 'out') actionTypeStr = 'จ่ายออกให้ทีม';
        else if (item.action === 'transfer') actionTypeStr = 'โอนย้าย (ยืมของ)';

        return {
            "วันที่/เวลา": new Date(item.timestamp).toLocaleString('th-TH'),
            "ประเภท": actionTypeStr,
            "หมายเลขซีเรียล": item.sn || '-',
            "ชื่อสินค้า": item.product_name,
            "รุ่น": item.model_name || 'วัสดุสิ้นเปลือง',
            "ผู้ทำรายการ (คนยิง/คนโอน)": adminText,
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
    fetchOutboundTargets(); // โหลดรายชื่อผู้รับตอนเข้าหน้าเว็บ (ใช้ทั้งแอดมินและช่าง)
    
    if (window.IS_ADMIN) {
        loadStockOverview();
        loadMasterOptions();
    } else {
        // สำหรับช่าง ให้เข้าหน้าโอนย้ายเป็นหน้าแรก
        invTab('transfer');
    }
});