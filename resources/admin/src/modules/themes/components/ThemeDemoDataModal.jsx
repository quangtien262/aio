import { useEffect, useMemo } from 'react';
import Alert from 'antd/es/alert';
import Form from 'antd/es/form';
import Modal from 'antd/es/modal';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';

const COMMERCE_PRESET_OPTIONS = [
    { label: 'Xưởng may quần áo sỉ lẻ', value: 'garment-workshop', description: 'Đồng phục, OEM/ODM, local brand, retail may sẵn.' },
    { label: 'Thời trang lookbook và retail', value: 'fashion-studio', description: 'Capsule collection, ready-to-wear, streetwear, showroom retail.' },
    { label: 'Điện máy công nghệ', value: 'electronics-superstore', description: 'Điện thoại, laptop, điện lạnh, gia dụng.' },
    { label: 'Điện thoại và phụ kiện', value: 'phones-accessories', description: 'Showroom smartphone, gear, bảo hành.' },
    { label: 'Máy tính và workstation', value: 'computer-workstation', description: 'PC, laptop, màn hình, server mini.' },
    { label: 'Du lịch và trải nghiệm', value: 'travel-deals', description: 'Tour, combo nghỉ dưỡng, vé trải nghiệm.' },
    { label: 'Mỹ phẩm và làm đẹp', value: 'cosmetics-beauty', description: 'Skincare, makeup, body care, spa.' },
    { label: 'Hóa chất và vật tư công nghiệp', value: 'industrial-chemicals', description: 'Dung môi, xử lý nước, vật tư lab.' },
    { label: 'Xây dựng và nội thất', value: 'construction-materials', description: 'Vật liệu hoàn thiện, nội thất, công cụ.' },
    { label: 'Phụ kiện công nghệ', value: 'tech-accessories', description: 'Gaming gear, sạc nhanh, smart-home.' },
];

const SERVICE_PRESET_OPTIONS = [
    { label: 'Nhà xe sân bay và city transfer', value: 'ser-airport-city', description: 'Đưa đón sân bay, city transfer, khách gia đình và khách công tác.' },
    { label: 'Nhà xe du lịch và xe đoàn', value: 'ser-tour-coach', description: 'Xe 16-45 chỗ, tour công ty, trường học, đoàn khách.' },
    { label: 'Shuttle doanh nghiệp và hàng nhẹ', value: 'ser-business-cargo', description: 'Shuttle theo hợp đồng, chở nhân sự và vận chuyển hàng nhẹ.' },
];

const { Paragraph, Text } = Typography;

export default function ThemeDemoDataModal({ open, theme, mode = 'generate', canGenerateDemoData, onCancel, onSubmit }) {
    const [form] = Form.useForm();
    const presetOptions = useMemo(() => {
        if ((theme?.website_type ?? '').toLowerCase() === 'service') {
            return SERVICE_PRESET_OPTIONS;
        }

        return COMMERCE_PRESET_OPTIONS;
    }, [theme?.website_type]);

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setFieldsValue({
            preset: presetOptions[0]?.value,
        });
    }, [form, open, presetOptions]);

    const handleOk = async () => {
        const values = await form.validateFields();
        const didFinish = await onSubmit?.(values.preset);

        if (didFinish !== false) {
            form.resetFields();
        }
    };

    return (
        <Modal
            title={theme ? `${mode === 'rebuild' ? 'Rebuild curated local demo' : 'Tạo data test'}: ${theme.name}` : mode === 'rebuild' ? 'Rebuild curated local demo' : 'Tạo data test'}
            open={open}
            onCancel={() => {
                form.resetFields();
                onCancel?.();
            }}
            onOk={handleOk}
            okText={mode === 'rebuild' ? 'Rebuild dữ liệu local' : 'Tạo dữ liệu'}
            okButtonProps={{ disabled: !theme || !canGenerateDemoData }}
            destroyOnHidden
        >
            <Space direction="vertical" size={16} style={{ width: '100%' }}>
                <Alert
                    type="info"
                    showIcon
                    message={
                        mode === 'rebuild'
                            ? 'Hệ thống sẽ regenerate lại bộ demo data theo preset đã chọn và buộc toàn bộ ảnh demo đi qua local curated asset pool trong repo. Chỉ dữ liệu test đã được hệ thống đánh dấu mới bị thay thế hoặc xóa về sau.'
                            : (theme?.website_type ?? '').toLowerCase() === 'service'
                                ? 'Hệ thống sẽ tạo menu, gói dịch vụ, cẩm nang, trang giới thiệu và banner demo cho website hiện tại. Chỉ dữ liệu test đã được hệ thống đánh dấu mới bị thay thế hoặc xóa về sau.'
                                : 'Hệ thống sẽ tạo menu, sản phẩm, tin tức, trang giới thiệu và banner demo cho website hiện tại. Với TH0002 có thể chọn preset xưởng may hoặc thời trang để sinh taxonomy, hero và catalog đúng domain may mặc. Chỉ dữ liệu test đã được hệ thống đánh dấu mới bị thay thế hoặc xóa về sau.'
                    }
                />

                <div>
                    <Text className="card-label">Theme đang chọn</Text>
                    <Paragraph style={{ marginBottom: 0 }}>{theme?.name ?? 'Chưa chọn theme'}</Paragraph>
                </div>

                <Form form={form} layout="vertical" initialValues={{ preset: presetOptions[0]?.value }}>
                    <Form.Item
                        name="preset"
                        label="Ngành dữ liệu mẫu"
                        rules={[{ required: true, message: 'Chọn loại dữ liệu test cần tạo' }]}
                    >
                        <Select
                            options={presetOptions}
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
