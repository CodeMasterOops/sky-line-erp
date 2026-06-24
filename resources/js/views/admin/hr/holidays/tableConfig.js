export const holidayColumns = [
    { title: 'SN',          key: 'sn',           width: 60 },
    { title: 'Name',        dataIndex: 'name' },
    { title: 'Date (AD)',   dataIndex: 'date' },
    { title: 'Date (BS)',   dataIndex: 'bs_date' },
    { title: 'Description', dataIndex: 'description' },
    { title: 'Action',      key: 'action',        align: 'center' },
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
