export const quotationColumns = [
    { title: "SN", key: "sn", width: 60 },
    { title: "Quotation No", dataIndex: "quotation_no", sorter: true },
    { title: "Date", dataIndex: "quotation_date", sorter: true },
    { title: "Customer", dataIndex: "party_name", sorter: true },
    { title: "Status", key: "status" },
    { title: "Action", key: "action" },
];

/**
 * @param {{
 *   onView: Function,
 *   onEdit: Function,
 *   onConvertSale: Function,
 *   onConvertInvoice: Function,
 *   onApprove: Function,
 *   onDelete: Function,
 * }} handlers
 */
export function createRowActions({
    onView,
    onEdit,
    onConvertSale,
    onConvertInvoice,
    onApprove,
    onDelete,
}) {
    return [
        {
            key: "view",
            icon: "ti-printer",
            title: "View / Print",
            handler: (record) => onView(record.id),
        },
        {
            key: "edit",
            icon: "ti-edit",
            title: "Edit",
            class: "edit-icon",
            condition: (record) => record.status === "draft",
            handler: (record) => onEdit(record.id),
        },
        {
            key: "convertSale",
            icon: "ti-shopping-cart",
            title: "Convert to Sale",
            condition: (record) =>
                record.status === "approved" && !record.sales_order_count,
            handler: (record) => onConvertSale(record.id),
        },
        {
            key: "convertInvoice",
            icon: "ti-file-invoice",
            title: "Convert to Invoice",
            condition: (record) =>
                record.status === "approved" && !record.invoice_count,
            handler: (record) => onConvertInvoice(record.id),
        },
        {
            key: "approve",
            icon: "ti-check",
            title: "Approve",
            condition: (record) => record.status === "draft",
            handler: (record) => onApprove(record.id),
        },
        {
            key: "delete",
            icon: "ti-trash",
            title: "Delete",
            handler: (record) => onDelete(record.id),
        },
    ];
}
