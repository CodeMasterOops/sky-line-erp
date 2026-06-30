import { onUnmounted, ref } from 'vue';
import { useDataTransferStore } from '@/stores/admin/data-transfer.js';
import {
    TERMINAL_STATUSES,
    isActiveStatus,
    isTerminalStatus,
    pollShouldStop,
} from '@/composables/dataTransferStatus.js';

export {
    ACTIVE_STATUSES,
    TERMINAL_STATUSES,
    isActiveStatus,
    isTerminalStatus,
    pollShouldStop,
} from '@/composables/dataTransferStatus.js';

export function useDataTransferJob(uuidRef) {
    const store = useDataTransferStore();
    const polling = ref(false);
    let intervalId = null;

    const fetchJob = async () => {
        const uuid = typeof uuidRef === 'function' ? uuidRef() : uuidRef?.value ?? uuidRef;
        if (!uuid) return null;
        return store.getJob(uuid);
    };

    const stopPolling = () => {
        polling.value = false;
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
        }
    };

    /**
     * Run a single managed poll. Only one poll runs at a time; calling again
     * cancels the previous one. The latest job is handed to `onUpdate` every
     * tick so the caller can keep its view in sync with the backend.
     */
    const pollUntil = (matches, { onUpdate = null, ms = 2000 } = {}) => {
        stopPolling();
        polling.value = true;

        return new Promise((resolve) => {
            const tick = async () => {
                const job = await fetchJob();
                if (onUpdate) {
                    onUpdate(job);
                }
                if (pollShouldStop(job, matches)) {
                    stopPolling();
                    resolve(job);
                }
            };

            intervalId = setInterval(tick, ms);
        });
    };

    onUnmounted(stopPolling);

    return {
        store,
        polling,
        fetchJob,
        pollUntil,
        stopPolling,
        isActive: isActiveStatus,
        isTerminal: isTerminalStatus,
        TERMINAL_STATUSES,
    };
}
