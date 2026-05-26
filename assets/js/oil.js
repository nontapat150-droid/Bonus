// assets/js/oil.js

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('oilForm');
    const litersInput = document.getElementById('liters');
    const priceInput = document.getElementById('price_per_liter');
    const totalInput = document.getElementById('total_price');
    const fileInput = document.getElementById('oil_images');
    const previewContainer = document.getElementById('imagePreviewContainer');
    const imageCount = document.getElementById('imageCount');
    const alertBox = document.getElementById('alertBox');
    
    // Auto-calculate total price
    const calculateTotal = () => {
        const liters = parseFloat(litersInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        totalInput.value = (liters * price).toFixed(2);
    };

    litersInput.addEventListener('input', calculateTotal);
    priceInput.addEventListener('input', calculateTotal);

    // Image Upload Preview (Max 10)
    let selectedFiles = [];

    fileInput.addEventListener('change', (e) => {
        const files = Array.from(e.target.files);
        
        // Check total files
        if (selectedFiles.length + files.length > 10) {
            showAlert('error', 'อัปโหลดรูปภาพได้สูงสุด 10 รูปเท่านั้น');
            fileInput.value = ''; // Reset
            return;
        }

        files.forEach(file => {
            if (file.type.startsWith('image/')) {
                selectedFiles.push(file);
                renderPreview(file, selectedFiles.length - 1);
            }
        });
        
        updateImageCount();
    });

    function renderPreview(file, index) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const div = document.createElement('div');
            div.className = 'relative group rounded-lg overflow-hidden border border-gray-200 aspect-square';
            div.innerHTML = `
                <img src="${e.target.result}" class="w-full h-full object-cover">
                <button type="button" onclick="removeImage(${index})" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity focus:opacity-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            `;
            previewContainer.appendChild(div);
        };
        reader.readAsDataURL(file);
    }

    window.removeImage = (index) => {
        selectedFiles.splice(index, 1);
        // Re-render all to fix indices
        previewContainer.innerHTML = '';
        selectedFiles.forEach((f, i) => renderPreview(f, i));
        updateImageCount();
    };

    function updateImageCount() {
        imageCount.textContent = `เลือกแล้ว: ${selectedFiles.length}/10 รูป`;
    }

    function showAlert(type, message) {
        alertBox.className = `p-4 mb-4 rounded-lg text-sm ${type === 'error' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'}`;
        alertBox.innerHTML = message;
        alertBox.classList.remove('hidden');
        setTimeout(() => alertBox.classList.add('hidden'), 5000);
    }

    // Form Submission Handler
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (selectedFiles.length === 0) {
            showAlert('error', 'กรุณาอัปโหลดรูปภาพหลักฐานอย่างน้อย 1 รูป');
            return;
        }

        const formData = new FormData(form);
        // Replace file input data with our managed selectedFiles array
        formData.delete('oil_images[]');
        selectedFiles.forEach(file => {
            formData.append('oil_images[]', file);
        });

        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<span class="animate-spin inline-block mr-2 border-2 border-white border-t-transparent rounded-full w-4 h-4"></span> กำลังบันทึก...';
        submitBtn.disabled = true;

        try {
            const response = await fetch('api/oil/submit_record.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                showAlert('success', 'บันทึกข้อมูลเรียบร้อยแล้ว!');
                form.reset();
                selectedFiles = [];
                previewContainer.innerHTML = '';
                updateImageCount();
                // Optionally show lock message if license plate is locked to this user now
                document.getElementById('vehicleLockMsg').classList.remove('hidden');
            } else {
                showAlert('error', result.error || 'เกิดข้อผิดพลาดในการบันทึกข้อมูล');
            }
        } catch (error) {
            console.error('Submit error:', error);
            showAlert('error', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
        } finally {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });

    // License Plate Auto-check (Debounced)
    let typingTimer;
    const licenseInput = document.getElementById('license_plate');
    
    licenseInput.addEventListener('input', () => {
        clearTimeout(typingTimer);
        document.getElementById('vehicleLockMsg').classList.add('hidden');
        
        if (licenseInput.value.length > 2) {
            typingTimer = setTimeout(async () => {
                try {
                    const res = await fetch(`api/oil/check_vehicle.php?plate=${encodeURIComponent(licenseInput.value)}`);
                    const data = await res.json();
                    if (data.success && data.locked_to_current_user) {
                        document.getElementById('vehicleLockMsg').classList.remove('hidden');
                    }
                } catch(e) { /* ignore */ }
            }, 500);
        }
    });
});
