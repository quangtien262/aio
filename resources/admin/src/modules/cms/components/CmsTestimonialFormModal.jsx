import { useEffect, useMemo, useRef, useState } from 'react';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import message from 'antd/es/message';
import Modal from 'antd/es/modal';
import Pagination from 'antd/es/pagination';
import Radio from 'antd/es/radio';
import Row from 'antd/es/row';
import Space from 'antd/es/space';
import Switch from 'antd/es/switch';
import Typography from 'antd/es/typography';
import dayjs from 'dayjs';

const { Text } = Typography;
const imageLibraryPageSize = 8;
const defaultTestimonialValues = {
    status: 'published',
    is_featured: false,
    sort_order: 0,
};

export default function CmsTestimonialFormModal({
    open,
    canManage,
    editingTestimonial,
    mediaOptions = [],
    callAdminApi,
    onCancel,
    onSubmit,
}) {
    const [form] = Form.useForm();
    const [messageApi, messageContextHolder] = message.useMessage();
    const [imageMode, setImageMode] = useState('upload');
    const [imageUploading, setImageUploading] = useState(null);
    const [imageLibraryOpen, setImageLibraryOpen] = useState(false);
    const [imageLibraryPage, setImageLibraryPage] = useState(1);
    const [imageKeyword, setImageKeyword] = useState('');
    const [imageUrlDraft, setImageUrlDraft] = useState('');
    const [imageMediaOptions, setImageMediaOptions] = useState(mediaOptions);
    const imageInputRef = useRef(null);
    const selectedImageMediaId = Form.useWatch('cms_media_id', form) ?? null;
    const selectedImageUrl = Form.useWatch('image_url', form) ?? '';

    useEffect(() => {
        form.setFieldsValue({
            ...defaultTestimonialValues,
            ...(editingTestimonial ?? {}),
        });
        setImageMode(editingTestimonial?.cms_media_id ? 'library' : (editingTestimonial?.image_url ? 'url' : 'upload'));
        setImageUrlDraft(editingTestimonial?.cms_media_id ? '' : (editingTestimonial?.image_url ?? ''));
        setImageKeyword('');
        setImageLibraryPage(1);
        setImageLibraryOpen(false);
    }, [editingTestimonial, form]);

    useEffect(() => {
        setImageMediaOptions((currentOptions) => {
            const nextMap = new Map(currentOptions.map((item) => [item.id, item]));

            mediaOptions.forEach((item) => {
                nextMap.set(item.id, item);
            });

            return Array.from(nextMap.values());
        });
    }, [mediaOptions]);

    const selectedImageMedia = useMemo(
        () => imageMediaOptions.find((item) => String(item.id) === String(selectedImageMediaId)) ?? null,
        [imageMediaOptions, selectedImageMediaId]
    );

    const filteredImageMediaOptions = useMemo(() => {
        const keyword = imageKeyword.trim().toLowerCase();

        if (!keyword) {
            return imageMediaOptions;
        }

        return imageMediaOptions.filter((item) => (
            String(item.title ?? '').toLowerCase().includes(keyword)
            || String(item.file_url ?? '').toLowerCase().includes(keyword)
            || String(item.alt_text ?? '').toLowerCase().includes(keyword)
        ));
    }, [imageKeyword, imageMediaOptions]);

    const paginatedImageMediaOptions = useMemo(() => {
        const start = (imageLibraryPage - 1) * imageLibraryPageSize;

        return filteredImageMediaOptions.slice(start, start + imageLibraryPageSize);
    }, [filteredImageMediaOptions, imageLibraryPage]);

    const applyImageMediaToForm = (media) => {
        if (!media) {
            return;
        }

        form.setFieldsValue({
            cms_media_id: media.id,
            image_url: media.file_url || '',
            image_alt: form.getFieldValue('image_alt') || media.alt_text || media.title || '',
        });
    };

    const createImageMediaRecord = async ({ file, fileUrl, title }) => {
        if (!callAdminApi) {
            throw new Error('Chưa cấu hình API upload media.');
        }

        const formData = new FormData();

        if (file) {
            formData.append('file', file);
        }

        if (fileUrl) {
            formData.append('file_url', fileUrl);
        }

        if (title) {
            formData.append('title', title);
        }

        const payload = await callAdminApi('/admin/api/cms/media', {
            method: 'POST',
            body: formData,
        });

        if (!payload?.data?.id) {
            throw new Error('Không thể tạo media ảnh đại diện.');
        }

        return payload.data;
    };

    const handleUploadImage = async (event) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        setImageUploading('upload');

        try {
            const media = await createImageMediaRecord({
                file,
                title: file.name.replace(/\.[^.]+$/, ''),
            });

            setImageMediaOptions((currentOptions) => [media, ...currentOptions.filter((item) => item.id !== media.id)]);
            applyImageMediaToForm(media);
            messageApi.success(`Đã upload và gán ảnh "${file.name}".`);
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : 'Upload ảnh đại diện không thành công.');
        } finally {
            setImageUploading(null);
            event.target.value = '';
        }
    };

    const handleCreateImageFromUrl = async () => {
        const trimmedUrl = imageUrlDraft.trim();

        if (!trimmedUrl) {
            messageApi.warning('Nhập URL ảnh trước khi lưu.');
            return;
        }

        setImageUploading('url');

        try {
            const media = await createImageMediaRecord({
                fileUrl: trimmedUrl,
                title: form.getFieldValue('name') || 'Testimonial image',
            });

            setImageMediaOptions((currentOptions) => [media, ...currentOptions.filter((item) => item.id !== media.id)]);
            applyImageMediaToForm(media);
            messageApi.success('Đã lưu URL và gán làm ảnh đại diện.');
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : 'Không thể lưu ảnh đại diện từ URL.');
        } finally {
            setImageUploading(null);
        }
    };

    const renderImagePreview = () => {
        const previewUrl = selectedImageMedia?.file_url || selectedImageUrl;

        if (!previewUrl) {
            return null;
        }

        return (
            <div className="cms-featured-media-preview">
                <img src={previewUrl} alt={form.getFieldValue('image_alt') || selectedImageMedia?.title || 'Testimonial media'} />
                <div className="cms-featured-media-preview-copy">
                    <strong>{selectedImageMedia?.title || form.getFieldValue('image_alt') || 'Ảnh đại diện nhận xét'}</strong>
                    <span>{previewUrl}</span>
                </div>
                <Button size="small" onClick={() => form.setFieldsValue({ cms_media_id: null, image_url: null })}>Bỏ chọn</Button>
            </div>
        );
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
        <>
            {messageContextHolder}
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
                <Form form={form} layout="vertical" initialValues={{ ...defaultTestimonialValues, ...(editingTestimonial ?? {}) }}>
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

                        <Card size="small" className="cms-post-form-card" title="Ảnh đại diện">
                            <Form.Item name="cms_media_id" style={{ marginBottom: 0 }}>
                                <div className="cms-featured-media-shell">
                                    <Radio.Group
                                        value={imageMode}
                                        onChange={(event) => setImageMode(event.target.value)}
                                        optionType="button"
                                        buttonStyle="solid"
                                        className="cms-featured-media-mode"
                                        options={[
                                            { label: 'Upload ảnh trực tiếp', value: 'upload' },
                                            { label: 'Chọn từ danh sách có sẵn', value: 'library' },
                                            { label: 'Nhập từ URL', value: 'url' },
                                        ]}
                                    />

                                    {imageMode === 'upload' ? (
                                        <div className="cms-featured-media-action-card">
                                            <input ref={imageInputRef} type="file" accept="image/*" style={{ display: 'none' }} onChange={handleUploadImage} />
                                            <Space direction="vertical" size={10} style={{ width: '100%' }}>
                                                <Space wrap>
                                                    <Button
                                                        type="primary"
                                                        disabled={!canManage}
                                                        loading={imageUploading === 'upload'}
                                                        onClick={() => imageInputRef.current?.click()}
                                                    >
                                                        Upload ảnh trực tiếp
                                                    </Button>
                                                    <Text type="secondary">Ảnh upload xong sẽ tự được gán làm ảnh đại diện.</Text>
                                                </Space>
                                                {renderImagePreview()}
                                            </Space>
                                        </div>
                                    ) : null}

                                    {imageMode === 'library' ? (
                                        <div className="cms-featured-media-action-card">
                                            <Space direction="vertical" size={10} style={{ width: '100%' }}>
                                                <Space wrap>
                                                    <Button type="primary" onClick={() => setImageLibraryOpen(true)}>
                                                        Mở thư viện media
                                                    </Button>
                                                    <Text type="secondary">Chọn lại từ media CMS đã có sẵn.</Text>
                                                </Space>
                                                {renderImagePreview()}
                                            </Space>
                                        </div>
                                    ) : null}

                                    {imageMode === 'url' ? (
                                        <div className="cms-featured-media-action-card">
                                            <Space direction="vertical" size={10} style={{ width: '100%' }}>
                                                <Input
                                                    value={imageUrlDraft}
                                                    onChange={(event) => setImageUrlDraft(event.target.value)}
                                                    placeholder="https://example.com/avatar.jpg"
                                                />
                                                <Space wrap>
                                                    <Button
                                                        type="primary"
                                                        disabled={!canManage}
                                                        loading={imageUploading === 'url'}
                                                        onClick={handleCreateImageFromUrl}
                                                    >
                                                        Lưu URL và gán ảnh
                                                    </Button>
                                                    <Text type="secondary">URL sẽ được lưu vào CMS media để tái sử dụng về sau.</Text>
                                                </Space>
                                                {renderImagePreview()}
                                            </Space>
                                        </div>
                                    ) : null}
                                </div>
                            </Form.Item>
                            <Form.Item name="image_url" hidden>
                                <Input />
                            </Form.Item>
                            <Form.Item name="image_alt" label="Alt text" style={{ marginTop: 12 }}>
                                <Input placeholder="Ảnh chân dung khách hàng" />
                            </Form.Item>
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

            <Modal
                title="Chọn ảnh từ thư viện media"
                open={imageLibraryOpen}
                onCancel={() => setImageLibraryOpen(false)}
                footer={null}
                width={920}
                destroyOnHidden
            >
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Input.Search
                        allowClear
                        value={imageKeyword}
                        onChange={(event) => {
                            setImageKeyword(event.target.value);
                            setImageLibraryPage(1);
                        }}
                        placeholder="Tìm theo tên media, alt text hoặc URL"
                    />

                    <div className="cms-featured-media-library-grid">
                        {paginatedImageMediaOptions.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                className={`cms-featured-media-library-item${String(item.id) === String(selectedImageMediaId) ? ' is-selected' : ''}`}
                                onClick={() => {
                                    applyImageMediaToForm(item);
                                    setImageLibraryOpen(false);
                                }}
                            >
                                <div className="cms-featured-media-library-thumb">
                                    {item.file_url ? <img src={item.file_url} alt={item.title || item.alt_text || ''} /> : null}
                                </div>
                                <div className="cms-featured-media-library-copy">
                                    <strong>{item.title || `Media #${item.id}`}</strong>
                                    <span>{item.file_url || 'Không có URL'}</span>
                                </div>
                            </button>
                        ))}
                    </div>

                    {filteredImageMediaOptions.length === 0 ? (
                        <Text type="secondary">Chưa có media phù hợp.</Text>
                    ) : null}

                    <Pagination
                        current={imageLibraryPage}
                        pageSize={imageLibraryPageSize}
                        total={filteredImageMediaOptions.length}
                        showSizeChanger={false}
                        onChange={setImageLibraryPage}
                    />
                </Space>
            </Modal>
        </>
    );
}
