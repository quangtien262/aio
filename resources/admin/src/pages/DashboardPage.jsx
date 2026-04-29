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
import Button from 'antd/es/button';
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

    return (
        <Space direction="vertical" size={20} style={{ width: '100%' }}>
            <Card className="dashboard-hero-card">
                <Text className="card-label">Trang chủ</Text>
                <Title level={2}>Chọn module để đi vào đúng workspace quản trị của từng khối chức năng.</Title>
                <Paragraph>
                    Dashboard này chỉ làm nhiệm vụ điều hướng. Mỗi card là một workspace module đang bật;
                    khi mở vào module, hệ thống mới hiển thị danh sách tính năng và màn quản trị chi tiết bên trong.
                </Paragraph>
            </Card>

            <div className="dashboard-module-heading">
                <div>
                    <Text className="card-label">Danh sách module</Text>
                    <Title level={3} style={{ margin: '4px 0 0' }}>Danh sách module đang bật</Title>
                </div>
                <Tag color="green">{`${activeModules.length} module đang hoạt động`}</Tag>
            </div>

            {activeModules.length ? (
                <div className="dashboard-module-grid">
                    {activeModules.map((moduleCard) => {
                        const IconComponent = resolveModuleIcon(moduleCard.icon);
                        const accentColor = resolveModuleColor(moduleCard.color);
                        const moduleRoute = normalizeAdminRoute(moduleCard.route);
                        const featureCount = moduleCard.menus?.length ?? 0;
                        const workspaceLabel = moduleCard.key === 'cms' ? 'CMS Workspace' : 'Module Workspace';
                        const launcherTitle = moduleCard.key === 'cms' ? 'Vào trung tâm quản trị CMS' : 'Mở workspace module';
                        const launcherDescription = moduleCard.key === 'cms'
                            ? 'Quản lý nội dung, đơn hàng, bản tin và cấu hình website trong cùng một workspace.'
                            : 'Mở không gian quản trị riêng để xem các tính năng bên trong module này.';

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
                                        <div className="dashboard-module-kicker">{workspaceLabel}</div>

                                        <div className="dashboard-module-title-row">
                                            <Title level={4} style={{ margin: 0 }}>{moduleCard.name}</Title>
                                            <Tag color="green">active</Tag>
                                        </div>

                                        <Paragraph className="dashboard-module-description">
                                            {moduleCard.description || 'Module đang hoạt động trong workspace hiện tại.'}
                                        </Paragraph>

                                        <Space wrap size={[8, 8]} className="dashboard-module-meta">
                                            <Tag color="processing">{`${featureCount} tính năng`}</Tag>
                                            {(moduleCard.website_types ?? []).map((type) => (
                                                <Tag key={`${moduleCard.key}-${type}`}>{type}</Tag>
                                            ))}
                                            <Tag color="blue">v{moduleCard.installed_version ?? moduleCard.latest_version}</Tag>
                                        </Space>
                                    </div>
                                </div>

                                <div className="dashboard-module-footer">
                                    <div className="dashboard-module-footer-copy">
                                        <Text strong>{launcherTitle}</Text>
                                        <Text type="secondary">
                                            {launcherDescription}
                                        </Text>
                                    </div>

                                    <Button
                                        type="primary"
                                        className="dashboard-module-action"
                                        onClick={() => navigate(moduleRoute)}
                                    >
                                        Mở module
                                    </Button>
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
        </Space>
    );
}
