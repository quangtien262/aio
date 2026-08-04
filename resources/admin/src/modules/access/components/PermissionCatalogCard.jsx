import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Modal from 'antd/es/modal';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';

const { Text } = Typography;

const MODULE_LABELS = {
    admin: 'Tài khoản quản trị',
    catalog: 'Danh mục & sản phẩm',
    cms: 'Nội dung website',
    inventory: 'Kho hàng',
    module: 'Ứng dụng & phân hệ',
    platform: 'Nền tảng hệ thống',
    permission: 'Phân quyền hệ thống',
    project: 'Quản lý dự án',
    rbac: 'Quản lý vai trò và phân quyền',
    setup: 'Cấu hình hệ thống',
    store: 'Kho ứng dụng',
    theme: 'Giao diện website',
};

export default function PermissionCatalogCard({ groupedPermissions, open, onClose }) {
    const totalPermissions = Object.values(groupedPermissions ?? {}).reduce((total, items) => total + items.length, 0);

    return (
        <Modal
            title="Danh mục quyền"
            open={open}
            onCancel={onClose}
            footer={<Button type="primary" onClick={onClose}>Đóng</Button>}
            width={860}
            destroyOnHidden
        >
            <Card className="permission-catalog-summary" bordered={false}>
                <Space direction="vertical" size={4}>
                    <Text strong>{totalPermissions} quyền đang hoạt động</Text>
                    <Text type="secondary">Danh sách này giúp kiểm tra nhanh các quyền có thể gán cho vai trò.</Text>
                </Space>
            </Card>

            <Space direction="vertical" size={16} style={{ width: '100%', marginTop: 16 }}>
                {Object.entries(groupedPermissions ?? {}).map(([groupKey, items]) => (
                    <div className="permission-catalog-group" key={groupKey}>
                        <Text strong>{MODULE_LABELS[groupKey] || groupKey}</Text>
                        <Space size={8} wrap style={{ width: '100%', marginTop: 10 }}>
                            {items.map((permission) => (
                                <Tag key={permission.id}>{permission.display_name ?? permission.name ?? permission.key}</Tag>
                            ))}
                        </Space>
                    </div>
                ))}
            </Space>
        </Modal>
    );
}
