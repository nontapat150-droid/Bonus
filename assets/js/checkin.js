// assets/js/checkin.js
let checkinData = [];
let activeCheckinTab = window.SHOW_REGULAR ? 'regular' : 'ma';
window.activeHistoryMode = 'checkin';

// เก็บไฟล์รูปที่ stamp GPS แล้วไว้ในตัวแปร global
let _regularStampedFile = null;
let _maStampedFile = null;
let _processingPhoto = false;
let _processingMaPhoto = false;

/**
 * วาด rounded rect แบบ cross-browser (ไม่ใช้ roundRect ที่ไม่รองรับใน Android เก่า)
 */
function _roundedRect(ctx, x, y, w, h, r) {
    r = Math.min(r, w / 2, h / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.lineTo(x + w - r, y);
    ctx.arcTo(x + w, y, x + w, y + r, r);
    ctx.lineTo(x + w, y + h - r);
    ctx.arcTo(x + w, y + h, x + w - r, y + h, r);
    ctx.lineTo(x + r, y + h);
    ctx.arcTo(x, y + h, x, y + h - r, r);
    ctx.lineTo(x, y + r);
    ctx.arcTo(x, y, x + r, y, r);
    ctx.closePath();
}

/**
 * stampGpsOnImage - วาด GPS Watermark ลงบนรูปภาพโดยใช้ Canvas
 * รองรับทุก browser รวมถึง Android WebView เก่า
 */
async function stampGpsOnImage(originalFile, lat, lng) {
    return new Promise((resolve) => {
        try {
            const reader = new FileReader();
            reader.onload = (ev) => {
                const img = new Image();
                img.onload = () => {
                    try {
                        const canvas = document.createElement('canvas');
                        // จำกัดขนาดสูงสุดเพื่อไม่ให้ canvas ใหญ่เกินไปบนมือถือ
                        const MAX_DIM = 2048;
                        let W = img.width, H = img.height;
                        if (W > MAX_DIM || H > MAX_DIM) {
                            const ratio = Math.min(MAX_DIM / W, MAX_DIM / H);
                            W = Math.round(W * ratio);
                            H = Math.round(H * ratio);
                        }
                        canvas.width = W;
                        canvas.height = H;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, W, H);

                        if (lat !== null && lat !== undefined && lng !== null && lng !== undefined) {
                            const now = new Date();
                            // ใช้รูปแบบวันที่ที่กระชับ
                            const pad2 = (n) => String(n).padStart(2, '0');
                            const dateStr = `${now.getFullYear()}-${pad2(now.getMonth() + 1)}-${pad2(now.getDate())}`;
                            const timeStr = `${pad2(now.getHours())}:${pad2(now.getMinutes())}:${pad2(now.getSeconds())}`;
                            const latStr = `Lat: ${parseFloat(lat).toFixed(6)}`;
                            const lngStr = `Lng: ${parseFloat(lng).toFixed(6)}`;

                            const lines = [`${dateStr} ${timeStr}`, latStr, lngStr];

                            // ขนาด font สัดส่วนกับรูป
                            const fontSize = Math.max(16, Math.round(W * 0.032));
                            ctx.font = `bold ${fontSize}px Courier, monospace`;

                            const lineH = fontSize * 1.65;
                            const padX = fontSize * 0.8;
                            const padY = fontSize * 0.6;

                            // วัดความกว้างสูงสุดของข้อความ
                            let maxTextW = 0;
                            lines.forEach(l => {
                                const w = ctx.measureText(l).width;
                                if (w > maxTextW) maxTextW = w;
                            });

                            const boxW = maxTextW + padX * 2;
                            const boxH = lines.length * lineH + padY * 1.4;
                            const margin = 14;
                            const boxX = W - boxW - margin;
                            const boxY = H - boxH - margin;
                            const radius = fontSize * 0.45;

                            // พื้นหลังทึบแบบ camera-stamp
                            ctx.fillStyle = 'rgba(0,0,0,0.68)';
                            _roundedRect(ctx, boxX, boxY, boxW, boxH, radius);
                            ctx.fill();

                            // เส้นขอบสีเขียว GPS
                            ctx.strokeStyle = 'rgba(52, 211, 153, 0.95)';
                            ctx.lineWidth = Math.max(2, W * 0.0018);
                            _roundedRect(ctx, boxX, boxY, boxW, boxH, radius);
                            ctx.stroke();

                            // วาดข้อความ
                            lines.forEach((line, i) => {
                                const x = boxX + padX;
                                const y = boxY + padY + (i + 0.82) * lineH;
                                // เงา
                                ctx.fillStyle = 'rgba(0,0,0,0.8)';
                                ctx.font = `bold ${fontSize}px Courier, monospace`;
                                ctx.fillText(line, x + 1.5, y + 1.5);
                                // ข้อความ
                                ctx.fillStyle = i === 0 ? '#FCD34D' : '#6EE7B7';
                                ctx.fillText(line, x, y);
                            });
                        }

                        canvas.toBlob((blob) => {
                            if (!blob) { resolve(originalFile); return; }
                            const baseName = (originalFile.name || 'photo').replace(/\.[^/.]+$/, '');
                            resolve(new File([blob], `${baseName}_gps.jpg`, { type: 'image/jpeg', lastModified: Date.now() }));
                        }, 'image/jpeg', 0.92);

                    } catch (err) {
                        console.warn('stampGpsOnImage canvas error:', err);
                        resolve(originalFile);
                    }
                };
                img.onerror = () => resolve(originalFile);
                img.src = ev.target.result;
            };
            reader.onerror = () => resolve(originalFile);
            reader.readAsDataURL(originalFile);
        } catch (err) {
            console.warn('stampGpsOnImage outer error:', err);
            resolve(originalFile);
        }
    });
}

/**
 * ดึง GPS แล้ว stamp บนรูปทันที เมื่อผู้ใช้เลือก/ถ่ายรูป
 * คืนค่า { stampedFile, lat, lng }
 */
