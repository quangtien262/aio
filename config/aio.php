<?php

return [
    'website_types' => [
        'ecommerce' => 'Thương mại điện tử',
        'corporate' => 'Giới thiệu doanh nghiệp',
        'service' => 'Website dịch vụ',
        'news' => 'Website tin tức',
        'landingpage' => 'Landing page',
        'backoffice' => 'Backoffice / Admin utility',
    ],

    'core_permissions' => [
        'platform.dashboard.view',
        'platform.settings.manage',
        'setup.view',
        'setup.complete',
        'store.module.view',
        'store.module.install',
        'store.module.enable',
        'store.module.disable',
        'store.module.upgrade',
        'store.module.uninstall',
        'theme.view',
        'theme.install',
        'theme.activate',
        'theme.customize',
        'rbac.role.view',
        'rbac.role.manage',
        'rbac.permission.view',
        'rbac.permission.assign',
        'rbac.scope.view',
        'rbac.scope.assign',
        'admin.account.view',
        'admin.account.manage',
        'admin.account.reset_password',
        'admin.account.lock',
    ],

    'scope_types' => [
        'website' => 'Website',
        'module' => 'Module',
        'owner' => 'Owner',
        'tenant' => 'Tenant',
    ],

    'setup_steps' => [
        'website_type',
        'theme',
        'branding',
        'modules',
        'admin_account',
        'finish',
    ],

    'setup_step_meta' => [
        'website_type' => [
            'label' => 'Cấu hình website',
            'description' => 'Đặt tên website và chọn loại website nền tảng.',
            'route' => '/setup',
            'manual_completion' => false,
        ],
        'theme' => [
            'label' => 'Kích hoạt theme',
            'description' => 'Chọn theme phù hợp với loại website đang vận hành.',
            'route' => '/themes',
            'manual_completion' => false,
        ],
        'branding' => [
            'label' => 'Xác nhận branding',
            'description' => 'Kiểm tra tên thương hiệu, màu chủ đạo và thông tin nhận diện cơ bản.',
            'route' => '/setup',
            'manual_completion' => true,
        ],
        'modules' => [
            'label' => 'Bật module nền tảng',
            'description' => 'Cài đặt hoặc kích hoạt các module nghiệp vụ cần dùng trước khi bàn giao.',
            'route' => '/modules',
            'manual_completion' => true,
        ],
        'admin_account' => [
            'label' => 'Kiểm tra admin account',
            'description' => 'Đảm bảo có ít nhất một tài khoản admin hoạt động để vận hành hệ thống.',
            'route' => '/admins',
            'manual_completion' => false,
        ],
        'finish' => [
            'label' => 'Chốt setup',
            'description' => 'Đánh dấu hoàn tất khi các bước nền tảng đã sẵn sàng.',
            'route' => '/setup',
            'manual_completion' => true,
        ],
    ],
];
