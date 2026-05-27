export const deliveryChallanColumns = [
    { title: 'SN', key: 'sn', width: 60 },
    { title: 'Challan No', dataIndex: 'challan_no', sorter: true },
    { title: 'Customer', dataIndex: 'party_name', sorter: true },
    { title: 'Warehouse', dataIndex: 'warehouse_name', sorter: true },
    { title: 'Date', dataIndex: 'challan_date', sorter: true },
    { title: 'Receiver', dataIndex: 'receiver_name', sorter: true },
    { title: 'Status', key: 'status' },
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
            title: 'Approve & Issue Stock',
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
