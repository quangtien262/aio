import { useEffect, useRef, useState } from 'react';
import './SetupWizardPage.css';
import Alert from 'antd/es/alert';
import App from 'antd/es/app';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Popconfirm from 'antd/es/popconfirm';
import Modal from 'antd/es/modal';
import Checkbox from 'antd/es/checkbox';
import SingleMediaPicker from '../../../shared/components/SingleMediaPicker';
import List from 'antd/es/list';
import Progress from 'antd/es/progress';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Tooltip from 'antd/es/tooltip';
import Typography from 'antd/es/typography';
import { useSearchParams } from 'react-router-dom';
import ThemeActionOverlayHost from '../../themes/components/ThemeActionOverlayHost';
import useThemeActionOverlayController from '../../themes/hooks/useThemeActionOverlayController';

const { Paragraph, Text, Title } = Typography;
const DEFAULT_BOC_FOOTER_NOTE = 'Website đang chờ khai báo Bộ Công Thương';

const BOC_STATUS_OPTIONS = [
    { value: 'not_notified', label: 'Chưa thông báo' },
    { value: 'pending', label: 'Đang thông báo' },
    { value: 'notified', label: 'Đã thông báo' },
];

function BrandingPreviewImage({ src, alt, frameClassName, placeholderTitle, placeholderHint }) {
    const [hasError, setHasError] = useState(false);

    useEffect(() => {
        setHasError(false);
    }, [src]);

    return (
        <div className={frameClassName}>
            {src && !hasError ? (
                <img
                    className="branding-image"
                    src={src}
                    alt={alt}
                    loading="lazy"
                    onError={() => setHasError(true)}
                />
            ) : (
                <div className="branding-image-placeholder">
                    <Text strong>{placeholderTitle}</Text>
                    <Text type="secondary">{placeholderHint}</Text>
                </div>
            )}
        </div>
    );
}

function ProfileFieldLabel({ children, tooltip }) {
    return (
        <span className="detail-label setup-field-label">
            <span>{children}</span>
            <Tooltip title={tooltip}>
                <button type="button" className="setup-field-info" aria-label={`Giải thích ${children}`}>i</button>
            </Tooltip>
        </span>
    );
}

