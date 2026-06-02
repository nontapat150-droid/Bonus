// assets/js/tech_bag.js

let techBagData = [];
let techConsumables = [];
let techHistory = [];
let availableTargets = [];

let currentViewUserId = ''; // For Admin view

function switchTechTab(tab) {
    document.querySelectorAll('.tech-tab').forEach(el => {
        el.classList.remove('active', 'bg-yellow-100', 'text-yellow-700', 'font-bold');
        el.classList.add('font-medium', 'text-slate-500', 'hover:bg-slate-50');
    });

    const activeTab = document.getElementById(`tab-${tab}`);
    if(activeTab) {
        activeTab.classList.add('active', 'bg-yellow-100', 'text-yellow-700', 'font-bold');
        activeTab.classList.remove('font-medium', 'text-slate-500', 'hover:bg-slate-50');
    }

    document.getElementById('view-bag').classList.add('hidden');
    document.getElementById('view-history').classList.add('hidden');
    document.getElementById(`view-${tab}`).classList.remove('hidden');

    if (tab === 'bag') {
        loadTechBag();
    } else if (tab === 'history') {
        loadTechHistory();
    }
}

async function loadAdminTechDropdown() {
    const select = document.getElementById('adminViewTechSelect');
    if (!select) return; // Not admin view
    try {
        const res = await fetch('api/inventory/get_outbound_targets.php');
        const data = await res.json();
        if (data.success) {
            let html = '<option value="">-- ของฉัน (หรือไม่มีช่าง) --</option>';
            data.users.forEach(u => {
                html += `<option value="${u.id}">${u.full_name} ${u.team_name ? `(${u.team_name})`:''}</option>`;
            });
            select.innerHTML = html;
            
            // On change, reload current tab
            select.addEventListener('change', (e) => {
                currentViewUserId = e.target.value;
                const activeTab = document.querySelector('.tech-tab.active').id.replace('tab-', '');
                if (activeTab === 'bag') loadTechBag();
                else loadTechHistory();
            });
        }
    } catch (e) { console.error(e); }
}

async function loadTechBag() {
    try {
        let url = 'api/inventory/get_tech_bag.php';
        if (currentViewUserId) url += '?target_user_id=' + currentViewUserId;

        const res = await fetch(url);
        const data = await res.json();
        
        if (data.success) {
            techBagData = data.data;
            techConsumables = data.consumables;
            renderTechBag();
        } else {
            Toast.error('โหลดข้อมูลกระเป๋าช่างล้มเหลว: ' + data.error);
        }
    } catch (e) {
        console.error(e);
        Toast.error('เชื่อมต่อเซิร์ฟเวอร์ล้มเหลว');
    }
}

function renderTechBag() {
    const snBody = document.getElementById('techBagItemsBody');
    const conBody = document.getElementById('techBagConsumablesBody');
    const badge = document.getElementById('bagCountBadge');

    snBody.innerHTML = '';
    conBody.innerHTML = '';

    const totalItems = techBagData.length + techConsumables.length;
    if (totalItems > 0) {
        badge.textContent = totalItems;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }

    // SN Items
    if (techBagData.length === 0) {
        snBody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-slate-400 italic">ไม่มีอุปกรณ์ในกระเป๋า</td></tr>';
    } else {
        techBagData.forEach((item, index) => {
            const dateObj = new Date(item.timestamp);
            const formattedDate = dateObj.toLocaleDateString('th-TH');
            
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-indigo-50/30 border-b border-slate-50';
            tr.innerHTML = `
                <td class="px-6 py-3 text-center">
                    <input type="checkbox" class="sn-checkbox rounded text-indigo-600 focus:ring-indigo-500" value="${item.id}" data-sn="${item.sn}">
                </td>
                <td class="px-6 py-3 font-mono font-bold text-indigo-600">${item.sn}</td>
                <td class="px-6 py-3 font-medium text-slate-700">${item.product_name}</td>
                <td class="px-6 py-3 text-slate-500">${item.model_name}</td>
                <td class="px-6 py-3 text-slate-500 text-sm">${formattedDate}</td>
            `;
            snBody.appendChild(tr);
        });
    }

    // Consumables
    if (techConsumables.length === 0) {
        conBody.innerHTML = '<tr><td colspan="3" class="px-6 py-8 text-center text-slate-400 italic">ไม่มีวัสดุสิ้นเปลืองในกระเป๋า</td></tr>';
    } else {
        techConsumables.forEach(item => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-yellow-50/30 border-b border-slate-50';
            tr.innerHTML = `
                <td class="px-6 py-3 font-medium text-slate-700">${item.product_name}</td>
                <td class="px-6 py-3 text-right font-mono font-bold text-yellow-600">${item.qty} <span class="text-xs text-slate-500 font-normal">${item.unit}</span></td>
                <td class="px-6 py-3 text-center space-x-2">
                    <button onclick="openUseConsumable(${item.consumable_id}, '${item.product_name}', ${item.qty}, '${item.unit}')" class="text-xs bg-green-100 text-green-700 hover:bg-green-200 px-3 py-1.5 rounded-lg font-bold transition">✅ ใช้งาน</button>
                    <button onclick="openTransferConsumable(${item.consumable_id}, '${item.product_name}', ${item.qty}, '${item.unit}')" class="text-xs bg-blue-100 text-blue-700 hover:bg-blue-200 px-3 py-1.5 rounded-lg font-bold transition">🔄 โอนย้าย</button>
                </td>
            `;
            conBody.appendChild(tr);
        });
    }
}

