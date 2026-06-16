/**
 * Remove orphaned Bootstrap modal artifacts left on the document.
 *
 * Native Bootstrap modals (`new Modal(...)`) append a `.modal-backdrop` element
 * to the body and add `modal-open` (with inline `overflow`/`padding-right`) while
 * shown. When the owning Vue component unmounts on a route change before the modal
 * is hidden/disposed, Bootstrap never runs its cleanup, leaving a full-viewport
 * backdrop above the header that swallows clicks. Calling this after navigation
 * guarantees the UI can never be stranded by a stray backdrop.
 */
export function cleanupModalArtifacts(doc) {
    const root = doc ?? (typeof document !== 'undefined' ? document : null);

    if (!root) {
        return;
    }

    root.querySelectorAll('.modal-backdrop').forEach((element) => {
        element.remove();
    });

    const body = root.body;

    if (!body) {
        return;
    }

    body.classList.remove('modal-open');
    body.style.removeProperty('overflow');
    body.style.removeProperty('padding-right');
}
