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
import Typography from 'antd/es/typography';
import { Link, useSearchParams } from 'react-router-dom';

const { Paragraph, Text, Title } = Typography;

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

export default function SetupWizardPage({ setup, onSaveProfile, onCompleteStep, canEditProfile, canCompleteSteps, callAdminApi }) {
    const { message } = App.useApp();
    const [searchParams, setSearchParams] = useSearchParams();
    const [siteName, setSiteName] = useState('');
    const [websiteType, setWebsiteType] = useState('');
    const [companyName, setCompanyName] = useState('');
    const [slogan, setSlogan] = useState('');
    const [logoUrl, setLogoUrl] = useState('');
    const [faviconUrl, setFaviconUrl] = useState('');
    const [supportHotline, setSupportHotline] = useState('');
    const [supportEmail, setSupportEmail] = useState('');
    const [supportLocation, setSupportLocation] = useState('');
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
    const [popCompanyVisible, setPopCompanyVisible] = useState(false);
    const [tempCompany, setTempCompany] = useState('');
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
        setWebsiteType(setup?.website_type ?? '');
        setCompanyName(setup?.branding?.company_name ?? '');
        setSlogan(setup?.branding?.slogan ?? '');
        setLogoUrl(setup?.branding?.logo_url ?? '');
        setFaviconUrl(setup?.branding?.favicon_url ?? '');
        setSupportHotline(setup?.branding?.support_hotline ?? '');
        setSupportEmail(setup?.branding?.support_email ?? '');
        setSupportLocation(setup?.branding?.support_location ?? '');
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
            website_type: websiteType,
            company_name: companyName,
            slogan,
            logo_url: logoUrl,
            favicon_url: faviconUrl,
            support_hotline: supportHotline,
            support_email: supportEmail,
            support_location: supportLocation,
            ...changes,
        };

        // update local state
        if (changes.site_name !== undefined) setSiteName(changes.site_name);
        if (changes.website_type !== undefined) setWebsiteType(changes.website_type);
        if (changes.company_name !== undefined) setCompanyName(changes.company_name);
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

            <div className="setup-shell">
                <div className="setup-main-column">


                <div className="setup-side-column">
                    <Card title="Site Profile & Branding">
                        <div className="detail-grid detail-grid-2">
                            <div className="detail-tile detail-tile-row detail-tile-wide">
                                <Text className="detail-label">Tên website</Text>
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
                                    visible={popSiteNameVisible}
                                    onVisibleChange={(v) => { setPopSiteNameVisible(v); if (v) setTempSiteName(siteName); }}
                                >
                                    <Button size="small" onClick={() => setPopSiteNameVisible(true)}>Sửa</Button>
                                </Popconfirm>
                            </div>

                            <div className="detail-tile detail-tile-row detail-tile-wide">
                                <Text className="detail-label">Loại website</Text>
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
                                    visible={popWebsiteTypeVisible}
                                    onVisibleChange={(v) => { setPopWebsiteTypeVisible(v); if (v) setTempWebsiteType(websiteType); }}
                                >
                                    <Button size="small" onClick={() => setPopWebsiteTypeVisible(true)}>Sửa</Button>
                                </Popconfirm>
                            </div>

                            <div className="detail-tile detail-tile-wide">
                                <Text className="detail-label">Company name</Text>
                                <Text strong>{companyName || 'Chưa cấu hình'}</Text>
                            </div>
                            <div className="detail-tile detail-tile-wide">
                                <Text className="detail-label">Slogan</Text>
                                <Text strong>{slogan || 'Chưa cấu hình'}</Text>
                            </div>
                            <div className="detail-tile detail-tile-wide">
                                <Text className="detail-label">Palette storefront</Text>
                                <Text strong>{setup.active_theme_key === 'TH0002' ? 'Quản lý trong Theme Manager' : 'Không áp dụng'}</Text>
                            </div>

                            <div className="detail-tile detail-tile-wide detail-tile-row">
                                <Text className="detail-label">Logo</Text>
                                <div style={{ flex: 1 }}>
                                    <BrandingPreviewImage
                                        src={logoUrl}
                                        alt={companyName || siteName || 'Logo'}
                                        frameClassName="branding-image-frame"
                                        placeholderTitle={logoUrl ? '' : 'Chưa cấu hình'}
                                        placeholderHint={logoUrl ? '' : 'Chưa có logo'}
                                    />
                                </div>
                                <Button size="small" onClick={() => { setPopLogoVisible(true); setTempLogo(logoUrl); }}>Sửa</Button>
                            </div>

                            <div className="detail-tile detail-tile-wide detail-tile-row">
                                <Text className="detail-label">Favicon</Text>
                                <div style={{ flex: '0 0 56px' }}>
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

                            <div className="detail-tile detail-tile-row">
                                <Text className="detail-label">Hotline</Text>
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
                                    visible={popHotlineVisible}
                                    onVisibleChange={(v) => { setPopHotlineVisible(v); if (v) setTempHotline(supportHotline); }}
                                >
                                    <Button size="small" onClick={() => setPopHotlineVisible(true)}>Sửa</Button>
                                </Popconfirm>
                            </div>

                            <div className="detail-tile detail-tile-row">
                                <Text className="detail-label">Email CSKH</Text>
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
                                    visible={popEmailVisible}
                                    onVisibleChange={(v) => { setPopEmailVisible(v); if (v) setTempEmail(supportEmail); }}
                                >
                                    <Button size="small" onClick={() => setPopEmailVisible(true)}>Sửa</Button>
                                </Popconfirm>
                            </div>

                            <div className="detail-tile detail-tile-wide detail-tile-row">
                                <Text className="detail-label">Vị trí hiển thị</Text>
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
                                    visible={popLocationVisible}
                                    onVisibleChange={(v) => { setPopLocationVisible(v); if (v) setTempLocation(supportLocation); }}
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
                        destroyOnClose
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
                        destroyOnClose
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
                </div>

                    <Card title="Site Profile & Branding">
                        <List
                            className="setup-steps-list"
                            dataSource={setup.steps}
                            renderItem={(item) => (
                                <List.Item
                                    ref={(element) => {
                                        if (element) {
                                            stepRefs.current.set(item.key, element);
                                        } else {
                                            stepRefs.current.delete(item.key);
                                        }
                                    }}
                                    className={`${focusStep === item.key ? 'setup-step-focus' : ''} setup-step-row`}
                                >
                                    <Space direction="vertical" size={10} style={{ width: '100%' }}>
                                        <Space className="setup-step-header" wrap>
                                            <div className="setup-step-copy">
                                                <Text strong>{item.label}</Text>
                                                {item.description ? <Paragraph style={{ marginBottom: 0, marginTop: 4 }}>{item.description}</Paragraph> : null}
                                            </div>
                                            <Space wrap>
                                                <Tag color={item.is_completed ? 'green' : item.is_blocked ? 'default' : 'blue'}>
                                                    {item.is_completed ? 'done' : item.is_blocked ? 'blocked' : 'pending'}
                                                </Tag>
                                                <Tag>{item.manual_completion ? 'manual' : 'auto'}</Tag>
                                                {item.completion_source === 'derived' && item.is_completed ? <Tag color="cyan">derived</Tag> : null}
                                            </Space>
                                        </Space>

                                        <Space wrap className="setup-step-actions">
                                            {item.route && item.route !== '/setup' ? (
                                                <Link to={`${item.route}?returnTo=${encodeURIComponent('/setup')}&focusStep=${encodeURIComponent(item.key)}${item.manual_completion ? `&completeStep=${encodeURIComponent(item.key)}` : ''}`}>
                                                    <Button size="small">Đi tới bước này</Button>
                                                </Link>
                                            ) : null}
                                            {!item.is_completed ? (
                                                <Button
                                                    size="small"
                                                    type="primary"
                                                    disabled={!canCompleteSteps || !item.can_complete}
                                                    onClick={() => onCompleteStep?.(item.key)}
                                                >
                                                    {item.key === 'finish' ? 'Chốt setup' : 'Đánh dấu hoàn thành'}
                                                </Button>
                                            ) : null}
                                        </Space>
                                    </Space>
                                </List.Item>
                            )}
                        />
                    </Card>
                </div>


                <div className="setup-side-column">
                    <Card title="Các bước setup">
                        <List
                            className="setup-steps-list"
                            dataSource={setup.steps}
                            renderItem={(item) => (
                                <List.Item
                                    ref={(element) => {
                                        if (element) {
                                            stepRefs.current.set(item.key, element);
                                        } else {
                                            stepRefs.current.delete(item.key);
                                        }
                                    }}
                                    className={`${focusStep === item.key ? 'setup-step-focus' : ''} setup-step-row`}
                                >
                                    <Space direction="vertical" size={10} style={{ width: '100%' }}>
                                        <Space className="setup-step-header" wrap>
                                            <div className="setup-step-copy">
                                                <Text strong>{item.label}</Text>
                                                {item.description ? <Paragraph style={{ marginBottom: 0, marginTop: 4 }}>{item.description}</Paragraph> : null}
                                            </div>
                                            <Space wrap>
                                                <Tag color={item.is_completed ? 'green' : item.is_blocked ? 'default' : 'blue'}>
                                                    {item.is_completed ? 'done' : item.is_blocked ? 'blocked' : 'pending'}
                                                </Tag>
                                                <Tag>{item.manual_completion ? 'manual' : 'auto'}</Tag>
                                                {item.completion_source === 'derived' && item.is_completed ? <Tag color="cyan">derived</Tag> : null}
                                            </Space>
                                        </Space>

                                        <Space wrap className="setup-step-actions">
                                            {item.route && item.route !== '/setup' ? (
                                                <Link to={`${item.route}?returnTo=${encodeURIComponent('/setup')}&focusStep=${encodeURIComponent(item.key)}${item.manual_completion ? `&completeStep=${encodeURIComponent(item.key)}` : ''}`}>
                                                    <Button size="small">Đi tới bước này</Button>
                                                </Link>
                                            ) : null}
                                            {!item.is_completed ? (
                                                <Button
                                                    size="small"
                                                    type="primary"
                                                    disabled={!canCompleteSteps || !item.can_complete}
                                                    onClick={() => onCompleteStep?.(item.key)}
                                                >
                                                    {item.key === 'finish' ? 'Chốt setup' : 'Đánh dấu hoàn thành'}
                                                </Button>
                                            ) : null}
                                        </Space>
                                    </Space>
                                </List.Item>
                            )}
                        />
                    </Card>
                </div>
            </div>
        </Space>
    );
}