// Checkbox select all
document.getElementById('selectAllItems')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.sn-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
});

async function useSelectedItems() {
    const checkboxes = document.querySelectorAll('.sn-checkbox:checked');
    if (checkboxes.length === 0) {
        Toast.warning('กรุณาเลือกอุปกรณ์ที่ต้องการใช้งาน');
        return;
    }

    const itemIds = Array.from(checkboxes).map(cb => cb.value);

    const result = await Swal.fire({
        title: 'ยืนยันการใช้งาน?',
        text: `คุณต้องการบันทึกการใช้งานอุปกรณ์จำนวน ${itemIds.length} รายการใช่หรือไม่? (สินค้าจะถูกตัดออกจากกระเป๋า)`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'ยืนยันการใช้งาน',
        cancelButtonText: 'ยกเลิก'
    });

    if (!result.isConfirmed) return;

    Loader.show();
    try {
        const res = await fetch('api/inventory/use_item.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ item_ids: itemIds })
        });
        const data = await res.json();
        
        Loader.hide();
        if (data.success) {
            Toast.success('บันทึกการใช้งานเรียบร้อยแล้ว');
            document.getElementById('selectAllItems').checked = false;
            loadTechBag();
        } else {
            Toast.error('เกิดข้อผิดพลาด: ' + data.error);
        }
    } catch (e) {
        Loader.hide();
        Toast.error('การเชื่อมต่อล้มเหลว');
    }
}

// ---------------- Transfer SN ----------------
async function loadTargets() {
    if (availableTargets.length > 0) return;
    try {
        const res = await fetch('api/inventory/get_outbound_targets.php');
        const data = await res.json();
        if (data.success) {
            availableTargets = data.users;
            const html = '<option value="">-- เลือกช่างผู้รับโอน --</option>' + 
                availableTargets.map(u => `<option value="${u.id}">${u.full_name} ${u.team_name ? `(ทีม ${u.team_name})`:''}</option>`).join('');
            
            const selectSN = document.getElementById('transferTargetUser');
            if (selectSN) selectSN.innerHTML = html;
            
            const selectCons = document.getElementById('transferConsTarget');
            if (selectCons) selectCons.innerHTML = html;
        }
    } catch (e) { console.error('Failed to load targets'); }
}

function openTransferModal() {
    const checkboxes = document.querySelectorAll('.sn-checkbox:checked');
    if (checkboxes.length === 0) {
        Toast.warning('กรุณาเลือกอุปกรณ์ที่ต้องการโอนย้าย');
        return;
    }
    
    document.getElementById('transferCount').textContent = checkboxes.length;
    document.getElementById('transferTargetUser').value = '';
    loadTargets();
    
    const modal = document.getElementById('transferModal');
    modal.classList.remove('hidden');
    modal.querySelector('div').classList.remove('animate__zoomOut');
    modal.querySelector('div').classList.add('animate__animated', 'animate__zoomIn');
}

function closeTransferModal() {
    const modal = document.getElementById('transferModal');
    modal.querySelector('div').classList.remove('animate__zoomIn');
    modal.querySelector('div').classList.add('animate__zoomOut');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 250);
}

