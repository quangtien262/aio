import { Suspense, lazy, useMemo, useState } from 'react';
import DeleteOutlined from '@ant-design/icons/DeleteOutlined';
import EditOutlined from '@ant-design/icons/EditOutlined';
import EyeOutlined from '@ant-design/icons/EyeOutlined';
import MoreOutlined from '@ant-design/icons/MoreOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import UploadOutlined from '@ant-design/icons/UploadOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Dropdown from 'antd/es/dropdown';
import Empty from 'antd/es/empty';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Row from 'antd/es/row';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import useAdminRouteResource from '../../../shared/hooks/useAdminRouteResource';

const CmsPageFormModal = lazy(() => import('../components/CmsPageFormModal'));
const CmsPostFormModal = lazy(() => import('../components/CmsPostFormModal'));
const CmsCategoryFormModal = lazy(() => import('../components/CmsCategoryFormModal'));
const CmsMenuFormModal = lazy(() => import('../components/CmsMenuFormModal'));
const { Paragraph, Text, Title } = Typography;

const sectionConfigMap = {
    'cms-pages': {
        title: 'Pages',
        description: 'Quản lý page công khai, SEO field cơ bản và preview unpublished.',
        endpoint: '/admin/api/cms/pages',
        permissionView: 'cms.view',
        permissionCreate: 'cms.create',
        permissionUpdate: 'cms.update',
        permissionDelete: 'cms.delete',
        permissionPublish: 'cms.publish',
    },
    'cms-posts': {
        title: 'Posts',
        description: 'Quản lý bài viết, category, featured media và public blog.',
        endpoint: '/admin/api/cms/posts',
        permissionView: 'cms.post.view',
        permissionCreate: 'cms.post.create',
        permissionUpdate: 'cms.post.update',
        permissionDelete: 'cms.post.delete',
        permissionPublish: 'cms.publish',
    },
    'cms-categories': {
        title: 'Categories',
        description: 'Quản lý taxonomy cho post và nội dung phân loại.',
        endpoint: '/admin/api/cms/categories',
        permissionView: 'cms.view',
        permissionCreate: 'cms.category.manage',
        permissionUpdate: 'cms.category.manage',
        permissionDelete: 'cms.category.manage',
        permissionPublish: null,
    },
    'cms-menus': {
        title: 'Menus',
        description: 'Quản lý menu primary/footer để render ra public site.',
        endpoint: '/admin/api/cms/menus',
        permissionView: 'cms.view',
        permissionCreate: 'cms.menu.manage',
        permissionUpdate: 'cms.menu.manage',
        permissionDelete: 'cms.menu.manage',
        permissionPublish: null,
    },
    'cms-media': {
        title: 'Media',
        description: 'Upload và chọn media cơ bản cho page/post.',
        endpoint: '/admin/api/cms/media',
        permissionView: 'cms.view',
        permissionCreate: 'cms.media.manage',
        permissionUpdate: 'cms.media.manage',
        permissionDelete: 'cms.media.manage',
        permissionPublish: null,
    },
};

