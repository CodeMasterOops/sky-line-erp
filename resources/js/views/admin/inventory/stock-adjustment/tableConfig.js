export const stockAdjustmentColumns = [
    { title: 'SN',        key: 'sn',           width: 60 },
    { title: 'Reference', dataIndex: 'reference_no', sorter: true },
    { title: 'Date',      dataIndex: 'date',         sorter: true, width: 120 },
    { title: 'Warehouse', dataIndex: 'warehouse',    sorter: true },
    { title: 'Status',    key: 'status',         width: 120 },
    { title: 'Action',    key: 'action',         width: 120 },
];

/**
 * @param {{ onEdit: Function, onApprove: Function, onDelete: Function }} handlers
 */
export function createRowActions({ onEdit, onApprove, onDelete }) {
    return [
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
            key:     'delete',
            icon:    'ti-trash',
            title:   'Delete',
            class:   'text-danger',
            handler: (record) => onDelete(record.id),
        },
    ];
}