document.getElementById('submitTransferBtn')?.addEventListener('click', async (e) => {
    const targetUserId = document.getElementById('transferTargetUser').value;
    if (!targetUserId) {
        Toast.error('กรุณาเลือกช่างผู้รับโอน');
        return;
    }

    const checkboxes = document.querySelectorAll('.sn-checkbox:checked');
    const sns = Array.from(checkboxes).map(cb => cb.getAttribute('data-sn'));

    Loader.show();
    e.target.disabled = true;
    try {
        const res = await fetch('api/inventory/submit_transfer.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ sns: sns, target_user_id: targetUserId })
        });
        const data = await res.json();
        
        Loader.hide();
        e.target.disabled = false;
        
        if (data.success) {
            Toast.success(`โอนย้าย ${data.processed} รายการสำเร็จ!`);
            closeTransferModal();
            document.getElementById('selectAllItems').checked = false;
            loadTechBag();
        } else {
            Toast.error(data.error);
        }
    } catch (err) {
        Loader.hide();
        e.target.disabled = false;
        Toast.error('การเชื่อมต่อล้มเหลว');
    }
});

// ---------------- Use Consumables ----------------
function openUseConsumable(id, name, qty, unit) {
    document.getElementById('useConsumableId').value = id;
    document.getElementById('useConsumableName').textContent = name;
    document.getElementById('useConsumableQty').value = '';
    document.getElementById('useConsumableUnit').textContent = unit;
    document.getElementById('useConsumableMax').textContent = `มีให้ใช้งานได้สูงสุด: ${qty} ${unit}`;
    document.getElementById('useConsumableQty').max = qty;
    
    const modal = document.getElementById('useConsumableModal');
    modal.classList.remove('hidden');
    modal.querySelector('div').classList.remove('animate__zoomOut');
    modal.querySelector('div').classList.add('animate__animated', 'animate__zoomIn');
}

function closeUseConsumableModal() {
    const modal = document.getElementById('useConsumableModal');
    modal.querySelector('div').classList.remove('animate__zoomIn');
    modal.querySelector('div').classList.add('animate__zoomOut');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 250);
}

document.getElementById('submitUseConsumableBtn')?.addEventListener('click', async (e) => {
    const id = document.getElementById('useConsumableId').value;
    const qty = parseFloat(document.getElementById('useConsumableQty').value);
    
    if (!qty || qty <= 0) {
        Toast.error('ระบุจำนวนให้ถูกต้อง');
        return;
    }

    Loader.show();
    e.target.disabled = true;
    try {
        const res = await fetch('api/inventory/use_consumable.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ consumable_id: id, qty: qty })
        });
        const data = await res.json();
        
        Loader.hide();
        e.target.disabled = false;
        
        if (data.success) {
            Toast.success(`บันทึกการใช้งานสำเร็จ!`);
            closeUseConsumableModal();
            loadTechBag();
        } else {
            Toast.error(data.error);
        }
    } catch (err) {
        Loader.hide();
        e.target.disabled = false;
        Toast.error('การเชื่อมต่อล้มเหลว');
    }
});

// ---------------- Transfer Consumables ----------------
function openTransferConsumable(id, name, qty, unit) {
    document.getElementById('transferConsId').value = id;
    document.getElementById('transferConsName').textContent = name;
    document.getElementById('transferConsQty').value = '';
    document.getElementById('transferConsUnit').textContent = unit;
    document.getElementById('transferConsMax').textContent = `มีให้โอนได้สูงสุด: ${qty} ${unit}`;
    document.getElementById('transferConsQty').max = qty;
    document.getElementById('transferConsTarget').value = '';
    
    loadTargets();
    
    const modal = document.getElementById('transferConsumableModal');
    modal.classList.remove('hidden');
    modal.querySelector('div').classList.remove('animate__zoomOut');
    modal.querySelector('div').classList.add('animate__animated', 'animate__zoomIn');
}

function closeTransferConsumableModal() {
    const modal = document.getElementById('transferConsumableModal');
    modal.querySelector('div').classList.remove('animate__zoomIn');
    modal.querySelector('div').classList.add('animate__zoomOut');
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 250);
}

