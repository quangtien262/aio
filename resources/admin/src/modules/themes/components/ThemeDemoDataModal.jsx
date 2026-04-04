import Alert from 'antd/es/alert';
import Form from 'antd/es/form';
import Modal from 'antd/es/modal';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';

const PRESET_OPTIONS = [
    { label: 'Điện máy công nghệ', value: 'electronics-superstore', description: 'Điện thoại, laptop, điện lạnh, gia dụng.' },
    { label: 'Điện thoại và phụ kiện', value: 'phones-accessories', description: 'Showroom smartphone, gear, bảo hành.' },
    { label: 'Máy tính và workstation', value: 'computer-workstation', description: 'PC, laptop, màn hình, server mini.' },
    { label: 'Du lịch và trải nghiệm', value: 'travel-deals', description: 'Tour, combo nghỉ dưỡng, vé trải nghiệm.' },
    { label: 'Mỹ phẩm và làm đẹp', value: 'cosmetics-beauty', description: 'Skincare, makeup, body care, spa.' },
    { label: 'Hóa chất và vật tư công nghiệp', value: 'industrial-chemicals', description: 'Dung môi, xử lý nước, vật tư lab.' },
    { label: 'Xây dựng và nội thất', value: 'construction-materials', description: 'Vật liệu hoàn thiện, nội thất, công cụ.' },
    { label: 'Phụ kiện công nghệ', value: 'tech-accessories', description: 'Gaming gear, sạc nhanh, smart-home.' },
];

const { Paragraph, Text } = Typography;

export default function ThemeDemoDataModal({ open, theme, canGenerateDemoData, onCancel, onSubmit }) {
    const [form] = Form.useForm();

    const handleOk = async () => {
        const values = await form.validateFields();
        const didFinish = await onSubmit?.(values.preset);

        if (didFinish !== false) {
            form.resetFields();
        }
    };

    return (
        <Modal
            title={theme ? `Tạo data test: ${theme.name}` : 'Tạo data test'}
            open={open}
            onCancel={() => {
                form.resetFields();
                onCancel?.();
            }}
            onOk={handleOk}
            okText="Tạo dữ liệu"
            okButtonProps={{ disabled: !theme || !canGenerateDemoData }}
            destroyOnHidden
        >
            <Space direction="vertical" size={16} style={{ width: '100%' }}>
                <Alert
                    type="info"
                    showIcon
                    message="Hệ thống sẽ tạo menu, sản phẩm, tin tức, trang giới thiệu và banner demo cho website hiện tại. Dữ liệu test cũ do hệ thống sinh ra sẽ được thay thế."
                />

                <div>
                    <Text className="card-label">Theme đang chọn</Text>
                    <Paragraph style={{ marginBottom: 0 }}>{theme?.name ?? 'Chưa chọn theme'}</Paragraph>
                </div>

                <Form form={form} layout="vertical" initialValues={{ preset: 'electronics-superstore' }}>
                    <Form.Item
                        name="preset"
                        label="Ngành dữ liệu mẫu"
                        rules={[{ required: true, message: 'Chọn loại dữ liệu test cần tạo' }]}
                    >
                        <Select
                            options={PRESET_OPTIONS}
                            optionRender={(option) => (
                                <div>
                                    <div>{option.data.label}</div>
                                    <Text type="secondary">{option.data.description}</Text>
                                </div>
                            )}
                        />
                    </Form.Item>
                </Form>
            </Space>
        </Modal>
    );
}
