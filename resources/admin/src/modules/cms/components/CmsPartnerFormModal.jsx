import { useEffect, useRef } from 'react';
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
import LocalizedContentTabs from '../../../shared/components/LocalizedContentTabs';
import MultiMediaPicker from '../../../shared/components/MultiMediaPicker';
import { toSlug } from '../../../shared/utils/slug';

const defaultValues = {
    status: 'published',
    sort_order: 0,
    is_featured: false,
};

export default function CmsPartnerFormModal({
    open,
    canManage,
    translationMode = false,
    editingPartner,
    mediaOptions = [],
    localeOptions = [],
    contentLocale = 'vi',
    sourceLocale = 'vi',
    callAdminApi,
    onCancel,
    onSubmit,
    onLocaleChange,
}) {
    const [form] = Form.useForm();
    const lastTitleRef = useRef('');
    const titleValue = Form.useWatch('title', form) ?? '';
    const imageUrl = Form.useWatch('image_url', form) ?? '';

    useEffect(() => {
        form.setFieldsValue({ ...defaultValues, ...(editingPartner ?? {}) });
        lastTitleRef.current = String(editingPartner?.title ?? '');
    }, [editingPartner, form]);

    useEffect(() => {
        if (titleValue === lastTitleRef.current) {
            return;
        }

        form.setFieldValue('slug', toSlug(titleValue));
        lastTitleRef.current = titleValue;
    }, [form, titleValue]);

    const handleSlugChange = (event) => {
        form.setFieldValue('slug', toSlug(event.target.value, { trimEdges: false }));
    };

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
                    <Button type="primary" disabled={!canManage || (!editingPartner?.id && translationMode)} onClick={handleSubmit}>
                        {!editingPartner?.id && translationMode ? 'Lưu tại ngôn ngữ gốc' : (translationMode ? 'Lưu bản dịch' : 'Lưu đối tác')}
                    </Button>
                </Space>
            )}
        >
            <LocalizedContentTabs
                localeOptions={localeOptions}
                contentLocale={contentLocale}
                sourceLocale={sourceLocale}
                editingRecord={editingPartner}
                entityLabel="đối tác"
                translationDescription="Tên, slug, mô tả, alt text của logo và trạng thái xuất bản được lưu riêng cho ngôn ngữ này. Logo, liên kết, thứ tự và thiết lập nổi bật tiếp tục dùng từ bản gốc."
                sourceDescription="Đây là ngôn ngữ gốc. Logo, liên kết, thứ tự và thiết lập nổi bật được quản lý tại đây."
                isDirty={() => form.isFieldsTouched()}
                getCurrentValues={() => form.getFieldsValue(true)}
                onLocaleChange={onLocaleChange}
            />
            <Form form={form} layout="vertical" initialValues={{ ...defaultValues, ...(editingPartner ?? {}) }}>
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
                                    <Input placeholder="hoa-binh" onChange={handleSlugChange} />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Row gutter={16}>
                            <Col xs={24} md={8}>
                                <Form.Item name="status" label="Trạng thái" rules={[{ required: true, message: 'Chọn trạng thái' }]}>
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
                                    <InputNumber disabled={translationMode} min={0} style={{ width: '100%' }} />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="is_featured" label="Doi tac noi bat" valuePropName="checked">
                                    <Switch disabled={translationMode} />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Form.Item name="description" label="Mo ta">
                            <Input.TextArea rows={3} placeholder="Mo ta ngan ve doi tac." />
                        </Form.Item>
                        <Form.Item name="link_url" label="Link click">
                            <Input disabled={translationMode} placeholder="https://example.com hoac /vi/doi-tac" />
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
                                canManage={canManage && !translationMode}
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
