export const adminNavigationSections = [
    {
        key: 'platform',
        label: 'Trang chủ',
    },
    {
        key: 'workspace',
        label: 'Website & Module',
    },
    {
        key: 'security',
        label: 'Quản trị & Phân quyền',
    },
];

export const adminNavigation = [
    {
        key: 'dashboard',
        label: 'Trang chủ',
        section: 'platform',
        route: '/dashboard',
        permission: 'platform.dashboard.view',
    },
    {
        key: 'module-store',
        label: 'App Store',
        section: 'platform',
        route: '/modules',
        permission: 'store.module.view',
    },
    {
        key: 'theme-manager',
        label: 'Theme Manager',
        section: 'workspace',
        route: '/themes',
        permission: 'theme.view',
    },
    {
        key: 'access-control',
        label: 'RBAC',
        section: 'security',
        route: '/access',
        permission: 'rbac.role.view',
    },
    {
        key: 'admin-accounts',
        label: 'QL Admin Account',
        section: 'security',
        route: '/admins',
        permission: 'admin.account.view',
    },
];
