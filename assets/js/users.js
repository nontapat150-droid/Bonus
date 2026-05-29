// assets/js/users.js

let allUsers = [];
let allTeams = [];

document.addEventListener('DOMContentLoaded', () => {
    loadUsers();
    loadTeams();

    document.getElementById('searchUser')?.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        const filtered = allUsers.filter(u => 
            u.full_name.toLowerCase().includes(term) || 
            u.username.toLowerCase().includes(term)
        );
        renderUserTable(filtered);
    });

    document.getElementById('userForm')?.addEventListener('submit', handleSaveUser);
});

async function loadUsers() {
    try {
        const res = await fetch('api/users/get_users.php');
        const data = await res.json();

        if (data.success) {
            allUsers = data.data;
            renderUserTable(allUsers);
        } else {
            Toast.error(data.error);
        }
    } catch (e) {
        Toast.error('ไม่สามารถโหลดข้อมูลผู้ใช้ได้');
    }
}

async function loadTeams() {
    try {
        const res = await fetch('api/users/get_teams.php');
        const data = await res.json();
        if (data.success) {
            allTeams = data.data;
            populateTeamDropdown();
        }
    } catch (e) {
        console.error('ไม่สามารถโหลดรายการทีมได้', e);
    }
}

function populateTeamDropdown(selectedId = '') {
    const select = document.getElementById('team_id');
    if (!select) return;
    
    select.innerHTML = '<option value="">-- ไม่มีทีม --</option>';
    
    allTeams.forEach(team => {
        const option = document.createElement('option');
        option.value = team.id;
        option.textContent = `🚗 ${team.team_name}`;
        if (String(team.id) === String(selectedId)) {
            option.selected = true;
        }
        select.appendChild(option);
    });
}

