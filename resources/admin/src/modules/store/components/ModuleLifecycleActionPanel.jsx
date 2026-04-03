import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';

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

    if (!moduleCard) {
        return <Card title="Module Lifecycle" loading />;
    }

    return (
        <Card title="Module Lifecycle">
            <Space direction="vertical" size={12} style={{ width: '100%' }}>
                <div>
                    <Space>
                        <Title level={4} style={{ margin: 0 }}>{moduleCard.name}</Title>
                        <Tag color={statusColorMap[moduleCard.status] ?? 'default'}>{moduleCard.status}</Tag>
                    </Space>
                    <Paragraph style={{ marginBottom: 0 }}>{moduleCard.description}</Paragraph>
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
                    <Text strong>Module phụ thuộc vào nó:</Text> {(moduleCard.dependents ?? []).map((item) => item.key).join(', ') || 'Không có'}
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
                            <Text strong>{actionKey}:</Text> {blockers.join(' | ')}
                        </Paragraph>
                    ) : null
                ))}
            </Space>
        </Card>
    );
}