document.getElementById('submitTransferConsBtn')?.addEventListener('click', async (e) => {
    const id = document.getElementById('transferConsId').value;
    const qty = parseFloat(document.getElementById('transferConsQty').value);
    const targetUserId = document.getElementById('transferConsTarget').value;
    
    if (!qty || qty <= 0) {
        Toast.error('ระบุจำนวนให้ถูกต้อง');
        return;
    }
    if (!targetUserId) {
        Toast.error('ระบุผู้รับโอน');
        return;
    }

    Loader.show();
    e.target.disabled = true;
    try {
        const res = await fetch('api/inventory/transfer_qty.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ 
                items: [{ consumable_id: id, qty: qty }], 
                target_user_id: targetUserId 
            })
        });
        const data = await res.json();
        
        Loader.hide();
        e.target.disabled = false;
        
        if (data.success) {
            Toast.success(`โอนย้ายสำเร็จ!`);
            closeTransferConsumableModal();
            loadTechBag();
        } else {
            Toast.error(data.error);
        }
    } catch (err) {
        Loader.hide();
        e.target.disabled = false;
        Toast.error('การเชื่อมต่อล้มเหลว');
    }
});


// ---------------- History ----------------
async function loadTechHistory() {
    try {
        const tbody = document.getElementById('techHistoryBody');
        if(tbody) tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-12 text-center text-slate-400"><div class="flex flex-col items-center justify-center"><div class="loader-spinner mb-4 w-8 h-8"></div> กำลังโหลดประวัติ...</div></td></tr>';
        
        let url = 'api/inventory/get_tech_history.php';
        if (currentViewUserId) url += '?target_user_id=' + currentViewUserId;

        const res = await fetch(url);
        const data = await res.json();

        if (data.success) {
            techHistory = data.data;
            renderTechHistory();
        } else {
            Toast.error('โหลดประวัติล้มเหลว: ' + data.error);
        }
    } catch (e) {
        console.error(e);
        Toast.error('ไม่สามารถโหลดประวัติการทำรายการได้');
    }
}

function renderTechHistory() {
    const tbody = document.getElementById('techHistoryBody');
    if(!tbody) return;
    tbody.innerHTML = '';

    if (techHistory.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-12 text-center text-gray-400 italic">ยังไม่มีประวัติการทำรายการ</td></tr>';
        return;
    }

    techHistory.forEach((item, index) => {
        const dateObj = new Date(item.timestamp);
        const formattedDate = dateObj.toLocaleDateString('th-TH') + ' ' + dateObj.toLocaleTimeString('th-TH');  
        
        let actionBadge = '';
        if (item.action === 'out') {
            actionBadge = '<span class="px-3 py-1 bg-emerald-50 text-emerald-700 text-xs rounded-full font-bold border border-emerald-100">📥 รับเข้ากระเป๋า</span>';
        } else if (item.action === 'transfer') {
             actionBadge = '<span class="px-3 py-1 bg-blue-50 text-blue-700 text-xs rounded-full font-bold border border-blue-100">🔄 โอนย้าย</span>';
        } else if (item.action === 'used') {
             actionBadge = '<span class="px-3 py-1 bg-slate-100 text-slate-700 text-xs rounded-full font-bold border border-slate-300">✅ ใช้งานแล้ว</span>';
        } else if (item.action === 'in') {
             actionBadge = '<span class="px-3 py-1 bg-rose-50 text-rose-700 text-xs rounded-full font-bold border border-rose-100">📤 คืนคลัง</span>';
        }

        let detailText = `ทำโดย: ${item.admin_name || 'System'}`;
        if (item.target_name) {
            detailText += `<br><span class="text-xs text-blue-500 font-bold">ไปยัง: ${item.target_name}</span>`;
        }

        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors animate__animated animate__fadeIn';
        row.style.animationDelay = `${index * 0.02}s`;
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap text-slate-500 text-sm">${formattedDate}</td>
            <td class="px-6 py-4">${actionBadge}</td>
            <td class="px-6 py-4 font-mono text-xs font-bold text-indigo-600">${item.sn || '-'}</td>
            <td class="px-6 py-4 font-medium text-slate-700">${item.product_name} <span class="text-slate-400 font-normal">(${item.model_name || 'วัสดุสิ้นเปลือง'})</span></td>
            <td class="px-6 py-4 text-slate-600 text-sm">${detailText}</td>
        `;
        tbody.appendChild(row);
    });
}

// Init
document.addEventListener('DOMContentLoaded', () => {
    loadAdminTechDropdown();
    loadTechBag();
});
