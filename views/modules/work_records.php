<?php
// views/modules/work_records.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');

if (!hasRole('intern')) {
    echo "<div class='p-12 text-center'><h2 class='text-2xl font-bold text-rose-500'>ไม่มีสิทธิ์เข้าถึงหน้านี้</h2><p class='text-slate-500 mt-2'>เฉพาะเด็กฝึกงานเท่านั้นที่สามารถใช้ฟีเจอร์นี้ได้</p></div>";
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
?>

<div class="space-y-6 pb-20 lg:pb-0">
    <div class="card flex flex-col md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-3xl font-black text-[var(--c-text-1)] tracking-tight flex items-center">
                <span class="mr-3 p-2 bg-[var(--c-primary-faint)] text-[var(--c-primary)] rounded-xl shadow-inner text-2xl"><i data-lucide="file-text" class="w-6 h-6"></i></span>
                รายงานการทำงาน
            </h2>
            <p class="text-[var(--c-text-3)] text-sm mt-1 font-medium">บันทึกและติดตามงานที่ทำมาในแต่ละวัน</p>
        </div>
        <div class="mt-4 md:mt-0 flex flex-col sm:flex-row gap-3">
            <button onclick="openRecordModal()" class="btn-primary flex items-center justify-center w-full sm:w-auto">
                <span class="mr-2 text-lg">+</span> เพิ่มรายงานใหม่
            </button>
        </div>
    </div>

    <div class="card !p-0 overflow-hidden animate__animated animate__fadeIn">
        <div class="px-6 py-4 border-b border-[var(--c-border)] bg-[var(--c-surface-2)] flex flex-col sm:flex-row justify-between items-center gap-4">
            <h3 class="font-black text-[var(--c-text-1)] tracking-tight">รายการรายงานการทำงาน</h3>
            <div class="relative w-full sm:w-64">
                <input type="text" id="searchRecord" placeholder="ค้นหารายงาน..." class="w-full pl-10 pr-4 py-2 input text-sm font-bold transition-all">
                <span class="absolute left-3 top-2.5 text-[var(--c-text-3)]"><i data-lucide="search" class="w-4 h-4"></i></span>
            </div>
        </div>
        
        <div class="overflow-x-auto w-full">
            <table class="w-full text-sm text-left text-[var(--c-text-2)] whitespace-nowrap">
                <thead class="text-[10px] text-[var(--c-text-3)] uppercase tracking-[0.1em] font-black bg-[var(--c-surface-3)]">
                    <tr>
                        <th class="px-6 py-4">วันที่</th>
                        <th class="px-6 py-4">ชื่องาน</th>
                        <th class="px-6 py-4">รายละเอียด</th>
                        <th class="px-6 py-4 text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody id="recordTableBody" class="divide-y divide-[var(--c-border)]">
                    <tr><td colspan="4" class="px-8 py-20 text-center"><div class="loader-spinner mx-auto mb-4 w-8 h-8"></div><p class="font-bold text-[var(--c-text-3)]">กำลังโหลดรายงาน...</p></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="recordModal" class="fixed inset-0 z-[80] hidden bg-[var(--c-overlay)] backdrop-blur-sm flex justify-center items-center p-4">
    <div class="bg-[var(--c-surface)] rounded-2xl w-full max-w-[95%] md:max-w-md overflow-hidden animate__animated animate__zoomIn z-[90]" style="box-shadow: var(--shadow-4);">
        <div class="p-6 bg-[var(--c-primary)] text-white flex justify-between items-center">
            <h3 id="modalTitle" class="text-xl font-bold tracking-tight">เพิ่มรายงานใหม่</h3>
            <button onclick="closeRecordModal()" class="text-white/70 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        
        <form id="recordForm" class="p-6 space-y-4">
            <input type="hidden" id="recordId" name="id">
            
            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-1">วันที่</label>
                <input type="date" id="record_date" name="record_date" required class="input">
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-1">ชื่องาน</label>
                <input type="text" id="title" name="title" required class="input" placeholder="กรอกชื่องาน">
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2 ml-1">รายละเอียด</label>
                <textarea id="content" name="content" class="input" rows="4" placeholder="รายละเอียดของงาน..."></textarea>
            </div>

            <div class="pt-4">
                <button type="submit" class="w-full py-4 btn-primary">
                    <i data-lucide="rocket" class="w-5 h-5 inline-block"></i> บันทึกรายงาน
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let allRecords = [];

document.addEventListener('DOMContentLoaded', () => {
    loadRecords();
    
    // Set today's date as default
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('record_date').value = today;

    document.getElementById('searchRecord')?.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        const filtered = allRecords.filter(r => 
            r.title.toLowerCase().includes(term) || 
            r.content.toLowerCase().includes(term)
        );
        renderRecordTable(filtered);
    });

    document.getElementById('recordForm')?.addEventListener('submit', handleSaveRecord);
});

