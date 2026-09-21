import { useEffect, useMemo, useState } from 'react';
import Alert from 'antd/es/alert';
import Checkbox from 'antd/es/checkbox';
import Form from 'antd/es/form';
import Modal from 'antd/es/modal';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';

const { Paragraph, Text } = Typography;

const COMMERCE_PRESET_OPTIONS = [
    { label: 'Du lieu thuong mai mac dinh', value: 'electronics-superstore', description: 'San pham, tin tuc, danh muc va banner demo.' },
];

const SERVICE_PRESET_OPTIONS = [
    { label: 'Nha xe san bay va city transfer', value: 'ser-airport-city', description: 'Demo dich vu van chuyen va booking.' },
    { label: 'Shuttle doanh nghiep va hang nhe', value: 'ser-business-cargo', description: 'Demo dich vu cho website service.' },
];

export default function ThemeDemoDataModal({ open, theme, mode = 'generate', canGenerateDemoData, onCancel, onSubmit }) {
    const [form] = Form.useForm();
    const [resetAll, setResetAll] = useState(false);
    const [countdown, setCountdown] = useState(0);
    const presetOptions = useMemo(() => {
        if (theme?.demo?.default_preset) {
            return [{ label: `Dữ liệu mẫu dành riêng cho ${theme.name}`, value: theme.demo.default_preset, description: 'Nội dung được thiết kế riêng cho bố cục và nguồn dữ liệu của theme.' }];
        }

        return (theme?.website_type ?? '').toLowerCase() === 'service' ? SERVICE_PRESET_OPTIONS : COMMERCE_PRESET_OPTIONS;
    }, [theme?.demo?.default_preset, theme?.name, theme?.website_type]);

    useEffect(() => {
        if (open) {
            form.setFieldsValue({ preset: presetOptions[0]?.value });
        }
    }, [form, open, presetOptions]);

    useEffect(() => {
        if (!open || !resetAll) {
            setCountdown(0);
            return undefined;
        }

        setCountdown(5);
        const timer = window.setInterval(() => {
            setCountdown((current) => {
                if (current <= 1) {
                    window.clearInterval(timer);
                    return 0;
                }

                return current - 1;
            });
        }, 1000);

        return () => window.clearInterval(timer);
    }, [open, resetAll]);

    const handleOk = async () => {
        const values = await form.validateFields();
        const didFinish = await onSubmit?.(values.preset, { resetAll });

        if (didFinish !== false) {
            form.resetFields();
            setResetAll(false);
        }
    };

    const close = () => {
        form.resetFields();
        setResetAll(false);
        onCancel?.();
    };

    return (
        <Modal
            title={theme ? `Tạo dữ liệu mẫu: ${theme.name}` : 'Tạo dữ liệu mẫu'}
            open={open}
            onCancel={close}
            onOk={handleOk}
            okText={mode === 'rebuild' ? 'Tạo lại dữ liệu' : 'Tạo dữ liệu'}
            okButtonProps={{ disabled: !theme || !canGenerateDemoData || countdown > 0 }}
            destroyOnHidden
        >
            <Space direction="vertical" size={16} style={{ width: '100%' }}>
                <Alert type="info" showIcon message="Hệ thống chỉ tạo và quản lý các bản ghi được đánh dấu là dữ liệu mẫu." />
                <div><Text className="card-label">Theme đang chọn</Text><Paragraph style={{ marginBottom: 0 }}>{theme?.name ?? 'Chưa chọn theme'}</Paragraph></div>
                <Form form={form} layout="vertical">
                    <Form.Item name="preset" label="Bộ dữ liệu mẫu" rules={[{ required: true, message: 'Chọn bộ dữ liệu mẫu cần tạo' }]}>
                        <Select options={presetOptions} optionLabelProp="label" />
                    </Form.Item>
                </Form>
                <Checkbox checked={resetAll} onChange={(event) => setResetAll(event.target.checked)}>
                    Xóa toàn bộ dữ liệu mẫu do hệ thống tạo trước khi tạo mới
                </Checkbox>
                {resetAll ? <Alert type="warning" showIcon message={countdown > 0 ? `Chờ ${countdown} giây để xác nhận xóa dữ liệu mẫu.` : 'Bạn có thể xác nhận xóa dữ liệu mẫu.'} description="Chỉ xóa các bản ghi mẫu có đánh dấu của hệ thống. Dữ liệu tạo thủ công được giữ lại." /> : null}
            </Space>
        </Modal>
    );
}
