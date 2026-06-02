-- ==========================================================
-- SQL Update: แก้ไขปัญหา Foreign Key Constraint 
-- สำหรับการกดใช้งานสินค้าและวัสดุสิ้นเปลืองโดยช่าง (Action: used)
-- ==========================================================

-- เนื่องจากเมื่อช่างกด "ใช้งาน" (used) ระบบจะไม่ได้ส่ง admin_id ไปด้วย 
-- ทำให้ MySQL พยายามใส่ค่าเริ่มต้น (0) และไปตรวจสอบ Foreign Key กับตาราง users 
-- ซึ่ง ID 0 ไม่มีอยู่จริง จึงเกิด Error 1452: Cannot add or update a child row

-- การแก้ไข: เปลี่ยนโครงสร้างคอลัมน์ admin_id ให้รองรับค่าว่าง (NULL) ได้
-- เพื่อให้เมื่อช่างกดใช้งาน admin_id จะเป็น NULL ได้อย่างถูกต้อง

ALTER TABLE `inventory_logs` MODIFY COLUMN `admin_id` INT(11) NULL;

ALTER TABLE `inventory_consumable_logs` MODIFY COLUMN `admin_id` INT(11) NULL;
