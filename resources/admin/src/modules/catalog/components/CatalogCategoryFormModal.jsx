import { useEffect } from 'react';
import Checkbox from 'antd/es/checkbox';
import Col from 'antd/es/col';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Modal from 'antd/es/modal';
import Row from 'antd/es/row';
import Select from 'antd/es/select';

export default function CatalogCategoryFormModal({ open, canManage, editingCategory, categoryOptions = [], onCancel, onSubmit }) {
    const [form] = Form.useForm();

    useEffect(() => {
        form.setFieldsValue(editingCategory);
    }, [editingCategory, form]);

    const handleSubmit = async () => {
        const values = await form.validateFields();
        await onSubmit?.({
            ...values,
            parent_id: values.parent_id || null,
            image_url: values.image_url || null,
            website_key: values.website_key || null,
            owner_key: values.owner_key || null,
            tenant_key: values.tenant_key || null,
            is_active: Boolean(values.is_active),
        });
        form.resetFields();
    };

    return (
        <Modal
            title={editingCategory?.id ? 'Cập nhật danh mục' : 'Tạo danh mục'}
            open={open}
            onCancel={onCancel}
            onOk={handleSubmit}
            okButtonProps={{ disabled: !canManage }}
            width={860}
            destroyOnHidden
        >
            <Form form={form} layout="vertical" initialValues={editingCategory}>
                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="name" label="Tên danh mục" rules={[{ required: true, message: 'Nhập tên danh mục' }]}>
                            <Input placeholder="Điện thoại" />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="parent_id" label="Danh mục cha">
                            <Select allowClear options={categoryOptions} placeholder="Danh mục gốc" />
                        </Form.Item>
                    </Col>
                </Row>
                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="slug" label="Slug public">
                            <Input placeholder="dien-thoai" />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="image_url" label="Ảnh đại diện">
                            <Input placeholder="https://cdn.example.com/category.jpg" />
                        </Form.Item>
                    </Col>
                </Row>
                <Form.Item name="description" label="Mô tả">
                    <Input.TextArea rows={4} placeholder="Mô tả ngắn cho landing page danh mục" />
                </Form.Item>
                <Row gutter={16}>
                    <Col span={8}>
                        <Form.Item name="sort_order" label="Thứ tự">
                            <InputNumber min={0} precision={0} style={{ width: '100%' }} />
                        </Form.Item>
                    </Col>
                    <Col span={8}>
                        <Form.Item name="website_key" label="Website">
                            <Input placeholder="website-main" />
                        </Form.Item>
                    </Col>
                    <Col span={8}>
                        <Form.Item name="owner_key" label="Owner">
                            <Input placeholder="owner-system" />
                        </Form.Item>
                    </Col>
                </Row>
                <Row gutter={16}>
                    <Col span={8}>
                        <Form.Item name="tenant_key" label="Tenant">
                            <Input placeholder="tenant-a" />
                        </Form.Item>
                    </Col>
                    <Col span={8}>
                        <Form.Item name="is_active" valuePropName="checked" label=" " colon={false}>
                            <Checkbox>Kích hoạt danh mục</Checkbox>
                        </Form.Item>
                    </Col>
                </Row>
            </Form>
        </Modal>
    );
}
