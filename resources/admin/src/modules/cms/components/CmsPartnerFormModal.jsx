import { useEffect } from 'react';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Radio from 'antd/es/radio';
import Row from 'antd/es/row';
import Space from 'antd/es/space';
import Switch from 'antd/es/switch';
import dayjs from 'dayjs';
import MultiMediaPicker from '../../../shared/components/MultiMediaPicker';

const defaultValues = {
    status: 'published',
    sort_order: 0,
    is_featured: false,
};

export default function CmsPartnerFormModal({
    open,
    canManage,
    editingPartner,
    mediaOptions = [],
    callAdminApi,
    onCancel,
    onSubmit,
}) {
    const [form] = Form.useForm();
    const titleValue = Form.useWatch('title', form) ?? '';
    const imageUrl = Form.useWatch('image_url', form) ?? '';

    useEffect(() => {
        form.setFieldsValue({ ...defaultValues, ...(editingPartner ?? {}) });
    }, [editingPartner, form]);

    const handleImageChange = (nextValue) => {
        const selectedUrl = Array.isArray(nextValue) ? (nextValue[0] || '') : '';
        form.setFieldValue('image_url', selectedUrl);
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
            <Form form={form} layout="vertical" initialValues={{ ...defaultValues, ...(editingPartner ?? {}) }}>
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Card size="small" title="Thong tin doi tac">
                        <Row gutter={16}>
                            <Col xs={24} md={14}>
                                <Form.Item name="title" label="Ten doi tac" rules={[{ required: true, message: 'Nhap ten doi tac' }]}>
                                    <Input placeholder="HOABINH" />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Form.Item name="slug" hidden>
                            <Input />
                        </Form.Item>
                        <Row gutter={16}>
                            <Col xs={24} md={8}>
                                <Form.Item name="status" label="Trang thai" rules={[{ required: true, message: 'Chon trang thai' }]}>
                                    <Radio.Group
                                        optionType="button"
                                        buttonStyle="solid"
                                        options={[
                                            { label: 'Ban nhap', value: 'draft' },
                                            { label: 'Da xuat ban', value: 'published' },
                                        ]}
                                    />
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
                        <Form.Item name="image_url" hidden>
                            <Input />
                        </Form.Item>
                        <Form.Item label="Logo / hinh anh" style={{ marginBottom: 16 }}>
                            <MultiMediaPicker
                                open={open}
                                value={imageUrl ? [imageUrl] : []}
                                onChange={handleImageChange}
                                coverValue={imageUrl}
                                onSetCover={(url) => form.setFieldValue('image_url', url)}
                                canManage={canManage}
                                callAdminApi={callAdminApi}
                                mediaOptions={mediaOptions}
                                recordTitle={titleValue || 'Partner logo'}
                                previewTitle="Logo doi tac"
                                uploadButtonLabel="Upload logo / hinh anh"
                                uploadHint="Upload hoac chon tu thu vien media. Neu chon nhieu anh, anh dau tien se duoc dung lam logo."
                                libraryModalTitle="Chon logo doi tac tu thu vien"
                                urlPlaceholder="https://cdn.example.com/logo.png"
                                uploadSuccessMessage="Da them logo doi tac."
                                urlSuccessMessage="Da luu URL vao thu vien media va them logo."
                                uploadErrorMessage="Upload logo doi tac khong thanh cong."
                                urlErrorMessage="Khong the luu logo tu URL."
                                emptyValueMessage="Nhap URL hinh anh truoc khi luu."
                            />
                        </Form.Item>
                        <Form.Item name="image_alt" label="Alt text">
                            <Input placeholder="Logo doi tac" />
                        </Form.Item>
                    </Card>
                </Space>
            </Form>
        </Drawer>
    );
}
