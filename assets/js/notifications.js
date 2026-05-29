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
    const notificationTitle = document.getElementById('notificationTitle');
    const notificationMessage = document.getElementById('notificationMessage');
    const notificationTeam = document.getElementById('notificationTeam');
    const isAdmin = window.NOTIFICATIONS_CONFIG?.isAdmin === true;

    if (!bellButton || !notificationModal) return;

    bellButton.addEventListener('click', async () => {
        notificationModal.classList.remove('hidden');
        await loadNotifications();
    });

    closeNotificationModal.addEventListener('click', () => {
        notificationModal.classList.add('hidden');
        if (notificationCreateCard) {
            notificationCreateCard.classList.add('hidden');
        }
    });

    notificationModal.addEventListener('click', (event) => {
        if (event.target === notificationModal) {
            notificationModal.classList.add('hidden');
            if (notificationCreateCard) notificationCreateCard.classList.add('hidden');
        }
    });

    if (openCreateBtn) {
        openCreateBtn.addEventListener('click', async () => {
            if (notificationCreateCard) {
                notificationCreateCard.classList.toggle('hidden');
                if (!notificationCreateCard.classList.contains('hidden')) {
                    await loadNotificationTeams();
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
            const teamId = notificationTeam.value;

            if (!title || !message) {
                Toast.error('กรุณากรอกหัวเรื่องและข้อความแจ้งเตือน');
                return;
            }

            sendNotificationBtn.disabled = true;
            try {
                const formData = new FormData();
                formData.append('title', title);
                formData.append('message', message);
                formData.append('team_id', teamId);

                const res = await fetch('api/notifications/save_notification.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    Toast.success('ส่งแจ้งเตือนเรียบร้อยแล้ว');
                    notificationTitle.value = '';
                    notificationMessage.value = '';
                    notificationTeam.value = '';
                    if (notificationCreateCard) notificationCreateCard.classList.add('hidden');
                    await loadNotifications();
                } else {
                    Toast.error(data.error || 'ส่งแจ้งเตือนล้มเหลว');
                }
            } catch (error) {
                console.error('save notification error', error);
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
            if (!data.success) {
                Toast.error(data.error || 'ไม่สามารถโหลดทีมได้');
                return;
            }
            notificationTeam.innerHTML = '<option value="">ทุกทีม</option>';
            data.data.forEach(team => {
                const option = document.createElement('option');
                option.value = team.id;
                option.textContent = team.team_name;
                notificationTeam.appendChild(option);
            });
        } catch (error) {
            console.error('load teams error', error);
            Toast.error('ไม่สามารถโหลดทีมได้');
        }
    }

    async function loadNotifications() {
        try {
            const res = await fetch('api/notifications/get_notifications.php');
            const data = await res.json();
            if (!data.success) {
                Toast.error(data.error || 'ไม่สามารถโหลดการแจ้งเตือนได้');
                return;
            }

            notificationList.innerHTML = '';
            notificationCount.textContent = data.unread_count || 0;
            unreadDot.classList.toggle('hidden', !(data.unread_count > 0));

            if (!data.notifications.length) {
                notificationList.innerHTML = '<div class="rounded-3xl bg-slate-50 border border-slate-200 p-4 text-slate-500 text-sm text-center">ยังไม่มีการแจ้งเตือน</div>';
                return;
            }

            data.notifications.forEach(notification => {
                const item = document.createElement('div');
                item.className = 'rounded-3xl border border-slate-200 p-4 bg-white shadow-sm cursor-pointer hover:border-indigo-300 transition-colors';
                item.innerHTML = `
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold text-slate-900">${escapeHtml(notification.title)}</div>
                            <div class="text-[11px] text-slate-500 mt-1">${notification.team_name} • โดย ${escapeHtml(notification.creator_name)} • ${formatDate(notification.created_at)}</div>
                        </div>
                        <div class="text-xs font-bold ${notification.is_read ? 'text-slate-400' : 'text-red-600'}">${notification.is_read ? 'อ่านแล้ว' : 'ใหม่'}</div>
                    </div>
                    <p class="mt-3 text-slate-600 text-sm line-clamp-3">${escapeHtml(notification.message)}</p>
                `;

                item.addEventListener('click', async () => {
                    await markNotificationRead(notification.id);
                    openNotificationDetail(notification);
                    await loadNotifications();
                });

                notificationList.appendChild(item);
            });
        } catch (error) {
            console.error('load notifications error', error);
            Toast.error('ไม่สามารถโหลดการแจ้งเตือนได้');
        }
    }

    async function markNotificationRead(notificationId) {
        try {
            await fetch('api/notifications/mark_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `notification_id=${encodeURIComponent(notificationId)}`
            });
        } catch (error) {
            console.error('mark read error', error);
        }
    }

    function openNotificationDetail(notification) {
        const detailHtml = `
            <div class="space-y-4">
                <div class="text-lg font-semibold text-slate-900">${escapeHtml(notification.title)}</div>
                <div class="text-xs text-slate-500">${notification.team_name} • โดย ${escapeHtml(notification.creator_name)} • ${formatDate(notification.created_at)}</div>
                <div class="text-slate-700 whitespace-pre-line">${escapeHtml(notification.message)}</div>
            </div>
        `;
        Swal.fire({
            title: false,
            html: detailHtml,
            showCloseButton: true,
            showConfirmButton: false,
            width: '600px',
            customClass: { popup: 'rounded-3xl' }
        });
    }

    function formatDate(value) {
        if (!value) return '';
        const date = new Date(value);
        return date.toLocaleString('th-TH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    loadNotifications().catch(() => {});
});
