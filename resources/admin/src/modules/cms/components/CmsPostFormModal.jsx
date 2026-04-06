import { useEffect, useMemo, useRef, useState } from 'react';
import { CKEditor } from '@ckeditor/ckeditor5-react';
import Button from 'antd/es/button';
import Col from 'antd/es/col';
import Divider from 'antd/es/divider';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import message from 'antd/es/message';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
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

export default function CmsPostFormModal({ open, canManage, editingPost, mediaOptions = [], categoryOptions = [], callAdminApi, onCancel, onSubmit }) {
    const [form] = Form.useForm();
    const [messageApi, messageContextHolder] = message.useMessage();
    const [uploadingAsset, setUploadingAsset] = useState(null);
    const editorInstanceRef = useRef(null);
    const editorSelectionRef = useRef(null);
    const imageInputRef = useRef(null);
    const videoInputRef = useRef(null);
    const slugEditedRef = useRef(Boolean(editingPost?.id));
    const titleValue = Form.useWatch('title', form) ?? '';
    const editorInitialData = useMemo(() => editingPost?.body ?? '', [editingPost?.id, editingPost?.slug, editingPost?.body]);
    const editorInstanceKey = useMemo(() => `${editingPost?.id ?? 'new'}:${editingPost?.slug ?? 'blank'}:${open ? 'open' : 'closed'}`, [editingPost?.id, editingPost?.slug, open]);

    useEffect(() => {
        form.setFieldsValue(editingPost);
        form.setFieldValue('body', editingPost?.body ?? '');
        slugEditedRef.current = Boolean(editingPost?.id || editingPost?.slug);
        editorSelectionRef.current = null;
    }, [editingPost, form]);

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
            publish_at: values.publish_at || null,
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
                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="title" label="Tiêu đề" rules={[{ required: true, message: 'Nhập tiêu đề bài viết' }]}>
                            <Input placeholder="Bài viết nổi bật" />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="slug" label="Slug" rules={[{ required: true, message: 'Nhập slug bài viết' }]}>
                            <Input placeholder="bai-viet-noi-bat" onChange={handleSlugChange} />
                        </Form.Item>
                    </Col>
                </Row>

                <Row gutter={16}>
                    <Col span={8}>
                        <Form.Item name="status" label="Trạng thái" rules={[{ required: true, message: 'Chọn trạng thái' }]}>
                            <Select options={[{ label: 'Bản nháp', value: 'draft' }, { label: 'Đã xuất bản', value: 'published' }]} />
                        </Form.Item>
                    </Col>
                    <Col span={8}>
                        <Form.Item name="category_id" label="Category">
                            <Select allowClear showSearch optionFilterProp="label" options={categoryOptions} />
                        </Form.Item>
                    </Col>
                    <Col span={8}>
                        <Form.Item name="publish_at" label="Publish At">
                            <Input type="datetime-local" />
                        </Form.Item>
                    </Col>
                </Row>

                <Form.Item name="excerpt" label="Mô tả ngắn">
                    <Input.TextArea rows={3} placeholder="Tóm tắt bài viết" />
                </Form.Item>

                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="meta_title" label="SEO Title">
                            <Input placeholder="SEO title" />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="featured_media_id" label="Featured Media">
                            <Select allowClear showSearch optionFilterProp="label" options={mediaOptions.map((item) => ({ label: item.title, value: item.id }))} />
                        </Form.Item>
                    </Col>
                </Row>

                <Form.Item name="meta_description" label="SEO Description">
                    <Input.TextArea rows={3} placeholder="Meta description bài viết" />
                </Form.Item>

                <Divider style={{ marginTop: 8 }}>Nội dung chi tiết</Divider>

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
                    </div>
                </div>

                <Form.Item label="Nội dung">
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
                <div className="cms-editor-hint">Sau khi upload, hình ảnh hoặc video sẽ được chèn ngay vào vị trí nội dung hiện tại. Với video ngoài hệ thống, anh vẫn có thể dán URL nếu toolbar media hỗ trợ nguồn đó.</div>
            </Form>
        </Drawer>
    );
}