async function processCheckinPhoto(file, previewEl, promptEl) {
    // แสดง preview รูปเดิมก่อน (สำหรับกรณีที่นี่ถูกเรียกซ้ำ preview อาจถูก set ไว้แล้วจากภายนอก)
    const rawUrl = URL.createObjectURL(file);
    previewEl.src = rawUrl;
    previewEl.classList.remove('hidden');
    if (promptEl) promptEl.classList.add('hidden');

    // ดึง GPS
    let lat = null, lng = null;
    try {
        const pos = await new Promise((res, rej) => {
            if (!navigator.geolocation) return rej(new Error('Browser ไม่รองรับ Geolocation'));
            navigator.geolocation.getCurrentPosition(res, rej, { enableHighAccuracy: true, timeout: 8000 });
        });
        lat = pos.coords.latitude;
        lng = pos.coords.longitude;
    } catch (e) {
        console.warn('GPS unavailable:', e);
    }

    // Stamp GPS บนรูป
    let stampedFile;
    try {
        stampedFile = await stampGpsOnImage(file, lat, lng);
    } catch (err) {
        console.warn('Canvas stamp error:', err);
        stampedFile = file; // fallback
    }

    // อัปเดต preview เป็นรูปที่ stamp แล้ว
    const stampedUrl = URL.createObjectURL(stampedFile);
    previewEl.src = stampedUrl;

    return { stampedFile, lat, lng };
}


window.switchHistoryMode = function (mode) {
    if (activeHistoryMode === mode) return;
    activeHistoryMode = mode;

    const tabCheckin = document.getElementById('histTabCheckin');
    const tabCheckout = document.getElementById('histTabCheckout');

    if (tabCheckin && tabCheckout) {
        if (mode === 'checkin') {
            tabCheckin.className = "flex-1 py-2 rounded-xl text-sm font-black transition-all bg-indigo-600 text-white";
            tabCheckout.className = "flex-1 py-2 rounded-xl text-sm font-black transition-all text-slate-500 hover:bg-slate-50";
        } else {
            tabCheckout.className = "flex-1 py-2 rounded-xl text-sm font-black transition-all bg-indigo-600 text-white";
            tabCheckin.className = "flex-1 py-2 rounded-xl text-sm font-black transition-all text-slate-500 hover:bg-slate-50";
        }
    }

    const tbody = document.getElementById('historyTableBody');
    if (tbody) {
        tbody.innerHTML = '';
        renderTable(checkinData);
    }
};

window.switchCheckinTab = function (tab) {
    activeCheckinTab = tab;
    const panelRegular = document.getElementById('panelRegular');
    const panelMa = document.getElementById('panelMa');
    const tabRegular = document.getElementById('tabRegular');
    const tabMa = document.getElementById('tabMa');

    if (panelRegular) panelRegular.classList.toggle('hidden', tab !== 'regular');
    if (panelMa) panelMa.classList.toggle('hidden', tab !== 'ma');

    if (tabRegular) {
        tabRegular.classList.toggle('bg-indigo-600', tab === 'regular');
        tabRegular.classList.toggle('text-white', tab === 'regular');
        tabRegular.classList.toggle('text-indigo-600', tab !== 'regular');
    }
    if (tabMa) {
        tabMa.classList.toggle('bg-violet-600', tab === 'ma');
        tabMa.classList.toggle('text-white', tab === 'ma');
        tabMa.classList.toggle('text-violet-600', tab !== 'ma');
    }

    const historyTitle = document.getElementById('historyTitle');
    if (historyTitle) historyTitle.textContent = tab === 'ma' ? 'ประวัติเช็คอิน MA' : 'ประวัติเช็คอิน';

    loadCheckinHistory();
};

document.addEventListener('DOMContentLoaded', () => {
    if (!window.SHOW_REGULAR && window.SHOW_MA) {
        activeCheckinTab = 'ma';
        switchCheckinTab('ma');
    }

    initRegularCheckin();
    initMaCheckin();
    loadMaSettings();

    // ตั้งค่าเดือนปัจจุบัน
    const now = new Date();
    const currMonth = now.toISOString().slice(0, 7);
    if (document.getElementById('filterMonth')) {
        document.getElementById('filterMonth').value = currMonth;
    }

    loadSettings();
    loadCheckinHistory();

    if (document.getElementById('filterDate')) {
        document.getElementById('filterDate').addEventListener('change', function () { document.getElementById('filterMonth').value = ''; });
    }
    if (document.getElementById('filterMonth')) {
        document.getElementById('filterMonth').addEventListener('change', function () { document.getElementById('filterDate').value = ''; });
    }
});

