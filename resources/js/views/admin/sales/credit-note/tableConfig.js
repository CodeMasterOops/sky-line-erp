export const creditNoteColumns = [
    { title: "SN", key: "sn", width: 60 },
    { title: "Credit Note No", dataIndex: "credit_note_no", sorter: true },
    { title: "Credit Note Date", dataIndex: "credit_note_date", sorter: true },
    { title: "Customer", dataIndex: "party_name", sorter: true },
    { title: "Grand Total", dataIndex: "grand_total", sorter: true },
    { title: "Status", key: "status" },
    { title: "Action", key: "action" },
];

/**
 * @param {{
 *   onView: Function,
 *   onEdit: Function,
 *   onApprove: Function,
 *   onVoid: Function,
 *   onDelete: Function,
 *   canViewCreditNote?: () => boolean,
 *   canVoidCreditNote?: () => boolean,
 * }} handlers
 */
export function createRowActions({
    onView,
    onEdit,
    onApprove,
    onVoid,
    onDelete,
    canViewCreditNote = () => true,
    canVoidCreditNote = () => true,
}) {
    return [
        {
            key: "view",
            icon: "ti-eye",
            title: "View",
            condition: () => canViewCreditNote(),
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
            key: "approve",
            icon: "ti-check",
            title: "Approve",
            condition: (record) => record.status === "draft",
            handler: (record) => onApprove(record.id),
        },
        {
            key: "void",
            icon: "ti-ban",
            title: "Void",
            class: "text-warning",
            condition: (record) =>
                canVoidCreditNote() &&
                record.status === "approved" &&
                !record.voided_at,
            handler: (record) => onVoid(record.id),
        },
        {
            key: "delete",
            icon: "ti-trash",
            title: "Delete",
            handler: (record) => onDelete(record.id),
        },
    ];
}
