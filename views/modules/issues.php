<?php
// views/modules/issues.php
if (!hasRole(['admin', 'super_admin'])) {
    echo "<div class='p-6 text-center text-red-500 font-bold'>Unauthorized Access</div>";
    exit;
}
?>

<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-6 h-6 text-rose-500"></i>
                รายงานปัญหา
            </h1>
            <p class="text-sm text-slate-500 mt-1">จัดการและตรวจสอบปัญหาการใช้งานที่ผู้ใช้รายงานเข้ามา</p>
        </div>
        <button id="refreshIssuesBtn" class="btn-primary shrink-0 self-start sm:self-auto bg-white !text-slate-700 border border-slate-300 hover:bg-slate-50 shadow-sm">
            <i data-lucide="refresh-cw" class="w-4 h-4"></i> รีเฟรชข้อมูล
        </button>
    </div>

    <!-- Status Tabs -->
    <div class="flex gap-2 p-1 bg-slate-100 rounded-xl w-fit">
        <button class="issue-tab active px-4 py-2 text-sm font-bold rounded-lg bg-white text-rose-600 shadow-sm transition-all" data-status="pending">
            รอดำเนินการ <span id="badgePending" class="ml-1 bg-rose-100 text-rose-600 py-0.5 px-2 rounded-full text-xs">0</span>
        </button>
        <button class="issue-tab px-4 py-2 text-sm font-bold rounded-lg text-slate-500 hover:text-slate-700 transition-all" data-status="resolved">
            แก้ไขแล้ว <span id="badgeResolved" class="ml-1 bg-slate-200 text-slate-600 py-0.5 px-2 rounded-full text-xs">0</span>
        </button>
    </div>

    <div id="issuesLoading" class="flex flex-col items-center justify-center py-12 text-slate-400 gap-3">
        <div class="w-8 h-8 border-2 border-slate-200 border-t-rose-500 rounded-full animate-spin"></div>
        <p class="text-sm">กำลังโหลดข้อมูล...</p>
    </div>

    <div id="issuesEmpty" class="hidden flex-col items-center justify-center py-16 text-slate-400 gap-3 bg-white rounded-2xl border border-slate-200">
        <i data-lucide="check-circle-2" class="w-12 h-12 text-emerald-400"></i>
        <p class="text-sm font-medium">ไม่มีรายการปัญหาในสถานะนี้</p>
    </div>

    <div id="issuesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 hidden">
        <!-- Cards will be injected here -->
    </div>
</div>

