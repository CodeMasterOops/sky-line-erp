import { onUnmounted, ref } from 'vue';
import { useDataTransferStore } from '@/stores/admin/data-transfer.js';

const TERMINAL_STATUSES = [
    'completed',
    'completed_with_errors',
    'failed',
    'cancelled',
    'rolled_back',
];

const ACTIVE_STATUSES = ['uploaded', 'parsing', 'parsed', 'mapped', 'validating', 'processing'];

export function useDataTransferJob(uuidRef) {
    const store = useDataTransferStore();
    const polling = ref(false);
    let intervalId = null;

    const fetchJob = async () => {
        const uuid = typeof uuidRef === 'function' ? uuidRef() : uuidRef?.value ?? uuidRef;
        if (!uuid) return null;
        return store.getJob(uuid);
    };

    const startPolling = (ms = 2000) => {
        if (intervalId) return;
        polling.value = true;
        intervalId = setInterval(async () => {
            const job = await fetchJob();
            if (!job || TERMINAL_STATUSES.includes(job.status)) {
                stopPolling();
            }
        }, ms);
    };

    const stopPolling = () => {
        polling.value = false;
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
        }
    };

    onUnmounted(stopPolling);

    const isActive = (status) => ACTIVE_STATUSES.includes(status);

    return {
        store,
        polling,
        fetchJob,
        startPolling,
        stopPolling,
        isActive,
        TERMINAL_STATUSES,
    };
}
