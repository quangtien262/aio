import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Descriptions from 'antd/es/descriptions';
import Drawer from 'antd/es/drawer';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';

const { Paragraph, Text } = Typography;

export default function FnbConflictDrawer({ conflict, onClose, onReload }) {
    if (!conflict) {
        return null;
    }

    const versions = conflict.error?.details?.current_versions ?? {};
    const versionItems = Object.entries(versions).map(([key, value]) => ({
        key,
        label: key,
        children: String(value),
    }));

    return (
        <Drawer
            title="Dữ liệu vừa thay đổi"
            open
            width="min(520px, 96vw)"
            onClose={onClose}
            extra={<Button type="primary" onClick={onReload}>Tải bản mới nhất</Button>}
        >
            <Space direction="vertical" size={18} style={{ width: '100%' }}>
                <Alert
                    type="warning"
                    showIcon
                    message={conflict.error?.message ?? 'Một thiết bị khác đã cập nhật dữ liệu này.'}
                    description="Thao tác chưa được ghi đè. Hãy tải bản mới nhất, kiểm tra thay đổi rồi thực hiện lại nếu vẫn cần."
                />
                {versionItems.length ? <Descriptions bordered size="small" column={1} items={versionItems} /> : null}
                {conflict.error?.requestId ? <Text className="fnb-request-id">Mã đối soát: {conflict.error.requestId}</Text> : null}
                <Paragraph type="secondary" style={{ marginBottom: 0 }}>
                    Hệ thống không tự động gửi lại mutation khi gặp xung đột để tránh tạo order, thanh toán hoặc hoàn tiền ngoài ý muốn.
                </Paragraph>
            </Space>
        </Drawer>
    );
}

