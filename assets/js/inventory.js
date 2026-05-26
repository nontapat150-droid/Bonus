// assets/js/inventory.js

// View switching logic
function invTab(tabName) {
    // Hide all views
    document.querySelectorAll('.inv-view').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.inv-view').forEach(el => el.classList.remove('block'));
    
    // Reset all tab buttons styling
    document.querySelectorAll('.inv-tab').forEach(el => {
        el.classList.remove('bg-purple-100', 'text-purple-700', 'font-bold');
        el.classList.add('text-gray-500', 'font-medium');
    });

    // Show selected view
    document.getElementById(`view-${tabName}`).classList.remove('hidden');
    document.getElementById(`view-${tabName}`).classList.add('block');
    
    // Highlight selected tab
    const activeBtn = document.getElementById(`tab-${tabName}`);
    activeBtn.classList.remove('text-gray-500', 'font-medium');
    activeBtn.classList.add('bg-purple-100', 'text-purple-700', 'font-bold');

    // Trigger data load if needed
    if (tabName === 'overview') loadStockOverview();
    if (tabName === 'history') loadHistory();
}

// ----------------------------------------------------
// TAB 1: Stock Overview
// ----------------------------------------------------
let stockData = [];

async function loadStockOverview() {
    try {
        document.getElementById('stockTableBody').innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">กำลังโหลดข้อมูล...</td></tr>';
        const res = await fetch('api/inventory/get_stock.php');
        const data = await res.json();
        
        if (data.success) {
            stockData = data.data;
            renderStockTable(stockData);
        } else {
            alert('Error: ' + data.error);
        }
    } catch (e) {
        console.error(e);
        alert('Failed to load stock data.');
    }
}

function renderStockTable(data) {
    const tbody = document.getElementById('stockTableBody');
    tbody.innerHTML = '';
    
    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">ไม่พบข้อมูลสินค้าในคลัง</td></tr>';
        return;
    }

    data.forEach(item => {
        tbody.innerHTML += `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 font-mono text-xs">${item.product_code}</td>
                <td class="px-6 py-4 font-bold text-gray-800">${item.product_name}</td>
                <td class="px-6 py-4">${item.model_name}</td>
                <td class="px-6 py-4 text-center">
                    <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-sm font-bold ${item.qty > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                        ${item.qty}
                    </span>
                </td>
                <td class="px-6 py-4 text-center">
                    <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">ดู SN</button>
                </td>
            </tr>
        `;
    });
}

// Simple client-side search
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

// Manual Inbound
document.getElementById('inboundForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const pName = document.getElementById('in_product_name').value.trim();
    const mName = document.getElementById('in_model_name').value.trim();
    const sn = document.getElementById('in_sn').value.trim();
    const btn = e.target.querySelector('button[type="submit"]');

    if (!pName || !mName) return alert('กรุณากรอกชื่อสินค้าและโมเดล');

    btn.disabled = true;
    btn.innerHTML = 'กำลังบันทึก...';

    const payload = {
        items: [{ product_name: pName, model_name: mName, sn: sn }]
    };

    try {
        const res = await fetch('api/inventory/import_inbound.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if (data.success && data.imported > 0) {
            alert(`รับเข้าสำเร็จ 1 รายการ`);
            document.getElementById('inboundForm').reset();
            if (stockData.length > 0) loadStockOverview(); // Refresh stock if loaded
        } else {
            alert('Error: ' + (data.errors ? data.errors.join('\n') : data.error));
        }
    } catch (err) {
        alert('Failed to connect to server.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'บันทึกรับเข้า';
    }
});

// Excel Inbound
let excelDataPayload = [];

