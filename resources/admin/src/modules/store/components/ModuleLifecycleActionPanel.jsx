import {
    AppstoreOutlined,
    CheckCircleFilled,
    DatabaseOutlined,
    DollarOutlined,
    FileTextOutlined,
    HomeOutlined,
    LinkOutlined,
    ProjectOutlined,
    RocketOutlined,
    SettingOutlined,
    ShoppingCartOutlined,
    TeamOutlined,
} from '@ant-design/icons';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { toAppTerminology } from '../utils/appTerminology';

const { Paragraph, Text, Title } = Typography;

const statusColorMap = {
    available: 'default',
    installed: 'blue',
    enabled: 'green',
    disabled: 'orange',
    upgrade_pending: 'gold',
};

const statusLabelMap = {
    available: 'Có thể cài đặt',
    installed: 'Đã cài đặt',
    enabled: 'Đang bật',
    disabled: 'Đang tắt',
    upgrade_pending: 'Chờ nâng cấp',
};

const appAppearance = {
    catalog: { icon: ShoppingCartOutlined, color: '#1677ff', background: '#eaf3ff' },
    cms: { icon: FileTextOutlined, color: '#7c3aed', background: '#f1ebff' },
    hrm: { icon: TeamOutlined, color: '#08979c', background: '#e6fffb' },
    inventory: { icon: DatabaseOutlined, color: '#d46b08', background: '#fff7e6' },
    payroll: { icon: DollarOutlined, color: '#389e0d', background: '#f6ffed' },
    project: { icon: ProjectOutlined, color: '#c41d7f', background: '#fff0f6' },
    'real-estate': { icon: HomeOutlined, color: '#1f7a56', background: '#e9f8f0' },
};

function DetailMetric({ label, value, icon }) {
    return (
        <div className="module-detail-metric">
            <span className="module-detail-metric__icon">{icon}</span>
            <div>
                <Text type="secondary">{label}</Text>
                <strong>{value || 'N/A'}</strong>
            </div>
        </div>
    );
}

function RelatedApps({ values, modules, onOpenModule, disabled, emptyText = 'Không có' }) {
    if (!values?.length) {
        return <Text type="secondary">{emptyText}</Text>;
    }

    const rows = values.map((key) => {
        const moduleCard = modules.find((item) => item.key === key);
        return { key, name: moduleCard?.name ?? key, moduleCard };
    });
    const columns = [
        {
            title: 'Tên App',
            dataIndex: 'name',
            render: (name, row) => row.moduleCard ? (
                <Button
                    className="module-detail-relation-link"
                    type="link"
                    size="small"
                    disabled={disabled}
                    onClick={() => onOpenModule?.(row.moduleCard)}
                    title={`Xem chi tiết App: ${name}`}
                >
                    {name}
                </Button>
            ) : <Text>{name}</Text>,
        },
        {
            title: 'Trạng thái',
            key: 'status',
            width: '40%',
            render: (_, { moduleCard }) => {
                if (!moduleCard) return <Text type="secondary">Không có thông tin</Text>;
                const statusLabel = !moduleCard.is_installed
                    ? 'Chưa cài đặt'
                    : ['enabled', 'disabled', 'upgrade_pending'].includes(moduleCard.status)
                        ? `Đã cài · ${statusLabelMap[moduleCard.status]}`
                        : 'Đã cài đặt';
                return <Tag color={statusColorMap[moduleCard.status] ?? 'default'}>{statusLabel}</Tag>;
            },
        },
    ];

    return <Table className="module-detail-relation-table" columns={columns} dataSource={rows} rowKey="key" pagination={false} size="small" tableLayout="fixed" />;
}