function initRegularCheckin() {
    const form = document.getElementById('checkinForm');
    const fileInput = document.getElementById('checkin_image');
    const imagePreview = document.getElementById('imagePreview');
    const uploadPrompt = document.getElementById('uploadPrompt');
    const timeDisplay = document.getElementById('currentTime');
    const submitBtn = document.getElementById('submitBtn');

    if (!form) return;

    if (timeDisplay) {
        setInterval(() => {
            timeDisplay.textContent = new Date().toLocaleTimeString('th-TH');
        }, 1000);
    }

    if (fileInput) {
        fileInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            // แสดง preview รูปเดิมก่อนทันที
            const rawUrl = URL.createObjectURL(file);
            imagePreview.src = rawUrl;
            imagePreview.classList.remove('hidden');
            if (uploadPrompt) uploadPrompt.classList.add('hidden');

            // ปิดปุ่มขณะกำลัง stamp GPS
            _processingPhoto = true;
            _regularStampedFile = null;
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '⏳ กำลังประมวลผลรูป...';
            }

            try {
                const result = await processCheckinPhoto(file, imagePreview, uploadPrompt);
                _regularStampedFile = result.stampedFile;
            } catch (err) {
                console.warn('processCheckinPhoto error:', err);
                _regularStampedFile = file; // fallback ใช้รูปเดิม
            } finally {
                _processingPhoto = false;
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '✅ ยืนยันการเช็คอิน';
                }
            }
        });
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (_processingPhoto) {
            return Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: 'กรุณารอสักครู่ ระบบกำลังประมวลผลรูปภาพ...', confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
        }

        const sendFile = _regularStampedFile || fileInput.files[0];
        if (!sendFile) {
            return Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: 'กรุณาถ่ายรูปเช็คอิน', confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
        }

        Loader.show();
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'กำลังบันทึก...';

        try {
            const formData = new FormData();
            formData.append('checkin_image', sendFile);

            // ดึง GPS แบบ optional (timeout สั้น ไม่ block)
            try {
                const pos = await new Promise((res, rej) => {
                    if (!navigator.geolocation) return rej('no geoloc');
                    navigator.geolocation.getCurrentPosition(res, rej, { timeout: 4000, maximumAge: 60000 });
                });
                formData.append('lat', pos.coords.latitude);
                formData.append('lng', pos.coords.longitude);
            } catch (gpsErr) {
                // GPS optional
            }

            const response = await fetch('api/checkin/submit.php', { method: 'POST', body: formData });

            let result;
            const rawText = await response.text();
            try {
                result = JSON.parse(rawText);
            } catch (parseErr) {
                throw new Error('Server ตอบกลับมาไม่ใช่ JSON: ' + rawText.substring(0, 100));
            }

            if (result.success) {
                Swal.fire({
                    title: 'สำเร็จ!',
                    text: result.message,
                    icon: 'success',
                    timer: 500,
                    showConfirmButton: false,
                    customClass: { popup: 'rounded-3xl' }
                });
                form.reset();
                _regularStampedFile = null;
                imagePreview.src = '';
                imagePreview.classList.add('hidden');
                if (uploadPrompt) uploadPrompt.classList.remove('hidden');
                loadCheckinHistory();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'เช็คอินไม่สำเร็จ',
                    text: result.error || 'เกิดข้อผิดพลาดจากเซิร์ฟเวอร์',
                    confirmButtonText: 'ตกลง',
                    customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }
                });
            }
        } catch (err) {
            console.error('Checkin submit error:', err);
            Swal.fire({
                icon: 'error',
                title: 'ข้อผิดพลาดระบบ',
                text: 'การเช็คอินล้มเหลว: ' + (err.message || String(err)),
                confirmButtonText: 'ตกลง',
                customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }
            });
        } finally {
            Loader.hide();
            submitBtn.disabled = false;
            submitBtn.innerHTML = '✅ ยืนยันการเช็คอิน';
        }
    });

    const editImageInput = document.getElementById('edit_checkin_image');
    if (editImageInput) {
        editImageInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            const preview = document.getElementById('editImagePreview');
            const placeholder = document.getElementById('editImagePlaceholder');

            if (!file) {
                if (preview) { preview.src = ''; preview.classList.add('hidden'); }
                if (placeholder) placeholder.classList.remove('hidden');
                return;
            }
            if (!file.type.startsWith('image/')) {
                Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: 'กรุณาเลือกไฟล์รูปภาพเท่านั้น', confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
                editImageInput.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = (event) => {
                if (preview) {
                    preview.src = event.target.result;
                    preview.classList.remove('hidden');
                }
                if (placeholder) placeholder.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        });
    }
}

function initMaCheckin() {
    const form = document.getElementById('maCheckinForm');
    const fileInput = document.getElementById('ma_checkin_image');
    const imagePreview = document.getElementById('maImagePreview');
    const uploadPrompt = document.getElementById('maUploadPrompt');
    const timeDisplay = document.getElementById('maCurrentTime');
    const submitBtn = document.getElementById('maSubmitBtn');

    if (timeDisplay) {
        setInterval(() => {
            timeDisplay.textContent = new Date().toLocaleTimeString('th-TH');
        }, 1000);
    }

    if (fileInput) {
        fileInput.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            // แสดง preview รูปเดิมก่อนทันที
            const rawUrl = URL.createObjectURL(file);
            imagePreview.src = rawUrl;
            imagePreview.classList.remove('hidden');
            if (uploadPrompt) uploadPrompt.classList.add('hidden');

            _processingMaPhoto = true;
            _maStampedFile = null;
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '⏳ กำลังประมวลผลรูป...';
            }

            try {
                const result = await processCheckinPhoto(file, imagePreview, uploadPrompt);
                _maStampedFile = result.stampedFile;
            } catch (err) {
                console.warn('processCheckinPhoto MA error:', err);
                _maStampedFile = file;
            } finally {
                _processingMaPhoto = false;
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '✅ ยืนยันเช็คอิน MA';
                }
            }
        });
    }

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (_processingMaPhoto) {
                return Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: 'กรุณารอสักครู่ ระบบกำลังประมวลผลรูปภาพ...', confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
            }

            const sendFile = _maStampedFile || fileInput.files[0];
            if (!sendFile) {
                return Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: 'กรุณาถ่ายรูปเช็คอิน MA', confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
            }

            Loader.show();
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'กำลังบันทึก...';

            try {
                const formData = new FormData();
                formData.append('ma_checkin_image', sendFile);

                try {
                    const pos = await new Promise((res, rej) => {
                        if (!navigator.geolocation) return rej('no geoloc');
                        navigator.geolocation.getCurrentPosition(res, rej, { timeout: 4000, maximumAge: 60000 });
                    });
                    formData.append('lat', pos.coords.latitude);
                    formData.append('lng', pos.coords.longitude);
                } catch (_) { /* GPS optional */ }

                const response = await fetch('api/checkin/ma_submit.php', { method: 'POST', body: formData });

                let result;
                const rawText = await response.text();
                try {
                    result = JSON.parse(rawText);
                } catch (parseErr) {
                    throw new Error('Server ตอบกลับมาไม่ใช่ JSON: ' + rawText.substring(0, 100));
                }

                if (result.success) {
                    Swal.fire({
                        title: result.is_late ? 'เช็คอินแล้ว (มาสาย)' : 'เช็คอินสำเร็จ!',
                        text: result.message,
                        icon: result.is_late ? 'warning' : 'success',
                        timer: 500,
                        showConfirmButton: false,
                        customClass: { popup: 'rounded-3xl' }
                    });
                    form.reset();
                    _maStampedFile = null;
                    imagePreview.src = '';
                    imagePreview.classList.add('hidden');
                    if (uploadPrompt) uploadPrompt.classList.remove('hidden');
                    loadCheckinHistory();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เช็คอิน MA ไม่สำเร็จ',
                        text: result.error || 'เกิดข้อผิดพลาดจากเซิร์ฟเวอร์',
                        confirmButtonText: 'ตกลง',
                        customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }
                    });
                }
            } catch (err) {
                console.error('MA Checkin submit error:', err);
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาดระบบ (MA)',
                    text: 'การเช็คอินล้มเหลว: ' + (err.message || String(err)),
                    confirmButtonText: 'ตกลง',
                    customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }
                });
            } finally {
                Loader.hide();
                submitBtn.disabled = false;
                submitBtn.innerHTML = '✅ ยืนยันเช็คอิน MA';
            }
        });
    }
}


