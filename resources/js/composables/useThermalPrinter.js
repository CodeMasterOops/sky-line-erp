import { reactive, readonly } from 'vue';
import { apiAdmin } from '@/helpers/api.js';
import { buildReceiptData } from '@/helpers/escpos.js';

/**
 * Direct ESC/POS thermal printing through QZ Tray.
 *
 * QZ Tray is a small app the cashier installs on the POS terminal; it exposes a
 * local WebSocket the browser connects to in order to print straight to the
 * receipt printer with no print dialog. This composable owns a single shared
 * connection, wires up server-side request signing, remembers the chosen
 * printer per terminal, and renders sales as ESC/POS.
 *
 * Everything degrades gracefully: if QZ Tray is not running, not installed, or
 * signing is not configured, callers can fall back to browser printing.
 */

const PRINTER_STORAGE_KEY = 'pos.thermal.printer';
const ALGORITHM = 'SHA512';

const state = reactive({
    supported: typeof window !== 'undefined',
    connecting: false,
    connected: false,
    printers: [],
    selectedPrinter: localStorage.getItem(PRINTER_STORAGE_KEY) || '',
    lastError: '',
});

let qzPromise = null;
let securityConfigured = false;

/**
 * Lazily import the QZ Tray library only when thermal printing is used,
 * keeping it out of the initial bundle.
 */
async function loadQz() {
    if (! qzPromise) {
        qzPromise = import('qz-tray').then((module) => module.default ?? module);
    }

    return qzPromise;
}

/**
 * Register the certificate + signature promises QZ Tray uses to trust this
 * site. When the server has no key configured we skip registration so QZ Tray
 * falls back to its unsigned "Allow" prompt.
 */
async function configureSecurity(qz) {
    if (securityConfigured) { return; }

    let certificate = null;
    try {
        const res = await apiAdmin('pos/qz/certificate');
        certificate = res.data?.certificate ?? null;
    } catch {
        certificate = null;
    }

    if (certificate) {
        qz.security.setCertificatePromise((resolve) => resolve(certificate));
        qz.security.setSignatureAlgorithm(ALGORITHM);
        qz.security.setSignaturePromise((toSign) => async (resolve, reject) => {
            try {
                const res = await apiAdmin('pos/qz/sign', 'post', { request: toSign });
                resolve(res.data?.signature ?? '');
            } catch (error) {
                reject(error);
            }
        });
    }

    securityConfigured = true;
}

/**
 * Ensure a live QZ Tray connection, returning the qz instance or null when
 * QZ Tray is unreachable (not installed / not running).
 */
async function ensureConnected() {
    if (! state.supported) { return null; }

    let qz;
    try {
        qz = await loadQz();
    } catch {
        state.lastError = 'QZ Tray library failed to load.';

        return null;
    }

    if (qz.websocket.isActive()) {
        state.connected = true;

        return qz;
    }

    state.connecting = true;
    state.lastError = '';
    try {
        await configureSecurity(qz);
        await qz.websocket.connect();
        state.connected = true;

        return qz;
    } catch {
        state.connected = false;
        state.lastError = 'QZ Tray is not running on this terminal.';

        return null;
    } finally {
        state.connecting = false;
    }
}

/**
 * Whether QZ Tray is reachable right now.
 */
async function isAvailable() {
    return (await ensureConnected()) !== null;
}

/**
 * Discover installed printers and cache them on the shared state.
 *
 * @returns {Promise<string[]>}
 */
async function refreshPrinters() {
    const qz = await ensureConnected();
    if (! qz) { return []; }

    try {
        const found = await qz.printers.find();
        state.printers = Array.isArray(found) ? found : [found];

        if (! state.selectedPrinter) {
            const def = await qz.printers.getDefault();
            if (def) { setPrinter(def); }
        }
    } catch {
        state.lastError = 'Could not list printers from QZ Tray.';
    }

    return state.printers;
}

/**
 * Persist the chosen printer for this terminal.
 */
function setPrinter(name) {
    state.selectedPrinter = name || '';
    if (name) {
        localStorage.setItem(PRINTER_STORAGE_KEY, name);
    } else {
        localStorage.removeItem(PRINTER_STORAGE_KEY);
    }
}

/**
 * Print a completed sale as ESC/POS.
 *
 * @param {object} receipt  activeReceipt object.
 * @param {object} company  company identity fields.
 * @param {object} [options] { width, openDrawer, customerPan }
 * @returns {Promise<boolean>} true when the job was sent.
 */
async function printReceipt(receipt, company = {}, options = {}) {
    const qz = await ensureConnected();
    if (! qz) {
        throw new Error(state.lastError || 'QZ Tray is unavailable.');
    }

    if (! state.selectedPrinter) {
        await refreshPrinters();
    }
    if (! state.selectedPrinter) {
        throw new Error('No thermal printer is selected.');
    }

    const config = qz.configs.create(state.selectedPrinter, { encoding: 'CP437' });
    const data = buildReceiptData(receipt, company, options);
    await qz.print(config, data);

    return true;
}

export function useThermalPrinter() {
    return {
        state: readonly(state),
        isAvailable,
        refreshPrinters,
        setPrinter,
        printReceipt,
    };
}