async function loadRecords() {
    try {
        const res = await fetch('api/work_records/get.php');
        const data = await res.json();

        if (data.success) {
            allRecords = data.data;
            renderRecordTable(allRecords);
        } else {
            Toast.error(data.error || 'ไม่สามารถโหลดรายงาน');
        }
    } catch (e) {
        Toast.error('เกิดข้อผิดพลาดในการเชื่อมต่อ');
        console.error(e);
    }
}

function renderRecordTable(records) {
    const tbody = document.getElementById('recordTableBody');
    tbody.innerHTML = '';

    if (records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="px-8 py-10 text-center text-slate-400 italic">ไม่มีรายงานการทำงาน</td></tr>';
        return;
    }

    records.forEach((record, index) => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition-colors animate__animated animate__fadeIn';
        tr.style.animationDelay = `${index * 0.05}s`;
        
        const recordDate = new Date(record.record_date).toLocaleDateString('th-TH');
        const contentPreview = record.content ? record.content.substring(0, 50) + (record.content.length > 50 ? '...' : '') : 'ไม่มีรายละเอียด';

        tr.innerHTML = `
            <td class="px-8 py-5">${recordDate}</td>
            <td class="px-8 py-5 font-bold text-slate-700">${record.title}</td>
            <td class="px-8 py-5 text-slate-500 text-xs">${contentPreview}</td>
            <td class="px-8 py-5 text-center">
                <div class="flex justify-center space-x-2">
                    <button onclick="editRecord(${index})" class="p-2 text-indigo-500 hover:bg-indigo-50 rounded-xl transition-all" title="แก้ไข">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </button>
                    <button onclick="deleteRecord(${record.id})" class="p-2 text-rose-400 hover:bg-rose-50 rounded-xl transition-all" title="ลบ">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h14"></path></svg>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function openRecordModal(isEdit = false) {
    const modal = document.getElementById('recordModal');
    const title = document.getElementById('modalTitle');
    const form = document.getElementById('recordForm');

    form.reset();
    document.getElementById('recordId').value = '';
    
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('record_date').value = today;
    
    if (isEdit) {
        title.innerText = 'แก้ไขรายงาน';
    } else {
        title.innerText = 'เพิ่มรายงานใหม่';
    }

    modal.classList.remove('hidden');
    modal.querySelector('div').classList.add('animate__zoomIn');
}

function closeRecordModal() {
    const modal = document.getElementById('recordModal');
    modal.querySelector('div').classList.remove('animate__zoomIn');
    modal.querySelector('div').classList.add('animate__zoomOut');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.querySelector('div').classList.remove('animate__zoomOut');
    }, 300);
}

function editRecord(index) {
    const record = allRecords[index];
    openRecordModal(true);
    
    document.getElementById('recordId').value = record.id;
    document.getElementById('record_date').value = record.record_date;
    document.getElementById('title').value = record.title;
    document.getElementById('content').value = record.content || '';
}

async function handleSaveRecord(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const payload = Object.fromEntries(formData.entries());

    Swal.fire({
        title: 'กำลังบันทึกข้อมูล...',
        text: 'กรุณารอสักครู่',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        },
        customClass: { popup: 'rounded-3xl' }
    });

    try {
        const res = await fetch('api/work_records/save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        await new Promise(resolve => setTimeout(resolve, 600));

        if (data.success) {
            Swal.fire({
                title: 'สำเร็จ!',
                text: data.message,
                icon: 'success',
                confirmButtonColor: '#4f46e5',
                customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }
            });
            closeRecordModal();
            loadRecords();
        } else {
            Swal.fire({
                title: 'เกิดข้อผิดพลาด',
                text: data.error,
                icon: 'error',
                customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold' }
            });
        }
    } catch (err) {
        Swal.fire('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
    }
}

async function deleteRecord(id) {
    const result = await Swal.fire({
        title: 'ยืนยันการลบรายงาน?',
        text: "รายงานนี้จะถูกลบออกจากระบบอย่างถาวร",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ใช่, ลบเลย',
        cancelButtonText: 'ยกเลิก',
        customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md', cancelButton: 'rounded-xl px-6 py-2.5 font-bold' }
    });

    if (!result.isConfirmed) return;

    Swal.fire({
        title: 'กำลังลบข้อมูล...',
        text: 'กรุณารอสักครู่',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        },
        customClass: { popup: 'rounded-3xl' }
    });

    try {
        const res = await fetch('api/work_records/delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();

        await new Promise(resolve => setTimeout(resolve, 600));

        if (data.success) {
            Swal.fire({
                title: 'ลบสำเร็จ!',
                text: data.message,
                icon: 'success',
                confirmButtonColor: '#4f46e5',
                customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }
            });
            loadRecords();
        } else {
            Swal.fire('เกิดข้อผิดพลาด', data.error, 'error');
        }
    } catch (err) {
        Swal.fire('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
    }
}
</script>
