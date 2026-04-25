import { useEffect, useRef, useState } from 'react';
import Alert from 'antd/es/alert';
import App from 'antd/es/app';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
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

export default function SetupWizardPage({ setup, onSaveProfile, onCompleteStep, canEditProfile, canCompleteSteps }) {
    const { message } = App.useApp();
    const [searchParams, setSearchParams] = useSearchParams();
    const [siteName, setSiteName] = useState('');
    const [websiteType, setWebsiteType] = useState('');
    const [companyName, setCompanyName] = useState('');
    const [slogan, setSlogan] = useState('');
    const [primaryColor, setPrimaryColor] = useState('');
    const [logoUrl, setLogoUrl] = useState('');
    const [faviconUrl, setFaviconUrl] = useState('');
    const [supportHotline, setSupportHotline] = useState('');
    const [supportEmail, setSupportEmail] = useState('');
    const [supportLocation, setSupportLocation] = useState('');
    const stepRefs = useRef(new Map());
    const announcedCompletedStepRef = useRef(null);
    const focusStep = searchParams.get('focusStep');
    const completedStep = searchParams.get('completedStep');

    useEffect(() => {
        setSiteName(setup?.site_name ?? '');
        setWebsiteType(setup?.website_type ?? '');
        setCompanyName(setup?.branding?.company_name ?? '');
        setSlogan(setup?.branding?.slogan ?? '');
        setPrimaryColor(setup?.branding?.primary_color ?? '#0f766e');
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
                    <Card
                        title="Site Profile & Branding"
                        extra={(
                            <Button htmlType="submit" type="primary" form="setup-profile-form" disabled={!canEditProfile}>
                                Lưu cấu hình
                            </Button>
                        )}
                    >
                        <Form
                            id="setup-profile-form"
                            layout="vertical"
                            onFinish={() => onSaveProfile?.({
                                site_name: siteName,
                                website_type: websiteType,
                                company_name: companyName,
                                slogan,
                                primary_color: primaryColor,
                                logo_url: logoUrl,
                                favicon_url: faviconUrl,
                                support_hotline: supportHotline,
                                support_email: supportEmail,
                                support_location: supportLocation,
                            })}
                        >
                            <div className="setup-form-stack">
                                <section className="setup-form-section">
                                    <div className="setup-form-section-heading">
                                        <Text className="card-label">Core Info</Text>
                                        <Title level={5} style={{ margin: 0 }}>Thông tin nền tảng</Title>
                                        <Paragraph style={{ marginBottom: 0 }}>
                                            Chốt tên website, loại website và phần mô tả thương hiệu cơ bản trước khi đi tiếp các bước theme/module.
                                        </Paragraph>
                                    </div>
                                    <div className="setup-form-grid setup-form-grid-2">
                                        <Form.Item label="Tên website" required>
                                            <Input disabled={!canEditProfile} value={siteName} onChange={(event) => setSiteName(event.target.value)} placeholder="VD: HTV Corporate Site" />
                                        </Form.Item>
                                        <Form.Item label="Loại website" required>
                                            <Select disabled={!canEditProfile} value={websiteType || undefined} options={websiteTypeOptions} onChange={setWebsiteType} placeholder="Chọn loại website" />
                                        </Form.Item>
                                        <Form.Item label="Company name">
                                            <Input disabled={!canEditProfile} value={companyName} onChange={(event) => setCompanyName(event.target.value)} placeholder="VD: HT Việt Nam" />
                                        </Form.Item>
                                        <Form.Item label="Slogan">
                                            <Input disabled={!canEditProfile} value={slogan} onChange={(event) => setSlogan(event.target.value)} placeholder="VD: Digital foundation for every website" />
                                        </Form.Item>
                                    </div>
                                </section>

                                <section className="setup-form-section">
                                    <div className="setup-form-section-heading">
                                        <Text className="card-label">Brand Assets</Text>
                                        <Title level={5} style={{ margin: 0 }}>Nhận diện thương hiệu</Title>
                                        <Paragraph style={{ marginBottom: 0 }}>
                                            Nhập màu chủ đạo, logo và favicon để sidebar, header và theme preview phản ánh đúng bộ nhận diện hiện tại.
                                        </Paragraph>
                                    </div>
                                    <div className="setup-form-grid setup-form-grid-branding">
                                        <Form.Item label="Primary color">
                                            <Space wrap align="center">
                                                <input
                                                    disabled={!canEditProfile}
                                                    type="color"
                                                    value={primaryColor || '#0f766e'}
                                                    onChange={(event) => setPrimaryColor(event.target.value)}
                                                    style={{ width: 48, height: 40, border: '1px solid #d9e6e2', borderRadius: 8, padding: 4, background: 'white' }}
                                                />
                                                <Input
                                                    disabled={!canEditProfile}
                                                    value={primaryColor}
                                                    onChange={(event) => setPrimaryColor(event.target.value)}
                                                    placeholder="#0f766e"
                                                    style={{ width: 160 }}
                                                />
                                            </Space>
                                        </Form.Item>
                                        <Form.Item label="Logo URL">
                                            <Input disabled={!canEditProfile} value={logoUrl} onChange={(event) => setLogoUrl(event.target.value)} placeholder="https://cdn.example.com/logo.svg" />
                                        </Form.Item>
                                        <Form.Item label="Favicon URL">
                                            <Input disabled={!canEditProfile} value={faviconUrl} onChange={(event) => setFaviconUrl(event.target.value)} placeholder="https://cdn.example.com/favicon.ico" />
                                        </Form.Item>
                                    </div>
                                </section>

                                <section className="setup-form-section">
                                    <div className="setup-form-section-heading">
                                        <Text className="card-label">Support</Text>
                                        <Title level={5} style={{ margin: 0 }}>Thông tin hỗ trợ</Title>
                                        <Paragraph style={{ marginBottom: 0 }}>
                                            Các trường này thường được render ở header, footer hoặc block liên hệ của theme storefront.
                                        </Paragraph>
                                    </div>
                                    <div className="setup-form-grid setup-form-grid-3">
                                        <Form.Item label="Hotline hiển thị ở header">
                                            <Input disabled={!canEditProfile} value={supportHotline} onChange={(event) => setSupportHotline(event.target.value)} placeholder="1900 6760 / 0354.466.968" />
                                        </Form.Item>
                                        <Form.Item label="Email chăm sóc khách hàng">
                                            <Input disabled={!canEditProfile} value={supportEmail} onChange={(event) => setSupportEmail(event.target.value)} placeholder="sales@example.com" />
                                        </Form.Item>
                                        <Form.Item label="Vị trí / khu vực hiển thị">
                                            <Input disabled={!canEditProfile} value={supportLocation} onChange={(event) => setSupportLocation(event.target.value)} placeholder="Hà Nội" />
                                        </Form.Item>
                                    </div>
                                </section>

                                <div className="setup-form-actions">
                                    <Text type="secondary">Lưu để cập nhật ngay bước branding trong setup wizard.</Text>
                                    <Button htmlType="submit" type="primary" disabled={!canEditProfile}>
                                        Lưu profile và branding
                                    </Button>
                                </div>
                            </div>
                        </Form>
                    </Card>

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

                <div className="setup-side-column">
                    <Card title="Branding Snapshot">
                        <div className="detail-grid detail-grid-2">
                            <div className="detail-tile">
                                <Text className="detail-label">Company name</Text>
                                <Text strong>{branding.company_name || 'Chưa cấu hình'}</Text>
                            </div>
                            <div className="detail-tile">
                                <Text className="detail-label">Slogan</Text>
                                <Text strong>{branding.slogan || 'Chưa cấu hình'}</Text>
                            </div>
                            <div className="detail-tile">
                                <Text className="detail-label">Primary color</Text>
                                <Space>
                                    <span
                                        style={{
                                            width: 14,
                                            height: 14,
                                            borderRadius: '50%',
                                            background: branding.primary_color || '#d9e6e2',
                                            display: 'inline-block',
                                            border: '1px solid #c9d8d3',
                                        }}
                                    />
                                    <Text strong>{branding.primary_color || 'Chưa cấu hình'}</Text>
                                </Space>
                            </div>
                            <div className="detail-tile detail-tile-wide">
                                <Text className="detail-label">Logo URL</Text>
                                <Text strong>{branding.logo_url || 'Chưa cấu hình'}</Text>
                                <BrandingPreviewImage
                                    src={branding.logo_url}
                                    alt="Logo preview"
                                    frameClassName="branding-image-frame branding-image-frame-logo"
                                    placeholderTitle="Logo placeholder"
                                    placeholderHint={branding.logo_url ? 'Link logo không tải được.' : 'Chưa có logo để xem trước.'}
                                />
                            </div>
                            <div className="detail-tile detail-tile-wide">
                                <Text className="detail-label">Favicon URL</Text>
                                <Text strong>{branding.favicon_url || 'Chưa cấu hình'}</Text>
                                <BrandingPreviewImage
                                    src={branding.favicon_url}
                                    alt="Favicon preview"
                                    frameClassName="branding-image-frame branding-image-frame-favicon"
                                    placeholderTitle="Favicon placeholder"
                                    placeholderHint={branding.favicon_url ? 'Link favicon không tải được.' : 'Chưa có favicon để xem trước.'}
                                />
                            </div>
                            <div className="detail-tile">
                                <Text className="detail-label">Hotline</Text>
                                <Text strong>{branding.support_hotline || 'Chưa cấu hình'}</Text>
                            </div>
                            <div className="detail-tile">
                                <Text className="detail-label">Email CSKH</Text>
                                <Text strong>{branding.support_email || 'Chưa cấu hình'}</Text>
                            </div>
                            <div className="detail-tile detail-tile-wide">
                                <Text className="detail-label">Vị trí hiển thị</Text>
                                <Text strong>{branding.support_location || 'Chưa cấu hình'}</Text>
                            </div>
                        </div>
                    </Card>
                </div>
            </div>
        </Space>
    );
}