<!-- Image Viewer Modal -->
<div id="imageViewerModal" class="fixed inset-0 z-[110] bg-black/90 hidden items-center justify-center p-4">
    <button id="closeImageViewer" class="absolute top-6 right-6 text-white/50 hover:text-white transition-colors">
        <i data-lucide="x" class="w-8 h-8"></i>
    </button>
    <img id="imageViewerImg" class="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl" src="" alt="View" />
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let allIssues = [];
    let currentStatus = 'pending';
    
    const issuesGrid = document.getElementById('issuesGrid');
    const loadingState = document.getElementById('issuesLoading');
    const emptyState = document.getElementById('issuesEmpty');
    const badgePending = document.getElementById('badgePending');
    const badgeResolved = document.getElementById('badgeResolved');
    
    // Image Viewer
    const viewerModal = document.getElementById('imageViewerModal');
    const viewerImg = document.getElementById('imageViewerImg');
    const closeViewer = document.getElementById('closeImageViewer');
    
    function openViewer(src) {
        viewerImg.src = src;
        viewerModal.classList.remove('hidden');
        viewerModal.classList.add('flex');
    }
    
    closeViewer.addEventListener('click', () => viewerModal.classList.add('hidden'));
    viewerModal.addEventListener('click', (e) => {
        if(e.target === viewerModal) viewerModal.classList.add('hidden');
    });
    
    async function fetchIssues() {
        loadingState.classList.remove('hidden');
        loadingState.classList.add('flex');
        issuesGrid.classList.add('hidden');
        emptyState.classList.add('hidden');
        
        try {
            const res = await fetch('api/issues/list.php');
            const data = await res.json();
            if (data.success) {
                allIssues = data.data;
                renderIssues();
            } else {
                alert('โหลดข้อมูลล้มเหลว: ' + data.message);
            }
        } catch(e) {
            console.error(e);
            alert('โหลดข้อมูลล้มเหลว ตรวจสอบการเชื่อมต่อ');
        }
    }
    
    function renderIssues() {
        // Update badges
        const pendingCount = allIssues.filter(i => i.status === 'pending').length;
        const resolvedCount = allIssues.filter(i => i.status === 'resolved').length;
        badgePending.textContent = pendingCount;
        badgeResolved.textContent = resolvedCount;
        
        const filtered = allIssues.filter(i => i.status === currentStatus);
        
        loadingState.classList.remove('flex');
        loadingState.classList.add('hidden');
        
        if (filtered.length === 0) {
            emptyState.classList.remove('hidden');
            emptyState.classList.add('flex');
            issuesGrid.classList.add('hidden');
            return;
        }
        
        emptyState.classList.add('hidden');
        emptyState.classList.remove('flex');
        issuesGrid.classList.remove('hidden');
        
        issuesGrid.innerHTML = '';
        
        filtered.forEach(issue => {
            const isPending = issue.status === 'pending';
            const dateObj = new Date(issue.created_at);
            const dateStr = dateObj.toLocaleString('th-TH', { dateStyle: 'medium', timeStyle: 'short' });
            
            const initials = issue.full_name ? issue.full_name.substring(0,2).toUpperCase() : 'U';
            const roleStr = issue.role ? issue.role.toUpperCase() : 'USER';
            
            let imgHtml = '';
            if (issue.image_url) {
                imgHtml = `
                    <div class="mt-4 rounded-xl overflow-hidden border border-slate-200 cursor-pointer hover:opacity-90 transition-opacity" onclick="window.openImageViewer('${issue.image_url}')">
                        <img src="${issue.image_url}" class="w-full h-40 object-cover" alt="Issue Image">
                    </div>
                `;
            }
            
            const card = document.createElement('div');
            card.className = `bg-white rounded-2xl border ${isPending ? 'border-rose-200 shadow-sm shadow-rose-100/50' : 'border-slate-200 shadow-sm'} p-5 flex flex-col`;
            card.innerHTML = `
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full ${isPending ? 'bg-rose-100 text-rose-600' : 'bg-slate-100 text-slate-600'} flex items-center justify-center font-bold text-sm shrink-0">
                            ${initials}
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-900 text-sm leading-tight">${issue.full_name || 'Unknown'}</h4>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-500 tracking-wider uppercase">${roleStr}</span>
                                <span class="text-[10px] text-slate-400 flex items-center gap-1"><i data-lucide="clock" class="w-3 h-3"></i> ${dateStr}</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex-1">
                    <p class="text-sm text-slate-700 whitespace-pre-line leading-relaxed">${issue.message ? escapeHtml(issue.message) : '<span class="italic text-slate-400">ไม่มีข้อความ</span>'}</p>
                    ${imgHtml}
                </div>
                
                <div class="mt-5 pt-4 border-t border-slate-100 flex gap-2">
                    ${isPending ? `
                        <button class="flex-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-600 text-sm font-bold py-2 rounded-xl transition-colors border border-emerald-200" onclick="window.updateIssueStatus(${issue.id}, 'resolved')">
                            เครื่องหมายว่าแก้ไขแล้ว
                        </button>
                    ` : `
                        <button class="flex-1 bg-slate-50 hover:bg-slate-100 text-slate-600 text-sm font-bold py-2 rounded-xl transition-colors border border-slate-200" onclick="window.updateIssueStatus(${issue.id}, 'pending')">
                            เปลี่ยนเป็นรอดำเนินการ
                        </button>
                    `}
                </div>
            `;
            issuesGrid.appendChild(card);
        });
        lucide.createIcons();
    }
    
    function escapeHtml(unsafe) {
        return unsafe
             .replace(/&/g, "&amp;")
             .replace(/</g, "&lt;")
             .replace(/>/g, "&gt;")
             .replace(/"/g, "&quot;")
             .replace(/'/g, "&#039;");
    }
    
    // Expose to window for inline onclick
    window.openImageViewer = openViewer;
    window.updateIssueStatus = async function(id, status) {
        if(!confirm(`ต้องการเปลี่ยนสถานะเป็น "${status === 'resolved' ? 'แก้ไขแล้ว' : 'รอดำเนินการ'}" ใช่หรือไม่?`)) return;
        
        try {
            const fd = new FormData();
            fd.append('id', id);
            fd.append('status', status);
            
            const res = await fetch('api/issues/update_status.php', { method: 'POST', body: fd });
            const data = await res.json();
            if(data.success) {
                // Update local array and re-render
                const idx = allIssues.findIndex(i => i.id == id);
                if(idx > -1) {
                    allIssues[idx].status = status;
                    renderIssues();
                }
            } else {
                alert(data.message);
            }
        } catch(e) {
            alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
        }
    };
    
    // Tabs
    document.querySelectorAll('.issue-tab').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.issue-tab').forEach(b => {
                b.classList.remove('active', 'bg-white', 'text-rose-600', 'shadow-sm');
                b.classList.add('text-slate-500');
            });
            this.classList.add('active', 'bg-white', 'text-rose-600', 'shadow-sm');
            this.classList.remove('text-slate-500');
            
            currentStatus = this.dataset.status;
            renderIssues();
        });
    });
    
    document.getElementById('refreshIssuesBtn').addEventListener('click', fetchIssues);
    
    // Initial fetch
    fetchIssues();
});
</script>
