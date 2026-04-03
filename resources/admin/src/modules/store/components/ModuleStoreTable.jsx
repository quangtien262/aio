import Button from 'antd/es/button';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';

const { Text } = Typography;

const statusColorMap = {
    available: 'default',
    installed: 'blue',
    enabled: 'green',
    disabled: 'orange',
    upgrade_pending: 'gold',
};

export default function ModuleStoreTable({ modules, selectedModuleKey, onSelectModule, onOpenChangelog }) {
    const columns = [
        {
            title: 'Module',
            key: 'module',
            render: (_, moduleCard) => (
                <Space direction="vertical" size={0}>
                    <Text strong>{moduleCard.name}</Text>
                    <Text type="secondary">{moduleCard.key}</Text>
                </Space>
            ),
        },
        {
            title: 'Status',
            dataIndex: 'status',
            key: 'status',
            render: (status) => <Tag color={statusColorMap[status] ?? 'default'}>{status}</Tag>,
        },
        {
            title: 'Version',
            key: 'version',
            render: (_, moduleCard) => `${moduleCard.installed_version ?? 'N/A'} / ${moduleCard.latest_version}`,
        },
        {
            title: 'Dependencies',
            key: 'dependencies',
            render: (_, moduleCard) => (moduleCard.dependencies ?? []).join(', ') || 'None',
        },
        {
            title: 'Upgrade',
            key: 'upgrade',
            render: (_, moduleCard) => (
                <Space>
                    {moduleCard.upgrade_available ? (
                        <Tag color="gold">Upgrade available</Tag>
                    ) : (
                        <Tag>Up to date</Tag>
                    )}
                    <Button size="small" onClick={() => onOpenChangelog?.(moduleCard)}>
                        Changelog
                    </Button>
                </Space>
            ),
        },
    ];

    return (
        <Table
            rowKey="key"
            columns={columns}
            dataSource={modules}
            pagination={false}
            rowClassName={(record) => (record.key === selectedModuleKey ? 'ant-table-row-selected' : '')}
            onRow={(record) => ({
                onClick: () => onSelectModule?.(record.key),
            })}
        />
    );
}