async function loadMaSettings() {
    try {
        const res = await fetch('api/checkin/ma_settings.php');
        const data = await res.json();
        if (data.success) {
            const globalTime = data.late_time || '08:30';
            const personalTime = data.personal_late_time || globalTime;

            const input = document.getElementById('maLateTimeInput');
            if (input) input.value = globalTime;

            const displayRo = document.getElementById('maDeadlineDisplayRo');
            if (displayRo) displayRo.textContent = globalTime;

            const displayEl = document.getElementById('maDeadlineDisplay');
            if (displayEl) {
                displayEl.textContent = personalTime;
                if (data.has_job) {
                    displayEl.classList.add('text-rose-600');
                    displayEl.title = 'คำนวณจากเวลาของงานแรกสุดของวันนี้';
                    // อัปเดตข้อความอธิบายให้ชัดเจนขึ้น
                    const parentP = displayEl.closest('p');
                    if (parentP) parentP.innerHTML = `เวลาเข้างาน MA (ตามงานแรก): ไม่เกิน <span id="maDeadlineDisplay" class="font-black text-rose-600">${personalTime}</span> น.`;
                }
            }
        }
    } catch (e) { }
}

window.saveMaSettings = async function () {
    if (!window.IS_SUPER_ADMIN) return Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: 'เฉพาะผู้ดูแลระบบเท่านั้น', confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
    const time = document.getElementById('maLateTimeInput')?.value;
    if (!time) return Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: 'กรุณาระบุเวลา', confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});

    Loader.show();
    try {
        const res = await fetch('api/checkin/ma_settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ late_time: time })
        });
        const data = await res.json();
        Loader.hide();
        if (data.success) {
            Swal.fire({ title: 'บันทึกสำเร็จ', text: data.message, icon: 'success', timer: 2000, showConfirmButton: false, customClass: { popup: 'rounded-3xl' } });
            loadMaSettings();
        } else {
            Swal.fire('ข้อผิดพลาด', data.error, 'error');
        }
    } catch (e) {
        Loader.hide();
        Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: 'เชื่อมต่อล้มเหลว', confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
    }
};

// โหลดข้อมูลตาราง
async function loadCheckinHistory() {
    const fDate = document.getElementById('filterDate').value;
    const fMonth = document.getElementById('filterMonth').value;

    const dashLabel = document.getElementById('dashLabel');
    const prefix = activeCheckinTab === 'ma' ? 'MA · ' : '';
    if (fDate) dashLabel.textContent = `${prefix}วันที่ ${new Date(fDate).toLocaleDateString('th-TH')}`;
    else if (fMonth) {
        const d = new Date(fMonth + '-01');
        dashLabel.textContent = `${prefix}เดือน ${d.toLocaleString('th-TH', { month: 'long', year: 'numeric' })}`;
    } else dashLabel.textContent = activeCheckinTab === 'ma' ? 'MA · ทั้งหมด' : 'ทั้งหมด';

    document.getElementById('historyTableBody').innerHTML = '<tr class="block md:table-row"><td colspan="5" class="text-center py-8 block md:table-cell">กำลังโหลดข้อมูล...</td></tr>';

    const apiUrl = activeCheckinTab === 'ma'
        ? `api/checkin/ma_get_history.php?date=${fDate}&month=${fMonth}`
        : `api/checkin/get_history.php?date=${fDate}&month=${fMonth}`;

    try {
        const res = await fetch(apiUrl);
        const data = await res.json();

        if (data.success) {
            checkinData = data.records;
            renderTable(checkinData);

            document.getElementById('dashTotal').textContent = data.dashboard.total;
            document.getElementById('dashOntime').textContent = data.dashboard.on_time;
            document.getElementById('dashLate').textContent = data.dashboard.late;
        } else {
            Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: data.error, confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
        }
    } catch (e) {
        document.getElementById('historyTableBody').innerHTML = '<tr class="block md:table-row"><td colspan="5" class="text-center py-8 text-red-500 block md:table-cell">โหลดข้อมูลล้มเหลว</td></tr>';
    }
}

