export const designationColumns = [
    { title: 'SN',          key: 'sn',          width: 60 },
    { title: 'Name',        dataIndex: 'name' },
    { title: 'Description', dataIndex: 'description' },
    { title: 'Status',      key: 'status' },
    { title: 'Action',      key: 'action',      align: 'center' },
];

export function createRowActions({ onEdit, onDelete }) {
    return [
        {
            key:     'edit',
            icon:    'ti-edit',
            title:   'Edit',
            class:   'edit-icon',
            handler: (record) => onEdit(record.id),
        },
        {
            key:     'delete',
            icon:    'ti-trash',
            title:   'Delete',
            handler: (record) => onDelete(record.id),
        },
    ];
}
