import { useEffect, useMemo, useRef, useState } from 'react';
import { CKEditor } from '@ckeditor/ckeditor5-react';
import InfoCircleOutlined from '@ant-design/icons/InfoCircleOutlined';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import message from 'antd/es/message';
import Modal from 'antd/es/modal';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Tooltip from 'antd/es/tooltip';
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

export const emptyCmsPageForm = {
    id: null,
    title: '',
    slug: '',
    status: 'draft',
    excerpt: '',
    body: '',
    meta_title: '',
    meta_description: '',
    template: '',
    featured_media_id: null,
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

export default function CmsPageFormModal({ open, canManage, editingPage, mediaOptions = [], callAdminApi, onCancel, onSubmit }) {
    const [form] = Form.useForm();
    const [messageApi, messageContextHolder] = message.useMessage();
    const [uploadingAsset, setUploadingAsset] = useState(null);
    const [youtubeEmbedOpen, setYoutubeEmbedOpen] = useState(false);
    const [youtubeUrl, setYoutubeUrl] = useState('');
    const editorInstanceRef = useRef(null);
    const editorSelectionRef = useRef(null);
    const imageInputRef = useRef(null);
    const videoInputRef = useRef(null);
    const slugEditedRef = useRef(Boolean(editingPage?.id));
    const titleValue = Form.useWatch('title', form) ?? '';
    const scopeValues = Form.useWatch(['website_key', 'owner_key', 'tenant_key'], form);
    const editorInitialData = useMemo(() => editingPage?.body ?? '', [editingPage?.id, editingPage?.slug, editingPage?.body]);
    const editorInstanceKey = useMemo(() => `${editingPage?.id ?? 'new'}:${editingPage?.slug ?? 'blank'}:${open ? 'open' : 'closed'}`, [editingPage?.id, editingPage?.slug, open]);

    useEffect(() => {
        form.setFieldsValue(editingPage);
        form.setFieldValue('body', editingPage?.body ?? '');
        slugEditedRef.current = Boolean(editingPage?.id || editingPage?.slug);
        editorSelectionRef.current = null;
    }, [editingPage, form]);

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
            ],
        },
    }), []);

    const uploadCmsMedia = async (file, typeLabel) => {
        const formData = new FormData();

        formData.append('file', file);
        formData.append('title', file.name.replace(/\.[^.]+$/, '') || typeLabel);

        [
            ['website_key', scopeValues?.[0] || null],
            ['owner_key', scopeValues?.[1] || null],
            ['tenant_key', scopeValues?.[2] || null],
        ].forEach(([key, value]) => {
            if (value) {
                formData.append(key, value);
            }
        });

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
            template: values.template || null,
            featured_media_id: values.featured_media_id || null,
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

    return (
        <Drawer
            title={editingPage?.id ? 'Cập nhật trang CMS' : 'Tạo trang CMS'}
            open={open}
            onCancel={handleCancel}
            width={960}
            destroyOnHidden
            className="cms-page-drawer"
            extra={(
                <Space>
                    <Button onClick={handleCancel}>Hủy</Button>
                    <Button type="primary" disabled={!canManage} onClick={handleSubmit}>Lưu trang</Button>
                </Space>
            )}
        >
            {messageContextHolder}
            <Form form={form} layout="vertical" initialValues={editingPage}>
                <div className="cms-post-form-shell">
                    <Card size="small" className="cms-post-form-card" title="Thông tin cơ bản">
                        <Row gutter={16}>
                            <Col xs={24} md={12}>
                                <Form.Item name="title" label="Tiêu đề" rules={[{ required: true, message: 'Nhập tiêu đề trang' }]}>
                                    <Input placeholder="VD: Trang giới thiệu" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item name="slug" label="Slug" rules={[{ required: true, message: 'Nhập slug' }]}>
                                    <Input placeholder="trang-gioi-thieu" onChange={handleSlugChange} />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item name="status" label="Trạng thái" rules={[{ required: true, message: 'Chọn trạng thái' }]}>
                                    <Select
                                        options={[
                                            { label: 'Bản nháp', value: 'draft' },
                                            { label: 'Đã xuất bản', value: 'published' },
                                        ]}
                                    />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item name="template" label="Template">
                                    <Input placeholder="default / landing / about" />
                                </Form.Item>
                            </Col>
                            <Col xs={24}>
                                <Form.Item name="excerpt" label="Mô tả ngắn" style={{ marginBottom: 0 }}>
                                    <Input.TextArea rows={3} placeholder="Tóm tắt ngắn dùng cho hero/SEO/listing" />
                                </Form.Item>
                            </Col>
                        </Row>
                    </Card>

                    <Card size="small" className="cms-post-form-card" title="Ảnh đại diện bài viết">
                        <Form.Item name="featured_media_id" label="Chọn media đại diện" style={{ marginBottom: 0 }}>
                            <Select
                                allowClear
                                showSearch
                                placeholder="Chọn media cơ bản"
                                optionFilterProp="label"
                                options={mediaOptions.map((item) => ({
                                    label: item.title,
                                    value: item.id,
                                }))}
                            />
                        </Form.Item>
                    </Card>

                    <Card size="small" className="cms-post-form-card" title="SEO cơ bản">
                        <Row gutter={16}>
                            <Col xs={24} md={12}>
                                <Form.Item name="meta_title" label="SEO Title">
                                    <Input.TextArea rows={3} placeholder="SEO title" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item name="meta_description" label="SEO Description" style={{ marginBottom: 0 }}>
                                    <Input.TextArea rows={3} placeholder="Meta description cơ bản" />
                                </Form.Item>
                            </Col>
                        </Row>
                    </Card>

                    <Card
                        size="small"
                        className="cms-post-form-card cms-post-form-card-editor"
                        title={(
                            <Space size={8}>
                                <span>Nội dung chi tiết</span>
                                <Tooltip title="Sau khi upload, hình ảnh hoặc video sẽ được chèn ngay vào vị trí nội dung hiện tại. Video YouTube có thể nhúng nhanh bằng nút riêng, không cần mở toolbar media của CKEditor.">
                                    <InfoCircleOutlined style={{ color: '#8c8c8c' }} />
                                </Tooltip>
                            </Space>
                        )}
                    >
                        <div className="cms-editor-upload-panel">
                            <Space wrap className="cms-editor-toolbar-row" size={12}>
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
                            </Space>
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
                    <Input.TextArea
                        rows={4}
                        value={youtubeUrl}
                        onChange={(event) => setYoutubeUrl(event.target.value)}
                        onPressEnter={handleInsertYoutubeEmbed}
                        placeholder="https://www.youtube.com/watch?v=..."
                    />
                </Space>
            </Modal>
        </Drawer>
    );
}