export default function SetupWizardPage({ setup, themes = [], activeTheme = null, onSaveProfile, onCompleteStep, canEditProfile, canCompleteSteps, canViewThemeManager = false, canManageThemeActions = false, frontendLocale = 'vi', defaultFrontendLocale = 'vi', onGenerateDemoData, onDeleteDemoData, onSaveThemePalette, runAdminAction, callAdminApi }) {
    const { message } = App.useApp();
    const [searchParams, setSearchParams] = useSearchParams();
    const themeActionController = useThemeActionOverlayController();
    const [siteName, setSiteName] = useState('');
    const [siteDescription, setSiteDescription] = useState('');
    const [websiteType, setWebsiteType] = useState('');
    const [companyName, setCompanyName] = useState('');
    const [companyDescription, setCompanyDescription] = useState('');
    const [slogan, setSlogan] = useState('');
    const [logoUrl, setLogoUrl] = useState('');
    const [faviconUrl, setFaviconUrl] = useState('');
    const [supportHotline, setSupportHotline] = useState('');
    const [supportEmail, setSupportEmail] = useState('');
    const [supportLocation, setSupportLocation] = useState('');
    const [copyrightText, setCopyrightText] = useState('');
    const [popLogoVisible, setPopLogoVisible] = useState(false);
    const [tempLogo, setTempLogo] = useState('');
    const [popFaviconVisible, setPopFaviconVisible] = useState(false);
    const [tempFavicon, setTempFavicon] = useState('');
    const [faviconUseLogo, setFaviconUseLogo] = useState(false);
    const [popHotlineVisible, setPopHotlineVisible] = useState(false);
    const [tempHotline, setTempHotline] = useState('');
    const [popEmailVisible, setPopEmailVisible] = useState(false);
    const [tempEmail, setTempEmail] = useState('');
    const [popLocationVisible, setPopLocationVisible] = useState(false);
    const [tempLocation, setTempLocation] = useState('');
    const [popCopyrightVisible, setPopCopyrightVisible] = useState(false);
    const [tempCopyrightText, setTempCopyrightText] = useState('');
    const [popCompanyVisible, setPopCompanyVisible] = useState(false);
    const [tempCompany, setTempCompany] = useState('');
    const [popSiteDescriptionVisible, setPopSiteDescriptionVisible] = useState(false);
    const [tempSiteDescription, setTempSiteDescription] = useState('');
    const [popCompanyDescriptionVisible, setPopCompanyDescriptionVisible] = useState(false);
    const [tempCompanyDescription, setTempCompanyDescription] = useState('');
    const [popSloganVisible, setPopSloganVisible] = useState(false);
    const [tempSlogan, setTempSlogan] = useState('');
    const [popBocVisible, setPopBocVisible] = useState(false);
    const [bocStatus, setBocStatus] = useState('not_notified');
    const [bocConfirmationUrl, setBocConfirmationUrl] = useState('');
    const [bocFooterNote, setBocFooterNote] = useState(DEFAULT_BOC_FOOTER_NOTE);
    const [tempBocStatus, setTempBocStatus] = useState('not_notified');
    const [tempBocConfirmationUrl, setTempBocConfirmationUrl] = useState('');
    const [tempBocFooterNote, setTempBocFooterNote] = useState(DEFAULT_BOC_FOOTER_NOTE);
    const [popSiteNameVisible, setPopSiteNameVisible] = useState(false);
    const [tempSiteName, setTempSiteName] = useState('');
    const [popWebsiteTypeVisible, setPopWebsiteTypeVisible] = useState(false);
    const [tempWebsiteType, setTempWebsiteType] = useState('');
    const stepRefs = useRef(new Map());
    const fieldContainerRef = useRef(null);
    const announcedCompletedStepRef = useRef(null);
    const focusStep = searchParams.get('focusStep');
    const completedStep = searchParams.get('completedStep');

    useEffect(() => {
        setSiteName(setup?.site_name ?? '');
        setSiteDescription(setup?.description ?? '');
        setWebsiteType(setup?.website_type ?? '');
        setCompanyName(setup?.branding?.company_name ?? '');
        setCompanyDescription(setup?.branding?.company_description ?? '');
        setSlogan(setup?.branding?.slogan ?? '');
        setLogoUrl(setup?.branding?.logo_url ?? '');
        setFaviconUrl(setup?.branding?.favicon_url ?? '');
        setSupportHotline(setup?.branding?.support_hotline ?? '');
        setSupportEmail(setup?.branding?.support_email ?? '');
        setSupportLocation(setup?.branding?.support_location ?? '');
        setCopyrightText(setup?.branding?.copyright_text ?? '');
        setBocStatus(setup?.branding?.boc_status ?? 'not_notified');
        setBocConfirmationUrl(setup?.branding?.boc_confirmation_url ?? '');
        setBocFooterNote(setup?.branding?.boc_footer_note ?? DEFAULT_BOC_FOOTER_NOTE);
    }, [setup]);

    useEffect(() => {
        if (!focusStep) {
            return;
        }

        const targetElement = stepRefs.current.get(focusStep);

        if (!targetElement) {
            return;
        }

        targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });

        const timer = window.setTimeout(() => {
            const nextParams = new URLSearchParams(searchParams);
            nextParams.delete('focusStep');
            setSearchParams(nextParams, { replace: true });
        }, 1800);

        return () => window.clearTimeout(timer);
    }, [focusStep, searchParams, setSearchParams]);

    useEffect(() => {
        if (!setup || !completedStep || announcedCompletedStepRef.current === completedStep) {
            return;
        }

        const completedStepMeta = (setup.steps ?? []).find((step) => step.key === completedStep);

        if (!completedStepMeta) {
            return;
        }

        announcedCompletedStepRef.current = completedStep;
        message.success(`Vừa hoàn tất bước ${completedStepMeta.label}.`);

        const timer = window.setTimeout(() => {
            const nextParams = new URLSearchParams(searchParams);
            nextParams.delete('completedStep');
            setSearchParams(nextParams, { replace: true });
        }, 1200);

        return () => window.clearTimeout(timer);
    }, [completedStep, message, searchParams, setSearchParams, setup]);

    if (! setup) {
        return <Card loading title="Cài đặt website" />;
    }

    const websiteTypeOptions = setup.website_type_options ?? [];
    const completionPercentage = setup.summary?.completion_percentage ?? 0;
    const nextStepLabel = setup.summary?.next_step_label;
    const branding = setup.branding ?? {};
    const activeThemeKey = setup.active_theme_key || activeTheme?.key || '';
    const activeThemePalette = setup.theme_palettes?.[activeThemeKey?.toUpperCase?.() ?? ''] ?? {};
    const hasActiveThemePalette = activeThemePalette && Object.keys(activeThemePalette).length > 0;
    const canQuickEditPalette = Boolean(activeTheme) && canManageThemeActions && Boolean(activeTheme?.supports?.custom_css ?? true);
    const bocStatusLabel = BOC_STATUS_OPTIONS.find((option) => option.value === bocStatus)?.label ?? 'Chưa thông báo';

    function scrollToField(fieldId) {
        try {
            const el = document.getElementById(fieldId);
            if (!el) return;
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            // if it's an input inside a wrapper, focus the first input
            if (typeof el.focus === 'function') {
                el.focus();
            } else {
                const input = el.querySelector && el.querySelector('input, textarea, select');
                if (input && typeof input.focus === 'function') input.focus();
            }
        } catch (err) {
            // ignore
        }
    }

    function saveProfile(changes) {
        const payload = {
            site_name: siteName,
            description: siteDescription,
            website_type: websiteType,
            company_name: companyName,
            company_description: companyDescription,
            slogan,
            logo_url: logoUrl,
            favicon_url: faviconUrl,
            support_hotline: supportHotline,
            support_email: supportEmail,
            support_location: supportLocation,
            copyright_text: copyrightText,
            boc_status: bocStatus,
            boc_confirmation_url: bocConfirmationUrl,
            boc_footer_note: bocFooterNote,
            ...changes,
        };

        // update local state
        if (changes.site_name !== undefined) setSiteName(changes.site_name);
        if (changes.description !== undefined) setSiteDescription(changes.description);
        if (changes.website_type !== undefined) setWebsiteType(changes.website_type);
        if (changes.company_name !== undefined) setCompanyName(changes.company_name);
        if (changes.company_description !== undefined) setCompanyDescription(changes.company_description);
        if (changes.slogan !== undefined) setSlogan(changes.slogan);
        if (changes.logo_url !== undefined) setLogoUrl(changes.logo_url);
        // if logo changed and user selected 'use logo as favicon', also update favicon
        if (changes.logo_url !== undefined && faviconUseLogo) {
            changes.favicon_url = changes.logo_url;
            setFaviconUrl(changes.logo_url);
        }
        if (changes.favicon_url !== undefined) setFaviconUrl(changes.favicon_url);
        if (changes.support_hotline !== undefined) setSupportHotline(changes.support_hotline);
        if (changes.support_email !== undefined) setSupportEmail(changes.support_email);
        if (changes.support_location !== undefined) setSupportLocation(changes.support_location);
        if (changes.copyright_text !== undefined) setCopyrightText(changes.copyright_text);
        if (changes.boc_status !== undefined) setBocStatus(changes.boc_status);
        if (changes.boc_confirmation_url !== undefined) setBocConfirmationUrl(changes.boc_confirmation_url);
        if (changes.boc_footer_note !== undefined) setBocFooterNote(changes.boc_footer_note);

        onSaveProfile?.(payload);
    }

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card title="Cài đặt website">
                <Space direction="vertical" size={12} style={{ width: '100%' }}>
                    <div>
                        <Text className="card-label">Foundation Progress</Text>
                        <Title level={3} style={{ marginTop: 0, marginBottom: 8 }}>
                            {setup.is_setup_completed ? 'Setup nền tảng đã hoàn tất.' : 'Theo dõi tiến độ khởi tạo website và chốt các bước nền tảng.'}
                        </Title>
                        <Paragraph style={{ marginBottom: 0 }}>
                            {setup.is_setup_completed
                                ? `Website đã được chốt setup${setup.setup_completed_at ? ` lúc ${setup.setup_completed_at}` : ''}.`
                                : nextStepLabel
                                    ? `Bước ưu tiên tiếp theo: ${nextStepLabel}.`
                                    : 'Tất cả bước đã sẵn sàng để chốt setup.'}
                        </Paragraph>
                    </div>

                    {!setup.is_setup_completed ? (
                        <Alert
                            type="info"
                            showIcon
                            message={`Đã hoàn thành ${setup.summary?.completed_steps ?? 0}/${setup.summary?.total_steps ?? 0} bước`}
                            description={nextStepLabel ? `Tiếp theo nên xử lý: ${nextStepLabel}.` : 'Hệ thống đã sẵn sàng để chốt setup.'}
                        />
                    ) : null}

                    <Progress percent={completionPercentage} strokeColor="#0f766e" />

                    <div className="metric-grid">
                        <div className="metric-tile">
                            <Text className="metric-label">Loại website</Text>
                            <Title level={4} style={{ margin: 0 }}>{setup.website_type_label || 'Chưa chọn'}</Title>
                        </div>
                        <div className="metric-tile">
                            <Text className="metric-label">Theme đang dùng</Text>
                            <Title level={4} style={{ margin: 0 }}>{setup.active_theme_key || 'Chưa kích hoạt'}</Title>
                        </div>
                        <div className="metric-tile">
                            <Text className="metric-label">Admin hoạt động</Text>
                            <Title level={4} style={{ margin: 0 }}>{setup.signals?.active_admins ?? 0}</Title>
                        </div>
                        <div className="metric-tile">
                            <Text className="metric-label">Module đã bật</Text>
                            <Title level={4} style={{ margin: 0 }}>{setup.signals?.enabled_modules ?? 0}</Title>
                        </div>
                    </div>
                </Space>
            </Card>

            <div className="setup-main-column">
                    <Card className="setup-profile-card">
                        <div className="setup-profile-hero">
                            <div className="setup-profile-logo">
                                {logoUrl ? (
                                    <img src={logoUrl} alt={companyName || siteName || 'Logo'} />
                                ) : (
                                    <span>{(companyName || siteName || 'AIO').trim().slice(0, 2).toUpperCase()}</span>
                                )}
                            </div>
                            <div className="setup-profile-overview">
                                <Text className="setup-profile-eyebrow">Hồ sơ website</Text>
                                <Title level={3} className="setup-profile-name">{companyName || siteName || 'Chưa cấu hình tên công ty'}</Title>
                                <Paragraph className="setup-profile-desc">
                                    {companyDescription || siteDescription || slogan || 'Bổ sung mô tả website để footer và các khu vực nhận diện thương hiệu hiển thị đầy đủ hơn.'}
                                </Paragraph>
                                <div className="setup-profile-badges">
                                    <Tag color="green">{setup.website_type_label || 'Chưa chọn loại website'}</Tag>
                                    <Tag color="blue">{activeThemeKey || 'Chưa kích hoạt theme'}</Tag>
                                    <Tag color={bocStatus === 'notified' ? 'success' : bocStatus === 'pending' ? 'processing' : 'default'}>{bocStatusLabel}</Tag>
                                </div>
                            </div>
                            <div className="setup-profile-actions">
                                <Button onClick={() => { setPopLogoVisible(true); setTempLogo(logoUrl); }}>Sửa logo</Button>
                                <Button disabled={!canQuickEditPalette} onClick={() => themeActionController.openPalette(activeTheme)}>Bảng màu</Button>
                            </div>
                        </div>

                        <div className="setup-profile-section-head">
                            <div>
                                <Space size={8} wrap>
                                    <Text className="setup-profile-eyebrow">Thiết lập nhanh</Text>
                                    <Tag color={setup.is_source_locale ? 'blue' : 'gold'}>
                                        {(setup.selected_locale || frontendLocale).toUpperCase()}
                                    </Tag>
                                    {!setup.is_source_locale ? <Tag color="processing">Nội dung dịch</Tag> : null}
                                </Space>
                            </div>
                            <Text type="secondary">
                                {setup.is_source_locale
                                    ? 'Click “Sửa” ở từng dòng để cập nhật nội dung gốc.'
                                    : `Đang cập nhật nội dung ${String(setup.selected_locale || frontendLocale).toUpperCase()}; logo, màu sắc và liên hệ vẫn dùng chung.`}
                            </Text>
                        </div>
                        <div className="setup-profile-grid">
                            <div className="setup-profile-item">
                                <ProfileFieldLabel tooltip="Tên chính của website, thường dùng trong tiêu đề hệ thống và metadata.">Tên website</ProfileFieldLabel>
                                <div className="detail-value" title={siteName || ''}>{siteName || 'Chưa cấu hình'}</div>
                                <Popconfirm
                                    title={(
                                        <div style={{ minWidth: 260 }}>
                                            <Input value={tempSiteName} onChange={(e) => setTempSiteName(e.target.value)} placeholder="Nhập tên website" />
                                        </div>
                                    )}
                                    okText="Lưu"
                                    cancelText="Hủy"
                                    onConfirm={() => { saveProfile({ site_name: tempSiteName }); setPopSiteNameVisible(false); }}
                                    open={popSiteNameVisible}
                                    onOpenChange={(v) => { setPopSiteNameVisible(v); if (v) setTempSiteName(siteName); }}
                                >
                                    <Button size="small" onClick={() => setPopSiteNameVisible(true)}>Sửa</Button>
                                </Popconfirm>
                            </div>

                            <div className="setup-profile-item">
                                <ProfileFieldLabel tooltip="Tên pháp lý hoặc tên thương hiệu hiển thị ở header, footer và các nội dung liên hệ.">Tên công ty</ProfileFieldLabel>
                                <div className="detail-value" title={companyName || ''}>{companyName || 'Ch\u01b0a c\u1ea5u h\u00ecnh'}</div>
                                <Popconfirm
                                    title={(
                                        <div style={{ minWidth: 300 }}>
                                            <Input value={tempCompany} onChange={(e) => setTempCompany(e.target.value)} placeholder={'Nh\u1eadp t\u00ean c\u00f4ng ty'} />
                                        </div>
                                    )}
                                    okText={'L\u01b0u'}
                                    cancelText={'H\u1ee7y'}
                                    onConfirm={() => { saveProfile({ company_name: tempCompany }); setPopCompanyVisible(false); }}
                                    open={popCompanyVisible}
                                    onOpenChange={(v) => { setPopCompanyVisible(v); if (v) setTempCompany(companyName); }}
                                >
                                    <Button size="small" onClick={() => setPopCompanyVisible(true)}>{'S\u1eeda'}</Button>
                                </Popconfirm>
                            </div>

                            <div className="setup-profile-item">
                                <ProfileFieldLabel tooltip="Số điện thoại chính để khách hàng liên hệ, dùng cho nút gọi và footer website.">Hotline</ProfileFieldLabel>
                                <div className="detail-value" title={supportHotline || ''}>{supportHotline || 'Chưa cấu hình'}</div>
                                <Popconfirm
                                    title={(
                                        <div style={{ minWidth: 260 }}>
                                            <Input value={tempHotline} onChange={(e) => setTempHotline(e.target.value)} placeholder="1900 6760 / 0354.466.968" />
                                        </div>
                                    )}
                                    okText="Lưu"
                                    cancelText="Hủy"
                                    onConfirm={() => { saveProfile({ support_hotline: tempHotline }); setPopHotlineVisible(false); }}
                                    open={popHotlineVisible}
                                    onOpenChange={(v) => { setPopHotlineVisible(v); if (v) setTempHotline(supportHotline); }}
                                >
                                    <Button size="small" onClick={() => setPopHotlineVisible(true)}>Sửa</Button>
                                </Popconfirm>
                            </div>

                            <div className="setup-profile-item setup-profile-item-wide">
                                <ProfileFieldLabel tooltip="Mô tả tổng quan về website hoặc doanh nghiệp, có thể dùng làm nội dung giới thiệu mặc định.">Mô tả website</ProfileFieldLabel>
                                <div className="detail-value is-multiline" title={siteDescription || ''}>{siteDescription || 'Chưa cấu hình'}</div>
                                <Popconfirm
                                    title={(
                                        <div style={{ minWidth: 380 }}>
                                            <Input.TextArea value={tempSiteDescription} onChange={(e) => setTempSiteDescription(e.target.value)} placeholder="Nhập mô tả website" autoSize={{ minRows: 3, maxRows: 6 }} />
                                        </div>
                                    )}
                                    okText="Lưu"
                                    cancelText="Hủy"
                                    onConfirm={() => { saveProfile({ description: tempSiteDescription }); setPopSiteDescriptionVisible(false); }}
                                    open={popSiteDescriptionVisible}
                                    onOpenChange={(v) => { setPopSiteDescriptionVisible(v); if (v) setTempSiteDescription(siteDescription); }}
                                >
                                    <Button size="small" onClick={() => setPopSiteDescriptionVisible(true)}>Sửa</Button>
                                </Popconfirm>
                            </div>

                            <div className="setup-profile-item setup-profile-item-wide">
                                <ProfileFieldLabel tooltip="Đoạn mô tả riêng hiển thị ở footer, ưu tiên hơn mô tả website nếu được cấu hình.">Mô tả footer</ProfileFieldLabel>
                                <div className="detail-value is-multiline" title={companyDescription || ''}>{companyDescription || 'Chưa cấu hình'}</div>
                                <Popconfirm
                                    title={(
                                        <div style={{ minWidth: 380 }}>
                                            <Input.TextArea value={tempCompanyDescription} onChange={(e) => setTempCompanyDescription(e.target.value)} placeholder="Nhập mô tả hiển thị ở footer" autoSize={{ minRows: 3, maxRows: 6 }} />
                                        </div>
                                    )}
                                    okText="Lưu"
                                    cancelText="Hủy"
                                    onConfirm={() => { saveProfile({ company_description: tempCompanyDescription }); setPopCompanyDescriptionVisible(false); }}
                                    open={popCompanyDescriptionVisible}
                                    onOpenChange={(v) => { setPopCompanyDescriptionVisible(v); if (v) setTempCompanyDescription(companyDescription); }}
                                >
                                    <Button size="small" onClick={() => setPopCompanyDescriptionVisible(true)}>Sửa</Button>
                                </Popconfirm>
                            </div>

                            <div className="setup-profile-item setup-profile-item-wide">
                                <ProfileFieldLabel tooltip="Dòng bản quyền hiển thị ở cuối website. Có thể nhập tên pháp nhân, đơn vị sở hữu hoặc thông tin bảo lưu quyền.">Thông tin bản quyền</ProfileFieldLabel>
                                <div className="detail-value is-multiline" title={copyrightText || ''}>{copyrightText || 'Chưa cấu hình'}</div>
                                <Popconfirm
                                    title={(
                                        <div style={{ minWidth: 420 }}>
                                            <Input.TextArea
                                                value={tempCopyrightText}
                                                onChange={(e) => setTempCopyrightText(e.target.value)}
                                                placeholder="Ví dụ: Bản quyền nội dung thuộc về Công ty ABC"
                                                autoSize={{ minRows: 2, maxRows: 5 }}
                                                maxLength={500}
                                                showCount
                                            />
                                        </div>
                                    )}
                                    okText="Lưu"
                                    cancelText="Hủy"
                                    onConfirm={() => { saveProfile({ copyright_text: tempCopyrightText }); setPopCopyrightVisible(false); }}
                                    open={popCopyrightVisible}
                                    onOpenChange={(visible) => { setPopCopyrightVisible(visible); if (visible) setTempCopyrightText(copyrightText); }}
                                >
                                    <Button size="small" onClick={() => setPopCopyrightVisible(true)}>Sửa</Button>
                                </Popconfirm>
                            </div>

                            <div className="setup-profile-item">
                                <ProfileFieldLabel tooltip="Thông điệp ngắn của thương hiệu, dùng ở các khu vực nhận diện hoặc nội dung giới thiệu.">Slogan</ProfileFieldLabel>
                                <div className="detail-value" title={slogan || ''}>{slogan || 'Ch\u01b0a c\u1ea5u h\u00ecnh'}</div>
                                <Popconfirm
                                    title={(
                                        <div style={{ minWidth: 340 }}>
                                            <Input.TextArea value={tempSlogan} onChange={(e) => setTempSlogan(e.target.value)} placeholder={'Nh\u1eadp slogan'} autoSize={{ minRows: 2, maxRows: 4 }} />
                                        </div>
                                    )}
                                    okText={'L\u01b0u'}
                                    cancelText={'H\u1ee7y'}
                                    onConfirm={() => { saveProfile({ slogan: tempSlogan }); setPopSloganVisible(false); }}
                                    open={popSloganVisible}
                                    onOpenChange={(v) => { setPopSloganVisible(v); if (v) setTempSlogan(slogan); }}
                                >
                                    <Button size="small" onClick={() => setPopSloganVisible(true)}>{'S\u1eeda'}</Button>
                                </Popconfirm>
                            </div>

                            <div className="setup-profile-item">
                                <ProfileFieldLabel tooltip="Nhóm ngành/mô hình website, giúp hệ thống chọn preset và cách gợi ý nội dung phù hợp.">Loại website</ProfileFieldLabel>
                                <div className="detail-value" title={setup.website_type_label || ''}>{setup.website_type_label || 'Chưa chọn'}</div>
                                <Popconfirm
                                    title={(
                                        <div style={{ minWidth: 260 }}>
                                            <Select value={tempWebsiteType || undefined} options={websiteTypeOptions} onChange={(v) => setTempWebsiteType(v)} placeholder="Chọn loại website" style={{ width: '100%' }} />
                                        </div>
                                    )}
                                    okText="Lưu"
                                    cancelText="Hủy"
                                    onConfirm={() => { saveProfile({ website_type: tempWebsiteType }); setPopWebsiteTypeVisible(false); }}
                                    open={popWebsiteTypeVisible}
                                    onOpenChange={(v) => { setPopWebsiteTypeVisible(v); if (v) setTempWebsiteType(websiteType); }}
                                >
                                    <Button size="small" onClick={() => setPopWebsiteTypeVisible(true)}>Sửa</Button>
                                </Popconfirm>
                            </div>

                            <div className="setup-profile-item setup-profile-item-wide">
                                <div className="setup-profile-boc-label">
                                    <img src="/img/dathongbao-bo-cong-thuong.png" alt="Bộ Công Thương" />
                                    <Tooltip title="Trạng thái khai báo Bộ Công Thương. Nếu đã thông báo, website sẽ hiển thị ảnh xác nhận và link đã cấu hình ở footer.">
                                        <button type="button" className="setup-field-info" aria-label="Giải thích Bộ Công Thương">i</button>
                                    </Tooltip>
                                </div>
                                <div className="detail-value is-multiline">
                                    {bocStatusLabel}
                                    {bocStatus === 'notified' && bocConfirmationUrl ? ` - ${bocConfirmationUrl}` : ''}
                                    {bocStatus === 'pending' ? ` - ${bocFooterNote || DEFAULT_BOC_FOOTER_NOTE}` : ''}
                                </div>
                                <Popconfirm
                                    title={(
                                        <div className="setup-boc-popover">
                                            <Select
                                                value={tempBocStatus}
                                                options={BOC_STATUS_OPTIONS}
                                                onChange={(value) => {
                                                    setTempBocStatus(value);
                                                    if (value === 'pending' && !tempBocFooterNote) {
                                                        setTempBocFooterNote(DEFAULT_BOC_FOOTER_NOTE);
                                                    }
                                                }}
                                                style={{ width: '100%' }}
                                            />
                                            {tempBocStatus === 'notified' ? (
                                                <label>
                                                    <span>Link xác nhận của Bộ Công Thương</span>
                                                    <Input value={tempBocConfirmationUrl} onChange={(e) => setTempBocConfirmationUrl(e.target.value)} placeholder="https://..." />
                                                </label>
                                            ) : null}
                                            {tempBocStatus === 'pending' ? (
                                                <label>
                                                    <span>Nội dung footer</span>
                                                    <Input.TextArea value={tempBocFooterNote} onChange={(e) => setTempBocFooterNote(e.target.value)} autoSize={{ minRows: 2, maxRows: 4 }} />
                                                </label>
                                            ) : null}
                                        </div>
                                    )}
                                    okText="Lưu"
                                    cancelText="Hủy"
                                    onConfirm={() => {
                                        saveProfile({
                                            boc_status: tempBocStatus,
                                            boc_confirmation_url: tempBocStatus === 'notified' ? tempBocConfirmationUrl : '',
                                            boc_footer_note: tempBocStatus === 'pending' ? (tempBocFooterNote || DEFAULT_BOC_FOOTER_NOTE) : '',
                                        });
                                        setPopBocVisible(false);
                                    }}
                                    open={popBocVisible}
                                    onOpenChange={(v) => {
                                        setPopBocVisible(v);
                                        if (v) {
                                            setTempBocStatus(bocStatus || 'not_notified');
                                            setTempBocConfirmationUrl(bocConfirmationUrl);
                                            setTempBocFooterNote(bocFooterNote || DEFAULT_BOC_FOOTER_NOTE);
                                        }
                                    }}
                                >
                                    <Button size="small" onClick={() => setPopBocVisible(true)}>Sửa</Button>
                                </Popconfirm>
                            </div>

                            <div className="setup-profile-item setup-profile-media">
                                <ProfileFieldLabel tooltip="Biểu tượng nhỏ của website trên tab trình duyệt. Khi lưu, hệ thống ghi thành public/favicon.ico.">Favicon</ProfileFieldLabel>
                                <div className="setup-profile-favicon-preview">
                                    <BrandingPreviewImage
                                        src={faviconUrl}
                                        alt="Favicon"
                                        frameClassName="branding-image-frame"
                                        placeholderTitle={faviconUrl ? '' : 'Chưa cấu hình'}
                                        placeholderHint={faviconUrl ? '' : 'Chưa có favicon'}
                                    />
                                </div>
                                <Button size="small" onClick={() => { setPopFaviconVisible(true); setTempFavicon(faviconUrl); }}>Sửa</Button>
                            </div>

                            <div className="setup-profile-item">
                                <ProfileFieldLabel tooltip="Email chăm sóc khách hàng, dùng trong footer và các luồng nhận liên hệ.">Email CSKH</ProfileFieldLabel>
                                <div className="detail-value" title={supportEmail || ''}>{supportEmail || 'Chưa cấu hình'}</div>
                                <Popconfirm
                                    title={(
                                        <div style={{ minWidth: 260 }}>
                                            <Input value={tempEmail} onChange={(e) => setTempEmail(e.target.value)} placeholder="sales@example.com" />
                                        </div>
                                    )}
                                    okText="Lưu"
                                    cancelText="Hủy"
                                    onConfirm={() => { saveProfile({ support_email: tempEmail }); setPopEmailVisible(false); }}
                                    open={popEmailVisible}
                                    onOpenChange={(v) => { setPopEmailVisible(v); if (v) setTempEmail(supportEmail); }}
                                >
                                    <Button size="small" onClick={() => setPopEmailVisible(true)}>Sửa</Button>
                                </Popconfirm>
                            </div>

                            <div className="setup-profile-item setup-profile-item-wide">
                                <ProfileFieldLabel tooltip="Địa chỉ doanh nghiệp hoặc địa điểm hiển thị công khai trên footer/trang liên hệ.">Địa chỉ</ProfileFieldLabel>
                                <div className="detail-value" title={supportLocation || ''}>{supportLocation || 'Chưa cấu hình'}</div>
                                <Popconfirm
                                    title={(
                                        <div style={{ minWidth: 260 }}>
                                            <Input value={tempLocation} onChange={(e) => setTempLocation(e.target.value)} placeholder="Hà Nội" />
                                        </div>
                                    )}
                                    okText="Lưu"
                                    cancelText="Hủy"
                                    onConfirm={() => { saveProfile({ support_location: tempLocation }); setPopLocationVisible(false); }}
                                    open={popLocationVisible}
                                    onOpenChange={(v) => { setPopLocationVisible(v); if (v) setTempLocation(supportLocation); }}
                                >
                                    <Button size="small" onClick={() => setPopLocationVisible(true)}>Sửa</Button>
                                </Popconfirm>
                            </div>
                        </div>
                    </Card>

                    <Modal
                        title="Upload Logo"
                        open={popLogoVisible}
                        onCancel={() => setPopLogoVisible(false)}
                        footer={null}
                        destroyOnHidden
                    >
                        <SingleMediaPicker
                            open={popLogoVisible}
                            value={tempLogo}
                            onChange={(v) => setTempLogo(v)}
                            canManage={canEditProfile}
                            callAdminApi={callAdminApi}
                            uploadButtonLabel="Upload logo"
                        />
                        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 12 }}>
                            <Button onClick={() => setPopLogoVisible(false)}>Hủy</Button>
                            <Button type="primary" onClick={() => { saveProfile({ logo_url: tempLogo }); setPopLogoVisible(false); }}>Lưu</Button>
                        </div>
                    </Modal>

                    <Modal
                        title="Upload Favicon"
                        open={popFaviconVisible}
                        onCancel={() => setPopFaviconVisible(false)}
                        footer={null}
                        destroyOnHidden
                    >
                        <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 12 }}>
                            <Checkbox checked={faviconUseLogo} onChange={(e) => {
                                const v = e.target.checked;
                                setFaviconUseLogo(v);
                                if (v) setTempFavicon(logoUrl);
                            }} disabled={!canEditProfile}>Dùng logo làm favicon</Checkbox>
                        </div>

                        {!faviconUseLogo ? (
                            <SingleMediaPicker
                                open={popFaviconVisible}
                                value={tempFavicon}
                                onChange={(v) => setTempFavicon(v)}
                                canManage={canEditProfile}
                                callAdminApi={callAdminApi}
                                uploadButtonLabel="Upload favicon"
                            />
                        ) : (
                            <div style={{ padding: 12, border: '1px dashed var(--sw-border)', borderRadius: 6 }}>
                                <Text>Favicon sẽ được lấy từ Logo hiện tại.</Text>
                            </div>
                        )}

                        <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 12 }}>
                            <Button onClick={() => setPopFaviconVisible(false)}>Hủy</Button>
                            <Button type="primary" onClick={() => { saveProfile({ favicon_url: faviconUseLogo ? logoUrl : tempFavicon }); setPopFaviconVisible(false); setFaviconUseLogo(false); }}>Lưu</Button>
                        </div>
                    </Modal>

                    <ThemeActionOverlayHost
                        state={themeActionController.overlayState}
                        themes={themes}
                        siteProfile={setup}
                        canManageThemeActions={canManageThemeActions}
                        callAdminApi={callAdminApi}
                        runAdminAction={runAdminAction}
                        frontendLocale={frontendLocale}
                        onGenerateDemoData={onGenerateDemoData}
                        onDeleteDemoData={onDeleteDemoData}
                        onSaveThemePalette={onSaveThemePalette}
                        onClose={themeActionController.closeOverlay}
                    />
            </div>
        </Space>
    );
}
