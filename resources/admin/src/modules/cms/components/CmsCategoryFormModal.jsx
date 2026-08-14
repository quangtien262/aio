import { useEffect } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Col from 'antd/es/col';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import LocalizedContentTabs from '../../../shared/components/LocalizedContentTabs';

export const emptyCmsCategoryForm = {
    id: null,
    name: '',
    slug: '',
    description: '',
    meta_title: '',
    meta_description: '',
    parent_id: null,
    website_key: '',
};

export default function CmsCategoryFormModal({ open, canManage, translationMode = false, editingCategory, parentOptions = [], localeOptions = [], contentLocale = 'vi', sourceLocale = 'vi', submitLoading = false, onCancel, onSubmit, onLocaleChange }) {
    const [form] = Form.useForm();

    useEffect(() => {
        form.setFieldsValue(editingCategory);
    }, [editingCategory, form]);

    const handleSubmit = async (publish = true) => {
        const values = await form.validateFields();

        const didSubmit = await onSubmit?.({
            ...values,
            description: values.description || null,
            meta_title: values.meta_title || null,
            meta_description: values.meta_description || null,
            parent_id: values.parent_id || null,
        }, { publish });

        if (didSubmit !== false) {
            form.resetFields();
        }
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
            footer={(
                <Space>
                    <Button onClick={handleCancel}>Hủy</Button>
                    {translationMode ? <Button disabled={!canManage} loading={submitLoading} onClick={() => handleSubmit(false)}>Lưu nháp</Button> : null}
                    <Button type="primary" disabled={!canManage} loading={submitLoading} onClick={() => handleSubmit(true)}>
                        {translationMode ? 'Lưu và xuất bản' : 'Lưu danh mục'}
                    </Button>
                </Space>
            )}
            confirmLoading={submitLoading}
            width={820}
            destroyOnHidden
        >
            <LocalizedContentTabs
                localeOptions={localeOptions}
                contentLocale={contentLocale}
                sourceLocale={sourceLocale}
                editingRecord={editingCategory}
                entityLabel="danh mục tin tức"
                translationDescription="Tên, slug, mô tả và SEO được lưu riêng cho ngôn ngữ này. Quan hệ danh mục cha dùng chung từ bản gốc."
                sourceDescription="Đây là ngôn ngữ gốc. Quan hệ danh mục cha và cấu trúc được quản lý tại đây."
                isDirty={() => form.isFieldsTouched()}
                getCurrentValues={() => form.getFieldsValue(true)}
                onLocaleChange={onLocaleChange}
            />
            <Form form={form} layout="vertical" initialValues={editingCategory}>
                {translationMode ? (
                    <Alert
                        type="info"
                        showIcon
                        message="Chế độ dịch chỉ lưu nội dung và SEO. Quan hệ danh mục cha dùng chung từ bản gốc."
                        style={{ marginBottom: 16 }}
                    />
                ) : null}
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
                            <Select disabled={translationMode} allowClear showSearch optionFilterProp="label" options={parentOptions} />
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
