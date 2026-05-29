// assets/js/start_day.js
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('startDayForm');
    const fileInput = document.getElementById('oil_images');
    const previewContainer = document.getElementById('imagePreviewContainer');
    const licensePlateSelect = document.getElementById('license_plate');
    const dateInput = document.getElementById('date_recorded');

    // เซ็ตเวลาเริ่มต้นเป็นเวลาปัจจุบัน
    if (dateInput) {
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        dateInput.value = now.toISOString().slice(0,16);
    }

    // โหลดป้ายทะเบียนรถ (ใช้งาน API เดียวกับระบบน้ำมันได้เลย)
    async function loadTeamPlates() {
        try {
            const res = await fetch('api/oil/get_team_plates.php');
            const data = await res.json();
            if (data.success) {
                licensePlateSelect.innerHTML = '<option value="">-- เลือกป้ายทะเบียนรถ --</option>';
                data.data.forEach(team => {
                    const option = document.createElement('option');
                    option.value = team.id;
                    option.textContent = `🚗 ${team.team_name}`;
                    option.setAttribute('data-plate', team.team_name);
                    if (data.my_team_id && String(team.id) === String(data.my_team_id)) {
                        option.selected = true;
                    }
                    licensePlateSelect.appendChild(option);
                });
            }
        } catch (e) {
            console.error('Error loading team plates:', e);
        }
    }
    loadTeamPlates();

    // จัดการอัปโหลดและพรีวิวรูปภาพ
    let selectedFiles = [];
    fileInput.addEventListener('change', async (e) => {
        const files = Array.from(e.target.files);
        if (selectedFiles.length + files.length > 10) return Toast.error('อัปโหลดได้สูงสุด 10 รูป');
        
        for (const file of files) {
            if (file.type.startsWith('image/')) {
                selectedFiles.push(file);
                renderPreview(file, selectedFiles.length - 1);
            }
        }
        fileInput.value = '';
    });

    function renderPreview(file, index) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const div = document.createElement('div');
            div.className = 'relative group rounded-lg overflow-hidden border border-gray-200 aspect-square';
            div.innerHTML = `
                <img src="${e.target.result}" class="w-full h-full object-cover">
                <button type="button" onclick="removeImage(${index})" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity">✕</button>
            `;
            previewContainer.appendChild(div);
        };
        reader.readAsDataURL(file);
    }

    window.removeImage = (index) => {
        selectedFiles.splice(index, 1);
        previewContainer.innerHTML = '';
        selectedFiles.forEach((f, i) => renderPreview(f, i));
    };

    // ส่งข้อมูล (ใช้ API ร่วมกับระบบน้ำมันเพราะโครงสร้าง DB เดียวกัน)
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const selectedOption = licensePlateSelect.options[licensePlateSelect.selectedIndex];
        const teamName = selectedOption ? selectedOption.getAttribute('data-plate') : '';
        if (!teamName) return Toast.error('กรุณาเลือกป้ายทะเบียนรถ');
        if (selectedFiles.length === 0) return Toast.error('กรุณาถ่ายรูปหน้าปัดไมล์');

        const formData = new FormData(form);
        formData.set('license_plate', teamName);
        formData.delete('oil_images[]');
        selectedFiles.forEach(file => formData.append('oil_images[]', file));

        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerHTML;
        Loader.show();
        submitBtn.disabled = true;

        try {
            const response = await fetch('api/oil/submit_record.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                Swal.fire({
                    title: 'สำเร็จ!',
                    text: 'บันทึกค่าแรกเข้าเรียบร้อยแล้ว ขอให้ทำงานอย่างปลอดภัยครับ',
                    icon: 'success',
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#059669',
                    customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2 font-bold' }
                });
                form.reset();
                selectedFiles = [];
                previewContainer.innerHTML = '';
                
                // เซ็ตเวลาใหม่
                const now = new Date();
                now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                dateInput.value = now.toISOString().slice(0,16);
            } else {
                Toast.error(result.error);
            }
        } catch (error) {
            Toast.error('เชื่อมต่อเซิร์ฟเวอร์ล้มเหลว');
        } finally {
            Loader.hide();
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });
});