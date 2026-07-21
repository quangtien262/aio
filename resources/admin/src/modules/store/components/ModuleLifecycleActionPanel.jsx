import { useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
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
        return <Card title="Quản lý App" loading />;
    }

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
            <Card title="Quản lý App">
                <Space direction="vertical" size={12} style={{ width: '100%' }}>
                <div>
                    <Space>
                        <Title level={4} style={{ margin: 0 }}>{moduleCard.name}</Title>
                        <Tag color={statusColorMap[moduleCard.status] ?? 'default'}>{moduleCard.status}</Tag>
                    </Space>
                    <Paragraph style={{ marginBottom: 0 }}>{toAppTerminology(moduleCard.description)}</Paragraph>
                </div>

                <div>
                    <Text strong>Version:</Text> {moduleCard.installed_version ?? 'N/A'} / Latest {moduleCard.latest_version}
                </div>
                <div>
                    <Text strong>Loại website:</Text> {(moduleCard.website_types ?? []).join(', ') || 'N/A'}
                </div>
                <div>
                    <Text strong>Phụ thuộc:</Text> {(moduleCard.dependencies ?? []).join(', ') || 'Không có'}
                </div>
                <div>
                    <Text strong>App phụ thuộc vào App này:</Text> {(moduleCard.dependents ?? []).map((item) => item.key).join(', ') || 'Không có'}
                </div>
                <div>
                    <Text strong>Menu:</Text> {(moduleCard.menus ?? []).map((item) => item.label).join(', ') || 'Không có'}
                </div>

                <Space wrap>
                    {!moduleCard.is_installed ? (
                        <Button size="small" disabled={!canInstall || !moduleCard.available_actions?.install} onClick={() => onAction?.(moduleCard.key, 'install')}>
                            Cài đặt
                        </Button>
                    ) : null}
                    {moduleCard.status !== 'enabled' ? (
                        <Button size="small" type="primary" disabled={!canEnable || !moduleCard.available_actions?.enable} onClick={() => onAction?.(moduleCard.key, 'enable')}>
                            Bật
                        </Button>
                    ) : null}
                    {moduleCard.status === 'enabled' ? (
                        <Button size="small" disabled={!canDisable || !moduleCard.available_actions?.disable} onClick={() => onAction?.(moduleCard.key, 'disable')}>
                            Tắt
                        </Button>
                    ) : null}
                    {moduleCard.is_installed ? (
                        <Button size="small" type="dashed" disabled={!canUpgrade || !moduleCard.available_actions?.upgrade} onClick={() => onAction?.(moduleCard.key, 'upgrade')}>
                            Nâng cấp
                        </Button>
                    ) : null}
                    {moduleCard.key === 'project' && moduleCard.is_installed ? (
                        <Button size="small" disabled={!canGenerateDemoData} onClick={openDemoDataModal}>
                            Tạo data test
                        </Button>
                    ) : null}
                    {moduleCard.is_installed ? (
                        <Button danger size="small" disabled={!canUninstall || !moduleCard.available_actions?.uninstall} onClick={() => onAction?.(moduleCard.key, 'uninstall')}>
                            Gỡ bỏ
                        </Button>
                    ) : null}
                    <Button size="small" onClick={() => onOpenChangelog?.(moduleCard)}>
                        Xem nhật ký thay đổi
                    </Button>
                </Space>

                {Object.entries(moduleCard.blockers ?? {}).map(([actionKey, blockers]) => (
                    blockers?.length ? (
                        <Paragraph key={actionKey} type="secondary" style={{ marginBottom: 0 }}>
                            <Text strong>{actionKey}:</Text> {blockers.map(toAppTerminology).join(' | ')}
                        </Paragraph>
                    ) : null
                ))}
                </Space>
            </Card>

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
                            Bật checkbox nếu muốn xóa toàn bộ data demo Project cũ rồi tạo lại 2 dự án mẫu chuẩn. Bỏ chọn nếu muốn giữ nguyên data demo cũ và thêm mới một batch 2 dự án demo khác.
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
