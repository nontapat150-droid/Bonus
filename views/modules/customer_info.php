<?php
// views/modules/customer_info.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');
requireRole(['admin', 'super_admin']);
?>

<div class="max-w-7xl mx-auto space-y-6 animate__animated animate__fadeIn">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-slate-800 tracking-tight flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center shadow-inner">
                    <i data-lucide="users-2" class="w-6 h-6"></i>
                </div>
                ข้อมูลลูกค้า (Customer Information)
            </h1>
            <p class="text-slate-500 mt-2 text-sm font-medium">ค้นหาประวัติการทำงานทั้งหมดของลูกค้าจากหมายเลข NON, Circuit ID หรือ Access No</p>
        </div>
    </div>

    <!-- Search Box -->
    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex gap-2">
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                    <i data-lucide="search" class="w-5 h-5 text-slate-400"></i>
                </div>
                <input type="text" id="ciSearchInput" class="input !pl-11 !py-4 w-full text-lg font-bold" placeholder="กรอกหมายเลข NON, Circuit ID, หรือ Access No...">
            </div>
            <button type="button" onclick="ciDoSearch()" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-8 rounded-xl shadow-btn transition-all shrink-0 flex items-center gap-2">
                <i data-lucide="search" class="w-5 h-5"></i> ค้นหา
            </button>
            <button type="button" onclick="ciShowAll()" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold px-6 rounded-xl transition-all shrink-0 flex items-center gap-2" title="แสดงข้อมูลลูกค้าทั้งหมดล่าสุด">
                <i data-lucide="list" class="w-5 h-5"></i> แสดงทั้งหมด
            </button>
        </div>
    </div>

    <!-- Status Banner -->
    <div id="ciStatusBanner" class="hidden items-center gap-3 px-5 py-3.5 rounded-2xl border text-sm font-bold shadow-sm transition-all">
        <i id="ciStatusIcon" data-lucide="info" class="w-5 h-5 shrink-0"></i>
        <span id="ciStatusText"></span>
    </div>

    <!-- Results Area -->
    <div id="ciResultsArea" class="hidden space-y-6">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-black text-slate-800">ผลการค้นหา</h2>
            <span id="ciResultCount" class="text-xs font-bold text-slate-500 bg-slate-100 px-3 py-1 rounded-full">พบ 0 รายการ</span>
        </div>
        <div id="ciCustomerCards" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"></div>
    </div>
    
    <!-- Empty State -->
    <div id="ciEmptyState" class="bg-white rounded-2xl border border-slate-200 p-12 text-center shadow-sm">
        <div class="w-24 h-24 bg-indigo-50 text-indigo-200 rounded-full flex items-center justify-center mx-auto mb-6">
            <i data-lucide="user-search" class="w-12 h-12"></i>
        </div>
        <h3 class="text-xl font-black text-slate-800 mb-2">ค้นหาประวัติลูกค้า</h3>
        <p class="text-slate-500 font-medium max-w-md mx-auto">กรอกหมายเลข NON หรือ Circuit ID ด้านบน เพื่อดูประวัติค่าแรกเข้า ประวัติการติดตั้ง และสถานะต่างๆ ของลูกค้า</p>
    </div>
</div>

<!-- Modal: Customer Detail -->
<div id="ciDetailModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-3xl max-h-[90vh] flex flex-col shadow-2xl">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50 rounded-t-2xl shrink-0">
            <h3 class="text-lg font-black text-slate-800 flex items-center gap-2">
                <i data-lucide="user" class="w-5 h-5 text-indigo-600"></i>
                รายละเอียดลูกค้า
            </h3>
            <div class="flex items-center gap-2">
                <button type="button" id="ciDeleteBtn" class="p-2 hover:bg-rose-100 text-rose-500 rounded-lg transition-colors hidden" title="ลบข้อมูลลูกค้านี้ทั้งหมด">
                    <i data-lucide="trash-2" class="w-5 h-5"></i>
                </button>
                <button type="button" onclick="ciCloseModal()" class="p-2 hover:bg-slate-200 text-slate-500 rounded-lg transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
        </div>
        <div class="p-6 overflow-y-auto flex-1 custom-scrollbar space-y-6" id="ciModalContent"></div>
        <div class="p-5 border-t border-slate-100 bg-slate-50 rounded-b-2xl shrink-0 flex justify-end">
            <button type="button" onclick="ciCloseModal()" class="px-5 py-2.5 text-sm font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-100 rounded-xl transition-colors">
                ปิดหน้าต่าง
            </button>
        </div>
    </div>
