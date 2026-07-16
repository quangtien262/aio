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
            return [{ label: `Du lieu mau danh rieng cho ${theme.name}`, value: theme.demo.default_preset, description: 'Noi dung duoc thiet ke rieng cho bo cuc va nguon du lieu cua theme.' }];
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
            title={theme ? `Tao data test: ${theme.name}` : 'Tao data test'}
            open={open}
            onCancel={close}
            onOk={handleOk}
            okText={mode === 'rebuild' ? 'Rebuild du lieu' : 'Tao du lieu'}
            okButtonProps={{ disabled: !theme || !canGenerateDemoData || countdown > 0 }}
            destroyOnHidden
        >
            <Space direction="vertical" size={16} style={{ width: '100%' }}>
                <Alert type="info" showIcon message="He thong chi tao va quan ly cac ban ghi demo co marker rieng." />
                <div><Text className="card-label">Theme dang chon</Text><Paragraph style={{ marginBottom: 0 }}>{theme?.name ?? 'Chua chon theme'}</Paragraph></div>
                <Form form={form} layout="vertical">
                    <Form.Item name="preset" label="Nganh du lieu mau" rules={[{ required: true, message: 'Chon loai du lieu test can tao' }]}>
                        <Select options={presetOptions} optionLabelProp="label" />
                    </Form.Item>
                </Form>
                <Checkbox checked={resetAll} onChange={(event) => setResetAll(event.target.checked)}>
                    Reset toan bo data test da duoc he thong tao
                </Checkbox>
                {resetAll ? <Alert type="warning" showIcon message={countdown > 0 ? `Cho ${countdown} giay de xac nhan reset data test.` : 'Ban co the xac nhan reset data test.'} description="Chi cac ban ghi demo co marker cua he thong moi bi xoa. Du lieu tao thu cong khong bi anh huong." /> : null}
            </Space>
        </Modal>
    );
}