// เรนเดอร์ตาราง
function renderTable(records) {
    const tbody = document.getElementById('historyTableBody');
    tbody.innerHTML = '';

    if (records.length === 0) {
        tbody.innerHTML = '<tr class="block md:table-row"><td colspan="5" class="text-center py-8 text-gray-400 italic block md:table-cell">ไม่พบประวัติการเข้างานในช่วงเวลานี้</td></tr>';
        return;
    }

    records.forEach((item) => {
        const dateObj = new Date(item.checkin_time);

        const tr = document.createElement('tr');
        tr.className = 'block md:table-row bg-white border border-slate-100 md:border-b md:border-x-0 md:border-t-0 rounded-[1.5rem] md:rounded-none shadow-sm md:shadow-none mb-4 md:mb-0 hover:bg-slate-50 transition-all p-4 md:p-0';

        let badge = '';
        if (activeHistoryMode === 'checkin') {
            badge = item.status_code === 'late'
                ? `<span class="bg-orange-100 text-orange-700 px-3 py-1 rounded-lg text-xs font-bold border border-orange-200">มาสาย</span>`
                : (item.status_code === 'day_off'
                    ? `<span class="bg-slate-100 text-slate-500 px-3 py-1 rounded-lg text-xs font-bold border border-slate-200">วันหยุด</span>`
                    : (item.status_code === 'leave'
                        ? `<span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-lg text-xs font-bold border border-purple-200">ลา</span>`
                        : `<span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-lg text-xs font-bold border border-emerald-200">ตรงเวลา</span>`));
        } else {
            if (item.status_code === 'day_off') {
                badge = `<span class="bg-slate-100 text-slate-500 px-3 py-1 rounded-lg text-xs font-bold border border-slate-200">วันหยุด</span>`;
            } else if (item.status_code === 'leave') {
                badge = `<span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-lg text-xs font-bold border border-purple-200">ลา</span>`;
            } else if (item.checkout_time) {
                badge = `<span class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-lg text-xs font-bold border border-indigo-200">เลิกงานแล้ว</span>`;
            } else {
                badge = `<span class="bg-slate-100 text-slate-500 px-3 py-1 rounded-lg text-xs font-bold border border-slate-200">ยังไม่เลิกงาน</span>`;
            }
        }

        const canEdit = item.status_code !== 'day_off' && activeCheckinTab !== 'ma' && ['super_admin', 'admin', 'technician', 'sales'].includes(window.USER_ROLE);
        const canDelete = item.status_code !== 'day_off' && activeCheckinTab !== 'ma' && window.USER_ROLE === 'super_admin';
        const canAdminManage = item.status_code !== 'day_off' && window.USER_ROLE === 'super_admin';

        let imageCell = '';
        let timeHtml = '';
        let actionHtml = `<div class="flex justify-end md:justify-center gap-2">`;
        let folder = activeCheckinTab === 'ma' ? 'ma_checkins' : 'checkins';

        if (activeHistoryMode === 'checkin') {
            let editedTag = parseInt(item.is_edited_image) === 1 ? `<span class="text-[10px] text-amber-600 font-bold ml-1">(แก้ไขรูป)</span>` : '';
            timeHtml = `<span class="text-xs text-indigo-600 font-mono bg-indigo-50 px-2 py-0.5 rounded-md md:ml-2 ml-1 font-bold" title="เวลาเข้างาน">${dateObj.toLocaleTimeString('th-TH')}</span>${editedTag}`;

            imageCell = item.image_path
                ? `<a href="assets/uploads/${folder}/${item.image_path}" target="_blank" class="inline-block hover:scale-105 transition-transform"><img src="assets/uploads/${folder}/${item.image_path}" class="w-12 h-12 md:w-10 md:h-10 object-cover rounded-xl shadow-sm border border-slate-200" alt="Evidence" title="รูปเข้างาน"></a>`
                : (item.status_code === 'day_off' ? `<div class="w-12 h-12 md:w-10 md:h-10 flex items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-[10px] text-slate-400">วันหยุด</div>` : `<div class="w-12 h-12 md:w-10 md:h-10 flex items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-[10px] text-slate-400">ไม่มีรูป</div>`);

            if (canEdit) actionHtml += `<button type="button" onclick="openEditCheckin('${item.id}')" class="px-3 py-1.5 bg-indigo-50 text-indigo-600 font-bold hover:bg-indigo-100 rounded-lg transition-all text-xs border border-indigo-100">🖼️ แก้ไขรูป</button>`;
            if (canAdminManage) actionHtml += `<button type="button" onclick="openAdminEdit('${item.id}')" class="px-3 py-1.5 bg-amber-50 text-amber-600 font-bold hover:bg-amber-100 rounded-lg transition-all text-xs border border-amber-100">🔧 จัดการ</button>`;
            if (canDelete) actionHtml += `<button type="button" onclick="deleteCheckin('${item.id}')" class="px-3 py-1.5 bg-rose-50 text-rose-600 font-bold hover:bg-rose-100 rounded-lg transition-all text-xs border border-rose-100">🗑️ ลบ</button>`;

            if (!canEdit && !canDelete && !canAdminManage) actionHtml += `<span class="text-slate-300 text-xs italic">-</span>`;
            if (item.lat && item.lng) actionHtml += `<a href="https://maps.google.com/?q=${item.lat},${item.lng}" target="_blank" class="px-3 py-1.5 bg-sky-50 text-sky-600 font-bold hover:bg-sky-100 rounded-lg transition-all text-xs border border-sky-100 ml-1" title="พิกัดเข้างาน">📍 แผนที่</a>`;
        } else {
            // Checkout mode
            if (item.checkout_time) {
                const coDateObj = new Date(item.checkout_time);
                timeHtml = `<span class="text-xs text-rose-600 font-mono bg-rose-50 px-2 py-0.5 rounded-md md:ml-2 ml-1 font-bold" title="เวลาเลิกงาน">${coDateObj.toLocaleTimeString('th-TH')}</span>`;
            } else {
                timeHtml = `<span class="text-xs text-slate-400 italic md:ml-2 ml-1">ยังไม่เลิกงาน</span>`;
            }

            if (item.checkout_image) {
                imageCell = `<a href="assets/uploads/${folder}/${item.checkout_image}" target="_blank" class="inline-block hover:scale-105 transition-transform"><img src="assets/uploads/${folder}/${item.checkout_image}" class="w-12 h-12 md:w-10 md:h-10 object-cover rounded-xl shadow-sm border border-rose-200" alt="Checkout Evidence" title="รูปเลิกงาน"></a>`;
            } else {
                imageCell = (item.status_code === 'day_off')
                    ? `<div class="w-12 h-12 md:w-10 md:h-10 flex items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-[10px] text-slate-400">วันหยุด</div>`
                    : `<div class="w-12 h-12 md:w-10 md:h-10 flex items-center justify-center rounded-xl border border-slate-200 bg-slate-100 text-[10px] text-slate-400">ไม่มีรูป</div>`;
            }

            if (canAdminManage) actionHtml += `<button type="button" onclick="openAdminEdit('${item.id}')" class="px-3 py-1.5 bg-amber-50 text-amber-600 font-bold hover:bg-amber-100 rounded-lg transition-all text-xs border border-amber-100">🔧 จัดการ</button>`;
            if (canDelete) actionHtml += `<button type="button" onclick="deleteCheckin('${item.id}')" class="px-3 py-1.5 bg-rose-50 text-rose-600 font-bold hover:bg-rose-100 rounded-lg transition-all text-xs border border-rose-100">🗑️ ลบ</button>`;

            if (!canAdminManage && !canDelete) actionHtml += `<span class="text-slate-300 text-xs italic">-</span>`;
            if (item.checkout_lat && item.checkout_lng) actionHtml += `<a href="https://maps.google.com/?q=${item.checkout_lat},${item.checkout_lng}" target="_blank" class="px-3 py-1.5 bg-purple-50 text-purple-600 font-bold hover:bg-purple-100 rounded-lg transition-all text-xs border border-purple-100 ml-1" title="พิกัดเลิกงาน">📍 แผนที่</a>`;
        }
        actionHtml += `</div>`;

        tr.innerHTML = `
            <td class="flex justify-between items-center md:table-cell px-2 md:px-4 py-3 border-b border-dashed border-slate-100 md:border-none">
                <span class="md:hidden text-[10px] font-black text-slate-400 uppercase tracking-widest">วันที่/เวลา</span>
                <div class="text-right md:text-left">
                    <span class="text-slate-800 font-bold">${dateObj.toLocaleDateString('th-TH')}</span>
                    <div class="mt-1 md:mt-0 md:inline-block">
                        ${timeHtml}
                    </div>
                </div>
            </td>
            <td class="flex justify-between items-center md:table-cell px-2 md:px-4 py-3 border-b border-dashed border-slate-100 md:border-none md:text-center">
                <span class="md:hidden text-[10px] font-black text-slate-400 uppercase tracking-widest">รูปถ่าย</span>
                <div class="flex items-center justify-end md:justify-center">
                    ${imageCell}
                </div>
            </td>
            <td class="flex justify-between items-center md:table-cell px-2 md:px-4 py-3 border-b border-dashed border-slate-100 md:border-none">
                <span class="md:hidden text-[10px] font-black text-slate-400 uppercase tracking-widest">พนักงาน</span>
                <div class="text-right md:text-left">
                    <p class="font-bold text-slate-800">${item.full_name}</p>
                    <p class="text-[10px] font-bold text-slate-400 bg-slate-100 inline-block px-2 py-0.5 rounded mt-1">${item.team_name || 'ไม่มีทีม'}</p>
                </div>
            </td>
            <td class="flex justify-between items-center md:table-cell px-2 md:px-4 py-3 border-b border-dashed border-slate-100 md:border-none md:text-center">
                <span class="md:hidden text-[10px] font-black text-slate-400 uppercase tracking-widest">สถานะ</span>
                ${badge}
            </td>
            <td class="flex justify-between items-center md:table-cell px-2 md:px-4 py-3 pt-4 md:text-center">
                <span class="md:hidden text-[10px] font-black text-slate-400 uppercase tracking-widest">จัดการ</span>
                ${actionHtml}
            </td>
        `;
        tbody.appendChild(tr);
    });
}

