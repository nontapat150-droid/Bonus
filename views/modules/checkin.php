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
/* ==================== CSS VARIABLES ==================== */
:root {
  --primary: #4F46E5;
  --primary-light: #6366F1;
  --primary-dark: #3730A3;
  --primary-bg: #EEF2FF;
  --ma-primary: #7C3AED;
  --ma-light: #8B5CF6;
  --ma-bg: #F5F3FF;
  --success: #059669;
  --success-bg: #ECFDF5;
  --danger: #E11D48;
  --danger-bg: #FFF1F2;
  --warning: #D97706;
  --warning-bg: #FFFBEB;
  --info: #0284C7;
  --info-bg: #F0F9FF;
  --surface: #FFFFFF;
  --surface-2: #F8FAFC;
  --surface-3: #F1F5F9;
  --border: #E2E8F0;
  --border-soft: #F1F5F9;
  --text-primary: #0F172A;
  --text-secondary: #475569;
  --text-muted: #94A3B8;
  --radius-sm: 10px;
  --radius-md: 16px;
  --radius-lg: 20px;
  --radius-xl: 28px;
  --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
  --shadow-md: 0 4px 12px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.04);
  --shadow-lg: 0 10px 30px rgba(0,0,0,0.10), 0 4px 10px rgba(0,0,0,0.06);
  --shadow-xl: 0 20px 50px rgba(0,0,0,0.12), 0 8px 20px rgba(0,0,0,0.06);
  --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ==================== GLOBAL ==================== */
.checkin-module * {
  box-sizing: border-box;
  -webkit-tap-highlight-color: transparent;
}

.checkin-module {
  font-family: 'Sarabun', system-ui, -apple-system, sans-serif;
  color: var(--text-primary);
  padding: 0;
}

/* ==================== TAB SWITCHER ==================== */
.tab-switcher {
  display: flex;
  background: var(--surface-3);
  border-radius: var(--radius-md);
  padding: 4px;
  max-width: 380px;
  margin-bottom: 20px;
  box-shadow: inset 0 1px 3px rgba(0,0,0,0.06);
}

.tab-btn {
  flex: 1;
  padding: 10px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: var(--transition);
  background: transparent;
  color: var(--text-secondary);
  white-space: nowrap;
  letter-spacing: -0.01em;
}

.tab-btn.active {
  background: var(--surface);
  color: var(--primary);
  box-shadow: var(--shadow-sm);
}

.tab-btn.active.ma {
  color: var(--ma-primary);
}

/* ==================== MAIN GRID ==================== */
.checkin-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
}

@media (min-width: 1024px) {
  .checkin-grid {
    grid-template-columns: 380px 1fr;
    gap: 20px;
    align-items: start;
  }
}

/* ==================== CARD ==================== */
.card {
  background: var(--surface);
  border-radius: var(--radius-xl);
  box-shadow: var(--shadow-md);
  border: 1px solid var(--border-soft);
  overflow: hidden;
}

.card-header {
  padding: 20px 24px;
  display: flex;
  align-items: center;
  gap: 12px;
}

