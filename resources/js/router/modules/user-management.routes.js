export default [
    {
        path: "user-management",
        redirect: "/admin/user-management/user",
        children: [
            {
                path: "role",
                name: "admin.role-list",
                meta: {
                    pageTitle: "Role List",
                },
                component: () =>
                    import("@/views/admin/user-management/role/Index.vue"),
            },
            {
                path: "role/create",
                name: "admin.role-create",
                meta: {
                    pageTitle: "Add Role",
                },
                component: () =>
                    import("@/views/admin/user-management/role/Create.vue"),
            },
            {
                path: "role/:id/edit",
                name: "admin.role-edit",
                meta: {
                    pageTitle: "Edit Role",
                },
                component: () =>
                    import("@/views/admin/user-management/role/Edit.vue"),
            },
            {
                path: "user",
                name: "admin.user-list",
                meta: {
                    pageTitle: "User List",
                },
                component: () =>
                    import("@/views/admin/user-management/user/Index.vue"),
            },
        ],
    }
];
