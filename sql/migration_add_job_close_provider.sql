-- เพิ่มประเภทงานติดตั้ง AIS / 3BB (รันหลัง migration_add_job_close_3bb.sql)
ALTER TABLE `job_close_3bb`
  ADD COLUMN `install_provider` enum('AIS','3BB') NOT NULL DEFAULT '3BB' AFTER `tech_id`;
