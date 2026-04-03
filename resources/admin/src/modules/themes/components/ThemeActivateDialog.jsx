import Modal from 'antd/es/modal';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';

const { Paragraph, Text } = Typography;

export default function ThemeActivateDialog({ open, theme, canActivate, onCancel, onConfirm }) {
    return (
        <Modal
            title={theme ? `Kích hoạt theme: ${theme.name}` : 'Kích hoạt theme'}
            open={open}
            onCancel={onCancel}
            onOk={() => onConfirm?.(theme?.key)}
            okText="Kích hoạt"
            cancelText="Đóng"
            okButtonProps={{ disabled: !theme || !canActivate || theme.is_active }}
            destroyOnHidden
        >
            {theme ? (
                <Space direction="vertical" size={12} style={{ width: '100%' }}>
                    <Paragraph style={{ marginBottom: 0 }}>
                        Việc kích hoạt sẽ chuyển website hiện tại sang theme <Text strong>{theme.name}</Text>. Dữ liệu nghiệp vụ vẫn giữ nguyên,
                        chỉ đổi lớp giao diện đang dùng.
                    </Paragraph>
                    <Space wrap>
                        <Tag color="gold">{theme.website_type}</Tag>
                        <Tag>{theme.version}</Tag>
                        {theme.parent ? <Tag>parent: {theme.parent}</Tag> : null}
                    </Space>
                    <Paragraph style={{ marginBottom: 0 }}>{theme.description || 'Theme chưa có mô tả.'}</Paragraph>
                </Space>
            ) : null}
        </Modal>
    );
}
