import { useEffect, useMemo, useState } from 'react';
import Alert from 'antd/es/alert';
import Checkbox from 'antd/es/checkbox';
import Divider from 'antd/es/divider';
import Modal from 'antd/es/modal';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';

const { Paragraph, Text } = Typography;

export default function ThemeActivateDialog({ open, theme, currentTheme = null, canActivate, onCancel, onConfirm }) {
    const [confirmedCrossTypeSwitch, setConfirmedCrossTypeSwitch] = useState(false);

    const isCrossWebsiteType = useMemo(() => {
        if (!theme || !currentTheme || currentTheme.key === theme.key) {
            return false;
        }

        return Boolean(currentTheme.website_type && theme.website_type && currentTheme.website_type !== theme.website_type);
    }, [currentTheme, theme]);

    useEffect(() => {
        setConfirmedCrossTypeSwitch(false);
    }, [open, theme?.key]);

    return (
        <Modal
            title={theme ? `Kích hoạt theme: ${theme.name}` : 'Kích hoạt theme'}
            open={open}
            onCancel={onCancel}
            onOk={() => onConfirm?.(theme?.key)}
            okText="Kích hoạt"
            cancelText="Đóng"
            okButtonProps={{ disabled: !theme || !canActivate || theme.is_active || (isCrossWebsiteType && !confirmedCrossTypeSwitch) }}
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
                    {currentTheme && currentTheme.key !== theme.key ? (
                        <Paragraph style={{ marginBottom: 0 }}>
                            Theme hiện tại: <Text strong>{currentTheme.name}</Text>
                            {' '}
                            <Tag color="default">{currentTheme.website_type}</Tag>
                        </Paragraph>
                    ) : null}
                    <Paragraph style={{ marginBottom: 0 }}>{theme.description || 'Theme chưa có mô tả.'}</Paragraph>
                    {isCrossWebsiteType ? (
                        <>
                            <Divider style={{ margin: '4px 0' }} />
                            <Alert
                                type="error"
                                showIcon
                                message="Theme mới thuộc loại website khác"
                                description={(
                                    <Space direction="vertical" size={10} style={{ width: '100%' }}>
                                        <Text>
                                            Dữ liệu hiện tại sẽ không bị xóa, nhưng một phần nội dung cũ có thể không còn tương thích hoàn toàn với bố cục,
                                            block và mục đích sử dụng của theme mới.
                                        </Text>
                                        <div>
                                            <div>• Menu vị trí cũ có thể không map đúng sang theme mới</div>
                                            <div>• Banner cũ có thể không còn phù hợp với homepage mới</div>
                                            <div>• Product hoặc service hiện tại có thể hiển thị sai ngữ cảnh</div>
                                            <div>• Translation content cũ có thể thiếu key tương ứng</div>
                                            <div>• Trang chủ có thể cần tạo lại data test hoặc cấu hình lại nội dung</div>
                                        </div>
                                    </Space>
                                )}
                            />
                            <Checkbox checked={confirmedCrossTypeSwitch} onChange={(event) => setConfirmedCrossTypeSwitch(event.target.checked)}>
                                Tôi hiểu dữ liệu hiện tại có thể không tương thích hoàn toàn với theme mới và vẫn muốn kích hoạt.
                            </Checkbox>
                        </>
                    ) : null}
                </Space>
            ) : null}
        </Modal>
    );
}
