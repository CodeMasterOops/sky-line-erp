/**
 * List sale (ref.) vs. line net purchase per unit, excluding tax — for purchase bill / PO grids.
 */
export function usePurchaseLineReferenceMargin() {
    function lineNetUnitPurchaseExTax(item) {
        const qty = Number(item.quantity || 0);
        if (!Number.isFinite(qty) || qty <= 0) {
            return 0;
        }
        const rate = Number(item.rate || 0);
        const lineDiscount = Number(item.discount_amount || 0);
        const net = qty * rate - lineDiscount;
        return net / qty;
    }

    function refGrossMarginPercent(item) {
        const list = Number(item.list_sale_snapshot ?? 0);
        if (!Number.isFinite(list) || list <= 0) {
            return null;
        }
        const cost = lineNetUnitPurchaseExTax(item);
        return ((list - cost) / list) * 100;
    }

    function formatRefGrossMargin(item) {
        const p = refGrossMarginPercent(item);
        if (p === null) {
            return '—';
        }
        return `${p.toFixed(1)}%`;
    }

    return {
        lineNetUnitPurchaseExTax,
        refGrossMarginPercent,
        formatRefGrossMargin,
    };
}
