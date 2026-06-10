<?php
// views/modules/checkin.php
if (!defined('PDO::ATTR_ERRMODE')) exit('เข้าถึงโดยตรงไม่ได้');
$isAdmin = hasRole(['admin', 'super_admin']);
$isSuperAdmin = hasRole('super_admin');
$canMaCheckin = hasRole('ma_technician');
$showRegularCheckin = !isMaTechnicianOnly();
$showMaCheckin = $canMaCheckin || $isAdmin;
?>

<script>
    window.USER_ROLE = '<?php echo $_SESSION['role'] ?? 'guest'; ?>';
    window.USER_ROLES = <?php echo json_encode(getUserRoles()); ?>;
    window.SHOW_REGULAR = <?php echo $showRegularCheckin ? 'true' : 'false'; ?>;
    window.SHOW_MA = <?php echo $showMaCheckin ? 'true' : 'false'; ?>;
    window.IS_SUPER_ADMIN = <?php echo $isSuperAdmin ? 'true' : 'false'; ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>

<style>
/* ====================================================
   CHECKIN MODULE — REDESIGNED 2025
   Font: Sarabun (Thai) + system-ui
   Palette: Indigo primary / Violet MA / Slate neutrals
   ==================================================== */

:root {
  --c-primary:       #4F46E5;
  --c-primary-h:     #3730A3;
  --c-primary-tint:  #EEF2FF;
  --c-primary-ring:  rgba(79,70,229,0.18);

  --c-ma:            #7C3AED;
  --c-ma-h:          #6D28D9;
  --c-ma-tint:       #F5F3FF;
  --c-ma-ring:       rgba(124,58,237,0.18);

  --c-ok:            #059669;
  --c-ok-tint:       #ECFDF5;
  --c-ok-border:     #A7F3D0;

  --c-danger:        #E11D48;
  --c-danger-tint:   #FFF1F2;
  --c-danger-border: #FECDD3;

  --c-warn:          #D97706;
  --c-warn-tint:     #FFFBEB;
  --c-warn-border:   #FDE68A;

  --c-info:          #0284C7;
  --c-info-tint:     #F0F9FF;
  --c-info-border:   #BAE6FD;

  --c-bg:            #F8FAFC;
  --c-surface:       #FFFFFF;
  --c-surface-alt:   #F1F5F9;
  --c-border:        #E2E8F0;
  --c-border-faint:  #F1F5F9;

  --c-ink:           #0F172A;
  --c-ink-2:         #334155;
  --c-ink-3:         #64748B;
  --c-ink-4:         #94A3B8;

  --r-sm: 10px;
  --r-md: 14px;
  --r-lg: 18px;
  --r-xl: 22px;

  --sh-sm: 0 1px 3px rgba(0,0,0,0.07), 0 1px 2px rgba(0,0,0,0.04);
  --sh-md: 0 4px 16px rgba(0,0,0,0.08), 0 1px 4px rgba(0,0,0,0.04);
  --sh-lg: 0 12px 36px rgba(0,0,0,0.10), 0 4px 12px rgba(0,0,0,0.05);
  --sh-xl: 0 24px 56px rgba(0,0,0,0.13), 0 8px 20px rgba(0,0,0,0.06);

  --ease: cubic-bezier(0.4, 0, 0.2, 1);
  --t: 0.18s var(--ease);
}

/* ─── RESET ─────────────────────────────────────── */
.ck * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
.ck {
  font-family: 'Sarabun', system-ui, -apple-system, sans-serif;
  color: var(--c-ink);
  font-size: 15px;
  line-height: 1.5;
}

/* ─── MODULE-LEVEL TAB STRIP ─────────────────────── */
.ck-tab-strip {
  display: flex;
  gap: 0;
  background: var(--c-surface-alt);
  border: 1px solid var(--c-border);
  border-radius: var(--r-xl);
  padding: 4px;
  width: fit-content;
  max-width: 100%;
  margin-bottom: 20px;
  box-shadow: var(--sh-sm);
}

.tab-btn {
  flex: 1;
  min-width: 130px;
  padding: 10px 20px;
  border: none;
  border-radius: var(--r-lg);
  font-size: 14px;
  font-family: inherit;
  font-weight: 700;
  cursor: pointer;
  transition: var(--t);
  background: transparent;
  color: var(--c-ink-3);
  white-space: nowrap;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.tab-btn.active {
  background: var(--c-surface);
  color: var(--c-primary);
  box-shadow: var(--sh-sm);
}

.tab-btn.active.ma { color: var(--c-ma); }

/* ─── MAIN LAYOUT GRID ───────────────────────────── */
.ck-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
  align-items: start;
}

@media (min-width: 900px) {
  .ck-grid {
    grid-template-columns: 340px 1fr;
    gap: 20px;
  }
}

@media (min-width: 1200px) {
  .ck-grid { grid-template-columns: 360px 1fr; }
}

/* ─── CARD ───────────────────────────────────────── */
.card {
  background: var(--c-surface);
  border-radius: var(--r-xl);
  border: 1px solid var(--c-border-faint);
  box-shadow: var(--sh-md);
  overflow: hidden;
}

/* ─── CARD HEADER ────────────────────────────────── */
.card-header {
  padding: 18px 20px;
  display: flex;
  align-items: center;
  gap: 14px;
}

