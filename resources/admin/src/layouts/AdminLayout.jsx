import { ADMIN_BASE_PATH, STOREFRONT_ROUTES, adminApi, adminPath } from '../shared/config/routes';
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
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';
import MenuOutlined from '@ant-design/icons/MenuOutlined';
import MoreOutlined from '@ant-design/icons/MoreOutlined';
import HomeOutlined from '@ant-design/icons/HomeOutlined';
import LogoutOutlined from '@ant-design/icons/LogoutOutlined';
import LockOutlined from '@ant-design/icons/LockOutlined';
import { Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom';
import { adminNavigation, adminNavigationSections } from '../shared/config/navigation';
import ChangePasswordModal from '../shared/components/ChangePasswordModal';
import TwoFactorModal from '../shared/components/TwoFactorModal';

const ModuleRoutePage = lazy(() => import('../pages/modules/ModuleRoutePage'));
const DashboardRoutePage = lazy(() => import('../pages/routes/DashboardRoutePage'));
const OrdersRoutePage = lazy(() => import('../pages/routes/OrdersRoutePage'));
const NewsletterSubscribersRoutePage = lazy(() => import('../pages/routes/NewsletterSubscribersRoutePage'));
const AccessRoutePage = lazy(() => import('../pages/routes/AccessRoutePage'));
const AdminAccountsRoutePage = lazy(() => import('../pages/routes/AdminAccountsRoutePage'));
const AuditLogsRoutePage = lazy(() => import('../pages/routes/AuditLogsRoutePage'));
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

const cmsContentMenuOrder = [
    { key: 'cms-pages', label: 'Pages' },
    { key: 'cms-products', label: 'Sản phẩm' },
    { key: 'cms-services', label: 'Dịch vụ' },
    { key: 'cms-posts', label: 'Tin tức' },
    { key: 'cms-projects', label: 'Dự án' },
    { key: 'cms-team-members', label: 'Đội ngũ nhân sự' },
    { key: 'cms-partners', label: 'Đối tác' },
    { key: 'cms-testimonials', label: 'Cảm nhận khách hàng' },
];

const cmsContentMenuKeySet = new Set(cmsContentMenuOrder.map((item) => item.key));
const cmsContentMenuLabelMap = new Map(cmsContentMenuOrder.map((item) => [item.key, item.label]));
const cmsStandaloneMenuLabelMap = new Map([
    ['cms-landing-pages', 'Landing pages'],
    ['cms-orders', 'Đơn đặt hàng'],
    ['cms-newsletter', 'ĐK nhận tin'],
]);

function withCmsMenuLabel(item) {
    return {
        ...item,
        label: cmsContentMenuLabelMap.get(item.key) ?? cmsStandaloneMenuLabelMap.get(item.key) ?? item.label,
    };
}

function buildWorkspaceMenuItems(items) {
    const contentItems = cmsContentMenuOrder
        .map(({ key, label }) => {
            const item = items.find((candidate) => candidate.key === key);

            return item ? { ...item, label } : null;
        })
        .filter(Boolean);

    if (!contentItems.length) {
        return items.map(withCmsMenuLabel);
    }

    const firstContentIndex = items.findIndex((item) => cmsContentMenuKeySet.has(item.key));
    const beforeContent = items
        .slice(0, Math.max(firstContentIndex, 0))
        .filter((item) => !cmsContentMenuKeySet.has(item.key))
        .map(withCmsMenuLabel);
    const afterContent = items
        .slice(Math.max(firstContentIndex, 0))
        .filter((item) => !cmsContentMenuKeySet.has(item.key))
        .map(withCmsMenuLabel);

    return [
        ...beforeContent,
        {
            key: 'cms-content-group',
            label: 'Nội dung',
            icon: <AppstoreOutlined />,
            children: contentItems,
        },
        ...afterContent,
    ];
}

function toAntMenuItem(item) {
    return {
        key: item.key,
        label: item.label,
        icon: item.icon,
        children: item.children?.map(toAntMenuItem),
    };
}

function normalizeWebsiteSearch(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[đĐ]/g, 'd')
        .toLowerCase()
        .trim();
}

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
    const [frontendLocale, setFrontendLocale] = useState(() => window.localStorage.getItem('aio.frontendLocale') || 'vi');
    const [selectedAdminWebsiteKey, setSelectedAdminWebsiteKey] = useState(() => window.localStorage.getItem('aio.admin.websiteKey') || 'website-main');
    const [currentAdmin, setCurrentAdmin] = useState(null);
    const [modules, setModules] = useState([]);
    const [loadError, setLoadError] = useState(null);
    const [shellReady, setShellReady] = useState(false);
    const [brandLogoFailed, setBrandLogoFailed] = useState(false);
    const [mobileNavigationOpen, setMobileNavigationOpen] = useState(false);
    const [mobileSectionKey, setMobileSectionKey] = useState(null);
    const [changePasswordOpen, setChangePasswordOpen] = useState(false);
    const [twoFactorOpen, setTwoFactorOpen] = useState(false);
    const location = useLocation();
    const navigate = useNavigate();
    const isMobile = !screens.lg;
    const frontendLocaleRecords = currentAdmin?.frontend_localization?.locales ?? [];
    const frontendLocaleOptions = frontendLocaleRecords
        .filter((localeItem) => localeItem.is_enabled_for_editing && localeItem.is_published)
        .map((localeItem) => localeItem.code);
    const defaultFrontendLocale = currentAdmin?.frontend_localization?.default_locale ?? 'vi';

    useEffect(() => {
        if (currentAdmin?.must_change_password) {
            setChangePasswordOpen(true);
        }
    }, [currentAdmin?.must_change_password]);

    const callAdminApi = useCallback(async (url, options = {}) => {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const isFormData = options.body instanceof FormData;
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': token ?? '',
                Accept: 'application/json',
                'X-Website-Key': selectedAdminWebsiteKey,
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
                const firstValidationError = Object.values(payload.errors ?? {})
                    .flat()
                    .find(Boolean);

                if (firstValidationError) {
                    errorMessage = String(firstValidationError);
                }
            } catch {
                // Ignore invalid JSON body.
            }

            throw new Error(errorMessage);
        }

        if (response.status === 204) {
            return null;
        }

        return response.json();
    }, [selectedAdminWebsiteKey]);

    const hasPermission = useCallback((permission) => (currentAdmin?.permissions ?? []).includes(permission), [currentAdmin]);

    const loadShellData = useCallback(async () => {
        try {
            setLoadError(null);

            const mePayload = await callAdminApi(adminApi('me'));
            const nextCurrentAdmin = mePayload.data ?? null;
            const resolvedWebsiteKey = nextCurrentAdmin?.current_website?.website_key;

            setCurrentAdmin(nextCurrentAdmin);
            setShellReady(true);

            if (resolvedWebsiteKey && resolvedWebsiteKey !== selectedAdminWebsiteKey) {
                window.localStorage.setItem('aio.admin.websiteKey', resolvedWebsiteKey);
                setSelectedAdminWebsiteKey(resolvedWebsiteKey);
            }

            if ((nextCurrentAdmin?.permissions ?? []).includes('store.module.view')) {
                try {
                    const modulePayload = await callAdminApi(adminApi('modules'));
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

    useEffect(() => {
        window.localStorage.setItem('aio.frontendLocale', frontendLocale);
    }, [frontendLocale]);

    useEffect(() => {
        window.localStorage.setItem('aio.admin.websiteKey', selectedAdminWebsiteKey);
    }, [selectedAdminWebsiteKey]);

    useEffect(() => {
        if (!frontendLocaleOptions.length) {
            return;
        }

        if (!frontendLocaleOptions.includes(frontendLocale)) {
            setFrontendLocale(defaultFrontendLocale);
        }
    }, [defaultFrontendLocale, frontendLocale, frontendLocaleOptions]);

    useEffect(() => {
        const handleLocalizationChanged = (event) => {
            const detail = event.detail ?? {};
            const nextLocales = detail.locales ?? [];
            const nextActiveLocales = nextLocales
                .filter((localeItem) => localeItem.is_enabled_for_editing && localeItem.is_published)
                .map((localeItem) => localeItem.code);
            const nextDefaultLocale = detail.default_locale ?? defaultFrontendLocale;

            setCurrentAdmin((current) => {
                if (!current) {
                    return current;
                }

                return {
                    ...current,
                    frontend_localization: {
                        ...(current.frontend_localization ?? {}),
                        default_locale: nextDefaultLocale,
                        fallback_locale: detail.fallback_locale ?? current.frontend_localization?.fallback_locale,
                        source_locale: detail.source_locale ?? current.frontend_localization?.source_locale,
                        locales: nextLocales.length ? nextLocales : (current.frontend_localization?.locales ?? []),
                        active_locales: nextActiveLocales.length ? nextActiveLocales : (current.frontend_localization?.active_locales ?? []),
                    },
                };
            });

            setFrontendLocale((current) => (nextActiveLocales.includes(current) ? current : nextDefaultLocale));
        };

        window.addEventListener('aio:frontend-localization-changed', handleLocalizationChanged);

        return () => {
            window.removeEventListener('aio:frontend-localization-changed', handleLocalizationChanged);
        };
    }, [defaultFrontendLocale]);

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
                label: item.key === 'cms-posts' ? 'Tin tức' : item.label,
                section: item.section ?? 'workspace',
            }))),
        ]
            // filter out any module-provided link to the setup page to keep CMS menu tidy
            .filter((item) => {
                const route = String(item.route ?? '').replace(/\/?$/, '');
                if (item.key === 'cms-categories' || route === adminPath('cms/categories')) {
                    return false;
                }
                if (route === adminPath('cms/setup') || route === adminPath('setup') || item.label === 'Cài đặt website') {
                    return false;
                }

                return true;
            })
            .filter((item) => !item.permission || hasPermission(item.permission));
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
        return navigationItems.filter((item) => item.source === 'module').map((item) => {
            const route = normalizeRoute(item.route);
            const modulePayload = modules.find((moduleItem) => moduleItem.key === item.module_key)
                ?? (item.module_key
                    ? {
                        key: item.module_key,
                        name: item.label ?? item.module_key,
                        description: item.description ?? '',
                    }
                    : null);

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
    }, [callAdminApi, modules, navigationItems, normalizeRoute, runAdminAction]);

    const navigationMenuItems = useMemo(() => {
        return navigationItems.map((item) => ({
            key: item.key,
            label: item.label,
            section: item.section ?? 'workspace',
            icon: resolveNavigationIcon(item.key, item.icon),
            route: normalizeRoute(item.route),
            source: item.source ?? 'static',
            moduleKey: item.module_key ?? null,
            hidden: item.hidden === true,
        }));
    }, [navigationItems, normalizeRoute, resolveNavigationIcon]);

    // Legacy storefronts still consume these routes, but modern themes configure
    // their equivalent content through Landing pages, so hidden items remain routable.
    const visibleNavigationMenuItems = useMemo(
        () => navigationMenuItems.filter((item) => !item.hidden),
        [navigationMenuItems],
    );

    const currentNavigationItem = useMemo(() => {
        return navigationMenuItems.find((item) => location.pathname === item.route)
            ?? navigationMenuItems.find((item) => item.route !== '/' && location.pathname.startsWith(`${item.route}/`))
            ?? null;
    }, [location.pathname, navigationMenuItems]);

    const availableTopSections = useMemo(() => {
        return adminNavigationSections.filter((section) => visibleNavigationMenuItems.some((item) => item.section === section.key));
    }, [visibleNavigationMenuItems]);

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
            const sectionItemCount = visibleNavigationMenuItems.filter((item) => item.section === section.key).length;

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
    }, [availableTopSections, visibleNavigationMenuItems]);

    const activeTopSection = useMemo(() => {
        return availableTopSections.find((section) => section.key === activeTopSectionKey) ?? null;
    }, [activeTopSectionKey, availableTopSections]);

    const effectiveSectionKey = isMobile ? (mobileSectionKey ?? activeTopSectionKey) : activeTopSectionKey;

    const sideMenuItems = useMemo(() => {
        const scopedItems = currentNavigationItem?.source === 'module'
            ? visibleNavigationMenuItems.filter((item) => item.source === 'module' && item.moduleKey === currentNavigationItem.moduleKey)
            : visibleNavigationMenuItems.filter((item) => item.section === effectiveSectionKey && item.source !== 'module');

        return buildWorkspaceMenuItems(scopedItems);
    }, [currentNavigationItem, effectiveSectionKey, visibleNavigationMenuItems]);

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
        const normalizedPath = location.pathname.startsWith(ADMIN_BASE_PATH)
            ? location.pathname.slice(ADMIN_BASE_PATH.length) || '/'
            : location.pathname;
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
        return `https://htvietnam.vn/layouts/HTVietNam/images/logo.png`;
    }, [brandInitials, sidebarIdentity]);
    const headerLogoUrl = sidebarLogoUrl || fallbackBrandLogoUrl;

    const navigateToMenuItem = useCallback((key) => {
        const target = visibleNavigationMenuItems.find((item) => item.key === key);

        if (target) {
            setMobileNavigationOpen(false);
            navigate(target.route);
        }
    }, [navigate, visibleNavigationMenuItems]);

    const handleTopMenuClick = ({ key }) => {
        if (isMobile) {
            setMobileSectionKey(key);
            return;
        }

        const firstItemInSection = visibleNavigationMenuItems.find((item) => item.section === key);

        if (firstItemInSection) {
            setMobileNavigationOpen(false);
            navigate(firstItemInSection.route);
        }
    };

    const handleAdminLogout = async () => {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        await fetch(adminPath('logout'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token ?? '',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });

        window.location.href = STOREFRONT_ROUTES.home;
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

    const desktopWorkspaceMenuItems = sideMenuItems.map(toAntMenuItem);

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
                            <Select
                                value={selectedAdminWebsiteKey}
                                style={{ minWidth: 220 }}
                                popupMatchSelectWidth={false}
                                showSearch
                                optionFilterProp="label"
                                filterOption={(input, option) => (
                                    normalizeWebsiteSearch(option?.label).includes(normalizeWebsiteSearch(input))
                                )}
                                placeholder="Tìm theo domain"
                                onChange={(value) => {
                                    setSelectedAdminWebsiteKey(value);
                                    window.localStorage.setItem('aio.admin.websiteKey', value);

                                    try {
                                        Object.keys(window.sessionStorage)
                                            .filter((key) => key.startsWith('admin.route.'))
                                            .forEach((key) => window.sessionStorage.removeItem(key));
                                    } catch {
                                        // Ignore storage cleanup failures.
                                    }

                                    window.location.reload();
                                }}
                                options={(currentAdmin?.site_options?.length ? currentAdmin.site_options : [{ website_key: selectedAdminWebsiteKey, label: selectedAdminWebsiteKey }]).map((site) => ({
                                    value: site.website_key,
                                    label: site.domain || (site.website_key === 'website-main' ? 'Website mặc định' : 'Chưa cấu hình domain'),
                                }))}
                            />
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
                                            <Space size={8} wrap>
                                                <Text type="secondary">Frontend locale</Text>
                                                {(frontendLocaleOptions.length ? frontendLocaleOptions : [frontendLocale]).map((localeOption) => (
                                                    <Button
                                                        key={localeOption}
                                                        size="small"
                                                        type={frontendLocale === localeOption ? 'primary' : 'default'}
                                                        onClick={(event) => {
                                                            event.preventDefault();
                                                            event.stopPropagation();
                                                            setFrontendLocale(localeOption);
                                                        }}
                                                    >
                                                        {localeOption.toUpperCase()}
                                                    </Button>
                                                ))}
                                            </Space>
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
                            <Button
                                type={location.pathname === adminPath('setup') ? 'primary' : 'default'}
                                className="admin-header-utility-button admin-header-settings-button"
                                icon={<SettingOutlined />}
                                href={adminPath('setup')}
                                aria-label="Cài đặt website"
                                title="Cài đặt website"
                            />
                            <Button href={STOREFRONT_ROUTES.home} target="_blank" rel="noopener noreferrer" className="admin-header-utility-button" icon={<HomeOutlined />} aria-label="Website">Website</Button>

                            <Dropdown
                                menu={{
                                    items: [
                                        { key: 'change_password', label: 'Đổi mật khẩu', icon: <LockOutlined /> },
                                        { key: 'two_factor', label: 'Xác thực hai lớp', icon: <SafetyCertificateOutlined /> },
                                        { key: 'logout', label: 'Đăng xuất', icon: <LogoutOutlined />, danger: true },
                                    ],
                                    onClick: ({ key }) => {
                                        if (key === 'change_password') {
                                            setChangePasswordOpen(true);
                                        }

                                        if (key === 'two_factor') {
                                            setTwoFactorOpen(true);
                                        }

                                        if (key === 'logout') {
                                            handleAdminLogout();
                                        }
                                    },
                                }}
                                trigger={['click']}
                                placement="bottomRight"
                            >
                                <Button className="admin-header-utility-button" icon={<MoreOutlined />} aria-label="Tài khoản">Tài khoản</Button>
                            </Dropdown>
                        </Space>
                    )}
                </Space>
            </Header>

            <ChangePasswordModal
                open={changePasswordOpen}
                onClose={() => setChangePasswordOpen(false)}
                callAdminApi={callAdminApi}
                runAdminAction={runAdminAction}
                forceChange={Boolean(currentAdmin?.must_change_password)}
            />
            <TwoFactorModal
                open={twoFactorOpen}
                enabled={Boolean(currentAdmin?.two_factor_enabled)}
                onClose={() => setTwoFactorOpen(false)}
                onChanged={(enabled) => setCurrentAdmin((current) => current ? { ...current, two_factor_enabled: enabled } : current)}
                callAdminApi={callAdminApi}
            />

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
                                        <Route path="orders" element={hasPermission('cms.order.view') ? renderLazyRouteElement(OrdersRoutePage, { canAccess: true, callAdminApi }, 'Đơn hàng') : <Navigate to={defaultRoute} replace />} />
                                        <Route path="newsletter" element={hasPermission('cms.newsletter.view') ? renderLazyRouteElement(NewsletterSubscribersRoutePage, { canAccess: true, callAdminApi }, 'Bản tin') : <Navigate to={defaultRoute} replace />} />
                                        <Route path="access" element={hasPermission('rbac.role.view') ? renderLazyRouteElement(AccessRoutePage, { canAccess: true, canManageRoles: hasPermission('rbac.role.manage'), callAdminApi, runAdminAction }, 'Access Control') : <Navigate to={defaultRoute} replace />} />
                                        <Route path="admins" element={hasPermission('admin.account.view') ? renderLazyRouteElement(AdminAccountsRoutePage, { canAccess: true, currentAdmin, permissions: { manage: hasPermission('admin.account.manage'), resetPassword: hasPermission('admin.account.reset_password'), lock: hasPermission('admin.account.lock') }, callAdminApi, runAdminAction }, 'Admin Accounts') : <Navigate to={defaultRoute} replace />} />
                                        <Route path="audit-logs" element={hasPermission('admin.audit.view') ? renderLazyRouteElement(AuditLogsRoutePage, { canAccess: true, callAdminApi }, 'Nhật ký bảo mật') : <Navigate to={defaultRoute} replace />} />
                                        <Route path="modules" element={hasPermission('store.module.view') ? renderLazyRouteElement(ModulesRoutePage, { canAccess: true, permissions: { install: hasPermission('store.module.install'), enable: hasPermission('store.module.enable'), disable: hasPermission('store.module.disable'), upgrade: hasPermission('store.module.upgrade'), uninstall: hasPermission('store.module.uninstall'), demoData: hasPermission('store.module.upgrade') }, callAdminApi, runAdminAction, refreshShell: loadShellData }, 'App Store') : <Navigate to={defaultRoute} replace />} />
                                        <Route path="themes" element={hasPermission('theme.view') ? renderLazyRouteElement(ThemesRoutePage, { canAccess: true, canActivate: hasPermission('theme.activate'), canGenerateDemoData: hasPermission('theme.customize'), callAdminApi, runAdminAction, frontendLocale, defaultFrontendLocale }, 'Themes') : <Navigate to={defaultRoute} replace />} />
                                        <Route path="setup" element={hasPermission('setup.view') ? renderLazyRouteElement(SetupRoutePage, { canAccess: true, canComplete: hasPermission('setup.complete'), canViewThemeManager: hasPermission('theme.view'), canManageThemeActions: hasPermission('theme.customize'), callAdminApi, runAdminAction, frontendLocale, defaultFrontendLocale }, 'Setup') : <Navigate to={defaultRoute} replace />} />
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
