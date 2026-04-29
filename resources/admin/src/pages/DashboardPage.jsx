import AppstoreOutlined from '@ant-design/icons/AppstoreOutlined';
import ApartmentOutlined from '@ant-design/icons/ApartmentOutlined';
import DatabaseOutlined from '@ant-design/icons/DatabaseOutlined';
import FileTextOutlined from '@ant-design/icons/FileTextOutlined';
import GlobalOutlined from '@ant-design/icons/GlobalOutlined';
import NotificationOutlined from '@ant-design/icons/NotificationOutlined';
import ReadOutlined from '@ant-design/icons/ReadOutlined';
import SettingOutlined from '@ant-design/icons/SettingOutlined';
import ShopOutlined from '@ant-design/icons/ShopOutlined';
import SkinOutlined from '@ant-design/icons/SkinOutlined';
import Card from 'antd/es/card';
import Empty from 'antd/es/empty';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { useNavigate } from 'react-router-dom';

const { Title, Paragraph, Text } = Typography;

const iconMap = {
    appstore: AppstoreOutlined,
    read: ReadOutlined,
    setting: SettingOutlined,
    shop: ShopOutlined,
    global: GlobalOutlined,
    database: DatabaseOutlined,
    apartment: ApartmentOutlined,
    profile: FileTextOutlined,
    notification: NotificationOutlined,
    skin: SkinOutlined,
};

const moduleColorMap = {
    geekblue: '#315efb',
    green: '#159a62',
    gold: '#c98700',
    cyan: '#0f8c95',
    purple: '#7a4cff',
    orange: '#f1662a',
    blue: '#1677ff',
};

function normalizeAdminRoute(route) {
    if (!route) {
        return '/';
    }

    return /^\/admin(?:\/|$)/.test(route) ? route.replace(/^\/admin(?=\/|$)/, '') || '/' : route;
}

function resolveModuleColor(colorKey) {
    return moduleColorMap[colorKey] ?? '#1677ff';
}

function resolveModuleIcon(iconKey) {
    return iconMap[iconKey] ?? AppstoreOutlined;
}

export default function DashboardPage({ overview }) {
    const navigate = useNavigate();
    const activeModules = overview?.active_modules ?? [];

    const openAppWorkspace = (route) => {
        navigate(route);
    };

    return (
        <Space direction="vertical" size={18} style={{ width: '100%' }}>
            <div className="dashboard-module-heading">
                <div>
                    <Text className="card-label">Danh sách App</Text>
                    <Title level={3} style={{ margin: '4px 0 0' }}>Danh sách App đang bật</Title>
                </div>
                <Tag color="green">{`${activeModules.length} App đang hoạt động`}</Tag>
            </div>

            {activeModules.length ? (
                <div className="dashboard-module-grid">
                    {activeModules.map((moduleCard) => {
                        const IconComponent = resolveModuleIcon(moduleCard.icon);
                        const accentColor = resolveModuleColor(moduleCard.color);
                        const moduleRoute = normalizeAdminRoute(moduleCard.route);
                        const workspaceLabel = moduleCard.key === 'cms' ? 'CMS App' : 'App Workspace';
                        const versionLabel = moduleCard.installed_version ?? moduleCard.latest_version ?? '0.0.0';

                        return (
                            <Card
                                key={moduleCard.key}
                                className="dashboard-module-card dashboard-module-card-clickable"
                                styles={{ body: { padding: 18 } }}
                                style={{ '--dashboard-accent': accentColor }}
                                role="button"
                                tabIndex={0}
                                onClick={() => openAppWorkspace(moduleRoute)}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter' || event.key === ' ') {
                                        event.preventDefault();
                                        openAppWorkspace(moduleRoute);
                                    }
                                }}
                            >
                                <div className="dashboard-module-card-top">
                                    <div className="dashboard-module-card-head">
                                        <div className="dashboard-module-icon-wrap">
                                            <div className="dashboard-module-icon">
                                                <IconComponent />
                                            </div>
                                        </div>

                                        <div className="dashboard-module-copy">
                                            <div className="dashboard-module-title-row">
                                                <Title level={4} style={{ margin: 0 }}>{moduleCard.name}</Title>
                                                <span className="dashboard-module-kicker">{workspaceLabel}</span>
                                            </div>

                                            <Paragraph className="dashboard-module-description" ellipsis={{ rows: 2, expandable: false }}>
                                                {moduleCard.description || 'App đang hoạt động trong workspace hiện tại.'}
                                            </Paragraph>

                                            <Text type="secondary" className="dashboard-module-version-text">
                                                Phiên bản v{versionLabel}
                                            </Text>
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        );
                    })}
                </div>
            ) : (
                <Card>
                    <Empty
                        description="Hiện chưa có App nào đang active cho tài khoản admin này."
                        image={Empty.PRESENTED_IMAGE_SIMPLE}
                    />
                </Card>
            )}
        </Space>
    );
}
