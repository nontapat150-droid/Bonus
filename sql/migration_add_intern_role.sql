-- Migration: Add Intern Role
-- Date: 2026-06-03
-- Description: Adds the 'intern' (เด็กฝึกงาน) role to the user roles system

-- Modify the users table to include 'intern' in the role enum
ALTER TABLE `users` 
MODIFY COLUMN `role` ENUM('super_admin', 'admin', 'technician', 'sales', 'intern') NOT NULL DEFAULT 'technician';

-- Add a note that interns can have late time allowance
-- This allows interns to have flexible start times like technicians and sales staff