.card-header.indigo {
  background: linear-gradient(135deg, var(--primary) 0%, #6366F1 100%);
}

.card-header.violet {
  background: linear-gradient(135deg, var(--ma-primary) 0%, #8B5CF6 100%);
}

.card-header-icon {
  width: 44px;
  height: 44px;
  background: rgba(255,255,255,0.2);
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 22px;
  flex-shrink: 0;
}

.card-header-text h2 {
  font-size: 17px;
  font-weight: 800;
  color: #fff;
  margin: 0;
  line-height: 1.2;
  letter-spacing: -0.02em;
}

.card-header-text p {
  font-size: 12px;
  color: rgba(255,255,255,0.72);
  margin: 3px 0 0;
  font-weight: 600;
}

.card-body {
  padding: 20px 24px;
}

/* ==================== TIME DISPLAY ==================== */
.time-block {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 16px 20px;
  text-align: center;
  margin-bottom: 16px;
}

.time-label {
  font-size: 11px;
  font-weight: 700;
  color: var(--text-muted);
  letter-spacing: 0.08em;
  text-transform: uppercase;
  margin-bottom: 4px;
}

.time-value {
  font-size: 38px;
  font-weight: 900;
  color: var(--primary);
  letter-spacing: -0.04em;
  line-height: 1;
  font-variant-numeric: tabular-nums;
}

.time-value.violet {
  color: var(--ma-primary);
}

.time-sublabel {
  font-size: 11px;
  font-weight: 600;
  color: #7C3AED;
  margin-top: 6px;
}

.time-sublabel span {
  font-weight: 800;
}

/* ==================== PHOTO UPLOAD ZONE ==================== */
.photo-zone {
  border: 2px dashed var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface-2);
  cursor: pointer;
  position: relative;
  overflow: hidden;
  transition: var(--transition);
  height: 148px;
  display: block;
}

.photo-zone:hover {
  border-color: var(--primary);
  background: var(--primary-bg);
}

.photo-zone.violet:hover {
  border-color: var(--ma-light);
  background: var(--ma-bg);
}

.photo-zone-inner {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  pointer-events: none;
}

.photo-zone-icon {
  width: 44px;
  height: 44px;
  background: var(--primary-bg);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: var(--transition);
}

.photo-zone:hover .photo-zone-icon {
  transform: scale(1.1);
}

.photo-zone-icon svg {
  width: 22px;
  height: 22px;
  color: var(--primary);
}

.photo-zone-icon.violet-icon {
  background: var(--ma-bg);
}

.photo-zone-icon.violet-icon svg {
  color: var(--ma-light);
}

.photo-zone-text {
  font-size: 13px;
  font-weight: 700;
  color: var(--primary);
}

.photo-zone-text.violet {
  color: var(--ma-light);
}

.photo-zone-subtext {
  font-size: 11px;
  color: var(--text-muted);
}

.photo-preview {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* ==================== ACTION BUTTONS ==================== */
.btn-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-top: 14px;
}

.btn {
  padding: 13px 16px;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 800;
  cursor: pointer;
  transition: var(--transition);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  letter-spacing: -0.01em;
  position: relative;
  overflow: hidden;
}

.btn:active {
  transform: scale(0.96);
}

.btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
  transform: none !important;
}

.btn-checkin {
  background: var(--primary);
  color: #fff;
  box-shadow: 0 4px 14px rgba(79,70,229,0.35);
}

.btn-checkin:hover:not(:disabled) {
  background: var(--primary-dark);
  box-shadow: 0 6px 20px rgba(79,70,229,0.45);
}

.btn-checkin.violet {
  background: var(--ma-primary);
  box-shadow: 0 4px 14px rgba(124,58,237,0.35);
}

.btn-checkin.violet:hover:not(:disabled) {
  background: #6D28D9;
  box-shadow: 0 6px 20px rgba(124,58,237,0.45);
}

.btn-checkout {
  background: var(--danger-bg);
  color: var(--danger);
  border: 1.5px solid #FECDD3;
}

.btn-checkout:hover:not(:disabled) {
  background: var(--danger);
  color: #fff;
  border-color: var(--danger);
  box-shadow: 0 4px 14px rgba(225,29,72,0.30);
}

/* ==================== STATS CARDS ==================== */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

.stat-card {
  background: var(--surface-2);
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  padding: 14px 12px;
  text-align: center;
}

.stat-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  margin-bottom: 4px;
}

.stat-value {
  font-size: 32px;
  font-weight: 900;
  letter-spacing: -0.04em;
  line-height: 1.1;
}

