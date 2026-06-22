export const bomColumns = [
    { title: 'SN',          key: 'sn',          width: 60 },
    { title: 'Product',     key: 'product' },
    { title: 'BOM Name',    dataIndex: 'name',       key: 'name' },
    { title: 'Version',     dataIndex: 'version',    key: 'version',    width: 100 },
    { title: 'Output Qty',  dataIndex: 'output_qty', key: 'output_qty', width: 120 },
    { title: 'Materials',   key: 'items_count',  width: 100 },
    { title: 'Default',     key: 'is_default',   width: 90 },
    { title: 'Status',      key: 'is_active',    width: 90 },
    { title: 'Action',      key: 'action',       width: 110 },
];

/**
 * @param {{ onView: Function, onEdit: Function, onDelete: Function }} handlers
 */
export function createRowActions({ onView, onEdit, onDelete }) {
    return [
        {
            key:     'view',
            icon:    'ti-eye',
            title:   'View',
            handler: (record) => onView(record),
        },
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
            class:   'text-danger',
            handler: (record) => onDelete(record.id),
        },
    ];
}
