// assets/js/common.js

// สร้างระบบแจ้งเตือน (Toast) แบบใหม่ที่ป้องกันการ Error (Safeguard)
window.Toast = {
    success: function(msg) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: msg, showConfirmButton: false, timer: 3000, timerProgressBar: true });
        } else {
            console.log('SUCCESS: ' + msg);
            alert(msg);
        }
    },
    error: function(msg) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: msg, showConfirmButton: false, timer: 3000, timerProgressBar: true });
        } else {
            console.error('ERROR: ' + msg);
            alert(msg);
        }
    },
    info: function(msg) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: msg, showConfirmButton: false, timer: 3000, timerProgressBar: true });
        } else {
            console.info('INFO: ' + msg);
        }
    },
    warning: function(msg) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ toast: true, position: 'top-end', icon: 'warning', title: msg, showConfirmButton: false, timer: 3000, timerProgressBar: true });
        } else {
            console.warn('WARNING: ' + msg);
        }
    }
};

// สร้างระบบ Loading
window.Loader = {
    show: function() {
        if (typeof Swal !== 'undefined') {
            Swal.fire({ title: 'กำลังประมวลผล...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        }
    },
    hide: function() {
        if (typeof Swal !== 'undefined') {
            Swal.close();
        }
    }
};