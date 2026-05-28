-- 1. เพิ่ม Unique Key ให้กับหมายเลขซีเรียล (SN) เพื่อป้องกันข้อมูลซ้ำซ้อนในระดับฐานข้อมูล
ALTER TABLE `inventory_items` ADD UNIQUE KEY `unique_sn` (`sn`);

-- 2. เพิ่ม Unique Key ให้กับชื่อสินค้า เพื่อป้องกันการสร้างชื่อสินค้าซ้ำกัน
ALTER TABLE `products` ADD UNIQUE KEY `unique_product_name` (`name`);

-- 3. ตรวจสอบความถูกต้องของคอลัมน์ใน inventory_logs (เผื่อกรณีระบบเดิมตกหล่น)
ALTER TABLE `inventory_logs` MODIFY `target_user_id` int(11) DEFAULT NULL;
ALTER TABLE `inventory_logs` MODIFY `receiver_id` int(11) DEFAULT NULL;

-- 4. เพิ่มคอลัมน์หมายเหตุ (Remark) เพื่อเก็บข้อมูลเพิ่มเติมจากการอัปโหลด
ALTER TABLE `inventory_items` ADD COLUMN `remark` TEXT DEFAULT NULL AFTER `status`;

-- 5. จัดการตารางวัสดุสิ้นเปลือง (กรณีต้องการนำเข้าผ่าน Excel ในอนาคต)
ALTER TABLE `inventory_consumable` ADD UNIQUE KEY `unique_cons_name` (`product_name`);
