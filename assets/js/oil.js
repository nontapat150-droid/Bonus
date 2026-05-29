// assets/js/oil.js

let teamPlatesData = [];

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('oilForm');
    const litersInput = document.getElementById('liters');
    const priceInput = document.getElementById('price_per_liter');
    const totalInput = document.getElementById('total_price');
    const fileInput = document.getElementById('oil_images');
    const previewContainer = document.getElementById('imagePreviewContainer');
    const imageCount = document.getElementById('imageCount');
    const licensePlateSelect = document.getElementById('license_plate');
    const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB

    // โหลดป้ายทะเบียนจากระบบทีม
    loadTeamPlates();

    // Auto-calculate total price
    const calculateTotal = () => {
        const liters = parseFloat(litersInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        totalInput.value = Math.round(liters * price);
    };

    const updateTeamInfo = (teamName) => {
        const teamInfoEl = document.getElementById('displayUserTeam');
        if (!teamInfoEl) return;

        if (teamName) {
            teamInfoEl.innerHTML = `<span class="bg-amber-100 text-amber-700 px-2 py-0.5 rounded-lg text-[10px] font-black">🚗 ทีม: ${teamName}</span>`;
        } else {
            teamInfoEl.innerHTML = '<span class="text-slate-400 text-xs">ยังไม่ได้เลือกทีม/ป้ายทะเบียน</span>';
        }
    };

    litersInput.addEventListener('input', calculateTotal);
    priceInput.addEventListener('input', calculateTotal);

    // เมื่อเลือกป้ายทะเบียน → แสดงจำนวนเคสงานของทีม
    licensePlateSelect.addEventListener('change', () => {
        const selectedTeamId = licensePlateSelect.value;
        const jobCountDiv = document.getElementById('teamJobCount');
        const jobCountValue = document.getElementById('jobCountValue');

        if (selectedTeamId) {
            const team = teamPlatesData.find(t => String(t.id) === String(selectedTeamId));
            if (team) {
                jobCountValue.textContent = team.job_count || 0;
                jobCountDiv.classList.remove('hidden');
                updateTeamInfo(team.team_name);
            }
        } else {
            jobCountDiv.classList.add('hidden');
            updateTeamInfo(null);
        }
    });

    // Image Upload Preview (Max 10)
    let selectedFiles = [];

    fileInput.addEventListener('change', async (e) => {
        const files = Array.from(e.target.files);

        if (selectedFiles.length + files.length > 10) {
            Toast.error('อัปโหลดรูปภาพได้สูงสุด 10 รูปเท่านั้น');
            fileInput.value = ''; // Reset
            return;
        }

        for (const file of files) {
            if (!file.type.startsWith('image/')) {
                continue;
            }

            let processedFile = file;
            if (file.size > MAX_IMAGE_SIZE) {
                try {
                    processedFile = await compressImage(file, MAX_IMAGE_SIZE);
                } catch (error) {
                    Toast.error(error.message || 'ไม่สามารถบีบอัดรูปภาพได้');
                    continue;
                }
            }

            selectedFiles.push(processedFile);
            renderPreview(processedFile, selectedFiles.length - 1);
        }

        updateImageCount();
        fileInput.value = '';
    });

    function renderPreview(file, index) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const div = document.createElement('div');
            div.className = 'relative group rounded-lg overflow-hidden border border-gray-200 aspect-square animate__animated animate__zoomIn';
            div.innerHTML = `
                <img src="${e.target.result}" class="w-full h-full object-cover">
                <button type="button" onclick="removeImage(${index})" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity focus:opacity-100 shadow-md">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            `;
            previewContainer.appendChild(div);
        };
        reader.readAsDataURL(file);
    }

    window.removeImage = (index) => {
        selectedFiles.splice(index, 1);
        previewContainer.innerHTML = '';
        selectedFiles.forEach((f, i) => renderPreview(f, i));
        updateImageCount();
    };

    function updateImageCount() {
        imageCount.textContent = `เลือกแล้ว: ${selectedFiles.length}/10 รูป`;
    }

    async function compressImage(file, maxSize) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (event) => {
                const img = new Image();
                img.onload = async () => {
                    let canvas = document.createElement('canvas');
                    let ctx = canvas.getContext('2d');
                    const ratio = Math.min(1, 1920 / Math.max(img.width, img.height));
                    let width = Math.round(img.width * ratio);
                    let height = Math.round(img.height * ratio);
                    let quality = 0.92;
                    let blob = null;

                    while (true) {
                        canvas.width = width;
                        canvas.height = height;
                        ctx.clearRect(0, 0, width, height);
                        ctx.drawImage(img, 0, 0, width, height);

                            const mimeType = file.type === 'image/png' ? 'image/jpeg' : file.type;
                        blob = await new Promise((res) => canvas.toBlob(res, mimeType, quality));

                        if (!blob) {
                            reject(new Error('เกิดข้อผิดพลาดขณะประมวลผลภาพ'));
                            return;
                        }

                        if (blob.size <= maxSize) {
                            const ext = mimeType === 'image/png' ? 'png' : 'jpg';
                            const name = file.name.replace(/\.[^/.]+$/, '') + `.${ext}`;
                            resolve(new File([blob], name, { type: blob.type }));
                            return;
                        }

                        if (quality > 0.5) {
                            quality -= 0.08;
                        } else if (width > 800 || height > 800) {
                            width = Math.round(width * 0.9);
                            height = Math.round(height * 0.9);
                        } else {
                            reject(new Error('ไม่สามารถลดขนาดรูปภาพให้อยู่ภายใน 5MB ได้'));
                            return;
                        }
                    }
                };
                img.onerror = () => {
                    reject(new Error('ไม่สามารถโหลดรูปภาพเพื่อบีบอัดได้'));
                };
                img.src = event.target.result;
            };
            reader.onerror = () => reject(new Error('ไม่สามารถอ่านไฟล์รูปภาพได้'));
            reader.readAsDataURL(file);
        });
    }

    // Form Submission Handler
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (selectedFiles.length === 0) {
            Toast.error('กรุณาอัปโหลดรูปภาพหลักฐานอย่างน้อย 1 รูป');
            return;
        }

        // ส่ง team_name (ป้ายทะเบียน) แทน team_id
        const selectedOption = licensePlateSelect.options[licensePlateSelect.selectedIndex];
        const teamName = selectedOption ? selectedOption.getAttribute('data-plate') : '';

        if (!teamName) {
            Toast.error('กรุณาเลือกป้ายทะเบียนรถ');
            return;
        }

        const formData = new FormData(form);
        // แทนที่ license_plate ด้วยชื่อทีม (ป้ายทะเบียน) จริง
        formData.set('license_plate', teamName);

        formData.delete('oil_images[]');
        selectedFiles.forEach(file => {
            formData.append('oil_images[]', file);
        });

        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerHTML;
        
        Loader.show();
        submitBtn.disabled = true;

        try {
            const response = await fetch('api/oil/submit_record.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                Toast.success('บันทึกข้อมูลและอัปโหลดรูปภาพเรียบร้อยแล้ว!');
                form.reset();
                selectedFiles = [];
                previewContainer.innerHTML = '';
                updateImageCount();
                // รีโหลด dropdown
                loadTeamPlates();
            } else {
                Toast.error(result.error || 'เกิดข้อผิดพลาดในการบันทึกข้อมูล');
            }
        } catch (error) {
            console.error('Submit error:', error);
            Toast.error('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
        } finally {
            Loader.hide();
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });
});