.card-header.indigo {
  background: linear-gradient(135deg, #4F46E5 0%, #6366F1 100%);
}

.card-header.violet {
  background: linear-gradient(135deg, #7C3AED 0%, #8B5CF6 100%);
}

.card-header-icon {
  width: 46px;
  height: 46px;
  background: rgba(255,255,255,0.18);
  border-radius: var(--r-md);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  flex-shrink: 0;
  border: 1px solid rgba(255,255,255,0.15);
}

.card-header-text h2 {
  font-size: 16px;
  font-weight: 800;
  color: #fff;
  margin: 0;
  line-height: 1.25;
}

.card-header-text p {
  font-size: 12px;
  color: rgba(255,255,255,0.70);
  margin: 2px 0 0;
  font-weight: 500;
}

/* ─── CARD BODY ──────────────────────────────────── */
.card-body {
  padding: 20px;
}

@media (max-width: 479px) {
  .card-body { padding: 16px; }
  .card-header { padding: 14px 16px; }
}

/* ─── CLOCK BLOCK ────────────────────────────────── */
.time-block {
  background: var(--c-bg);
  border: 1px solid var(--c-border);
  border-radius: var(--r-lg);
  padding: 14px 20px;
  text-align: center;
  margin-bottom: 14px;
}

.time-label {
  font-size: 10px;
  font-weight: 700;
  color: var(--c-ink-4);
  letter-spacing: 0.10em;
  text-transform: uppercase;
  margin-bottom: 2px;
}

.time-value {
  font-size: 40px;
  font-weight: 900;
  color: var(--c-primary);
  letter-spacing: -0.04em;
  line-height: 1;
  font-variant-numeric: tabular-nums;
}

.time-value.violet { color: var(--c-ma); }

@media (max-width: 479px) { .time-value { font-size: 34px; } }

.time-sublabel {
  font-size: 11px;
  font-weight: 600;
  color: var(--c-ma);
  margin-top: 5px;
}

.time-sublabel span { font-weight: 800; }

/* ─── PHOTO UPLOAD ZONE ──────────────────────────── */
.photo-zone {
  display: block;
  border: 2px dashed var(--c-border);
  border-radius: var(--r-lg);
  background: var(--c-bg);
  cursor: pointer;
  position: relative;
  overflow: hidden;
  transition: border-color var(--t), background var(--t);
  height: 150px;
}

.photo-zone:hover,
.photo-zone:focus-within {
  border-color: var(--c-primary);
  background: var(--c-primary-tint);
}

.photo-zone.violet:hover,
.photo-zone.violet:focus-within {
  border-color: var(--c-ma);
  background: var(--c-ma-tint);
}

.photo-zone-inner {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  pointer-events: none;
}

.photo-zone-icon {
  width: 46px;
  height: 46px;
  background: var(--c-primary-tint);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform var(--t);
}

.photo-zone:hover .photo-zone-icon { transform: scale(1.08); }

.photo-zone-icon svg { width: 22px; height: 22px; color: var(--c-primary); }

.photo-zone-icon.violet-icon { background: var(--c-ma-tint); }
.photo-zone-icon.violet-icon svg { color: var(--c-ma); }

.photo-zone-text {
  font-size: 13px;
  font-weight: 700;
  color: var(--c-primary);
}

.photo-zone-text.violet { color: var(--c-ma); }
.photo-zone-subtext { font-size: 11px; color: var(--c-ink-4); }

.photo-preview {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* ─── ACTION BUTTONS ─────────────────────────────── */
.btn-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-top: 12px;
}

.btn {
  padding: 13px 16px;
  border: none;
  border-radius: var(--r-md);
  font-size: 14px;
  font-family: inherit;
  font-weight: 800;
  cursor: pointer;
  transition: var(--t);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  white-space: nowrap;
}

.btn:active { transform: scale(0.96); }
.btn:disabled { opacity: 0.40; cursor: not-allowed; transform: none !important; }

.btn-checkin {
  background: var(--c-primary);
  color: #fff;
  box-shadow: 0 4px 14px rgba(79,70,229,0.32);
}

.btn-checkin:hover:not(:disabled) {
  background: var(--c-primary-h);
  box-shadow: 0 6px 20px rgba(79,70,229,0.44);
  transform: translateY(-1px);
}

.btn-checkin.violet {
  background: var(--c-ma);
  box-shadow: 0 4px 14px rgba(124,58,237,0.32);
}

.btn-checkin.violet:hover:not(:disabled) {
  background: var(--c-ma-h);
  box-shadow: 0 6px 20px rgba(124,58,237,0.44);
  transform: translateY(-1px);
}

.btn-checkout {
  background: var(--c-danger-tint);
  color: var(--c-danger);
  border: 1.5px solid var(--c-danger-border);
}

.btn-checkout:hover:not(:disabled) {
  background: var(--c-danger);
  color: #fff;
  border-color: var(--c-danger);
  box-shadow: 0 4px 14px rgba(225,29,72,0.28);
  transform: translateY(-1px);
}

/* ─── STATS GRID ─────────────────────────────────── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.stat-card {
  border-radius: var(--r-md);
  padding: 14px 10px 12px;
  text-align: center;
  border: 1px solid var(--c-border);
}

.stat-label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  margin-bottom: 5px;
}

.stat-value {
  font-size: 30px;
  font-weight: 900;
  letter-spacing: -0.03em;
  line-height: 1;
}

@media (max-width: 479px) {
  .stat-value { font-size: 26px; }
  .stats-grid { gap: 8px; }
}

.stat-card.blue  { background: var(--c-info-tint);   border-color: var(--c-info-border); }
.stat-card.blue .stat-label  { color: var(--c-info); }
.stat-card.blue .stat-value  { color: #0C4A6E; }

.stat-card.green { background: var(--c-ok-tint);     border-color: var(--c-ok-border); }
.stat-card.green .stat-label { color: var(--c-ok); }
.stat-card.green .stat-value { color: #064E3B; }

.stat-card.orange{ background: var(--c-warn-tint);   border-color: var(--c-warn-border); }
.stat-card.orange .stat-label{ color: var(--c-warn); }
.stat-card.orange .stat-value{ color: #78350F; }

/* ─── SECTION HEADER ─────────────────────────────── */
.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}

.section-title {
  font-size: 15px;
  font-weight: 800;
  color: var(--c-ink);
  display: flex;
  align-items: center;
  gap: 8px;
}

.section-title .icon {
  width: 30px;
  height: 30px;
  background: var(--c-primary-tint);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  flex-shrink: 0;
}

/* ─── SETTINGS PANEL ─────────────────────────────── */
.settings-group {
  padding: 14px 16px;
  background: var(--c-bg);
  border-radius: var(--r-md);
  border: 1px solid var(--c-border);
  margin-bottom: 12px;
}

.settings-group-label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--c-ink-4);
  margin-bottom: 10px;
}

.role-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 12px;
}

.role-chip label { cursor: pointer; }
.role-chip input { display: none; }

