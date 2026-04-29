import { Suspense, lazy, useCallback, useEffect, useMemo, useState } from 'react';
import AppstoreOutlined from '@ant-design/icons/AppstoreOutlined';
import BgColorsOutlined from '@ant-design/icons/BgColorsOutlined';
import DashboardOutlined from '@ant-design/icons/DashboardOutlined';
import PictureOutlined from '@ant-design/icons/PictureOutlined';
import ProfileOutlined from '@ant-design/icons/ProfileOutlined';
import ReadOutlined from '@ant-design/icons/ReadOutlined';
import SafetyCertificateOutlined from '@ant-design/icons/SafetyCertificateOutlined';
import SettingOutlined from '@ant-design/icons/SettingOutlined';
import TagsOutlined from '@ant-design/icons/TagsOutlined';
import TeamOutlined from '@ant-design/icons/TeamOutlined';
import Alert from 'antd/es/alert';
import App from 'antd/es/app';
import Breadcrumb from 'antd/es/breadcrumb';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Drawer from 'antd/es/drawer';
import Dropdown from 'antd/es/dropdown';
import Grid from 'antd/es/grid';
import Layout from 'antd/es/layout';
import Menu from 'antd/es/menu';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';
import MenuOutlined from '@ant-design/icons/MenuOutlined';
import MoreOutlined from '@ant-design/icons/MoreOutlined';
import { Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom';
import { adminNavigation, adminNavigationSections } from '../shared/config/navigation';

const ModuleRoutePage = lazy(() => import('../pages/modules/ModuleRoutePage'));
const DashboardRoutePage = lazy(() => import('../pages/routes/DashboardRoutePage'));
const OrdersRoutePage = lazy(() => import('../pages/routes/OrdersRoutePage'));
const NewsletterSubscribersRoutePage = lazy(() => import('../pages/routes/NewsletterSubscribersRoutePage'));
const AccessRoutePage = lazy(() => import('../pages/routes/AccessRoutePage'));
const AdminAccountsRoutePage = lazy(() => import('../pages/routes/AdminAccountsRoutePage'));
const ModulesRoutePage = lazy(() => import('../pages/routes/ModulesRoutePage'));
const ThemesRoutePage = lazy(() => import('../pages/routes/ThemesRoutePage'));
const SetupRoutePage = lazy(() => import('../pages/routes/SetupRoutePage'));

const { Header, Content } = Layout;
const { Text } = Typography;
const { useBreakpoint } = Grid;

const sectionMetaMap = {
    platform: {
        kicker: 'Core',
        description: 'Trang chủ launcher, App Store và các điểm vào nền tảng.',
    },
    workspace: {
        kicker: 'Workspace',
        description: 'Các không gian vận hành website, module và theme đang bật.',
    },
    security: {
        kicker: 'Security',
        description: 'RBAC, tài khoản admin và quyền truy cập nội bộ.',
    },
};

function renderLazyRouteElement(Component, props, fallbackTitle) {
    return (
        <Suspense fallback={<Card loading title={fallbackTitle} />}>
            <Component {...props} />
        </Suspense>
    );
}

export default function AdminLayout() {
    const { message } = App.useApp();
    const screens = useBreakpoint();
    const [currentAdmin, setCurrentAdmin] = useState(null);
    const [modules, setModules] = useState([]);
    const [loadError, setLoadError] = useState(null);
    const [shellReady, setShellReady] = useState(false);
    const [brandLogoFailed, setBrandLogoFailed] = useState(false);
    const [mobileNavigationOpen, setMobileNavigationOpen] = useState(false);
    const [mobileSectionKey, setMobileSectionKey] = useState(null);
    const location = useLocation();
    const navigate = useNavigate();
    const isMobile = !screens.lg;

    const callAdminApi = useCallback(async (url, options = {}) => {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const isFormData = options.body instanceof FormData;
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': token ?? '',
                Accept: 'application/json',
                ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
                ...(options.headers ?? {}),
            },
            ...options,
        });

        if (!response.ok) {
            let errorMessage = 'Không thực hiện được thao tác.';

            try {
                const payload = await response.json();
                errorMessage = payload.message ?? errorMessage;
            } catch {
                // Ignore invalid JSON body.
            }

            throw new Error(errorMessage);
        }

        if (response.status === 204) {
            return null;
        }

        return response.json();
    }, []);

    const hasPermission = useCallback((permission) => (currentAdmin?.permissions ?? []).includes(permission), [currentAdmin]);

    const loadShellData = useCallback(async () => {
        try {
            setLoadError(null);

            const mePayload = await callAdminApi('/admin/api/me');
            const nextCurrentAdmin = mePayload.data ?? null;

            setCurrentAdmin(nextCurrentAdmin);
            setShellReady(true);

            if ((nextCurrentAdmin?.permissions ?? []).includes('store.module.view')) {
                try {
                    const modulePayload = await callAdminApi('/admin/api/modules');
                    setModules(modulePayload.data ?? []);
                } catch {
                    setModules([]);
                }
            } else {
                setModules([]);
            }
        } catch (error) {
            setLoadError(error instanceof Error ? error.message : 'Không tải được dữ liệu admin.');
            setShellReady(true);
        }
    }, [callAdminApi]);

    useEffect(() => {
        loadShellData();
    }, [loadShellData]);

    useEffect(() => {
        setBrandLogoFailed(false);
    }, [currentAdmin?.site_profile?.branding?.logo_url]);

    useEffect(() => {
        if (!isMobile) {
            setMobileNavigationOpen(false);
        }
    }, [isMobile]);

    const runAdminAction = useCallback(async (executor, successMessage, onSuccess) => {
        try {
            await executor();

            if (typeof onSuccess === 'function') {
                await onSuccess();
            } else {
                await loadShellData();
            }

            message.success(successMessage);
            return true;
        } catch (error) {
            message.error(error instanceof Error ? error.message : 'Không thực hiện được thao tác.');
            return false;
        }
    }, [loadShellData, message]);

    const navigationItems = useMemo(() => {
        return [
            ...adminNavigation,
            ...((currentAdmin?.module_navigation ?? []).map((item) => ({
                ...item,
                section: item.section ?? 'workspace',
            }))),
        ].filter((item) => !item.permission || hasPermission(item.permission));
    }, [currentAdmin, hasPermission]);

    const defaultRoute = navigationItems[0]?.route ?? '/dashboard';

    const resolveNavigationIcon = useCallback((itemKey, iconKey = null) => {
        const iconMap = {
            dashboard: <DashboardOutlined />,
            orders: <ProfileOutlined />,
            newsletter: <ReadOutlined />,
            'module-store': <AppstoreOutlined />,
            'theme-manager': <BgColorsOutlined />,
            'access-control': <SafetyCertificateOutlined />,
            'admin-accounts': <TeamOutlined />,
            'setup-wizard': <SettingOutlined />,
            appstore: <AppstoreOutlined />,
            menu: <MenuOutlined />,
            picture: <PictureOutlined />,
            profile: <ProfileOutlined />,
            read: <ReadOutlined />,
            tags: <TagsOutlined />,
        };

        return iconMap[iconKey] ?? iconMap[itemKey] ?? <AppstoreOutlined />;
    }, []);

    const normalizeRoute = useCallback((route) => {
        if (!route) {
            return '/';
        }

        return /^\/admin(?:\/|$)/.test(route) ? route.replace(/^\/admin(?=\/|$)/, '') || '/' : route;
    }, []);

    const renderModuleRoutes = useCallback(() => {
        return (currentAdmin?.module_navigation ?? []).map((item) => {
            const route = normalizeRoute(item.route);
            const modulePayload = modules.find((moduleItem) => moduleItem.key === item.module_key) ?? null;

            return (
                <Route
                    key={item.key}
                    path={route === '/' ? '/' : `${route.replace(/^\//, '')}/*`}
                    element={renderLazyRouteElement(ModuleRoutePage, {
                        moduleMenu: item,
                        modulePayload,
                        callAdminApi,
                        runAdminAction,
                        currentPermissions: currentAdmin?.permissions ?? [],
                    }, item.label ?? modulePayload?.name ?? 'Module')}
                />
            );
        });
    }, [callAdminApi, currentAdmin, modules, normalizeRoute, runAdminAction]);

    const navigationMenuItems = useMemo(() => {
        return navigationItems.map((item) => ({
            key: item.key,
            label: item.label,
            section: item.section ?? 'workspace',
            icon: resolveNavigationIcon(item.key, item.icon),
            route: normalizeRoute(item.route),
            source: item.source ?? 'static',
            moduleKey: item.module_key ?? null,
        }));
    }, [navigationItems, normalizeRoute, resolveNavigationIcon]);

    const currentNavigationItem = useMemo(() => {
        return navigationMenuItems.find((item) => location.pathname === item.route)
            ?? navigationMenuItems.find((item) => item.route !== '/' && location.pathname.startsWith(`${item.route}/`))
            ?? null;
    }, [location.pathname, navigationMenuItems]);

    const availableTopSections = useMemo(() => {
        return adminNavigationSections.filter((section) => navigationMenuItems.some((item) => item.section === section.key));
    }, [navigationMenuItems]);

    const activeTopSectionKey = currentNavigationItem?.section ?? availableTopSections[0]?.key ?? 'platform';

    useEffect(() => {
        if (!isMobile || !mobileNavigationOpen) {
            setMobileSectionKey(activeTopSectionKey);
        }
    }, [activeTopSectionKey, isMobile, mobileNavigationOpen]);

    const topMenuItems = useMemo(() => {
        return availableTopSections.map((section) => ({
            key: section.key,
            label: section.label,
        }));
    }, [availableTopSections]);

    const sectionDropdownItems = useMemo(() => {
        return availableTopSections.map((section) => {
            const sectionMeta = sectionMetaMap[section.key] ?? {
                kicker: 'Section',
                description: 'Đi vào đúng nhóm chức năng quản trị tương ứng.',
            };
            const sectionItemCount = navigationMenuItems.filter((item) => item.section === section.key).length;

            return {
                key: section.key,
                label: (
                    <div className="admin-section-switcher-item">
                        <div className="admin-section-switcher-item-kicker">{sectionMeta.kicker}</div>
                        <div className="admin-section-switcher-item-title-row">
                            <span className="admin-section-switcher-item-title">{section.label}</span>
                            <span className="admin-section-switcher-item-count">{sectionItemCount}</span>
                        </div>
                        <div className="admin-section-switcher-item-description">{sectionMeta.description}</div>
                    </div>
                ),
            };
        });
    }, [availableTopSections, navigationMenuItems]);

    const activeTopSection = useMemo(() => {
        return availableTopSections.find((section) => section.key === activeTopSectionKey) ?? null;
    }, [activeTopSectionKey, availableTopSections]);

    const effectiveSectionKey = isMobile ? (mobileSectionKey ?? activeTopSectionKey) : activeTopSectionKey;

    const sideMenuItems = useMemo(() => {
        const scopedItems = currentNavigationItem?.source === 'module'
            ? navigationMenuItems.filter((item) => item.source === 'module' && item.moduleKey === currentNavigationItem.moduleKey)
            : navigationMenuItems.filter((item) => item.section === effectiveSectionKey && item.source !== 'module');

        return scopedItems
            .map((item) => ({
                key: item.key,
                icon: item.icon,
                label: item.label,
            }));
    }, [currentNavigationItem, effectiveSectionKey, navigationMenuItems]);

    const selectedMenuKey = useMemo(() => {
        return currentNavigationItem?.key ?? null;
    }, [currentNavigationItem]);

    const breadcrumbItems = useMemo(() => {
        return [
            activeTopSection ? { title: activeTopSection.label } : null,
            currentNavigationItem ? { title: currentNavigationItem.label } : null,
        ].filter(Boolean);
    }, [activeTopSection, currentNavigationItem]);

    const shouldShowBreadcrumb = useMemo(() => {
        return !isMobile && currentNavigationItem?.key !== 'dashboard';
    }, [currentNavigationItem, isMobile]);

    const shellLoadingTitle = useMemo(() => {
        const normalizedPath = location.pathname.replace(/^\/admin/, '') || '/';
        const matchedItem = navigationItems.find((item) => {
            const route = normalizeRoute(item.route);

            return normalizedPath === route || (route !== '/' && normalizedPath.startsWith(`${route}/`));
        });

        if (matchedItem?.label) {
            return matchedItem.label;
        }

        if (normalizedPath === '/dashboard' || normalizedPath === '/') {
            return 'Trang chủ';
        }

        return 'Đang tải trang';
    }, [location.pathname, navigationItems, normalizeRoute]);

    const siteBranding = currentAdmin?.site_profile?.branding ?? {};
    const sidebarLogoUrl = brandLogoFailed ? '' : (siteBranding.logo_url ?? '');
    const sidebarIdentity = siteBranding.company_name || currentAdmin?.site_profile?.site_name || 'AIO Platform';
    const brandInitials = useMemo(() => {
        const normalizedIdentity = String(sidebarIdentity).trim();

        if (!normalizedIdentity) {
            return 'AP';
        }

        const initials = normalizedIdentity
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join('');

        return initials || normalizedIdentity.slice(0, 2).toUpperCase() || 'AP';
    }, [sidebarIdentity]);
    const fallbackBrandLogoUrl = useMemo(() => {
        const svg = `
            <svg xmlns="http://www.w3.org/2000/svg" width="144" height="48" viewBox="0 0 144 48" fill="none">
                <rect width="144" height="48" rx="14" fill="#0F4C81"/>
                <rect x="4" y="4" width="40" height="40" rx="12" fill="#ffffff" fill-opacity="0.16"/>
                <path d="M112 15c5.523 0 10 4.477 10 10s-4.477 10-10 10c-2.53 0-4.841-.94-6.602-2.488l2.23-2.558A6.964 6.964 0 0 0 112 32a7 7 0 1 0 0-14 6.97 6.97 0 0 0-5.162 2.273l-2.224-2.563A9.963 9.963 0 0 1 112 15Z" fill="#FF7A00"/>
                <text x="24" y="30" text-anchor="middle" fill="#ffffff" font-family="Segoe UI, Arial, sans-serif" font-size="16" font-weight="700">${brandInitials}</text>
                <text x="56" y="29" fill="#ffffff" font-family="Segoe UI, Arial, sans-serif" font-size="16" font-weight="700">${sidebarIdentity}</text>
            </svg>`;

        return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
    }, [brandInitials, sidebarIdentity]);
    const headerLogoUrl = sidebarLogoUrl || fallbackBrandLogoUrl;

    const navigateToMenuItem = useCallback((key) => {
        const target = navigationMenuItems.find((item) => item.key === key);

        if (target) {
            setMobileNavigationOpen(false);
            navigate(target.route);
        }
    }, [navigate, navigationMenuItems]);

    const handleTopMenuClick = ({ key }) => {
        if (isMobile) {
            setMobileSectionKey(key);
            return;
        }

        const firstItemInSection = navigationMenuItems.find((item) => item.section === key);

        if (firstItemInSection) {
            setMobileNavigationOpen(false);
            navigate(firstItemInSection.route);
        }
    };

    const handleAdminLogout = async () => {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        await fetch('/admin/logout', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token ?? '',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });

        window.location.href = '/admin/login';
    };

    const adminActionItems = [
        {
            key: 'website',
            label: 'Website',
        },
        {
            key: 'diagram',
            label: 'Source Diagram',
        },
        {
            key: 'logout',
            label: 'Đăng xuất',
            danger: true,
        },
    ];

    const handleAdminActionClick = async ({ key }) => {
        if (key === 'website') {
            window.location.href = '/';
            return;
        }

        if (key === 'diagram') {
            window.location.href = '/docs/architecture/aio-source-code-structure.svg';
            return;
        }

        if (key === 'logout') {
            await handleAdminLogout();
        }
    };

    const desktopWorkspaceMenuItems = sideMenuItems.map((item) => ({
        key: item.key,
        label: item.label,
        icon: item.icon,
    }));

    return (
        <Layout className="admin-shell">
            <Header className="admin-top-header">
                <div className="admin-top-header-main">
                    {isMobile ? (
                        <Button
                            type="text"
                            className="admin-mobile-nav-trigger"
                            icon={<MenuOutlined />}
                            onClick={() => setMobileNavigationOpen(true)}
                            aria-label="Mở điều hướng admin"
                        />
                    ) : null}

                    <div className="admin-header-brand" onClick={() => navigate(defaultRoute)} role="button" tabIndex={0}>
                        <img
                            className="sidebar-brand-logo admin-header-brand-logo"
                            src={headerLogoUrl}
                            alt={sidebarIdentity}
                            loading="lazy"
                            onError={() => {
                                if (sidebarLogoUrl) {
                                    setBrandLogoFailed(true);
                                }
                            }}
                        />
                    </div>
                </div>

                <Space className="admin-header-actions">
                    {isMobile ? (
                        <Dropdown menu={{ items: adminActionItems, onClick: handleAdminActionClick }} trigger={['click']}>
                            <Button type="text" className="admin-mobile-action-trigger" icon={<MoreOutlined />} aria-label="Mở tác vụ admin" />
                        </Dropdown>
                    ) : (
                        <Space size={10} className="admin-header-actions-desktop">
                            <Dropdown
                                menu={{
                                    items: sectionDropdownItems,
                                    selectable: true,
                                    selectedKeys: [activeTopSectionKey],
                                    onClick: handleTopMenuClick,
                                }}
                                trigger={['click']}
                                placement="bottomRight"
                                overlayClassName="admin-section-switcher-overlay"
                                popupRender={(menuNode) => (
                                    <div className="admin-section-switcher-panel">
                                        <div className="admin-section-switcher-panel-head">
                                            <span className="admin-section-switcher-panel-kicker">Workspace Switcher</span>
                                            <strong>{activeTopSection?.label ?? 'Điều hướng'}</strong>
                                            <span className="admin-section-switcher-panel-description">Chuyển nhanh giữa các nhóm chức năng quản trị chính.</span>
                                        </div>
                                        <div className="admin-section-switcher-panel-body">{menuNode}</div>
                                    </div>
                                )}
                            >
                                <Button type="text" className="admin-section-dropdown-trigger">
                                    <span className="admin-section-dropdown-pill" aria-hidden="true" />
                                    <span className="admin-section-dropdown-label">{activeTopSection?.label ?? 'Điều hướng'}</span>
                                    <span className="admin-section-dropdown-caret" aria-hidden="true" />
                                </Button>
                            </Dropdown>
                            <Button href="/" className="admin-header-utility-button">Website</Button>
                            <Button onClick={handleAdminLogout} className="admin-header-utility-button">Đăng xuất</Button>
                        </Space>
                    )}
                </Space>
            </Header>

            {!isMobile ? (
                <Header className="admin-sub-header">
                    <Menu
                        mode="horizontal"
                        className="admin-workspace-menu"
                        selectedKeys={selectedMenuKey ? [selectedMenuKey] : []}
                        items={desktopWorkspaceMenuItems}
                        onClick={({ key }) => navigateToMenuItem(key)}
                        overflowedIndicator={<MoreOutlined />}
                    />
                </Header>
            ) : null}

            <Drawer
                title="Điều hướng admin"
                placement="left"
                open={isMobile && mobileNavigationOpen}
                onClose={() => setMobileNavigationOpen(false)}
                width={320}
                className="admin-mobile-drawer"
                destroyOnHidden
            >
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Menu
                        mode="inline"
                        className="admin-mobile-section-menu"
                        selectedKeys={[mobileSectionKey ?? activeTopSectionKey]}
                        items={topMenuItems}
                        onClick={handleTopMenuClick}
                    />

                    <Menu
                        mode="inline"
                        className="admin-side-menu admin-mobile-side-menu"
                        selectedKeys={selectedMenuKey ? [selectedMenuKey] : []}
                        items={sideMenuItems}
                        onClick={({ key }) => navigateToMenuItem(key)}
                    />
                </Space>
            </Drawer>

            <Layout className="admin-main-layout">
                <Content className="admin-content">
                    <div className="panel-stack">
                        {loadError ? <Alert type="error" showIcon message={loadError} /> : null}

                        {!shellReady && !loadError ? (
                            <Card loading title={shellLoadingTitle} />
                        ) : (
                            <>
                                {shouldShowBreadcrumb ? <Breadcrumb className="admin-breadcrumb" items={breadcrumbItems} /> : null}

                                <div className="admin-page-shell">
                                    <Routes>
                                        <Route path="/" element={<Navigate to={defaultRoute} replace />} />
                                        <Route path="dashboard" element={hasPermission('platform.dashboard.view') ? renderLazyRouteElement(DashboardRoutePage, { canAccess: true, callAdminApi }, 'Dashboard') : <Navigate to={defaultRoute} replace />} />
                                        <Route path="orders" element={hasPermission('platform.dashboard.view') ? renderLazyRouteElement(OrdersRoutePage, { canAccess: true, callAdminApi }, 'Đơn hàng') : <Navigate to={defaultRoute} replace />} />
                                        <Route path="newsletter" element={hasPermission('platform.dashboard.view') ? renderLazyRouteElement(NewsletterSubscribersRoutePage, { canAccess: true, callAdminApi }, 'Bản tin') : <Navigate to={defaultRoute} replace />} />
                                        <Route path="access" element={hasPermission('rbac.role.view') ? renderLazyRouteElement(AccessRoutePage, { canAccess: true, canManageRoles: hasPermission('rbac.role.manage'), callAdminApi, runAdminAction }, 'Access Control') : <Navigate to={defaultRoute} replace />} />
                                        <Route path="admins" element={hasPermission('admin.account.view') ? renderLazyRouteElement(AdminAccountsRoutePage, { canAccess: true, currentAdmin, permissions: { manage: hasPermission('admin.account.manage'), resetPassword: hasPermission('admin.account.reset_password'), lock: hasPermission('admin.account.lock') }, callAdminApi, runAdminAction }, 'Admin Accounts') : <Navigate to={defaultRoute} replace />} />
                                        <Route path="modules" element={hasPermission('store.module.view') ? renderLazyRouteElement(ModulesRoutePage, { canAccess: true, permissions: { install: hasPermission('store.module.install'), enable: hasPermission('store.module.enable'), disable: hasPermission('store.module.disable'), upgrade: hasPermission('store.module.upgrade'), uninstall: hasPermission('store.module.uninstall') }, callAdminApi, runAdminAction, refreshShell: loadShellData }, 'App Store') : <Navigate to={defaultRoute} replace />} />
                                        <Route path="themes" element={hasPermission('theme.view') ? renderLazyRouteElement(ThemesRoutePage, { canAccess: true, canActivate: hasPermission('theme.activate'), canGenerateDemoData: hasPermission('theme.customize'), callAdminApi, runAdminAction }, 'Themes') : <Navigate to={defaultRoute} replace />} />
                                        <Route path="setup" element={hasPermission('setup.view') ? renderLazyRouteElement(SetupRoutePage, { canAccess: true, canComplete: hasPermission('setup.complete'), callAdminApi, runAdminAction }, 'Setup') : <Navigate to={defaultRoute} replace />} />
                                        {renderModuleRoutes()}
                                        <Route path="*" element={<Navigate to={defaultRoute} replace />} />
                                    </Routes>
                                </div>
                            </>
                        )}
                    </div>
                </Content>
            </Layout>
        </Layout>
    );
}
