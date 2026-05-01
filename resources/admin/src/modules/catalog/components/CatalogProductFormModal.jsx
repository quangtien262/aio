import { useEffect, useMemo, useRef, useState } from 'react';
import { CKEditor } from '@ckeditor/ckeditor5-react';
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
import Pagination from 'antd/es/pagination';
import Radio from 'antd/es/radio';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';
import dayjs from 'dayjs';
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
const { Text } = Typography;

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

export const emptyCatalogProductForm = {
    id: null,
    catalog_category_id: null,
    name: '',
    slug: '',
    sku: '',
    price: 0,
    original_price: null,
    stock: 0,
    short_description: '',
    detail_content: '',
    highlights: '',
    usage_terms: '',
    usage_location: '',
    image_url: '',
    gallery_images: [],
    sold_count: 0,
    deal_end_at: '',
    is_featured: false,
    sort_order: 0,
    is_active: true,
};

export default function CatalogProductFormModal({ open, canManage, editingProduct, categoryOptions = [], callAdminApi, onCancel, onSubmit }) {
    const [form] = Form.useForm();
    const [messageApi, messageContextHolder] = message.useMessage();
    const [uploadingAsset, setUploadingAsset] = useState(null);
    const [galleryMediaMode, setGalleryMediaMode] = useState('upload');
    const [galleryLibraryOpen, setGalleryLibraryOpen] = useState(false);
    const [galleryLibraryPage, setGalleryLibraryPage] = useState(1);
    const [galleryKeyword, setGalleryKeyword] = useState('');
    const [galleryUrl, setGalleryUrl] = useState('');
    const [galleryMediaOptions, setGalleryMediaOptions] = useState([]);
    const [galleryLibrarySelection, setGalleryLibrarySelection] = useState([]);
    const [youtubeEmbedOpen, setYoutubeEmbedOpen] = useState(false);
    const [youtubeUrl, setYoutubeUrl] = useState('');
    const editorInstanceRef = useRef(null);
    const editorSelectionRef = useRef(null);
    const imageInputRef = useRef(null);
    const videoInputRef = useRef(null);
    const galleryInputRef = useRef(null);
    const editorInitialData = useMemo(() => editingProduct?.detail_content ?? '', [editingProduct?.id, editingProduct?.slug, editingProduct?.detail_content]);
    const editorInstanceKey = useMemo(() => `${editingProduct?.id ?? 'new'}:${editingProduct?.slug ?? 'blank'}:${open ? 'open' : 'closed'}`, [editingProduct?.id, editingProduct?.slug, open]);
    const galleryImages = Form.useWatch('gallery_images', form) ?? [];
    const normalizedGalleryImages = useMemo(() => normalizeGalleryImages(galleryImages), [galleryImages]);
    const filteredGalleryMediaOptions = useMemo(() => {
        const normalizedKeyword = galleryKeyword.trim().toLowerCase();

        if (!normalizedKeyword) {
            return galleryMediaOptions;
        }

        return galleryMediaOptions.filter((item) => [item.title, item.file_url]
            .some((value) => String(value ?? '').toLowerCase().includes(normalizedKeyword)));
    }, [galleryKeyword, galleryMediaOptions]);
    const galleryPageSize = 8;
    const paginatedGalleryMediaOptions = useMemo(() => {
        const startIndex = (galleryLibraryPage - 1) * galleryPageSize;

        return filteredGalleryMediaOptions.slice(startIndex, startIndex + galleryPageSize);
    }, [filteredGalleryMediaOptions, galleryLibraryPage]);

    useEffect(() => {
        form.setFieldsValue({
            ...editingProduct,
            deal_end_at: normalizeDealEndAtValue(editingProduct?.deal_end_at),
        });
        form.setFieldValue('detail_content', editingProduct?.detail_content ?? '');
        form.setFieldValue('gallery_images', normalizeGalleryImages(editingProduct?.gallery_images ?? []));
        editorSelectionRef.current = null;
        setGalleryMediaMode('upload');
        setGalleryLibraryOpen(false);
        setGalleryLibraryPage(1);
        setGalleryKeyword('');
        setGalleryUrl('');
        setGalleryLibrarySelection([]);
    }, [editingProduct, form]);

    useEffect(() => {
        if (!open || !callAdminApi) {
            return undefined;
        }

        let isActive = true;

        callAdminApi('/admin/api/cms/media')
            .then((payload) => {
                if (!isActive) {
                    return;
                }

                setGalleryMediaOptions(payload?.data?.items ?? []);
            })
            .catch(() => {
                if (isActive) {
                    setGalleryMediaOptions([]);
                }
            });

        return () => {
            isActive = false;
        };
    }, [open, callAdminApi]);

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

        const payload = await callAdminApi('/admin/api/cms/media', {
            method: 'POST',
            body: formData,
        });

        const url = payload?.data?.file_url;

        if (!url) {
            throw new Error(`Upload ${typeLabel} vào CMS không thành công.`);
        }

        return url;
    };

    const createGalleryMediaRecord = async ({ file, fileUrl, title }) => {
        if (!callAdminApi) {
            throw new Error('Thiếu cấu hình media cho gallery sản phẩm.');
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

        if (!payload?.data?.file_url) {
            throw new Error('Không thể tạo ảnh cho gallery sản phẩm.');
        }

        return payload.data;
    };

    const setGalleryImages = (nextImages) => {
        form.setFieldValue('gallery_images', Array.from(new Set(normalizeGalleryImages(nextImages))));
    };

    const appendGalleryImages = (nextImages) => {
        setGalleryImages([...normalizedGalleryImages, ...normalizeGalleryImages(nextImages)]);
    };

    const removeGalleryImage = (imageUrl) => {
        setGalleryImages(normalizedGalleryImages.filter((item) => item !== imageUrl));
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

    const handleUploadGalleryImages = async (event) => {
        const files = Array.from(event.target.files ?? []);

        if (!files.length) {
            return;
        }

        setUploadingAsset('gallery-image');

        try {
            const uploadedMedia = [];

            for (const file of files) {
                const media = await createGalleryMediaRecord({
                    file,
                    title: file.name.replace(/\.[^.]+$/, ''),
                });

                uploadedMedia.push(media);
            }

            setGalleryMediaOptions((currentOptions) => {
                const nextMap = new Map(currentOptions.map((item) => [item.id, item]));

                uploadedMedia.forEach((item) => nextMap.set(item.id, item));

                return Array.from(nextMap.values());
            });
            appendGalleryImages(uploadedMedia.map((item) => item.file_url));
            messageApi.success(`Đã thêm ${uploadedMedia.length} ảnh vào gallery sản phẩm.`);
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : 'Upload gallery sản phẩm không thành công.');
        } finally {
            setUploadingAsset(null);
            event.target.value = '';
        }
    };

    const handleCreateGalleryImagesFromUrl = async () => {
        const urls = galleryUrl
            .split(/\r?\n/)
            .map((item) => item.trim())
            .filter(Boolean);

        if (!urls.length) {
            messageApi.warning('Nhập ít nhất một URL ảnh trước khi lưu.');
            return;
        }

        setUploadingAsset('gallery-url');

        try {
            const createdMedia = [];

            for (const [index, fileUrl] of urls.entries()) {
                const media = await createGalleryMediaRecord({
                    fileUrl,
                    title: `${form.getFieldValue('name') || 'Gallery image'} ${index + 1}`,
                });

                createdMedia.push(media);
            }

            setGalleryMediaOptions((currentOptions) => {
                const nextMap = new Map(currentOptions.map((item) => [item.id, item]));

                createdMedia.forEach((item) => nextMap.set(item.id, item));

                return Array.from(nextMap.values());
            });
            appendGalleryImages(createdMedia.map((item) => item.file_url));
            setGalleryUrl('');
            messageApi.success(`Đã thêm ${createdMedia.length} ảnh từ URL vào gallery.`);
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : 'Không thể lưu ảnh gallery từ URL.');
        } finally {
            setUploadingAsset(null);
        }
    };

    const openGalleryLibrary = () => {
        setGalleryLibrarySelection(normalizedGalleryImages);
        setGalleryLibraryOpen(true);
    };

    const applyGalleryLibrarySelection = () => {
        appendGalleryImages(galleryLibrarySelection);
        setGalleryLibraryOpen(false);
    };

    const renderGalleryPreview = () => {
        if (!normalizedGalleryImages.length) {
            return null;
        }

        return (
            <div style={{ display: 'grid', gap: 12 }}>
                {normalizedGalleryImages.map((imageUrl, index) => (
                    <div
                        key={`${imageUrl}-${index}`}
                        style={{
                            display: 'grid',
                            gridTemplateColumns: '96px minmax(0, 1fr) auto',
                            gap: 12,
                            alignItems: 'center',
                            padding: 12,
                            border: '1px solid #dbe7e4',
                            borderRadius: 16,
                            background: '#fff',
                        }}
                    >
                        <img
                            src={imageUrl}
                            alt={`Gallery ${index + 1}`}
                            style={{ width: 96, height: 96, objectFit: 'cover', borderRadius: 12 }}
                        />
                        <div style={{ minWidth: 0, display: 'grid', gap: 4 }}>
                            <strong>{`Ảnh gallery ${index + 1}`}</strong>
                            <span style={{ color: '#6b7280', wordBreak: 'break-all' }}>{imageUrl}</span>
                        </div>
                        <Button size="small" onClick={() => removeGalleryImage(imageUrl)}>Bỏ chọn</Button>
                    </div>
                ))}
            </div>
        );
    };

    const handleSubmit = async () => {
        const values = await form.validateFields();

        await onSubmit?.({
            ...values,
            catalog_category_id: values.catalog_category_id || null,
            slug: values.slug || null,
            sku: values.sku || null,
            original_price: values.original_price ?? null,
            short_description: values.short_description || null,
            detail_content: values.detail_content || null,
            highlights: values.highlights || null,
            usage_terms: values.usage_terms || null,
            usage_location: values.usage_location || null,
            image_url: values.image_url || null,
            gallery_images: normalizeGalleryImages(values.gallery_images),
            sold_count: values.sold_count ?? 0,
            deal_end_at: values.deal_end_at ? values.deal_end_at.format('YYYY-MM-DDTHH:mm:ss') : null,
            is_featured: Boolean(values.is_featured),
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
                    <Card size="small" className="cms-post-form-card" title="Thông tin cơ bản">
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
                                <Form.Item name="catalog_category_id" label="Danh mục">
                                    <Select allowClear showSearch optionFilterProp="label" options={categoryOptions} placeholder="Chọn danh mục" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item name="slug" label="Slug public">
                                    <Input placeholder="san-pham-noi-bat" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item name="image_url" label="Ảnh cover sản phẩm">
                                    <Input placeholder="https://cdn.example.com/product.jpg" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item name="short_description" label="Mô tả ngắn">
                                    <Input.TextArea rows={3} placeholder="Mô tả cho card và detail page" />
                                </Form.Item>
                            </Col>
                        </Row>
                    </Card>

                    <Card size="small" className="cms-post-form-card" title="Giá bán và tồn kho">
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
                                <Form.Item name="is_featured" valuePropName="checked" label=" " colon={false}>
                                    <Checkbox>Đánh dấu nổi bật</Checkbox>
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="is_active" valuePropName="checked" label=" " colon={false}>
                                    <Checkbox>Kích hoạt sản phẩm</Checkbox>
                                </Form.Item>
                            </Col>
                        </Row>
                    </Card>

                    <Card size="small" className="cms-post-form-card" title="Gallery và điều kiện hiển thị">
                        <Form.Item name="gallery_images" label="Gallery ảnh sản phẩm" style={{ marginBottom: 16 }}>
                            <div className="cms-featured-media-shell">
                                <Radio.Group
                                    value={galleryMediaMode}
                                    onChange={(event) => setGalleryMediaMode(event.target.value)}
                                    optionType="button"
                                    buttonStyle="solid"
                                    className="cms-featured-media-mode"
                                    options={[
                                        { label: 'Upload nhiều ảnh', value: 'upload' },
                                        { label: 'Chọn từ thư viện', value: 'library' },
                                        { label: 'Nhập từ URL', value: 'url' },
                                    ]}
                                />

                                {galleryMediaMode === 'upload' ? (
                                    <div className="cms-featured-media-action-card">
                                        <input ref={galleryInputRef} type="file" accept="image/*" multiple style={{ display: 'none' }} onChange={handleUploadGalleryImages} />
                                        <Space direction="vertical" size={10} style={{ width: '100%' }}>
                                            <Space wrap>
                                                <Button
                                                    type="primary"
                                                    disabled={!canManage || !callAdminApi}
                                                    loading={uploadingAsset === 'gallery-image'}
                                                    onClick={() => galleryInputRef.current?.click()}
                                                >
                                                    Upload nhiều ảnh
                                                </Button>
                                                <Text type="secondary">Mỗi lần có thể chọn nhiều ảnh và tự thêm vào gallery.</Text>
                                            </Space>
                                            {renderGalleryPreview()}
                                        </Space>
                                    </div>
                                ) : null}

                                {galleryMediaMode === 'library' ? (
                                    <div className="cms-featured-media-action-card">
                                        <Space direction="vertical" size={10} style={{ width: '100%' }}>
                                            <Space wrap>
                                                <Button type="primary" onClick={openGalleryLibrary}>
                                                    Mở thư viện media
                                                </Button>
                                                <Text type="secondary">Chọn nhiều ảnh có sẵn từ CMS media rồi thêm vào gallery.</Text>
                                            </Space>
                                            {renderGalleryPreview()}
                                        </Space>
                                    </div>
                                ) : null}

                                {galleryMediaMode === 'url' ? (
                                    <div className="cms-featured-media-action-card">
                                        <Space direction="vertical" size={10} style={{ width: '100%' }}>
                                            <TextArea
                                                value={galleryUrl}
                                                onChange={(event) => setGalleryUrl(event.target.value)}
                                                rows={4}
                                                placeholder={['https://cdn.example.com/product-1.jpg', 'https://cdn.example.com/product-2.jpg'].join('\n')}
                                            />
                                            <Space wrap>
                                                <Button
                                                    type="primary"
                                                    disabled={!canManage || !callAdminApi}
                                                    loading={uploadingAsset === 'gallery-url'}
                                                    onClick={handleCreateGalleryImagesFromUrl}
                                                >
                                                    Lưu URL và thêm vào gallery
                                                </Button>
                                                <Text type="secondary">Mỗi dòng là một URL ảnh, hệ thống sẽ lưu vào CMS media để tái sử dụng.</Text>
                                            </Space>
                                            {renderGalleryPreview()}
                                        </Space>
                                    </div>
                                ) : null}
                            </div>
                        </Form.Item>

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
                    </Card>

                    <Card size="small" className="cms-post-form-card cms-post-form-card-editor" title="Nội dung chi tiết sản phẩm">
                        <div className="cms-editor-upload-panel">
                            <div className="cms-editor-upload-copy">
                                <strong>Chèn hình ảnh và video vào nội dung</strong>
                                <span>Dùng các nút bên dưới để upload media vào CMS rồi chèn trực tiếp vào mô tả sản phẩm.</span>
                            </div>
                            <div className="cms-editor-toolbar-row">
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
                            </div>
                        </div>

                        <Form.Item label="Nội dung" style={{ marginBottom: 0 }}>
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
                        </Form.Item>
                        <Form.Item name="detail_content" hidden>
                            <Input />
                        </Form.Item>
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

            <Modal
                title="Chọn ảnh gallery từ thư viện"
                open={galleryLibraryOpen}
                onCancel={() => setGalleryLibraryOpen(false)}
                onOk={applyGalleryLibrarySelection}
                okText={`Thêm ${galleryLibrarySelection.length} ảnh`}
                cancelText="Hủy"
                width={920}
                destroyOnHidden
            >
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Input.Search
                        allowClear
                        value={galleryKeyword}
                        onChange={(event) => {
                            setGalleryKeyword(event.target.value);
                            setGalleryLibraryPage(1);
                        }}
                        placeholder="Tìm theo tên media hoặc URL"
                    />

                    <div className="cms-featured-media-library-grid">
                        {paginatedGalleryMediaOptions.map((item) => {
                            const isSelected = galleryLibrarySelection.includes(item.file_url);

                            return (
                                <button
                                    key={item.id}
                                    type="button"
                                    className={`cms-featured-media-library-item${isSelected ? ' is-selected' : ''}`}
                                    onClick={() => {
                                        setGalleryLibrarySelection((currentSelection) => (
                                            currentSelection.includes(item.file_url)
                                                ? currentSelection.filter((currentItem) => currentItem !== item.file_url)
                                                : [...currentSelection, item.file_url]
                                        ));
                                    }}
                                >
                                    <div className="cms-featured-media-library-thumb">
                                        {item.file_url ? <img src={item.file_url} alt={item.title} /> : null}
                                    </div>
                                    <div className="cms-featured-media-library-copy">
                                        <strong>{item.title || `Media #${item.id}`}</strong>
                                        <span>{item.file_url || 'Không có URL'}</span>
                                    </div>
                                </button>
                            );
                        })}
                    </div>

                    <Pagination
                        current={galleryLibraryPage}
                        pageSize={galleryPageSize}
                        total={filteredGalleryMediaOptions.length}
                        showSizeChanger={false}
                        onChange={setGalleryLibraryPage}
                    />
                </Space>
            </Modal>
        </Drawer>
    );
}
