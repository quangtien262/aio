import { useEffect } from 'react';
import Col from 'antd/es/col';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Row from 'antd/es/row';
import Select from 'antd/es/select';

export const emptyCmsCategoryForm = {
    id: null,
    name: '',
    slug: '',
    description: '',
    meta_title: '',
    meta_description: '',
    parent_id: null,
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

export default function CmsCategoryFormModal({ open, canManage, editingCategory, parentOptions = [], onCancel, onSubmit }) {
    const [form] = Form.useForm();

    useEffect(() => {
        form.setFieldsValue(editingCategory);
    }, [editingCategory, form]);

    const handleSubmit = async () => {
        const values = await form.validateFields();

        await onSubmit?.({
            ...values,
            description: values.description || null,
            meta_title: values.meta_title || null,
            meta_description: values.meta_description || null,
            parent_id: values.parent_id || null,
        });

        form.resetFields();
    };

    const handleCancel = () => {
        form.resetFields();
        onCancel?.();
    };

    return (
        <Modal
            title={editingCategory?.id ? 'Cập nhật category CMS' : 'Tạo category CMS'}
            open={open}
            onCancel={handleCancel}
            onOk={handleSubmit}
            okButtonProps={{ disabled: !canManage }}
            width={820}
            destroyOnHidden
        >
            <Form form={form} layout="vertical" initialValues={editingCategory}>
                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="name" label="Tên category" rules={[{ required: true, message: 'Nhập tên category' }]}>
                            <Input placeholder="Tin doanh nghiệp" />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="slug" label="Slug" rules={[{ required: true, message: 'Nhập slug category' }]}>
                            <Input placeholder="tin-doanh-nghiep" />
                        </Form.Item>
                    </Col>
                </Row>

                <Form.Item name="description" label="Mô tả">
                    <Input.TextArea rows={3} placeholder="Mô tả category" />
                </Form.Item>

                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="meta_title" label="SEO Title">
                            <Input placeholder="SEO title" />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="parent_id" label="Parent Category">
                            <Select allowClear showSearch optionFilterProp="label" options={parentOptions} />
                        </Form.Item>
                    </Col>
                </Row>

                <Form.Item name="meta_description" label="SEO Description">
                    <Input.TextArea rows={3} placeholder="Meta description category" />
                </Form.Item>
            </Form>
        </Modal>
    );
}
