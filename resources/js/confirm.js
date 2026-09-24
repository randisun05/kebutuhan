import Swal from 'sweetalert2';
import { router } from '@inertiajs/vue3';

export const destroy = async (url, title = 'Hapus data ini?') => {
    const res = await Swal.fire({
        icon: 'warning', title, text: 'Data yang dihapus tidak dapat dikembalikan.',
        showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Ya, hapus', cancelButtonText: 'Batal',
    });
    if (res.isConfirmed) router.delete(url, { preserveScroll: true });
};
