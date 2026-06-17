export const structureColumns = [
    { title: 'SN',             key: 'sn',            width: 60 },
    { title: 'Employee',       key: 'employee' },
    { title: 'Effective From', dataIndex: 'effective_from' },
    { title: 'Gross',          key: 'gross' },
    { title: 'Status',         key: 'status' },
    { title: 'Action',         key: 'action',        align: 'center' },
];

export function createRowActions({ onView, onDelete }) {
    return [
        {
            key:     'view',
            icon:    'ti-eye',
            title:   'View',
            handler: (record) => onView(record),
        },
        {
            key:     'delete',
            icon:    'ti-trash',
            title:   'Delete',
            handler: (record) => onDelete(record.id),
        },
    ];
}
