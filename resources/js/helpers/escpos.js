/**
 * ESC/POS receipt builder for QZ Tray raw thermal printing.
 *
 * Produces the data array QZ Tray's `qz.print()` expects — a mix of plain text
 * lines and hex command objects — formatted for an 80mm (48-column) printer.
 */

const ESC = {
    init: '1B40', // ESC @  — reset printer
    alignLeft: '1B6100',
    alignCenter: '1B6101',
    alignRight: '1B6102',
    boldOn: '1B4501',
    boldOff: '1B4500',
    doubleOn: '1D2111', // GS ! — double width + height
    doubleOff: '1D2100',
    cut: '1D5601', // GS V 1 — partial cut
    drawer: '1B70001964FA', // ESC p 0 25 250 — open cash drawer (no-op if none)
};

function cmd(hex) {
    return { type: 'raw', format: 'command', flavor: 'hex', data: hex };
}

function text(value) {
    return `${value}\n`;
}

function formatAmount(value) {
    const number = Number(value ?? 0);

    return number.toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/**
 * Wrap a string to a fixed column width.
 *
 * @returns {string[]}
 */
function wrap(value, width) {
    const words = String(value ?? '').split(/\s+/).filter(Boolean);
    const lines = [];
    let current = '';

    for (const word of words) {
        if (word.length > width) {
            if (current) { lines.push(current); current = ''; }
            for (let i = 0; i < word.length; i += width) {
                lines.push(word.slice(i, i + width));
            }

            continue;
        }
        if ((current ? `${current} ${word}` : word).length > width) {
            lines.push(current);
            current = word;
        } else {
            current = current ? `${current} ${word}` : word;
        }
    }
    if (current) { lines.push(current); }

    return lines.length ? lines : [''];
}

/**
 * A full-width row with left- and right-aligned segments.
 */
function twoCol(left, right, width) {
    const l = String(left ?? '');
    const r = String(right ?? '');
    const gap = width - l.length - r.length;

    if (gap < 1) {
        return `${l} ${r}`;
    }

    return `${l}${' '.repeat(gap)}${r}`;
}

/**
 * Build the QZ Tray print payload for a completed POS sale.
 *
 * @param {object}  receipt  The activeReceipt object.
 * @param {object}  company  Company identity fields.
 * @param {object} [options] { width=48, openDrawer=false, customerPan }
 * @returns {Array<string|object>}
 */
export function buildReceiptData(receipt, company = {}, options = {}) {
    const width = options.width ?? 48;
    const data = [cmd(ESC.init)];

    // ── Header ───────────────────────────────────────────────────────────
    data.push(cmd(ESC.alignCenter), cmd(ESC.boldOn));
    if (company.companyName) {
        data.push(cmd(ESC.doubleOn), text(company.companyName), cmd(ESC.doubleOff));
    }
    data.push(cmd(ESC.boldOff));
    if (company.companyLegalName && company.companyLegalName !== company.companyName) {
        data.push(text(company.companyLegalName));
    }
    for (const detail of [company.companyAddress, company.companyLocation, company.companyPhone]) {
        if (detail) { data.push(text(detail)); }
    }
    if (company.companyPan) { data.push(text(`PAN: ${company.companyPan}`)); }

    const title = Number(receipt?.tax_total) > 0 ? 'TAX INVOICE' : 'CASH MEMO';
    data.push('\n', cmd(ESC.boldOn), text(title), cmd(ESC.boldOff));

    // ── Meta ─────────────────────────────────────────────────────────────
    data.push(cmd(ESC.alignLeft));
    data.push(text('-'.repeat(width)));
    data.push(text(twoCol('Invoice:', receipt?.invoice_no ?? '-', width)));
    data.push(text(twoCol('Date:', receipt?.invoice_date ?? '-', width)));
    if (receipt?.invoice_date_bs) {
        data.push(text(twoCol('Miti:', receipt.invoice_date_bs, width)));
    }
    data.push(text(twoCol('Customer:', receipt?.party_name ?? 'Walk-in Customer', width)));
    const customerPan = options.customerPan || receipt?.party_pan;
    if (customerPan) {
        data.push(text(twoCol('Cust. PAN:', customerPan, width)));
    }

    // ── Items ────────────────────────────────────────────────────────────
    data.push(text('-'.repeat(width)));
    data.push(cmd(ESC.boldOn), text(twoCol('Item', 'Amount', width)), cmd(ESC.boldOff));
    data.push(text('-'.repeat(width)));

    for (const item of receipt?.items ?? []) {
        for (const nameLine of wrap(item.name, width)) {
            data.push(text(nameLine));
        }
        if (item.sku) {
            data.push(text(`  ${item.sku}`));
        }
        const qtyRate = `  ${item.quantity} x ${formatAmount(item.rate)}`;
        data.push(text(twoCol(qtyRate, formatAmount(item.total), width)));
    }

    // ── Totals ───────────────────────────────────────────────────────────
    data.push(text('-'.repeat(width)));
    if (receipt?.subtotal != null) {
        data.push(text(twoCol('Subtotal', formatAmount(receipt.subtotal), width)));
    }
    if (Number(receipt?.line_discount_total) > 0) {
        data.push(text(twoCol('Item Discount', `- ${formatAmount(receipt.line_discount_total)}`, width)));
    }
    if (Number(receipt?.order_discount_amount) > 0) {
        data.push(text(twoCol('Order Discount', `- ${formatAmount(receipt.order_discount_amount)}`, width)));
    }
    if (Number(receipt?.tax_total) > 0) {
        const taxable = receipt.taxable_amount
            ?? (receipt.subtotal ?? 0) - (receipt.line_discount_total ?? 0) - (receipt.order_discount_amount ?? 0);
        data.push(text(twoCol('Taxable Amount', formatAmount(taxable), width)));
        data.push(text(twoCol('VAT', formatAmount(receipt.tax_total), width)));
    }

    data.push(cmd(ESC.boldOn), cmd(ESC.doubleOn));
    data.push(text(twoCol('TOTAL', formatAmount(receipt?.grand_total), Math.floor(width / 2))));
    data.push(cmd(ESC.doubleOff), cmd(ESC.boldOff));

    // ── Payment ──────────────────────────────────────────────────────────
    data.push(text('-'.repeat(width)));
    if (receipt?.payment_method === 'credit') {
        data.push(text(twoCol('Payment', 'CREDIT (Due)', width)));
    } else if (Array.isArray(receipt?.payments) && receipt.payments.length > 1) {
        for (const payment of receipt.payments) {
            data.push(text(twoCol(payment.method, formatAmount(payment.amount), width)));
        }
    } else {
        data.push(text(twoCol('Payment', (receipt?.payment_method ?? '-').toUpperCase(), width)));
    }

    // ── Footer ───────────────────────────────────────────────────────────
    data.push('\n', cmd(ESC.alignCenter));
    if (company.companyInvoiceNote) {
        for (const noteLine of wrap(company.companyInvoiceNote, width)) {
            data.push(text(noteLine));
        }
    }
    data.push(text('Thank you for your business!'));
    data.push('\n\n\n');

    if (options.openDrawer) { data.push(cmd(ESC.drawer)); }
    data.push(cmd(ESC.cut));

    return data;
}
