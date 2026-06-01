<?php
// config/job_close.php — กำหนดเวลาปิด/แก้ไขงาน (12:00 น. วันถัดไปจากวันมอบหมาย)

function job_close_timezone(): DateTimeZone
{
    return new DateTimeZone('Asia/Bangkok');
}

/** กำหนดเส้นตาย: 12:00 น. ของวันถัดไปจาก plan_arrival_date */
function job_close_deadline(?string $planArrivalDate): ?DateTime
{
    if (!$planArrivalDate) {
        return null;
    }
    $datePart = substr((string)$planArrivalDate, 0, 10);
    $base = DateTime::createFromFormat('Y-m-d', $datePart, job_close_timezone());
    if (!$base) {
        return null;
    }
    $deadline = clone $base;
    $deadline->modify('+1 day');
    $deadline->setTime(12, 0, 0);
    return $deadline;
}

function job_close_now(): DateTime
{
    return new DateTime('now', job_close_timezone());
}

function job_close_can_edit(?string $planArrivalDate, bool $isAdmin): bool
{
    if ($isAdmin) {
        return true;
    }
    $deadline = job_close_deadline($planArrivalDate);
    if (!$deadline) {
        return false;
    }
    return job_close_now() <= $deadline;
}

function job_close_deadline_iso(?string $planArrivalDate): ?string
{
    $d = job_close_deadline($planArrivalDate);
    return $d ? $d->format('c') : null;
}

function job_close_deadline_label(?string $planArrivalDate): string
{
    $d = job_close_deadline($planArrivalDate);
    if (!$d) {
        return '-';
    }
    return $d->format('d/m/Y H:i') . ' น.';
}

function job_close_seconds_until_deadline(?string $planArrivalDate): ?int
{
    $deadline = job_close_deadline($planArrivalDate);
    if (!$deadline) {
        return null;
    }
    return $deadline->getTimestamp() - job_close_now()->getTimestamp();
}

/** งานที่ยังไม่ปิดและควรแจ้งเตือน (วันมอบหมายผ่านมาแล้ว หรือเหลือเวลาไม่เกิน 6 ชม.) */
function job_close_is_urgent(?string $planArrivalDate): bool
{
    $seconds = job_close_seconds_until_deadline($planArrivalDate);
    if ($seconds === null || $seconds <= 0) {
        return false;
    }
    $datePart = substr((string)$planArrivalDate, 0, 10);
    $today = job_close_now()->format('Y-m-d');
    if ($datePart <= $today) {
        return true;
    }
    return $seconds <= 6 * 3600;
}
