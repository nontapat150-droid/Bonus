// assets/js/checkin.js

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('checkinForm');
    const fileInput = document.getElementById('checkin_image');
    const imagePreview = document.getElementById('imagePreview');
    const uploadPrompt = document.getElementById('uploadPrompt');
    const timeDisplay = document.getElementById('currentTime');
    const submitBtn = document.getElementById('submitBtn');

    // 1. นาฬิกา Real-time
    setInterval(() => {
        const now = new Date();
        timeDisplay.textContent = now.toLocaleTimeString('th-TH');
    }, 1000);

    // 2. แสดงตัวอย่างรูปภาพก่อนอัปโหลด
    fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            if (!file.type.startsWith('image/')) {
                Toast.error('กรุณาเลือกไฟล์รูปภาพเท่านั้น');
                fileInput.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = (e) => {
                imagePreview.src = e.target.result;
                imagePreview.classList.remove('hidden');
                uploadPrompt.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        }
    });

    // 3. ส่งข้อมูลไปที่ API
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (!fileInput.files[0]) {
            Toast.error('กรุณาถ่ายรูปหรือเลือกรูปภาพก่อนเช็คอิน');
            return;
        }

        Loader.show();
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'กำลังบันทึก...';

        const formData = new FormData(form);

        try {
            const response = await fetch('api/checkin/submit.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                Toast.success(result.message);
                // ล้างฟอร์มหลังจากสำเร็จ
                form.reset();
                imagePreview.src = '';
                imagePreview.classList.add('hidden');
                uploadPrompt.classList.remove('hidden');
            } else {
                Toast.error(result.error || 'เกิดข้อผิดพลาดในการบันทึกข้อมูล');
            }
        } catch (error) {
            console.error('Submit error:', error);
            Toast.error('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
        } finally {
            Loader.hide();
            submitBtn.disabled = false;
            submitBtn.innerHTML = '✅ ยืนยันการเช็คอิน';
        }
    });

    // 4. ส่งออกข้อมูล (สำหรับ Admin)
    const exportCheckinBtn = document.getElementById('exportCheckinBtn');
    if (exportCheckinBtn) {
        exportCheckinBtn.addEventListener('click', async () => {
            const dateStr = document.getElementById('exportCheckinDate').value;
            if (!dateStr) {
                Toast.error('กรุณาเลือกวันที่');
                return;
            }
            
            Loader.show();
            try {
                const response = await fetch(`api/checkin/get_checkins.php?date=${dateStr}`);
                const result = await response.json();
                
                if (result.success) {
                    const data = result.data;
                    if (data.length === 0) {
                        Toast.info('ไม่มีข้อมูลเช็คอินในวันที่เลือก');
                        return;
                    }
                    
                    const exportData = data.map((item, index) => ({
                        'ลำดับ': index + 1,
                        'ชื่อ-นามสกุล': item.full_name,
                        'ชื่อผู้ใช้': item.username,
                        'ทีม / ป้ายทะเบียน': item.team_name || '-',
                        'เวลาเช็คอิน': item.checkin_time
                    }));

                    const ws = XLSX.utils.json_to_sheet(exportData);
                    const wscols = [
                        {wch: 10}, {wch: 30}, {wch: 20}, {wch: 20}, {wch: 25}
                    ];
                    ws['!cols'] = wscols;

                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, "Checkin_Data");
                    XLSX.writeFile(wb, `Checkin_Report_${dateStr}.xlsx`);
                    
                    Toast.success(`ส่งออกข้อมูลเรียบร้อยแล้ว (${data.length} รายการ)`);
                } else {
                    Toast.error(result.error || 'เกิดข้อผิดพลาดในการดึงข้อมูล');
                }
            } catch (error) {
                console.error('Export error:', error);
                Toast.error('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
            } finally {
                Loader.hide();
            }
        });
    }
});