import { useEffect } from 'react';
import Checkbox from 'antd/es/checkbox';
import Col from 'antd/es/col';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Modal from 'antd/es/modal';
import Row from 'antd/es/row';
import Select from 'antd/es/select';

const placementOptions = [
    { label: 'Hero chính', value: 'hero-main' },
    { label: 'Hero phụ', value: 'hero-side' },
];

export default function SiteBannerFormModal({ open, canManage, editingBanner, onCancel, onSubmit }) {
    const [form] = Form.useForm();

    useEffect(() => {
        form.setFieldsValue(editingBanner);
    }, [editingBanner, form]);

    const handleSubmit = async () => {
        const values = await form.validateFields();
        await onSubmit?.({
            ...values,
            theme_key: values.theme_key || null,
            title: values.title || null,
            subtitle: values.subtitle || null,
            link_url: values.link_url || null,
            badge: values.badge || null,
            eyebrow: values.eyebrow || null,
            summary: values.summary || null,
            button_label: values.button_label || null,
            website_key: values.website_key || null,
            owner_key: values.owner_key || null,
            tenant_key: values.tenant_key || null,
            is_active: Boolean(values.is_active),
        });
        form.resetFields();
    };

    return (
        <Modal
            title={editingBanner?.id ? 'Cập nhật banner' : 'Tạo banner'}
            open={open}
            onCancel={onCancel}
            onOk={handleSubmit}
            okButtonProps={{ disabled: !canManage }}
            width={920}
            destroyOnHidden
        >
            <Form form={form} layout="vertical" initialValues={editingBanner}>
                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="theme_key" label="Theme key">
                            <Input placeholder="TH0001" />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="placement" label="Vị trí" rules={[{ required: true, message: 'Chọn vị trí banner' }]}>
                            <Select options={placementOptions} />
                        </Form.Item>
                    </Col>
                </Row>
                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="title" label="Tiêu đề">
                            <Input placeholder="Deal sốc cuối tuần" />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="subtitle" label="Phụ đề">
                            <Input placeholder="Ưu đãi theo ngành hàng" />
                        </Form.Item>
                    </Col>
                </Row>
                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="image_url" label="Ảnh banner" rules={[{ required: true, message: 'Nhập URL ảnh banner' }]}>
                            <Input placeholder="https://cdn.example.com/banner.jpg" />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="link_url" label="Link click">
                            <Input placeholder="/danh-muc/dien-thoai" />
                        </Form.Item>
                    </Col>
                </Row>
                <Row gutter={16}>
                    <Col span={8}>
                        <Form.Item name="badge" label="Badge">
                            <Input placeholder="Chỉ từ 399K" />
                        </Form.Item>
                    </Col>
                    <Col span={8}>
                        <Form.Item name="eyebrow" label="Eyebrow">
                            <Input placeholder="Flash sale" />
                        </Form.Item>
                    </Col>
                    <Col span={8}>
                        <Form.Item name="button_label" label="Nhãn nút">
                            <Input placeholder="Mua ngay" />
                        </Form.Item>
                    </Col>
                </Row>
                <Form.Item name="summary" label="Mô tả dài">
                    <Input.TextArea rows={3} placeholder="Mô tả dùng cho hero lớn" />
                </Form.Item>
                <Row gutter={16}>
                    <Col span={6}>
                        <Form.Item name="sort_order" label="Thứ tự">
                            <InputNumber min={0} precision={0} style={{ width: '100%' }} />
                        </Form.Item>
                    </Col>
                    <Col span={6}>
                        <Form.Item name="website_key" label="Website">
                            <Input placeholder="website-main" />
                        </Form.Item>
                    </Col>
                    <Col span={6}>
                        <Form.Item name="owner_key" label="Owner">
                            <Input placeholder="demo-content" />
                        </Form.Item>
                    </Col>
                    <Col span={6}>
                        <Form.Item name="tenant_key" label="Tenant">
                            <Input placeholder="theme-seeder" />
                        </Form.Item>
                    </Col>
                </Row>
                <Form.Item name="is_active" valuePropName="checked" label=" " colon={false}>
                    <Checkbox>Kích hoạt banner</Checkbox>
                </Form.Item>
            </Form>
        </Modal>
    );
}
