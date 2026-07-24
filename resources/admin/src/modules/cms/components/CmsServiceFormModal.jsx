import { useEffect, useMemo, useRef } from 'react';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Radio from 'antd/es/radio';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Switch from 'antd/es/switch';
import dayjs from 'dayjs';
import MultiMediaPicker from '../../../shared/components/MultiMediaPicker';
import RichContentEditor from '../../../shared/components/RichContentEditor';
import { toSlug } from '../../../shared/utils/slug';

function normalizeImageUrls(value) {
    if (Array.isArray(value)) {
        return value.map((item) => String(item ?? '').trim()).filter(Boolean);
    }

    return String(value ?? '')
        .split(/\r?\n/)
        .map((item) => item.trim())
        .filter(Boolean);
}

function FormValueBridge() {
    return null;
}

const SERVICE_ICON_OPTIONS = [
    { label: 'Nhà ở', value: 'fa-solid fa-house' },
    { label: 'Tòa nhà', value: 'fa-regular fa-building' },
    { label: 'Bản vẽ', value: 'fa-solid fa-compass-drafting' },
    { label: 'Thước thiết kế', value: 'fa-solid fa-ruler-combined' },
    { label: 'Thi công', value: 'fa-solid fa-trowel-bricks' },
    { label: 'An toàn lao động', value: 'fa-solid fa-helmet-safety' },
    { label: 'Cài đặt kỹ thuật', value: 'fa-solid fa-gear' },
    { label: 'Danh sách kiểm tra', value: 'fa-solid fa-list-check' },
    { label: 'Vật liệu nhiều lớp', value: 'fa-solid fa-layer-group' },
    { label: 'Chìa khóa bàn giao', value: 'fa-solid fa-key' },
    { label: 'Ý tưởng', value: 'fa-regular fa-lightbulb' },
    { label: 'Đối tượng thiết kế', value: 'fa-regular fa-object-group' },
    { label: 'Bắt tay hợp tác', value: 'fa-solid fa-handshake' },
    { label: 'Ngân sách', value: 'fa-solid fa-hand-holding-dollar' },
    { label: 'Đội ngũ', value: 'fa-solid fa-users-gear' },
    { label: 'Tư vấn', value: 'fa-regular fa-comments' },
    { label: 'Đồng hồ tiến độ', value: 'fa-solid fa-clock' },
    { label: 'Bảo vệ', value: 'fa-solid fa-shield-halved' },
    { label: 'Giải thưởng', value: 'fa-solid fa-award' },
    { label: 'Nông nghiệp', value: 'fa-solid fa-seedling' },
];

