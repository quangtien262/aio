import { useEffect } from 'react';
import Col from 'antd/es/col';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Row from 'antd/es/row';
import Select from 'antd/es/select';

export const emptyCmsPostForm = {
    id: null,
    title: '',
    slug: '',
    status: 'draft',
    excerpt: '',
    body: '',
    meta_title: '',
    meta_description: '',
    featured_media_id: null,
    category_id: null,
    publish_at: null,
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

export default function CmsPostFormModal({ open, canManage, editingPost, mediaOptions = [], categoryOptions = [], onCancel, onSubmit }) {
    const [form] = Form.useForm();

    useEffect(() => {
        form.setFieldsValue(editingPost);
    }, [editingPost, form]);

    const handleSubmit = async () => {
        const values = await form.validateFields();

        await onSubmit?.({
            ...values,
            excerpt: values.excerpt || null,
            body: values.body || null,
            meta_title: values.meta_title || null,
            meta_description: values.meta_description || null,
            featured_media_id: values.featured_media_id || null,
            category_id: values.category_id || null,
            publish_at: values.publish_at || null,
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
            title={editingPost?.id ? 'Cập nhật bài viết CMS' : 'Tạo bài viết CMS'}
            open={open}
            onCancel={handleCancel}
            onOk={handleSubmit}
            okButtonProps={{ disabled: !canManage }}
            width={900}
            destroyOnHidden
        >
            <Form form={form} layout="vertical" initialValues={editingPost}>
                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="title" label="Tiêu đề" rules={[{ required: true, message: 'Nhập tiêu đề bài viết' }]}>
                            <Input placeholder="Bài viết nổi bật" />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="slug" label="Slug" rules={[{ required: true, message: 'Nhập slug bài viết' }]}>
                            <Input placeholder="bai-viet-noi-bat" />
                        </Form.Item>
                    </Col>
                </Row>

                <Row gutter={16}>
                    <Col span={8}>
                        <Form.Item name="status" label="Trạng thái" rules={[{ required: true, message: 'Chọn trạng thái' }]}>
                            <Select options={[{ label: 'Draft', value: 'draft' }, { label: 'Published', value: 'published' }]} />
                        </Form.Item>
                    </Col>
                    <Col span={8}>
                        <Form.Item name="category_id" label="Category">
                            <Select allowClear showSearch optionFilterProp="label" options={categoryOptions} />
                        </Form.Item>
                    </Col>
                    <Col span={8}>
                        <Form.Item name="publish_at" label="Publish At">
                            <Input type="datetime-local" />
                        </Form.Item>
                    </Col>
                </Row>

                <Form.Item name="excerpt" label="Mô tả ngắn">
                    <Input.TextArea rows={3} placeholder="Tóm tắt bài viết" />
                </Form.Item>

                <Form.Item name="body" label="Nội dung">
                    <Input.TextArea rows={8} placeholder="Nội dung bài viết" />
                </Form.Item>

                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="meta_title" label="SEO Title">
                            <Input placeholder="SEO title" />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="featured_media_id" label="Featured Media">
                            <Select allowClear showSearch optionFilterProp="label" options={mediaOptions.map((item) => ({ label: item.title, value: item.id }))} />
                        </Form.Item>
                    </Col>
                </Row>

                <Form.Item name="meta_description" label="SEO Description">
                    <Input.TextArea rows={3} placeholder="Meta description bài viết" />
                </Form.Item>

                <Row gutter={16}>
                    <Col span={8}>
                        <Form.Item name="website_key" label="Website" rules={[{ required: true, message: 'Nhập website key áp dụng cho bài viết' }]} extra="Scope chính cho CMS ecommerce là website.">
                            <Input placeholder="storefront-main" />
                        </Form.Item>
                    </Col>
                    <Col span={8}>
                        <Form.Item name="owner_key" label="Owner (tuỳ chọn)">
                            <Input placeholder="owner-system" />
                        </Form.Item>
                    </Col>
                    <Col span={8}>
                        <Form.Item name="tenant_key" label="Tenant (tuỳ chọn)">
                            <Input placeholder="tenant-a" />
                        </Form.Item>
                    </Col>
                </Row>
            </Form>
        </Modal>
    );
}
