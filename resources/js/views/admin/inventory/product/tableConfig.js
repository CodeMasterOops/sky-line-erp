import { formatMoney } from '@/helpers/formatMoney.js';

function sortByString(getter) {
    return (a, b) =>
        (getter(a) ?? "")
            .toLowerCase()
            .localeCompare((getter(b) ?? "").toLowerCase());
}

function sortByNumber(getter) {
    return (a, b) => (getter(a) ?? 0) - (getter(b) ?? 0);
}

const typeColumn = {
    title: "Type",
    key: "product_type",
    dataIndex: "product_type",
    sorter: sortByString((r) => r.product_type),
};

const stockColumns = [
    {
        title: "Total stock",
        key: "total_stock",
        dataIndex: "total_stock",
        sorter: sortByNumber((r) => r.total_stock),
    },
    {
        title: "Inventory value",
        key: "total_inventory_value",
        dataIndex: "total_inventory_value",
        sorter: sortByNumber((r) => r.total_inventory_value),
    },
];

const baseColumns = [
    {
        title: "Item",
        dataIndex: "name",
        key: "Product",
        sorter: sortByString((r) => r.name),
    },
    typeColumn,
    { title: "Category", dataIndex: "category", sorter: true },
    { title: "Brand", dataIndex: "brand", sorter: true },
    {
        title: "Purchase",
        key: "purchase_price",
        dataIndex: "purchase_price",
        sorter: sortByNumber((r) => r.defaultVariant?.purchase_price),
    },
    {
        title: "VAT",
        key: "tax",
        dataIndex: "tax",
        sorter: sortByString((r) => r.tax?.name),
    },
];

export function getProductColumns({ showStock = true } = {}) {
    const cols = [...baseColumns];
    if (showStock) {
        cols.push(...stockColumns);
    }
    cols.push({ title: "Action", key: "action" });
    return cols;
}

/** @deprecated Use getProductColumns() */
export const productColumns = getProductColumns();

/**
 * @param {{ onEdit: Function, onDelete: Function }} handlers
 */
export function createRowActions({ onEdit, onDelete }) {
    return [
        {
            key: "edit",
            icon: "ti-edit",
            title: "Edit",
            class: "edit-icon",
            handler: (record) => onEdit(record.id),
        },
        {
            key: "delete",
            icon: "ti-trash",
            title: "Delete",
            handler: (record) => onDelete(record.id),
        },
    ];
}

export function formatProductType(type) {
    return type === "service" ? "Service" : "Product";
}

export function formatHsnLabel(productType) {
    return productType === "service" ? "SAC" : "HSN";
}

/**
 * @param {number|string|null|undefined} price
 * @param {string|null|undefined} unit
 * @returns {{ price: string, unit: string|null }}
 */
export function formatPriceWithUnit(price, unit) {
    const formatted = formatMoney(price);

    if (formatted === "—") {
        return { price: "—", unit: null };
    }

    return {
        price: formatted,
        unit: unit ? String(unit).trim() || null : null,
    };
}
