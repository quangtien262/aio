import { useState } from 'react';
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
import Checkbox from 'antd/es/checkbox';
import Modal from 'antd/es/modal';
import Space from 'antd/es/space';
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

function DetailTags({ values, emptyText = 'Không có' }) {
    if (!values?.length) {
        return <Text type="secondary">{emptyText}</Text>;
    }

    return (
        <Space size={[6, 6]} wrap>
            {values.map((value) => <Tag key={value}>{value}</Tag>)}
        </Space>
    );
}

export default function ModuleLifecycleActionPanel({ moduleCard, permissions, onAction, onOpenChangelog }) {
    const canInstall = permissions?.install ?? false;
    const canEnable = permissions?.enable ?? false;
    const canDisable = permissions?.disable ?? false;
    const canUpgrade = permissions?.upgrade ?? false;
    const canUninstall = permissions?.uninstall ?? false;
    const canGenerateDemoData = permissions?.demoData ?? false;
    const [demoModalOpen, setDemoModalOpen] = useState(false);
    const [removeExistingDemoData, setRemoveExistingDemoData] = useState(true);
    const [submittingDemoData, setSubmittingDemoData] = useState(false);

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

    const openDemoDataModal = () => {
        setRemoveExistingDemoData(true);
        setDemoModalOpen(true);
    };

    const closeDemoDataModal = () => {
        if (submittingDemoData) {
            return;
        }

        setDemoModalOpen(false);
        setRemoveExistingDemoData(true);
    };

    const handleGenerateDemoData = async () => {
        setSubmittingDemoData(true);

        try {
            const didSucceed = await onAction?.(moduleCard.key, 'demo-data', {
                remove_existing: removeExistingDemoData,
            });

            if (didSucceed !== false) {
                closeDemoDataModal();
            }
        } finally {
            setSubmittingDemoData(false);
        }
    };

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
                            <DetailTags values={dependencyNames} />
                        </div>
                        <div>
                            <Text strong>App đang phụ thuộc vào App này</Text>
                            <DetailTags values={dependentNames} />
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
                                disabled={!canInstall || !moduleCard.available_actions?.install}
                                onClick={() => onAction?.(moduleCard.key, 'install')}
                            >
                                Cài đặt App
                            </Button>
                        ) : null}
                        {moduleCard.status !== 'enabled' ? (
                            <Button
                                type="primary"
                                disabled={!canEnable || !moduleCard.available_actions?.enable}
                                onClick={() => onAction?.(moduleCard.key, 'enable')}
                            >
                                Bật App
                            </Button>
                        ) : null}
                        {moduleCard.status === 'enabled' ? (
                            <Button disabled={!canDisable || !moduleCard.available_actions?.disable} onClick={() => onAction?.(moduleCard.key, 'disable')}>
                                Tắt App
                            </Button>
                        ) : null}
                        {moduleCard.is_installed ? (
                            <Button disabled={!canUpgrade || !moduleCard.available_actions?.upgrade} onClick={() => onAction?.(moduleCard.key, 'upgrade')}>
                                Nâng cấp
                            </Button>
                        ) : null}
                        {moduleCard.key === 'project' && moduleCard.is_installed ? (
                            <Button disabled={!canGenerateDemoData} onClick={openDemoDataModal}>
                                Tạo data test
                            </Button>
                        ) : null}
                        <Button onClick={() => onOpenChangelog?.(moduleCard)}>Nhật ký thay đổi</Button>
                        {moduleCard.is_installed ? (
                            <Button danger disabled={!canUninstall || !moduleCard.available_actions?.uninstall} onClick={() => onAction?.(moduleCard.key, 'uninstall')}>
                                Gỡ bỏ
                            </Button>
                        ) : null}
                    </Space>

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

            <Modal
                title={moduleCard.key === 'project' ? `Tạo data test: ${moduleCard.name}` : 'Tạo data test'}
                open={demoModalOpen}
                onCancel={closeDemoDataModal}
                onOk={handleGenerateDemoData}
                okText="Tạo dữ liệu"
                cancelText="Hủy"
                confirmLoading={submittingDemoData}
                okButtonProps={{ disabled: !canGenerateDemoData }}
                destroyOnHidden
            >
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Alert
                        type="warning"
                        showIcon
                        message="Dữ liệu demo Project có thể ghi đè hoặc tạo thêm batch mới tùy lựa chọn bên dưới. Hãy kiểm tra kỹ trước khi thực hiện."
                    />

                    <div>
                        <Text strong>Chế độ tạo data test</Text>
                        <Paragraph type="secondary" style={{ marginBottom: 0 }}>
                            Bật lựa chọn nếu muốn xóa toàn bộ data demo Project cũ rồi tạo lại hai dự án mẫu chuẩn.
                            Bỏ chọn nếu muốn giữ nguyên dữ liệu cũ và thêm một batch demo mới.
                        </Paragraph>
                    </div>

                    <Checkbox checked={removeExistingDemoData} onChange={(event) => setRemoveExistingDemoData(event.target.checked)}>
                        Xóa data demo cũ trước khi tạo mới
                    </Checkbox>
                </Space>
            </Modal>
        </>
    );
}
