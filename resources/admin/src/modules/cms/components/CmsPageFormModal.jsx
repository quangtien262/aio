import { adminApi } from '../../../shared/config/routes';
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
import AntList from 'antd/es/list';
import Pagination from 'antd/es/pagination';
import Radio from 'antd/es/radio';
import Row from 'antd/es/row';
import Space from 'antd/es/space';
import Tooltip from 'antd/es/tooltip';
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

const { Text } = Typography;

export const emptyCmsPageForm = {
    id: null,
    title: '',
    slug: '',
    status: 'draft',
    excerpt: '',
    body: '',
    meta_title: '',
    meta_description: '',
    meta_keywords: '',
    featured_media_id: null,
    website_key: '',
};

function toSlug(value, { trimEdges = true } = {}) {
    const slug = String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'd')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-');

    return trimEdges ? slug.replace(/^-+|-+$/g, '') : slug.replace(/^-+/g, '');
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
    const [featuredMediaMode, setFeaturedMediaMode] = useState('upload');
    const [featuredMediaLibraryOpen, setFeaturedMediaLibraryOpen] = useState(false);
    const [featuredMediaLibraryPage, setFeaturedMediaLibraryPage] = useState(1);
    const [featuredMediaKeyword, setFeaturedMediaKeyword] = useState('');
    const [featuredMediaUrl, setFeaturedMediaUrl] = useState('');
    const [featuredMediaOptions, setFeaturedMediaOptions] = useState(mediaOptions);
    const [youtubeEmbedOpen, setYoutubeEmbedOpen] = useState(false);
    const [youtubeUrl, setYoutubeUrl] = useState('');
    const [sampleModalOpen, setSampleModalOpen] = useState(false);
    const [contentMode, setContentMode] = useState('editor');
    const [editorContentVersion, setEditorContentVersion] = useState(0);
    const editorInstanceRef = useRef(null);
    const editorSelectionRef = useRef(null);
    const imageInputRef = useRef(null);
    const videoInputRef = useRef(null);
    const sampleImageInputRef = useRef(null);
    const featuredMediaInputRef = useRef(null);
    const slugEditedRef = useRef(Boolean(editingPage?.id));
    const titleValue = Form.useWatch('title', form) ?? '';
    const featuredMediaId = Form.useWatch('featured_media_id', form) ?? null;
    const bodyValue = Form.useWatch('body', form) ?? '';
    const websiteKey = Form.useWatch('website_key', form);
    const editorInitialData = useMemo(
        () => form.getFieldValue('body') ?? editingPage?.body ?? '',
        [editingPage?.id, editingPage?.slug, editingPage?.body, editorContentVersion, form]
    );
    const editorInstanceKey = useMemo(
        () => `${editingPage?.id ?? 'new'}:${editingPage?.slug ?? 'blank'}:${open ? 'open' : 'closed'}:${contentMode}:${editorContentVersion}`,
        [editingPage?.id, editingPage?.slug, open, contentMode, editorContentVersion]
    );

    useEffect(() => {
        form.setFieldsValue(editingPage);
        form.setFieldValue('body', editingPage?.body ?? '');
        slugEditedRef.current = Boolean(editingPage?.id || editingPage?.slug);
        setContentMode('editor');
        setEditorContentVersion((current) => current + 1);
        editorInstanceRef.current = null;
        editorSelectionRef.current = null;
        setFeaturedMediaMode(editingPage?.featured_media_id ? 'library' : 'upload');
        setFeaturedMediaUrl('');
        setFeaturedMediaKeyword('');
        setFeaturedMediaLibraryPage(1);
        setFeaturedMediaLibraryOpen(false);
    }, [editingPage, form]);

    useEffect(() => {
        setFeaturedMediaOptions((currentOptions) => {
            const nextMap = new Map(currentOptions.map((item) => [item.id, item]));

            mediaOptions.forEach((item) => nextMap.set(item.id, item));

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
            ],
        },
    }), []);

    const SAMPLE_PRESETS = [
        {
            id: 'landing-intro',
            title: 'Landing page giới thiệu',
            description: 'Bố cục landing hai cột: ảnh/video bên trái, nội dung nổi bật bên phải.',
            html: '<section style="display:flex;gap:24px;align-items:start;flex-wrap:wrap"><div style="flex:1;min-width:300px"><div style="position:relative;border-radius:12px;overflow:hidden;background:linear-gradient(135deg,#f3f7f6,#e6f3f2);height:100%;min-height:260px;display:flex;align-items:center;justify-content:center;border:1px solid #eef2f2"><div style="width:76px;height:76px;border-radius:50%;background:#ff9f1c;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(0,0,0,0.12)"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 5v14l11-7L8 5z" fill="#fff"/></svg></div></div></div><div style="flex:1;min-width:320px"><div style="color:#06b6d4;font-weight:700;letter-spacing:1.6px;font-size:12px;margin-bottom:8px">CHÚNG TÔI LÀ AI</div><h2 style="margin:0 0 12px;font-size:28px;line-height:1.15">Cam kết chất lượng và kết quả vượt trội</h2><p style="color:#374151;margin:0 0 16px">Chúng tôi là những người giải quyết vấn đề. Chúng tôi cam kết mang đến cho khách hàng sản phẩm và dịch vụ tốt nhất, đáp ứng và vượt qua mong đợi của họ bằng sự tập trung, chuyên môn và trách nhiệm.</p><div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:8px"><div style="flex:1;min-width:140px;padding:12px;border:1px solid #eef2f2;border-radius:8px;background:#fff"><div style="font-weight:700;margin-bottom:6px">Đảm bảo chất lượng</div><div style="color:#6b7280;font-size:13px">Quy trình kiểm soát chất lượng chặt chẽ cho mọi dự án.</div></div><div style="flex:1;min-width:140px;padding:12px;border:1px solid #eef2f2;border-radius:8px;background:#fff"><div style="font-weight:700;margin-bottom:6px">Trách nhiệm xã hội</div><div style="color:#6b7280;font-size:13px">Cam kết phát triển bền vững và hỗ trợ cộng đồng.</div></div></div></div></section>'
        },
        {
            id: 'features',
            title: '3 Features',
            description: 'Ba cột tính năng với icon và mô tả ngắn.',
            html: '<div class="sample-features" style="display:flex;gap:16px"><div style="flex:1;padding:12px;border:1px solid #eef2f2;border-radius:8px"><h3>Feature A</h3><p>Miêu tả ngắn về tính năng A.</p></div><div style="flex:1;padding:12px;border:1px solid #eef2f2;border-radius:8px"><h3>Feature B</h3><p>Miêu tả ngắn về tính năng B.</p></div><div style="flex:1;padding:12px;border:1px solid #eef2f2;border-radius:8px"><h3>Feature C</h3><p>Miêu tả ngắn về tính năng C.</p></div></div>'
        },
        {
            id: 'faq',
            title: 'FAQ (Accordion)',
            description: 'Danh sách các câu hỏi thường gặp dạng list để copy nhanh.',
            html: '<section class="sample-faq"><h2>FAQ</h2><dl><dt><strong>Hỏi: Làm sao để đăng ký?</strong></dt><dd>Trả lời: Bạn chỉ cần điền thông tin và bấm nút Đăng ký.</dd><dt><strong>Hỏi: Thời gian giao hàng?</strong></dt><dd>Trả lời: Thông thường 3-5 ngày làm việc.</dd></dl></section>'
        }
    ];

    const buildPresetHtml = (item) => {
        if (!item) return item?.html ?? '';

        if (item.id === 'landing-intro') {
            const svg = `<?xml version="1.0" encoding="UTF-8"?><svg xmlns='http://www.w3.org/2000/svg' width='1200' height='800' viewBox='0 0 1200 800' preserveAspectRatio='xMidYMid slice'><defs><linearGradient id='g' x1='0' y1='0' x2='1' y2='1'><stop offset='0' stop-color='%23eef2ff'/><stop offset='1' stop-color='%23f8fbff'/></linearGradient><radialGradient id='sun' cx='30%25' cy='20%25' r='40%25'><stop offset='0' stop-color='%23ffd27d'/><stop offset='1' stop-color='%23ff9f1c'/></radialGradient></defs><rect width='100%25' height='100%25' rx='14' fill='url(%23g)'/><g transform='translate(0 120)'><path d='M0 560 C200 420 400 420 600 560 C800 700 1000 700 1200 560 L1200 800 L0 800 Z' fill='%23dff6f0'/><path d='M0 480 C220 340 420 360 600 480 C780 600 1000 620 1200 480 L1200 800 L0 800 Z' fill='%23bfece4'/></g><g transform='translate(80 40)'><circle cx='320' cy='120' r='56' fill='url(%23sun)' opacity='0.95'/><g transform='translate(180 260)'><rect x='0' y='0' width='680' height='420' rx='12' fill='%23ffffff' stroke='%23e6eef0' stroke-width='2' /><rect x='20' y='20' width='320' height='380' rx='8' fill='%23f3f7f6' /><rect x='360' y='20' width='300' height='120' rx='6' fill='%23fff4e6' /><rect x='360' y='150' width='300' height='90' rx='6' fill='%23fff' /><rect x='360' y='255' width='300' height='145' rx='6' fill='%23fff' /></g></g><g transform='translate(460 380)'><circle r='36' fill='%23ff9f1c' /><path d='M-8 -14 L18 0 L-8 14 z' fill='%23fff' transform='translate(6 0)'/></g></svg>`;

            const imgSrc = `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`;

            return (`<section style="display:flex;gap:24px;align-items:start;flex-wrap:wrap"><div style="flex:1;min-width:300px"><div style="position:relative;border-radius:12px;overflow:hidden;height:100%;min-height:260px;display:flex;align-items:center;justify-content:center;border:1px solid #eef2f2"><img src="${imgSrc}" alt="hero" style="width:100%;height:100%;object-fit:cover;display:block"/><div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:76px;height:76px;border-radius:50%;background:#ff9f1c;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(0,0,0,0.12)"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 5v14l11-7L8 5z" fill="#fff"/></svg></div></div></div><div style="flex:1;min-width:320px"><div style="color:#06b6d4;font-weight:700;letter-spacing:1.6px;font-size:12px;margin-bottom:8px">CHÚNG TÔI LÀ AI</div><h2 style="margin:0 0 12px;font-size:28px;line-height:1.15">Cam kết chất lượng và kết quả vượt trội</h2><p style="color:#374151;margin:0 0 16px">Chúng tôi là những người giải quyết vấn đề. Chúng tôi cam kết mang đến cho khách hàng sản phẩm và dịch vụ tốt nhất, đáp ứng và vượt qua mong đợi của họ bằng sự tập trung, chuyên môn và trách nhiệm.</p><div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:8px"><div style="flex:1;min-width:140px;padding:12px;border:1px solid #eef2f2;border-radius:8px;background:#fff"><div style="font-weight:700;margin-bottom:6px">Đảm bảo chất lượng</div><div style="color:#6b7280;font-size:13px">Quy trình kiểm soát chất lượng chặt chẽ cho mọi dự án.</div></div><div style="flex:1;min-width:140px;padding:12px;border:1px solid #eef2f2;border-radius:8px;background:#fff"><div style="font-weight:700;margin-bottom:6px">Trách nhiệm xã hội</div><div style="color:#6b7280;font-size:13px">Cam kết phát triển bền vững và hỗ trợ cộng đồng.</div></div></div></div></section>`);
        }

        return item.html ?? '';
    };

    const buildPresetHtmlWithOverride = (item, overrideImageSrc = null) => {
        if (!item) return item?.html ?? '';

        if (item.id === 'landing-intro') {
            const svg = `<?xml version="1.0" encoding="UTF-8"?><svg xmlns='http://www.w3.org/2000/svg' width='1200' height='800' viewBox='0 0 1200 800' preserveAspectRatio='xMidYMid slice'><defs><linearGradient id='g' x1='0' y1='0' x2='1' y2='1'><stop offset='0' stop-color='%23eef2ff'/><stop offset='1' stop-color='%23f8fbff'/></linearGradient><radialGradient id='sun' cx='30%25' cy='20%25' r='40%25'><stop offset='0' stop-color='%23ffd27d'/><stop offset='1' stop-color='%23ff9f1c'/></radialGradient></defs><rect width='100%25' height='100%25' rx='14' fill='url(%23g)'/><g transform='translate(0 120)'><path d='M0 560 C200 420 400 420 600 560 C800 700 1000 700 1200 560 L1200 800 L0 800 Z' fill='%23dff6f0'/><path d='M0 480 C220 340 420 360 600 480 C780 600 1000 620 1200 480 L1200 800 L0 800 Z' fill='%23bfece4'/></g><g transform='translate(80 40)'><circle cx='320' cy='120' r='56' fill='url(%23sun)' opacity='0.95'/><g transform='translate(180 260)'><rect x='0' y='0' width='680' height='420' rx='12' fill='%23ffffff' stroke='%23e6eef0' stroke-width='2' /><rect x='20' y='20' width='320' height='380' rx='8' fill='%23f3f7f6' /><rect x='360' y='20' width='300' height='120' rx='6' fill='%23fff4e6' /><rect x='360' y='150' width='300' height='90' rx='6' fill='%23fff' /><rect x='360' y='255' width='300' height='145' rx='6' fill='%23fff' /></g></g><g transform='translate(460 380)'><circle r='36' fill='%23ff9f1c' /><path d='M-8 -14 L18 0 L-8 14 z' fill='%23fff' transform='translate(6 0)'/></g></svg>`;

            const defaultImgSrc = `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`;
            const imgSrc = overrideImageSrc ?? defaultImgSrc;

            return (`<section style="display:flex;gap:24px;align-items:start;flex-wrap:wrap"><div style="flex:1;min-width:300px"><div style="position:relative;border-radius:12px;overflow:hidden;height:100%;min-height:260px;display:flex;align-items:center;justify-content:center;border:1px solid #eef2f2"><img src="${imgSrc}" alt="hero" style="width:100%;height:100%;object-fit:cover;display:block"/><div style="position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:76px;height:76px;border-radius:50%;background:#ff9f1c;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(0,0,0,0.12)"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 5v14l11-7L8 5z" fill="#fff"/></svg></div></div></div><div style="flex:1;min-width:320px"><div style="color:#06b6d4;font-weight:700;letter-spacing:1.6px;font-size:12px;margin-bottom:8px">CHÚNG TÔI LÀ AI</div><h2 style="margin:0 0 12px;font-size:28px;line-height:1.15">Cam kết chất lượng và kết quả vượt trội</h2><p style="color:#374151;margin:0 0 16px">Chúng tôi là những người giải quyết vấn đề. Chúng tôi cam kết mang đến cho khách hàng sản phẩm và dịch vụ tốt nhất, đáp ứng và vượt qua mong đợi của họ bằng sự tập trung, chuyên môn và trách nhiệm.</p><div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:8px"><div style="flex:1;min-width:140px;padding:12px;border:1px solid #eef2f2;border-radius:8px;background:#fff"><div style="font-weight:700;margin-bottom:6px">Đảm bảo chất lượng</div><div style="color:#6b7280;font-size:13px">Quy trình kiểm soát chất lượng chặt chẽ cho mọi dự án.</div></div><div style="flex:1;min-width:140px;padding:12px;border:1px solid #eef2f2;border-radius:8px;background:#fff"><div style="font-weight:700;margin-bottom:6px">Trách nhiệm xã hội</div><div style="color:#6b7280;font-size:13px">Cam kết phát triển bền vững và hỗ trợ cộng đồng.</div></div></div></div></section>`);
        }

        return item.html ?? '';
    };

    const [pendingPresetToInsert, setPendingPresetToInsert] = useState(null);

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

    const handleSampleImageSelected = (event) => {
        const file = event?.target?.files?.[0];

        if (!file || !pendingPresetToInsert) {
            if (sampleImageInputRef.current) sampleImageInputRef.current.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = () => {
            const dataUrl = reader.result;
            const html = buildPresetHtmlWithOverride(pendingPresetToInsert, dataUrl);
            insertHtmlIntoEditor(html);
            setSampleModalOpen(false);
            messageApi.success('Đã chèn nội dung mẫu vào editor với ảnh đã chọn.');
            setPendingPresetToInsert(null);
            if (sampleImageInputRef.current) sampleImageInputRef.current.value = '';
        };
        reader.readAsDataURL(file);
    };

    const uploadCmsMedia = async (file, typeLabel) => {
        const formData = new FormData();

        formData.append('file', file);
        formData.append('title', file.name.replace(/\.[^.]+$/, '') || typeLabel);

        [
            ['website_key', websiteKey || null],
        ].forEach(([key, value]) => {
            if (value) {
                formData.append(key, value);
            }
        });

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

    const createFeaturedMediaRecord = async ({ file, fileUrl, title }) => {
        const formData = new FormData();

        if (file) formData.append('file', file);
        if (fileUrl) formData.append('file_url', fileUrl);
        if (title) formData.append('title', title);
        if (websiteKey) formData.append('website_key', websiteKey);

        const payload = await callAdminApi(adminApi('cms/media'), {
            method: 'POST',
            body: formData,
        });

        if (!payload?.data?.id) {
            throw new Error('Không thể tạo media đại diện trang.');
        }

        return payload.data;
    };

    const syncEditorBodyToForm = (editor) => {
        form.setFieldValue('body', editor.getData());
    };

    const syncCurrentEditorBodyToForm = () => {
        const editor = editorInstanceRef.current;

        if (contentMode === 'editor' && editor) {
            form.setFieldValue('body', editor.getData());
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

    const handleUploadFeaturedMedia = async (event) => {
        const file = event.target.files?.[0];

        if (!file) return;

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
                title: form.getFieldValue('title') || 'Ảnh đại diện trang',
            });

            setFeaturedMediaOptions((currentOptions) => [media, ...currentOptions.filter((item) => item.id !== media.id)]);
            form.setFieldValue('featured_media_id', media.id);
            messageApi.success('Đã lưu URL và gán làm ảnh đại diện trang.');
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : 'Không thể lưu ảnh đại diện từ URL.');
        } finally {
            setUploadingAsset(null);
        }
    };

    const renderFeaturedMediaPreview = () => {
        if (!selectedFeaturedMedia?.file_url) return null;

        return (
            <div className="cms-featured-media-preview">
                <img src={selectedFeaturedMedia.file_url} alt={selectedFeaturedMedia.title || 'Ảnh đại diện trang'} />
                <div className="cms-featured-media-preview-copy">
                    <strong>{selectedFeaturedMedia.title || 'Ảnh đại diện trang'}</strong>
                    <span>{selectedFeaturedMedia.file_url}</span>
                </div>
                <Button size="small" onClick={() => form.setFieldValue('featured_media_id', null)}>Bỏ chọn</Button>
            </div>
        );
    };

    const handleSubmit = async () => {
        syncCurrentEditorBodyToForm();

        const values = await form.validateFields();

        const didSave = await onSubmit?.({
            ...values,
            excerpt: values.excerpt || null,
            body: values.body || null,
            meta_title: values.meta_title || null,
            meta_description: values.meta_description || null,
            meta_keywords: values.meta_keywords || null,
            featured_media_id: values.featured_media_id || null,
        });

        if (didSave !== false) {
            form.resetFields();
        }
    };

    const handleCancel = () => {
        form.resetFields();
        onCancel?.();
    };

    const handleSlugChange = (event) => {
        slugEditedRef.current = true;
        form.setFieldValue('slug', toSlug(event.target.value, { trimEdges: false }));
    };

    return (
        <Drawer
            title={editingPage?.id ? 'Cập nhật trang CMS' : 'Tạo trang CMS'}
            open={open}
            onClose={handleCancel}
            width="min(1280px, 90vw)"
            destroyOnHidden
            className="cms-page-drawer cms-page-form-drawer"
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
                                    <Input
                                        placeholder="VD: Trang giới thiệu"
                                        onChange={(event) => {
                                            if (!editingPage?.id && !slugEditedRef.current) {
                                                form.setFieldValue('slug', toSlug(event.target.value));
                                            }
                                        }}
                                    />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item name="slug" label="Slug" rules={[{ required: true, message: 'Nhập slug' }]}>
                                    <Input placeholder="trang-gioi-thieu" onChange={handleSlugChange} />
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
                            <Col xs={24}>
                                <Form.Item name="excerpt" label="Mô tả ngắn" style={{ marginBottom: 0 }}>
                                    <Input.TextArea rows={3} placeholder="Tóm tắt ngắn dùng cho hero/SEO/listing" />
                                </Form.Item>
                            </Col>
                        </Row>
                    </Card>

                    <Card size="small" className="cms-post-form-card" title="Ảnh đại diện bài viết">
                        <Form.Item name="featured_media_id" style={{ marginBottom: 0 }}>
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
                                                <Button type="primary" onClick={() => setFeaturedMediaLibraryOpen(true)}>Mở thư viện media</Button>
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
                            <Col xs={24}>
                                <Form.Item name="meta_keywords" label="SEO Keywords" style={{ marginBottom: 0 }}>
                                    <Input.TextArea rows={2} placeholder="Từ khóa 1, từ khóa 2, từ khóa 3" />
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
                                <Radio.Group
                                    value={contentMode}
                                    onChange={handleContentModeChange}
                                    optionType="button"
                                    buttonStyle="solid"
                                    disabled={!canManage}
                                    options={[
                                        { label: 'Soạn thảo', value: 'editor' },
                                        { label: 'Nhập mã HTML', value: 'html' },
                                    ]}
                                />
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
                                <Button type="default" disabled={!canManage} onClick={() => setSampleModalOpen(true)}>Nội dung mẫu</Button>
                            </Space>
                        </div>

                        {contentMode === 'editor' ? (
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
                        ) : null}
                        {contentMode === 'html' ? (
                            <Form.Item label="Mã HTML" style={{ marginBottom: 0 }}>
                                <Input.TextArea
                                    rows={18}
                                    value={bodyValue}
                                    disabled={!canManage}
                                    placeholder="<section>...</section>"
                                    onChange={(event) => form.setFieldValue('body', event.target.value)}
                                    style={{ fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace' }}
                                />
                            </Form.Item>
                        ) : null}
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

            <Modal
                title="Chèn nội dung mẫu"
                open={sampleModalOpen}
                onCancel={() => setSampleModalOpen(false)}
                footer={null}
                width={720}
                destroyOnHidden
            >
                <input ref={sampleImageInputRef} type="file" accept="image/*" style={{ display: 'none' }} onChange={handleSampleImageSelected} />
                <AntList
                    dataSource={SAMPLE_PRESETS}
                    renderItem={(item) => (
                        <AntList.Item
                            actions={[
                                <Button
                                    key="insert"
                                    type="primary"
                                    onClick={() => {
                                        const html = buildPresetHtml(item);
                                        insertHtmlIntoEditor(html);
                                        setSampleModalOpen(false);
                                        messageApi.success('Đã chèn nội dung mẫu vào editor.');
                                    }}
                                >
                                    Chèn
                                </Button>,
                                <Button
                                    key="insertWithImage"
                                    onClick={() => {
                                        setPendingPresetToInsert(item);
                                        if (sampleImageInputRef.current) {
                                            sampleImageInputRef.current.click();
                                        }
                                    }}
                                >
                                    Chèn với ảnh
                                </Button>,
                            ]}
                        >
                            <div style={{ display: 'flex', gap: 12, alignItems: 'center', width: '100%' }}>
                                <div style={{ width: 180, height: 112, flex: '0 0 180px', overflow: 'hidden', borderRadius: 8, border: '1px solid #eef2f2' }} dangerouslySetInnerHTML={{ __html: buildPresetHtmlWithOverride(item) }} />
                                <div style={{ flex: 1 }}>
                                    <AntList.Item.Meta
                                        title={item.title}
                                        description={item.description}
                                    />
                                </div>
                            </div>
                        </AntList.Item>
                    )}
                />
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
