import { useEffect, useMemo, useRef, useState } from 'react';
import { CKEditor } from '@ckeditor/ckeditor5-react';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Collapse from 'antd/es/collapse';
import DatePicker from 'antd/es/date-picker';
import Divider from 'antd/es/divider';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
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

const { Text } = Typography;

export const emptyCmsPostForm = {
    id: null,
    title: '',
    slug: '',
    status: 'draft',
    excerpt: '',
    body: '',
    meta_title: '',
    meta_description: '',
    featured_media_id: null,
    category_id: null,
    publish_at: null,
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

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

function normalizePublishAtValue(value) {
    if (!value) {
        return null;
    }

    const dateValue = dayjs(value);

    return dateValue.isValid() ? dateValue : null;
}

function getYoutubeEmbedUrl(value) {
    const trimmedValue = String(value ?? '').trim();

    if (!trimmedValue) {
        return null;
    }

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

        if (!videoId) {
            return null;
        }

        const safeVideoId = videoId.replace(/[^a-zA-Z0-9_-]/g, '');

        return safeVideoId ? `https://www.youtube.com/embed/${safeVideoId}` : null;
    } catch {
        return null;
    }
}

export default function CmsPostFormModal({ open, canManage, editingPost, mediaOptions = [], categoryOptions = [], callAdminApi, onCancel, onSubmit }) {
    const [form] = Form.useForm();
    const [messageApi, messageContextHolder] = message.useMessage();
    const [uploadingAsset, setUploadingAsset] = useState(null);
    const [featuredMediaMode, setFeaturedMediaMode] = useState('upload');
    const [featuredMediaLibraryOpen, setFeaturedMediaLibraryOpen] = useState(false);
    const [featuredMediaLibraryPage, setFeaturedMediaLibraryPage] = useState(1);
    const [featuredMediaKeyword, setFeaturedMediaKeyword] = useState('');
    const [featuredMediaUrl, setFeaturedMediaUrl] = useState('');
    const [featuredMediaOptions, setFeaturedMediaOptions] = useState(mediaOptions);
    const [youtubeEmbedOpen, setYoutubeEmbedOpen] = useState(false);
    const [youtubeUrl, setYoutubeUrl] = useState('');
    const editorInstanceRef = useRef(null);
    const editorSelectionRef = useRef(null);
    const imageInputRef = useRef(null);
    const videoInputRef = useRef(null);
    const featuredMediaInputRef = useRef(null);
    const slugEditedRef = useRef(Boolean(editingPost?.id));
    const titleValue = Form.useWatch('title', form) ?? '';
    const featuredMediaId = Form.useWatch('featured_media_id', form) ?? null;
    const editorInitialData = useMemo(() => editingPost?.body ?? '', [editingPost?.id, editingPost?.slug, editingPost?.body]);
    const editorInstanceKey = useMemo(() => `${editingPost?.id ?? 'new'}:${editingPost?.slug ?? 'blank'}:${open ? 'open' : 'closed'}`, [editingPost?.id, editingPost?.slug, open]);

    useEffect(() => {
        const normalizedPublishAt = normalizePublishAtValue(editingPost?.publish_at) ?? (!editingPost?.id ? dayjs() : null);

        form.setFieldsValue({
            ...editingPost,
            publish_at: normalizedPublishAt,
        });
        form.setFieldValue('body', editingPost?.body ?? '');
        slugEditedRef.current = Boolean(editingPost?.id || editingPost?.slug);
        editorSelectionRef.current = null;
        setFeaturedMediaMode(editingPost?.featured_media_id ? 'library' : 'upload');
        setFeaturedMediaUrl('');
        setFeaturedMediaKeyword('');
        setFeaturedMediaLibraryPage(1);
        setFeaturedMediaLibraryOpen(false);
    }, [editingPost, form]);

    useEffect(() => {
        setFeaturedMediaOptions((currentOptions) => {
            const nextMap = new Map(currentOptions.map((item) => [item.id, item]));

            mediaOptions.forEach((item) => {
                nextMap.set(item.id, item);
            });

            return Array.from(nextMap.values());
        });
    }, [mediaOptions]);

    useEffect(() => {
        if (slugEditedRef.current) {
            return;
        }

        form.setFieldValue('slug', toSlug(titleValue));
    }, [form, titleValue]);

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
                {
                    name: 'figure',
                    classes: true,
                    attributes: true,
                    styles: true,
                },
                {
                    name: 'video',
                    classes: true,
                    attributes: true,
                    styles: true,
                },
                {
                    name: 'source',
                    classes: true,
                    attributes: true,
                    styles: true,
                },
                {
                    name: 'img',
                    classes: true,
                    attributes: true,
                    styles: true,
                },
                {
                    name: 'div',
                    classes: true,
                    attributes: true,
                    styles: true,
                },
                {
                    name: 'iframe',
                    classes: true,
                    attributes: true,
                    styles: true,
                },
            ],
        },
    }), []);

    const selectedFeaturedMedia = useMemo(
        () => featuredMediaOptions.find((item) => item.id === featuredMediaId) ?? null,
        [featuredMediaId, featuredMediaOptions],
    );

    const filteredFeaturedMediaOptions = useMemo(() => {
        const normalizedKeyword = featuredMediaKeyword.trim().toLowerCase();

        if (!normalizedKeyword) {
            return featuredMediaOptions;
        }

        return featuredMediaOptions.filter((item) => [item.title, item.file_url]
            .some((value) => String(value ?? '').toLowerCase().includes(normalizedKeyword)));
    }, [featuredMediaKeyword, featuredMediaOptions]);

    const featuredMediaPageSize = 8;
    const paginatedFeaturedMediaOptions = useMemo(() => {
        const startIndex = (featuredMediaLibraryPage - 1) * featuredMediaPageSize;

        return filteredFeaturedMediaOptions.slice(startIndex, startIndex + featuredMediaPageSize);
    }, [featuredMediaLibraryPage, filteredFeaturedMediaOptions]);

    const uploadCmsMedia = async (file, typeLabel) => {
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

    const createFeaturedMediaRecord = async ({ file, fileUrl, title }) => {
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
            throw new Error('Không thể tạo media đại diện bài viết.');
        }

        return payload.data;
    };

    const syncEditorBodyToForm = (editor) => {
        form.setFieldValue('body', editor.getData());
    };

    const captureEditorSelection = (editor) => {
        const range = editor?.model?.document?.selection?.getFirstRange?.();

        editorSelectionRef.current = range ? range.clone() : null;
    };

    const insertHtmlIntoEditor = (html) => {
        const editor = editorInstanceRef.current;

        if (!editor) {
            const currentData = form.getFieldValue('body') || '';

            form.setFieldValue('body', `${currentData}${html}`);
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
            messageApi.success(`Đã chèn ảnh "${file.name}" vào nội dung.`);
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
            messageApi.success(`Đã chèn video "${file.name}" vào nội dung.`);
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : 'Upload video vào nội dung không thành công.');
        } finally {
            setUploadingAsset(null);
            event.target.value = '';
        }
    };

    const handleInsertYoutubeEmbed = () => {
        const embedUrl = getYoutubeEmbedUrl(youtubeUrl);

        if (!embedUrl) {
            messageApi.warning('Nhập đúng link YouTube trước khi nhúng.');
            return;
        }

        insertHtmlIntoEditor(`<div class="cms-inline-video cms-inline-youtube" style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:16px;"><iframe src="${embedUrl}" title="YouTube video player" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;"></iframe></div>`);
        setYoutubeEmbedOpen(false);
        setYoutubeUrl('');
        messageApi.success('Đã nhúng video YouTube vào nội dung.');
    };

    const handleSubmit = async () => {
        const values = await form.validateFields();

        await onSubmit?.({
            ...values,
            excerpt: values.excerpt || null,
            body: values.body || null,
            meta_title: values.meta_title || null,
            meta_description: values.meta_description || null,
            featured_media_id: values.featured_media_id || null,
            category_id: values.category_id || null,
            publish_at: values.publish_at ? values.publish_at.format('YYYY-MM-DDTHH:mm:ss') : null,
        });

        form.resetFields();
    };

    const handleCancel = () => {
        form.resetFields();
        onCancel?.();
    };

    const handleSlugChange = (event) => {
        slugEditedRef.current = true;
        form.setFieldValue('slug', toSlug(event.target.value));
    };

    const handleUploadFeaturedMedia = async (event) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        setUploadingAsset('featured-image');

        try {
            const media = await createFeaturedMediaRecord({
                file,
                title: file.name.replace(/\.[^.]+$/, ''),
            });

            setFeaturedMediaOptions((currentOptions) => [media, ...currentOptions.filter((item) => item.id !== media.id)]);
            form.setFieldValue('featured_media_id', media.id);
            messageApi.success(`Đã upload và gán ảnh đại diện "${file.name}".`);
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : 'Upload ảnh đại diện không thành công.');
        } finally {
            setUploadingAsset(null);
            event.target.value = '';
        }
    };

    const handleCreateFeaturedMediaFromUrl = async () => {
        const trimmedUrl = featuredMediaUrl.trim();

        if (!trimmedUrl) {
            messageApi.warning('Nhập URL ảnh trước khi lưu.');
            return;
        }

        setUploadingAsset('featured-url');

        try {
            const media = await createFeaturedMediaRecord({
                fileUrl: trimmedUrl,
                title: form.getFieldValue('title') || 'Featured image',
            });

            setFeaturedMediaOptions((currentOptions) => [media, ...currentOptions.filter((item) => item.id !== media.id)]);
            form.setFieldValue('featured_media_id', media.id);
            messageApi.success('Đã lưu URL và gán làm ảnh đại diện bài viết.');
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : 'Không thể lưu ảnh đại diện từ URL.');
        } finally {
            setUploadingAsset(null);
        }
    };

    const renderFeaturedMediaPreview = () => {
        if (!selectedFeaturedMedia?.file_url) {
            return null;
        }

        return (
            <div className="cms-featured-media-preview">
                <img src={selectedFeaturedMedia.file_url} alt={selectedFeaturedMedia.title || 'Featured media'} />
                <div className="cms-featured-media-preview-copy">
                    <strong>{selectedFeaturedMedia.title || 'Ảnh đại diện bài viết'}</strong>
                    <span>{selectedFeaturedMedia.file_url}</span>
                </div>
                <Button size="small" onClick={() => form.setFieldValue('featured_media_id', null)}>Bỏ chọn</Button>
            </div>
        );
    };

    return (
        <Drawer
            title={editingPost?.id ? 'Cập nhật bài viết CMS' : 'Tạo bài viết CMS'}
            open={open}
            onCancel={handleCancel}
            width={960}
            destroyOnHidden
            className="cms-page-drawer"
            extra={(
                <Space>
                    <Button onClick={handleCancel}>Hủy</Button>
                    <Button type="primary" disabled={!canManage} onClick={handleSubmit}>Lưu bài viết</Button>
                </Space>
            )}
        >
            {messageContextHolder}
            <Form form={form} layout="vertical" initialValues={editingPost}>
                <div className="cms-post-form-shell">
                    <Card size="small" className="cms-post-form-card" title="Thông tin bài viết">
                        <Row gutter={16}>
                            <Col xs={24} md={14}>
                                <Form.Item name="title" label="Tiêu đề" rules={[{ required: true, message: 'Nhập tiêu đề bài viết' }]}>
                                    <Input placeholder="Bài viết nổi bật" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={10}>
                                <Form.Item name="slug" label="Slug" rules={[{ required: true, message: 'Nhập slug bài viết' }]}>
                                    <Input placeholder="bai-viet-noi-bat" onChange={handleSlugChange} />
                                </Form.Item>
                            </Col>
                        </Row>

                        <Form.Item name="excerpt" label="Mô tả ngắn" style={{ marginBottom: 0 }}>
                            <Input.TextArea rows={3} placeholder="Tóm tắt ngắn để hiển thị ở listing, SEO snippet hoặc block nổi bật." />
                        </Form.Item>
                    </Card>

                    <Card size="small" className="cms-post-form-card" title="Xuất bản và hiển thị">
                        <Row gutter={16}>
                            <Col xs={24} md={8}>
                                <Form.Item name="status" label="Trạng thái" rules={[{ required: true, message: 'Chọn trạng thái' }]}>
                                    <Select options={[{ label: 'Bản nháp', value: 'draft' }, { label: 'Đã xuất bản', value: 'published' }]} />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="category_id" label="Danh mục">
                                    <Select allowClear showSearch optionFilterProp="label" options={categoryOptions} placeholder="Chọn danh mục" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="publish_at" label="Publish At">
                                    <DatePicker
                                        showTime
                                        format="DD/MM/YYYY HH:mm"
                                        placeholder="Chọn thời gian xuất bản"
                                        style={{ width: '100%' }}
                                    />
                                </Form.Item>
                            </Col>
                            <Col xs={24}>
                                <br/>
                                <Form.Item name="featured_media_id" label="Ảnh đại diện bài viết" style={{ marginBottom: 0 }}>
                                    <div className="cms-featured-media-shell">
                                        <Radio.Group
                                            value={featuredMediaMode}
                                            onChange={(event) => setFeaturedMediaMode(event.target.value)}
                                            optionType="button"
                                            buttonStyle="solid"
                                            className="cms-featured-media-mode"
                                            options={[
                                                { label: 'Upload ảnh trực tiếp', value: 'upload' },
                                                { label: 'Chọn từ danh sách có sẵn', value: 'library' },
                                                { label: 'Nhập từ URL', value: 'url' },
                                            ]}
                                        />

                                        {featuredMediaMode === 'upload' ? (
                                            <div className="cms-featured-media-action-card">
                                                <input ref={featuredMediaInputRef} type="file" accept="image/*" style={{ display: 'none' }} onChange={handleUploadFeaturedMedia} />
                                                <Space direction="vertical" size={10} style={{ width: '100%' }}>
                                                    <Space wrap>
                                                        <Button
                                                            type="primary"
                                                            disabled={!canManage}
                                                            loading={uploadingAsset === 'featured-image'}
                                                            onClick={() => featuredMediaInputRef.current?.click()}
                                                        >
                                                            Upload ảnh trực tiếp
                                                        </Button>
                                                        <Text type="secondary">Ảnh upload xong sẽ tự được gán làm ảnh đại diện.</Text>
                                                    </Space>
                                                    {renderFeaturedMediaPreview()}
                                                </Space>
                                            </div>
                                        ) : null}

                                        {featuredMediaMode === 'library' ? (
                                            <div className="cms-featured-media-action-card">
                                                <Space direction="vertical" size={10} style={{ width: '100%' }}>
                                                    <Space wrap>
                                                        <Button type="primary" onClick={() => setFeaturedMediaLibraryOpen(true)}>
                                                            Mở thư viện media
                                                        </Button>
                                                        <Text type="secondary">Chọn lại từ media CMS đã có sẵn.</Text>
                                                    </Space>
                                                    {renderFeaturedMediaPreview()}
                                                </Space>
                                            </div>
                                        ) : null}

                                        {featuredMediaMode === 'url' ? (
                                            <div className="cms-featured-media-action-card">
                                                <Space direction="vertical" size={10} style={{ width: '100%' }}>
                                                    <Input
                                                        value={featuredMediaUrl}
                                                        onChange={(event) => setFeaturedMediaUrl(event.target.value)}
                                                        placeholder="https://example.com/featured-image.jpg"
                                                    />
                                                    <Space wrap>
                                                        <Button
                                                            type="primary"
                                                            disabled={!canManage}
                                                            loading={uploadingAsset === 'featured-url'}
                                                            onClick={handleCreateFeaturedMediaFromUrl}
                                                        >
                                                            Lưu URL và gán ảnh
                                                        </Button>
                                                        <Text type="secondary">URL sẽ được lưu vào CMS media để tái sử dụng về sau.</Text>
                                                    </Space>
                                                    {renderFeaturedMediaPreview()}
                                                </Space>
                                            </div>
                                        ) : null}
                                    </div>
                                </Form.Item>
                            </Col>
                        </Row>
                    </Card>

                    <Collapse
                        className="cms-post-seo-collapse"
                        items={[
                            {
                                key: 'seo',
                                label: 'SEO cơ bản',
                                children: (
                                    <Row gutter={16}>
                                        <Col xs={24} md={12}>
                                            <Form.Item name="meta_title" label="SEO Title">
                                                <Input placeholder="SEO title" />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} md={12}>
                                            <Form.Item name="meta_description" label="SEO Description" style={{ marginBottom: 0 }}>
                                                <Input.TextArea rows={3} placeholder="Meta description bài viết" />
                                            </Form.Item>
                                        </Col>
                                    </Row>
                                ),
                            },
                        ]}
                    />

                    <Card size="small" className="cms-post-form-card cms-post-form-card-editor" title="Nội dung chi tiết">
                        <div className="cms-editor-upload-panel">
                            <div className="cms-editor-upload-copy">
                                <strong>Chèn hình ảnh và video vào nội dung</strong>
                                <span>Dùng các nút bên dưới để upload media vào CMS rồi chèn trực tiếp vào bài viết.</span>
                            </div>
                            <div className="cms-editor-toolbar-row">
                                <input ref={imageInputRef} type="file" accept="image/*" style={{ display: 'none' }} onChange={handleInsertImage} />
                                <input ref={videoInputRef} type="file" accept="video/*" style={{ display: 'none' }} onChange={handleInsertVideo} />
                                <Button type="default" disabled={!canManage || uploadingAsset === 'video'} loading={uploadingAsset === 'image'} onClick={() => openAssetPicker(imageInputRef)}>Upload ảnh vào nội dung</Button>
                                <Button type="default" disabled={!canManage || uploadingAsset === 'image'} loading={uploadingAsset === 'video'} onClick={() => openAssetPicker(videoInputRef)}>Upload video vào nội dung</Button>
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
                        <Form.Item name="body" hidden>
                            <Input.TextArea />
                        </Form.Item>
                        <Divider style={{ margin: '16px 0 12px' }} />
                        <div className="cms-editor-hint">Sau khi upload, hình ảnh hoặc video sẽ được chèn ngay vào vị trí nội dung hiện tại. Video YouTube có thể nhúng nhanh bằng nút riêng, không cần mở toolbar media của CKEditor.</div>
                    </Card>
                </div>
            </Form>

            <Modal
                title="Nhúng video từ YouTube"
                open={youtubeEmbedOpen}
                onCancel={() => {
                    setYoutubeEmbedOpen(false);
                    setYoutubeUrl('');
                }}
                onOk={handleInsertYoutubeEmbed}
                okText="Nhúng vào nội dung"
                cancelText="Hủy"
                destroyOnHidden
            >
                <Space direction="vertical" size={12} style={{ width: '100%' }}>
                    <Text type="secondary">Dán link YouTube dạng `watch?v=`, `youtu.be`, `shorts` hoặc `embed`.</Text>
                    <Input
                        autoFocus
                        value={youtubeUrl}
                        onChange={(event) => setYoutubeUrl(event.target.value)}
                        onPressEnter={handleInsertYoutubeEmbed}
                        placeholder="https://www.youtube.com/watch?v=..."
                    />
                </Space>
            </Modal>

            <Modal
                title="Chọn ảnh đại diện từ thư viện"
                open={featuredMediaLibraryOpen}
                onCancel={() => setFeaturedMediaLibraryOpen(false)}
                footer={null}
                width={920}
                destroyOnHidden
            >
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Input.Search
                        allowClear
                        value={featuredMediaKeyword}
                        onChange={(event) => {
                            setFeaturedMediaKeyword(event.target.value);
                            setFeaturedMediaLibraryPage(1);
                        }}
                        placeholder="Tìm theo tên media hoặc URL"
                    />

                    <div className="cms-featured-media-library-grid">
                        {paginatedFeaturedMediaOptions.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                className={`cms-featured-media-library-item${item.id === featuredMediaId ? ' is-selected' : ''}`}
                                onClick={() => {
                                    form.setFieldValue('featured_media_id', item.id);
                                    setFeaturedMediaLibraryOpen(false);
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
                        ))}
                    </div>

                    <Pagination
                        current={featuredMediaLibraryPage}
                        pageSize={featuredMediaPageSize}
                        total={filteredFeaturedMediaOptions.length}
                        showSizeChanger={false}
                        onChange={setFeaturedMediaLibraryPage}
                    />
                </Space>
            </Modal>
        </Drawer>
    );
}