// ---------------- ระบบแก้ไข (สำหรับรูปภาพ) และ ลบ ----------------

// เปิดหน้าต่าง Modal อย่างปลอดภัยด้วย ID
window.openEditCheckin = function (id) {
    try {
        const item = checkinData.find(r => r.id == id);

        if (!item) {
            Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: 'ไม่พบข้อมูล กรุณารีเฟรชหน้าเว็บ', confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
            return;
        }

        const idInput = document.getElementById('edit_checkin_id');
        const editInput = document.getElementById('edit_checkin_image');
        const preview = document.getElementById('editImagePreview');
        const placeholder = document.getElementById('editImagePlaceholder');
        const delBtn = document.getElementById('deleteImageBtn');
        const modal = document.getElementById('editCheckinModal');

        if (idInput) idInput.value = item.id;
        if (editInput) editInput.value = '';

        if (item.image_path) {
            if (preview) {
                preview.src = `assets/uploads/checkins/${item.image_path}`;
                preview.classList.remove('hidden');
            }
            if (placeholder) placeholder.classList.add('hidden');

            if (delBtn) {
                if (window.USER_ROLE === 'super_admin') {
                    delBtn.classList.remove('hidden');
                } else {
                    delBtn.classList.add('hidden');
                }
            }
        } else {
            if (preview) {
                preview.src = '';
                preview.classList.add('hidden');
            }
            if (placeholder) placeholder.classList.remove('hidden');
            if (delBtn) delBtn.classList.add('hidden');
        }

        if (modal) {
            modal.classList.remove('hidden');
        }
    } catch (err) {
        console.error("Error modal:", err);
        alert('เกิดข้อผิดพลาดในการเปิดหน้าต่างแก้ไข');
    }
};

// ปิดหน้าต่าง Modal
window.closeEditCheckinModal = function () {
    const modal = document.getElementById('editCheckinModal');
    if (modal) modal.classList.add('hidden');

    const preview = document.getElementById('editImagePreview');
    if (preview) { preview.src = ''; preview.classList.add('hidden'); }

    const placeholder = document.getElementById('editImagePlaceholder');
    if (placeholder) placeholder.classList.remove('hidden');

    const editInput = document.getElementById('edit_checkin_image');
    if (editInput) editInput.value = '';

    const delBtn = document.getElementById('deleteImageBtn');
    if (delBtn) delBtn.classList.add('hidden');
};

// บันทึกการอัปเดตรูปภาพ
window.saveEditCheckin = async function () {
    const idInput = document.getElementById('edit_checkin_id');
    const editInput = document.getElementById('edit_checkin_image');

    if (!idInput || !idInput.value) {
        return Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: 'ไม่พบ ID ข้อมูล', confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
    }

    if (!editInput || !editInput.files || editInput.files.length === 0) {
        return Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: 'กรุณาเลือกรูปภาพใหม่ก่อนทำการบันทึก', confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
    }

    const formData = new FormData();
    formData.append('id', idInput.value);
    formData.append('checkin_image', editInput.files[0]);
    formData.append('type', activeCheckinTab);

    Loader.show();
    try {
        const res = await fetch('api/checkin/edit.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            // ป๊อปอัปแจ้งเตือนเมื่อแก้ไขรูปภาพสำเร็จ
            Swal.fire({
                title: 'สำเร็จ!',
                text: 'อัปเดตรูปภาพเช็คอินเรียบร้อยแล้ว',
                icon: 'success',
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#4f46e5',
                customClass: {
                    popup: 'rounded-3xl',
                    confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md'
                }
            });
            closeEditCheckinModal();
            loadCheckinHistory();
        } else {
            Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: data.error, confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
        }
    } catch (e) {
        Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
    } finally {
        Loader.hide();
    }
};

window.openAdminEdit = function (id) {
    try {
        const item = checkinData.find(r => r.id == id);
        if (!item) {
            Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: 'ไม่พบข้อมูล กรุณารีเฟรชหน้าเว็บ', confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
            return;
        }

        document.getElementById('admin_edit_id').value = item.id;
        document.getElementById('admin_edit_checkin_time').value = item.checkin_time || '';
        document.getElementById('admin_edit_checkout_time').value = item.checkout_time || '';
        document.getElementById('admin_edit_status').value = item.admin_status || '';

        const modal = document.getElementById('adminEditModal');
        if (modal) modal.classList.remove('hidden');
    } catch (err) {
        console.error("Error admin modal:", err);
        alert('เกิดข้อผิดพลาดในการเปิดหน้าต่างจัดการแอดมิน');
    }
};

window.closeAdminEditModal = function () {
    const modal = document.getElementById('adminEditModal');
    if (modal) modal.classList.add('hidden');
};

