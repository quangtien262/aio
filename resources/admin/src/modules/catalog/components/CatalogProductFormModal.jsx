import { useEffect } from 'react';
import Col from 'antd/es/col';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Modal from 'antd/es/modal';
import Row from 'antd/es/row';

export const emptyCatalogProductForm = {
    id: null,
    name: '',
    sku: '',
    price: 0,
    stock: 0,
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

export default function CatalogProductFormModal({ open, canManage, editingProduct, onCancel, onSubmit }) {
    const [form] = Form.useForm();

    useEffect(() => {
        form.setFieldsValue(editingProduct);
    }, [editingProduct, form]);

    const handleSubmit = async () => {
        const values = await form.validateFields();

        await onSubmit?.({
            ...values,
            website_key: values.website_key || null,
            owner_key: values.owner_key || null,
            tenant_key: values.tenant_key || null,
        });

        form.resetFields();
    };

    const handleCancel = () => {
        form.resetFields();
        onCancel?.();
    };

    return (
        <Modal
            title={editingProduct?.id ? 'Cập nhật sản phẩm' : 'Tạo sản phẩm'}
            open={open}
            onCancel={handleCancel}
            onOk={handleSubmit}
            okButtonProps={{ disabled: !canManage }}
            width={860}
            destroyOnHidden
        >
            <Form form={form} layout="vertical" initialValues={editingProduct}>
                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="name" label="Tên sản phẩm" rules={[{ required: true, message: 'Nhập tên sản phẩm' }]}>
                            <Input placeholder="Áo sơ mi xanh" />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="sku" label="SKU" rules={[{ required: true, message: 'Nhập SKU' }]}>
                            <Input placeholder="SKU-001" />
                        </Form.Item>
                    </Col>
                </Row>

                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="price" label="Giá" rules={[{ required: true, message: 'Nhập giá' }]}>
                            <InputNumber min={0} style={{ width: '100%' }} />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="stock" label="Tồn kho" rules={[{ required: true, message: 'Nhập tồn kho' }]}>
                            <InputNumber min={0} precision={0} style={{ width: '100%' }} />
                        </Form.Item>
                    </Col>
                </Row>

                <Row gutter={16}>
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
                    <Col span={8}>
                        <Form.Item name="tenant_key" label="Tenant">
                            <Input placeholder="tenant-a" />
                        </Form.Item>
                    </Col>
                </Row>
            </Form>
        </Modal>
    );
}