document.getElementById('excelImport')?.addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, {type: 'array'});
        const firstSheetName = workbook.SheetNames[0];
        const worksheet = workbook.Sheets[firstSheetName];
        
        // Assuming columns: A = Product Name, B = Model Name, C = SN
        // We can parse it as array of arrays
        const json = XLSX.utils.sheet_to_json(worksheet, {header: 1});
        
        excelDataPayload = [];
        let skipped = 0;

        json.forEach((row, index) => {
            // Find keys that match our expected columns (ignoring case/spaces)
            const keys = Object.keys(row);
            let pName = '', mName = '', sn = '';
            
            keys.forEach(k => {
                const kLower = k.toLowerCase().replace(/\s/g, '');
                if (kLower.includes('product')) pName = row[k];
                else if (kLower.includes('model')) mName = row[k];
                else if (kLower === 'sn' || kLower.includes('serial')) sn = row[k];
            });

            // If header=1 (arrays), index 0 is row 1
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
            countP.textContent = `พบข้อมูลพร้อมนำเข้า ${excelDataPayload.length} รายการ (ข้าม ${skipped} แถวที่ไม่สมบูรณ์)`;
            previewDiv.classList.remove('hidden');
        } else {
            alert('ไม่พบข้อมูลที่ตรงกับรูปแบบ (Product, Model, SN)');
            previewDiv.classList.add('hidden');
            document.getElementById('excelImport').value = '';
        }
    };
    reader.readAsArrayBuffer(file);
});

document.getElementById('confirmExcelBtn')?.addEventListener('click', async (e) => {
    if (excelDataPayload.length === 0) return;

    const btn = e.target;
    btn.disabled = true;
    btn.innerHTML = 'กำลังนำเข้า กรุณารอสักครู่...';

    try {
        const res = await fetch('api/inventory/import_inbound.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items: excelDataPayload })
        });
        const data = await res.json();
        
        if (data.success) {
            let msg = `นำเข้าสำเร็จ ${data.imported} รายการ`;
            if (data.errors && data.errors.length > 0) {
                msg += `\n\nมีข้อผิดพลาด ${data.errors.length} รายการ:\n` + data.errors.slice(0,5).join('\n') + (data.errors.length > 5 ? '\n...' : '');
            }
            alert(msg);
            document.getElementById('excelPreview').classList.add('hidden');
            document.getElementById('excelImport').value = '';
            excelDataPayload = [];
            loadStockOverview();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (err) {
        alert('Failed to connect to server.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'ยืนยันนำเข้าทั้งหมด';
    }
});

// Load initial data
document.addEventListener('DOMContentLoaded', () => {
    loadStockOverview();
});

// Outbound modal functions
function closeOutboundModal() {
    document.getElementById('outboundModal').classList.add('hidden');
}

// ----------------------------------------------------
// TAB 3: Outbound (Staging & Confirm)
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
        alert('SN นี้อยู่ในคิวรอเบิกแล้ว');
        snInput.value = '';
        return;
    }

    try {
        const res = await fetch(`api/inventory/check_outbound.php?sn=${encodeURIComponent(sn)}`);
        const data = await res.json();
        
        if (data.success) {
            stagedOutbound.push(data.data);
            renderStaging();
            snInput.value = '';
            snInput.focus();
        } else {
            alert(data.error);
            snInput.select();
        }
    } catch (e) {
        alert('Failed to connect to server.');
    }
});

function renderStaging() {
    const tbody = document.getElementById('stagingTableBody');
    const badge = document.getElementById('outboundBadge');
    const confirmBtn = document.getElementById('confirmOutboundBtn');
    
    tbody.innerHTML = '';
    
    if (stagedOutbound.length === 0) {
        tbody.innerHTML = '<tr id="emptyStaging"><td colspan="3" class="px-6 py-8 text-center text-gray-400">ยังไม่มีรายการสแกน รอการเบิก</td></tr>';
        badge.classList.add('hidden');
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'ยืนยันการเบิกออก (0 รายการ)';
        return;
    }

    badge.textContent = stagedOutbound.length;
    badge.classList.remove('hidden');
    confirmBtn.disabled = false;
    confirmBtn.textContent = `เปิดบิลยืนยันการเบิก (${stagedOutbound.length} รายการ)`;

    stagedOutbound.forEach((item, index) => {
        tbody.innerHTML += `
            <tr>
                <td class="px-6 py-3 font-mono font-bold">${item.sn}</td>
                <td class="px-6 py-3">${item.product_name} - ${item.model_name}</td>
                <td class="px-6 py-3 text-center">
                    <button onclick="removeStaged(${index})" class="text-red-500 hover:text-red-700 font-bold">X</button>
                </td>
            </tr>
        `;
    });
}

window.removeStaged = function(index) {
    stagedOutbound.splice(index, 1);
    renderStaging();
};

