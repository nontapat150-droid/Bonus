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
    const notificationTitle = document.getElementById('notificationTitle');
    const notificationMessage = document.getElementById('notificationMessage');
    const notificationTeam = document.getElementById('notificationTeam');
    const isAdmin = window.NOTIFICATIONS_CONFIG?.isAdmin === true;

    if (!bellButton || !notificationModal) return;

    // เช็คแจ้งเตือนทันทีตอนเปิดเว็บเพื่อแสดงจุดแดง
    loadNotifications(true);

    bellButton.addEventListener('click', async () => {
        notificationModal.classList.remove('hidden');
        await loadNotifications(false);
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
                    await loadNotifications(false);
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

    async function loadNotifications(isBackground = false) {
        try {
            const res = await fetch('api/notifications/get_notifications.php');
            const data = await res.json();
            if (!data.success) return;

            notificationCount.textContent = data.unread_count || 0;
            unreadDot.classList.toggle('hidden', !(data.unread_count > 0));

            // ถ้ารันอยู่เบื้องหลัง (เช่นตอนเปิดเว็บครั้งแรก) ไม่ต้องเรนเดอร์รายการ
            if (isBackground) return;

            notificationList.innerHTML = '';

            if (!data.notifications.length) {
                notificationList.innerHTML = '<div class="rounded-3xl bg-slate-50 border border-slate-200 p-4 text-slate-500 text-sm text-center">ยังไม่มีการแจ้งเตือน</div>';
                return;
            }

            data.notifications.forEach(notification => {
                const item = document.createElement('div');
                const isSmartAlert = typeof notification.id === 'string'; // เช็คว่าเป็นการเตือนอัตโนมัติหรือไม่
                
                item.className = `rounded-3xl border ${isSmartAlert ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-white'} p-4 shadow-sm cursor-pointer hover:border-indigo-400 transition-all`;
                item.innerHTML = `
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-bold ${isSmartAlert ? 'text-amber-900' : 'text-slate-900'}">${escapeHtml(notification.title)}</div>
                            <div class="text-[11px] text-slate-500 mt-1">
                                <span class="font-bold ${isSmartAlert ? 'text-amber-700' : 'text-indigo-600'}">ส่งจาก: ${escapeHtml(notification.creator_name)}</span> • ${notification.team_name}
                            </div>
                        </div>
                        <div class="text-xs font-bold ${notification.is_read ? 'text-slate-400' : 'text-red-600'}">${notification.is_read ? 'อ่านแล้ว' : 'ใหม่'}</div>
                    </div>
                    <div class="mt-2 text-slate-700 text-sm line-clamp-2">
                        <span class="font-bold">เนื้อหา:</span> ${escapeHtml(notification.message)}
                    </div>
                    <div class="text-[10px] text-slate-400 mt-2 text-right">${formatDate(notification.created_at)}</div>
                `;

                item.addEventListener('click', async () => {
                    // มาร์คว่าอ่านแล้วเฉพาะรหัสที่เป็นตัวเลข (ดึงจากฐานข้อมูล) 
                    // ส่วนรหัสที่ตั้งขึ้นมาเองเช่น alert_job จะไม่ถูกส่งไปเซิร์ฟเวอร์
                    if (!isSmartAlert) {
                        await markNotificationRead(notification.id);
                    }
                    openNotificationDetail(notification, isSmartAlert);
                    await loadNotifications(false);
                });

                notificationList.appendChild(item);
            });
        } catch (error) {
            console.error('load notifications error', error);
            if(!isBackground) Toast.error('ไม่สามารถโหลดการแจ้งเตือนได้');
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

    function openNotificationDetail(notification, isSmartAlert) {
        const detailHtml = `
            <div class="space-y-4 text-left">
                <div class="text-xl font-bold ${isSmartAlert ? 'text-amber-600' : 'text-slate-900'}">${escapeHtml(notification.title)}</div>
                <div class="text-xs text-slate-500 border-b border-slate-100 pb-3">
                    <span class="font-bold text-indigo-600">ผู้ส่ง: ${escapeHtml(notification.creator_name)}</span><br>
                    กลุ่มเป้าหมาย: ${notification.team_name} <br>
                    เวลา: ${formatDate(notification.created_at)}
                </div>
                <div class="text-slate-700 whitespace-pre-line text-sm leading-relaxed p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <span class="font-black text-slate-800">💬 ข้อความ:</span><br><br>
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
            confirmButtonColor: '#4f46e5',
            width: '600px',
            customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl font-bold px-6 py-2' }
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
});