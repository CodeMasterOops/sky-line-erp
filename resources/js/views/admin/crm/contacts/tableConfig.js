export const contactColumns = [
    { title: "SN",     key: "sn",         width: 60 },
    { title: "Name",   dataIndex: "name",       sorter: true },
    { title: "Code",   dataIndex: "code",       sorter: true },
    { title: "Type",   dataIndex: "type_label", sorter: true },
    { title: "Phone",  dataIndex: "phone" },
    { title: "Email",  dataIndex: "email" },
    { title: "Action", key: "action", align: "center" },
];

/**
 * @param {{ onView: Function, onEdit: Function, onConvert: Function, onDelete: Function }} handlers
 */
export function createRowActions({ onView, onEdit, onConvert, onDelete }) {
    return [
        {
            key:       'view',
            icon:      'ti-eye',
            title:     'Customer 360',
            condition: (record) => record.type === 'customer',
            handler:   (record) => onView(record),
        },
        {
            key:     'edit',
            icon:    'ti-edit',
            title:   'Edit',
            class:   'edit-icon',
            handler: (record) => onEdit(record.id),
        },
        {
            key:       'convert',
            icon:      'ti-user-check',
            title:     'Convert to customer',
            condition: (record) => record.type === 'lead',
            handler:   (record) => onConvert(record),
        },
        {
            key:     'delete',
            icon:    'ti-trash',
            title:   'Delete',
            handler: (record) => onDelete(record.id),
        },
    ];
}
