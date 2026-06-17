export const leaveTypeColumns = [
    { title: 'SN',           key: 'sn',          width: 60 },
    { title: 'Name',         dataIndex: 'name' },
    { title: 'Days Allowed', key: 'days_allowed' },
    { title: 'Paid',         key: 'is_paid' },
    { title: 'Status',       key: 'status' },
    { title: 'Action',       key: 'action',      align: 'center' },
];

export function createRowActions({ onEdit, onDelete }) {
    return [
        {
            key:     'edit',
            icon:    'ti-edit',
            title:   'Edit',
            class:   'edit-icon',
            handler: (record) => onEdit(record),
        },
        {
            key:     'delete',
            icon:    'ti-trash',
            title:   'Delete',
            handler: (record) => onDelete(record.id),
        },
    ];
}
