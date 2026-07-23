import { adminApi } from '../../../shared/config/routes';
import { useEffect, useMemo, useRef, useState } from 'react';
import { CKEditor } from '@ckeditor/ckeditor5-react';
import AppstoreOutlined from '@ant-design/icons/AppstoreOutlined';
import DollarOutlined from '@ant-design/icons/DollarOutlined';
import DownOutlined from '@ant-design/icons/DownOutlined';
import FileTextOutlined from '@ant-design/icons/FileTextOutlined';
import PictureOutlined from '@ant-design/icons/PictureOutlined';
import RightOutlined from '@ant-design/icons/RightOutlined';
import SearchOutlined from '@ant-design/icons/SearchOutlined';
import StarOutlined from '@ant-design/icons/StarOutlined';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Checkbox from 'antd/es/checkbox';
import Col from 'antd/es/col';
import DatePicker from 'antd/es/date-picker';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import message from 'antd/es/message';
import Modal from 'antd/es/modal';
import Radio from 'antd/es/radio';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Switch from 'antd/es/switch';
import dayjs from 'dayjs';
import MultiMediaPicker from '../../../shared/components/MultiMediaPicker';
import {
    BlockQuote,
    Bold,
    ClassicEditor,
    Essentials,
    GeneralHtmlSupport,
    Heading,
    Image,
    ImageCaption,
    ImageResize,
    ImageStyle,
    ImageToolbar,
    Italic,
    Link,
    List,
    MediaEmbed,
    Paragraph,
    Table,
    TableToolbar,
    Underline,
} from 'ckeditor5';
import 'ckeditor5/ckeditor5.css';

const { TextArea } = Input;

function normalizeGalleryImages(value) {
    if (Array.isArray(value)) {
        return value.map((item) => String(item ?? '').trim()).filter(Boolean);
    }

    return String(value ?? '')
        .split(/\r?\n/)
        .map((item) => item.trim())
        .filter(Boolean);
}

function normalizeDealEndAtValue(value) {
    if (!value) {
        return null;
    }

    const dateValue = dayjs(value);

    return dateValue.isValid() ? dateValue : null;
}

function FormValueBridge() {
    return null;
}

export const emptyCatalogProductForm = {
    id: null,
    catalog_category_id: null,
    name: '',
    slug: '',
    sku: '',
    price: 0,
    original_price: null,
    stock: 1000,
    short_description: '',
    detail_content: '',
    meta_title: '',
    meta_description: '',
    meta_keywords: '',
    highlights: '',
    usage_terms: '',
    usage_location: '',
    image_url: '',
    gallery_images: [],
    sold_count: 0,
    deal_end_at: '',
    is_featured: false,
    is_highlight: false,
    sort_order: 0,
    is_active: true,
};

