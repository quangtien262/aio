import { useEffect, useMemo, useRef, useState } from 'react';
import { CKEditor } from '@ckeditor/ckeditor5-react';
import { PictureOutlined, VideoCameraOutlined, YoutubeOutlined } from '@ant-design/icons';
import Button from 'antd/es/button';
import Input from 'antd/es/input';
import message from 'antd/es/message';
import Modal from 'antd/es/modal';
import Radio from 'antd/es/radio';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';
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
import { adminApi } from '../config/routes';

const { Text } = Typography;
const { TextArea } = Input;

function getYoutubeEmbedUrl(value) {
    try {
        const url = new URL(String(value ?? '').trim());
        let videoId = '';

        if (url.hostname.includes('youtu.be')) {
            videoId = url.pathname.split('/').filter(Boolean)[0] ?? '';
        } else {
            videoId = url.searchParams.get('v') ?? url.pathname.split('/').filter(Boolean).pop() ?? '';
        }

        const safeVideoId = videoId.replace(/[^a-zA-Z0-9_-]/g, '');

        return safeVideoId ? `https://www.youtube.com/embed/${safeVideoId}` : null;
    } catch {
        return null;
    }
}

export default function RichContentEditor({
    value = '',
    onChange,
    disabled = false,
    callAdminApi,
    recordKey = 'new',
    open = true,
    htmlPlaceholder = '<section>Nhập mã HTML...</section>',
    hint = 'Ảnh và video được lưu vào thư viện CMS rồi chèn tại vị trí con trỏ.',
    showVideo = true,
    showYoutube = true,
    minHeight = 300,
    extraActions = null,
}) {
    const [messageApi, messageContextHolder] = message.useMessage();
    const [contentMode, setContentMode] = useState('editor');
    const [editorVersion, setEditorVersion] = useState(0);
    const [uploadingAsset, setUploadingAsset] = useState(null);
    const [youtubeOpen, setYoutubeOpen] = useState(false);
    const [youtubeUrl, setYoutubeUrl] = useState('');
    const editorInstanceRef = useRef(null);
    const editorSelectionRef = useRef(null);
    const imageInputRef = useRef(null);
    const videoInputRef = useRef(null);
    const initialData = useMemo(() => value ?? '', [recordKey, open, contentMode, editorVersion]);

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
            allow: [{ name: /.*/, attributes: true, classes: true, styles: true }],
        },
    }), []);

    useEffect(() => {
        setContentMode('editor');
        setEditorVersion((current) => current + 1);
        setYoutubeOpen(false);
        setYoutubeUrl('');
        editorInstanceRef.current = null;
        editorSelectionRef.current = null;
    }, [recordKey, open]);

    const captureSelection = (editor) => {
        const range = editor?.model?.document?.selection?.getFirstRange?.();

        editorSelectionRef.current = range ? range.clone() : null;
    };

    const insertHtml = (html) => {
        const editor = editorInstanceRef.current;

        if (!editor) {
            onChange?.(`${value || ''}${html}`);
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

        captureSelection(editor);
        onChange?.(editor.getData());
        editor.editing.view.focus();
    };

    const uploadCmsMedia = async (file, typeLabel) => {
        if (!callAdminApi) {
            throw new Error('Thiếu cấu hình thư viện CMS.');
        }

        const formData = new FormData();
        formData.append('file', file);
        formData.append('title', file.name.replace(/\.[^.]+$/, '') || typeLabel);

        const payload = await callAdminApi(adminApi('cms/media'), {
            method: 'POST',
            body: formData,
        });
        const fileUrl = payload?.data?.file_url;

        if (!fileUrl) {
            throw new Error(`Upload ${typeLabel} vào CMS không thành công.`);
        }

        return fileUrl;
    };

    const handleUpload = async (event, assetType) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        setUploadingAsset(assetType);

        try {
            const fileUrl = await uploadCmsMedia(file, assetType === 'image' ? 'ảnh' : 'video');
            const html = assetType === 'image'
                ? `<figure class="image"><img src="${fileUrl}" alt="${file.name}" /></figure>`
                : `<figure class="cms-inline-video"><video controls style="max-width:100%;height:auto;" src="${fileUrl}"></video></figure>`;

            insertHtml(html);
            messageApi.success(`Đã chèn ${assetType === 'image' ? 'ảnh' : 'video'} "${file.name}" vào nội dung.`);
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : 'Không thể upload media vào nội dung.');
        } finally {
            setUploadingAsset(null);
            event.target.value = '';
        }
    };

    const handleModeChange = (event) => {
        const nextMode = event.target.value;

        if (nextMode === contentMode) {
            return;
        }

        if (contentMode === 'editor' && editorInstanceRef.current) {
            onChange?.(editorInstanceRef.current.getData());
        }

        editorInstanceRef.current = null;
        editorSelectionRef.current = null;
        setContentMode(nextMode);
        setEditorVersion((current) => current + 1);
    };

    const handleYoutubeInsert = () => {
        const embedUrl = getYoutubeEmbedUrl(youtubeUrl);

        if (!embedUrl) {
            messageApi.warning('Nhập đúng đường dẫn YouTube trước khi nhúng.');
            return;
        }

        insertHtml(`<div class="cms-inline-video cms-inline-youtube" style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:16px;"><iframe src="${embedUrl}" title="YouTube video player" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;"></iframe></div>`);
        setYoutubeOpen(false);
        setYoutubeUrl('');
        messageApi.success('Đã nhúng video YouTube vào nội dung.');
    };

    return (
        <>
            {messageContextHolder}
            <div className="cms-editor-upload-panel shared-rich-editor-toolbar">
                <Space size={[10, 10]} wrap>
                    <Radio.Group
                        value={contentMode}
                        onChange={handleModeChange}
                        optionType="button"
                        buttonStyle="solid"
                        disabled={disabled}
                        options={[
                            { label: 'Trình soạn thảo', value: 'editor' },
                            { label: 'Nhập mã HTML', value: 'html' },
                        ]}
                    />
                    <input ref={imageInputRef} type="file" accept="image/*" hidden onChange={(event) => handleUpload(event, 'image')} />
                    <Button
                        icon={<PictureOutlined />}
                        loading={uploadingAsset === 'image'}
                        disabled={disabled || contentMode !== 'editor' || uploadingAsset === 'video'}
                        onClick={() => {
                            if (editorInstanceRef.current) {
                                captureSelection(editorInstanceRef.current);
                            }
                            imageInputRef.current?.click();
                        }}
                    >
                        Chèn ảnh
                    </Button>
                    {showVideo ? (
                        <>
                            <input ref={videoInputRef} type="file" accept="video/*" hidden onChange={(event) => handleUpload(event, 'video')} />
                            <Button
                                icon={<VideoCameraOutlined />}
                                loading={uploadingAsset === 'video'}
                                disabled={disabled || contentMode !== 'editor' || uploadingAsset === 'image'}
                                onClick={() => {
                                    if (editorInstanceRef.current) {
                                        captureSelection(editorInstanceRef.current);
                                    }
                                    videoInputRef.current?.click();
                                }}
                            >
                                Chèn video
                            </Button>
                        </>
                    ) : null}
                    {showYoutube ? (
                        <Button
                            icon={<YoutubeOutlined />}
                            disabled={disabled || contentMode !== 'editor' || Boolean(uploadingAsset)}
                            onClick={() => {
                                if (editorInstanceRef.current) {
                                    captureSelection(editorInstanceRef.current);
                                }
                                setYoutubeOpen(true);
                            }}
                        >
                            Nhúng YouTube
                        </Button>
                    ) : null}
                    {extraActions}
                </Space>
                {hint ? <Text type="secondary">{hint}</Text> : null}
            </div>

            {contentMode === 'editor' ? (
                <div className="cms-editor-shell shared-rich-editor" style={{ '--shared-editor-min-height': `${minHeight}px` }}>
                    <CKEditor
                        key={`${recordKey}:${open ? 'open' : 'closed'}:${editorVersion}`}
                        editor={ClassicEditor}
                        config={editorConfig}
                        data={initialData}
                        disabled={disabled}
                        onReady={(editor) => {
                            editorInstanceRef.current = editor;
                            captureSelection(editor);
                            editor.model.document.selection.on('change:range', () => captureSelection(editor));
                        }}
                        onChange={(_, editor) => {
                            captureSelection(editor);
                            onChange?.(editor.getData());
                        }}
                    />
                </div>
            ) : (
                <TextArea
                    rows={18}
                    className="cms-html-code-input"
                    value={value}
                    disabled={disabled}
                    placeholder={htmlPlaceholder}
                    onChange={(event) => onChange?.(event.target.value)}
                />
            )}

            <Modal
                title="Nhúng video YouTube"
                open={youtubeOpen}
                onCancel={() => setYoutubeOpen(false)}
                onOk={handleYoutubeInsert}
                okText="Chèn video"
                cancelText="Hủy"
                destroyOnHidden
            >
                <Space direction="vertical" size={8} style={{ width: '100%' }}>
                    <Text>Dán đường dẫn YouTube cần nhúng vào nội dung.</Text>
                    <Input value={youtubeUrl} onChange={(event) => setYoutubeUrl(event.target.value)} placeholder="https://www.youtube.com/watch?v=..." />
                </Space>
            </Modal>
        </>
    );
}
