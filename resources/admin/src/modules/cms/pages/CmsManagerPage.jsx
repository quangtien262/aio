import { Suspense, lazy, useState } from 'react';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Popconfirm from 'antd/es/popconfirm';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';

const { Paragraph, Text, Title } = Typography;
const CmsPageFormModal = lazy(() => import('../components/CmsPageFormModal'));
const emptyPageForm = {
    id: null,
    title: '',
    slug: '',
    status: 'draft',
    body: '',
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

export default function CmsManagerPage({ payload, permissions, onCreate, onUpdate, onDelete }) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editingPage, setEditingPage] = useState(emptyPageForm);

    const openCreateModal = () => {
        setEditingPage(emptyPageForm);
        setModalOpen(true);
    };

    const openEditModal = (page) => {
        setEditingPage({
            id: page.id,
            title: page.title,
            slug: page.slug,
            status: page.status,
            body: page.body ?? '',
            website_key: page.website_key ?? '',
            owner_key: page.owner_key ?? '',
            tenant_key: page.tenant_key ?? '',
        });
        setModalOpen(true);
    };

    const handleCloseModal = () => {
        setModalOpen(false);
        setEditingPage(emptyPageForm);
    };

    const handleSavePage = async (nextPayload) => {
        if (editingPage.id) {
            await onUpdate?.(editingPage.id, nextPayload);
        } else {
            await onCreate?.(nextPayload);
        }

        handleCloseModal();
    };

    const columns = [
        { title: 'Title', dataIndex: 'title', key: 'title' },
        { title: 'Slug', dataIndex: 'slug', key: 'slug' },
        {
            title: 'Status',
            dataIndex: 'status',
            key: 'status',
            render: (status) => <Tag color={status === 'published' ? 'green' : 'default'}>{status}</Tag>,
        },
        { title: 'Website', dataIndex: 'website_key', key: 'website_key' },
        { title: 'Owner', dataIndex: 'owner_key', key: 'owner_key' },
        { title: 'Tenant', dataIndex: 'tenant_key', key: 'tenant_key' },
        {
            title: 'Tác vụ',
            key: 'actions',
            render: (_, page) => (
                <Space wrap>
                    <Button size="small" disabled={!permissions?.update} onClick={() => openEditModal(page)}>
                        Sửa
                    </Button>
                    <Popconfirm title="Xóa trang CMS này?" disabled={!permissions?.delete} onConfirm={() => onDelete?.(page.id)}>
                        <Button danger size="small" disabled={!permissions?.delete}>
                            Xóa
                        </Button>
                    </Popconfirm>
                </Space>
            ),
        },
    ];

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card>
                <Text className="card-label">CMS</Text>
                <Title level={3}>CMS Dashboard theo data scope</Title>
                <Paragraph>Dashboard CMS đã lọc theo tenant, owner và website của admin hiện tại, hiển thị tổng quan và danh sách trang nội dung.</Paragraph>
            </Card>

            <Card>
                <div className="metric-grid">
                    {[
                        { label: 'Tổng số trang', value: payload?.total ?? 0 },
                        { label: 'Published', value: payload?.metrics?.published ?? 0 },
                        { label: 'Draft', value: payload?.metrics?.draft ?? 0 },
                    ].map((item) => (
                        <div key={item.label} className="metric-tile">
                            <Text className="metric-label">{item.label}</Text>
                            <Title level={3} style={{ margin: 0 }}>{item.value}</Title>
                        </div>
                    ))}
                </div>
            </Card>

            <Card
                title={`CMS Pages (${payload?.total ?? 0})`}
                extra={(
                    <Button type="primary" disabled={!permissions?.create} onClick={openCreateModal}>
                        Tạo trang
                    </Button>
                )}
            >
                <Table rowKey="id" columns={columns} dataSource={payload?.items ?? []} pagination={false} />
            </Card>

            {modalOpen ? (
                <Suspense fallback={null}>
                    <CmsPageFormModal
                        open={modalOpen}
                        canManage={editingPage.id ? permissions?.update : permissions?.create}
                        editingPage={editingPage}
                        onCancel={handleCloseModal}
                        onSubmit={handleSavePage}
                    />
                </Suspense>
            ) : null}
        </Space>
    );
}
