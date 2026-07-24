import {
    AppstoreOutlined,
    DatabaseOutlined,
    DollarOutlined,
    FileTextOutlined,
    HomeOutlined,
    ProjectOutlined,
    ShoppingCartOutlined,
    TeamOutlined,
} from '@ant-design/icons';
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

const appAppearance = {
    catalog: { icon: ShoppingCartOutlined, color: '#1677ff', background: '#eaf3ff' },
    cms: { icon: FileTextOutlined, color: '#7c3aed', background: '#f1ebff' },
    hrm: { icon: TeamOutlined, color: '#08979c', background: '#e6fffb' },
    inventory: { icon: DatabaseOutlined, color: '#d46b08', background: '#fff7e6' },
    payroll: { icon: DollarOutlined, color: '#389e0d', background: '#f6ffed' },
    project: { icon: ProjectOutlined, color: '#c41d7f', background: '#fff0f6' },
    'real-estate': { icon: HomeOutlined, color: '#1f7a56', background: '#e9f8f0' },
};

export default function ModuleStoreTable({ modules, onOpenDetails, onOpenChangelog }) {
    const columns = [
        {
            title: 'App',
            key: 'module',
            render: (_, moduleCard) => {
                const appearance = appAppearance[moduleCard.key] ?? {
                    icon: AppstoreOutlined,
                    color: '#475569',
                    background: '#f1f5f9',
                };
                const AppIcon = appearance.icon;

                return (
                    <Space size={12}>
                        <span className="module-store-app-icon" style={{ color: appearance.color, background: appearance.background }}>
                            <AppIcon />
                        </span>
                        <Space direction="vertical" size={0}>
                            <Button type="link" className="module-store-app-name" onClick={() => onOpenDetails?.(moduleCard)}>
                                {moduleCard.name}
                            </Button>
                            <Text type="secondary">{moduleCard.key}</Text>
                        </Space>
                    </Space>
                );
            },
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
        />
    );
}
