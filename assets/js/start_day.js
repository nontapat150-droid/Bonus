// assets/js/start_day.js
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('startDayForm');
    const fileInput = document.getElementById('start_day_images');
    const previewContainer = document.getElementById('imagePreviewContainer');
    const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB

    let selectedFiles = [];

    fileInput.addEventListener('change', async (e) => {
        const files = Array.from(e.target.files);
        if (selectedFiles.length + files.length > 10) {
            Toast.error('อัปโหลดได้สูงสุด 10 รูป');
            fileInput.value = ''; 
            return;
        }
        
        for (const file of files) {
            if (!file.type.startsWith('image/')) continue;

            let processedFile = file;
            if (file.size > MAX_IMAGE_SIZE) {
                try {
                    processedFile = await compressImage(file, MAX_IMAGE_SIZE);
                } catch (err) {
                    Toast.error('รูปภาพใหญ่เกินไป บีบอัดไม่สำเร็จ');
                    continue;
                }
            }
            selectedFiles.push(processedFile);
            renderPreview(processedFile, selectedFiles.length - 1);
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
                <button type="button" onclick="removeImage(${index})" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity shadow-md">✕</button>
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

    // ฟังก์ชันย่อขนาดรูปภาพ (เหมือนระบบ Oil)
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
                    let quality = 0.9;
                    canvas.width = width;
                    canvas.height = height;
                    ctx.drawImage(img, 0, 0, width, height);
                    canvas.toBlob(blob => {
                        const name = file.name.replace(/\.[^/.]+$/, '') + '.jpg';
                        resolve(new File([blob], name, { type: 'image/jpeg' }));
                    }, 'image/jpeg', quality);
                };
                img.src = event.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (selectedFiles.length === 0) return Toast.error('กรุณาถ่ายรูปหรือแนบรูปภาพอย่างน้อย 1 รูป');

        const formData = new FormData(form);
        formData.delete('start_day_images[]');
        selectedFiles.forEach(file => formData.append('start_day_images[]', file));

        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerHTML;
        Loader.show();
        submitBtn.disabled = true;

        try {
            const response = await fetch('api/start_day/submit.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                Swal.fire({
                    title: 'สำเร็จ!',
                    text: 'บันทึกข้อมูลเรียบร้อยแล้ว (ระบบบันทึกเวลาให้คุณอัตโนมัติ)',
                    icon: 'success',
                    confirmButtonText: 'ตกลง',
                    confirmButtonColor: '#059669',
                    customClass: { popup: 'rounded-3xl', confirmButton: 'rounded-xl px-6 py-2.5 font-bold shadow-md' }
                });
                form.reset();
                selectedFiles = [];
                previewContainer.innerHTML = '';
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