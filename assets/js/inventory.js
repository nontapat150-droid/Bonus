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
            <td class="px-6 py-4 font-mono text-xs text-slate-400">${item.product_code}</td>
            <td class="px-6 py-4 font-bold text-slate-800">${item.product_name}</td>
            <td class="px-6 py-4 text-slate-600">${item.model_name}</td>
            <td class="px-6 py-4 text-center">
                <span class="inline-flex items-center justify-center px-4 py-1.5 rounded-full text-sm font-bold ${item.qty > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-rose-50 text-rose-700 border border-rose-100'}">
                    ${item.qty} ${item.qty > 0 ? 'ชิ้น' : 'หมด'}
                </span>
            </td>
            <td class="px-6 py-4 text-center">
                <button onclick="Toast.info('ฟีเจอร์ดูหมายเลขซีเรียลกำลังจะมาเร็วๆ นี้')" class="text-indigo-600 hover:text-indigo-800 text-sm font-bold transition-colors">🔍 ดู SN</button>    
            </td>
        `;
        tbody.appendChild(row);
    });
}

document.getElementById('searchStock')?.addEventListener('input', (e) => {
    const term = e.target.value.toLowerCase();
    const filtered = stockData.filter(item =>
        item.product_name.toLowerCase().includes(term) ||
        item.model_name.toLowerCase().includes(term) ||
        item.product_code.toLowerCase().includes(term)
    );
    renderStockTable(filtered);
});

// ----------------------------------------------------
// TAB 2: Inbound (Manual & Excel)
// ----------------------------------------------------
let stagedInbound = [];
let allProducts = [];
let isProductLocked = false;

document.getElementById('in_product_name')?.addEventListener('change', (e) => {
    const pName = e.target.value.trim();
    const product = allProducts.find(p => p.name === pName);
    const modelList = document.getElementById('modelList');
    modelList.innerHTML = '';
    if (product && product.models) {
        product.models.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.name;
            modelList.appendChild(opt);
        });
    }
});

document.getElementById('lockProductBtn')?.addEventListener('click', () => {
    const pInput = document.getElementById('in_product_name');
    const mInput = document.getElementById('in_model_name');
    const lockBtn = document.getElementById('lockProductBtn');
    
    isProductLocked = !isProductLocked;
    pInput.readOnly = isProductLocked;
    mInput.readOnly = isProductLocked;
    
    if (isProductLocked) {
        pInput.classList.add('bg-gray-100');
        mInput.classList.add('bg-gray-100');
        lockBtn.innerHTML = '🔒';
        lockBtn.classList.replace('bg-gray-100', 'bg-purple-100');
        lockBtn.classList.replace('text-gray-600', 'text-purple-700');
        document.getElementById('in_sn').focus();
        Toast.info('ล็อคสินค้าเรียบร้อย สามารถสแกน SN ต่อเนื่องได้เลย');
    } else {
        pInput.classList.remove('bg-gray-100');
        mInput.classList.remove('bg-gray-100');
        lockBtn.innerHTML = '🔓';
        lockBtn.classList.replace('bg-purple-100', 'bg-gray-100');
        lockBtn.classList.replace('text-purple-700', 'text-gray-600');
    }
});

document.getElementById('in_sn')?.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        
        const pName = document.getElementById('in_product_name').value.trim();
        const mName = document.getElementById('in_model_name').value.trim();
        const snInput = document.getElementById('in_sn');
        const sn = snInput.value.trim();

        if (!pName || !mName) return Toast.error('กรุณาระบุชื่อสินค้าและรุ่นก่อนสแกน');
        if (sn.length < 12) return Toast.error('หมายเลขซีเรียลต้องมีอย่างน้อย 12 หลัก');
        
        if (stagedInbound.some(item => item.sn === sn)) {
            Toast.error('รายการนี้ถูกสแกนไว้ในคิวรับเข้าแล้ว');
            snInput.value = '';
            return;
        }

        stagedInbound.push({ product_name: pName, model_name: mName, sn: sn });
        renderInboundStaging();
        snInput.value = '';
        snInput.focus();
    }
});

function renderInboundStaging() {
    const tbody = document.getElementById('inboundStagingBody');
    const submitBtn = document.getElementById('submitInboundStagingBtn');

    tbody.innerHTML = '';
    if (stagedInbound.length === 0) {
        tbody.innerHTML = '<tr id="emptyInboundStaging"><td colspan="3" class="px-4 py-6 text-center text-gray-400">รอการสแกนรับเข้า...</td></tr>';
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'บันทึกรับเข้าคลัง (0 รายการ)';
        return;
    }

    submitBtn.disabled = false;
    submitBtn.innerHTML = `บันทึกรับเข้าคลัง (${stagedInbound.length} รายการ)`;

    stagedInbound.forEach((item, index) => {
        const row = document.createElement('tr');
        row.className = 'border-b border-gray-50';
        row.innerHTML = `
            <td class="px-4 py-2 font-mono text-indigo-600">${item.sn}</td>
            <td class="px-4 py-2">${item.product_name} - ${item.model_name}</td>
            <td class="px-4 py-2 text-center">
                <button type="button" onclick="removeInboundStaged(${index})" class="text-red-500 hover:text-red-700 font-bold">✕</button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

