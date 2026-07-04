import { useEffect, useMemo, useRef, useState } from 'react';
import { CKEditor } from '@ckeditor/ckeditor5-react';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
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

function normalizeProjectImages(value) {
    if (!Array.isArray(value)) {
        return [];
    }

    return value
        .filter((image) => image?.image_url)
        .map((image, index) => ({
            cms_media_id: image.cms_media_id ?? null,
            image_url: String(image.image_url ?? '').trim(),
            alt_text: image.alt_text ?? '',
            caption: image.caption ?? '',
            is_featured: Boolean(image.is_featured),
            sort_order: Number(image.sort_order ?? index),
        }))
        .filter((image) => image.image_url);
}

function FormValueBridge() {
    return null;
}

export default function CmsProjectFormModal({ open, canManage, editingProject, mediaOptions = [], callAdminApi, onCancel, onSubmit }) {
    const [form] = Form.useForm();
    const slugEditedRef = useRef(Boolean(editingProject?.id));
    const editorInstanceRef = useRef(null);
    const [contentMode, setContentMode] = useState('editor');
    const [editorContentVersion, setEditorContentVersion] = useState(0);
    const titleValue = Form.useWatch('title', form) ?? '';
    const contentValue = Form.useWatch('content', form) ?? '';
    const projectImagesValue = Form.useWatch('images', form) ?? [];
    const editorInitialData = useMemo(
        () => form.getFieldValue('content') ?? editingProject?.content ?? '',
        [editingProject?.id, editingProject?.slug, editingProject?.content, editorContentVersion, form]
    );
    const editorInstanceKey = useMemo(
        () => `${editingProject?.id ?? 'new'}:${editingProject?.slug ?? 'blank'}:${open ? 'open' : 'closed'}:${contentMode}:${editorContentVersion}`,
        [editingProject?.id, editingProject?.slug, open, contentMode, editorContentVersion]
    );

    useEffect(() => {
        form.setFieldsValue({
            ...editingProject,
            content: editingProject?.content ?? '',
            images: editingProject?.images?.length ? editingProject.images : [],
        });
        slugEditedRef.current = Boolean(editingProject?.id || editingProject?.slug);
        setContentMode('editor');
        setEditorContentVersion((current) => current + 1);
        editorInstanceRef.current = null;
    }, [editingProject, form]);

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
                { name: 'figure', classes: true, attributes: true, styles: true },
                { name: 'img', classes: true, attributes: true, styles: true },
                { name: 'div', classes: true, attributes: true, styles: true },
                { name: 'iframe', classes: true, attributes: true, styles: true },
                { name: 'table', classes: true, attributes: true, styles: true },
            ],
        },
    }), []);

    const projectImageUrls = useMemo(
        () => normalizeProjectImages(projectImagesValue).map((image) => image.image_url),
        [projectImagesValue]
    );
    const featuredProjectImageUrl = useMemo(() => {
        const images = normalizeProjectImages(projectImagesValue);
        const featuredImage = images.find((image) => image.is_featured) ?? images[0] ?? null;

        return featuredImage?.image_url ?? '';
    }, [projectImagesValue]);

    const syncProjectImages = (nextValue, nextCover = null) => {
        const currentImages = normalizeProjectImages(form.getFieldValue('images') ?? []);
        const existingByUrl = new Map(currentImages.map((image) => [image.image_url, image]));
        const normalizedUrls = Array.from(new Set((Array.isArray(nextValue) ? nextValue : [])
            .map((item) => String(item ?? '').trim())
            .filter(Boolean)));
        const selectedCover = nextCover && normalizedUrls.includes(nextCover)
            ? nextCover
            : normalizedUrls.includes(featuredProjectImageUrl)
                ? featuredProjectImageUrl
                : normalizedUrls[0] ?? '';
        const orderedUrls = selectedCover
            ? [selectedCover, ...normalizedUrls.filter((item) => item !== selectedCover)]
            : normalizedUrls;

        form.setFieldValue('images', orderedUrls.map((imageUrl, index) => {
            const existingImage = existingByUrl.get(imageUrl) ?? {};

            return {
                ...existingImage,
                image_url: imageUrl,
                alt_text: existingImage.alt_text ?? titleValue,
                caption: existingImage.caption ?? '',
                is_featured: imageUrl === selectedCover,
                sort_order: index,
            };
        }));
    };

    const setProjectCoverImage = (nextCover) => {
        syncProjectImages(projectImageUrls, nextCover);
    };

    const handleSlugChange = (event) => {
        slugEditedRef.current = true;
        form.setFieldValue('slug', toSlug(event.target.value));
    };

    const syncCurrentEditorBodyToForm = () => {
        const editor = editorInstanceRef.current;

        if (contentMode === 'editor' && editor) {
            form.setFieldValue('content', editor.getData());
        }
    };

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

        await onSubmit?.({
            ...values,
            summary: values.summary || null,
            content: values.content || null,
            button_label: values.button_label || null,
            link_url: values.link_url || null,
            meta_title: values.meta_title || null,
            meta_description: values.meta_description || null,
            sort_order: Number(values.sort_order ?? 0),
            is_featured: Boolean(values.is_featured),
            is_highlight: Boolean(values.is_highlight),
            publish_at: values.status === 'published' ? (values.publish_at || dayjs().format('YYYY-MM-DDTHH:mm:ss')) : null,
            images: normalizeProjectImages(values.images).map((image, index, images) => ({
                ...image,
                is_featured: images.some((item) => item.is_featured) ? image.is_featured : index === 0,
                sort_order: index,
            })),
        });

        form.resetFields();
    };

    const handleCancel = () => {
        form.resetFields();
        onCancel?.();
    };

    return (
        <Drawer
            title={editingProject?.id ? 'Cập nhật dự án CMS' : 'Tạo dự án CMS'}
            open={open}
            onClose={handleCancel}
            width={960}
            destroyOnHidden
            className="cms-page-drawer"
            extra={(
                <Space>
                    <Button onClick={handleCancel}>Hủy</Button>
                    <Button type="primary" disabled={!canManage} onClick={handleSubmit}>Lưu dự án</Button>
                </Space>
            )}
        >
            <Form form={form} layout="vertical" initialValues={editingProject}>
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Card size="small" title="Thông tin dự án">
                        <Row gutter={16}>
                            <Col xs={24} md={14}>
                                <Form.Item name="title" label="Tiêu đề" rules={[{ required: true, message: 'Nhập tiêu đề dự án' }]}>
                                    <Input placeholder="Công trình biệt thự nhà vườn" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={10}>
                                <Form.Item name="slug" label="Slug" rules={[{ required: true, message: 'Nhập slug dự án' }]}>
                                    <Input placeholder="cong-trinh-biet-thu-nha-vuon" onChange={handleSlugChange} />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Row gutter={16}>
                            <Col xs={24} md={8}>
                                <Form.Item name="status" label="Trạng thái" rules={[{ required: true, message: 'Chọn trạng thái' }]}>
                                    <Select options={[{ label: 'Bản nháp', value: 'draft' }, { label: 'Đã xuất bản', value: 'published' }]} />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="sort_order" label="Thứ tự">
                                    <InputNumber min={0} style={{ width: '100%' }} />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="is_featured" label="Dự án nổi bật" valuePropName="checked">
                                    <Switch />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="is_highlight" label="Đánh dấu highlight" valuePropName="checked">
                                    <Switch />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Row gutter={16}>
                            <Col xs={24} md={12}>
                                <Form.Item name="button_label" label="Nhãn nút">
                                    <Input placeholder="Xem chi tiết" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item name="link_url" label="Link click">
                                    <Input placeholder="/vi/lien-he hoặc https://..." />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Form.Item name="summary" label="Mô tả ngắn">
                            <Input.TextArea rows={3} placeholder="Mô tả hiển thị trên card dự án." />
                        </Form.Item>
                    </Card>

                    <Card size="small" className="cms-post-form-card cms-post-form-card-editor" title="Nội dung chi tiết">
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
                                    placeholder="<section>Nhập mã HTML chi tiết dự án...</section>"
                                    onChange={(event) => form.setFieldValue('content', event.target.value)}
                                />
                            )}
                        </Form.Item>
                        <Form.Item name="content" hidden>
                            <Input.TextArea />
                        </Form.Item>
                    </Card>
                    <Card size="small" title="Gallery ảnh dự án">
                        <Form.Item name="images" hidden>
                            <FormValueBridge />
                        </Form.Item>
                        <Form.Item label="Danh sách hình ảnh" style={{ marginBottom: 0 }}>
                            <MultiMediaPicker
                                open={open}
                                value={projectImageUrls}
                                onChange={(nextValue) => syncProjectImages(nextValue)}
                                coverValue={featuredProjectImageUrl}
                                onSetCover={setProjectCoverImage}
                                canManage={canManage}
                                callAdminApi={callAdminApi}
                                mediaOptions={mediaOptions}
                                recordTitle={titleValue || 'Project images'}
                                previewTitle="Ảnh dự án"
                                uploadButtonLabel="Upload ảnh dự án"
                                uploadHint="Có thể upload nhiều ảnh. Ảnh đầu tiên sẽ tự làm ảnh đại diện."
                                libraryModalTitle="Chọn ảnh dự án từ thư viện"
                                urlPlaceholder={['https://cdn.example.com/project-1.jpg', 'https://cdn.example.com/project-2.jpg'].join('\n')}
                                uploadSuccessMessage="Đã thêm ảnh dự án."
                                urlSuccessMessage="Đã lưu URL vào thư viện media và thêm ảnh dự án."
                                uploadErrorMessage="Upload ảnh dự án không thành công."
                                urlErrorMessage="Không thể lưu ảnh dự án từ URL."
                                emptyValueMessage="Nhập ít nhất một URL ảnh trước khi lưu."
                            />
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
                                    <Input.TextArea rows={3} placeholder="Meta description dự án" />
                                </Form.Item>
                            </Col>
                        </Row>
                    </Card>
                </Space>
            </Form>
        </Drawer>
    );
}
