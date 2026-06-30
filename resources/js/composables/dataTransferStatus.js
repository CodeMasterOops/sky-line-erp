export const TERMINAL_STATUSES = [
    'completed',
    'completed_with_errors',
    'failed',
    'cancelled',
    'rolled_back',
];

export const ACTIVE_STATUSES = ['uploaded', 'parsing', 'parsed', 'mapped', 'validating', 'processing'];

export const isTerminalStatus = (status) => TERMINAL_STATUSES.includes(status);

export const isActiveStatus = (status) => ACTIVE_STATUSES.includes(status);

/**
 * Decide whether a running poll should stop. A poll always stops on a terminal
 * status (so failures never loop forever) or once the caller's target matcher
 * is satisfied.
 */
export const pollShouldStop = (job, matches) => {
    if (!job) {
        return false;
    }
    if (isTerminalStatus(job.status)) {
        return true;
    }
    return typeof matches === 'function' ? !!matches(job) : false;
};
