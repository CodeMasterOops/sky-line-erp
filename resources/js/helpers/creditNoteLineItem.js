export function variantLabel(variant) {
    let label = variant.name || '';
    if (variant.sku) {
        label += ` (${variant.sku})`;
    }

    return label;
}

export function productLabelFromInvoiceLine(line) {
    const variant = line.product_variant;
    if (!variant) {
        return 'Unknown product';
    }

    return variantLabel(variant);
}

/**
 * @param {object} item Invoice line from API
 * @param {number} invoicedQty Max quantity from invoice line
 * @param {{ includeId?: boolean }} [options]
 */
export function lineFromInvoiceItem(item, invoicedQty, options = {}) {
    const { includeId = false } = options;
    const ldv =
        item.line_discount_value !== null && item.line_discount_value !== undefined && item.line_discount_value !== ''
            ? String(item.line_discount_value)
            : '0';
    const qtyDefault = Math.min(1, Math.max(1, invoicedQty));

    const line = {
        invoice_item_id: item.id,
        product_variant_id: item.product_variant_id,
        product_label: productLabelFromInvoiceLine(item),
        sku: item.product_variant?.sku ?? '',
        purchase_snapshot: item.product_variant?.purchase_price ?? 0,
        unit_id: item.unit_id ?? '',
        quantity: String(qtyDefault),
        rate: String(Number(item.rate ?? 0)),
        tax_id: item.tax_id || '',
        line_discount_type: item.line_discount_type || 'fixed',
        line_discount_value: ldv,
        is_service: !!item.product_variant?.is_service,
        warehouse_id: item.warehouse_id || '',
        warehouse_name: item.warehouse?.name ?? '',
        stock_qty: null,
    };

    if (includeId) {
        return { id: '', ...line };
    }

    return line;
}

export function firstFormValidationError(errors) {
    const messages = Object.values(errors?.value ?? errors ?? {}).filter(Boolean);

    return messages[0] ?? 'Please fix the highlighted errors before submitting.';
}
