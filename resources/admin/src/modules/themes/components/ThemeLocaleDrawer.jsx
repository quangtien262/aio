import { useEffect, useMemo, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Drawer from 'antd/es/drawer';
import Empty from 'antd/es/empty';
import Input from 'antd/es/input';
import List from 'antd/es/list';
import Modal from 'antd/es/modal';
import Space from 'antd/es/space';
import Spin from 'antd/es/spin';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';

const { Paragraph, Text } = Typography;

function dispatchLocalizationUpdate(detail) {
    window.dispatchEvent(new CustomEvent('aio:frontend-localization-changed', { detail }));
}

export default function ThemeLocaleDrawer({ open, theme, canManageLocales, callAdminApi, runAdminAction, onClose }) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [locales, setLocales] = useState([]);
    const [availableBuiltins, setAvailableBuiltins] = useState([]);
    const [defaultLocale, setDefaultLocale] = useState('vi');
    const [fallbackLocale, setFallbackLocale] = useState('vi');
    const [sourceLocale, setSourceLocale] = useState('vi');
    const [createModalOpen, setCreateModalOpen] = useState(false);
    const [formState, setFormState] = useState({ code: '', name: '', native_name: '' });

    const sortedLocales = useMemo(
        () => [...locales].sort((left, right) => Number(right.is_default) - Number(left.is_default) || left.sort_order - right.sort_order || left.code.localeCompare(right.code)),
        [locales],
    );

    const applyPayload = (payload) => {
        setLocales(payload?.locales ?? []);
        setAvailableBuiltins(payload?.available_builtin_locales ?? []);
        setDefaultLocale(payload?.default_locale ?? 'vi');
        setFallbackLocale(payload?.fallback_locale ?? 'vi');
        setSourceLocale(payload?.source_locale ?? 'vi');
        dispatchLocalizationUpdate(payload ?? {});
    };

    const loadLocales = async () => {
        if (!open || !theme?.key) {
            return;
        }

        try {
            setLoading(true);
            setError(null);
            const payload = await callAdminApi(`/admin/api/themes/locales?theme_key=${encodeURIComponent(theme.key)}`);
            applyPayload(payload.data ?? {});
        } catch (nextError) {
            setError(nextError instanceof Error ? nextError.message : 'Không tải được cấu hình ngôn ngữ.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (!open) {
            return;
        }

        void loadLocales();
    }, [open, theme?.key]);

    const handleUpdateLocale = async (code, payload, successMessage) => {
        let responsePayload = null;

        await runAdminAction(
            async () => {
                responsePayload = await callAdminApi(`/admin/api/themes/locales/${encodeURIComponent(code)}`, {
                    method: 'PUT',
                    body: JSON.stringify({ theme_key: theme?.key, ...payload }),
                });
            },
            successMessage,
            async () => {
                applyPayload(responsePayload?.data ?? {});
            },
        );
    };

    const handleQuickAdd = async (code) => {
        let responsePayload = null;

        await runAdminAction(
            async () => {
                responsePayload = await callAdminApi('/admin/api/themes/locales', {
                    method: 'POST',
                    body: JSON.stringify({ theme_key: theme?.key, code }),
                });
            },
            'Đã thêm ngôn ngữ built-in vào hệ thống.',
            async () => {
                applyPayload(responsePayload?.data ?? {});
            },
        );
    };

    const handleCreateLocale = async () => {
        let responsePayload = null;

        await runAdminAction(
            async () => {
                responsePayload = await callAdminApi('/admin/api/themes/locales', {
                    method: 'POST',
                    body: JSON.stringify({ theme_key: theme?.key, ...formState }),
                });
            },
            'Đã thêm ngôn ngữ custom vào hệ thống.',
            async () => {
                applyPayload(responsePayload?.data ?? {});
                setCreateModalOpen(false);
                setFormState({ code: '', name: '', native_name: '' });
            },
        );
    };

    return (
        <>
            <Drawer
                title={theme ? `Quản lý ngôn ngữ: ${theme.name}` : 'Quản lý ngôn ngữ'}
                open={open}
                onClose={onClose}
                width={760}
                destroyOnHidden
                extra={(
                    <Space>
                        <Button onClick={() => setCreateModalOpen(true)} disabled={!canManageLocales}>Thêm locale custom</Button>
                    </Space>
                )}
            >
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Paragraph style={{ marginBottom: 0 }}>
                        Danh sách này quản lý các locale storefront đang hoạt động, locale mặc định đang redirect từ trang chủ, và đánh dấu locale nào đã được theme hỗ trợ sẵn.
                    </Paragraph>

                    <Space size={8} wrap>
                        <Tag color="blue">Default: {defaultLocale.toUpperCase()}</Tag>
                        <Tag>Source: {sourceLocale.toUpperCase()}</Tag>
                        <Tag>Fallback: {fallbackLocale.toUpperCase()}</Tag>
                    </Space>

                    {availableBuiltins.length ? (
                        <Space direction="vertical" size={8} style={{ width: '100%' }}>
                            <Text className="card-label">Locale built-in của theme</Text>
                            <Space size={8} wrap>
                                {availableBuiltins.map((localeItem) => (
                                    <Button key={localeItem.code} size="small" onClick={() => handleQuickAdd(localeItem.code)} disabled={!canManageLocales}>
                                        Thêm {localeItem.code.toUpperCase()} · {localeItem.name}
                                    </Button>
                                ))}
                            </Space>
                        </Space>
                    ) : null}

                    {error ? <Alert type="error" showIcon message={error} /> : null}

                    {loading ? (
                        <div style={{ padding: '48px 0', textAlign: 'center' }}>
                            <Spin />
                        </div>
                    ) : sortedLocales.length ? (
                        <List
                            dataSource={sortedLocales}
                            renderItem={(localeItem) => (
                                <List.Item
                                    key={localeItem.code}
                                    actions={[
                                        !localeItem.is_default ? (
                                            <Button key="default" type="link" disabled={!canManageLocales} onClick={() => handleUpdateLocale(localeItem.code, { is_default: true }, 'Đã đổi ngôn ngữ mặc định storefront.')}>Đặt mặc định</Button>
                                        ) : null,
                                        <Button
                                            key="publish"
                                            type="link"
                                            disabled={!canManageLocales}
                                            onClick={() => handleUpdateLocale(localeItem.code, { is_published: !localeItem.is_published }, localeItem.is_published ? 'Đã chuyển locale về draft.' : 'Đã publish locale storefront.')}
                                        >
                                            {localeItem.is_published ? 'Chuyển draft' : 'Publish'}
                                        </Button>,
                                        <Button
                                            key="active"
                                            type="link"
                                            disabled={!canManageLocales}
                                            onClick={() => handleUpdateLocale(localeItem.code, { is_active: !localeItem.is_active }, localeItem.is_active ? 'Đã tắt locale khỏi storefront.' : 'Đã bật locale cho storefront.')}
                                        >
                                            {localeItem.is_active ? 'Tắt' : 'Bật'}
                                        </Button>,
                                    ].filter(Boolean)}
                                >
                                    <Space direction="vertical" size={4} style={{ width: '100%' }}>
                                        <Space size={8} wrap>
                                            <Text strong>{localeItem.code.toUpperCase()}</Text>
                                            <Text>{localeItem.name}</Text>
                                            {localeItem.native_name ? <Text type="secondary">{localeItem.native_name}</Text> : null}
                                        </Space>
                                        <Space size={8} wrap>
                                            {localeItem.is_default ? <Tag color="blue">Default</Tag> : null}
                                            {localeItem.is_source ? <Tag color="gold">Source</Tag> : null}
                                            {localeItem.is_published ? <Tag color="green">Published</Tag> : <Tag>Draft</Tag>}
                                            {localeItem.is_active ? <Tag color="cyan">Active</Tag> : <Tag>Inactive</Tag>}
                                            {localeItem.is_theme_supported ? <Tag color="purple">Built-in theme support</Tag> : <Tag>Custom locale</Tag>}
                                        </Space>
                                    </Space>
                                </List.Item>
                            )}
                        />
                    ) : (
                        <Empty description="Chưa có locale storefront nào." />
                    )}
                </Space>
            </Drawer>

            <Modal
                title="Thêm locale custom"
                open={createModalOpen}
                onCancel={() => setCreateModalOpen(false)}
                onOk={handleCreateLocale}
                okText="Thêm locale"
                destroyOnHidden
            >
                <Space direction="vertical" size={12} style={{ width: '100%' }}>
                    <Input
                        value={formState.code}
                        onChange={(event) => setFormState((current) => ({ ...current, code: event.target.value }))}
                        placeholder="Mã locale, ví dụ: en hoặc ja"
                    />
                    <Input
                        value={formState.name}
                        onChange={(event) => setFormState((current) => ({ ...current, name: event.target.value }))}
                        placeholder="Tên hiển thị"
                    />
                    <Input
                        value={formState.native_name}
                        onChange={(event) => setFormState((current) => ({ ...current, native_name: event.target.value }))}
                        placeholder="Tên bản địa"
                    />
                </Space>
            </Modal>
        </>
    );
}
