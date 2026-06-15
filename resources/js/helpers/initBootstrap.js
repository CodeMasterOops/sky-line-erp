/**
 * Ensure Bootstrap dropdowns and tooltips work after Vue mounts or route changes.
 * The data-api listens on document, but explicit instances fix first-visit timing issues in SPAs.
 * window.bootstrap is set from the bundle in app.js — do not rely on the ESM package here.
 */
export function initBootstrapUi(container) {
    const root = container ?? (typeof document !== 'undefined' ? document : null);

    if (!root) {
        return;
    }

    const { Dropdown, Tooltip } = window.bootstrap ?? {};

    if (!Dropdown || !Tooltip) {
        return;
    }

    root.querySelectorAll('[data-bs-toggle="dropdown"]').forEach((element) => {
        Dropdown.getOrCreateInstance(element);
    });

    root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
        Tooltip.getOrCreateInstance(element);
    });
}
