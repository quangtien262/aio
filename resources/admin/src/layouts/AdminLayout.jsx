import { Suspense, lazy, useCallback, useEffect, useMemo, useState } from 'react';
import Alert from 'antd/es/alert';
import App from 'antd/es/app';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Layout from 'antd/es/layout';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { Link, Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { adminNavigation } from '../shared/config/navigation';

const ModuleRoutePage = lazy(() => import('../pages/modules/ModuleRoutePage'));
const DashboardRoutePage = lazy(() => import('../pages/routes/DashboardRoutePage'));
const AccessRoutePage = lazy(() => import('../pages/routes/AccessRoutePage'));
const AdminAccountsRoutePage = lazy(() => import('../pages/routes/AdminAccountsRoutePage'));
const ModulesRoutePage = lazy(() => import('../pages/routes/ModulesRoutePage'));
const ThemesRoutePage = lazy(() => import('../pages/routes/ThemesRoutePage'));
const SetupRoutePage = lazy(() => import('../pages/routes/SetupRoutePage'));

const { Header, Content, Sider } = Layout;
const { Title, Paragraph, Text } = Typography;

function renderLazyRouteElement(Component, props, fallbackTitle) {
    return (
        <Suspense fallback={<Card loading title={fallbackTitle} />}>
            <Component {...props} />
        </Suspense>
    );
}

export default function AdminLayout() {
    const { message } = App.useApp();
    const [currentAdmin, setCurrentAdmin] = useState(null);
    const [modules, setModules] = useState([]);
    const [loadError, setLoadError] = useState(null);
    const [shellReady, setShellReady] = useState(false);
    const location = useLocation();

    const callAdminApi = useCallback(async (url, options = {}) => {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': token ?? '',
                Accept: 'application/json',
                'Content-Type': 'application/json',
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
        return [...adminNavigation, ...(currentAdmin?.module_navigation ?? [])]
            .filter((item) => !item.permission || hasPermission(item.permission));
    }, [currentAdmin, hasPermission]);

    const defaultRoute = navigationItems[0]?.route ?? '/dashboard';

    const normalizeRoute = useCallback((route) => {
        if (!route) {
            return '/';
        }

        return route.startsWith('/admin') ? route.replace('/admin', '') || '/' : route;
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

    return (
        <Layout className="admin-shell">
            <Sider width={276} theme="light" className="admin-sider">
                <div className="brand-block">
                    <Text className="brand-kicker">AIO Platform</Text>
                    <Title level={3}>HT Viet Nam</Title>
                    <Paragraph>
                        Base source quản trị module, theme, setup wizard và phân quyền cho hệ sinh thái website.
                    </Paragraph>
                </div>

                <Space direction="vertical" size={12} className="nav-stack">
                    {navigationItems.map((item) => {
                        const itemRoute = normalizeRoute(item.route);
                        const isActive = location.pathname === itemRoute;

                        return (
                            <Link className="nav-link" key={item.key} to={itemRoute}>
                                <div className="nav-link-label">
                                    <strong>{item.label}</strong>
                                    <span>{item.description}</span>
                                </div>
                                <Tag color={isActive ? item.color : 'default'}>{item.badge}</Tag>
                            </Link>
                        );
                    })}
                </Space>
            </Sider>

            <Layout>
                <Header className="admin-header">
                    <div>
                        <Text className="header-label">Admin Shell</Text>
                        <Title level={4}>Laravel 13 + React + Vite + Ant Design</Title>
                    </div>
                    <Space>
                        <Button href="/" size="large">
                            Website
                        </Button>
                        <Button onClick={handleAdminLogout} size="large">
                            Đăng xuất
                        </Button>
                        <Button type="primary" href="/docs/architecture/aio-source-code-structure.svg" size="large">
                            Source Diagram
                        </Button>
                    </Space>
                </Header>

                <Content className="admin-content">
                    <div className="panel-stack">
                        {loadError ? <Alert type="error" showIcon message={loadError} /> : null}

                        {!shellReady && !loadError ? (
                            <Card loading title="Đang khởi tạo admin shell" />
                        ) : (
                            <Routes>
                                <Route path="/" element={<Navigate to={defaultRoute} replace />} />
                                <Route path="dashboard" element={hasPermission('platform.dashboard.view') ? renderLazyRouteElement(DashboardRoutePage, { canAccess: true, callAdminApi }, 'Dashboard') : <Navigate to={defaultRoute} replace />} />
                                <Route path="access" element={hasPermission('rbac.role.view') ? renderLazyRouteElement(AccessRoutePage, { canAccess: true, canManageRoles: hasPermission('rbac.role.manage'), callAdminApi, runAdminAction }, 'Access Control') : <Navigate to={defaultRoute} replace />} />
                                <Route path="admins" element={hasPermission('admin.account.view') ? renderLazyRouteElement(AdminAccountsRoutePage, { canAccess: true, currentAdmin, permissions: { manage: hasPermission('admin.account.manage'), resetPassword: hasPermission('admin.account.reset_password'), lock: hasPermission('admin.account.lock') }, callAdminApi, runAdminAction }, 'Admin Accounts') : <Navigate to={defaultRoute} replace />} />
                                <Route path="modules" element={hasPermission('store.module.view') ? renderLazyRouteElement(ModulesRoutePage, { canAccess: true, permissions: { install: hasPermission('store.module.install'), enable: hasPermission('store.module.enable'), disable: hasPermission('store.module.disable'), upgrade: hasPermission('store.module.upgrade'), uninstall: hasPermission('store.module.uninstall') }, callAdminApi, runAdminAction, refreshShell: loadShellData }, 'Module Store') : <Navigate to={defaultRoute} replace />} />
                                <Route path="themes" element={hasPermission('theme.view') ? renderLazyRouteElement(ThemesRoutePage, { canAccess: true, canActivate: hasPermission('theme.activate'), callAdminApi, runAdminAction }, 'Themes') : <Navigate to={defaultRoute} replace />} />
                                <Route path="setup" element={hasPermission('setup.view') ? renderLazyRouteElement(SetupRoutePage, { canAccess: true, canComplete: hasPermission('setup.complete'), callAdminApi, runAdminAction }, 'Setup') : <Navigate to={defaultRoute} replace />} />
                                {renderModuleRoutes()}
                                <Route path="*" element={<Navigate to={defaultRoute} replace />} />
                            </Routes>
                        )}
                    </div>
                </Content>
            </Layout>
        </Layout>
    );
}