function renderUserTable(users) {
    const tbody = document.getElementById('userTableBody');
    tbody.innerHTML = '';

    if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-8 py-10 text-center text-slate-400 italic">ไม่พบรายชื่อพนักงาน</td></tr>';
        return;
    }

    const roleBadges = {
        'super_admin': '<span class="px-3 py-1 bg-rose-50 text-rose-600 rounded-full font-bold text-[10px] border border-rose-100">SUPER ADMIN</span>',
        'admin': '<span class="px-3 py-1 bg-indigo-50 text-indigo-600 rounded-full font-bold text-[10px] border border-indigo-100">ADMIN</span>',
        'technician': '<span class="px-3 py-1 bg-slate-50 text-slate-500 rounded-full font-bold text-[10px] border border-slate-100">TECHNICIAN</span>',
        'sales': '<span class="px-3 py-1 bg-green-50 text-green-600 rounded-full font-bold text-[10px] border border-green-100">SALES</span>'
    };

    users.forEach((u, index) => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition-colors animate__animated animate__fadeIn';
        tr.style.animationDelay = `${index * 0.05}s`;
        
        const date = new Date(u.created_at).toLocaleDateString('th-TH');
        const teamBadge = u.team_name 
            ? `<span class="px-3 py-1 bg-amber-50 text-amber-600 rounded-full font-bold text-[10px] border border-amber-100">🚗 ${u.team_name}</span>` 
            : '<span class="text-slate-300 text-[10px] italic">ไม่มีทีม</span>';

        tr.innerHTML = `
            <td class="px-8 py-5">
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500 font-bold mr-3 text-xs">
                        ${u.full_name.charAt(0)}
                    </div>
                    <div>
                        <span class="font-bold text-slate-700">${u.full_name}</span>
                        <div class="mt-1">${teamBadge}</div>
                    </div>
                </div>
            </td>
            <td class="px-8 py-5 font-mono text-xs text-slate-400">@${u.username}</td>
            
            <td class="px-8 py-5">
                <span class="bg-slate-100 text-slate-500 px-2 py-1 rounded text-xs tracking-widest font-mono cursor-help" title="รหัสผ่านถูกเข้ารหัสทางเดียวเพื่อความปลอดภัย หากลืมสามารถกดแก้ไขเพื่อตั้งใหม่ได้">********</span>
            </td>

            <td class="px-8 py-5">${roleBadges[u.role] || u.role}</td>
            <td class="px-8 py-5 text-slate-400 text-xs">${date}</td>
            <td class="px-8 py-5 text-center">
                <div class="flex justify-center space-x-2">
                    <button onclick="editUser(${index})" class="p-2 text-indigo-500 hover:bg-indigo-50 rounded-xl transition-all" title="แก้ไข">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </button>
                    <button onclick="deleteUser(${u.id})" class="p-2 text-rose-400 hover:bg-rose-50 rounded-xl transition-all" title="ลบ">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h14"></path></svg>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function openUserModal(isEdit = false) {
    const modal = document.getElementById('userModal');
    const title = document.getElementById('modalTitle');
    const form = document.getElementById('userForm');
    const help = document.getElementById('passwordHelp');

    form.reset();
    document.getElementById('userId').value = '';
    
    if (isEdit) {
        title.innerText = 'แก้ไขข้อมูลพนักงาน';
        help.classList.remove('hidden');
    } else {
        title.innerText = 'เพิ่มพนักงานใหม่';
        help.classList.add('hidden');
        populateTeamDropdown(''); 
    }

    modal.classList.remove('hidden');
    modal.querySelector('div').classList.add('animate__zoomIn');
}

function closeUserModal() {
    const modal = document.getElementById('userModal');
    modal.querySelector('div').classList.remove('animate__zoomIn');
    modal.querySelector('div').classList.add('animate__zoomOut');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.querySelector('div').classList.remove('animate__zoomOut');
    }, 300);
}

function editUser(index) {
    const u = allUsers[index];
    openUserModal(true);
    
    document.getElementById('userId').value = u.id;
    document.getElementById('full_name').value = u.full_name;
    document.getElementById('username_field').value = u.username;
    document.getElementById('role').value = u.role;
    
    if (u.allow_late_time) {
        document.getElementById('allow_late_time').value = u.allow_late_time.substring(0, 5);
    }
    
    toggleLateTimeField();
    populateTeamDropdown(u.team_id || '');
}

async function handleSaveUser(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const payload = Object.fromEntries(formData.entries());

    Loader.show();
    try {
        const res = await fetch('api/users/save_user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
            // แจ้งเตือนด้วย SweetAlert2 แทน Toast
            Swal.fire({
                title: 'สำเร็จ!',
                text: data.message,
                icon: 'success',
                confirmButtonColor: '#4f46e5',
                customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }
            });
            closeUserModal();
            loadUsers();
        } else {
            Swal.fire('เกิดข้อผิดพลาด', data.error, 'error');
        }
    } catch (err) {
        Swal.fire('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
    } finally {
        Loader.hide();
    }
}

async function deleteUser(id) {
    // ถามยืนยันลบด้วย SweetAlert2
    const result = await Swal.fire({
        title: 'ยืนยันการลบพนักงาน?',
        text: "ข้อมูลของพนักงานคนนี้จะถูกลบออกจากระบบอย่างถาวร",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ใช่, ลบเลย',
        cancelButtonText: 'ยกเลิก',
        customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md', cancelButton: 'rounded-xl px-6 py-2.5 font-bold' }
    });

    if (!result.isConfirmed) return;

    Loader.show();
    try {
        const res = await fetch('api/users/delete_user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await res.json();

        if (data.success) {
            Swal.fire({
                title: 'ลบสำเร็จ!',
                text: data.message,
                icon: 'success',
                confirmButtonColor: '#4f46e5',
                customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }
            });
            loadUsers();
        } else {
            Swal.fire('เกิดข้อผิดพลาด', data.error, 'error');
        }
    } catch (err) {
        Swal.fire('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
    } finally {
        Loader.hide();
    }
}

function toggleLateTimeField() {
    const role = document.getElementById('role').value;
    const lateTimeField = document.getElementById('lateTimeField');
    
    if (role === 'sales' || role === 'technician') {
        lateTimeField.classList.remove('hidden');
    } else {
        lateTimeField.classList.add('hidden');
    }
}