window.saveAdminEdit = async function () {
    const id = document.getElementById('admin_edit_id').value;
    const checkin_time = document.getElementById('admin_edit_checkin_time').value;
    const checkout_time = document.getElementById('admin_edit_checkout_time').value;
    const admin_status = document.getElementById('admin_edit_status').value;

    if (!id || !checkin_time) {
        return Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: 'กรุณาระบุเวลาเข้างานให้ครบถ้วน', confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
    }

    const formData = new FormData();
    formData.append('id', id);
    formData.append('type', activeCheckinTab);
    formData.append('checkin_time', checkin_time);
    formData.append('checkout_time', checkout_time);
    formData.append('admin_status', admin_status);

    Loader.show();
    try {
        const res = await fetch('api/checkin/admin_edit.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            Swal.fire({
                title: 'สำเร็จ!',
                text: 'อัปเดตข้อมูลแอดมินเรียบร้อยแล้ว',
                icon: 'success',
                confirmButtonText: 'ตกลง',
                confirmButtonColor: '#4f46e5',
                customClass: {
                    popup: 'rounded-3xl',
                    confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md'
                }
            });
            closeAdminEditModal();
            loadCheckinHistory();
        } else {
            Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: data.error, confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
        }
    } catch (e) {
        Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
    } finally {
        Loader.hide();
    }
};

// ระบบลบเฉพาะรูปภาพอย่างเดียว
window.deleteCheckinImage = async function () {
    const idInput = document.getElementById('edit_checkin_id');
    if (!idInput || !idInput.value) return Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: 'ไม่พบข้อมูลที่ต้องการลบรูป', confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});

    Swal.fire({
        title: 'ยืนยันการลบรูปภาพ?',
        text: 'รูปภาพจะถูกลบออกจากระบบ แต่ข้อมูลบันทึกเวลาเช็คอินจะยังอยู่',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ใช่, ลบรูป',
        cancelButtonText: 'ยกเลิก'
    }).then(async (result) => {
        if (result.isConfirmed) {
            Loader.show();
            try {
                const res = await fetch('api/checkin/delete_image.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: idInput.value })
                });
                const data = await res.json();
                if (data.success) {
                    // ป๊อปอัปแจ้งเตือนเมื่อลบรูปภาพสำเร็จ
                    Swal.fire({
                        title: 'สำเร็จ!',
                        text: 'ลบรูปภาพเรียบร้อยแล้ว',
                        icon: 'success',
                        confirmButtonText: 'ตกลง',
                        confirmButtonColor: '#4f46e5',
                        customClass: {
                            popup: 'rounded-3xl',
                            confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md'
                        }
                    });
                    closeEditCheckinModal();
                    loadCheckinHistory();
                } else {
                    Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: data.error, confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
                }
            } catch (e) {
                Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
            } finally {
                Loader.hide();
            }
        }
    });
};

// ระบบลบข้อมูลเช็คอินทั้งรายการ
window.deleteCheckin = async function (id) {
    Swal.fire({
        title: 'ยืนยันการลบข้อมูล?',
        text: "ข้อมูลการเช็คอินนี้และรูปภาพจะถูกลบออกจากระบบอย่างถาวร!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ใช่, ลบข้อมูลเลย',
        cancelButtonText: 'ยกเลิก'
    }).then(async (result) => {
        if (result.isConfirmed) {
            Loader.show();
            try {
                const res = await fetch('api/checkin/delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                const data = await res.json();

                if (data.success) {
                    // ป๊อปอัปแจ้งเตือนเมื่อลบรายการเช็คอินสำเร็จ
                    Swal.fire({
                        title: 'สำเร็จ!',
                        text: 'ลบข้อมูลเช็คอินเรียบร้อยแล้ว',
                        icon: 'success',
                        confirmButtonText: 'ตกลง',
                        confirmButtonColor: '#4f46e5',
                        customClass: {
                            popup: 'rounded-3xl',
                            confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md'
                        }
                    });
                    loadCheckinHistory();
                } else {
                    Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: data.error, confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
                }
            } catch (e) {
                Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
            } finally {
                Loader.hide();
            }
        }
    });
};

// ---------------- Settings & Export (Admin) ----------------
let currentRoleSettings = {};

async function loadSettings() {
    try {
        const res = await fetch('api/checkin/settings.php');
        const data = await res.json();
        if (data.success && data.settings) {
            currentRoleSettings = data.settings;

            // จัดกลุ่มบทบาทตามเวลา
            const timeGroups = {};
            for (const [role, time] of Object.entries(data.settings)) {
                if (!timeGroups[time]) timeGroups[time] = [];
                timeGroups[time].push(role);
            }

            const uniqueTimes = Object.keys(timeGroups).sort();

            // รีเซ็ต Checkbox ทั้งหมดก่อน
            document.querySelectorAll('.role-cb-1, .role-cb-2').forEach(cb => cb.checked = false);

            if (uniqueTimes.length > 0) {
                const time1 = uniqueTimes[0];
                const input1 = document.getElementById('lateTimeInput1');
                if (input1) input1.value = time1;
                timeGroups[time1].forEach(r => {
                    const cb = document.querySelector(`.role-cb-1[value="${r}"]`);
                    if (cb) cb.checked = true;
                });
            }
            if (uniqueTimes.length > 1) {
                const time2 = uniqueTimes[1];
                const input2 = document.getElementById('lateTimeInput2');
                if (input2) input2.value = time2;
                timeGroups[time2].forEach(r => {
                    const cb = document.querySelector(`.role-cb-2[value="${r}"]`);
                    if (cb) cb.checked = true;
                });
            }
        }
    } catch (e) { }
}

window.saveSettingsMulti = async function (rowNum) {
    const timeInput = document.getElementById(`lateTimeInput${rowNum}`);
    const time = timeInput ? timeInput.value : '';

    const checkedBoxes = document.querySelectorAll(`.role-cb-${rowNum}:checked`);
    const roles = Array.from(checkedBoxes).map(cb => cb.value);

    if (roles.length === 0) return Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: 'กรุณาเลือกอย่างน้อย 1 บทบาท', confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
    if (!time) return Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: 'กรุณาระบุเวลา', confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});

    Loader.show();
    try {
        const res = await fetch('api/checkin/settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ late_time: time, roles: roles })
        });
        const data = await res.json();

        Loader.hide(); // ต้องซ่อน Loader ก่อนเรียก Swal เพื่อไม่ให้มันไปปิด Swal

        if (data.success) {
            // ป๊อปอัปแจ้งเตือนสวยๆ มีดีเลย์ก่อนหาย
            Swal.fire({
                title: 'บันทึกสำเร็จ!',
                text: `อัปเดตเวลาเข้างานเรียบร้อยแล้ว`,
                icon: 'success',
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: false,
                customClass: {
                    popup: 'rounded-3xl'
                }
            }).then(() => {
                loadCheckinHistory();
                loadSettings(); // โหลดใหม่เพื่อจัดกลุ่มให้ตรง
            });
        } else {
            Swal.fire('ข้อผิดพลาด', data.error || 'เกิดข้อผิดพลาด', 'error');
        }
    } catch (e) {
        Loader.hide();
        Swal.fire('ล้มเหลว', 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้', 'error');
    }
};