window.removeInboundStaged = function(index) {
    stagedInbound.splice(index, 1);
    renderInboundStaging();
};

document.getElementById('submitInboundStagingBtn')?.addEventListener('click', async () => {
    if (stagedInbound.length === 0) return;

    Loader.show();
    const btn = document.getElementById('submitInboundStagingBtn');
    btn.disabled = true;

    try {
        const res = await fetch('api/inventory/import_inbound.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items: stagedInbound })
        });
        const data = await res.json();

        if (data.success && data.imported > 0) {
            Toast.success(`บันทึกรับเข้าสำเร็จ ${data.imported} รายการ!`);
            stagedInbound = [];
            renderInboundStaging();
            if (stockData.length > 0) loadStockOverview();
        } else {
            Toast.error('เกิดข้อผิดพลาด: ' + (data.errors ? data.errors.join('\n') : data.error));
        }
    } catch (err) {
        Toast.error('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
    } finally {
        Loader.hide();
        btn.disabled = false;
    }
});

// Excel Inbound
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

            json.forEach((row, index) => {
                if (index === 0) return; // Skip header
                const pName = row[0], mName = row[1], sn = row[2];
                if (pName && mName) {
                    excelDataPayload.push({
                        product_name: String(pName),
                        model_name: String(mName),
                        sn: sn ? String(sn) : ''
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

function closeOutboundModal() {
    const modal = document.getElementById('outboundModal');
    modal.querySelector('div').classList.remove('animate__fadeInUp');
    modal.querySelector('div').classList.add('animate__fadeOutDown');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.querySelector('div').classList.remove('animate__fadeOutDown');
    }, 300);
}

document.getElementById('finalSubmitOutbound')?.addEventListener('click', async (e) => {
    if (stagedOutbound.length === 0) return;

    Loader.show();
    const btn = e.target;
    btn.disabled = true;

    const sns = stagedOutbound.map(i => i.sn);
    const receiver_id = document.getElementById('out_receiver_id').value;

    try {
        const res = await fetch('api/inventory/confirm_outbound.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sns, receiver_id })
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

        const receiverInfo = item.receiver_name ? `<br><span class="text-xs text-indigo-500">มอบให้: ${item.receiver_name} ${item.receiver_team ? `(${item.receiver_team})` : ''}</span>` : '';

        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors animate__animated animate__fadeIn';
        row.style.animationDelay = `${index * 0.02}s`;
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap text-slate-500 text-sm">${formattedDate}</td>
            <td class="px-6 py-4">${actionBadge}</td>
            <td class="px-6 py-4 font-mono text-xs font-bold text-indigo-600">${item.sn}</td>
            <td class="px-6 py-4 font-medium text-slate-700">${item.product_name} <span class="text-slate-400 font-normal">(${item.model_name})</span></td>
            <td class="px-6 py-4 text-slate-600 text-sm">${item.admin_name}</td>
            <td class="px-6 py-4 text-slate-600 text-sm">${item.receiver_name || '-'} ${item.receiver_team ? `<br><span class="text-[10px] bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-full">${item.receiver_team}</span>` : ''}</td>
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
        "หมายเลขซีเรียล": item.sn,
        "ชื่อสินค้า": item.product_name,
        "รุ่น": item.model_name,
        "ผู้ทำรายการ": item.admin_name
    }));

    const worksheet = XLSX.utils.json_to_sheet(exportData);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "ประวัติ");
    XLSX.writeFile(workbook, `ประวัติคลังสินค้า_${new Date().getTime()}.xlsx`);
    Toast.success('ดาวน์โหลดไฟล์ประวัติเรียบร้อยแล้ว');
});

async function loadProductsAndUsers() {
    try {
        const [prodRes, userRes] = await Promise.all([
            fetch('api/inventory/get_products.php').then(r => r.json()),
            fetch('api/users/get_users.php').then(r => r.json())
        ]);

        if (prodRes.success) {
            allProducts = prodRes.data;
            const productList = document.getElementById('productList');
            productList.innerHTML = '';
            allProducts.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.name;
                productList.appendChild(opt);
            });
        }

        if (userRes.success) {
            const receiverSelect = document.getElementById('out_receiver_id');
            const currentUserId = receiverSelect.value;
            receiverSelect.innerHTML = `<option value="${currentUserId}">🙋‍♂️ เบิกให้ตัวเอง</option>`;
            
            userRes.data.forEach(u => {
                if (u.id != currentUserId) {
                    receiverSelect.innerHTML += `<option value="${u.id}">👷‍♂️ ${u.full_name} ${u.team_name ? `(${u.team_name})` : ''}</option>`;
                }
            });
        }
    } catch (e) {
        console.error("Failed to load metadata", e);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadStockOverview();
    loadProductsAndUsers();
});