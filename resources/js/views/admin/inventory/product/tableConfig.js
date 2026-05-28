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
    { title: "SKU", dataIndex: "code", sorter: true },
    { title: "Product Name", dataIndex: "name", key: "Product", sorter: true },
    typeColumn,
    { title: "Category", dataIndex: "category", sorter: true },
    { title: "Brand", dataIndex: "brand", sorter: true },
    {
        title: "Price",
        customRender: ({ record }) => formatMoney(record.defaultVariant?.sales_price),
        sorter: true,
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
