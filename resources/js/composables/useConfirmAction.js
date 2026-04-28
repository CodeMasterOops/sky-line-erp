import Swal from 'sweetalert2';
import { toast } from '@/helpers/toast';
import showErrors from '@/helpers/showErrors';

/**
 * Reusable confirm-action composable.
 *
 * Wraps SweetAlert2 + toast + error handling for any destructive or
 * irreversible action (delete, approve, convert, etc.).
 *
 * Usage:
 *   const { confirmDelete, confirmAction } = useConfirmAction();
 *
 *   // Delete shorthand (pre-configured wording + red button):
 *   confirmDelete(() => store.deleteItem(id));
 *
 *   // Generic confirm (custom title, icon, button colour):
 *   confirmAction({
 *     title: 'Approve Sales Order?',
 *     text: 'This will mark the order as approved.',
 *     icon: 'question',
 *     confirmButtonColor: 'green',
 *     confirmButtonText: 'Approve',
 *     action: () => store.approveOrder(id),
 *   });
 */
export function useConfirmAction() {
    async function confirmAction({
        title,
        text,
        icon = 'warning',
        confirmButtonColor = 'red',
        confirmButtonText = 'Yes',
        action,
        onSuccess,
    } = {}) {
        const result = await Swal.fire({
            title,
            text,
            icon,
            showCancelButton: true,
            confirmButtonColor,
            confirmButtonText,
        });

        if (!result.isConfirmed) return;

        try {
            const res = await action();
            toast(res.status, res.data.message);
            onSuccess?.();
        } catch (e) {
            showErrors(e);
        }
    }

    function confirmDelete(action, onSuccess) {
        return confirmAction({
            title: 'Are you sure you want to delete?',
            text: 'This record will be permanently removed.',
            icon: 'warning',
            confirmButtonColor: 'red',
            confirmButtonText: 'Delete',
            action,
            onSuccess,
        });
    }

    return { confirmAction, confirmDelete };
}
