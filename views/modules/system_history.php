<?php
// views/modules/system_history.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');
if (!hasRole(['admin', 'super_admin'])) exit('ไม่มีสิทธิ์เข้าถึงหน้านี้');
?>

<div class="max-w-6xl mx-auto space-y-6 animate__animated animate__fadeIn">
    
    <div class="bg-indigo-600 rounded-3xl px-8 py-6 shadow-lg text-white flex flex-col md:flex-row items-center justify-between">
        <div>
            <h2 class="text-2xl md:text-3xl font-black flex items-center">
                <i data-lucide="database" class="w-8 h-8 mr-3"></i> ศูนย์ข้อมูลประวัติรวมทั้งหมด
            </h2>
            <p class="text-indigo-100 mt-2">ดูประวัติการทำรายการของพนักงานทุกคนในระบบ ค้นหาได้ทั้งรายวันและรายเดือน</p>
        </div>
    </div>

    <div class="bg-white p-2 rounded-2xl border border-slate-200 shadow-sm flex overflow-x-auto gap-2 custom-scrollbar">
        <button onclick="loadHistory('checkin')" id="tab-checkin" class="hist-tab active-tab px-5 py-3 rounded-xl font-bold whitespace-nowrap flex-1 text-center transition-all bg-indigo-50 text-indigo-700">
            📸 เช็คอินเข้างาน
        </button>
        <button onclick="loadHistory('start_day')" id="tab-start_day" class="hist-tab px-5 py-3 rounded-xl font-bold whitespace-nowrap flex-1 text-center transition-all text-slate-500 hover:bg-slate-50">
            🏁 ค่าแรกเข้า
        </button>
        <button onclick="loadHistory('oil')" id="tab-oil" class="hist-tab px-5 py-3 rounded-xl font-bold whitespace-nowrap flex-1 text-center transition-all text-slate-500 hover:bg-slate-50">
            ⛽ เติมน้ำมัน
        </button>
        <button onclick="loadHistory('inventory')" id="tab-inventory" class="hist-tab px-5 py-3 rounded-xl font-bold whitespace-nowrap flex-1 text-center transition-all text-slate-500 hover:bg-slate-50">
            📦 คลังสินค้า
        </button>
    </div>

    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-4 items-center justify-between">
        <div class="flex items-center gap-2 font-bold text-slate-700">
            <i data-lucide="calendar-search" class="w-5 h-5 text-indigo-500"></i> ตัวกรองเวลา <span id="recordCountBadge" class="ml-2 bg-slate-100 text-slate-600 px-2 py-0.5 rounded-md text-xs">0 รายการ</span>
        </div>
        <div class="flex flex-wrap items-center justify-center gap-2 w-full sm:w-auto">
            <div class="relative w-full sm:w-auto">
                <input type="hidden" id="filterDate" class="hidden">
                <input type="text" id="filterDateDisplay" readonly placeholder="เลือกวันที่" class="input py-2 text-sm w-full sm:w-auto cursor-pointer bg-white">
                <div id="datePickerPopup" class="floating-calendar hidden"></div>
            </div>
            <span class="text-slate-400 text-sm hidden sm:inline">หรือ</span>
            <input type="month" id="filterMonth" class="input py-2 text-sm w-full sm:w-auto">
            <button onclick="applyFilter()" class="btn-primary py-2 px-6 w-full sm:w-auto text-sm shadow-md">
                <i data-lucide="search" class="w-4 h-4"></i> ค้นหา
            </button>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto bg-white min-h-[400px]">
            <table class="w-full text-sm text-left block md:table">
                <thead id="tableHead" class="hidden md:table-header-group text-xs text-slate-500 uppercase bg-slate-50 border-b border-slate-100">
                    </thead>
                <tbody id="tableBody" class="block md:table-row-group divide-y divide-slate-100">
                    <tr><td class="text-center py-10 text-slate-400 block md:table-cell">กำลังโหลดข้อมูล...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .active-tab { background-color: #eef2ff !important; color: #4338ca !important; }
    .floating-calendar {
        position: absolute;
        top: 100%;
        left: 0;
        width: min(340px, calc(100vw - 2rem));
        background: #ffffff;
        border-radius: 1rem;
        box-shadow: 0 24px 80px rgba(15, 23, 42, 0.18);
        border: 1px solid rgba(148, 163, 184, 0.18);
        padding: 1rem;
        z-index: 60;
        overflow: hidden;
    }
    .floating-calendar.hidden { display: none; }
    .floating-calendar header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }
    .floating-calendar header h3 {
        font-weight: 700;
        font-size: 0.95rem;
        color: #1f2937;
    }
    .floating-calendar button {
        background: transparent;
        border: none;
        color: #4f46e5;
        font-size: 1.1rem;
        cursor: pointer;
        padding: 0.35rem;
        border-radius: 9999px;
        transition: background 0.2s ease;
    }
    .floating-calendar button:hover { background: rgba(79, 70, 229, 0.08); }
    .floating-calendar .day-labels,
    .floating-calendar .day-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 0.35rem;
    }
    .floating-calendar .day-labels span {
        font-size: 0.75rem;
        color: #6b7280;
        text-align: center;
        font-weight: 700;
    }
    .floating-calendar .day-cell {
        width: 38px;
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.75rem;
        font-size: 0.9rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
        color: #374151;
    }
    .floating-calendar .day-cell:hover {
        background: #eef2ff;
        color: #312e81;
    }
    .floating-calendar .day-cell.disabled {
        color: #cbd5e1;
        cursor: default;
    }
    .floating-calendar .day-cell.selected {
        background: #4f46e5;
        color: #ffffff;
        box-shadow: 0 10px 30px rgba(79, 70, 229, 0.18);
    }
    .floating-calendar .day-cell.today {
        box-shadow: inset 0 0 0 1px rgba(79, 70, 229, 0.25);
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const displayField = document.getElementById('filterDateDisplay');
        const hiddenField = document.getElementById('filterDate');
        const monthField = document.getElementById('filterMonth');
        const popup = document.getElementById('datePickerPopup');

        if (!displayField || !hiddenField || !popup) return;

        const dayNames = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];
        const monthNames = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
        let currentMonth = new Date();

        function formatIso(date) {
            return date.toISOString().slice(0, 10);
        }

        function formatDisplay(date) {
            return `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}/${date.getFullYear()}`;
        }

        function renderCalendar(date) {
            currentMonth = new Date(date.getFullYear(), date.getMonth(), 1);
            const year = currentMonth.getFullYear();
            const month = currentMonth.getMonth();
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const selectedDate = hiddenField.value ? new Date(hiddenField.value) : null;
            const today = new Date();

            let html = `
                <header>
                    <button type="button" data-calendar-nav="prev" aria-label="เดือนก่อนหน้า">‹</button>
                    <div>
                        <h3>${monthNames[month]} ${year}</h3>
                    </div>
                    <button type="button" data-calendar-nav="next" aria-label="เดือนถัดไป">›</button>
                </header>
                <div class="day-labels">
                    ${dayNames.map(day => `<span>${day}</span>`).join('')}
                </div>
                <div class="day-grid">
            `;

            for (let i = 0; i < firstDay; i += 1) {
                html += '<div class="day-cell disabled"></div>';
            }

            for (let day = 1; day <= daysInMonth; day += 1) {
                const dateObj = new Date(year, month, day);
                const isToday = dateObj.toDateString() === today.toDateString();
                const isSelected = selectedDate && dateObj.toDateString() === selectedDate.toDateString();
                const classes = ['day-cell'];
                if (isToday) classes.push('today');
                if (isSelected) classes.push('selected');

                html += `<button type="button" class="${classes.join(' ')}" data-calendar-day="${day}">${day}</button>`;
            }

            html += '</div>';
            popup.innerHTML = html;
        }

        function positionPopup() {
            const rect = displayField.getBoundingClientRect();
            popup.style.top = `${rect.bottom + window.scrollY + 10}px`;
            popup.style.left = `${rect.left + window.scrollX}px`;
        }

        function openPopup() {
            renderCalendar(hiddenField.value ? new Date(hiddenField.value) : new Date());
            positionPopup();
            popup.classList.remove('hidden');
        }

        function closePopup() {
            popup.classList.add('hidden');
        }

        displayField.addEventListener('click', (event) => {
            event.stopPropagation();
            openPopup();
        });

        popup.addEventListener('click', (event) => {
            event.stopPropagation();
            const button = event.target.closest('[data-calendar-nav]');
            if (button) {
                const direction = button.getAttribute('data-calendar-nav');
                currentMonth.setMonth(currentMonth.getMonth() + (direction === 'next' ? 1 : -1));
                renderCalendar(currentMonth);
                return;
            }

            const dayButton = event.target.closest('[data-calendar-day]');
            if (dayButton) {
                const day = Number(dayButton.getAttribute('data-calendar-day'));
                const selected = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), day);
                hiddenField.value = formatIso(selected);
                hiddenField.dispatchEvent(new Event('change', { bubbles: true }));
                displayField.value = formatDisplay(selected);
                closePopup();
            }
        });

        document.addEventListener('click', () => {
            if (!popup.classList.contains('hidden')) {
                closePopup();
            }
        });

        window.addEventListener('resize', () => {
            if (!popup.classList.contains('hidden')) positionPopup();
        });

        monthField.addEventListener('change', () => {
            hiddenField.value = '';
            displayField.value = '';
        });

        hiddenField.addEventListener('change', () => {
            monthField.value = '';
        });
    });
</script>
<script src="assets/js/common.js"></script>
<script src="assets/js/system_history.js"></script>