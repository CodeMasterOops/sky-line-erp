/**
 * Ensure Bootstrap dropdowns and tooltips work after Vue mounts or route changes.
 * The data-api listens on document, but explicit instances fix first-visit timing issues in SPAs.
 */
export function initBootstrapUi(container) {
    const root = container ?? (typeof document !== 'undefined' ? document : null);

    if (!root) {
        return;
    }

    const bootstrap = window.bootstrap;

    if (!bootstrap?.Dropdown || !bootstrap?.Tooltip) {
        return;
    }

    root.querySelectorAll('[data-bs-toggle="dropdown"]').forEach((element) => {
        bootstrap.Dropdown.getOrCreateInstance(element);
    });

    root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
        bootstrap.Tooltip.getOrCreateInstance(element);
    });
}