window.exportCheckin = function () {
    if (checkinData.length === 0) return Swal.fire({icon: 'error', title: 'ข้อผิดพลาด', text: 'ไม่มีข้อมูลให้ Export', confirmButtonText: 'ตกลง', customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }});
    Toast.info('กำลังสร้างไฟล์ Excel...');

    const exportArr = checkinData.map((r, i) => {
        const d = new Date(r.checkin_time);
        let checkoutTime = 'ยังไม่เลิกงาน';
        if (r.checkout_time) {
            const coDate = new Date(r.checkout_time);
            checkoutTime = coDate.toLocaleTimeString('th-TH');
        }
        return {
            "ลำดับ": i + 1,
            "วันที่": d.toLocaleDateString('th-TH'),
            "เวลาเข้างาน": d.toLocaleTimeString('th-TH'),
            "พิกัดเข้างาน": r.lat && r.lng ? `${r.lat}, ${r.lng}` : '-',
            "เวลาเลิกงาน": checkoutTime,
            "พิกัดเลิกงาน": r.checkout_lat && r.checkout_lng ? `${r.checkout_lat}, ${r.checkout_lng}` : '-',
            "สถานะ": r.status_text,
            "ชื่อ-นามสกุล": r.full_name,
            "สังกัด/ทีม": r.team_name || '-',
            "รูปเข้างาน": r.image_path || 'ไม่มีรูป',
            "รูปเลิกงาน": r.checkout_image || 'ไม่มีรูป'
        };
    });

    const worksheet = XLSX.utils.json_to_sheet(exportArr);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, "ประวัติการเช็คอิน");
    XLSX.writeFile(workbook, `เช็คอิน_${new Date().getTime()}.xlsx`);
    Toast.success('ดาวน์โหลดสำเร็จ');
};

document.addEventListener('DOMContentLoaded', function () {

    // จัดการปุ่มเลิกงาน (ระบบทั่วไป)
    const checkoutBtn = document.getElementById('checkoutBtn');
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', function () {
            if (_processingPhoto) {
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณารอสักครู่',
                    text: 'ระบบกำลังประมวลผลรูปภาพ...',
                    customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }
                });
                return;
            }
            const fileInput = document.getElementById('checkin_image');
            const sendFile = _regularStampedFile || fileInput.files[0];
            processCheckout(sendFile, 'regular');
        });
    }

    // จัดการปุ่มเลิกงาน (ระบบ MA)
    const maCheckoutBtn = document.getElementById('maCheckoutBtn');
    if (maCheckoutBtn) {
        maCheckoutBtn.addEventListener('click', function () {
            if (_processingMaPhoto) {
                Swal.fire({
                    icon: 'warning',
                    title: 'กรุณารอสักครู่',
                    text: 'ระบบกำลังประมวลผลรูปภาพ...',
                    customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }
                });
                return;
            }
            const fileInput = document.getElementById('ma_checkin_image');
            const sendFile = _maStampedFile || fileInput.files[0];
            processCheckout(sendFile, 'ma');
        });
    }

    // ฟังก์ชันหลักสำหรับส่งข้อมูลเลิกงาน
    async function processCheckout(imageFile, type) {
        if (!imageFile) {
            Swal.fire({
                icon: 'warning',
                title: 'กรุณาถ่ายรูป',
                text: 'คุณต้องถ่ายรูปเพื่อยืนยันการเลิกงาน'
            });
            return;
        }

        Swal.fire({
            title: 'กำลังขอตำแหน่ง...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        let lat = null, lng = null;
        try {
            const pos = await new Promise((resolve, reject) => {
                if (!navigator.geolocation) reject("Browser no geoloc");
                else navigator.geolocation.getCurrentPosition(resolve, reject, { enableHighAccuracy: true, timeout: 10000 });
            });
            lat = pos.coords.latitude;
            lng = pos.coords.longitude;
        } catch (e) {
            console.warn("Location error:", e);
        }

        Swal.fire({
            title: 'กำลังบันทึกการเลิกงาน...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        const formData = new FormData();
        formData.append('checkout_image', imageFile);
        formData.append('type', type); // 'regular' หรือ 'ma'
        if (lat && lng) {
            formData.append('lat', lat);
            formData.append('lng', lng);
        }

        // เรียกใช้งาน API สำหรับ Checkout
        fetch('api/checkin/checkout.php', {
            method: 'POST',
            body: formData
        })
            .then(async response => {
                const text = await response.text();
                try {
                    return JSON.parse(text);
                } catch (err) {
                    throw new Error('เซิร์ฟเวอร์ตอบกลับผิดพลาด: ' + text.substring(0, 100));
                }
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'บันทึกการเลิกงานสำเร็จ',
                        text: 'เวลาเลิกงานของคุณถูกบันทึกเรียบร้อยแล้ว',
                        timer: 500,
                        showConfirmButton: false,
                        customClass: { popup: 'rounded-3xl' }
                    }).then(() => {
                        location.reload(); // รีเฟรชหน้าเพื่ออัปเดตสถานะและประวัติ
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'เลิกงานไม่สำเร็จ',
                        text: data.error || 'ไม่สามารถบันทึกการเลิกงานได้',
                        customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }
                    });
                }
            })
            .catch(error => {
                console.error("Checkout error:", error);
                Swal.fire({
                    icon: 'error',
                    title: 'ข้อผิดพลาดระบบ',
                    text: 'การเลิกงานล้มเหลว: ' + (error.message || String(error)),
                    customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }
                });
            });
    }
});