// โหลดรายการป้ายทะเบียน (ทีม) จาก API
async function loadTeamPlates() {
    const select = document.getElementById('license_plate');
    const teamInfoEl = document.getElementById('displayUserTeam');

    try {
        const res = await fetch('api/oil/get_team_plates.php');
        const data = await res.json();

        if (data.success) {
            teamPlatesData = data.data;
            const myTeamId = data.my_team_id;

            // สร้าง options
            select.innerHTML = '<option value="">-- เลือกป้ายทะเบียนรถ --</option>';

            let myTeamName = null;

            teamPlatesData.forEach(team => {
                const option = document.createElement('option');
                option.value = team.id;
                option.textContent = `🚗 ${team.team_name} (${team.job_count} งาน)`;
                option.setAttribute('data-plate', team.team_name);
                option.setAttribute('data-jobs', team.job_count);

                // เลือกทีมตัวเองอัตโนมัติ
                if (myTeamId && String(team.id) === String(myTeamId)) {
                    option.selected = true;
                    myTeamName = team.team_name;
                }

                select.appendChild(option);
            });

            // แสดงข้อมูลทีมของผู้ใช้
            if (myTeamName) {
                updateTeamInfo(myTeamName);

                // แสดงจำนวนเคสงานทันที
                const myTeam = teamPlatesData.find(t => String(t.id) === String(myTeamId));
                if (myTeam) {
                    const jobCountDiv = document.getElementById('teamJobCount');
                    const jobCountValue = document.getElementById('jobCountValue');
                    jobCountValue.textContent = myTeam.job_count || 0;
                    jobCountDiv.classList.remove('hidden');
                }
            } else {
                teamInfoEl.innerHTML = '<span class="text-slate-400 text-xs">ยังไม่ได้ผูกทีม/ป้ายทะเบียน</span>';
            }
        } else {
            select.innerHTML = '<option value="">-- ไม่สามารถโหลดข้อมูลได้ --</option>';
            teamInfoEl.textContent = 'โหลดข้อมูลทีมไม่สำเร็จ';
        }
    } catch (e) {
        console.error('Error loading team plates:', e);
        select.innerHTML = '<option value="">-- เกิดข้อผิดพลาด --</option>';
        teamInfoEl.textContent = 'เกิดข้อผิดพลาดในการโหลดข้อมูลทีม';
    }
}
// ฟังก์ชันสำหรับกดปุ่มคำนวณระยะทางไมล์ใหม่ทั้งหมด
window.recalculateAllMileage = function() {
    Swal.fire({
        title: 'จัดเรียงและคำนวณไมล์ใหม่?',
        text: "ระบบจะทำการเรียงลำดับวันที่การเติมน้ำมันของรถทุกคันใหม่ และคำนวณระยะทางจากเลขไมล์ให้สอดคล้องกันทั้งหมด คุณต้องการดำเนินการต่อหรือไม่?",
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#0ea5e9',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'ใช่, คำนวณใหม่เลย',
        cancelButtonText: 'ยกเลิก',
        reverseButtons: true
    }).then(async (result) => {
        if (result.isConfirmed) {
            showLoader();
            try {
                const res = await fetch('api/oil/recalculate.php');
                const data = await res.json();
                if (data.success) {
                    showToast('success', 'เรียงวันที่และคำนวณระยะทางใหม่เรียบร้อยแล้ว!');
                    // ดึงข้อมูลมาแสดงใหม่ทันที
                    fetchData(true); 
                } else {
                    Swal.fire('เกิดข้อผิดพลาด', data.error, 'error');
                }
            } catch (e) {
                Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้', 'error');
            } finally {
                hideLoader();
            }
        }
    });
};