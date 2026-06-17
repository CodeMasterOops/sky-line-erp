export const componentColumns = [
    { title: 'SN',          key: 'sn',               width: 60 },
    { title: 'Name',        dataIndex: 'name' },
    { title: 'Type',        key: 'type' },
    { title: 'Calculation', key: 'calculation_type' },
    { title: 'Taxable',     key: 'is_taxable' },
    { title: 'GL Account',  key: 'account' },
    { title: 'Status',      key: 'status' },
    { title: 'Action',      key: 'action',           align: 'center' },
];

export function createRowActions({ onEdit, onDelete }) {
    return [
        {
            key:     'edit',
            icon:    'ti-edit',
            title:   'Edit',
            class:   'edit-icon',
            handler: (record) => onEdit({ ...record }),
        },
        {
            key:     'delete',
            icon:    'ti-trash',
            title:   'Delete',
            handler: (record) => onDelete(record.id),
        },
    ];
}