</div>

<script>
// ===== Customer Info Module — Inline Script =====
// ใช้ inline โดยตรงแทน external file เพื่อหลีกเลี่ยงปัญหา timing

(function() {
    'use strict';

    var ciCurrentData = [];

    // ---- Status Banner ----
    function ciSetStatus(type, msg) {
        var banner = document.getElementById('ciStatusBanner');
        var icon   = document.getElementById('ciStatusIcon');
        var text   = document.getElementById('ciStatusText');
        if (!banner || !icon || !text) return;

        // Reset classes
        banner.className = 'flex items-center gap-3 px-5 py-3.5 rounded-2xl border text-sm font-bold shadow-sm transition-all';

        if (type === 'success') {
            banner.classList.add('bg-emerald-50', 'border-emerald-200', 'text-emerald-800');
            icon.setAttribute('data-lucide', 'check-circle-2');
        } else if (type === 'warning') {
            banner.classList.add('bg-amber-50', 'border-amber-200', 'text-amber-800');
            icon.setAttribute('data-lucide', 'search-x');
        } else {
            banner.classList.add('bg-rose-50', 'border-rose-200', 'text-rose-800');
            icon.setAttribute('data-lucide', 'alert-circle');
        }

        text.textContent = msg;
        if (window.lucide) lucide.createIcons();
    }

    // ---- รองรับ Enter key บน input ----
    var inp = document.getElementById('ciSearchInput');
    if (inp) {
        inp.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                ciDoSearch();
            }
        });
    }

    // ---- Search ----
    window.ciDoSearch = function() {
        var input = document.getElementById('ciSearchInput');
        if (!input) return;
        var q = input.value.trim();
        if (!q) {
            alert('กรุณากรอกหมายเลขที่ต้องการค้นหา');
            return;
        }
        ciFetch('api/customer/search_info.php?q=' + encodeURIComponent(q));
    };

    // ---- Show All ----
    window.ciShowAll = function() {
        var input = document.getElementById('ciSearchInput');
        if (input) input.value = '';
        ciFetch('api/customer/search_info.php?all=1');
    };

    // ---- Fetch ----
    function ciFetch(url) {
        var loader = document.createElement('div');
        loader.id = 'ciLoader';
        loader.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.3);z-index:9999;display:flex;align-items:center;justify-content:center;';
        loader.innerHTML = '<div style="background:white;padding:24px 32px;border-radius:16px;font-weight:800;font-size:14px;">กำลังโหลด...</div>';
        document.body.appendChild(loader);

        fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(res) {
            if (!res.ok) {
                return res.text().then(function(t) {
                    throw new Error('HTTP ' + res.status + ' — ' + t.substring(0, 200));
                });
            }
            return res.json();
        })
        .then(function(data) {
            if (data.success) {
                ciCurrentData = data.data;
                ciRender(ciCurrentData);
            } else {
                ciSetStatus('error', data.error || 'ไม่สามารถค้นหาข้อมูลได้');
            }
        })
        .catch(function(err) {
            ciSetStatus('error', 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้: ' + err.message);
        })
        .finally(function() {
            var l = document.getElementById('ciLoader');
            if (l) l.remove();
        });
    }

    // ---- Render Cards ----
    function ciRender(customers) {
        var resultsArea = document.getElementById('ciResultsArea');
        var emptyState  = document.getElementById('ciEmptyState');
        var cards       = document.getElementById('ciCustomerCards');
        var count       = document.getElementById('ciResultCount');
        var inp         = document.getElementById('ciSearchInput');
        var q           = inp ? inp.value.trim() : '';

        if (customers.length === 0) {
            var notFoundMsg = q
                ? 'ไม่พบข้อมูลลูกค้าสำหรับ “' + q + '” กรุณาตรวจสอบหมายเลขอีกครั้ง'
                : 'ไม่พบข้อมูลในระบบ';
            ciSetStatus('warning', notFoundMsg);
            if (emptyState) emptyState.classList.remove('hidden');
            if (resultsArea) resultsArea.classList.add('hidden');
            return;
        }

        var successMsg = q
            ? 'ค้นหา “' + q + '” เจอทั้งหมด ' + customers.length + ' รายการ'
            : 'แสดงข้อมูลลูกค้าทั้งหมด ' + customers.length + ' รายการ';
        ciSetStatus('success', successMsg);

        if (emptyState) emptyState.classList.add('hidden');
        if (resultsArea) resultsArea.classList.remove('hidden');
        if (count) count.textContent = 'พบ ' + customers.length + ' รายการ';
        if (!cards) return;

        cards.innerHTML = '';
        customers.forEach(function(c, idx) {
            var jobsCount   = (c.jobs || []).length;
            var maCount     = (c.ma_jobs || []).length;
            var hasStart    = (c.start_days || []).length > 0;

            var latestStatus = '';
            if (jobsCount > 0) {
                var s = c.jobs[0].status;
                if (s === 'completed') latestStatus = '<span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded text-[10px] font-bold">ติดตั้งสำเร็จ</span>';
                else if (s === 'failed') latestStatus = '<span class="px-2 py-0.5 bg-rose-100 text-rose-700 rounded text-[10px] font-bold">ไม่สำเร็จ</span>';
                else latestStatus = '<span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-[10px] font-bold">รอดำเนินการ</span>';
            } else if (maCount > 0) {
                var ms = c.ma_jobs[0].status;
                if (ms === 'completed') latestStatus = '<span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded text-[10px] font-bold">MA เสร็จแล้ว</span>';
                else latestStatus = '<span class="px-2 py-0.5 bg-violet-100 text-violet-700 rounded text-[10px] font-bold">งาน MA</span>';
            }

            var card = document.createElement('div');
            card.className = 'bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all cursor-pointer group relative';
            card.onclick = function() { ciOpenDetail(idx); };
            
            const role = window.USER_ROLE;
            const isAdmin = (role === 'admin' || role === 'super_admin');
            
            var delBtnHTML = isAdmin ? '<button type="button" onclick="event.stopPropagation(); ciDeleteCustomer(\'' + ciEscape(c.id) + '\')" class="absolute top-3 right-3 p-2 bg-white hover:bg-rose-100 text-slate-300 hover:text-rose-500 rounded-lg transition-colors opacity-0 group-hover:opacity-100 shadow-sm z-10" title="ลบข้อมูลลูกค้านี้ทั้งหมด"><i data-lucide="trash-2" class="w-4 h-4"></i></button>' : '';
            
            card.innerHTML = delBtnHTML + '<div class="flex justify-between items-start mb-4">'
                + '<div class="flex items-center gap-3">'
                + '<div class="w-10 h-10 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600">'
                + '<i data-lucide="user" class="w-5 h-5"></i></div>'
                + '<div><p class="text-[10px] font-bold text-slate-400 uppercase">NON / Circuit ID</p>'
                + '<p class="font-black text-indigo-600 text-lg pr-6">' + ciEscape(c.id) + '</p></div>'
                + '</div>' + latestStatus + '</div>'
                + '<div class="space-y-1 mb-4">'
                + '<p class="text-[10px] font-bold text-slate-400 uppercase">ชื่อลูกค้า</p>'
                + '<p class="text-sm font-bold text-slate-700 truncate">' + ciEscape(c.customer_name || 'ไม่ระบุ') + '</p>'
                + '<p class="text-xs text-slate-500">' + ciEscape(c.phone || '') + '</p>'
                + '</div>'
                + '<div class="flex gap-2 pt-4 border-t border-slate-100">'
                + '<div class="flex-1 text-center bg-slate-50 p-2 rounded-lg">'
                + '<p class="text-[10px] font-bold text-slate-400">ติดตั้ง</p>'
                + '<p class="font-black text-slate-700">' + jobsCount + '</p></div>'
                + '<div class="flex-1 text-center bg-slate-50 p-2 rounded-lg">'
                + '<p class="text-[10px] font-bold text-slate-400">งาน MA</p>'
                + '<p class="font-black ' + (maCount > 0 ? 'text-violet-600' : 'text-slate-400') + '">' + maCount + '</p></div>'
                + '<div class="flex-1 text-center bg-slate-50 p-2 rounded-lg">'
                + '<p class="text-[10px] font-bold text-slate-400">แรกเข้า</p>'
                + '<p class="font-black ' + (hasStart ? 'text-emerald-600' : 'text-slate-400') + '">' + (hasStart ? 'มี' : '-') + '</p></div>'
                + '</div>';
            cards.appendChild(card);
        });

        if (window.lucide) lucide.createIcons();
    }

    // ---- Open Detail Modal ----
    window.ciOpenDetail = function(idx) {
        var c = ciCurrentData[idx];
        if (!c) return;

        var html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">'
            + '<div class="bg-indigo-50 p-4 rounded-xl border border-indigo-100"><p class="text-[10px] font-bold text-indigo-400 uppercase mb-1">NON / ID</p><p class="text-xl font-black text-indigo-700">' + ciEscape(c.id) + '</p></div>'
            + '<div class="bg-slate-50 p-4 rounded-xl border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase mb-1">ชื่อลูกค้า</p><p class="text-lg font-black text-slate-800">' + ciEscape(c.customer_name || '-') + '</p></div>'
            + '<div class="bg-slate-50 p-4 rounded-xl border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase mb-1">เบอร์โทร</p><p class="text-sm font-bold text-slate-700">' + ciEscape(c.phone || '-') + '</p></div>'
            + '<div class="bg-slate-50 p-4 rounded-xl border border-slate-100"><p class="text-[10px] font-bold text-slate-400 uppercase mb-1">ที่อยู่</p><p class="text-sm font-bold text-slate-700">' + ciEscape(c.address || '-') + '</p></div>'
            + '</div>';

        // MA Jobs
        html += '<div><h4 class="text-sm font-black text-slate-800 mb-3 flex items-center gap-2"><i data-lucide="wrench" class="w-4 h-4 text-violet-500"></i> ประวัติงาน MA</h4>';
        if (c.ma_jobs && c.ma_jobs.length > 0) {
            c.ma_jobs.forEach(function(j) {
                var statusLabel = j.status === 'completed' ? '<span class="px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded text-[10px] font-bold">เสร็จแล้ว</span>'
                    : j.status === 'failed' ? '<span class="px-2 py-0.5 bg-rose-100 text-rose-700 rounded text-[10px] font-bold">ไม่สำเร็จ</span>'
                    : '<span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-[10px] font-bold">รอดำเนินการ</span>';

                html += '<div class="bg-white border border-slate-200 p-4 rounded-xl mb-3 shadow-sm">'
                    + '<div class="flex justify-between items-start mb-2"><div>'
                    + '<p class="text-[10px] font-bold text-slate-400">วันที่นัด / ช่าง</p>'
                    + '<p class="text-sm font-bold text-slate-800">' + ciEscape(j.plan_arrival_date || '-') + ' • ' + ciEscape(j.tech_name || j.team_name || 'รอจ่าย') + '</p>'
                    + '</div>' + statusLabel + '</div>'
                    + '<div class="grid grid-cols-2 gap-2 text-[11px] mb-2">'
                    + '<div class="bg-slate-50 p-2 rounded"><b>อาการ:</b> ' + ciEscape(j.symptoms || '-') + '</div>'
                    + '<div class="bg-slate-50 p-2 rounded"><b>โครงข่าย:</b> ' + ciEscape(j.area_provider || '-') + '</div>'
                    + '</div>';

                if (j.status === 'completed') {
                    html += '<div class="bg-emerald-50 border border-emerald-100 p-3 rounded-lg mb-2 text-[11px]">'
                        + '<p class="font-bold text-emerald-700 mb-1">ข้อมูลการปิดงาน</p>'
                        + '<div class="grid grid-cols-2 gap-1"><span><b>Signal After:</b> ' + ciEscape(j.signal_after || '-') + '</span>'
                        + '<span><b>Power RX:</b> ' + ciEscape(j.power_rx || '-') + '</span></div>'
                        + '<p class="mt-1"><b>สาเหตุ:</b> ' + ciEscape(j.problem_cause || '-') + '</p>'
                        + '<p><b>วิธีแก้:</b> ' + ciEscape(j.solution || '-') + '</p>'
                        + '<p><b>หมายเหตุ:</b> ' + ciEscape(j.remark || '-') + '</p>'
                        + '</div>';
                }

                if (j.images && j.images.length > 0) {
                    html += '<div class="flex gap-2 overflow-x-auto py-2">';
                    j.images.forEach(function(img) {
                        var src = img.indexOf('/') !== -1 ? img : 'assets/uploads/ma_jobs/' + img;
                        html += '<img src="' + src + '" class="h-16 rounded cursor-pointer border border-slate-200 hover:opacity-80" onclick="window.open(\'' + src + '\',\'_blank\')">';
                    });
                    html += '</div>';
                }

                html += '</div>';
            });
        } else {
            html += '<div class="text-xs text-slate-500 bg-slate-50 p-4 rounded-xl text-center border border-slate-100 font-bold">ไม่พบประวัติงาน MA</div>';
        }
        html += '</div>';

        // Start Days
        html += '<div><h4 class="text-sm font-black text-slate-800 mb-3 flex items-center gap-2"><i data-lucide="gauge" class="w-4 h-4 text-emerald-500"></i> ประวัติค่าแรกเข้า</h4>';
        if (c.start_days && c.start_days.length > 0) {
            c.start_days.forEach(function(sd) {
                html += '<div class="bg-white border border-slate-200 p-4 rounded-xl mb-3 shadow-sm">'
                    + '<div class="flex flex-wrap justify-between gap-2 mb-2">'
                    + '<div><p class="text-[10px] font-bold text-slate-400">วันที่บันทึก</p><p class="text-xs font-bold">' + ciEscape(sd.created_at) + '</p></div>'
                    + '<div><p class="text-[10px] font-bold text-slate-400">ช่างผู้บันทึก</p><p class="text-xs font-bold">' + ciEscape(sd.tech_name || '-') + '</p></div>'
                    + '</div>';
                if (sd.images && sd.images.length > 0) {
                    html += '<div class="flex gap-2 overflow-x-auto py-2">';
                    sd.images.forEach(function(img) {
                        var src = img.indexOf('/') !== -1 ? img : 'assets/uploads/start_day/' + img;
                        html += '<img src="' + src + '" class="h-16 rounded cursor-pointer border border-slate-200 hover:opacity-80" onclick="window.open(\'' + src + '\',\'_blank\')">';
                    });
                    html += '</div>';
                }
                html += '</div>';
            });
        } else {
            html += '<div class="text-xs text-slate-500 bg-slate-50 p-4 rounded-xl text-center border border-slate-100 font-bold">ไม่พบประวัติค่าแรกเข้า</div>';
        }
        html += '</div>';

        // Jobs (Office)
        html += '<div><h4 class="text-sm font-black text-slate-800 mb-3 flex items-center gap-2"><i data-lucide="map" class="w-4 h-4 text-amber-500"></i> ประวัติการติดตั้ง (Office)</h4>';
        if (c.jobs && c.jobs.length > 0) {
            c.jobs.forEach(function(j) {
                html += '<div class="bg-white border border-slate-200 p-4 rounded-xl mb-3 shadow-sm">'
                    + '<div class="flex justify-between items-start mb-2">'
                    + '<p class="text-sm font-bold text-slate-800">' + ciEscape(j.plan_arrival_date || '-') + ' • ' + ciEscape(j.team_name || 'รอจ่าย') + '</p></div>'
                    + '<div class="grid grid-cols-2 gap-2 text-[11px]">'
                    + '<div class="bg-slate-50 p-2 rounded"><b>แพ็กเกจ:</b> ' + ciEscape(j.package || '-') + '</div>'
                    + '<div class="bg-slate-50 p-2 rounded"><b>สินค้า:</b> ' + ciEscape(j.product || '-') + '</div>'
                    + '</div></div>';
            });
        } else {
            html += '<div class="text-xs text-slate-500 bg-slate-50 p-4 rounded-xl text-center border border-slate-100 font-bold">ไม่พบประวัติการติดตั้ง</div>';
        }
        html += '</div>';

        var mc = document.getElementById('ciModalContent');
        if (mc) mc.innerHTML = html;

        var modal = document.getElementById('ciDetailModal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        const role = window.USER_ROLE;
        const isAdmin = (role === 'admin' || role === 'super_admin');
        var delBtn = document.getElementById('ciDeleteBtn');
        if (delBtn && isAdmin) {
            delBtn.classList.remove('hidden');
            delBtn.onclick = function() { ciDeleteCustomer(c.id); };
        } else if (delBtn) {
            delBtn.classList.add('hidden');
        }

        if (window.lucide) lucide.createIcons();
    };

    // ---- Close Modal ----
    window.ciCloseModal = function() {
        var modal = document.getElementById('ciDetailModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    };

    // ---- Delete ----
    window.ciDeleteCustomer = function(customerId) {
        if (!confirm('ยืนยันลบข้อมูลลูกค้า ' + customerId + ' ทั้งหมด?')) return;
        fetch('api/customer/delete.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ id: customerId })
        }).then(function(r) { return r.json(); }).then(function(d) {
            if (d.success) {
                alert('ลบข้อมูลสำเร็จ');
                ciCloseModal();
                ciShowAll();
            } else {
                alert('ลบไม่สำเร็จ: ' + (d.error || 'ไม่ทราบสาเหตุ'));
            }
        }).catch(function(e) { alert('Error: ' + e.message); });
    };

    // ---- Escape HTML ----
    function ciEscape(str) {
        if (!str) return '';
        return String(str).replace(/[&<>"']/g, function(c) {
            return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
        });
    }

})();
</script>
