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

const statusLabelMap = {
    available: 'Có thể cài đặt',
    installed: 'Đã cài đặt',
    enabled: 'Đang bật',
    disabled: 'Đang tắt',
    upgrade_pending: 'Chờ nâng cấp',
};

export default function ModuleStoreTable({ modules, selectedModuleKey, onSelectModule, onOpenChangelog }) {
    const columns = [
        {
            title: 'App',
            key: 'module',
            render: (_, moduleCard) => (
                <Space direction="vertical" size={0}>
                    <Text strong>{moduleCard.name}</Text>
                    <Text type="secondary">{moduleCard.key}</Text>
                </Space>
            ),
        },
        {
            title: 'Trạng thái',
            dataIndex: 'status',
            key: 'status',
            render: (status) => <Tag color={statusColorMap[status] ?? 'default'}>{statusLabelMap[status] ?? status}</Tag>,
        },
        {
            title: 'Phiên bản',
            key: 'version',
            render: (_, moduleCard) => `${moduleCard.installed_version ?? 'N/A'} / ${moduleCard.latest_version}`,
        },
        {
            title: 'Phụ thuộc',
            key: 'dependencies',
            render: (_, moduleCard) => (moduleCard.dependencies ?? []).join(', ') || 'Không có',
        },
        {
            title: 'Nâng cấp',
            key: 'upgrade',
            render: (_, moduleCard) => (
                <Space>
                    {moduleCard.upgrade_available ? (
                        <Tag color="gold">Có bản nâng cấp</Tag>
                    ) : (
                        <Tag>Đã mới nhất</Tag>
                    )}
                    <Button size="small" onClick={() => onOpenChangelog?.(moduleCard)}>
                        Nhật ký thay đổi
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
            scroll={{ x: 820 }}
            rowClassName={(record) => (record.key === selectedModuleKey ? 'ant-table-row-selected' : '')}
            onRow={(record) => ({
                onClick: () => onSelectModule?.(record.key),
            })}
        />
    );
}
