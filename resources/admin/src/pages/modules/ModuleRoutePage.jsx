import { Suspense, lazy, useMemo } from 'react';
import Alert from 'antd/es/alert';
import Card from 'antd/es/card';
import Empty from 'antd/es/empty';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import useAdminRouteResource from '../../shared/hooks/useAdminRouteResource';
import NewsletterSubscribersRoutePage from '../routes/NewsletterSubscribersRoutePage';
import SetupRoutePage from '../routes/SetupRoutePage';

const { Paragraph, Title, Text } = Typography;
const CmsManagerPage = lazy(() => import('../../modules/cms/pages/CmsManagerPage'));
const CatalogManagerPage = lazy(() => import('../../modules/catalog/pages/CatalogManagerPage'));
const ProjectManagerPage = lazy(() => import('../../modules/project/pages/ProjectManagerPage'));

export default function ModuleRoutePage({ moduleMenu, modulePayload, callAdminApi, runAdminAction, currentPermissions }) {
    const resourceEndpointMap = {
        catalog: null,
    };

    const modulePermissions = useMemo(() => ({
        create: (currentPermissions ?? []).includes(`${modulePayload?.key}.create`),
        update: (currentPermissions ?? []).includes(`${modulePayload?.key}.update`),
        delete: (currentPermissions ?? []).includes(`${modulePayload?.key}.delete`),
    }), [currentPermissions, modulePayload?.key]);

    const { data, loading, error, reload } = useAdminRouteResource({
        enabled: Boolean(modulePayload && resourceEndpointMap[modulePayload.key]),
        loader: async () => {
            const payload = await callAdminApi(resourceEndpointMap[modulePayload.key]);

            return payload.data ?? null;
        },
        deps: [modulePayload?.key],
    });

    if (!modulePayload) {
        return (
            <Card>
                <Empty description="Module chưa sẵn sàng hoặc chưa được đồng bộ dữ liệu." />
            </Card>
        );
    }

    if (modulePayload.key === 'cms') {
        if (moduleMenu?.key === 'cms-newsletter') {
            return (
                <NewsletterSubscribersRoutePage
                    canAccess
                    callAdminApi={callAdminApi}
                />
            );
        }

        if (moduleMenu?.key === 'cms-setup') {
            return (
                <SetupRoutePage
                    canAccess
                    canComplete={(currentPermissions ?? []).includes('setup.complete')}
                    callAdminApi={callAdminApi}
                    runAdminAction={runAdminAction}
                />
            );
        }

        return (
            <Suspense fallback={<Card loading title={moduleMenu?.label ?? modulePayload.name} />}>
                <CmsManagerPage
                    moduleMenu={moduleMenu}
                    modulePayload={modulePayload}
                    callAdminApi={callAdminApi}
                    runAdminAction={runAdminAction}
                    currentPermissions={currentPermissions}
                />
            </Suspense>
        );
    }

    if (modulePayload.key === 'catalog') {
        return (
            <Suspense fallback={<Card loading title={moduleMenu?.label ?? modulePayload.name} />}>
                <CatalogManagerPage
                    callAdminApi={callAdminApi}
                    runAdminAction={runAdminAction}
                    currentPermissions={currentPermissions}
                />
            </Suspense>
        );
    }

    if (modulePayload.key === 'project') {
        return (
            <Suspense fallback={<Card loading title={moduleMenu?.label ?? modulePayload.name} />}>
                <ProjectManagerPage
                    moduleMenu={moduleMenu}
                    modulePayload={modulePayload}
                    callAdminApi={callAdminApi}
                    runAdminAction={runAdminAction}
                    currentPermissions={currentPermissions}
                />
            </Suspense>
        );
    }

    if (loading) {
        return <Card loading title={moduleMenu?.label ?? modulePayload.name} />;
    }

    if (error) {
        return <Alert type="error" showIcon message={error} />;
    }

    const crudHandlers = {
        onCreate: modulePayload?.key === 'cms'
            ? (payload) => runAdminAction(() => callAdminApi('/admin/api/cms/pages', { method: 'POST', body: JSON.stringify(payload) }), 'Đã tạo trang CMS.', reload)
            : modulePayload?.key === 'catalog'
                ? (payload) => runAdminAction(() => callAdminApi('/admin/api/catalog/products', { method: 'POST', body: JSON.stringify(payload) }), 'Đã tạo sản phẩm catalog.', reload)
                : undefined,
        onUpdate: modulePayload?.key === 'cms'
            ? (id, payload) => runAdminAction(() => callAdminApi(`/admin/api/cms/pages/${id}`, { method: 'PUT', body: JSON.stringify(payload) }), 'Đã cập nhật trang CMS.', reload)
            : modulePayload?.key === 'catalog'
                ? (id, payload) => runAdminAction(() => callAdminApi(`/admin/api/catalog/products/${id}`, { method: 'PUT', body: JSON.stringify(payload) }), 'Đã cập nhật sản phẩm catalog.', reload)
                : undefined,
        onDelete: modulePayload?.key === 'cms'
            ? (id) => runAdminAction(() => callAdminApi(`/admin/api/cms/pages/${id}`, { method: 'DELETE' }), 'Đã xóa trang CMS.', reload)
            : modulePayload?.key === 'catalog'
                ? (id) => runAdminAction(() => callAdminApi(`/admin/api/catalog/products/${id}`, { method: 'DELETE' }), 'Đã xóa sản phẩm catalog.', reload)
                : undefined,
    };

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card>
                <Space direction="vertical" size={4}>
                    <Text className="card-label">Module Page</Text>
                    <Title level={3} style={{ margin: 0 }}>{moduleMenu?.label ?? modulePayload.name}</Title>
                    <Paragraph style={{ marginBottom: 0 }}>{modulePayload.description}</Paragraph>
                </Space>
            </Card>

            <Card title="Module Runtime">
                <div className="detail-grid detail-grid-2">
                    <div className="detail-tile">
                        <Text className="detail-label">Module key</Text>
                        <Text strong>{modulePayload.key}</Text>
                    </div>
                    <div className="detail-tile">
                        <Text className="detail-label">Status</Text>
                        <div>
                            <Tag color={modulePayload.is_enabled ? 'green' : 'default'}>{modulePayload.status}</Tag>
                        </div>
                    </div>
                    <div className="detail-tile">
                        <Text className="detail-label">Installed version</Text>
                        <Text strong>{modulePayload.installed_version ?? 'N/A'}</Text>
                    </div>
                    <div className="detail-tile">
                        <Text className="detail-label">Latest version</Text>
                        <Text strong>{modulePayload.latest_version}</Text>
                    </div>
                    <div className="detail-tile">
                        <Text className="detail-label">Route</Text>
                        <Text strong>{moduleMenu?.route ?? 'N/A'}</Text>
                    </div>
                    <div className="detail-tile">
                        <Text className="detail-label">Permissions</Text>
                        <Text strong>{(modulePayload.permissions ?? []).join(', ') || 'N/A'}</Text>
                    </div>
                    <div className="detail-tile">
                        <Text className="detail-label">Dependencies</Text>
                        <Text strong>{(modulePayload.dependencies ?? []).join(', ') || 'None'}</Text>
                    </div>
                    <div className="detail-tile">
                        <Text className="detail-label">Website types</Text>
                        <Text strong>{(modulePayload.website_types ?? []).join(', ') || 'N/A'}</Text>
                    </div>
                </div>
            </Card>

            <Card title="Changelog">
                {(modulePayload.changelog ?? []).length ? (
                    <Space direction="vertical" size={12} style={{ width: '100%' }}>
                        {(modulePayload.changelog ?? []).map((entry) => (
                            <div key={`${modulePayload.key}-${entry.version}`}>
                                <Text strong>{entry.version}</Text>
                                {entry.date ? <Text type="secondary"> {' '}({entry.date})</Text> : null}
                                <Paragraph style={{ marginBottom: 0 }}>{(entry.notes ?? []).join(' | ')}</Paragraph>
                            </div>
                        ))}
                    </Space>
                ) : (
                    <Empty description="Chưa có changelog." />
                )}
            </Card>
        </Space>
    );
}
