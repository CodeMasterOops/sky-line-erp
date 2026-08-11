export default [
    {
        path: "gym/members",
        name: "admin.gym-member-list",
        meta: {
            pageTitle: "Members",
        },
        component: () => import("@/views/admin/gym/members/Index.vue"),
    },
    {
        path: "gym/members/create",
        name: "admin.gym-member-create",
        meta: {
            pageTitle: "Register Member",
        },
        component: () => import("@/views/admin/gym/members/Create.vue"),
    },
    {
        path: "gym/members/:id/edit",
        name: "admin.gym-member-edit",
        meta: {
            pageTitle: "Edit Member",
        },
        component: () => import("@/views/admin/gym/members/Edit.vue"),
    },
    {
        path: "gym/members/:id",
        name: "admin.gym-member-profile",
        meta: {
            pageTitle: "Member Profile",
        },
        component: () => import("@/views/admin/gym/members/Show.vue"),
    },
    {
        path: "gym/memberships",
        name: "admin.gym-membership-list",
        meta: {
            pageTitle: "Memberships",
        },
        component: () => import("@/views/admin/gym/memberships/Index.vue"),
    },
    {
        path: "gym/check-ins",
        name: "admin.gym-check-in",
        meta: {
            pageTitle: "Check-ins",
        },
        component: () => import("@/views/admin/gym/check-ins/Index.vue"),
    },
    {
        path: "gym/reports",
        name: "admin.gym-reports",
        meta: {
            pageTitle: "Gym Reports",
        },
        component: () => import("@/views/admin/gym/reports/Index.vue"),
    },
    {
        path: "gym/membership-plans",
        name: "admin.gym-membership-plan-list",
        meta: {
            pageTitle: "Membership Plans",
        },
        component: () => import("@/views/admin/gym/membership-plans/Index.vue"),
    },
];