.stat-card.blue { background: var(--info-bg); border-color: #BAE6FD; }
.stat-card.blue .stat-label { color: var(--info); }
.stat-card.blue .stat-value { color: #0C4A6E; }

.stat-card.green { background: var(--success-bg); border-color: #A7F3D0; }
.stat-card.green .stat-label { color: var(--success); }
.stat-card.green .stat-value { color: #064E3B; }

.stat-card.orange { background: var(--warning-bg); border-color: #FDE68A; }
.stat-card.orange .stat-label { color: var(--warning); }
.stat-card.orange .stat-value { color: #78350F; }

/* ==================== SECTION HEADER ==================== */
.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 16px;
}

.section-title {
  font-size: 16px;
  font-weight: 800;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 8px;
  letter-spacing: -0.01em;
}

.section-title .icon {
  width: 32px;
  height: 32px;
  background: var(--primary-bg);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
}

/* ==================== SETTINGS PANEL ==================== */
.settings-group {
  padding: 16px;
  background: var(--surface-2);
  border-radius: var(--radius-md);
  border: 1px solid var(--border);
  margin-bottom: 12px;
}

.settings-group-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-muted);
  margin-bottom: 10px;
}

.role-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 12px;
}

.role-chip label {
  cursor: pointer;
}

.role-chip input { display: none; }

.role-chip-label {
  display: inline-block;
  padding: 6px 14px;
  border-radius: 99px;
  border: 1.5px solid var(--border);
  font-size: 13px;
  font-weight: 700;
  color: var(--text-secondary);
  background: var(--surface);
  transition: var(--transition);
  user-select: none;
  white-space: nowrap;
}

.role-chip input:checked + .role-chip-label {
  background: var(--primary);
  color: #fff;
  border-color: var(--primary);
}

.role-chip.green input:checked + .role-chip-label {
  background: var(--success);
  border-color: var(--success);
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
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  font-size: 15px;
  font-weight: 700;
  color: var(--text-primary);
  background: var(--surface);
  outline: none;
  transition: var(--transition);
  font-variant-numeric: tabular-nums;
}

.time-input:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
}

.btn-save {
  padding: 10px 20px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 800;
  cursor: pointer;
  transition: var(--transition);
  white-space: nowrap;
}

.btn-save.indigo {
  background: var(--primary);
  color: #fff;
  box-shadow: 0 2px 8px rgba(79,70,229,0.25);
}

.btn-save.indigo:hover { background: var(--primary-dark); }

.btn-save.green {
  background: var(--success);
  color: #fff;
  box-shadow: 0 2px 8px rgba(5,150,105,0.25);
}

.btn-save.green:hover { background: #047857; }

/* ==================== FILTER ROW ==================== */
.filter-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.filter-input {
  padding: 8px 12px;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  font-size: 13px;
  color: var(--text-primary);
  outline: none;
  transition: var(--transition);
  background: var(--surface);
}

.filter-input:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(79,70,229,0.10);
}

.btn-filter {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: var(--transition);
}

.btn-filter.search {
  background: var(--primary-bg);
  color: var(--primary);
}

.btn-filter.search:hover { background: #C7D2FE; }

.btn-filter.excel {
  background: var(--success-bg);
  color: var(--success);
  border: 1.5px solid #A7F3D0;
}

.btn-filter.excel:hover { background: #D1FAE5; }

.filter-sep {
  font-size: 12px;
  color: var(--text-muted);
  font-weight: 600;
  display: none;
}

@media (min-width: 540px) { .filter-sep { display: inline; } }

/* ==================== HISTORY TABLE ==================== */
.history-table-wrap {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  margin: 0 -4px;
}

.history-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  min-width: 480px;
}

.history-table thead th {
  padding: 10px 14px;
  font-size: 11px;
  font-weight: 800;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  background: var(--surface-3);
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
}

.history-table thead th:first-child { border-radius: var(--radius-sm) 0 0 0; }
.history-table thead th:last-child { border-radius: 0 var(--radius-sm) 0 0; }

.history-table tbody tr {
  transition: background 0.15s;
}

.history-table tbody tr:hover { background: var(--surface-2); }

.history-table tbody td {
  padding: 11px 14px;
  font-size: 13px;
  border-bottom: 1px solid var(--border-soft);
  vertical-align: middle;
  color: var(--text-secondary);
}

/* ==================== BADGE ==================== */
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

.badge.on-time { background: var(--success-bg); color: var(--success); }
.badge.late { background: var(--danger-bg); color: var(--danger); }
.badge.leave { background: var(--warning-bg); color: var(--warning); }
.badge.default { background: var(--surface-3); color: var(--text-muted); }

/* ==================== ACTION ICONS ==================== */
.action-btn {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: var(--transition);
  font-size: 15px;
  background: transparent;
}

.action-btn.edit { color: var(--primary); }
.action-btn.edit:hover { background: var(--primary-bg); }

.action-btn.admin { color: var(--warning); }
.action-btn.admin:hover { background: var(--warning-bg); }

.action-btn.delete { color: var(--danger); }
.action-btn.delete:hover { background: var(--danger-bg); }

/* ==================== MODAL ==================== */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15,23,42,0.55);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  animation: fadeIn 0.18s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.modal-box {
  background: var(--surface);
  border-radius: var(--radius-xl);
  width: 100%;
  max-width: 420px;
  overflow: hidden;
  box-shadow: var(--shadow-xl);
  animation: slideUp 0.22s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(20px) scale(0.97); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

.modal-header {
  padding: 18px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--border-soft);
}

.modal-title {
  font-size: 16px;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 8px;
  letter-spacing: -0.01em;
}

.modal-close {
  width: 32px;
  height: 32px;
  border: none;
  background: var(--surface-3);
  border-radius: 8px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: var(--text-muted);
  transition: var(--transition);
  line-height: 1;
}

.modal-close:hover { background: var(--danger-bg); color: var(--danger); }

.modal-body { padding: 20px; }

.modal-footer {
  padding: 14px 20px;
  background: var(--surface-2);
  border-top: 1px solid var(--border-soft);
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

/* ==================== FORM FIELDS ==================== */
.form-group { margin-bottom: 16px; }
.form-group:last-child { margin-bottom: 0; }

.form-label {
  display: block;
  font-size: 13px;
  font-weight: 700;
  color: var(--text-secondary);
  margin-bottom: 6px;
}

.form-input {
  width: 100%;
  padding: 10px 14px;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  font-size: 14px;
  color: var(--text-primary);
  background: var(--surface);
  outline: none;
  transition: var(--transition);
  appearance: none;
  -webkit-appearance: none;
}

.form-input:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(79,70,229,0.10);
}

.form-hint {
  font-size: 11px;
  color: var(--text-muted);
  margin-top: 4px;
}

/* ==================== EDIT IMAGE UPLOAD ==================== */
.edit-photo-zone {
  border-radius: var(--radius-md);
  overflow: hidden;
  border: 1px solid var(--border);
  background: var(--surface-3);
  min-height: 160px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.edit-photo-placeholder {
  text-align: center;
  padding: 20px;
  color: var(--text-muted);
  font-size: 13px;
  font-weight: 600;
}

.edit-photo-actions {
  display: flex;
  gap: 8px;
  margin-top: 12px;
  flex-wrap: wrap;
}

.btn-sm {
  padding: 8px 16px;
  border: none;
  border-radius: var(--radius-sm);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: var(--transition);
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.btn-sm.primary {
  background: var(--primary);
  color: #fff;
}

.btn-sm.primary:hover { background: var(--primary-dark); }

.btn-sm.danger {
  background: var(--danger-bg);
  color: var(--danger);
  border: 1px solid #FECDD3;
}

.btn-sm.danger:hover { background: var(--danger); color: #fff; border-color: var(--danger); }

.btn-sm.neutral {
  background: var(--surface-3);
  color: var(--text-secondary);
  border: 1px solid var(--border);
}

.btn-sm.neutral:hover { background: var(--surface-3); color: var(--text-primary); }

.btn-sm.amber {
  background: #FEF3C7;
  color: #92400E;
}

.btn-sm.amber:hover { background: #FDE68A; }

/* ==================== EMPTY STATE ==================== */
.empty-state {
  text-align: center;
  padding: 40px 20px;
  color: var(--text-muted);
}

.empty-state-icon {
  font-size: 36px;
  margin-bottom: 10px;
  opacity: 0.5;
}

.empty-state-text {
  font-size: 14px;
  font-weight: 600;
}

/* ==================== HISTORY SUMMARY ROW ==================== */
.hist-summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px;
  padding: 8px 12px;
  background: var(--surface-2);
  border-radius: var(--radius-sm);
  border: 1px solid var(--border);
  margin-bottom: 12px;
}

.hist-summary-text {
  font-size: 12px;
  font-weight: 700;
  color: var(--text-secondary);
}

.hist-page-info {
  font-size: 12px;
  font-weight: 700;
  color: var(--text-muted);
}

/* ==================== PAGINATION ==================== */
.pagination-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding-top: 18px;
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
  width: 36px;
  height: 36px;
  border: 1.5px solid var(--border);
  border-radius: var(--radius-sm);
  background: var(--surface);
  color: var(--text-secondary);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: var(--transition);
  font-size: 13px;
  font-weight: 700;
  flex-shrink: 0;
}

.pg-btn:hover:not(:disabled) {
  border-color: var(--primary);
  color: var(--primary);
  background: var(--primary-bg);
}

.pg-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.pg-btn.active {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
  box-shadow: 0 2px 8px rgba(79,70,229,0.30);
}

.pg-btn.pg-ellipsis {
  border-color: transparent;
  background: transparent;
  cursor: default;
  color: var(--text-muted);
  font-size: 15px;
  width: 28px;
}

.pg-btn.pg-ellipsis:hover {
  background: transparent;
  border-color: transparent;
  color: var(--text-muted);
}

@media (max-width: 400px) {
  .pg-btn { width: 32px; height: 32px; font-size: 12px; }
  .pagination-wrap { gap: 4px; }
  .pg-numbers { gap: 3px; }
}

/* ==================== NO MA ROLE ==================== */
.no-role-state {
  text-align: center;
  padding: 32px 20px;
}

.no-role-icon {
  width: 52px;
  height: 52px;
  background: var(--surface-3);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 24px;
  margin: 0 auto 12px;
}

.no-role-text {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-muted);
}

/* ==================== RESPONSIVE ADJUSTMENTS ==================== */
@media (max-width: 480px) {
  .card-body { padding: 16px; }
  .card-header { padding: 16px 18px; }
  .time-value { font-size: 32px; }
  .stats-grid { gap: 8px; }
  .stat-value { font-size: 26px; }
}
</style>

<!-- ==================== TAB SWITCHER ==================== -->
<?php if ($showRegularCheckin && $showMaCheckin): ?>
<div class="checkin-module">
  <div class="tab-switcher">
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
<div class="checkin-module checkin-grid animate__animated animate__fadeIn">

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
  <div style="display:flex; flex-direction:column; gap:16px;">

    <!-- ========== STATS CARD ========== -->
    <div class="card">
      <div class="card-body">
        <div class="section-header" style="margin-bottom:14px;">
          <div class="section-title">
            <div class="icon">📊</div>
            สรุปการเข้างาน
          </div>
          <span id="dashLabel"
            style="font-size:12px; font-weight:700; color:var(--primary);
                   background:var(--primary-bg); padding:4px 12px; border-radius:99px;">–</span>
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
        <p style="font-size:12px; color:var(--text-muted); margin:0 0 14px; font-weight:600;">
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
            <input type="time" id="lateTimeInput2" class="time-input" style="border-color:var(--success);">
            <button onclick="saveSettingsMulti(2)" class="btn-save green">บันทึก</button>
          </div>
        </div>

      </div>
    </div>
    <?php endif; ?>

    <!-- ========== HISTORY TABLE ========== -->
    <div class="card" style="flex:1;">
      <div class="card-body">

        <!-- Header row -->
        <div class="section-header" style="margin-bottom:14px;">
          <div class="section-title">
            <div class="icon">🕒</div>
            <span id="historyTitle">ประวัติเช็คอิน</span>
          </div>
          <!-- Tab: checkin / checkout -->
          <div class="tab-switcher" style="margin-bottom:0; max-width:220px;">
            <button type="button" id="histTabCheckin" onclick="switchHistoryMode('checkin')"
              class="tab-btn active" style="font-size:13px; padding:7px 12px;">เข้างาน</button>
            <button type="button" id="histTabCheckout" onclick="switchHistoryMode('checkout')"
              class="tab-btn" style="font-size:13px; padding:7px 12px;">เลิกงาน</button>
          </div>
        </div>

        <!-- Filters -->
        <div class="filter-row" style="margin-bottom:16px;">
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
</div><!-- end checkin-grid -->


<!-- ==================== MODAL: แก้ไขรูปภาพ ==================== -->
<div id="editCheckinModal" class="modal-overlay" style="display:none;">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title" style="color:var(--primary);">
        ✏️ แก้ไขรูปภาพเช็คอิน
      </div>
      <button onclick="closeEditCheckinModal()" class="modal-close">&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="edit_checkin_id">
      <div class="form-group">
        <div class="form-label">รูปภาพปัจจุบัน / อัปโหลดใหม่</div>
        <div class="edit-photo-zone" id="editImagePreviewWrapper">
          <img id="editImagePreview" style="width:100%; height:180px; object-fit:cover; display:none;" src="" alt="Preview">
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
      <div class="modal-title" style="color:var(--warning);">
        🔧 จัดการข้อมูลเช็คอิน (Admin)
      </div>
      <button onclick="closeAdminEditModal()" class="modal-close">&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="admin_edit_id">

      <div class="form-group">
        <div class="form-label">เวลาเข้างาน</div>
        <input type="datetime-local" id="admin_edit_checkin_time" step="1" class="form-input"
          style="accent-color:var(--warning);">
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