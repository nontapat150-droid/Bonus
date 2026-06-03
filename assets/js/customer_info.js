// assets/js/customer_info.js

function initCustomerInfo() {
    const searchForm = document.getElementById('searchCustomerForm');
    const searchInput = document.getElementById('searchInput');
    const resultsArea = document.getElementById('resultsArea');
    const emptyState = document.getElementById('emptyState');
    const customerCards = document.getElementById('customerCards');
    const resultCount = document.getElementById('resultCount');
    const modal = document.getElementById('customerDetailModal');
    const modalContent = document.getElementById('modalContent');

    // ถ้าหา element หลักไม่เจอ แสดงว่าหน้านี้ยังไม่โหลด
    if (!searchForm || !searchInput) return;

    let currentData = [];

    // Close Modal Events
    document.querySelectorAll('.closeModalBtn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        });
    });

    const showAllBtn = document.getElementById('showAllBtn');
    if (showAllBtn) {
        showAllBtn.addEventListener('click', (e) => {
            e.preventDefault();
            searchInput.value = '';
            fetchCustomers('?all=1');
        });
    }

    const searchBtn = document.getElementById('searchBtn');
    if (searchBtn) {
        searchBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const query = searchInput.value.trim();
            if (!query) return;
            fetchCustomers(`?q=${encodeURIComponent(query)}`);
        });
    }

    // รองรับ Enter ในช่อง input ด้วย
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            e.stopPropagation();
            const query = searchInput.value.trim();
            if (!query) return;
            fetchCustomers(`?q=${encodeURIComponent(query)}`);
        }
    });

    // ป้องกัน form submit แบบ native ทุกกรณี
    searchForm.addEventListener('submit', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const query = searchInput.value.trim();
        if (query) fetchCustomers(`?q=${encodeURIComponent(query)}`);
        return false;
    });

    async function fetchCustomers(queryString) {
        if (window.Loader) window.Loader.show();
        try {
            const base = window.location.pathname.replace(/\/[^/]*$/, '/');
            const res = await fetch(`${base}api/customer/search_info.php${queryString}`);
            const data = await res.json();
            
            if (data.success) {
                currentData = data.data;
                renderResults(currentData);
            } else {
                Swal.fire('ข้อผิดพลาด', data.error || 'ไม่สามารถค้นหาข้อมูลได้', 'error');
            }
        } catch (error) {
            Swal.fire('ข้อผิดพลาด', 'เกิดปัญหาในการเชื่อมต่อเซิร์ฟเวอร์', 'error');
        } finally {
            if (window.Loader) window.Loader.hide();
        }
    }

    function renderResults(customers) {
        if (customers.length === 0) {
            Swal.fire('ไม่พบข้อมูล', 'ไม่พบประวัติลูกค้าจากหมายเลขที่คุณค้นหา กรุณาตรวจสอบหมายเลขอีกครั้ง', 'info');
            emptyState.classList.remove('hidden');
            resultsArea.classList.add('hidden');
            return;
        }

        emptyState.classList.add('hidden');
        resultsArea.classList.remove('hidden');
        resultCount.textContent = `พบ ${customers.length} รายการ`;
        customerCards.innerHTML = '';

        customers.forEach((customer, index) => {
            const hasStartDay = customer.start_days.length > 0;
            const jobsCount = customer.jobs.length;
            const maJobsCount = customer.ma_jobs ? customer.ma_jobs.length : 0;
            
            let statusBadge = '<span class="px-2 py-1 bg-slate-100 text-slate-600 rounded text-[10px] font-bold">ไม่มีงานในระบบ</span>';
            if (jobsCount > 0) {
                const latestJob = customer.jobs[0];
                if (latestJob.status === 'completed') {
                    statusBadge = '<span class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded text-[10px] font-bold">ติดตั้งสำเร็จ</span>';
                } else if (latestJob.status === 'failed') {
                    statusBadge = '<span class="px-2 py-1 bg-rose-100 text-rose-700 rounded text-[10px] font-bold">ติดตั้งไม่สำเร็จ</span>';
                } else {
                    statusBadge = '<span class="px-2 py-1 bg-amber-100 text-amber-700 rounded text-[10px] font-bold">รอดำเนินการ</span>';
                }
            }

            const card = document.createElement('div');
            card.className = "bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md hover:border-indigo-300 transition-all cursor-pointer group";
            card.onclick = () => openCustomerDetail(index);
            
            card.innerHTML = `
                <div class="flex justify-between items-start mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600 font-bold group-hover:scale-110 transition-transform">
                            <i data-lucide="user" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">ID / NON</p>
                            <p class="font-black text-indigo-600 text-lg group-hover:text-indigo-800 transition-colors">${displayValue(customer.id)}</p>
                        </div>
                    </div>
                    ${statusBadge}
                </div>
                <div class="space-y-2 mb-4">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase">ชื่อลูกค้า</p>
                        <p class="text-sm font-bold text-slate-700 truncate">${displayValue(customer.customer_name, 'ไม่ระบุชื่อ')}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase">แพ็กเกจ / สินค้า</p>
                        <p class="text-xs font-bold text-slate-600 truncate">${displayValue(customer.package)} ${customer.product ? `(${customer.product})` : ''}</p>
                    </div>
                </div>
                <div class="flex gap-2 pt-4 border-t border-slate-100">
                    <div class="flex-1 text-center bg-slate-50 p-2 rounded-lg">
                        <p class="text-[10px] font-bold text-slate-400">งานติดตั้ง</p>
                        <p class="font-black text-slate-700">${jobsCount}</p>
                    </div>
                    <div class="flex-1 text-center bg-slate-50 p-2 rounded-lg">
                        <p class="text-[10px] font-bold text-slate-400">งาน MA</p>
                        <p class="font-black ${maJobsCount > 0 ? 'text-violet-600' : 'text-slate-400'}">${maJobsCount}</p>
                    </div>
                    <div class="flex-1 text-center bg-slate-50 p-2 rounded-lg">
                        <p class="text-[10px] font-bold text-slate-400">ค่าแรกเข้า</p>
                        <p class="font-black ${hasStartDay ? 'text-emerald-600' : 'text-slate-400'}">${hasStartDay ? 'มีบันทึก' : '-'}</p>
                    </div>
                </div>
            `;
            customerCards.appendChild(card);
        });
        
        lucide.createIcons();
    }

    window.openCustomerDetail = function(index) {
        const customer = currentData[index];
        if (!customer) return;

        let html = `
            <!-- Header Info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-indigo-50 p-4 rounded-xl border border-indigo-100">
                    <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider mb-1">ID / NON</p>
                    <p class="text-xl font-black text-indigo-700">${displayValue(customer.id)}</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">ชื่อลูกค้า</p>
                    <p class="text-lg font-black text-slate-800">${displayValue(customer.customer_name, 'ไม่ระบุ')}</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">เบอร์โทรศัพท์</p>
                    <p class="text-sm font-bold text-slate-700">${displayValue(customer.phone)}</p>
                </div>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">ที่อยู่</p>
                    <p class="text-sm font-bold text-slate-700 leading-relaxed">${displayValue(customer.address)}</p>
                </div>
            </div>

            <!-- Start Day Records -->
            <div>
                <h4 class="text-sm font-black text-slate-800 mb-3 flex items-center gap-2">
                    <i data-lucide="gauge" class="w-4 h-4 text-emerald-500"></i> ประวัติค่าแรกเข้า
                </h4>
                ${customer.start_days.length > 0 ? customer.start_days.map(sd => `
                    <div class="bg-white border border-slate-200 p-4 rounded-xl mb-3 shadow-sm relative group">
                        <div class="absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="deleteSingleStartDay(${sd.id}, '${customer.id}')" class="p-1.5 bg-rose-50 hover:bg-rose-100 text-rose-500 rounded-lg transition-colors" title="ลบรายการนี้">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </div>
                        <div class="flex flex-wrap justify-between gap-2 mb-3 pr-8">
                            <div>
                                <p class="text-[10px] font-bold text-slate-400">วันที่บันทึก</p>
                                <p class="text-xs font-bold text-slate-700">${sd.created_at}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400">ช่างผู้บันทึก</p>
                                <p class="text-xs font-bold text-slate-700">${sd.tech_name || 'ไม่ทราบ'}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400">สถานะ</p>
                                ${getFeeBadge(sd.has_initial_fee)}
                            </div>
                        </div>
                        ${sd.images && sd.images.length > 0 ? `
                        <div class="flex gap-2 overflow-x-auto py-2 custom-scrollbar">
                            ${sd.images.map(img => {
                                const imgSrc = img.includes('/') ? img : `assets/uploads/start_day/${img}`;
                                return `<img src="${imgSrc}" class="h-16 rounded cursor-pointer border border-slate-200" onclick="window.open('${imgSrc}','_blank')">`;
                            }).join('')}
                        </div>
                        ` : ''}
                    </div>
                `).join('') : '<div class="text-xs text-slate-500 bg-slate-50 p-4 rounded-xl text-center border border-slate-100 font-bold">ไม่พบประวัติค่าแรกเข้า</div>'}
            </div>

            <!-- Installation Jobs -->
            <div>
                <h4 class="text-sm font-black text-slate-800 mb-3 flex items-center gap-2">
                    <i data-lucide="map" class="w-4 h-4 text-amber-500"></i> ประวัติการติดตั้ง (ระบบแจกจ่ายงาน)
                </h4>
                ${customer.jobs.length > 0 ? customer.jobs.map(job => `
                    <div class="bg-white border border-slate-200 p-4 rounded-xl mb-3 shadow-sm">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <p class="text-[10px] font-bold text-slate-400">วันที่นัดหมาย / ทีม</p>
                                <p class="text-sm font-bold text-slate-800">${job.plan_arrival_date} • ${job.team_name || 'รอจ่าย'}</p>
                            </div>
                            ${statusBadge(job.status)}
                        </div>
                        <div class="grid grid-cols-2 gap-2 mb-3">
                            <div class="bg-slate-50 p-2 rounded">
                                <p class="text-[9px] font-bold text-slate-400">แพ็กเกจ</p>
                                <p class="text-xs font-bold text-slate-700">${displayValue(job.package)}</p>
                            </div>
                            <div class="bg-slate-50 p-2 rounded">
                                <p class="text-[9px] font-bold text-slate-400">สินค้า</p>
                                <p class="text-xs font-bold text-slate-700">${displayValue(job.product)}</p>
                            </div>
                        </div>
                        
                        ${job.closes && job.closes.length > 0 ? `
                        <div class="bg-emerald-50 border border-emerald-100 p-3 rounded-lg mb-3">
                            <p class="text-[10px] font-bold text-emerald-600 mb-1"><i data-lucide="check-circle" class="w-3 h-3 inline"></i> ข้อมูลการปิดงาน</p>
                            ${job.closes.map(c => `
                                <div class="text-xs font-bold text-emerald-800 mb-1">
                                    โครงข่าย: ${c.install_provider || '-'} • ช่าง: ${c.tech_name || 'ไม่ระบุ'}
                                </div>
                                <div class="text-[10px] text-emerald-600">ระยะสาย: ${c.actual_cable_length || 0}m • วันที่: ${c.created_at}</div>
                            `).join('')}
                        </div>
                        ` : ''}

                        <!-- Action Logs Button -->
                        <button onclick="toggleLogs('logs_${job.id}')" class="w-full text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 py-2 rounded-lg transition-colors flex justify-center items-center gap-1">
                            <i data-lucide="list" class="w-3 h-3"></i> ดูประวัติการทำรายการ (Logs)
                        </button>
                        
                        <!-- Logs Container -->
                        <div id="logs_${job.id}" class="hidden mt-3 space-y-2 border-l-2 border-slate-200 pl-3">
                            ${job.logs && job.logs.length > 0 ? job.logs.map(log => `
                                <div class="text-[10px] bg-slate-50 p-2 rounded">
                                    <span class="font-bold text-indigo-600">${log.status}</span>
                                    <span class="text-slate-500 ml-1">โดย ${log.full_name || 'ระบบ'}</span>
                                    <div class="text-slate-400 mt-0.5">${log.timestamp}</div>
                                </div>
                            `).join('') : '<div class="text-[10px] text-slate-400">ไม่มีประวัติการทำรายการ</div>'}
                        </div>
                    </div>
                `).join('') : '<div class="text-xs text-slate-500 bg-slate-50 p-4 rounded-xl text-center border border-slate-100 font-bold">ไม่พบประวัติการติดตั้ง</div>'}
            </div>

            <!-- MA Jobs -->
            <div class="mt-4">
                <h4 class="text-sm font-black text-slate-800 mb-3 flex items-center gap-2">
                    <i data-lucide="wrench" class="w-4 h-4 text-violet-500"></i> ประวัติงาน MA (ซ่อมบำรุง)
                </h4>
                ${(customer.ma_jobs && customer.ma_jobs.length > 0) ? customer.ma_jobs.map(job => `
                    <div class="bg-white border border-slate-200 p-4 rounded-xl mb-3 shadow-sm">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <p class="text-[10px] font-bold text-slate-400">วันที่นัดหมาย / ทีม (ช่าง)</p>
                                <p class="text-sm font-bold text-slate-800">${job.plan_arrival_date || '-'} • ${job.team_name || job.tech_name || 'รอจ่าย'}</p>
                            </div>
                            ${statusBadge(job.status)}
                        </div>
                        <div class="grid grid-cols-2 gap-2 mb-3">
                            <div class="bg-slate-50 p-2 rounded">
                                <p class="text-[9px] font-bold text-slate-400">อาการ</p>
                                <p class="text-xs font-bold text-slate-700">${displayValue(job.symptoms)}</p>
                            </div>
                            <div class="bg-slate-50 p-2 rounded">
                                <p class="text-[9px] font-bold text-slate-400">โครงข่าย</p>
                                <p class="text-xs font-bold text-slate-700">${displayValue(job.area_provider)}</p>
                            </div>
                        </div>
                        
                        ${job.status === 'completed' ? `
                        <div class="bg-emerald-50 border border-emerald-100 p-3 rounded-lg mb-3">
                            <p class="text-[10px] font-bold text-emerald-600 mb-1"><i data-lucide="check-circle" class="w-3 h-3 inline"></i> ข้อมูลการปิดงาน</p>
                            <div class="text-[10px] text-emerald-700 grid grid-cols-2 gap-2 mt-2">
                                <div><b>Signal After:</b> ${displayValue(job.signal_after)}</div>
                                <div><b>Power RX:</b> ${displayValue(job.power_rx)}</div>
                            </div>
                            <div class="text-[10px] text-emerald-700 mt-2 space-y-1">
                                <div><b>สาเหตุ:</b> ${displayValue(job.problem_cause)}</div>
                                <div><b>วิธีแก้ไข:</b> ${displayValue(job.solution)}</div>
                                <div><b>หมายเหตุ:</b> ${displayValue(job.remark)}</div>
                            </div>
                        </div>
                        ` : ''}

                        ${job.images && job.images.length > 0 ? `
                        <div class="flex gap-2 overflow-x-auto py-2 custom-scrollbar">
                            ${job.images.map(img => {
                                const imgSrc = img.includes('/') ? img : 'assets/uploads/ma_jobs/' + img;
                                return '<img src="' + imgSrc + '" class="h-16 rounded cursor-pointer border border-slate-200" onclick="window.open(\\'' + imgSrc + '\\',\\'_blank\\')">';
                            }).join('')}
                        </div>
                        ` : ''}
                    </div>
                `).join('') : '<div class="text-xs text-slate-500 bg-slate-50 p-4 rounded-xl text-center border border-slate-100 font-bold">ไม่พบประวัติงาน MA</div>'}
            </div>
        `;

        modalContent.innerHTML = html;
        lucide.createIcons();
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Setup Delete Customer Button
        const deleteBtn = document.getElementById('deleteCustomerBtn');
        if (deleteBtn) {
            // Show only for admin/super_admin if role is available, assuming backend handles security
            deleteBtn.classList.remove('hidden');
            deleteBtn.onclick = () => deleteCustomer(customer.id);
        }
    }

    window.deleteCustomer = function(customerId) {
        Swal.fire({
            title: 'ยืนยันลบข้อมูลลูกค้า?',
            html: `คุณกำลังจะลบข้อมูลลูกค้าหมายเลข <b>${customerId}</b><br>ประวัติงานและค่าแรกเข้าที่ผูกกับลูกค้านี้จะถูกลบทิ้งทั้งหมด!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'ใช่, ลบถาวร',
            cancelButtonText: 'ยกเลิก',
            customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md', cancelButton: 'rounded-xl px-6 py-2.5 font-bold' }
        }).then(async (result) => {
            if (result.isConfirmed) {
                window.Loader.show();
                try {
                    const res = await fetch('api/customer/delete.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ id: customerId })
                    });
                    const data = await res.json();
                    if (data.success) {
                        Swal.fire('สำเร็จ', 'ลบข้อมูลลูกค้าเรียบร้อยแล้ว', 'success');
                        document.getElementById('customerDetailModal').classList.add('hidden');
                        document.getElementById('customerDetailModal').classList.remove('flex');
                        
                        refreshData();
                    } else {
                        Swal.fire('ข้อผิดพลาด', data.error || 'ไม่สามารถลบข้อมูลได้', 'error');
                    }
                } catch(e) {
                    Swal.fire('ข้อผิดพลาด', 'เกิดปัญหาเชื่อมต่อเซิร์ฟเวอร์', 'error');
                } finally {
                    window.Loader.hide();
                }
            }
        });
    }

    window.deleteSingleStartDay = function(id, customerId) {
        Swal.fire({
            title: 'ยืนยันลบประวัติค่าแรกเข้า?',
            text: "ข้อมูลนี้จะถูกลบและไม่สามารถกู้คืนได้",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'ลบข้อมูล',
            cancelButtonText: 'ยกเลิก'
        }).then(async (result) => {
            if (result.isConfirmed) {
                window.Loader.show();
                try {
                    const res = await fetch('api/start_day/delete.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ id: id })
                    });
                    const data = await res.json();
                    if (data.success) {
                        Swal.fire('สำเร็จ', 'ลบประวัติเรียบร้อยแล้ว', 'success');
                        refreshCustomerDetail(customerId);
                    } else {
                        Swal.fire('ข้อผิดพลาด', data.error || 'ไม่สามารถลบข้อมูลได้', 'error');
                    }
                } catch(e) {
                    Swal.fire('ข้อผิดพลาด', 'เกิดปัญหาเชื่อมต่อเซิร์ฟเวอร์', 'error');
                } finally {
                    window.Loader.hide();
                }
            }
        });
    }

    window.deleteSingleJob = function(id, customerId) {
        Swal.fire({
            title: 'ยืนยันลบงานติดตั้ง?',
            text: "ข้อมูลงานนี้จะถูกลบทิ้งทั้งหมด รวมถึงประวัติย่อย",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'ลบข้อมูล',
            cancelButtonText: 'ยกเลิก'
        }).then(async (result) => {
            if (result.isConfirmed) {
                window.Loader.show();
                try {
                    const res = await fetch('api/dispatch/bulk_delete.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ ids: [id] })
                    });
                    const data = await res.json();
                    if (data.success) {
                        Swal.fire('สำเร็จ', 'ลบงานเรียบร้อยแล้ว', 'success');
                        refreshCustomerDetail(customerId);
                    } else {
                        Swal.fire('ข้อผิดพลาด', data.error || 'ไม่สามารถลบข้อมูลได้', 'error');
                    }
                } catch(e) {
                    Swal.fire('ข้อผิดพลาด', 'เกิดปัญหาเชื่อมต่อเซิร์ฟเวอร์', 'error');
                } finally {
                    window.Loader.hide();
                }
            }
        });
    }

    function refreshData() {
        const query = searchInput.value.trim();
        if (query) {
            fetchCustomers(`?q=${encodeURIComponent(query)}`);
        } else {
            fetchCustomers('?all=1');
        }
    }

    function refreshCustomerDetail(customerId) {
        // Find current query to refresh data, then re-open modal for this customer
        const query = searchInput.value.trim();
        const url = query ? `?q=${encodeURIComponent(query)}` : '?all=1';
        
        fetch(`api/customer/search_info.php${url}`).then(r => r.json()).then(data => {
            if(data.success) {
                currentData = data.data;
                renderResults(currentData);
                // Re-open detail
                const idx = currentData.findIndex(c => c.id == customerId);
                if(idx !== -1) {
                    openCustomerDetail(idx);
                } else {
                    document.getElementById('customerDetailModal').classList.add('hidden');
                    document.getElementById('customerDetailModal').classList.remove('flex');
                }
            }
        });
    }

    window.toggleLogs = function(id) {
        const el = document.getElementById(id);
        if (el) {
            el.classList.toggle('hidden');
        }
    }

    function displayValue(val, fallback = '-') {
        return val ? escapeHTML(String(val)) : fallback;
    }

    function escapeHTML(str) {
        return str.replace(/[&<>'"]/g, 
            tag => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            }[tag] || tag)
        );
    }

    function getFeeBadge(status) {
        if (status == 1) return '<span class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded text-[10px] font-bold">มีค่าแรกเข้า</span>';
        if (status == 2) return '<span class="px-2 py-1 bg-amber-100 text-amber-700 rounded text-[10px] font-bold">จ่ายหน้างาน</span>';
        return '<span class="px-2 py-1 bg-rose-100 text-rose-700 rounded text-[10px] font-bold">ไม่มีค่าแรกเข้า</span>';
    }

    function statusBadge(status) {
        if (status === 'completed') return '<span class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded text-[10px] font-bold">ติดตั้งสำเร็จ</span>';
        if (status === 'failed') return '<span class="px-2 py-1 bg-rose-100 text-rose-700 rounded text-[10px] font-bold">ติดตั้งไม่สำเร็จ</span>';
        if (status === 'dispatched') return '<span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-[10px] font-bold">จ่ายงานแล้ว</span>';
        return '<span class="px-2 py-1 bg-amber-100 text-amber-700 rounded text-[10px] font-bold">รอดำเนินการ</span>';
    }
}

// Script โหลดหลัง DOM เสร็จแล้ว (อยู่ใน PHP include ท้าย body)
// จึงเรียกได้โดยตรง
initCustomerInfo();