.role-chip-label {
  display: inline-flex;
  align-items: center;
  padding: 6px 14px;
  border-radius: 99px;
  border: 1.5px solid var(--c-border);
  font-size: 13px;
  font-weight: 700;
  color: var(--c-ink-2);
  background: var(--c-surface);
  transition: var(--t);
  user-select: none;
  white-space: nowrap;
}

.role-chip input:checked + .role-chip-label {
  background: var(--c-primary);
  color: #fff;
  border-color: var(--c-primary);
}

.role-chip.green input:checked + .role-chip-label {
  background: var(--c-ok);
  border-color: var(--c-ok);
}

.settings-time-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.time-input {
  flex: 1;
  min-width: 120px;
  padding: 10px 14px;
  border: 1.5px solid var(--c-border);
  border-radius: var(--r-sm);
  font-size: 15px;
  font-family: inherit;
  font-weight: 700;
  color: var(--c-ink);
  background: var(--c-surface);
  outline: none;
  transition: var(--t);
  font-variant-numeric: tabular-nums;
}

.time-input:focus {
  border-color: var(--c-primary);
  box-shadow: 0 0 0 3px var(--c-primary-ring);
}

.btn-save {
  padding: 10px 20px;
  border: none;
  border-radius: var(--r-sm);
  font-size: 13px;
  font-family: inherit;
  font-weight: 800;
  cursor: pointer;
  transition: var(--t);
  white-space: nowrap;
}

.btn-save.indigo {
  background: var(--c-primary);
  color: #fff;
  box-shadow: 0 2px 8px rgba(79,70,229,0.22);
}
.btn-save.indigo:hover { background: var(--c-primary-h); }

.btn-save.green {
  background: var(--c-ok);
  color: #fff;
  box-shadow: 0 2px 8px rgba(5,150,105,0.22);
}
.btn-save.green:hover { background: #047857; }

/* ─── FILTER ROW ─────────────────────────────────── */
.filter-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 14px;
}

.filter-input {
  padding: 9px 12px;
  border: 1.5px solid var(--c-border);
  border-radius: var(--r-sm);
  font-size: 13px;
  font-family: inherit;
  color: var(--c-ink);
  outline: none;
  transition: var(--t);
  background: var(--c-surface);
  flex: 1;
  min-width: 130px;
}

.filter-input:focus {
  border-color: var(--c-primary);
  box-shadow: 0 0 0 3px var(--c-primary-ring);
}

.btn-filter {
  padding: 9px 18px;
  border: none;
  border-radius: var(--r-sm);
  font-size: 13px;
  font-family: inherit;
  font-weight: 700;
  cursor: pointer;
  transition: var(--t);
  white-space: nowrap;
  flex-shrink: 0;
}

.btn-filter.search {
  background: var(--c-primary);
  color: #fff;
  box-shadow: 0 2px 8px rgba(79,70,229,0.20);
}
.btn-filter.search:hover { background: var(--c-primary-h); }

.btn-filter.excel {
  background: var(--c-ok-tint);
  color: var(--c-ok);
  border: 1.5px solid var(--c-ok-border);
}
.btn-filter.excel:hover { background: #D1FAE5; }

.filter-sep {
  font-size: 12px;
  color: var(--c-ink-4);
  font-weight: 600;
  display: none;
}
@media (min-width: 540px) { .filter-sep { display: inline; } }

/* ─── HISTORY TABLE ──────────────────────────────── */
.history-table-wrap {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  border-radius: var(--r-md);
  border: 1px solid var(--c-border);
}

.history-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 480px;
}

.history-table thead th {
  padding: 11px 14px;
  font-size: 11px;
  font-weight: 700;
  color: var(--c-ink-4);
  text-transform: uppercase;
  letter-spacing: 0.07em;
  background: var(--c-bg);
  border-bottom: 1px solid var(--c-border);
  white-space: nowrap;
}

.history-table thead th:first-child { border-radius: var(--r-md) 0 0 0; }
.history-table thead th:last-child  { border-radius: 0 var(--r-md) 0 0; }

.history-table tbody tr { transition: background 0.12s; }
.history-table tbody tr:last-child td { border-bottom: none; }
.history-table tbody tr:hover { background: var(--c-bg); }

.history-table tbody td {
  padding: 12px 14px;
  font-size: 13px;
  border-bottom: 1px solid var(--c-border-faint);
  vertical-align: middle;
  color: var(--c-ink-2);
}

/* ─── BADGE ──────────────────────────────────────── */
.badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 99px;
  font-size: 11px;
  font-weight: 800;
  white-space: nowrap;
  letter-spacing: 0.02em;
}

.badge.on-time { background: var(--c-ok-tint);     color: var(--c-ok); }
.badge.late    { background: var(--c-danger-tint);  color: var(--c-danger); }
.badge.leave   { background: var(--c-warn-tint);    color: var(--c-warn); }
.badge.default { background: var(--c-surface-alt);  color: var(--c-ink-4); }

/* ─── ACTION ICON BUTTONS ────────────────────────── */
.action-btn {
  width: 34px;
  height: 34px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: var(--t);
  font-size: 15px;
  background: transparent;
}

.action-btn.edit  { color: var(--c-primary); }
.action-btn.edit:hover  { background: var(--c-primary-tint); }
.action-btn.admin { color: var(--c-warn); }
.action-btn.admin:hover { background: var(--c-warn-tint); }
.action-btn.delete{ color: var(--c-danger); }
.action-btn.delete:hover{ background: var(--c-danger-tint); }

/* ─── MODAL ──────────────────────────────────────── */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15,23,42,0.52);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  animation: ckFadeIn 0.16s ease;
}

@keyframes ckFadeIn { from { opacity: 0; } to { opacity: 1; } }