export default function CatalogProductFormModal({ open, canManage, editingProduct, categoryOptions = [], callAdminApi, onCancel, onSubmit }) {
    const [form] = Form.useForm();
    const [messageApi, messageContextHolder] = message.useMessage();
    const [uploadingAsset, setUploadingAsset] = useState(null);
    const [youtubeEmbedOpen, setYoutubeEmbedOpen] = useState(false);
    const [youtubeUrl, setYoutubeUrl] = useState('');
    const [contentMode, setContentMode] = useState('editor');
    const [collapsedSections, setCollapsedSections] = useState({});
    const [editorContentVersion, setEditorContentVersion] = useState(0);
    const editorInstanceRef = useRef(null);
    const editorSelectionRef = useRef(null);
    const imageInputRef = useRef(null);
    const videoInputRef = useRef(null);
    const editorInitialData = useMemo(
        () => form.getFieldValue('detail_content') ?? editingProduct?.detail_content ?? '',
        [editingProduct?.id, editingProduct?.slug, editingProduct?.detail_content, editorContentVersion, form]
    );
    const editorInstanceKey = useMemo(
        () => `${editingProduct?.id ?? 'new'}:${editingProduct?.slug ?? 'blank'}:${open ? 'open' : 'closed'}:${contentMode}:${editorContentVersion}`,
        [editingProduct?.id, editingProduct?.slug, open, contentMode, editorContentVersion]
    );
    const galleryImages = Form.useWatch('gallery_images', form) ?? [];
    const coverImageUrl = Form.useWatch('image_url', form) ?? '';
    const productName = Form.useWatch('name', form) ?? '';
    const detailContentValue = Form.useWatch('detail_content', form) ?? '';
    const productImages = useMemo(() => Array.from(new Set([
        coverImageUrl,
        ...normalizeGalleryImages(galleryImages),
    ].filter(Boolean))), [coverImageUrl, galleryImages]);

    const syncProductImages = (nextValue, nextCover = null) => {
        const normalizedImages = Array.from(new Set(normalizeGalleryImages(nextValue)));
        const selectedCover = nextCover && normalizedImages.includes(nextCover)
            ? nextCover
            : normalizedImages[0] ?? '';
        const orderedImages = selectedCover
            ? [selectedCover, ...normalizedImages.filter((item) => item !== selectedCover)]
            : normalizedImages;

        form.setFieldsValue({
            image_url: selectedCover,
            gallery_images: orderedImages,
        });
    };

    const setProductCoverImage = (nextCover) => {
        syncProductImages(productImages, nextCover);
    };

    const isSectionCollapsed = (sectionKey) => Boolean(collapsedSections[sectionKey]);

    const toggleSection = (sectionKey) => {
        setCollapsedSections((current) => ({
            ...current,
            [sectionKey]: !current[sectionKey],
        }));
    };

    const renderSectionTitle = (sectionKey, title, IconComponent, extra = null) => {
        const collapsed = isSectionCollapsed(sectionKey);

        return (
            <button
                type="button"
                onClick={() => toggleSection(sectionKey)}
                style={{
                    width: '100%',
                    border: 0,
                    padding: 0,
                    background: 'transparent',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: 12,
                    cursor: 'pointer',
                    textAlign: 'left',
                }}
            >
                <Space size={8}>
                    <IconComponent style={{ color: '#1677ff' }} />
                    <span>{title}</span>
                    {extra}
                </Space>
                {collapsed ? <RightOutlined style={{ color: '#8c8c8c' }} /> : <DownOutlined style={{ color: '#8c8c8c' }} />}
            </button>
        );
    };

    useEffect(() => {
        const highlighted = Boolean(editingProduct?.is_highlight ?? editingProduct?.is_featured ?? false);

        form.setFieldsValue({
            ...editingProduct,
            is_featured: highlighted,
            is_highlight: highlighted,
            deal_end_at: normalizeDealEndAtValue(editingProduct?.deal_end_at),
        });
        form.setFieldValue('detail_content', editingProduct?.detail_content ?? '');
        form.setFieldValue('gallery_images', normalizeGalleryImages(editingProduct?.gallery_images ?? []));
        setContentMode('editor');
        setEditorContentVersion((current) => current + 1);
        editorInstanceRef.current = null;
        editorSelectionRef.current = null;
    }, [editingProduct, form]);

    const editorConfig = useMemo(() => ({
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
                { name: 'video', classes: true, attributes: true, styles: true },
                { name: 'source', classes: true, attributes: true, styles: true },
                { name: 'img', classes: true, attributes: true, styles: true },
                { name: 'div', classes: true, attributes: true, styles: true },
                { name: 'iframe', classes: true, attributes: true, styles: true },
            ],
        },
    }), []);

    const syncEditorBodyToForm = (editor) => {
        form.setFieldValue('detail_content', editor.getData());
    };

    const syncCurrentEditorBodyToForm = () => {
        const editor = editorInstanceRef.current;

        if (contentMode === 'editor' && editor) {
            syncEditorBodyToForm(editor);
        }
    };

    const handleContentModeChange = (event) => {
        const nextMode = event.target.value;

        if (nextMode === contentMode) {
            return;
        }

        syncCurrentEditorBodyToForm();
        editorInstanceRef.current = null;
        editorSelectionRef.current = null;
        setContentMode(nextMode);
        setEditorContentVersion((current) => current + 1);
    };

    const captureEditorSelection = (editor) => {
        const range = editor?.model?.document?.selection?.getFirstRange?.();

        editorSelectionRef.current = range ? range.clone() : null;
    };

    const insertHtmlIntoEditor = (html) => {
        const editor = editorInstanceRef.current;

        if (!editor) {
            const currentData = form.getFieldValue('detail_content') || '';

            form.setFieldValue('detail_content', `${currentData}${html}`);
            return;
        }

        editor.model.change((writer) => {
            const viewFragment = editor.data.processor.toView(html);
            const modelFragment = editor.data.toModel(viewFragment);

            if (editorSelectionRef.current) {
                writer.setSelection(editorSelectionRef.current);
            } else {
                writer.setSelection(editor.model.document.getRoot(), 'end');
            }

            editor.model.insertContent(modelFragment, editor.model.document.selection);
        });

        captureEditorSelection(editor);
        syncEditorBodyToForm(editor);
        editor.editing.view.focus();
    };

    const uploadCmsMedia = async (file, typeLabel) => {
        if (!callAdminApi) {
            throw new Error('Thiếu cấu hình upload media cho editor sản phẩm.');
        }

        const formData = new FormData();

        formData.append('file', file);
        formData.append('title', file.name.replace(/\.[^.]+$/, '') || typeLabel);

        const payload = await callAdminApi(adminApi('cms/media'), {
            method: 'POST',
            body: formData,
        });

        const url = payload?.data?.file_url;

        if (!url) {
            throw new Error(`Upload ${typeLabel} vào CMS không thành công.`);
        }

        return url;
    };

    const openAssetPicker = (inputRef) => {
        const editor = editorInstanceRef.current;

        if (editor) {
            captureEditorSelection(editor);
        }

        inputRef.current?.click();
    };

    const handleInsertImage = async (event) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        setUploadingAsset('image');

        try {
            const url = await uploadCmsMedia(file, 'image');
            insertHtmlIntoEditor(`<figure class="image"><img src="${url}" alt="${file.name}" /></figure>`);
            messageApi.success(`Đã chèn ảnh "${file.name}" vào nội dung sản phẩm.`);
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : 'Upload ảnh vào nội dung không thành công.');
        } finally {
            setUploadingAsset(null);
            event.target.value = '';
        }
    };

    const handleInsertVideo = async (event) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        setUploadingAsset('video');

        try {
            const url = await uploadCmsMedia(file, 'video');
            insertHtmlIntoEditor(`<figure class="cms-inline-video"><video controls style="max-width:100%;height:auto;" src="${url}"></video></figure>`);
            messageApi.success(`Đã chèn video "${file.name}" vào nội dung sản phẩm.`);
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : 'Upload video vào nội dung không thành công.');
        } finally {
            setUploadingAsset(null);
            event.target.value = '';
        }
    };

    const handleInsertYoutubeEmbed = () => {
        const trimmedValue = String(youtubeUrl ?? '').trim();

        if (!trimmedValue) {
            messageApi.warning('Nhập đúng link YouTube trước khi nhúng.');
            return;
        }

        let embedUrl = null;

        try {
            const parsedUrl = new URL(trimmedValue);
            const hostname = parsedUrl.hostname.replace(/^www\./, '').toLowerCase();
            let videoId = '';

            if (hostname === 'youtu.be') {
                videoId = parsedUrl.pathname.split('/').filter(Boolean)[0] ?? '';
            } else if (hostname === 'youtube.com' || hostname === 'm.youtube.com' || hostname === 'music.youtube.com') {
                if (parsedUrl.pathname === '/watch') {
                    videoId = parsedUrl.searchParams.get('v') ?? '';
                } else if (parsedUrl.pathname.startsWith('/shorts/')) {
                    videoId = parsedUrl.pathname.split('/').filter(Boolean)[1] ?? '';
                } else if (parsedUrl.pathname.startsWith('/embed/')) {
                    videoId = parsedUrl.pathname.split('/').filter(Boolean)[1] ?? '';
                }
            }

            if (videoId) {
                const safeVideoId = videoId.replace(/[^a-zA-Z0-9_-]/g, '');
                embedUrl = safeVideoId ? `https://www.youtube.com/embed/${safeVideoId}` : null;
            }
        } catch {
            embedUrl = null;
        }

        if (!embedUrl) {
            messageApi.warning('Nhập đúng link YouTube trước khi nhúng.');
            return;
        }

        insertHtmlIntoEditor(`<div class="cms-inline-video cms-inline-youtube" style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:16px;"><iframe src="${embedUrl}" title="YouTube video player" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;"></iframe></div>`);
        setYoutubeEmbedOpen(false);
        setYoutubeUrl('');
        messageApi.success('Đã nhúng video YouTube vào nội dung sản phẩm.');
    };

    const handleSubmit = async () => {
        const values = await form.validateFields();
        const highlighted = Boolean(values.is_highlight ?? values.is_featured ?? false);
        const submittedImages = Array.from(new Set([
            values.image_url,
            ...normalizeGalleryImages(values.gallery_images),
        ].filter(Boolean)));
        const submittedCover = values.image_url || submittedImages[0] || null;

        await onSubmit?.({
            ...values,
            catalog_category_id: values.catalog_category_id || null,
            slug: null,
            sku: values.sku || null,
            original_price: values.original_price ?? null,
            short_description: values.short_description || null,
            detail_content: values.detail_content || null,
            meta_title: values.name || null,
            meta_description: values.meta_description || values.short_description || null,
            meta_keywords: values.meta_keywords || null,
            highlights: values.highlights || null,
            usage_terms: values.usage_terms || null,
            usage_location: values.usage_location || null,
            image_url: submittedCover,
            gallery_images: submittedImages,
            sold_count: values.sold_count ?? 0,
            deal_end_at: values.deal_end_at ? values.deal_end_at.format('YYYY-MM-DDTHH:mm:ss') : null,
            is_featured: highlighted,
            is_highlight: highlighted,
            is_active: Boolean(values.is_active),
        });

        form.resetFields();
    };

    const handleCancel = () => {
        form.resetFields();
        onCancel?.();
    };

    return (
        <Drawer
            title={editingProduct?.id ? 'Cập nhật sản phẩm' : 'Tạo sản phẩm'}
            open={open}
            onClose={handleCancel}
            width={960}
            destroyOnHidden
            className="cms-page-drawer"
            maskClosable={false}
            extra={(
                <Space>
                    <Button onClick={handleCancel}>Hủy</Button>
                    <Button type="primary" disabled={!canManage} onClick={handleSubmit}>Lưu sản phẩm</Button>
                </Space>
            )}
        >
            {messageContextHolder}
            <Form form={form} layout="vertical" initialValues={editingProduct}>
                <div className="cms-post-form-shell">
                    <Card size="small" className="cms-post-form-card" title={renderSectionTitle('basic', 'Thông tin cơ bản', AppstoreOutlined)}>
                        {!isSectionCollapsed('basic') ? (

                        <Row gutter={16}>
                            <Col xs={24} md={14}>
                                <Form.Item name="name" label="Tên sản phẩm" rules={[{ required: true, message: 'Nhập tên sản phẩm' }]}>
                                    <Input placeholder="Áo sơ mi xanh" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={10}>
                                <Form.Item name="sku" label="Mã sản phẩm" extra="Để trống để hệ thống tự sinh theo format PRO&lt;ID&gt;.">
                                    <Input placeholder="PRO101" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item
                                    name="catalog_category_id"
                                    label="Danh mục"
                                    rules={[{ required: true, message: 'Vui lòng chọn danh mục sản phẩm' }]}
                                >
                                    <Select showSearch optionFilterProp="label" options={categoryOptions} placeholder="Chọn danh mục" />
                                </Form.Item>
                            </Col>
                            <Col xs={24}>
                                <Form.Item name="short_description" label="Mô tả ngắn">
                                    <Input.TextArea rows={3} placeholder="Mô tả cho card và detail page" />
                                </Form.Item>
                            </Col>
                        </Row>
                                            ) : null}
</Card>

                    <Card size="small" className="cms-post-form-card" title={renderSectionTitle('pricing', 'Giá bán và tồn kho', DollarOutlined)}>
                        {!isSectionCollapsed('pricing') ? (

                        <Row gutter={16}>
                            <Col xs={24} md={8}>
                                <Form.Item name="price" label="Giá" rules={[{ required: true, message: 'Nhập giá' }]}>
                                    <InputNumber min={0} style={{ width: '100%' }} />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="original_price" label="Giá gốc">
                                    <InputNumber min={0} style={{ width: '100%' }} />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="stock" label="Tồn kho" rules={[{ required: true, message: 'Nhập tồn kho' }]}>
                                    <InputNumber min={0} precision={0} style={{ width: '100%' }} />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="sold_count" label="Đã mua">
                                    <InputNumber min={0} precision={0} style={{ width: '100%' }} />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="deal_end_at" label="Hết hạn deal">
                                    <DatePicker
                                        showTime
                                        format="DD/MM/YYYY HH:mm"
                                        placeholder="Chọn thời gian hết hạn"
                                        style={{ width: '100%' }}
                                    />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="is_highlight" valuePropName="checked" label=" " colon={false}>
                                    <Checkbox>Đánh dấu nổi bật</Checkbox>
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="is_active" valuePropName="checked" label="Active sản phẩm" colon={false}>
                                    <Switch checkedChildren="active" unCheckedChildren="unactive" />
                                </Form.Item>
                            </Col>
                        </Row>
                                            ) : null}
</Card>

                    <Card size="small" className="cms-post-form-card" title={renderSectionTitle('images', 'Hình ảnh sản phẩm', PictureOutlined)}>
                        {!isSectionCollapsed('images') ? (
                            <>
                        <Form.Item name="image_url" hidden>
                            <FormValueBridge />
                        </Form.Item>
                        <Form.Item name="gallery_images" hidden>
                            <FormValueBridge />
                        </Form.Item>
                        <Form.Item label="Danh sách hình ảnh" style={{ marginBottom: 0 }}>
                            <MultiMediaPicker
                                open={open}
                                value={productImages}
                                onChange={(nextValue) => syncProductImages(nextValue)}
                                coverValue={coverImageUrl}
                                onSetCover={setProductCoverImage}
                                canManage={canManage}
                                callAdminApi={callAdminApi}
                                recordTitle={productName || 'Product images'}
                                previewTitle="Ảnh sản phẩm"
                                uploadButtonLabel="Upload ảnh sản phẩm"
                                uploadHint="Có thể upload nhiều ảnh. Ảnh đầu tiên sẽ tự làm ảnh đại diện."
                                libraryModalTitle="Chọn ảnh sản phẩm từ thư viện"
                                urlPlaceholder={['https://cdn.example.com/product-1.jpg', 'https://cdn.example.com/product-2.jpg'].join('\n')}
                                uploadSuccessMessage="Đã thêm ảnh sản phẩm."
                                urlSuccessMessage="Đã lưu URL vào thư viện media và thêm ảnh sản phẩm."
                                uploadErrorMessage="Upload ảnh sản phẩm không thành công."
                                urlErrorMessage="Không thể lưu ảnh sản phẩm từ URL."
                                emptyValueMessage="Nhập ít nhất một URL ảnh trước khi lưu."
                            />
                        </Form.Item>
                            </>
                                            ) : null}
</Card>

                    <Card size="small" className="cms-post-form-card" title={renderSectionTitle('seo', 'SEO cơ bản', SearchOutlined)}>
                        {!isSectionCollapsed('seo') ? (

                        <Row gutter={16}>
                            <Col xs={24} md={12}>
                                <Form.Item
                                    name="meta_keywords"
                                    label="SEO Keyword"
                                    extra="Nhap cac tu khoa chinh, phan tach bang dau phay."
                                >
                                    <TextArea rows={3} placeholder="phu gia dau nhon, chong tham, vat tu xay dung" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item name="meta_description" label="SEO Description" style={{ marginBottom: 0 }}>
                                    <TextArea rows={3} placeholder="Meta description sản phẩm" />
                                </Form.Item>
                            </Col>
                        </Row>
                                            ) : null}
</Card>

                    <Card size="small" className="cms-post-form-card" title={renderSectionTitle('usage', 'Điểm nổi bật và điều kiện sử dụng', StarOutlined)}>
                        {!isSectionCollapsed('usage') ? (

                        <Row gutter={16}>
                            <Col xs={24} md={12}>
                                <Form.Item name="highlights" label="Điểm nổi bật" extra="Mỗi dòng là một ý nổi bật hiển thị dạng bullet.">
                                    <TextArea rows={6} placeholder={['Buffet hải sản 5 sao', 'Không gian sang trọng', 'Dùng vào tối thứ 7'].join('\n')} />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item name="usage_terms" label="Điều kiện sử dụng" extra="Mỗi dòng là một điều kiện hoặc ghi chú sử dụng.">
                                    <TextArea rows={6} placeholder={['Thời hạn sử dụng đến 30/06/2026', 'Áp dụng ăn tại chỗ', 'Đặt chỗ trước khi đến'].join('\n')} />
                                </Form.Item>
                            </Col>
                            <Col xs={24}>
                                <Form.Item name="usage_location" label="Địa điểm sử dụng" extra="Ví dụ: tên địa điểm, địa chỉ, hotline." style={{ marginBottom: 0 }}>
                                    <TextArea rows={4} placeholder="La Brasserie - Hotel Nikko HaiPhong..." />
                                </Form.Item>
                            </Col>
                        </Row>
                                            ) : null}
</Card>

                    <Card
                        size="small"
                        className="cms-post-form-card cms-post-form-card-editor"
                        title={renderSectionTitle('detail', 'Nội dung chi tiết sản phẩm', FileTextOutlined)}
                    >
                        {!isSectionCollapsed('detail') ? (
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
                                {contentMode === 'editor' ? (
                                    <>
                                        <input ref={imageInputRef} type="file" accept="image/*" style={{ display: 'none' }} onChange={handleInsertImage} />
                                        <input ref={videoInputRef} type="file" accept="video/*" style={{ display: 'none' }} onChange={handleInsertVideo} />
                                        <Button type="default" disabled={!canManage || uploadingAsset === 'video' || !callAdminApi} loading={uploadingAsset === 'image'} onClick={() => openAssetPicker(imageInputRef)}>Upload ảnh vào nội dung</Button>
                                        <Button type="default" disabled={!canManage || uploadingAsset === 'image' || !callAdminApi} loading={uploadingAsset === 'video'} onClick={() => openAssetPicker(videoInputRef)}>Upload video vào nội dung</Button>
                                        <Button type="default" disabled={!canManage || Boolean(uploadingAsset)} onClick={() => {
                                            const editor = editorInstanceRef.current;

                                            if (editor) {
                                                captureEditorSelection(editor);
                                            }

                                            setYoutubeEmbedOpen(true);
                                        }}>
                                            Nhúng video YouTube
                                        </Button>
                                    </>
                                ) : null}
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
                                        captureEditorSelection(editor);
                                        editor.model.document.selection.on('change:range', () => {
                                            captureEditorSelection(editor);
                                        });
                                    }}
                                    onChange={(_, editor) => {
                                        captureEditorSelection(editor);
                                        syncEditorBodyToForm(editor);
                                    }}
                                />
                                </div>
                            ) : (
                                <TextArea
                                    rows={18}
                                    className="cms-html-code-input"
                                    value={detailContentValue}
                                    disabled={!canManage}
                                    placeholder="<section>Nhập mã HTML chi tiết sản phẩm...</section>"
                                    onChange={(event) => form.setFieldValue('detail_content', event.target.value)}
                                />
                            )}
                        </Form.Item>
                        <Form.Item name="detail_content" hidden>
                            <Input />
                        </Form.Item>
                            </>
                                            ) : null}
</Card>
                </div>
            </Form>

            <Modal
                title="Nhúng video YouTube"
                open={youtubeEmbedOpen}
                onCancel={() => setYoutubeEmbedOpen(false)}
                onOk={handleInsertYoutubeEmbed}
                okText="Chèn video"
                cancelText="Hủy"
                width={520}
                destroyOnHidden
            >
                <Space direction="vertical" size={12} style={{ width: '100%' }}>
                    <TextArea
                        rows={5}
                        value={youtubeUrl}
                        onChange={(event) => setYoutubeUrl(event.target.value)}
                        placeholder="Dán link YouTube hoặc mã nhúng vào đây"
                    />
                    <div style={{ color: 'rgba(0, 0, 0, 0.45)' }}>
                        Dán link YouTube chuẩn hoặc mã nhúng để hệ thống chèn video responsive vào nội dung sản phẩm.
                    </div>
                </Space>
            </Modal>
        </Drawer>
    );
}
