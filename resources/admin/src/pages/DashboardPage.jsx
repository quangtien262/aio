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
import { useMemo } from 'react';
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
    const dashboardMetrics = useMemo(() => ([
        {
            label: 'Module Active',
            value: activeModules.length,
        },
        {
            label: 'Theme hiện tại',
            value: overview?.setup?.active_theme_key ?? 'Chưa kích hoạt',
        },
        {
            label: 'Website Type',
            value: overview?.setup?.website_type ?? 'Chưa cấu hình',
        },
        {
            label: 'Trạng thái Setup',
            value: overview?.setup?.is_setup_completed ? 'Completed' : 'In Progress',
        },
    ]), [activeModules.length, overview?.setup?.active_theme_key, overview?.setup?.is_setup_completed, overview?.setup?.website_type]);

    return (
        <Space direction="vertical" size={20} style={{ width: '100%' }}>
            <Card className="dashboard-hero-card">
                <Text className="card-label">Active Workspace</Text>
                <Title level={2}>Toàn bộ module đang hoạt động được đưa ra ngay tại dashboard để vào việc nhanh.</Title>
                <Paragraph>
                    Dashboard này ưu tiên thao tác thực tế: thấy ngay module nào đang active, vào đúng màn quản trị của module đó bằng một chạm,
                    đồng thời vẫn giữ lại snapshot tổng quan của platform, theme và setup state.
                </Paragraph>

                <div className="metric-grid">
                    {dashboardMetrics.map((item) => (
                        <div key={item.label} className="metric-tile">
                            <Text className="metric-label">{item.label}</Text>
                            <Title level={3} style={{ margin: 0 }}>{item.value}</Title>
                        </div>
                    ))}
                </div>
            </Card>

            <div className="dashboard-module-heading">
                <div>
                    <Text className="card-label">Active Modules</Text>
                    <Title level={3} style={{ margin: '4px 0 0' }}>Danh sách module đang bật</Title>
                </div>
                <Tag color="green">{`${activeModules.length} modules active`}</Tag>
            </div>

            {activeModules.length ? (
                <div className="dashboard-module-grid">
                    {activeModules.map((moduleCard) => {
                        const IconComponent = resolveModuleIcon(moduleCard.icon);
                        const accentColor = resolveModuleColor(moduleCard.color);

                        return (
                            <Card
                                key={moduleCard.key}
                                className="dashboard-module-card"
                                styles={{ body: { padding: 22 } }}
                                style={{ '--dashboard-accent': accentColor }}
                            >
                                <div className="dashboard-module-card-top">
                                    <div className="dashboard-module-icon-wrap">
                                        <div className="dashboard-module-icon">
                                            <IconComponent />
                                        </div>
                                    </div>

                                    <div className="dashboard-module-copy">
                                        <div className="dashboard-module-title-row">
                                            <Title level={4} style={{ margin: 0 }}>{moduleCard.name}</Title>
                                            <Tag color="green">active</Tag>
                                        </div>

                                        <Paragraph className="dashboard-module-description">
                                            {moduleCard.description || 'Module đang hoạt động trong workspace hiện tại.'}
                                        </Paragraph>

                                        <Space wrap size={[8, 8]}>
                                            {(moduleCard.website_types ?? []).map((type) => (
                                                <Tag key={`${moduleCard.key}-${type}`}>{type}</Tag>
                                            ))}
                                            <Tag color="blue">v{moduleCard.installed_version ?? moduleCard.latest_version}</Tag>
                                        </Space>
                                    </div>
                                </div>

                                <div className="dashboard-module-links">
                                    {(moduleCard.menus ?? []).map((menu) => {
                                        const MenuIcon = resolveModuleIcon(menu.icon);

                                        return (
                                            <button
                                                key={menu.key}
                                                type="button"
                                                className="dashboard-module-link"
                                                onClick={() => navigate(normalizeAdminRoute(menu.route))}
                                            >
                                                <span className="dashboard-module-link-icon"><MenuIcon /></span>
                                                <span className="dashboard-module-link-copy">
                                                    <strong>{menu.label}</strong>
                                                    <span>{menu.description || 'Đi tới màn hình quản trị module.'}</span>
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </Card>
                        );
                    })}
                </div>
            ) : (
                <Card>
                    <Empty
                        description="Hiện chưa có module nào đang active cho tài khoản admin này."
                        image={Empty.PRESENTED_IMAGE_SIMPLE}
                    />
                </Card>
            )}

            <div className="detail-grid detail-grid-2">
                <div className="detail-tile">
                    <Text className="detail-label">Admins</Text>
                    <Title level={4} style={{ margin: '4px 0 0' }}>{overview?.metrics?.admins ?? 0}</Title>
                </div>
                <div className="detail-tile">
                    <Text className="detail-label">Customers</Text>
                    <Title level={4} style={{ margin: '4px 0 0' }}>{overview?.metrics?.customers ?? 0}</Title>
                </div>
                <div className="detail-tile">
                    <Text className="detail-label">Roles / Permissions</Text>
                    <Title level={4} style={{ margin: '4px 0 0' }}>{`${overview?.metrics?.roles ?? 0} / ${overview?.metrics?.permissions ?? 0}`}</Title>
                </div>
                <div className="detail-tile">
                    <Text className="detail-label">Themes Registered</Text>
                    <Title level={4} style={{ margin: '4px 0 0' }}>{overview?.metrics?.themes ?? 0}</Title>
                </div>
            </div>
        </Space>
    );
}
