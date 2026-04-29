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

const { Header, Content, Sider } = Layout;
const { Text } = Typography;
const { useBreakpoint } = Grid;

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

            if ((nextCurrentAdmin?.permissions ?? []).includes('store.module.view')) {
                const modulePayload = await callAdminApi('/admin/api/modules');
                setModules(modulePayload.data ?? []);
            } else {
                setModules([]);
            }
        } catch (error) {
            setLoadError(error instanceof Error ? error.message : 'Không tải được dữ liệu admin.');
        } finally {
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
                    path={route === '/' ? '/' : route.replace(/^\//, '')}
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
        const activeSection = availableTopSections.find((section) => section.key === activeTopSectionKey);

        return [
            activeSection ? { title: activeSection.label } : null,
            currentNavigationItem ? { title: currentNavigationItem.label } : null,
        ].filter(Boolean);
    }, [activeTopSectionKey, availableTopSections, currentNavigationItem]);

    const siteBranding = currentAdmin?.site_profile?.branding ?? {};
    const sidebarLogoUrl = brandLogoFailed ? '' : (siteBranding.logo_url ?? '');
    const sidebarIdentity = siteBranding.company_name || currentAdmin?.site_profile?.site_name || 'AIO Platform';

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

    return (
        <Layout className="admin-shell">
            <Header className="admin-top-header">
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
                    {sidebarLogoUrl ? (
                        <img
                            className="sidebar-brand-logo"
                            src={sidebarLogoUrl}
                            alt={sidebarIdentity}
                            loading="lazy"
                            onError={() => setBrandLogoFailed(true)}
                        />
                    ) : (
                        <div className="sidebar-brand-fallback admin-header-brand-fallback">
                            <Text>{sidebarIdentity}</Text>
                        </div>
                    )}
                </div>

                {!isMobile ? (
                    <Menu
                        mode="horizontal"
                        className="admin-top-menu"
                        selectedKeys={[activeTopSectionKey]}
                        items={topMenuItems}
                        onClick={handleTopMenuClick}
                    />
                ) : null}

                <Space className="admin-header-actions">
                    {isMobile ? (
                        <Dropdown menu={{ items: adminActionItems, onClick: handleAdminActionClick }} trigger={['click']}>
                            <Button type="text" className="admin-mobile-action-trigger" icon={<MoreOutlined />} aria-label="Mở tác vụ admin" />
                        </Dropdown>
                    ) : (
                        <>
                            <Button href="/">Website</Button>
                            <Button onClick={handleAdminLogout}>Đăng xuất</Button>
                            <Button type="primary" href="/docs/architecture/aio-source-code-structure.svg">
                                Source Diagram
                            </Button>
                        </>
                    )}
                </Space>
            </Header>

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

            <Layout>
                <Sider width={272} theme="light" className="admin-sider admin-left-sider" breakpoint="lg" collapsedWidth={0} collapsed={isMobile} trigger={null}>
                    <div className="nav-stack nav-stack-wide">
                        <Menu
                            mode="inline"
                            className="admin-side-menu"
                            selectedKeys={selectedMenuKey ? [selectedMenuKey] : []}
                            items={sideMenuItems}
                            onClick={({ key }) => navigateToMenuItem(key)}
                        />
                    </div>
                </Sider>

                <Layout className="admin-main-layout">
                    <Content className="admin-content">
                        <div className="panel-stack">
                            {loadError ? <Alert type="error" showIcon message={loadError} /> : null}

                            {!shellReady && !loadError ? (
                                <Card loading title="Đang khởi tạo admin shell" />
                            ) : (
                                <>
                                    {!isMobile ? <Breadcrumb className="admin-breadcrumb" items={breadcrumbItems} /> : null}

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
        </Layout>
    );
}
