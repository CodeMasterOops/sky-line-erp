export default [
    {
        path: "crm/contacts",
        name: "admin.crm-contacts",
        meta: {
            pageTitle: "Contacts",
        },
        component: () => import("@/views/admin/crm/contacts/Index.vue"),
    },
];