document.getElementById('confirmOutboundBtn')?.addEventListener('click', () => {
    // Show Modal
    const modal = document.getElementById('outboundModal');
    const billBody = document.getElementById('billTableBody');
    
    document.getElementById('billDate').textContent = new Date().toLocaleString('th-TH');
    document.getElementById('billTotal').textContent = `${stagedOutbound.length} ชิ้น`;
    
    billBody.innerHTML = '';
    stagedOutbound.forEach((item, i) => {
        billBody.innerHTML += `
            <tr>
                <td class="py-2 text-gray-500">${i+1}.</td>
                <td class="py-2 font-medium">${item.product_name} - ${item.model_name}</td>
                <td class="py-2 text-right font-mono text-sm">${item.sn}</td>
            </tr>
        `;
    });
    
    modal.classList.remove('hidden');
});

document.getElementById('finalSubmitOutbound')?.addEventListener('click', async (e) => {
    if (stagedOutbound.length === 0) return;
    
    const btn = e.target;
    btn.disabled = true;
    btn.innerHTML = 'กำลังประมวลผล...';

    const sns = stagedOutbound.map(i => i.sn);

    try {
        const res = await fetch('api/inventory/confirm_outbound.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sns })
        });
        const data = await res.json();
        
        if (data.success) {
            alert(`ตัดสต๊อกสำเร็จ ${data.processed} รายการ`);
            stagedOutbound = [];
            renderStaging();
            closeOutboundModal();
            loadStockOverview(); // Refresh stock
        } else {
            alert('Error: ' + data.error);
        }
    } catch (err) {
        alert('Failed to connect to server.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="mr-2">✅</span> ยืนยันตัดสต๊อกทันที';
    }
});

// ----------------------------------------------------
// TAB 4: History & Export
// ----------------------------------------------------
let historyData = [];

async function loadHistory() {
    try {
        document.getElementById('historyTableBody').innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">กำลังโหลดข้อมูล...</td></tr>';
        const res = await fetch('api/inventory/get_history.php');
        const data = await res.json();
        
        if (data.success) {
            historyData = data.data;
            renderHistoryTable();
        } else {
            document.getElementById('historyTableBody').innerHTML = `<tr><td colspan="5" class="px-6 py-4 text-center text-red-500">Error: ${data.error}</td></tr>`;
        }
    } catch (e) {
        console.error(e);
    }
}

function renderHistoryTable() {
    const tbody = document.getElementById('historyTableBody');
    tbody.innerHTML = '';
    
    if (historyData.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">ไม่พบประวัติการทำรายการ</td></tr>';
        return;
    }

    historyData.forEach(item => {
        const dateObj = new Date(item.timestamp);
        const formattedDate = dateObj.toLocaleDateString('th-TH') + ' ' + dateObj.toLocaleTimeString('th-TH');
        const actionBadge = item.action === 'in' 
            ? '<span class="px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full font-bold">รับเข้า</span>' 
            : '<span class="px-2 py-1 bg-red-100 text-red-700 text-xs rounded-full font-bold">เบิกออก</span>';

        tbody.innerHTML += `
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">${formattedDate}</td>
                <td class="px-6 py-4">${actionBadge}</td>
                <td class="px-6 py-4 font-mono text-xs">${item.sn}</td>
                <td class="px-6 py-4">${item.product_name} - ${item.model_name}</td>
                <td class="px-6 py-4">${item.admin_name}</td>
            </tr>
        `;
    });
}

document.getElementById('exportHistoryBtn')?.addEventListener('click', () => {
    if (historyData.length === 0) return alert('ไม่มีข้อมูลสำหรับ Export');
    
    // Format data for Excel
    const exportData = historyData.map(item => ({
        "วันที่/เวลา": new Date(item.timestamp).toLocaleString('th-TH'),
        "ประเภท": item.action === 'in' ? 'รับเข้า' : 'เบิกออก',
        "Serial Number": item.sn,
        "ชื่อสินค้า": item.product_name,
        "โมเดล": item.model_name,
        "ผู้ทำรายการ": item.admin_name
    }));

    const worksheet = XLSX.utils.json_to_sheet(exportData);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "History");
    
    // Trigger download
    XLSX.writeFile(workbook, `Inventory_History_${new Date().getTime()}.xlsx`);
});
