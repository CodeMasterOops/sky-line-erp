export default [
    {
        path: "crm/contacts",
        name: "admin.crm-contacts",
        meta: {
            pageTitle: "Contacts",
        },
        component: () => import("@/views/admin/crm/contacts/Index.vue"),
    },
    {
        path: "crm/follow-ups",
        name: "admin.crm-follow-ups",
        meta: {
            pageTitle: "Follow-ups",
        },
        component: () => import("@/views/admin/crm/follow-ups/Index.vue"),
    },
    {
        path: "crm/tasks",
        name: "admin.crm-tasks",
        meta: {
            pageTitle: "Tasks",
        },
        component: () => import("@/views/admin/crm/tasks/Index.vue"),
    },
];
