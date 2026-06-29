import { useEffect } from 'react';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Switch from 'antd/es/switch';
import dayjs from 'dayjs';

export default function CmsTestimonialFormModal({ open, canManage, editingTestimonial, mediaOptions = [], onCancel, onSubmit }) {
    const [form] = Form.useForm();

    useEffect(() => {
        form.setFieldsValue(editingTestimonial);
    }, [editingTestimonial, form]);

    const mediaSelectOptions = mediaOptions.map((item) => ({
        label: item.title || item.file_url,
        value: item.id,
        media: item,
    }));

    const handleMediaChange = (mediaId) => {
        const selected = mediaOptions.find((item) => item.id === mediaId);

        if (!selected) {
            return;
        }

        form.setFieldValue('image_url', selected.file_url);

        if (!form.getFieldValue('image_alt')) {
            form.setFieldValue('image_alt', selected.alt_text || selected.title || '');
        }
    };

    const handleSubmit = async () => {
        const values = await form.validateFields();

        await onSubmit?.({
            ...values,
            role: values.role || null,
            company: values.company || null,
            image_url: values.image_url || null,
            image_alt: values.image_alt || null,
            link_url: values.link_url || null,
            sort_order: Number(values.sort_order ?? 0),
            is_featured: Boolean(values.is_featured),
            publish_at: values.status === 'published' ? (values.publish_at || dayjs().format('YYYY-MM-DDTHH:mm:ss')) : null,
        });

        form.resetFields();
    };

    const handleCancel = () => {
        form.resetFields();
        onCancel?.();
    };

    return (
        <Drawer
            title={editingTestimonial?.id ? 'Cập nhật nhận xét' : 'Tạo nhận xét'}
            open={open}
            onClose={handleCancel}
            width={860}
            destroyOnHidden
            className="cms-page-drawer"
            extra={(
                <Space>
                    <Button onClick={handleCancel}>Hủy</Button>
                    <Button type="primary" disabled={!canManage} onClick={handleSubmit}>Lưu nhận xét</Button>
                </Space>
            )}
        >
            <Form form={form} layout="vertical" initialValues={editingTestimonial}>
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Card size="small" title="Thông tin khách hàng">
                        <Row gutter={16}>
                            <Col xs={24} md={12}>
                                <Form.Item name="name" label="Tên khách hàng" rules={[{ required: true, message: 'Nhập tên khách hàng' }]}>
                                    <Input placeholder="Sharah Albert" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item name="status" label="Trạng thái" rules={[{ required: true, message: 'Chọn trạng thái' }]}>
                                    <Select options={[{ label: 'Bản nháp', value: 'draft' }, { label: 'Đã xuất bản', value: 'published' }]} />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Row gutter={16}>
                            <Col xs={24} md={8}>
                                <Form.Item name="role" label="Chức danh">
                                    <Input placeholder="Chủ đầu tư" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="company" label="Công ty">
                                    <Input placeholder="ABC Group" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="sort_order" label="Thứ tự">
                                    <InputNumber min={0} style={{ width: '100%' }} />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Form.Item name="quote" label="Nội dung nhận xét" rules={[{ required: true, message: 'Nhập nội dung nhận xét' }]}>
                            <Input.TextArea rows={5} placeholder="Khách hàng nhận xét về dịch vụ, chất lượng thi công..." />
                        </Form.Item>
                    </Card>

                    <Card size="small" title="Ảnh đại diện">
                        <Row gutter={16}>
                            <Col xs={24} md={8}>
                                <Form.Item shouldUpdate noStyle>
                                    {() => {
                                        const imageUrl = form.getFieldValue('image_url');

                                        return imageUrl ? (
                                            <img src={imageUrl} alt="" style={{ width: '100%', height: 180, objectFit: 'cover', borderRadius: 16, border: '1px solid #dbe7e4' }} />
                                        ) : (
                                            <div style={{ height: 180, borderRadius: 16, border: '1px dashed #cbd5d1', display: 'grid', placeItems: 'center', color: '#8aa19a' }}>Chưa có ảnh</div>
                                        );
                                    }}
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={16}>
                                <Form.Item name="cms_media_id" label="Chọn từ media">
                                    <Select
                                        allowClear
                                        showSearch
                                        optionFilterProp="label"
                                        options={mediaSelectOptions}
                                        placeholder="Chọn ảnh có sẵn"
                                        onChange={handleMediaChange}
                                    />
                                </Form.Item>
                                <Form.Item name="image_url" label="URL ảnh">
                                    <Input placeholder="https://example.com/avatar.jpg" />
                                </Form.Item>
                                <Form.Item name="image_alt" label="Alt text">
                                    <Input placeholder="Ảnh chân dung khách hàng" />
                                </Form.Item>
                            </Col>
                        </Row>
                    </Card>

                    <Card size="small" title="Hiển thị">
                        <Row gutter={16}>
                            <Col xs={24} md={8}>
                                <Form.Item name="is_featured" label="Nhận xét nổi bật" valuePropName="checked">
                                    <Switch />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={16}>
                                <Form.Item name="link_url" label="Link click">
                                    <Input placeholder="/vi/du-an hoặc https://..." />
                                </Form.Item>
                            </Col>
                        </Row>
                    </Card>
                </Space>
            </Form>
        </Drawer>
    );
}
