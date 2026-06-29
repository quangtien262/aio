import { useEffect, useRef } from 'react';
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

function toSlug(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'd')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export default function CmsPartnerFormModal({ open, canManage, editingPartner, mediaOptions = [], onCancel, onSubmit }) {
    const [form] = Form.useForm();
    const slugEditedRef = useRef(Boolean(editingPartner?.id));
    const titleValue = Form.useWatch('title', form) ?? '';

    useEffect(() => {
        form.setFieldsValue(editingPartner);
        slugEditedRef.current = Boolean(editingPartner?.id || editingPartner?.slug);
    }, [editingPartner, form]);

    useEffect(() => {
        if (slugEditedRef.current) {
            return;
        }

        form.setFieldValue('slug', toSlug(titleValue));
    }, [form, titleValue]);

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

    const handleSlugChange = (event) => {
        slugEditedRef.current = true;
        form.setFieldValue('slug', toSlug(event.target.value));
    };

    const handleSubmit = async () => {
        const values = await form.validateFields();

        await onSubmit?.({
            ...values,
            description: values.description || null,
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
            title={editingPartner?.id ? 'Cap nhat doi tac CMS' : 'Tao doi tac CMS'}
            open={open}
            onClose={handleCancel}
            width={860}
            destroyOnHidden
            className="cms-page-drawer"
            extra={(
                <Space>
                    <Button onClick={handleCancel}>Huy</Button>
                    <Button type="primary" disabled={!canManage} onClick={handleSubmit}>Luu doi tac</Button>
                </Space>
            )}
        >
            <Form form={form} layout="vertical" initialValues={editingPartner}>
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Card size="small" title="Thong tin doi tac">
                        <Row gutter={16}>
                            <Col xs={24} md={14}>
                                <Form.Item name="title" label="Ten doi tac" rules={[{ required: true, message: 'Nhap ten doi tac' }]}>
                                    <Input placeholder="HOABINH" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={10}>
                                <Form.Item name="slug" label="Slug" rules={[{ required: true, message: 'Nhap slug doi tac' }]}>
                                    <Input placeholder="hoabinh" onChange={handleSlugChange} />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Row gutter={16}>
                            <Col xs={24} md={8}>
                                <Form.Item name="status" label="Trang thai" rules={[{ required: true, message: 'Chon trang thai' }]}>
                                    <Select options={[{ label: 'Ban nhap', value: 'draft' }, { label: 'Da xuat ban', value: 'published' }]} />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="sort_order" label="Thu tu">
                                    <InputNumber min={0} style={{ width: '100%' }} />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="is_featured" label="Doi tac noi bat" valuePropName="checked">
                                    <Switch />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Form.Item name="description" label="Mo ta">
                            <Input.TextArea rows={3} placeholder="Mo ta ngan ve doi tac." />
                        </Form.Item>
                        <Form.Item name="link_url" label="Link click">
                            <Input placeholder="https://example.com hoac /vi/doi-tac" />
                        </Form.Item>
                    </Card>

                    <Card size="small" title="Logo / hinh anh">
                        <Row gutter={16}>
                            <Col xs={24} md={8}>
                                <Form.Item shouldUpdate noStyle>
                                    {() => {
                                        const imageUrl = form.getFieldValue('image_url');

                                        return imageUrl ? (
                                            <img src={imageUrl} alt="" style={{ width: '100%', height: 150, objectFit: 'contain', borderRadius: 16, border: '1px solid #dbe7e4', padding: 16, background: '#fff' }} />
                                        ) : (
                                            <div style={{ height: 150, borderRadius: 16, border: '1px dashed #cbd5d1', display: 'grid', placeItems: 'center', color: '#8aa19a' }}>Chua co logo</div>
                                        );
                                    }}
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={16}>
                                <Form.Item name="cms_media_id" label="Chon tu media">
                                    <Select
                                        allowClear
                                        showSearch
                                        optionFilterProp="label"
                                        options={mediaSelectOptions}
                                        placeholder="Chon anh co san"
                                        onChange={handleMediaChange}
                                    />
                                </Form.Item>
                                <Form.Item name="image_url" label="URL logo / hinh anh">
                                    <Input placeholder="https://example.com/logo.png" />
                                </Form.Item>
                                <Form.Item name="image_alt" label="Alt text">
                                    <Input placeholder="Logo doi tac" />
                                </Form.Item>
                            </Col>
                        </Row>
                    </Card>
                </Space>
            </Form>
        </Drawer>
    );
}