export default function ModuleLifecycleActionPanel({ moduleCard, modules = [], onOpenModule, permissions, onAction, onOpenChangelog, onOpenTestData, pendingAction = null }) {
    const canInstall = permissions?.install ?? false;
    const canEnable = permissions?.enable ?? false;
    const canDisable = permissions?.disable ?? false;
    const canUpgrade = permissions?.upgrade ?? false;
    const canUninstall = permissions?.uninstall ?? false;
    const canGenerateDemoData = permissions?.demoData ?? false;

    if (!moduleCard) {
        return <div className="module-detail-loading">Đang tải thông tin App...</div>;
    }

    const appearance = appAppearance[moduleCard.key] ?? {
        icon: AppstoreOutlined,
        color: '#475569',
        background: '#f1f5f9',
    };
    const AppIcon = appearance.icon;
    const dependencyNames = moduleCard.dependencies ?? [];
    const dependentNames = (moduleCard.dependents ?? []).map((item) => item.key);
    const menuNames = (moduleCard.menus ?? []).map((item) => item.label);

    return (
        <>
            <div className="module-detail-shell">
                <section className="module-detail-hero">
                    <span
                        className="module-detail-hero__icon"
                        style={{ color: appearance.color, background: appearance.background }}
                    >
                        <AppIcon />
                    </span>
                    <div className="module-detail-hero__content">
                        <Text className="module-detail-eyebrow">AIO PLATFORM APP</Text>
                        <Space size={8} wrap>
                            <Title level={3}>{moduleCard.name}</Title>
                            <Tag color={statusColorMap[moduleCard.status] ?? 'default'}>
                                {statusLabelMap[moduleCard.status] ?? moduleCard.status}
                            </Tag>
                        </Space>
                        <Paragraph>{toAppTerminology(moduleCard.description)}</Paragraph>
                        <span className="module-detail-key">{moduleCard.key}</span>
                    </div>
                </section>

                <section className="module-detail-section">
                    <div className="module-detail-section__heading">
                        <div>
                            <Text className="module-detail-eyebrow">TỔNG QUAN</Text>
                            <Title level={5}>Thông tin phiên bản</Title>
                        </div>
                        {!moduleCard.upgrade_available && (
                            <span className="module-detail-current">
                                <CheckCircleFilled /> Đã mới nhất
                            </span>
                        )}
                    </div>
                    <div className="module-detail-metrics">
                        <DetailMetric
                            label="Đang sử dụng"
                            value={moduleCard.installed_version ?? 'Chưa cài đặt'}
                            icon={<SettingOutlined />}
                        />
                        <DetailMetric label="Bản mới nhất" value={moduleCard.latest_version} icon={<RocketOutlined />} />
                        <DetailMetric
                            label="Loại website"
                            value={(moduleCard.website_types ?? []).join(', ') || 'Tất cả'}
                            icon={<AppstoreOutlined />}
                        />
                        <DetailMetric
                            label="Menu tích hợp"
                            value={menuNames.join(', ') || 'Không có'}
                            icon={<FileTextOutlined />}
                        />
                    </div>
                </section>

                <section className="module-detail-section">
                    <div className="module-detail-section__heading">
                        <div>
                            <Text className="module-detail-eyebrow">HỆ SINH THÁI</Text>
                            <Title level={5}>Quan hệ phụ thuộc</Title>
                        </div>
                        <LinkOutlined className="module-detail-section__symbol" />
                    </div>
                    <div className="module-detail-relations">
                        <div>
                            <Text strong>App cần thiết</Text>
                            <RelatedApps values={dependencyNames} modules={modules} onOpenModule={onOpenModule} disabled={Boolean(pendingAction)} />
                        </div>
                        <div>
                            <Text strong>App đang phụ thuộc vào App này</Text>
                            <RelatedApps values={dependentNames} modules={modules} onOpenModule={onOpenModule} disabled={Boolean(pendingAction)} />
                        </div>
                    </div>
                </section>

                <section className="module-detail-section module-detail-actions">
                    <div className="module-detail-section__heading">
                        <div>
                            <Text className="module-detail-eyebrow">THAO TÁC</Text>
                            <Title level={5}>Quản lý vòng đời App</Title>
                        </div>
                    </div>
                    <Space size={[8, 8]} wrap>
                        {!moduleCard.is_installed ? (
                            <Button
                                type="primary"
                                disabled={Boolean(pendingAction) || !canInstall || !moduleCard.available_actions?.install}
                                loading={pendingAction === 'install'} onClick={() => onAction?.(moduleCard.key, 'install')}
                            >
                                Cài đặt App
                            </Button>
                        ) : null}
                        {moduleCard.status !== 'enabled' ? (
                            <Button
                                type="primary"
                                disabled={Boolean(pendingAction) || !canEnable || !moduleCard.available_actions?.enable}
                                loading={pendingAction === 'enable'} onClick={() => onAction?.(moduleCard.key, 'enable')}
                            >
                                Bật App
                            </Button>
                        ) : null}
                        {moduleCard.status === 'enabled' ? (
                            <Button disabled={Boolean(pendingAction) || !canDisable || !moduleCard.available_actions?.disable} loading={pendingAction === 'disable'} onClick={() => onAction?.(moduleCard.key, 'disable')}>
                                Tắt App
                            </Button>
                        ) : null}
                        {moduleCard.is_installed ? (
                            <Button disabled={Boolean(pendingAction) || !canUpgrade || !moduleCard.available_actions?.upgrade} loading={pendingAction === 'upgrade'} onClick={() => onAction?.(moduleCard.key, 'upgrade')}>
                                Nâng cấp
                            </Button>
                        ) : null}
                        {moduleCard.status === 'enabled' && canGenerateDemoData ? (
                            <Button disabled={Boolean(pendingAction) || !moduleCard.test_data?.supported || !moduleCard.test_data?.storage_ready} onClick={() => onOpenTestData?.(moduleCard)}>
                                Tạo data demo
                            </Button>
                        ) : null}
                        <Button disabled={Boolean(pendingAction)} onClick={() => onOpenChangelog?.(moduleCard)}>Nhật ký thay đổi</Button>
                        {moduleCard.is_installed ? (
                            <Button danger disabled={Boolean(pendingAction) || !canUninstall || !moduleCard.available_actions?.uninstall} loading={pendingAction === 'uninstall'} onClick={() => onAction?.(moduleCard.key, 'uninstall')}>
                                Gỡ bỏ
                            </Button>
                        ) : null}
                    </Space>

                    {pendingAction ? <Alert type="info" showIcon message={pendingAction === 'install' ? 'Đang cài đặt App…' : 'Đang xử lý thao tác App…'} description="Vui lòng chờ, quá trình này có thể mất một lúc." style={{ marginTop: 12 }} /> : null}
                    <div className="module-detail-blockers">
                        {Object.entries(moduleCard.blockers ?? {}).map(([actionKey, blockers]) => (
                            blockers?.length ? (
                                <Alert
                                    key={actionKey}
                                    type="info"
                                    showIcon
                                    message={actionKey}
                                    description={blockers.map(toAppTerminology).join(' | ')}
                                />
                            ) : null
                        ))}
                    </div>
                </section>
            </div>

        </>
    );
}
