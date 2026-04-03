import { Suspense, lazy, useState } from 'react';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Popconfirm from 'antd/es/popconfirm';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Typography from 'antd/es/typography';

const { Paragraph, Text, Title } = Typography;
const CatalogProductFormModal = lazy(() => import('../components/CatalogProductFormModal'));
const emptyProductForm = {
    id: null,
    name: '',
    sku: '',
    price: 0,
    stock: 0,
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

export default function CatalogManagerPage({ payload, permissions, onCreate, onUpdate, onDelete }) {
    const [modalOpen, setModalOpen] = useState(false);
    const [editingProduct, setEditingProduct] = useState(emptyProductForm);

    const openCreateModal = () => {
        setEditingProduct(emptyProductForm);
        setModalOpen(true);
    };

    const openEditModal = (product) => {
        setEditingProduct({
            id: product.id,
            name: product.name,
            sku: product.sku,
            price: product.price,
            stock: product.stock,
            website_key: product.website_key ?? '',
            owner_key: product.owner_key ?? '',
            tenant_key: product.tenant_key ?? '',
        });
        setModalOpen(true);
    };

    const handleCloseModal = () => {
        setModalOpen(false);
        setEditingProduct(emptyProductForm);
    };

    const handleSaveProduct = async (nextPayload) => {
        if (editingProduct.id) {
            await onUpdate?.(editingProduct.id, nextPayload);
        } else {
            await onCreate?.(nextPayload);
        }

        handleCloseModal();
    };

    const columns = [
        { title: 'Name', dataIndex: 'name', key: 'name' },
        { title: 'SKU', dataIndex: 'sku', key: 'sku' },
        { title: 'Price', dataIndex: 'price', key: 'price' },
        { title: 'Stock', dataIndex: 'stock', key: 'stock' },
        { title: 'Website', dataIndex: 'website_key', key: 'website_key' },
        { title: 'Owner', dataIndex: 'owner_key', key: 'owner_key' },
        { title: 'Tenant', dataIndex: 'tenant_key', key: 'tenant_key' },
        {
            title: 'Tác vụ',
            key: 'actions',
            render: (_, product) => (
                <Space wrap>
                    <Button size="small" disabled={!permissions?.update} onClick={() => openEditModal(product)}>
                        Sửa
                    </Button>
                    <Popconfirm title="Xóa sản phẩm này?" disabled={!permissions?.delete} onConfirm={() => onDelete?.(product.id)}>
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
                <Text className="card-label">Catalog</Text>
                <Title level={3}>Catalog Dashboard theo data scope</Title>
                <Paragraph>Dashboard Catalog đã lọc theo tenant, owner và website của admin hiện tại, hiển thị tổng quan tồn kho và danh sách sản phẩm.</Paragraph>
            </Card>

            <Card>
                <div className="metric-grid">
                    {[
                        { label: 'Tổng sản phẩm', value: payload?.total ?? 0 },
                        { label: 'In stock', value: payload?.metrics?.in_stock ?? 0 },
                        { label: 'Inventory units', value: payload?.metrics?.inventory_units ?? 0 },
                    ].map((item) => (
                        <div key={item.label} className="metric-tile">
                            <Text className="metric-label">{item.label}</Text>
                            <Title level={3} style={{ margin: 0 }}>{item.value}</Title>
                        </div>
                    ))}
                </div>
            </Card>

            <Card
                title={`Catalog Products (${payload?.total ?? 0})`}
                extra={(
                    <Button type="primary" disabled={!permissions?.create} onClick={openCreateModal}>
                        Tạo sản phẩm
                    </Button>
                )}
            >
                <Table rowKey="id" columns={columns} dataSource={payload?.items ?? []} pagination={false} />
            </Card>

            {modalOpen ? (
                <Suspense fallback={null}>
                    <CatalogProductFormModal
                        open={modalOpen}
                        canManage={editingProduct.id ? permissions?.update : permissions?.create}
                        editingProduct={editingProduct}
                        onCancel={handleCloseModal}
                        onSubmit={handleSaveProduct}
                    />
                </Suspense>
            ) : null}
        </Space>
    );
}
