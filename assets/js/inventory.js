// assets/js/inventory.js

// Shared Toast and Loader utilities are defined in assets/js/common.js

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

        const displayProductName = item.product_name || item.name || item.product || '-';
        const displayModelName = item.model_name || item.model || (item.product_name ? 'วัสดุสิ้นเปลือง' : '');
        const displayQty = typeof item.qty === 'number' ? item.qty : (item.qty || 0);

        let actionContent = '<span class="text-xs text-slate-300 italic">วัสดุสิ้นเปลือง / ไม่มี SN</span>';
        if (item.sn_list) {
            const snsCount = item.sn_list.split('|').length;
            actionContent = `<button onclick="openSnModal(this)" data-pname="${item.product_name}" data-mname="${item.model_name || ''}" data-sns="${item.sn_list}" class="text-indigo-600 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 px-4 py-2 rounded-lg text-xs font-bold transition-colors">🔍 กดดู SN ทั้งหมด (${snsCount})</button>`;
        }

        row.innerHTML = `
            <td class="px-6 py-4 font-mono text-xs text-slate-400">${item.product_code || '-'}</td>
            <td class="px-6 py-4 font-bold text-slate-800">${displayProductName}</td>
            <td class="px-6 py-4 text-slate-600">${displayModelName || 'วัสดุสิ้นเปลือง'}</td>
            <td class="px-6 py-4 text-center">
                <span class="inline-flex items-center justify-center px-4 py-1.5 rounded-full text-sm font-bold ${displayQty > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-rose-50 text-rose-700 border border-rose-100'}">
                    ${displayQty} ${displayQty > 0 ? (item.unit || 'ชิ้น') : 'หมด'}
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
    const filtered = stockData.filter(item => {
        const productName = (item.product_name || item.name || item.product || '').toString().toLowerCase();
        const modelName = (item.model_name || item.model || '').toString().toLowerCase();
        const productCode = (item.product_code || '').toString().toLowerCase();
        const snList = (item.sn_list || '').toString().toLowerCase();
        return productName.includes(term) || modelName.includes(term) || productCode.includes(term) || snList.includes(term);
    });
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
    
    currentModalSns = snString.split('|').map(item => {
        let parts = item.split(':');
        return parts.length > 1 ? parts.slice(1).join(':').trim() : item.trim();
    }).filter(sn => sn !== '');
    
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
        div.className = 'bg-white border-2 border-slate-100 p-4 rounded-xl text-sm font-mono text-indigo-700 font-bold flex flex-col items-center justify-center hover:bg-indigo-50 hover:border-indigo-300 transition-all shadow-sm cursor-pointer group relative overflow-hidden';
        div.innerHTML = `
            <span class="z-10 text-base mb-2 tracking-wide">${sn}</span>
            <span class="text-[10px] uppercase tracking-widest bg-slate-100 text-slate-500 px-3 py-1 rounded-full group-hover:bg-indigo-100 group-hover:text-indigo-600 transition-colors z-10 flex items-center">
                <i data-lucide="copy" class="w-3 h-3 mr-1 inline-block"></i> คัดลอก
            </span>
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-50/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
        `;
        div.onclick = () => {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(sn).then(() => {
                    Toast.success('คัดลอก SN: ' + sn + ' เรียบร้อย');
                }).catch(err => {
                    Toast.error('ไม่สามารถคัดลอกได้');
                });
            } else {
                // Fallback
                const textArea = document.createElement("textarea");
                textArea.value = sn;
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                try {
                    document.execCommand('copy');
                    Toast.success('คัดลอก SN: ' + sn + ' เรียบร้อย');
                } catch (err) {
                    Toast.error('ไม่สามารถคัดลอกได้');
                }
                document.body.removeChild(textArea);
            }
        };
        container.appendChild(div);
    });
    
    if (typeof lucide !== 'undefined') {
        lucide.createIcons({
            root: container
        });
    }
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
    const productInput = document.getElementById('mainProductInput');
    const modelInput = document.getElementById('mainModelInput');
    if (productInput && selectedProd) productInput.value = selectedProd;
    if (modelInput && selectedModel) modelInput.value = selectedModel;
    checkScanReady();
};

window.handleMainProductChange = function() {
    const pInput = document.getElementById('mainProductInput');
    const productValue = pInput ? pInput.value.trim() : '';
    const unitInput = document.getElementById('inboundUnit');

    if (currentInboundMode === 'QTY' && productValue && masterOptions.consumables.length > 0) {
        const matched = masterOptions.consumables.find(c => c.name.toLowerCase() === productValue.toLowerCase());
        if (matched && unitInput) {
            unitInput.value = matched.unit || 'ชิ้น';
        }
    }
    checkScanReady();
};

window.handleMainModelChange = function() {
    checkScanReady();
};

window.renderProductDropdown = function(filter = '') {
    const dropdown = document.getElementById('productDropdown');
    if (!dropdown) return;
    
    let items = [];
    if (currentInboundMode === 'SN') {
        items = Object.keys(masterOptions.sn_products || {});
    } else {
        items = (masterOptions.consumables || []).map(c => c.name);
    }
    
    items = items.filter(i => i.toLowerCase().includes(filter.toLowerCase()));
    
    if (items.length === 0) {
        dropdown.innerHTML = '<div class="p-3 text-sm text-slate-400 italic">พิมพ์เพื่อสร้างรายการใหม่...</div>';
    } else {
        dropdown.innerHTML = items.map(item => `
            <div class="px-4 py-2 hover:bg-slate-50 cursor-pointer text-sm font-semibold text-slate-700 border-b border-slate-100 last:border-0" 
                 onclick="selectMainProduct('${item.replace(/'/g, "\\'")}')">
                ${item}
            </div>
        `).join('');
    }
    dropdown.classList.remove('hidden');
};

window.selectMainProduct = function(name) {
    const pInput = document.getElementById('mainProductInput');
    pInput.value = name;
    document.getElementById('productDropdown').classList.add('hidden');
    handleMainProductChange();
    
    if (currentInboundMode === 'SN') {
        const mInput = document.getElementById('mainModelInput');
        mInput.value = '';
        mInput.focus();
        renderModelDropdown('');
    }
};

window.renderModelDropdown = function(filter = '') {
    const dropdown = document.getElementById('modelDropdown');
    const pName = document.getElementById('mainProductInput')?.value.trim();
    if (!dropdown || currentInboundMode !== 'SN') return;
    
    let items = [];
    if (pName && masterOptions.sn_products && masterOptions.sn_products[pName]) {
        items = masterOptions.sn_products[pName];
    }
    
    items = items.filter(i => i.toLowerCase().includes(filter.toLowerCase()));
    
    if (items.length === 0) {
        if (!pName) {
             dropdown.innerHTML = '<div class="p-3 text-sm text-slate-400 italic">กรุณาเลือกชื่อสินค้าก่อน...</div>';
        } else {
             dropdown.innerHTML = '<div class="p-3 text-sm text-slate-400 italic">พิมพ์เพื่อสร้างรุ่นใหม่...</div>';
        }
    } else {
        dropdown.innerHTML = items.map(item => `
            <div class="px-4 py-2 hover:bg-slate-50 cursor-pointer text-sm font-semibold text-slate-700 border-b border-slate-100 last:border-0" 
                 onclick="selectMainModel('${item.replace(/'/g, "\\'")}')">
                ${item}
            </div>
        `).join('');
    }
    dropdown.classList.remove('hidden');
};

window.selectMainModel = function(name) {
    const mInput = document.getElementById('mainModelInput');
    mInput.value = name;
    document.getElementById('modelDropdown').classList.add('hidden');
    handleMainModelChange();
};

document.addEventListener('click', (e) => {
    if (!e.target.closest('#mainProductInput') && !e.target.closest('#productDropdown')) {
        document.getElementById('productDropdown')?.classList.add('hidden');
    }
    if (!e.target.closest('#mainModelInput') && !e.target.closest('#modelDropdown')) {
        document.getElementById('modelDropdown')?.classList.add('hidden');
    }
});

window.checkScanReady = function() {
    const productValue = document.getElementById('mainProductInput')?.value.trim();
    const pReady = productValue && productValue.length > 0;
    let isReady = false;

    if (currentInboundMode === 'SN') {
        const modelValue = document.getElementById('mainModelInput')?.value.trim();
        isReady = pReady && modelValue && modelValue.length > 0;
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

document.getElementById('mainProductInput')?.addEventListener('focus', function(e) {
    renderProductDropdown(this.value.trim());
});
document.getElementById('mainProductInput')?.addEventListener('input', function(e) {
    renderProductDropdown(this.value.trim());
    checkScanReady();
});

document.getElementById('mainModelInput')?.addEventListener('focus', function(e) {
    renderModelDropdown(this.value.trim());
});
document.getElementById('mainModelInput')?.addEventListener('input', function(e) {
    renderModelDropdown(this.value.trim());
    checkScanReady();
});

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
    
    const pName = document.getElementById('mainProductInput')?.value.trim() || '';
    const mName = document.getElementById('mainModelInput')?.value.trim() || '';

    try {
        const res = await fetch('api/inventory/add_sn_fast.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ product_name: pName, model_name: mName, sn: sn })
        });
        const data = await res.json();
        
        if (data.success) {
            Toast.success(`เพิ่ม SN: ${sn} สำเร็จ!`);
            loadMasterOptions(pName, mName);
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
    const pName = document.getElementById('mainProductInput')?.value.trim() || '';
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
            loadMasterOptions(pName);
            loadStockOverview();
        } else { Toast.error(data.error); }
    } catch (e) { Toast.error('การเชื่อมต่อล้มเหลว'); }
};

// --- นำเข้า Excel ---
let excelDataPayload = [];

document.getElementById('excelImport')?.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;

    Loader.show();
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, {type: 'array', cellDates: true});
            const worksheet = workbook.Sheets[workbook.SheetNames[0]];
            const json = XLSX.utils.sheet_to_json(worksheet, {header: 1, defval: ""});

            if (!json || json.length === 0) {
                Loader.hide();
                Toast.error('ไฟล์ Excel ว่างเปล่า');
                return;
            }

            excelDataPayload = [];
            let skipped = 0;

            // ฟังก์ชันตรวจสอบว่าเป็นคอลัมน์ที่ต้องการหรือไม่ (ปรับให้ฉลาดขึ้นและแม่นยำขึ้น)
            const isSNCol = (h) => {
                const s = String(h).toLowerCase().trim();
                return s === 'sn' || s.includes('ซีเรียล') || s.includes('serial') || s.includes('หมายเลข');
            };
            const isModelCol = (h) => {
                const s = String(h).toLowerCase().trim();
                if (!s) return false;
                // เน้นคำที่บ่งบอกว่าเป็น "รุ่น" โดยเฉพาะ
                const include = ['model', 'รุ่น', 'model_name', 'model_no', 'รุ่นสินค้า', 'model no', 'type'];
                const exclude = ['รูป', 'รูปภาพ', 'image', 'picture', 'brand', 'ยี่ห้อ'];
                for (const ex of exclude) if (s.includes(ex)) return false;
                
                // ถ้ามีคำว่า model หรือ รุ่น อยู่ในหัวตาราง ให้ถือว่าเป็นคอลัมน์รุ่น
                return include.some(k => s.includes(k));
            };
            const isNameCol = (h) => {
                const s = String(h).toLowerCase().trim();
                // ถ้าเป็นคอลัมน์รุ่นไปแล้ว ไม่ควรเป็นคอลัมน์ชื่อสินค้า
                if (s.includes('model') || s.includes('รุ่น')) return false;
                return s.includes('name') || s.includes('ชื่อ') || s.includes('product') || s.includes('สินค้า');
            };
            const isCodeCol = (h) => {
                const s = String(h).toLowerCase().trim();
                return s.includes('code') || s.includes('รหัส') || s.includes('sku') || s.includes('p-');
            };
            const isRemarkCol = (h) => {
                const s = String(h).toLowerCase().trim();
                return s.includes('remark') || s.includes('หมายเหตุ') || s.includes('note') || s.includes('รายละเอียด');
            };

            // ตรวจสอบคอลัมน์จากแถวแรก (Header)
            const headerRow = json[0] || [];
            let cI = -1, nI = -1, mI = -1, sI = -1, rI = -1;

            headerRow.forEach((cell, idx) => {
                const header = String(cell).trim();
                if (!header) return;

                // ตรวจสอบรุ่นก่อนชื่อสินค้า เพื่อป้องกันการสลับกัน (Heuristic order)
                if (isCodeCol(header)) cI = idx;
                else if (isModelCol(header)) {
                    if (mI === -1) mI = idx; // ล็อกอินเด็กซ์แรกที่เจอ
                }
                else if (isNameCol(header)) {
                    if (nI === -1) nI = idx;
                }
                else if (isSNCol(header)) sI = idx;
                else if (isRemarkCol(header)) rI = idx;
            });

            // ตรวจสอบความถูกต้องของการตรวจพบ
            console.log('Column Detection Result:', { 
                productCode: cI !== -1 ? headerRow[cI] : 'Not Found', 
                productName: nI !== -1 ? headerRow[nI] : 'Not Found', 
                model: mI !== -1 ? headerRow[mI] : 'Not Found', 
                sn: sI !== -1 ? headerRow[sI] : 'Not Found', 
                remark: rI !== -1 ? headerRow[rI] : 'Not Found' 
            });

            // ถ้าไม่พบ Name หรือ Model จากการแสกน ให้ใช้ตำแหน่งเริ่มต้น (Fallback แบบระมัดระวัง)
            let startRow = 1;
            if (nI === -1 && mI === -1) {
                // ถ้าไม่เจอ Header เลยจริงๆ ให้สันนิษฐานตามลำดับมาตรฐาน: ชื่อ | รุ่น | SN
                nI = 0; mI = 1; sI = 2;
                startRow = 0; 
                console.warn('No headers detected, using default column order: [0]Product, [1]Model, [2]SN');
            }

            // ถ้าไม่พบคอลัมน์ model ให้พยายามเดาจากข้อมูลแถว (fallback heuristic)
            if (mI === -1) {
                // หา column ที่มีค่าไม่ว่างมากที่สุดซึ่งไม่ใช่ SN หรือ ชื่อสินค้า
                const colCounts = {};
                for (let r = 1; r < Math.min(json.length, 30); r++) {
                    const row = json[r] || [];
                    for (let ci = 0; ci < row.length; ci++) {
                        const val = String(row[ci] || '').trim();
                        if (!val) continue;
                        colCounts[ci] = (colCounts[ci] || 0) + 1;
                    }
                }
                // เลือกคอลัมน์ที่มีค่าสูงสุด แต่ไม่ใช่ index ของ name หรือ sn หรือ code
                let bestCol = -1, bestCount = 0;
                Object.keys(colCounts).forEach(k => {
                    const idx = parseInt(k);
                    if (idx === nI || idx === sI || idx === cI) return;
                    if (colCounts[k] > bestCount) {
                        bestCount = colCounts[k];
                        bestCol = idx;
                    }
                });
                if (bestCol !== -1) mI = bestCol;
            }

            console.log('Column Detection:', { productCode: cI, productName: nI, model: mI, sn: sI, remark: rI, startFrom: startRow });

            // ประมวลผลข้อมูล
            for (let i = startRow; i < json.length; i++) {
                const row = json[i];
                if (!row || row.length === 0 || !row.some(cell => cell !== "")) continue;

                const pCode = cI !== -1 ? String(row[cI] || '').trim() : '';
                const pName = nI !== -1 ? String(row[nI] || '').trim() : '';
                const mName = mI !== -1 ? String(row[mI] || '').trim() : '';
                const sn = sI !== -1 ? String(row[sI] || '').trim() : '';
                const remark = rI !== -1 ? String(row[rI] || '').trim() : '';

                // ตรวจสอบว่ามี ชื่อสินค้า (ขั้นต่ำ)
                if (pName) {
                    excelDataPayload.push({
                        product_code: pCode,
                        product_name: pName,
                        model_name: mName,
                        sn: sn,
                        remark: remark
                    });
                } else {
                    skipped++;
                }
            }

            Loader.hide();
            const previewDiv = document.getElementById('excelPreview');
            const countP = document.getElementById('excelCount');

            if (excelDataPayload.length > 0) {
                const detectedCols = [];
                if (nI !== -1) detectedCols.push(`ชื่อสินค้า (Col ${nI+1}: ${headerRow[nI]})`);
                if (mI !== -1) detectedCols.push(`รุ่น (Col ${mI+1}: ${headerRow[mI]})`);
                if (sI !== -1) detectedCols.push(`SN (Col ${sI+1}: ${headerRow[sI]})`);
                
                countP.innerHTML = `✓ พบข้อมูลพร้อมนำเข้า ${excelDataPayload.length} รายการ<br>` + 
                                  `<div class="text-[10px] text-slate-500 mt-1 font-normal bg-slate-50 p-2 rounded border border-slate-100 italic">` +
                                  `ระบบตรวจพบหัวตาราง: ${detectedCols.join(' | ')}</div>`;
                
                previewDiv.classList.remove('hidden');
                previewDiv.classList.add('animate__animated', 'animate__bounceIn');
                Toast.success('โหลดไฟล์สำเร็จ! กรุณาตรวจสอบคอลัมน์และกดยืนยัน');
            } else {
                Toast.error(`ไม่พบข้อมูลที่ถูกต้องในไฟล์\n\nตรวจสอบว่าไฟล์มีคอลัมน์: ชื่อสินค้า | รุ่น | ซีเรียล`);
                previewDiv.classList.add('hidden');
            }
        } catch (err) {
            Loader.hide();
            console.error('Error:', err);
            Toast.error('ไฟล์ Excel รูปแบบไม่ถูกต้อง: ' + err.message);
        }
    };
    reader.readAsArrayBuffer(file);
});

document.getElementById('confirmExcelBtn')?.addEventListener('click', async (e) => {
    if (excelDataPayload.length === 0) return;

    const result = await Swal.fire({
        title: 'ยืนยันการนำเข้า?',
        text: `คุณกำลังจะนำเข้าข้อมูลสินค้าจำนวน ${excelDataPayload.length} รายการ เข้าสู่ระบบคลัง`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ยืนยันนำเข้า',
        cancelButtonText: 'ยกเลิก'
    });

    if (!result.isConfirmed) return;

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

        Loader.hide();
        if (data.success) {
            let msg = `นำเข้าสำเร็จ ${data.imported} รายการ`;
            if (data.errors && data.errors.length > 0) {
                msg += `\n(พบข้อผิดพลาด ${data.errors.length} รายการ)`;
                console.warn('Import Errors:', data.errors);
            }
            
            await Swal.fire({
                title: 'ดำเนินการเสร็จสิ้น',
                text: msg,
                icon: data.errors && data.errors.length > 0 ? 'warning' : 'success',
                confirmButtonText: 'ตกลง'
            });

            document.getElementById('excelPreview').classList.add('hidden');
            document.getElementById('excelImport').value = '';
            excelDataPayload = [];
            loadStockOverview();
            invTab('overview');
        } else {
            Toast.error('เกิดข้อผิดพลาด: ' + data.error);
        }
    } catch (err) {
        Loader.hide();
        Toast.error('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
    } finally {
        btn.disabled = false;
    }
});


// ====================================================
// ฟังก์ชันดาวน์โหลด Template ไฟล์ Excel
// ====================================================
function downloadTemplate() {
    // สร้าง CSV data ตามรูปแบบที่ถูกต้อง
    const headers = ['ชื่อสินค้า', 'รุ่น (Model)', 'ซีเรียล (SN)', 'หมายเหตุ'];
    const rows = [
        ['iPhone 15 Pro', 'A3001', 'SN12345678', 'จากซัพพลายเออร์ A'],
        ['Samsung Galaxy S24', 'SM-S921B', 'SN87654321', 'จากซัพพลายเออร์ B'],
        ['MacBook Pro', 'MBP16-2024', 'SN11111111', 'ครื่องใหม่'],
        ['iPad Air', 'iPad-Air-6', 'SN22222222', '']
    ];

    let csvContent = headers.join('\t') + '\n';
    rows.forEach(row => {
        csvContent += row.join('\t') + '\n';
    });

    // สร้าง Blob และดาวน์โหลด
    const BOM = '\uFEFF'; // UTF-8 BOM สำหรับ Excel
    const blob = new Blob([BOM + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    const fileName = `template_import_${new Date().toISOString().split('T')[0]}.csv`;
    link.setAttribute('href', url);
    link.setAttribute('download', fileName);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    Toast.success('ดาวน์โหลด Template สำเร็จ! ใช้ Excel เปิดและกรอกข้อมูล');
}



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