// assets/js/notifications.js
document.addEventListener('DOMContentLoaded', () => {
    const bellButton = document.getElementById('notificationBell');
    const notificationModal = document.getElementById('notificationModal');
    const closeNotificationModal = document.getElementById('closeNotificationModal');
    const notificationList = document.getElementById('notificationList');
    const notificationCount = document.getElementById('notificationCount');
    const unreadDot = document.getElementById('notificationUnreadDot');
    const openCreateBtn = document.getElementById('openNotificationCreate');
    const notificationCreateCard = document.getElementById('notificationCreateCard');
    const cancelNotificationCreate = document.getElementById('cancelNotificationCreate');
    const sendNotificationBtn = document.getElementById('sendNotificationBtn');
    
    // Form Elements
    const notificationTitle = document.getElementById('notificationTitle');
    const notificationMessage = document.getElementById('notificationMessage');
    const notificationType = document.getElementById('notificationType');
    const notificationTeamContainer = document.getElementById('notificationTeamContainer');
    const notificationUserContainer = document.getElementById('notificationUserContainer');
    const notificationTeam = document.getElementById('notificationTeam');
    const notificationUser = document.getElementById('notificationUser');
    
    const isAdmin = window.NOTIFICATIONS_CONFIG?.isAdmin === true;

    if (!bellButton || !notificationModal) return;

    loadNotifications(true);

    bellButton.addEventListener('click', async () => {
        notificationModal.classList.remove('hidden');
        await loadNotifications(false);
    });

    closeNotificationModal.addEventListener('click', () => {
        notificationModal.classList.add('hidden');
        if (notificationCreateCard) notificationCreateCard.classList.add('hidden');
    });

    // สลับโหมดการส่ง (ทุกคน / ทีม / บุคคล)
    if (notificationType) {
        notificationType.addEventListener('change', () => {
            if (notificationType.value === 'all') {
                notificationTeamContainer.classList.add('hidden');
                notificationUserContainer.classList.add('hidden');
            } else if (notificationType.value === 'team') {
                notificationTeamContainer.classList.remove('hidden');
                notificationUserContainer.classList.add('hidden');
            } else if (notificationType.value === 'user') {
                notificationTeamContainer.classList.add('hidden');
                notificationUserContainer.classList.remove('hidden');
            }
        });
    }

    if (openCreateBtn) {
        openCreateBtn.addEventListener('click', async () => {
            if (notificationCreateCard) {
                notificationCreateCard.classList.toggle('hidden');
                if (!notificationCreateCard.classList.contains('hidden')) {
                    await loadNotificationTeams();
                    await loadNotificationUsers();
                }
            }
        });
    }

    if (cancelNotificationCreate) {
        cancelNotificationCreate.addEventListener('click', () => {
            if (notificationCreateCard) notificationCreateCard.classList.add('hidden');
        });
    }

    if (sendNotificationBtn) {
        sendNotificationBtn.addEventListener('click', async () => {
            const title = notificationTitle.value.trim();
            const message = notificationMessage.value.trim();
            const type = notificationType.value;
            const teamId = notificationTeam.value;
            const targetUserId = notificationUser.value;

            if (!title || !message) return Toast.error('กรุณากรอกหัวเรื่องและข้อความ');
            if (type === 'team' && !teamId) return Toast.error('กรุณาเลือกทีมเป้าหมาย');
            if (type === 'user' && !targetUserId) return Toast.error('กรุณาเลือกพนักงานเป้าหมาย');

            sendNotificationBtn.disabled = true;
            try {
                const formData = new FormData();
                formData.append('title', title);
                formData.append('message', message);
                formData.append('type', type);
                formData.append('team_id', teamId);
                formData.append('target_user_id', targetUserId);

                const res = await fetch('api/notifications/save_notification.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if (data.success) {
                    Swal.fire({
                        title: 'สำเร็จ!',
                        text: 'ส่งแจ้งเตือนเรียบร้อยแล้ว',
                        icon: 'success',
                        confirmButtonColor: '#0ea5e9',
                        customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }
                    });
                    notificationTitle.value = '';
                    notificationMessage.value = '';
                    notificationType.value = 'all';
                    notificationType.dispatchEvent(new Event('change')); // Reset dropdowns
                    if (notificationCreateCard) notificationCreateCard.classList.add('hidden');
                    await loadNotifications(false);
                } else {
                    Toast.error(data.error || 'ส่งแจ้งเตือนล้มเหลว');
                }
            } catch (error) {
                Toast.error('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
            } finally {
                sendNotificationBtn.disabled = false;
            }
        });
    }

    async function loadNotificationTeams() {
        if (!isAdmin || !notificationTeam) return;
        try {
            const res = await fetch('api/notifications/get_teams.php');
            const data = await res.json();
            if (data.success) {
                notificationTeam.innerHTML = '<option value="">-- เลือกรถ/ทีม --</option>';
                data.data.forEach(team => {
                    const option = document.createElement('option');
                    option.value = team.id;
                    option.textContent = `🚗 ${team.team_name}`;
                    notificationTeam.appendChild(option);
                });
            }
        } catch (error) {}
    }

    async function loadNotificationUsers() {
        if (!isAdmin || !notificationUser) return;
        try {
            const res = await fetch('api/users/get_users.php');
            const data = await res.json();
            if (data.success) {
                notificationUser.innerHTML = '<option value="">-- เลือกพนักงาน --</option>';
                data.data.forEach(u => {
                    const option = document.createElement('option');
                    option.value = u.id;
                    option.textContent = `👤 ${u.full_name} (@${u.username})`;
                    notificationUser.appendChild(option);
                });
            }
        } catch (error) {}
    }

    async function loadNotifications(isBackground = false) {
        try {
            const res = await fetch('api/notifications/get_notifications.php');
            const data = await res.json();
            if (!data.success) return;

            notificationCount.textContent = data.unread_count || 0;
            unreadDot.classList.toggle('hidden', !(data.unread_count > 0));

            if (isBackground) return;

            notificationList.innerHTML = '';

            if (!data.notifications.length) {
                notificationList.innerHTML = '<div class="rounded-3xl bg-slate-50 border border-slate-200 p-4 text-slate-500 text-sm text-center">ยังไม่มีการแจ้งเตือน</div>';
                return;
            }

            data.notifications.forEach(notification => {
                const item = document.createElement('div');
                const isSmartAlert = typeof notification.id === 'string'; 
                
                // จัดสีป้ายกำกับเป้าหมาย
                let targetBadge = `<span class="text-sky-600 font-bold bg-sky-50 px-2 py-0.5 rounded-lg">${notification.target_name}</span>`;
                if(notification.target_name.includes('เฉพาะคุณ')) {
                    targetBadge = `<span class="text-rose-600 font-bold bg-rose-50 px-2 py-0.5 rounded-lg">🔒 เฉพาะคุณ</span>`;
                }

                item.className = `rounded-3xl border ${isSmartAlert ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-white'} p-4 shadow-sm cursor-pointer hover:border-indigo-400 transition-all`;
                item.innerHTML = `
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-bold text-base ${isSmartAlert ? 'text-amber-900' : 'text-slate-900'}">${escapeHtml(notification.title)}</div>
                            <div class="text-[11px] text-slate-500 mt-1.5 flex items-center gap-1 flex-wrap">
                                <span class="font-bold ${isSmartAlert ? 'text-amber-700' : 'text-indigo-600'}">ส่งจาก: ${escapeHtml(notification.creator_name)}</span> 
                                <span class="text-slate-300">•</span> ${targetBadge}
                            </div>
                        </div>
                        <div class="text-xs font-bold ${notification.is_read ? 'text-slate-300' : 'text-rose-500 px-2 py-1 bg-rose-50 rounded-lg shadow-sm'}">${notification.is_read ? 'อ่านแล้ว' : 'ใหม่'}</div>
                    </div>
                    <div class="mt-3 text-slate-700 text-sm line-clamp-2 bg-slate-50/50 p-2 rounded-xl">
                        ${escapeHtml(notification.message)}
                    </div>
                    <div class="text-[10px] text-slate-400 mt-2 text-right">${formatDate(notification.created_at)}</div>
                `;

                item.addEventListener('click', async () => {
                    if (!isSmartAlert) await markNotificationRead(notification.id);
                    openNotificationDetail(notification, isSmartAlert, targetBadge);
                    await loadNotifications(false);
                });

                notificationList.appendChild(item);
            });
        } catch (error) {}
    }

    async function markNotificationRead(notificationId) {
        try {
            await fetch('api/notifications/mark_read.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `notification_id=${encodeURIComponent(notificationId)}` });
        } catch (error) {}
    }

    function openNotificationDetail(notification, isSmartAlert, targetBadge) {
        const detailHtml = `
            <div class="space-y-4 text-left">
                <div class="text-xl font-bold ${isSmartAlert ? 'text-amber-600' : 'text-slate-900'}">${escapeHtml(notification.title)}</div>
                <div class="text-xs text-slate-500 border-b border-slate-100 pb-3 leading-loose">
                    <span class="font-bold text-indigo-600">ผู้ส่ง: ${escapeHtml(notification.creator_name)}</span><br>
                    เป้าหมาย: ${targetBadge} <br>
                    เวลา: ${formatDate(notification.created_at)}
                </div>
                <div class="text-slate-700 whitespace-pre-line text-sm leading-relaxed p-4 bg-slate-50 rounded-2xl border border-slate-100 shadow-inner">
                    ${escapeHtml(notification.message)}
                </div>
            </div>
        `;
        Swal.fire({
            title: false,
            html: detailHtml,
            showCloseButton: true,
            showConfirmButton: true,
            confirmButtonText: 'ปิดหน้าต่าง',
            confirmButtonColor: '#0ea5e9',
            width: '600px',
            customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl font-bold px-6 py-2 shadow-md' }
        });
    }

    function formatDate(value) {
        if (!value) return '';
        const date = new Date(value);
        return date.toLocaleString('th-TH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function escapeHtml(text) {
        return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
});