.modal-box {
  background: var(--c-surface);
  border-radius: var(--r-xl);
  width: 100%;
  max-width: 440px;
  overflow: hidden;
  box-shadow: var(--sh-xl);
  animation: ckSlideUp 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes ckSlideUp {
  from { opacity: 0; transform: translateY(18px) scale(0.97); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}

.modal-header {
  padding: 18px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--c-border-faint);
}

.modal-title {
  font-size: 16px;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 8px;
}

.modal-close {
  width: 34px;
  height: 34px;
  border: none;
  background: var(--c-surface-alt);
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: var(--c-ink-4);
  transition: var(--t);
  line-height: 1;
  flex-shrink: 0;
}

.modal-close:hover { background: var(--c-danger-tint); color: var(--c-danger); }

.modal-body { padding: 20px; }

.modal-footer {
  padding: 14px 20px;
  background: var(--c-bg);
  border-top: 1px solid var(--c-border-faint);
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

/* ─── FORM FIELDS ────────────────────────────────── */
.form-group { margin-bottom: 16px; }
.form-group:last-child { margin-bottom: 0; }

.form-label {
  display: block;
  font-size: 13px;
  font-weight: 700;
  color: var(--c-ink-2);
  margin-bottom: 6px;
}

.form-input {
  width: 100%;
  padding: 10px 14px;
  border: 1.5px solid var(--c-border);
  border-radius: var(--r-sm);
  font-size: 14px;
  font-family: inherit;
  color: var(--c-ink);
  background: var(--c-surface);
  outline: none;
  transition: var(--t);
  appearance: none;
  -webkit-appearance: none;
}

.form-input:focus {
  border-color: var(--c-primary);
  box-shadow: 0 0 0 3px var(--c-primary-ring);
}

.form-hint {
  font-size: 11px;
  color: var(--c-ink-4);
  margin-top: 4px;
}

/* ─── EDIT IMAGE UPLOAD ──────────────────────────── */
.edit-photo-zone {
  border-radius: var(--r-md);
  overflow: hidden;
  border: 1px solid var(--c-border);
  background: var(--c-surface-alt);
  min-height: 160px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.edit-photo-placeholder {
  text-align: center;
  padding: 20px;
  color: var(--c-ink-4);
  font-size: 13px;
  font-weight: 600;
}

.edit-photo-actions {
  display: flex;
  gap: 8px;
  margin-top: 12px;
  flex-wrap: wrap;
}

/* ─── SMALL BUTTONS ──────────────────────────────── */
.btn-sm {
  padding: 9px 18px;
  border: none;
  border-radius: var(--r-sm);
  font-size: 13px;
  font-family: inherit;
  font-weight: 700;
  cursor: pointer;
  transition: var(--t);
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.btn-sm.primary { background: var(--c-primary); color: #fff; }
.btn-sm.primary:hover { background: var(--c-primary-h); }

.btn-sm.danger { background: var(--c-danger-tint); color: var(--c-danger); border: 1px solid var(--c-danger-border); }
.btn-sm.danger:hover { background: var(--c-danger); color: #fff; border-color: var(--c-danger); }

.btn-sm.neutral { background: var(--c-surface-alt); color: var(--c-ink-2); border: 1px solid var(--c-border); }
.btn-sm.neutral:hover { color: var(--c-ink); border-color: var(--c-ink-3); }

.btn-sm.amber { background: #FEF3C7; color: #92400E; }
.btn-sm.amber:hover { background: #FDE68A; }

/* ─── EMPTY STATE ────────────────────────────────── */
.empty-state {
  text-align: center;
  padding: 44px 20px;
  color: var(--c-ink-4);
}

.empty-state-icon { font-size: 34px; margin-bottom: 10px; opacity: 0.45; }
.empty-state-text { font-size: 14px; font-weight: 600; }

/* ─── HISTORY SUMMARY ROW ────────────────────────── */
.hist-summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px;
  padding: 8px 12px;
  background: var(--c-bg);
  border-radius: var(--r-sm);
  border: 1px solid var(--c-border);
  margin-bottom: 12px;
}

.hist-summary-text { font-size: 12px; font-weight: 700; color: var(--c-ink-2); }
.hist-page-info    { font-size: 12px; font-weight: 700; color: var(--c-ink-4); }

/* ─── PAGINATION ─────────────────────────────────── */
.pagination-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  padding-top: 16px;
  flex-wrap: wrap;
}

.pg-numbers {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
  justify-content: center;
}

.pg-btn {
  width: 38px;
  height: 38px;
  border: 1.5px solid var(--c-border);
  border-radius: var(--r-sm);
  background: var(--c-surface);
  color: var(--c-ink-2);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: var(--t);
  font-size: 13px;
  font-weight: 700;
  flex-shrink: 0;
}

.pg-btn:hover:not(:disabled) {
  border-color: var(--c-primary);
  color: var(--c-primary);
  background: var(--c-primary-tint);
}

.pg-btn:disabled { opacity: 0.30; cursor: not-allowed; }

.pg-btn.active {
  background: var(--c-primary);
  border-color: var(--c-primary);
  color: #fff;
  box-shadow: 0 2px 8px rgba(79,70,229,0.28);
}

.pg-btn.pg-ellipsis {
  border-color: transparent;
  background: transparent;
  cursor: default;
  color: var(--c-ink-4);
  font-size: 15px;
  width: 28px;
}

.pg-btn.pg-ellipsis:hover { background: transparent; border-color: transparent; color: var(--c-ink-4); }

@media (max-width: 400px) {
  .pg-btn { width: 34px; height: 34px; font-size: 12px; }
  .pagination-wrap { gap: 3px; }
}

/* ─── NO MA ROLE ─────────────────────────────────── */
.no-role-state { text-align: center; padding: 36px 20px; }

.no-role-icon {
  width: 54px;
  height: 54px;
  background: var(--c-surface-alt);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  margin: 0 auto 12px;
  border: 1px solid var(--c-border);
}

.no-role-text { font-size: 14px; font-weight: 600; color: var(--c-ink-4); }

/* ─── RIGHT-COLUMN STACK ─────────────────────────── */
.ck-right-col {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

/* ─── INLINE HISTORY TAB STRIP ───────────────────── */
.hist-tab-strip {
  display: flex;
  background: var(--c-surface-alt);
  border: 1px solid var(--c-border);
  border-radius: var(--r-lg);
  padding: 3px;
  gap: 0;
}

.hist-tab-strip .tab-btn {
  flex: 1;
  min-width: 0;
  font-size: 13px;
  padding: 8px 12px;
}

/* ─── UTILITY ────────────────────────────────────── */
.dash-label-pill {
  font-size: 12px;
  font-weight: 700;
  color: var(--c-primary);
  background: var(--c-primary-tint);
  padding: 4px 12px;
  border-radius: 99px;
  flex-shrink: 0;
}

.settings-desc {
  font-size: 12px;
  color: var(--c-ink-4);
  margin: 0 0 14px;
  font-weight: 600;
}

.time-input-green { border-color: var(--c-ok); }
.time-input-green:focus { border-color: var(--c-ok); box-shadow: 0 0 0 3px rgba(5,150,105,0.15); }

.modal-title-indigo { color: var(--c-primary); }
.modal-title-warn   { color: var(--c-warn); }

.form-input-warn { accent-color: var(--c-warn); }

.edit-image-preview { width: 100%; height: 180px; object-fit: cover; }
</style>

<!-- ==================== TAB SWITCHER ==================== -->
<?php if ($showRegularCheckin && $showMaCheckin): ?>
<div class="ck">
  <div class="ck-tab-strip">
    <button type="button" id="tabRegular" onclick="switchCheckinTab('regular')"
      class="tab-btn active">
      📸 เช็คอินทั่วไป
    </button>
    <button type="button" id="tabMa" onclick="switchCheckinTab('ma')"
      class="tab-btn ma">
      🔧 เช็คอิน MA
    </button>
  </div>
</div>
<?php endif; ?>

<!-- ==================== MAIN CONTENT ==================== -->
<div class="ck ck-grid animate__animated animate__fadeIn">

  <!-- ========== LEFT COLUMN: CHECK-IN PANELS ========== -->

  <!-- Panel Regular -->
  <div id="panelRegular" class="card <?php echo !$showRegularCheckin ? 'hidden' : ''; ?>">
    <div class="card-header indigo">
      <div class="card-header-icon">📸</div>
      <div class="card-header-text">
        <h2>เช็คอินเข้างาน</h2>
        <p>บันทึกเวลาเข้า-ออกงานพร้อมรูปถ่าย</p>
      </div>
    </div>
    <div class="card-body">
      <!-- Clock -->
      <div class="time-block">
        <div class="time-label">เวลาปัจจุบัน</div>
        <div id="currentTime" class="time-value">00:00:00</div>
      </div>
      <!-- Form -->
      <form id="checkinForm" enctype="multipart/form-data">
        <label for="checkin_image" class="photo-zone">
          <div id="uploadPrompt" class="photo-zone-inner">
            <div class="photo-zone-icon">
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
            </div>
            <div class="photo-zone-text">แตะเพื่อถ่ายรูป</div>
            <div class="photo-zone-subtext">หรือเลือกจากคลังภาพ</div>
          </div>
          <img id="imagePreview" class="photo-preview" style="display:none;" src="" alt="Preview">
          <input id="checkin_image" name="checkin_image" type="file" style="display:none;"
            accept="image/*" capture="environment" />
        </label>
        <div class="btn-row">
          <button type="submit" id="submitBtn" class="btn btn-checkin">
            ✅ เข้างาน
          </button>
          <button type="button" id="checkoutBtn" class="btn btn-checkout">
            🏁 เลิกงาน
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Panel MA -->
  <div id="panelMa" class="card <?php echo (!$showMaCheckin || ($showRegularCheckin && $showMaCheckin)) ? 'hidden' : ''; ?>">
    <div class="card-header violet">
      <div class="card-header-icon">🔧</div>
      <div class="card-header-text">
        <h2>เช็คอิน MA</h2>
        <p>สำหรับช่าง MA — มาสายจะบันทึกทันที</p>
      </div>
    </div>
    <div class="card-body">
      <!-- Clock -->
      <div class="time-block">
        <div class="time-label">เวลาปัจจุบัน</div>
        <div id="maCurrentTime" class="time-value violet">00:00:00</div>
        <div class="time-sublabel">
          เวลาเข้างาน MA: ไม่เกิน
          <span id="maDeadlineDisplay" style="font-weight:900;">--:--</span> น.
        </div>
      </div>

      <?php if ($canMaCheckin): ?>
      <form id="maCheckinForm" enctype="multipart/form-data">
        <label for="ma_checkin_image" class="photo-zone">
          <div id="maUploadPrompt" class="photo-zone-inner">
            <div class="photo-zone-icon violet-icon">
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
            </div>
            <div class="photo-zone-text violet">แตะเพื่อถ่ายรูป MA</div>
            <div class="photo-zone-subtext">หรือเลือกจากคลังภาพ</div>
          </div>
          <img id="maImagePreview" class="photo-preview" style="display:none;" src="" alt="Preview">
          <input id="ma_checkin_image" name="ma_checkin_image" type="file" style="display:none;"
            accept="image/*" capture="environment" />
        </label>
        <div class="btn-row">
          <button type="submit" id="maSubmitBtn" class="btn btn-checkin violet">
            ✅ เข้างาน MA
          </button>
          <button type="button" id="maCheckoutBtn" class="btn btn-checkout">
            🏁 เลิกงาน MA
          </button>
        </div>
      </form>
      <?php else: ?>
      <div class="no-role-state">
        <div class="no-role-icon">🔒</div>
        <div class="no-role-text">บัญชีนี้ไม่มีสิทธิ์ช่าง MA</div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ========== RIGHT COLUMN ========== -->
  <div class="ck-right-col">

    <!-- ========== STATS CARD ========== -->
    <div class="card">
      <div class="card-body">
        <div class="section-header" style="margin-bottom:14px;">
          <div class="section-title">
            <div class="icon">📊</div>
            สรุปการเข้างาน
          </div>
          <span id="dashLabel" class="dash-label-pill">–</span>
        </div>
        <div class="stats-grid">
          <div class="stat-card blue">
            <div class="stat-label">วันทั้งหมด</div>
            <div class="stat-value" id="dashTotal">0</div>
          </div>
          <div class="stat-card green">
            <div class="stat-label">ตรงเวลา</div>
            <div class="stat-value" id="dashOntime">0</div>
          </div>
          <div class="stat-card orange">
            <div class="stat-label">มาสาย</div>
            <div class="stat-value" id="dashLate">0</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ========== SETTINGS (Admin Only) ========== -->
    <?php if($isAdmin): ?>
    <div class="card">
      <div class="card-body">
        <div class="section-header">
          <div class="section-title">
            <div class="icon">⚙️</div>
            ตั้งค่าเวลาเข้างาน
          </div>
        </div>
        <p class="settings-desc">
          กำหนดเวลาที่ถือว่า "มาสาย" สามารถเลือกหลายบทบาทพร้อมกัน
        </p>

        <!-- Group 1 -->
        <div class="settings-group">
          <div class="settings-group-label">กลุ่มที่ 1 — กำหนดเวลาสาย</div>
          <div class="role-chips" id="roleGroup1">
            <div class="role-chip">
              <label><input type="checkbox" value="admin" class="role-cb-1">
              <span class="role-chip-label">แอดมิน</span></label>
            </div>
            <div class="role-chip">
              <label><input type="checkbox" value="super_admin" class="role-cb-1">
              <span class="role-chip-label">ซุปเปอร์แอดมิน</span></label>
            </div>
            <div class="role-chip">
              <label><input type="checkbox" value="technician" class="role-cb-1">
              <span class="role-chip-label">ช่าง</span></label>
            </div>
            <div class="role-chip">
              <label><input type="checkbox" value="sales" class="role-cb-1">
              <span class="role-chip-label">เซลส์</span></label>
            </div>
          </div>
          <div class="settings-time-row">
            <input type="time" id="lateTimeInput1" class="time-input">
            <button onclick="saveSettingsMulti(1)" class="btn-save indigo">บันทึก</button>
          </div>
        </div>

        <!-- Group 2 -->
        <div class="settings-group" style="margin-bottom:0;">
          <div class="settings-group-label">กลุ่มที่ 2 — กำหนดเวลาสาย</div>
          <div class="role-chips" id="roleGroup2">
            <div class="role-chip green">
              <label><input type="checkbox" value="admin" class="role-cb-2">
              <span class="role-chip-label">แอดมิน</span></label>
            </div>
            <div class="role-chip green">
              <label><input type="checkbox" value="super_admin" class="role-cb-2">
              <span class="role-chip-label">ซุปเปอร์แอดมิน</span></label>
            </div>
            <div class="role-chip green">
              <label><input type="checkbox" value="technician" class="role-cb-2">
              <span class="role-chip-label">ช่าง</span></label>
            </div>
            <div class="role-chip green">
              <label><input type="checkbox" value="sales" class="role-cb-2">
              <span class="role-chip-label">เซลส์</span></label>
            </div>
          </div>
          <div class="settings-time-row">
            <input type="time" id="lateTimeInput2" class="time-input time-input-green">
            <button onclick="saveSettingsMulti(2)" class="btn-save green">บันทึก</button>
          </div>
        </div>

      </div>
    </div>
    <?php endif; ?>

    <!-- ========== HISTORY TABLE ========== -->
    <div class="card">
      <div class="card-body">

        <!-- Header row -->
        <div class="section-header">
          <div class="section-title">
            <div class="icon">🕒</div>
            <span id="historyTitle">ประวัติเช็คอิน</span>
          </div>
          <!-- Tab: checkin / checkout -->
          <div class="hist-tab-strip">
            <button type="button" id="histTabCheckin" onclick="switchHistoryMode('checkin')"
              class="tab-btn active">เข้างาน</button>
            <button type="button" id="histTabCheckout" onclick="switchHistoryMode('checkout')"
              class="tab-btn">เลิกงาน</button>
          </div>
        </div>

        <!-- Filters -->
        <div class="filter-row">
          <input type="date" id="filterDate" class="filter-input">
          <span class="filter-sep">หรือ</span>
          <input type="month" id="filterMonth" class="filter-input">
          <button onclick="loadCheckinHistoryAndReset()" class="btn-filter search">🔍 ค้นหา</button>
          <?php if($isAdmin): ?>
          <button onclick="exportCheckin()" class="btn-filter excel">📥 Excel</button>
          <?php endif; ?>
        </div>

        <!-- Summary row -->
        <div id="histSummaryRow" class="hist-summary-row" style="display:none;">
          <span id="histSummaryText" class="hist-summary-text"></span>
          <span id="histPageInfo" class="hist-page-info"></span>
        </div>

        <!-- Table -->
        <div class="history-table-wrap">
          <table class="history-table">
            <thead>
              <tr>
                <th>วันที่ - เวลา</th>
                <th style="text-align:center;">รูปถ่าย</th>
                <th>พนักงาน</th>
                <th style="text-align:center;">สถานะ</th>
                <th style="text-align:center;">จัดการ</th>
              </tr>
            </thead>
            <tbody id="historyTableBody">
              <tr>
                <td colspan="5">
                  <div class="empty-state">
                    <div class="empty-state-icon">📋</div>
                    <div class="empty-state-text">ยังไม่มีข้อมูล กรุณาเลือกวันที่แล้วค้นหา</div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div id="paginationWrap" class="pagination-wrap" style="display:none;">
          <button id="pgPrev" class="pg-btn pg-prev" onclick="goHistoryPage(window._histPage - 1)">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
          </button>
          <div id="pgNumbers" class="pg-numbers"></div>
          <button id="pgNext" class="pg-btn pg-next" onclick="goHistoryPage(window._histPage + 1)">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
          </button>
        </div>

      </div>
    </div>

  </div><!-- end right column -->
</div><!-- end ck-grid -->


<!-- ==================== MODAL: แก้ไขรูปภาพ ==================== -->
<div id="editCheckinModal" class="modal-overlay" style="display:none;">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title modal-title-indigo">
        ✏️ แก้ไขรูปภาพเช็คอิน
      </div>
      <button onclick="closeEditCheckinModal()" class="modal-close">&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="edit_checkin_id">
      <div class="form-group">
        <div class="form-label">รูปภาพปัจจุบัน / อัปโหลดใหม่</div>
        <div class="edit-photo-zone" id="editImagePreviewWrapper">
          <img id="editImagePreview" class="edit-image-preview" style="display:none;" src="" alt="Preview">
          <div id="editImagePlaceholder" class="edit-photo-placeholder">
            <div style="font-size:28px; margin-bottom:8px;">🖼️</div>
            ไม่มีรูปภาพแนบ<br>
            <span style="font-size:11px; opacity:0.7;">หรือเลือกไฟล์ใหม่เพื่อแทนที่</span>
          </div>
        </div>
        <div class="edit-photo-actions">
          <button type="button" onclick="document.getElementById('edit_checkin_image').click()"
            class="btn-sm primary">📁 เลือกไฟล์รูป</button>
          <button type="button" id="deleteImageBtn" onclick="deleteCheckinImage()"
            class="btn-sm danger" style="display:none;">🗑️ ลบรูปภาพ</button>
        </div>
        <input type="file" id="edit_checkin_image" name="checkin_image" accept="image/*" style="display:none;">
      </div>
    </div>
    <div class="modal-footer">
      <button onclick="closeEditCheckinModal()" class="btn-sm neutral">ยกเลิก</button>
      <button onclick="saveEditCheckin()" class="btn-sm primary">💾 อัปเดตรูปภาพ</button>
    </div>
  </div>
</div>


<!-- ==================== MODAL: Admin Edit ==================== -->
<div id="adminEditModal" class="modal-overlay" style="display:none;">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title modal-title-warn">
        🔧 จัดการข้อมูลเช็คอิน (Admin)
      </div>
      <button onclick="closeAdminEditModal()" class="modal-close">&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="admin_edit_id">

      <div class="form-group">
        <div class="form-label">เวลาเข้างาน</div>
        <input type="datetime-local" id="admin_edit_checkin_time" step="1" class="form-input form-input-warn">
      </div>

      <div class="form-group">
        <div class="form-label">เวลาเลิกงาน</div>
        <input type="datetime-local" id="admin_edit_checkout_time" step="1" class="form-input">
        <div class="form-hint">ปล่อยว่างหากยังไม่ได้เลิกงาน</div>
      </div>

      <div class="form-group">
        <div class="form-label">ปรับสถานะบังคับ</div>
        <select id="admin_edit_status" class="form-input">
          <option value="">(คำนวณอัตโนมัติจากเวลา)</option>
          <option value="on_time">✅ มาตรงเวลา</option>
          <option value="late">⏰ มาสาย</option>
          <option value="leave">📝 ลา</option>
        </select>
        <div class="form-hint">สถานะที่เลือกที่นี่จะแทนที่การคำนวณจากเวลา</div>
      </div>
    </div>
    <div class="modal-footer">
      <button onclick="closeAdminEditModal()" class="btn-sm neutral">ยกเลิก</button>
      <button onclick="saveAdminEdit()" class="btn-sm amber">💾 บันทึกข้อมูล</button>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/common.js?v=<?= filemtime('assets/js/common.js') ?>"></script>
<script src="assets/js/checkin.js?v=<?= filemtime('assets/js/checkin.js') ?>"></script>

<script>
/* === Tab visual state sync (add to existing switchCheckinTab logic) === */
(function patchTabSwitcher() {
  const origSwitch = window.switchCheckinTab;
  if (typeof origSwitch !== 'function') return;
  window.switchCheckinTab = function(tab) {
    origSwitch(tab);
    document.querySelectorAll('.tab-btn').forEach(b => {
      b.classList.remove('active');
    });
    const activeBtn = tab === 'regular'
      ? document.getElementById('tabRegular')
      : document.getElementById('tabMa');
    if (activeBtn) activeBtn.classList.add('active');
  };
})();

/* === Modal helpers: replace hidden class with display:none toggling === */
function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = 'flex';
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.style.display = 'none';
}

/* === Patch existing modal openers if they use .hidden class === */
const _origOpenEdit = window.openEditCheckinModal;
if (typeof _origOpenEdit === 'function') {
  window.openEditCheckinModal = function(...args) {
    _origOpenEdit(...args);
    openModal('editCheckinModal');
  };
}

const _origCloseEdit = window.closeEditCheckinModal;
if (typeof _origCloseEdit === 'function') {
  window.closeEditCheckinModal = function() {
    _origCloseEdit();
    closeModal('editCheckinModal');
  };
} else {
  window.closeEditCheckinModal = function() {
    closeModal('editCheckinModal');
  };
}

const _origOpenAdmin = window.openAdminEditModal;
if (typeof _origOpenAdmin === 'function') {
  window.openAdminEditModal = function(...args) {
    _origOpenAdmin(...args);
    openModal('adminEditModal');
  };
}

const _origCloseAdmin = window.closeAdminEditModal;
if (typeof _origCloseAdmin === 'function') {
  window.closeAdminEditModal = function() {
    _origCloseAdmin();
    closeModal('adminEditModal');
  };
} else {
  window.closeAdminEditModal = function() {
    closeModal('adminEditModal');
  };
}

/* === Close modals on overlay click === */
document.addEventListener('click', function(e) {
  ['editCheckinModal', 'adminEditModal'].forEach(id => {
    const modal = document.getElementById(id);
    if (modal && e.target === modal) closeModal(id);
  });
});

/* ============================================================
   PAGINATION ENGINE
   ============================================================
   Strategy: intercept the data that checkin.js feeds into
   #historyTableBody, slice it into pages of ROWS_PER_PAGE,
   and render page-tab controls below the table.

   We patch window.loadCheckinHistory so our engine runs right
   after the original function populates the table, then slices
   the rendered rows into pages.
   ============================================================ */

(function initPagination() {

  const ROWS_PER_PAGE = 10;      // rows shown per page
  const MAX_VISIBLE  = 5;        // page buttons visible at once (excluding prev/next)

  /* --- state --- */
  window._histAllRows = [];      // NodeList clone of all <tr> in tbody
  window._histPage    = 1;       // current page (1-based)
  window._histTotal   = 0;       // total rows

  /* ----------------------------------------------------------
     renderPage(page) — show only the rows for that page
  ---------------------------------------------------------- */
  function renderPage(page) {
    const total   = window._histAllRows.length;
    const pages   = Math.ceil(total / ROWS_PER_PAGE) || 1;
    page          = Math.max(1, Math.min(page, pages));
    window._histPage = page;

    const tbody = document.getElementById('historyTableBody');
    if (!tbody) return;

    /* show / hide rows */
    const start = (page - 1) * ROWS_PER_PAGE;
    const end   = start + ROWS_PER_PAGE;

    tbody.innerHTML = '';
    const slice = window._histAllRows.slice(start, end);

    if (slice.length === 0) {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td colspan="5">
        <div class="empty-state">
          <div class="empty-state-icon">📋</div>
          <div class="empty-state-text">ไม่พบข้อมูลในหน้านี้</div>
        </div></td>`;
      tbody.appendChild(tr);
    } else {
      slice.forEach(tr => tbody.appendChild(tr.cloneNode(true)));
    }

    /* summary */
    const summaryRow = document.getElementById('histSummaryRow');
    const summaryTxt = document.getElementById('histSummaryText');
    const pageInfo   = document.getElementById('histPageInfo');
    if (total > 0) {
      summaryRow.style.display = 'flex';
      const realEnd = Math.min(end, total);
      summaryTxt.textContent  = `แสดง ${start + 1}–${realEnd} จาก ${total} รายการ`;
      pageInfo.textContent    = `หน้า ${page} / ${pages}`;
    } else {
      summaryRow.style.display = 'none';
    }

    /* pagination controls */
    renderPaginationControls(page, pages);
  }

  /* ----------------------------------------------------------
     renderPaginationControls(current, total)
  ---------------------------------------------------------- */
  function renderPaginationControls(current, pages) {
    const wrap    = document.getElementById('paginationWrap');
    const numbers = document.getElementById('pgNumbers');
    const pgPrev  = document.getElementById('pgPrev');
    const pgNext  = document.getElementById('pgNext');

    if (!wrap) return;

    if (pages <= 1) {
      wrap.style.display = 'none';
      return;
    }

    wrap.style.display = 'flex';
    pgPrev.disabled = (current === 1);
    pgNext.disabled = (current === pages);

    /* build page number buttons with smart ellipsis */
    numbers.innerHTML = '';

    const pageNums = getPageRange(current, pages, MAX_VISIBLE);

    pageNums.forEach(p => {
      const btn = document.createElement('button');
      btn.type = 'button';
      if (p === '...') {
        btn.className = 'pg-btn pg-ellipsis';
        btn.textContent = '···';
        btn.disabled = true;
      } else {
        btn.className = 'pg-btn' + (p === current ? ' active' : '');
        btn.textContent = p;
        btn.onclick = () => goHistoryPage(p);
      }
      numbers.appendChild(btn);
    });
  }

  /* ----------------------------------------------------------
     getPageRange — returns array like [1,'...',4,5,6,'...',20]
  ---------------------------------------------------------- */
  function getPageRange(current, total, maxVisible) {
    if (total <= maxVisible + 2) {
      return Array.from({ length: total }, (_, i) => i + 1);
    }

    const half  = Math.floor(maxVisible / 2);
    let start   = Math.max(2, current - half);
    let end     = Math.min(total - 1, current + half);

    if (current - half <= 2)  end   = Math.min(total - 1, 1 + maxVisible);
    if (current + half >= total - 1) start = Math.max(2, total - maxVisible);

    const range = [];
    range.push(1);
    if (start > 2)           range.push('...');
    for (let i = start; i <= end; i++) range.push(i);
    if (end < total - 1)     range.push('...');
    range.push(total);
    return range;
  }

  /* ----------------------------------------------------------
     goHistoryPage — public, called from HTML onclick
  ---------------------------------------------------------- */
  window.goHistoryPage = function(page) {
    renderPage(page);
    /* scroll table into view smoothly on mobile */
    const card = document.getElementById('historyTableBody');
    if (card) {
      const wrap = card.closest('.card');
      if (wrap) wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  };

  /* ----------------------------------------------------------
     captureRows — call right after tbody is populated
  ---------------------------------------------------------- */
  function captureRows() {
    const tbody = document.getElementById('historyTableBody');
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('tr'));
    /* filter out the empty-state placeholder */
    window._histAllRows = rows.filter(tr => !tr.querySelector('.empty-state'));
    window._histTotal   = window._histAllRows.length;
    renderPage(1);
  }

  /* ----------------------------------------------------------
     Patch window.loadCheckinHistory
     Run captureRows after the original call settles.
     We watch for DOM mutations on tbody as a reliable trigger.
  ---------------------------------------------------------- */
  function hookTableBody() {
    const tbody = document.getElementById('historyTableBody');
    if (!tbody) return;

    let debounce = null;
    const observer = new MutationObserver(() => {
      clearTimeout(debounce);
      debounce = setTimeout(() => {
        /* Only re-capture if NOT already paginated
           (i.e. the mutation came from checkin.js, not from us) */
        if (window._paginationRendering) return;
        captureRows();
      }, 60);
    });

    observer.observe(tbody, { childList: true, subtree: true });
  }

  /* ----------------------------------------------------------
     loadCheckinHistoryAndReset — called by our Search button
  ---------------------------------------------------------- */
  window.loadCheckinHistoryAndReset = function() {
    window._histAllRows = [];
    window._histPage    = 1;
    const summaryRow = document.getElementById('histSummaryRow');
    const paginationWrap = document.getElementById('paginationWrap');
    if (summaryRow) summaryRow.style.display = 'none';
    if (paginationWrap) paginationWrap.style.display = 'none';

    if (typeof window.loadCheckinHistory === 'function') {
      window.loadCheckinHistory();
    }
  };

  /* flag used to prevent re-entrant mutation triggers */
  const _origRenderPage = renderPage;
  window._paginationRendering = false;
  window._renderHistPage = function(page) {
    window._paginationRendering = true;
    _origRenderPage(page);
    window._paginationRendering = false;
  };
  window.goHistoryPage = function(page) {
    window._renderHistPage(page);
    const tbody = document.getElementById('historyTableBody');
    if (tbody) {
      const wrap = tbody.closest('.card');
      if (wrap) wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  };

  /* Start observing after DOM ready */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', hookTableBody);
  } else {
    hookTableBody();
  }

})();
</script>