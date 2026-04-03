import { useCallback, useEffect, useState } from 'react';
import { Alert, App, Button, Card, Layout, Space, Statistic, Tag, Typography } from 'antd';
import DashboardPage from '../pages/DashboardPage';
import ModuleStorePage from '../modules/store/pages/ModuleStorePage';
import SetupWizardPage from '../modules/setup/pages/SetupWizardPage';
import ThemeManagerPage from '../modules/themes/pages/ThemeManagerPage';
import { adminNavigation } from '../shared/config/navigation';

const { Header, Content, Sider } = Layout;
const { Title, Paragraph, Text } = Typography;

export default function AdminLayout() {
    const { message } = App.useApp();
    const [overview, setOverview] = useState(null);
    const [modules, setModules] = useState([]);
    const [themes, setThemes] = useState([]);
    const [setup, setSetup] = useState(null);
    const [loadError, setLoadError] = useState(null);

    const loadAdminData = useCallback(async () => {
        try {
            setLoadError(null);

            const [overviewResponse, modulesResponse, themesResponse, setupResponse] = await Promise.all([
                fetch('/admin/api/dashboard', { credentials: 'same-origin' }),
                fetch('/admin/api/modules', { credentials: 'same-origin' }),
                fetch('/admin/api/themes', { credentials: 'same-origin' }),
                fetch('/admin/api/setup', { credentials: 'same-origin' }),
            ]);

            const [overviewPayload, modulesPayload, themesPayload, setupPayload] = await Promise.all([
                overviewResponse.json(),
                modulesResponse.json(),
                themesResponse.json(),
                setupResponse.json(),
            ]);

            setOverview(overviewPayload);
            setModules(modulesPayload.data ?? []);
            setThemes(themesPayload.data ?? []);
            setSetup(setupPayload.data ?? null);
        } catch (error) {
            setLoadError(error instanceof Error ? error.message : 'Khong tai duoc du lieu admin.');
        }
    }, []);

    useEffect(() => {
        loadAdminData();
    }, [loadAdminData]);

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

        if (! response.ok) {
            let errorMessage = 'Khong thuc hien duoc thao tac.';

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

    const runAdminAction = useCallback(async (executor, successMessage) => {
        try {
            await executor();
            await loadAdminData();
            message.success(successMessage);
        } catch (error) {
            message.error(error instanceof Error ? error.message : 'Khong thuc hien duoc thao tac.');
        }
    }, [loadAdminData, message]);

    const handleModuleAction = useCallback((moduleKey, action) => {
        const endpointMap = {
            install: { url: `/admin/api/modules/${moduleKey}/install`, method: 'POST', success: 'Da cai dat module.' },
            enable: { url: `/admin/api/modules/${moduleKey}/enable`, method: 'POST', success: 'Da kich hoat module.' },
            disable: { url: `/admin/api/modules/${moduleKey}/disable`, method: 'POST', success: 'Da tat module.' },
            uninstall: { url: `/admin/api/modules/${moduleKey}`, method: 'DELETE', success: 'Da go module.' },
        };

        const target = endpointMap[action];

        if (! target) {
            return;
        }

        runAdminAction(() => callAdminApi(target.url, { method: target.method }), target.success);
    }, [callAdminApi, runAdminAction]);

    const handleThemeActivate = useCallback((themeKey) => {
        runAdminAction(
            () => callAdminApi(`/admin/api/themes/${themeKey}/activate`, { method: 'POST' }),
            'Da kich hoat theme.',
        );
    }, [callAdminApi, runAdminAction]);

    const handleSetupSave = useCallback((payload) => {
        runAdminAction(
            () => callAdminApi('/admin/api/setup', { method: 'PUT', body: JSON.stringify(payload) }),
            'Da luu cau hinh setup.',
        );
    }, [callAdminApi, runAdminAction]);

    const handleSetupStepComplete = useCallback((stepKey) => {
        runAdminAction(
            () => callAdminApi(`/admin/api/setup/steps/${stepKey}`, { method: 'POST' }),
            'Da cap nhat buoc setup.',
        );
    }, [callAdminApi, runAdminAction]);

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
                        Base source quan tri module, theme, setup wizard va phan quyen cho he sinh thai website.
                    </Paragraph>
                </div>

                <Space direction="vertical" size={12} className="nav-stack">
                    {adminNavigation.map((item) => (
                        <div className="nav-link" key={item.key}>
                            <div className="nav-link-label">
                                <strong>{item.label}</strong>
                                <span>{item.description}</span>
                            </div>
                            <Tag color={item.color}>{item.badge}</Tag>
                        </div>
                    ))}
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
                            Dang xuat
                        </Button>
                        <Button type="primary" href="/docs/architecture/aio-source-code-structure.svg" size="large">
                            Source Diagram
                        </Button>
                    </Space>
                </Header>

                <Content className="admin-content">
                    <div className="panel-stack">
                        {loadError ? <Alert type="error" showIcon message={loadError} /> : null}

                        <Card>
                            <Space size={24} wrap>
                                <Statistic title="Core Platform" value="Laravel 13" />
                                <Statistic title="Admin UI" value="React + AntD" />
                                <Statistic title="Modules Registered" value={overview?.metrics?.modules ?? 0} />
                                <Statistic title="Themes Registered" value={overview?.metrics?.themes ?? 0} />
                            </Space>
                        </Card>

                        <DashboardPage overview={overview} />
                        <ModuleStorePage modules={modules} onAction={handleModuleAction} />
                        <ThemeManagerPage themes={themes} onActivate={handleThemeActivate} />
                        <SetupWizardPage setup={setup} onSaveProfile={handleSetupSave} onCompleteStep={handleSetupStepComplete} />
                    </div>
                </Content>
            </Layout>
        </Layout>
    );
}
