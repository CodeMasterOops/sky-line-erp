export const grnColumns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'GRN No', dataIndex: 'grn_no', sorter: true },
    { title: 'Supplier', dataIndex: 'party_name', sorter: true },
    { title: 'Warehouse', dataIndex: 'warehouse_name', sorter: true },
    { title: 'Received Date', key: 'received_date', sorter: true },
    { title: 'Status', key: 'status' },
    { title: 'Billing', key: 'billing_status' },
    { title: 'Action', key: 'action' },
];

/**
 * @param {{ onView: Function, onEdit: Function, onApprove: Function, onDelete: Function }} handlers
 */
export function createRowActions({ onView, onEdit, onApprove, onDelete }) {
    return [
        {
            key: 'view',
            icon: 'ti-eye',
            title: 'View',
            handler: (record) => onView(record.id),
        },
        {
            key: 'edit',
            icon: 'ti-edit',
            title: 'Edit',
            class: 'edit-icon',
            condition: (record) => record.status === 'draft',
            handler: (record) => onEdit(record.id),
        },
        {
            key: 'approve',
            icon: 'ti-check',
            title: 'Approve & Receive Stock',
            condition: (record) => record.status === 'draft',
            handler: (record) => onApprove(record.id),
        },
        {
            key: 'delete',
            icon: 'ti-trash',
            title: 'Delete',
            condition: (record) => record.status === 'draft',
            handler: (record) => onDelete(record.id),
        },
    ];
}
