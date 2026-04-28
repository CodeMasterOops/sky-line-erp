export const salesOrderColumns = [
    { title: 'SN',          key: 'sn',          width: 60 },
    { title: 'Order No',    dataIndex: 'order_no',    sorter: true },
    { title: 'Date',        dataIndex: 'order_date',  sorter: true },
    { title: 'Customer',    dataIndex: 'party_name',  sorter: true },
    { title: 'Grand Total', dataIndex: 'grand_total', sorter: true },
    { title: 'Status',      key: 'status' },
    { title: 'Action',      key: 'action' },
];

/**
 * @param {{ onView: Function, onEdit: Function, onApprove: Function, onConvert: Function, onDelete: Function }} handlers
 */
export function createRowActions({ onView, onEdit, onApprove, onConvert, onDelete }) {
    return [
        {
            key:     'view',
            icon:    'ti-eye',
            title:   'View',
            handler: (record) => onView(record.id),
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
            key:       'approve',
            icon:      'ti-check',
            title:     'Approve',
            condition: (record) => record.status === 'draft',
            handler:   (record) => onApprove(record.id),
        },
        {
            key:       'convert',
            icon:      'ti-file-invoice',
            title:     'Convert to Invoice',
            condition: (record) => record.status === 'approved' && !record.invoice_count,
            handler:   (record) => onConvert(record.id),
        },
        {
            key:     'delete',
            icon:    'ti-trash',
            title:   'Delete',
            handler: (record) => onDelete(record.id),
        },
    ];
}
