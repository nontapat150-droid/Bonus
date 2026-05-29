// assets/js/common.js

if (typeof Swal === 'undefined') {
    console.error('SweetAlert2 is required for common UI utilities. Please include SweetAlert2 before common.js.');
}

window.Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
    }
});

Toast.success = (msg) => Toast.fire({ icon: 'success', title: msg });
Toast.error = (msg) => Toast.fire({ icon: 'error', title: msg });
Toast.info = (msg) => Toast.fire({ icon: 'info', title: msg });
Toast.warning = (msg) => Toast.fire({ icon: 'warning', title: msg });

window.Loader = {
    show: () => Swal.fire({ title: 'กำลังประมวลผล...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } }),
    hide: () => Swal.close()
};
