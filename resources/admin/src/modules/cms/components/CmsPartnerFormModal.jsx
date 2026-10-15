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
            title={editingPartner?.id ? 'Cập nhật đối tác' : 'Tạo đối tác'}
            open={open}
            onClose={handleCancel}
            width={860}
            destroyOnHidden
            className="cms-page-drawer"
            extra={(
                <Space>
                    <Button onClick={handleCancel}>Hủy</Button>
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
                translationDescription="Tên, slug, mô tả đối tác, mô tả ảnh và trạng thái xuất bản được lưu riêng cho ngôn ngữ này. Logo, liên kết, thứ tự và thiết lập nổi bật tiếp tục dùng từ bản gốc."
                sourceDescription="Đây là ngôn ngữ gốc. Logo, liên kết, thứ tự và thiết lập nổi bật được quản lý tại đây."
                isDirty={() => form.isFieldsTouched()}
                getCurrentValues={() => form.getFieldsValue(true)}
                onLocaleChange={onLocaleChange}
            />
            <Form form={form} layout="vertical" initialValues={{ ...defaultValues, ...(editingPartner ?? {}) }}>
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Card size="small" title="Thông tin đối tác">
                        <Row gutter={16}>
                            <Col xs={24} md={14}>
                                <Form.Item name="title" label="Tên đối tác" rules={[{ required: true, message: 'Nhập tên đối tác' }]}>
                                    <Input placeholder="HOABINH" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={10}>
                                <Form.Item name="slug" label="Slug" rules={[{ required: true, message: 'Nhập slug đối tác' }]}>
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
                                            { label: 'Bản nháp', value: 'draft' },
                                            { label: 'Đã xuất bản', value: 'published' },
                                        ]}
                                    />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="sort_order" label="Thứ tự">
                                    <InputNumber disabled={translationMode} min={0} style={{ width: '100%' }} />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="is_featured" label="Đối tác nổi bật" valuePropName="checked">
                                    <Switch disabled={translationMode} />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Form.Item name="description" label="Mô tả">
                            <Input.TextArea rows={3} placeholder="Mô tả ngắn về đối tác." />
                        </Form.Item>
                        <Form.Item name="link_url" label="Liên kết">
                            <Input disabled={translationMode} placeholder="https://example.com hoặc /vi/doi-tac" />
                        </Form.Item>
                    </Card>

                    <Card size="small" title="Logo / hình ảnh">
                        <Form.Item name="image_url" hidden>
                            <Input />
                        </Form.Item>
                        <Form.Item label="Logo / hình ảnh" style={{ marginBottom: 16 }}>
                            <MultiMediaPicker
                                open={open}
                                value={imageUrl ? [imageUrl] : []}
                                onChange={handleImageChange}
                                coverValue={imageUrl}
                                onSetCover={(url) => form.setFieldValue('image_url', url)}
                                canManage={canManage && !translationMode}
                                callAdminApi={callAdminApi}
                                mediaOptions={mediaOptions}
                                recordTitle={titleValue || 'Logo đối tác'}
                                previewTitle="Logo đối tác"
                                uploadButtonLabel="Tải lên logo / hình ảnh"
                                uploadHint="Tải ảnh lên hoặc chọn từ thư viện. Nếu chọn nhiều ảnh, ảnh đầu tiên sẽ được dùng làm logo."
                                libraryModalTitle="Chọn logo đối tác từ thư viện"
                                urlPlaceholder="https://cdn.example.com/logo.png"
                                uploadSuccessMessage="Đã thêm logo đối tác."
                                urlSuccessMessage="Đã lưu URL vào thư viện và thêm logo."
                                uploadErrorMessage="Tải logo đối tác không thành công."
                                urlErrorMessage="Không thể lưu logo từ URL."
                                emptyValueMessage="Nhập URL hình ảnh trước khi lưu."
                            />
                        </Form.Item>
                        <Form.Item name="image_alt" label="Mô tả ảnh">
                            <Input placeholder="Logo đối tác" />
                        </Form.Item>
                    </Card>
                </Space>
            </Form>
        </Drawer>
    );
}
