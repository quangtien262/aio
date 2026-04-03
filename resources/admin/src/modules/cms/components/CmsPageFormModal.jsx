import { useEffect } from 'react';
import Col from 'antd/es/col';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Row from 'antd/es/row';
import Select from 'antd/es/select';

export const emptyCmsPageForm = {
    id: null,
    title: '',
    slug: '',
    status: 'draft',
    body: '',
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

export default function CmsPageFormModal({ open, canManage, editingPage, onCancel, onSubmit }) {
    const [form] = Form.useForm();

    useEffect(() => {
        form.setFieldsValue(editingPage);
    }, [editingPage, form]);

    const handleSubmit = async () => {
        const values = await form.validateFields();

        await onSubmit?.({
            ...values,
            website_key: values.website_key || null,
            owner_key: values.owner_key || null,
            tenant_key: values.tenant_key || null,
            body: values.body || null,
        });

        form.resetFields();
    };

    const handleCancel = () => {
        form.resetFields();
        onCancel?.();
    };

    return (
        <Modal
            title={editingPage?.id ? 'Cập nhật trang CMS' : 'Tạo trang CMS'}
            open={open}
            onCancel={handleCancel}
            onOk={handleSubmit}
            okButtonProps={{ disabled: !canManage }}
            width={860}
            destroyOnHidden
        >
            <Form form={form} layout="vertical" initialValues={editingPage}>
                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="title" label="Tiêu đề" rules={[{ required: true, message: 'Nhập tiêu đề trang' }]}>
                            <Input placeholder="VD: Trang giới thiệu" />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="slug" label="Slug" rules={[{ required: true, message: 'Nhập slug' }]}>
                            <Input placeholder="trang-gioi-thieu" />
                        </Form.Item>
                    </Col>
                </Row>

                <Form.Item name="status" label="Trạng thái" rules={[{ required: true, message: 'Chọn trạng thái' }]}>
                    <Select
                        options={[
                            { label: 'Draft', value: 'draft' },
                            { label: 'Published', value: 'published' },
                            { label: 'Archived', value: 'archived' },
                        ]}
                    />
                </Form.Item>

                <Form.Item name="body" label="Nội dung">
                    <Input.TextArea rows={6} placeholder="Nội dung trang CMS" />
                </Form.Item>

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
