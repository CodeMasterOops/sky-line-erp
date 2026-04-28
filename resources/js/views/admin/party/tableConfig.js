export const partyColumns = [
    { title: "SN",     key: "sn",         width: 60 },
    { title: "Name",   dataIndex: "name",       sorter: true },
    { title: "Code",   dataIndex: "code",       sorter: true },
    { title: "Type",   dataIndex: "type_label", sorter: true },
    { title: "Phone",  dataIndex: "phone" },
    { title: "Email",  dataIndex: "email" },
    { title: "Action", key: "action", align: "center" },
];

/**
 * @param {{ onEdit: Function, onDelete: Function }} handlers
 */
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
