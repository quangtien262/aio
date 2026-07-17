import { useEffect } from 'react';
import Checkbox from 'antd/es/checkbox';
import Col from 'antd/es/col';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Modal from 'antd/es/modal';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import SingleMediaPicker from '../../../shared/components/SingleMediaPicker';

export default function CatalogCategoryFormModal({ open, canManage, editingCategory, categoryOptions = [], callAdminApi, submitLoading = false, onCancel, onSubmit }) {
    const [form] = Form.useForm();
    const imageUrl = Form.useWatch('image_url', form) ?? '';
    const categoryName = Form.useWatch('name', form) ?? '';

    useEffect(() => {
        form.setFieldsValue(editingCategory);
    }, [editingCategory, form]);

    const handleSubmit = async () => {
        const values = await form.validateFields();
        const didSubmit = await onSubmit?.({
            ...values,
            parent_id: values.parent_id || null,
            slug: undefined,
            image_url: values.image_url || null,
            is_active: Boolean(values.is_active),
        });

        if (didSubmit !== false) {
            form.resetFields();
        }
    };

    return (
        <Modal
            title={editingCategory?.id ? 'Cập nhật danh mục' : 'Tạo danh mục'}
            open={open}
            onCancel={onCancel}
            onOk={handleSubmit}
            okButtonProps={{ disabled: !canManage }}
            confirmLoading={submitLoading}
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
                    <Col span={24}>
                        <Form.Item name="image_url" hidden>
                            <Input />
                        </Form.Item>
                        <Form.Item label="Ảnh đại diện">
                            <SingleMediaPicker
                                open={open}
                                value={imageUrl}
                                onChange={(nextValue) => form.setFieldValue('image_url', nextValue)}
                                canManage={canManage}
                                callAdminApi={callAdminApi}
                                recordTitle={categoryName || 'Category image'}
                                previewTitle="Ảnh danh mục"
                                uploadButtonLabel="Upload ảnh danh mục"
                                uploadHint="Ảnh upload xong sẽ tự được gán cho danh mục hiện tại."
                                libraryModalTitle="Chọn ảnh danh mục từ thư viện"
                                urlPlaceholder="https://example.com/category.jpg"
                                uploadSuccessMessage="Đã upload và gán ảnh danh mục."
                                urlSuccessMessage="Đã lưu URL vào thư viện media và gán cho danh mục."
                                uploadErrorMessage="Upload ảnh danh mục không thành công."
                                urlErrorMessage="Không thể lưu ảnh danh mục từ URL."
                            />
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
                        <Form.Item name="is_active" valuePropName="checked" label=" " colon={false}>
                            <Checkbox>Kích hoạt danh mục</Checkbox>
                        </Form.Item>
                    </Col>
                </Row>
            </Form>
        </Modal>
    );
}
