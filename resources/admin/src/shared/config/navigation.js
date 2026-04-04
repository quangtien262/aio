export const adminNavigationSections = [
    {
        key: 'platform',
        label: 'Nền tảng',
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
        label: 'Platform Dashboard',
        section: 'platform',
        route: '/dashboard',
        permission: 'platform.dashboard.view',
    },
    {
        key: 'orders',
        label: 'Đơn hàng',
        section: 'platform',
        route: '/orders',
        permission: 'platform.dashboard.view',
    },
    {
        key: 'newsletter',
        label: 'Bản tin',
        section: 'platform',
        route: '/newsletter',
        permission: 'platform.dashboard.view',
    },
    {
        key: 'module-store',
        label: 'Module Store',
        section: 'workspace',
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
    {
        key: 'setup-wizard',
        label: 'Cài đặt website',
        section: 'platform',
        route: '/setup',
        permission: 'setup.view',
    },
];
