<?php
// views/modules/oil_form.php
// Ensure this file is included within index.php
if (!defined('PDO::ATTR_ERRMODE')) {
    exit('เข้าถึงโดยตรงไม่ได้');
}
?>

<div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-xl overflow-hidden">
    <div class="bg-blue-600 px-6 py-4">
        <h2 class="text-2xl font-bold text-white flex items-center">
            <span class="mr-2 text-3xl">⛽</span> บันทึกการใช้น้ำมัน
        </h2>
        <p class="text-blue-100 text-sm mt-1">กรอกข้อมูลการใช้รถและอัปเดตหลักฐาน (สูงสุด 10 รูป)</p>
    </div>

    <div class="p-6">
        <form id="oilForm" enctype="multipart/form-data" class="space-y-6">

            <!-- Alert Message Area -->
            <div id="alertBox" class="hidden rounded-lg p-4 mb-4 text-sm"></div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- License Plate -->
                <div class="col-span-1 md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">ป้ายทะเบียนรถ <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="text" id="license_plate" name="license_plate" required
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors uppercase"
                            placeholder="เช่น 1กข 1234">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">      
                            <span class="text-gray-400">🚗</span>
                        </div>
                    </div>
                    <p id="vehicleLockMsg" class="text-xs text-green-600 mt-1 hidden">✓ ผูกมัดป้ายทะเบียนนี้กับคุณเรียบร้อยแล้ว</p>
                </div>

                <!-- Mileage -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">เลขไมล์ปัจจุบัน <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="number" id="mileage" name="mileage" required min="0"
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            placeholder="0">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">      
                            <span class="text-gray-400">📊</span>
                        </div>
                    </div>
                </div>

                <!-- Liters -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">จำนวนลิตร <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="number" id="liters" name="liters" required min="0.01" step="0.01"
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            placeholder="0.00">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">      
                            <span class="text-gray-400">💧</span>
                        </div>
                    </div>
                </div>

                <!-- Price per Liter -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ราคา/ลิตร <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="number" id="price_per_liter" name="price_per_liter" required min="0.01" step="0.01"
                            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            placeholder="0.00">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">      
                            <span class="text-gray-400">฿</span>
                        </div>
                    </div>
                </div>

                <!-- Total Price (Auto-calculated) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ราคารวม (บาท)</label>
                    <div class="relative">
                        <input type="number" id="total_price" name="total_price" readonly
                            class="w-full pl-10 pr-4 py-2 bg-gray-50 border border-gray-300 rounded-lg font-bold text-gray-700 cursor-not-allowed"
                            placeholder="0.00">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">      
                            <span class="text-gray-400">💰</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Image Upload -->
            <div class="mt-6 border-t border-gray-200 pt-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">รูปภาพหลักฐาน (อัปโหลดได้สูงสุด 10 รูป) <span class="text-red-500">*</span></label>   

                <div class="flex items-center justify-center w-full">
                    <label for="oil_images" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 transition-colors">      
                        <div class="flex flex-col items-center justify-center pt-5 pb-6">
                            <svg class="w-8 h-8 mb-4 text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2"/>
                            </svg>
                            <p class="mb-2 text-sm text-gray-500"><span class="font-semibold">คลิกเพื่ออัปโหลด</span> หรือลากไฟล์มาวาง</p>
                            <p class="text-xs text-gray-500">PNG, JPG, JPEG (รองรับมือถือ)</p>
                        </div>
                        <input id="oil_images" name="oil_images[]" type="file" class="hidden" multiple accept="image/*" required />
                    </label>
                </div>

                <!-- Image Preview Area -->
                <div id="imagePreviewContainer" class="mt-4 grid grid-cols-2 sm:grid-cols-5 gap-4">
                    <!-- Previews will be injected here via JS -->
                </div>
                <p id="imageCount" class="text-xs text-gray-500 mt-2 text-right">เลือกแล้ว: 0/10 รูป</p>
            </div>

            <!-- Submit Button -->
            <div class="pt-4">
                <button type="submit" id="submitBtn" class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    <span>บันทึกข้อมูล</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Load Oil Form Logic -->
<script src="assets/js/oil.js"></script>