const emptyPage = {
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
    publish_at: null,
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

const emptyPost = {
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

const emptyCategory = {
    id: null,
    name: '',
    slug: '',
    description: '',
    meta_title: '',
    meta_description: '',
    parent_id: null,
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

const emptyMenu = {
    id: null,
    name: '',
    location: 'primary',
    items: [{ label: '', url: '', target: '_self' }],
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

function renderStatusTag(status) {
    const colorMap = { published: 'green', draft: 'default' };
    const labelMap = { published: 'Đã xuất bản', draft: 'Bản nháp' };
    return <Tag color={colorMap[status] ?? 'default'}>{labelMap[status] ?? status}</Tag>;
}

function formatPublishAt(value) {
    if (!value) {
        return 'Chưa hẹn';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString('vi-VN');
}

function normalizeDatetimeLocal(value) {
    if (!value) {
        return null;
    }

    return String(value).replace(/\.\d+Z$/, '').replace(/Z$/, '').slice(0, 16);
}

function formatBytes(size) {
    if (!size) {
        return '0 B';
    }

    if (size < 1024) {
        return `${size} B`;
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

export default function CmsManagerPage({ moduleMenu, callAdminApi, runAdminAction, currentPermissions }) {
    const sectionKey = moduleMenu?.key ?? 'cms-pages';
    const sectionConfig = sectionConfigMap[sectionKey] ?? sectionConfigMap['cms-pages'];
    const [modalOpen, setModalOpen] = useState(false);
    const [editingRecord, setEditingRecord] = useState(emptyPage);
    const [mediaUpload, setMediaUpload] = useState({ title: '', alt_text: '', website_key: '', owner_key: '', tenant_key: '' });
    const [mediaFile, setMediaFile] = useState(null);

    const sectionPermissions = useMemo(() => ({
        canView: (currentPermissions ?? []).includes(sectionConfig.permissionView),
        canCreate: (currentPermissions ?? []).includes(sectionConfig.permissionCreate),
        canUpdate: (currentPermissions ?? []).includes(sectionConfig.permissionUpdate),
        canDelete: (currentPermissions ?? []).includes(sectionConfig.permissionDelete),
        canPublish: sectionConfig.permissionPublish ? (currentPermissions ?? []).includes(sectionConfig.permissionPublish) : false,
    }), [currentPermissions, sectionConfig]);

    const { data, loading, error, reload } = useAdminRouteResource({
        enabled: sectionPermissions.canView,
        loader: async () => {
            const payload = await callAdminApi(sectionConfig.endpoint);
            return payload.data ?? null;
        },
        deps: [sectionConfig.endpoint, sectionPermissions.canView],
    });

    const scopeHint = sectionKey === 'cms-media'
        ? 'Media nên gắn website_key để tái sử dụng đúng storefront ecommerce; owner/tenant chỉ dùng khi thật sự cần.'
        : 'Workflow hiện chỉ dùng Bản nháp/Đã xuất bản và scope chính của CMS là website_key.';

    const metrics = useMemo(() => {
        if (!data) {
            return [];
        }

        if (sectionKey === 'cms-media') {
            return [
                { label: 'Tổng media', value: data.total ?? 0 },
                { label: 'Tài nguyên sẵn dùng', value: (data.items ?? []).length },
            ];
        }

        if (sectionKey === 'cms-categories' || sectionKey === 'cms-menus') {
            return [{ label: 'Tổng bản ghi', value: data.total ?? 0 }];
        }

        return [
            { label: 'Tổng bản ghi', value: data.total ?? 0 },
            { label: 'Đã xuất bản', value: data.metrics?.published ?? 0 },
            { label: 'Bản nháp', value: data.metrics?.draft ?? 0 },
        ];
    }, [data, sectionKey]);

    const openCreateModal = () => {
        if (sectionKey === 'cms-posts') {
            setEditingRecord(emptyPost);
        } else if (sectionKey === 'cms-categories') {
            setEditingRecord(emptyCategory);
        } else if (sectionKey === 'cms-menus') {
            setEditingRecord(emptyMenu);
        } else {
            setEditingRecord(emptyPage);
        }

        setModalOpen(true);
    };

    const openEditModal = (record) => {
        setEditingRecord(sectionKey === 'cms-pages' || sectionKey === 'cms-posts'
            ? {
                ...record,
                publish_at: normalizeDatetimeLocal(record.publish_at),
            }
            : record);
        setModalOpen(true);
    };

    const handleSaveRecord = async (payload) => {
        const didSave = editingRecord?.id
            ? await runAdminAction(() => callAdminApi(`${sectionConfig.endpoint}/${editingRecord.id}`, { method: 'PUT', body: JSON.stringify(payload) }), `Đã cập nhật ${sectionConfig.title}.`, reload)
            : await runAdminAction(() => callAdminApi(sectionConfig.endpoint, { method: 'POST', body: JSON.stringify(payload) }), `Đã tạo ${sectionConfig.title}.`, reload);

        if (didSave) {
            setModalOpen(false);
        }
    };

    const handleDeleteRecord = async (recordId) => {
        await runAdminAction(() => callAdminApi(`${sectionConfig.endpoint}/${recordId}`, { method: 'DELETE' }), `Đã xóa ${sectionConfig.title}.`, reload);
    };

    const confirmDeleteRecord = (recordId) => {
        Modal.confirm({
            title: 'Xóa bản ghi này?',
            content: 'Thao tác này không thể hoàn tác.',
            okText: 'Xóa',
            okButtonProps: { danger: true },
            cancelText: 'Hủy',
            onOk: () => handleDeleteRecord(recordId),
        });
    };

    const handleUploadMedia = async () => {
        if (!mediaFile) {
            return;
        }

        const formData = new FormData();
        formData.append('file', mediaFile);
        Object.entries(mediaUpload).forEach(([key, value]) => {
            if (value) {
                formData.append(key, value);
            }
        });

        const didUpload = await runAdminAction(() => callAdminApi('/admin/api/cms/media', { method: 'POST', body: formData }), 'Đã upload media CMS.', reload);

        if (didUpload) {
            setMediaFile(null);
            setMediaUpload({ title: '', alt_text: '', website_key: '', owner_key: '', tenant_key: '' });
        }
    };

    const renderActions = (record) => {
        const actionItems = [];

        if (record.public_url) {
            actionItems.push({
                key: 'public',
                label: 'Mở public',
                icon: <EyeOutlined />,
            });
        }

        if (record.preview_url && sectionPermissions.canPublish) {
            actionItems.push({
                key: 'preview',
                label: 'Xem preview',
                icon: <EyeOutlined />,
            });
        }

        if (sectionKey !== 'cms-media') {
            actionItems.push({
                key: 'edit',
                label: 'Chỉnh sửa',
                icon: <EditOutlined />,
                disabled: !sectionPermissions.canUpdate,
            });
        } else {
            actionItems.push({
                key: 'open',
                label: 'Mở media',
                icon: <EyeOutlined />,
            });
        }

        actionItems.push({
            key: 'delete',
            label: 'Xóa',
            icon: <DeleteOutlined />,
            danger: true,
            disabled: !sectionPermissions.canDelete,
        });

        const handleActionClick = ({ key }) => {
            if (key === 'public' && record.public_url) {
                window.open(record.public_url, '_blank', 'noopener,noreferrer');
                return;
            }

            if (key === 'preview' && record.preview_url) {
                window.open(record.preview_url, '_blank', 'noopener,noreferrer');
                return;
            }

            if (key === 'open' && record.file_url) {
                window.open(record.file_url, '_blank', 'noopener,noreferrer');
                return;
            }

            if (key === 'edit') {
                openEditModal(record);
                return;
            }

            if (key === 'delete') {
                confirmDeleteRecord(record.id);
            }
        };

        return (
            <Dropdown menu={{ items: actionItems, onClick: handleActionClick }} trigger={['click']}>
                <Button size="small" icon={<MoreOutlined />}>Tác vụ</Button>
            </Dropdown>
        );
    };

    const columns = useMemo(() => {
        if (sectionKey === 'cms-pages') {
            return [
                { title: 'Title', dataIndex: 'title', key: 'title' },
                { title: 'Slug', dataIndex: 'slug', key: 'slug' },
                { title: 'Status', dataIndex: 'status', key: 'status', render: renderStatusTag },
                { title: 'Publish At', dataIndex: 'publish_at', key: 'publish_at', render: formatPublishAt },
                { title: 'SEO', key: 'seo', render: (_, record) => record.meta_title || record.meta_description ? <Text type="secondary">{record.meta_title || record.meta_description}</Text> : 'Chưa có' },
                { title: 'Tác vụ', key: 'actions', render: (_, record) => renderActions(record) },
            ];
        }

        if (sectionKey === 'cms-posts') {
            return [
                { title: 'Post', dataIndex: 'title', key: 'title' },
                { title: 'Slug', dataIndex: 'slug', key: 'slug' },
                { title: 'Category', dataIndex: 'category_name', key: 'category_name', render: (value) => value || 'Chưa phân loại' },
                { title: 'Status', dataIndex: 'status', key: 'status', render: renderStatusTag },
                { title: 'Publish At', dataIndex: 'publish_at', key: 'publish_at', render: formatPublishAt },
                { title: 'Tác vụ', key: 'actions', render: (_, record) => renderActions(record) },
            ];
        }

        if (sectionKey === 'cms-categories') {
            return [
                { title: 'Category', dataIndex: 'name', key: 'name' },
                { title: 'Slug', dataIndex: 'slug', key: 'slug' },
                { title: 'Parent', dataIndex: 'parent_id', key: 'parent_id', render: (value) => value || '-' },
                { title: 'SEO', key: 'seo', render: (_, record) => record.meta_title || record.meta_description ? <Text type="secondary">{record.meta_title || record.meta_description}</Text> : 'Chưa có' },
                { title: 'Tác vụ', key: 'actions', render: (_, record) => renderActions(record) },
            ];
        }

        if (sectionKey === 'cms-menus') {
            return [
                { title: 'Menu', dataIndex: 'name', key: 'name' },
                { title: 'Location', dataIndex: 'location', key: 'location', render: (value) => <Tag>{value}</Tag> },
                { title: 'Items', key: 'items', render: (_, record) => (record.items ?? []).length },
                { title: 'Tác vụ', key: 'actions', render: (_, record) => renderActions(record) },
            ];
        }

        return [
            {
                title: 'Media',
                key: 'media',
                render: (_, record) => (
                    <Space>
                        <img src={record.file_url} alt={record.title} style={{ width: 56, height: 56, objectFit: 'cover', borderRadius: 12, border: '1px solid #dbe7e4' }} />
                        <Space direction="vertical" size={0}>
                            <Text strong>{record.title}</Text>
                            <Text type="secondary">{record.alt_text || record.mime_type || 'Media asset'}</Text>
                        </Space>
                    </Space>
                ),
            },
            { title: 'Dung lượng', dataIndex: 'size', key: 'size', render: formatBytes },
            { title: 'URL', dataIndex: 'file_url', key: 'file_url', render: (value) => <Text copyable>{value}</Text> },
            {
                title: 'Tác vụ',
                key: 'actions',
                render: (_, record) => renderActions(record),
            },
        ];
    }, [sectionKey, sectionPermissions.canDelete, sectionPermissions.canPublish, sectionPermissions.canUpdate]);

    const renderModal = () => {
        if (!modalOpen) {
            return null;
        }

        if (sectionKey === 'cms-posts') {
            return (
                <Suspense fallback={null}>
                    <CmsPostFormModal
                        open={modalOpen}
                        canManage={sectionPermissions.canCreate || sectionPermissions.canUpdate}
                        editingPost={editingRecord}
                        mediaOptions={data?.media ?? []}
                        categoryOptions={data?.categories ?? []}
                        onCancel={() => setModalOpen(false)}
                        onSubmit={handleSaveRecord}
                    />
                </Suspense>
            );
        }

        if (sectionKey === 'cms-categories') {
            return (
                <Suspense fallback={null}>
                    <CmsCategoryFormModal
                        open={modalOpen}
                        canManage={sectionPermissions.canCreate || sectionPermissions.canUpdate}
                        editingCategory={editingRecord}
                        parentOptions={(data?.items ?? []).filter((item) => item.id !== editingRecord?.id).map((item) => ({ label: item.name, value: item.id }))}
                        onCancel={() => setModalOpen(false)}
                        onSubmit={handleSaveRecord}
                    />
                </Suspense>
            );
        }

        if (sectionKey === 'cms-menus') {
            return (
                <Suspense fallback={null}>
                    <CmsMenuFormModal
                        open={modalOpen}
                        canManage={sectionPermissions.canCreate || sectionPermissions.canUpdate}
                        editingMenu={editingRecord}
                        locationOptions={data?.locations ?? []}
                        callAdminApi={callAdminApi}
                        runAdminAction={runAdminAction}
                        onLocationsChanged={reload}
                        onCancel={() => setModalOpen(false)}
                        onSubmit={handleSaveRecord}
                    />
                </Suspense>
            );
        }

        return (
            <Suspense fallback={null}>
                <CmsPageFormModal
                    open={modalOpen}
                    canManage={sectionPermissions.canCreate || sectionPermissions.canUpdate}
                    editingPage={editingRecord}
                    mediaOptions={data?.media ?? []}
                    callAdminApi={callAdminApi}
                    onCancel={() => setModalOpen(false)}
                    onSubmit={handleSaveRecord}
                />
            </Suspense>
        );
    };

    if (!sectionPermissions.canView) {
        return <Alert type="warning" showIcon message="Tài khoản hiện tại chưa có quyền truy cập khu vực CMS này." />;
    }

    if (loading) {
        return <Card loading title={sectionConfig.title} />;
    }

    if (error) {
        return <Alert type="error" showIcon message={error} />;
    }

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card className="hero-card">
                <Text className="card-label">CMS Workspace</Text>
                <Title level={3}>{sectionConfig.title}</Title>
                <Paragraph style={{ marginBottom: 0 }}>{sectionConfig.description}</Paragraph>
            </Card>

            {metrics.length ? (
                <Row gutter={[12, 12]}>
                    {metrics.map((item) => (
                        <Col key={item.label} xs={24} sm={12} lg={8}>
                            <Card size="small">
                                <Text className="detail-label">{item.label}</Text>
                                <Title level={4} style={{ margin: 0 }}>{item.value}</Title>
                            </Card>
                        </Col>
                    ))}
                    <Col xs={24}>
                        <Alert type="info" showIcon message={scopeHint} />
                    </Col>
                </Row>
            ) : null}

            {sectionKey === 'cms-media' ? (
                <Card title="Upload Media" extra={<Button type="primary" icon={<UploadOutlined />} disabled={!sectionPermissions.canCreate || !mediaFile} onClick={handleUploadMedia}>Upload media</Button>}>
                    <Row gutter={[12, 12]}>
                        <Col xs={24} md={8}><Input value={mediaUpload.title} onChange={(event) => setMediaUpload((current) => ({ ...current, title: event.target.value }))} placeholder="Tiêu đề media" /></Col>
                        <Col xs={24} md={8}><Input value={mediaUpload.alt_text} onChange={(event) => setMediaUpload((current) => ({ ...current, alt_text: event.target.value }))} placeholder="Alt text" /></Col>
                        <Col xs={24} md={8}><input type="file" onChange={(event) => setMediaFile(event.target.files?.[0] ?? null)} /></Col>
                        <Col xs={24} md={8}><Input value={mediaUpload.website_key} onChange={(event) => setMediaUpload((current) => ({ ...current, website_key: event.target.value }))} placeholder="storefront-main" /></Col>
                        <Col xs={24} md={8}><Input value={mediaUpload.owner_key} onChange={(event) => setMediaUpload((current) => ({ ...current, owner_key: event.target.value }))} placeholder="owner-system" /></Col>
                        <Col xs={24} md={8}><Input value={mediaUpload.tenant_key} onChange={(event) => setMediaUpload((current) => ({ ...current, tenant_key: event.target.value }))} placeholder="tenant-a" /></Col>
                    </Row>
                </Card>
            ) : null}

            <Card className="admin-table-card" title={`${sectionConfig.title} (${data?.total ?? 0})`} extra={sectionKey !== 'cms-media' ? <Button type="primary" icon={<PlusOutlined />} disabled={!sectionPermissions.canCreate} onClick={openCreateModal}>{`Tạo ${sectionConfig.title}`}</Button> : null}>
                {(data?.items ?? []).length ? (
                    <Table rowKey="id" columns={columns} dataSource={data?.items ?? []} pagination={{ pageSize: 10, hideOnSinglePage: true }} scroll={{ x: 980 }} />
                ) : (
                    <Empty description={`Chưa có dữ liệu cho ${sectionConfig.title}.`} />
                )}
            </Card>

            {renderModal()}
        </Space>
    );
}