export default function CmsServiceFormModal({ open, canManage, editingService, mediaOptions = [], categoryOptions = [], callAdminApi, onCancel, onSubmit }) {
    const [form] = Form.useForm();
    const lastTitleRef = useRef('');
    const titleValue = Form.useWatch('title', form) ?? '';
    const contentValue = Form.useWatch('content', form) ?? '';
    const galleryImages = Form.useWatch('gallery_images', form) ?? [];
    const featuredImageUrl = Form.useWatch('featured_image_url', form) ?? '';
    const serviceImages = useMemo(() => Array.from(new Set([
        featuredImageUrl,
        ...normalizeImageUrls(galleryImages),
    ].filter(Boolean))), [featuredImageUrl, galleryImages]);
    useEffect(() => {
        const featuredImage = editingService?.images?.find((image) => image?.is_featured)
            ?? editingService?.images?.[0]
            ?? null;

        form.setFieldsValue({
            ...editingService,
            content: editingService?.content ?? '',
            images: editingService?.images?.length ? editingService.images : [],
            featured_image_url: editingService?.featured_image_url || featuredImage?.image_url || '',
            featured_image_alt: editingService?.featured_image_alt || featuredImage?.alt_text || '',
            featured_media_id: featuredImage?.cms_media_id || null,
            gallery_images: (editingService?.images ?? []).map((image) => image?.image_url).filter(Boolean),
        });
        lastTitleRef.current = String(editingService?.title ?? '');
    }, [editingService, form]);

    useEffect(() => {
        if (titleValue === lastTitleRef.current) {
            return;
        }

        form.setFieldValue('slug', toSlug(titleValue));
        lastTitleRef.current = titleValue;
    }, [form, titleValue]);

    const syncServiceImages = (nextValue, nextCover = null) => {
        const normalizedImages = Array.from(new Set(normalizeImageUrls(nextValue)));
        const selectedCover = nextCover && normalizedImages.includes(nextCover)
            ? nextCover
            : normalizedImages[0] ?? '';
        const orderedImages = selectedCover
            ? [selectedCover, ...normalizedImages.filter((item) => item !== selectedCover)]
            : normalizedImages;
        const existingCover = (editingService?.images ?? []).find((image) => image?.image_url === selectedCover);

        form.setFieldsValue({
            featured_image_url: selectedCover,
            featured_image_alt: existingCover?.alt_text || form.getFieldValue('featured_image_alt') || titleValue || '',
            featured_media_id: existingCover?.cms_media_id || null,
            gallery_images: orderedImages,
        });
    };

    /* Shared RichContentEditor owns the CKEditor configuration.
        licenseKey: 'GPL',
        plugins: [
            Essentials,
            Paragraph,
            Heading,
            Bold,
            Italic,
            Underline,
            Link,
            List,
            BlockQuote,
            Image,
            ImageCaption,
            ImageStyle,
            ImageToolbar,
            ImageResize,
            Table,
            TableToolbar,
            MediaEmbed,
            GeneralHtmlSupport,
        ],
        toolbar: {
            items: [
                'undo',
                'redo',
                '|',
                'heading',
                '|',
                'bold',
                'italic',
                'underline',
                '|',
                'link',
                'bulletedList',
                'numberedList',
                'blockQuote',
                '|',
                'insertTable',
                'mediaEmbed',
            ],
            shouldNotGroupWhenFull: true,
        },
        image: {
            toolbar: ['imageStyle:inline', 'imageStyle:block', 'imageStyle:side', '|', 'toggleImageCaption'],
            resizeOptions: [
                { name: 'resizeImage:original', value: null, label: 'Gốc' },
                { name: 'resizeImage:50', value: '50', label: '50%' },
                { name: 'resizeImage:75', value: '75', label: '75%' },
            ],
        },
        table: {
            contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells'],
        },
        mediaEmbed: {
            previewsInData: true,
        },
        htmlSupport: {
            allow: [
                { name: 'figure', classes: true, attributes: true, styles: true },
                { name: 'img', classes: true, attributes: true, styles: true },
                { name: 'div', classes: true, attributes: true, styles: true },
                { name: 'iframe', classes: true, attributes: true, styles: true },
                { name: 'table', classes: true, attributes: true, styles: true },
            ],
        },
    }), []); */

    const handleSlugChange = (event) => {
        form.setFieldValue('slug', toSlug(event.target.value, { trimEdges: false }));
    };

    /* Editor mode synchronization moved into RichContentEditor.
        const editor = editorInstanceRef.current;

        if (contentMode === 'editor' && editor) {
            form.setFieldValue('content', editor.getData());
        }
    }; */

    const handleContentModeChange = (event) => {
        const nextMode = event.target.value;

        if (nextMode === contentMode) {
            return;
        }

        syncCurrentEditorBodyToForm();
        editorInstanceRef.current = null;
        setContentMode(nextMode);
        setEditorContentVersion((current) => current + 1);
    };

    const handleSubmit = async () => {
        const values = await form.validateFields();
        const {
            featured_image_url: rawFeaturedImageUrl,
            featured_image_alt: featuredImageAlt,
            featured_media_id: featuredMediaId,
            gallery_images: rawGalleryImages,
            ...payloadValues
        } = values;
        const featuredImageUrl = String(rawFeaturedImageUrl ?? '').trim();
        const existingImages = editingService?.images ?? [];
        const submittedImageUrls = Array.from(new Set([
            featuredImageUrl,
            ...normalizeImageUrls(rawGalleryImages),
        ].filter(Boolean)));
        const normalizedImages = submittedImageUrls.map((imageUrl, index) => {
            const existingImage = existingImages.find((image) => image?.image_url === imageUrl);
            const selectedMedia = mediaOptions.find((media) => media?.file_url === imageUrl);

            return {
                cms_media_id: existingImage?.cms_media_id || selectedMedia?.id || (index === 0 ? featuredMediaId : null) || null,
                image_url: imageUrl,
                alt_text: index === 0
                    ? (featuredImageAlt || existingImage?.alt_text || values.title || null)
                    : (existingImage?.alt_text || values.title || null),
                caption: existingImage?.caption || null,
                is_featured: index === 0,
                sort_order: index,
            };
        });

        await onSubmit?.({
            ...payloadValues,
            cms_service_category_id: values.cms_service_category_id || null,
            summary: values.summary || null,
            content: values.content || null,
            icon: values.icon || null,
            button_label: values.button_label || null,
            link_url: null,
            meta_title: values.meta_title || null,
            meta_description: values.meta_description || null,
            meta_keywords: values.meta_keywords || null,
            sort_order: Number(values.sort_order ?? 0),
            is_featured: Boolean(values.is_featured),
            is_highlight: Boolean(values.is_highlight),
            publish_at: values.status === 'published' ? (values.publish_at || dayjs().format('YYYY-MM-DDTHH:mm:ss')) : null,
            images: normalizedImages,
        });

        form.resetFields();
    };

    const handleCancel = () => {
        form.resetFields();
        onCancel?.();
    };

    return (
        <Drawer
            title={editingService?.id ? 'Cập nhật dịch vụ CMS' : 'Tạo dịch vụ CMS'}
            open={open}
            onClose={handleCancel}
            width={960}
            destroyOnHidden
            className="cms-page-drawer"
            extra={(
                <Space>
                    <Button onClick={handleCancel}>Hủy</Button>
                    <Button type="primary" disabled={!canManage} onClick={handleSubmit}>Lưu dịch vụ</Button>
                </Space>
            )}
        >
            <Form form={form} layout="vertical" initialValues={editingService}>
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Card size="small" title="Thông tin dịch vụ">
                        <Row gutter={16}>
                            <Col xs={24} md={14}>
                                <Form.Item name="title" label="Tiêu đề" rules={[{ required: true, message: 'Nhập tiêu đề dịch vụ' }]}>
                                    <Input placeholder="Thiết kế và thi công nhà phố" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={10}>
                                <Form.Item name="slug" label="Slug" rules={[{ required: true, message: 'Nhập slug dịch vụ' }]}>
                                    <Input placeholder="thiet-ke-thi-cong-nha-pho" onChange={handleSlugChange} />
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
                                <Form.Item name="cms_service_category_id" label="Danh mục dịch vụ">
                                    <Select
                                        allowClear
                                        showSearch
                                        optionFilterProp="label"
                                        options={categoryOptions}
                                        placeholder="Chọn danh mục"
                                    />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item name="icon" label="Icon ngắn">
                                    <Select
                                        allowClear
                                        showSearch
                                        optionFilterProp="searchLabel"
                                        placeholder="Chọn icon dịch vụ"
                                        options={SERVICE_ICON_OPTIONS.map((option) => ({
                                            ...option,
                                            searchLabel: `${option.label} ${option.value}`,
                                            label: (
                                                <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                                                    <i className={option.value} style={{ width: 18, textAlign: 'center' }} />
                                                    <span>{option.label}</span>
                                                </span>
                                            ),
                                        }))}
                                    />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Row gutter={16}>
                            <Col xs={24} md={8}>
                                <Form.Item
                                    name="is_featured"
                                    label="Hiển thị trong khối Dịch vụ nổi bật"
                                    extra="Dùng cho các block chỉ lấy dịch vụ nổi bật."
                                    valuePropName="checked"
                                >
                                    <Switch />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item
                                    name="is_highlight"
                                    label="Ưu tiên trong khối nội dung động"
                                    extra="Dùng khi block tổng hợp bật lọc nội dung ưu tiên."
                                    valuePropName="checked"
                                >
                                    <Switch />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="button_label" label="Nhãn nút">
                                    <Input placeholder="Tìm hiểu ngay" />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Form.Item name="summary" label="Mô tả ngắn">
                            <Input.TextArea rows={3} placeholder="Mô tả hiển thị ngoài card dịch vụ." />
                        </Form.Item>
                    </Card>

                    <Card size="small" className="cms-post-form-card" title="Thư viện ảnh dịch vụ">
                        <Form.Item name="featured_image_url" hidden>
                            <FormValueBridge />
                        </Form.Item>
                        <Form.Item name="gallery_images" hidden>
                            <FormValueBridge />
                        </Form.Item>
                        <Form.Item label="Danh sách hình ảnh" style={{ marginBottom: 0 }}>
                            <MultiMediaPicker
                                open={open}
                                value={serviceImages}
                                onChange={(nextValue) => syncServiceImages(nextValue)}
                                coverValue={featuredImageUrl}
                                onSetCover={(nextCover) => syncServiceImages(serviceImages, nextCover)}
                                canManage={canManage}
                                callAdminApi={callAdminApi}
                                mediaOptions={mediaOptions}
                                recordTitle={titleValue || 'Service images'}
                                previewTitle="Ảnh dịch vụ"
                                uploadButtonLabel="Upload nhiều ảnh dịch vụ"
                                uploadHint="Có thể upload nhiều ảnh. Ảnh đầu tiên sẽ là ảnh đại diện và slide đầu tiên."
                                libraryButtonLabel="Chọn nhiều ảnh từ thư viện"
                                libraryHint="Có thể chọn nhiều media CMS để thêm vào slide dịch vụ."
                                urlPlaceholder={['https://cdn.example.com/service-1.jpg', 'https://cdn.example.com/service-2.jpg'].join('\n')}
                                urlButtonLabel="Lưu URL và thêm vào slide"
                            />
                        </Form.Item>
                        <Form.Item name="featured_media_id" hidden>
                            <Input />
                        </Form.Item>
                        <Form.Item name="featured_image_alt" label="Alt text" style={{ marginTop: 12, marginBottom: 0 }}>
                            <Input placeholder="Mô tả ảnh dịch vụ" />
                        </Form.Item>
                    </Card>

                    <Card size="small" title="SEO">
                        <Row gutter={16}>
                            <Col xs={24} md={12}>
                                <Form.Item name="meta_title" label="SEO Title">
                                    <Input.TextArea rows={3} placeholder="SEO title" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item name="meta_description" label="SEO Description" style={{ marginBottom: 0 }}>
                                    <Input.TextArea rows={3} placeholder="Meta description dịch vụ" />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Form.Item
                            name="meta_keywords"
                            label="SEO Keywords"
                            extra="Nhập các từ khóa chính, phân tách bằng dấu phẩy."
                            style={{ marginTop: 16, marginBottom: 0 }}
                        >
                            <Input.TextArea rows={2} placeholder="thiet ke noi that, thi cong nha pho, bao gia xay dung" />
                        </Form.Item>
                    </Card>

                    <Card size="small" className="cms-post-form-card cms-post-form-card-editor" title="Nội dung chi tiết">
                        <RichContentEditor
                            value={contentValue}
                            onChange={(nextContent) => form.setFieldValue('content', nextContent)}
                            disabled={!canManage}
                            callAdminApi={callAdminApi}
                            recordKey={editingService?.id ?? 'new'}
                            open={open}
                            htmlPlaceholder="<section>Nhập mã HTML chi tiết dịch vụ...</section>"
                        />
                        {false && (
                        <>
                        <div className="cms-editor-upload-panel">
                            <Space wrap className="cms-editor-toolbar-row" size={12}>
                                <Radio.Group
                                    value={contentMode}
                                    onChange={handleContentModeChange}
                                    optionType="button"
                                    buttonStyle="solid"
                                    disabled={!canManage}
                                    options={[
                                        { label: 'Trình soạn thảo', value: 'editor' },
                                        { label: 'Nhập mã HTML', value: 'html' },
                                    ]}
                                />
                            </Space>
                        </div>
                        <Form.Item label="Nội dung" style={{ marginBottom: 0 }}>
                            {contentMode === 'editor' ? (
                                <div className="cms-editor-shell">
                                    <CKEditor
                                        key={editorInstanceKey}
                                        editor={ClassicEditor}
                                        config={editorConfig}
                                        data={editorInitialData}
                                        disabled={!canManage}
                                        onReady={(editor) => {
                                            editorInstanceRef.current = editor;
                                        }}
                                        onChange={(_, editor) => {
                                            form.setFieldValue('content', editor.getData());
                                        }}
                                    />
                                </div>
                            ) : (
                                <Input.TextArea
                                    rows={18}
                                    className="cms-html-code-input"
                                    value={contentValue}
                                    disabled={!canManage}
                                    placeholder="<section>Nhập mã HTML chi tiết dịch vụ...</section>"
                                    onChange={(event) => form.setFieldValue('content', event.target.value)}
                                />
                            )}
                        </Form.Item>
                        <Form.Item name="content" hidden>
                            <Input.TextArea />
                        </Form.Item>
                        </>
                        )}
                        <Form.Item name="content" hidden>
                            <Input.TextArea />
                        </Form.Item>
                    </Card>
                </Space>
            </Form>
        </Drawer>
    );
}
