export const invoiceColumns = [
    { title: 'SN',           key: 'sn',              width: 60 },
    { title: 'Invoice No',   dataIndex: 'invoice_no',   sorter: true },
    { title: 'Invoice Date', dataIndex: 'invoice_date', sorter: true },
    { title: 'Due Date',     dataIndex: 'due_date',     sorter: true },
    { title: 'Customer',     dataIndex: 'party_name',   sorter: true },
    { title: 'Grand Total',  dataIndex: 'grand_total',  sorter: true },
    { title: 'Status',       key: 'status' },
    { title: 'Action',       key: 'action' },
];

/**
 * @param {{
 *   onView: Function,
 *   onEdit: Function,
 *   onRecordPayment: Function,
 *   onApprove: Function,
 *   onVoid: Function,
 *   onDelete: Function,
 *   canViewInvoice?: () => boolean,
 *   canVoidInvoice?: () => boolean,
 * }} handlers
 */
export function createRowActions({
    onView,
    onEdit,
    onRecordPayment,
    onApprove,
    onVoid,
    onDelete,
    canViewInvoice = () => true,
    canVoidInvoice = () => true,
}) {
    return [
        {
            key:       'view',
            icon:      'ti-eye',
            title:     'View',
            condition: () => canViewInvoice(),
            handler:   (record) => onView(record.id),
        },
        {
            key:       'edit',
            icon:      'ti-edit',
            title:     'Edit',
            class:     'edit-icon',
            condition: (record) => record.status === 'draft',
            handler:   (record) => onEdit(record.id),
        },
        {
            key:       'payment',
            icon:      'ti-receipt',
            title:     'Record payment',
            condition: (record) => record.status === 'approved' && !record.voided_at,
            handler:   (record) => onRecordPayment(record.id),
        },
        {
            key:       'approve',
            icon:      'ti-check',
            title:     'Approve',
            condition: (record) => record.status === 'draft',
            handler:   (record) => onApprove(record.id),
        },
        {
            key:       'void',
            icon:      'ti-ban',
            title:     'Void',
            class:     'text-warning',
            condition: (record) =>
                canVoidInvoice() && record.status === 'approved' && !record.voided_at,
            handler: (record) => onVoid(record.id),
        },
        {
            key:     'delete',
            icon:    'ti-trash',
            title:   'Delete',
            handler: (record) => onDelete(record.id),
        },
    ];
}
