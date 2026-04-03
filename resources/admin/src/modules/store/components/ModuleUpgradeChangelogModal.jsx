import Empty from 'antd/es/empty';
import Modal from 'antd/es/modal';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';

const { Paragraph, Text, Title } = Typography;

export default function ModuleUpgradeChangelogModal({ open, moduleCard, onCancel, onAction, canUpgrade }) {
    return (
        <Modal
            title={moduleCard ? `Changelog: ${moduleCard.name}` : 'Changelog'}
            open={open}
            onCancel={onCancel}
            onOk={moduleCard?.is_installed ? () => onAction?.(moduleCard.key, 'upgrade') : onCancel}
            okText={moduleCard?.is_installed ? 'Nâng cấp ngay' : 'Đóng'}
            okButtonProps={{ disabled: !moduleCard?.is_installed || !canUpgrade || !moduleCard?.available_actions?.upgrade }}
            cancelText="Đóng"
            width={860}
            destroyOnHidden
        >
            {moduleCard ? (
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <div>
                        <Space>
                            <Title level={4} style={{ margin: 0 }}>{moduleCard.name}</Title>
                            {moduleCard.upgrade_available ? <Tag color="gold">Upgrade available</Tag> : <Tag>Up to date</Tag>}
                        </Space>
                        <Paragraph style={{ marginBottom: 0 }}>
                            Installed: {moduleCard.installed_version ?? 'N/A'} | Latest: {moduleCard.latest_version}
                        </Paragraph>
                    </div>

                    {(moduleCard.changelog ?? []).length ? (
                        <Space direction="vertical" size={12} style={{ width: '100%' }}>
                            {(moduleCard.changelog ?? []).map((entry) => (
                                <div key={`${moduleCard.key}-${entry.version}`}>
                                    <Text strong>{entry.version}</Text>
                                    {entry.date ? <Text type="secondary"> {' '}({entry.date})</Text> : null}
                                    <Paragraph style={{ marginBottom: 0 }}>{(entry.notes ?? []).join(' | ')}</Paragraph>
                                </div>
                            ))}
                        </Space>
                    ) : (
                        <Empty description="Chưa có changelog." />
                    )}
                </Space>
            ) : null}
        </Modal>
    );
}
