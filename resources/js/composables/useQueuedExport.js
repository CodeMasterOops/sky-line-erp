import { ref } from 'vue';
import { useToast } from 'vue-toastification';
import { useDataTransferStore } from '@/stores/admin/data-transfer.js';

/**
 * Queue a server-side data export via the data-transfer API.
 */
export function useQueuedExport() {
    const store = useDataTransferStore();
    const toast = useToast();
    const exporting = ref(false);

    const runExport = async (entityType, getFilters = () => ({}), format = 'csv') => {
        if (!entityType || exporting.value) {
            return;
        }

        exporting.value = true;
        try {
            const raw = typeof getFilters === 'function' ? getFilters() : (getFilters ?? {});
            const filters = Object.fromEntries(
                Object.entries(raw).filter(([, v]) => v !== '' && v !== null && v !== undefined),
            );

            await store.queueExport(entityType, format, filters);
            toast.info('Export queued. Open Settings → Company & profile → Data transfer when ready.');
        } finally {
            exporting.value = false;
        }
    };

    return { exporting, runExport };
}
