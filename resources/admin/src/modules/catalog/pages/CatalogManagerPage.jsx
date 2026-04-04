import { Suspense, lazy, useMemo, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Popconfirm from 'antd/es/popconfirm';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tabs from 'antd/es/tabs';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import useAdminRouteResource from '../../../shared/hooks/useAdminRouteResource';

const { Paragraph, Text, Title } = Typography;
const CatalogProductFormModal = lazy(() => import('../components/CatalogProductFormModal'));
const CatalogCategoryFormModal = lazy(() => import('../components/CatalogCategoryFormModal'));
const SiteBannerFormModal = lazy(() => import('../components/SiteBannerFormModal'));
const emptyProductForm = {
    id: null,
    catalog_category_id: null,
    name: '',
    slug: '',
    sku: '',
    price: 0,
    original_price: null,
    stock: 0,
    short_description: '',
    detail_content: '',
    highlights: '',
    usage_terms: '',
    usage_location: '',
    image_url: '',
    gallery_images: [],
    sold_count: 0,
    deal_end_at: '',
    is_featured: false,
    sort_order: 0,
    is_active: true,
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

const emptyCategoryForm = {
    id: null,
    parent_id: null,
    name: '',
    slug: '',
    description: '',
    image_url: '',
    sort_order: 0,
    is_active: true,
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

const emptyBannerForm = {
    id: null,
    theme_key: 'TH0001',
    placement: 'hero-side',
    title: '',
    subtitle: '',
    image_url: '',
    link_url: '',
    badge: '',
    eyebrow: '',
    summary: '',
    button_label: '',
    sort_order: 0,
    is_active: true,
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

export default function CatalogManagerPage({ callAdminApi, runAdminAction, currentPermissions }) {
    const [activeTabKey, setActiveTabKey] = useState('products');
    const [productModalOpen, setProductModalOpen] = useState(false);
    const [categoryModalOpen, setCategoryModalOpen] = useState(false);
    const [bannerModalOpen, setBannerModalOpen] = useState(false);
    const [editingProduct, setEditingProduct] = useState(emptyProductForm);
    const [editingCategory, setEditingCategory] = useState(emptyCategoryForm);
    const [editingBanner, setEditingBanner] = useState(emptyBannerForm);

    const permissions = useMemo(() => ({
        catalogCreate: (currentPermissions ?? []).includes('catalog.create'),
        catalogUpdate: (currentPermissions ?? []).includes('catalog.update'),
        catalogDelete: (currentPermissions ?? []).includes('catalog.delete'),
    }), [currentPermissions]);

    const { data, loading, error, reload } = useAdminRouteResource({
        enabled: true,
        loader: async () => {
            const [productsPayload, categoriesPayload, bannersPayload] = await Promise.all([
                callAdminApi('/admin/api/catalog/products'),
                callAdminApi('/admin/api/catalog/categories'),
                callAdminApi('/admin/api/site-banners'),
            ]);

            return {
                products: productsPayload.data ?? { items: [], total: 0, metrics: {} },
                categories: categoriesPayload.data ?? { items: [], total: 0 },
                banners: bannersPayload.data ?? { items: [], total: 0 },
            };
        },
    });

    const categoryOptions = useMemo(() => (data?.categories?.items ?? []).map((category) => ({
        label: category.parent_name ? `${category.parent_name} / ${category.name}` : category.name,
        value: category.id,
    })), [data?.categories?.items]);

    const openProductModal = (product = null) => {
        setEditingProduct(product ? {
            id: product.id,
            catalog_category_id: product.catalog_category_id ?? null,
            name: product.name,
            slug: product.slug ?? '',
            sku: product.sku,
            price: product.price,
            original_price: product.original_price,
            stock: product.stock,
            short_description: product.short_description ?? '',
            detail_content: product.detail_content ?? '',
            highlights: product.highlights ?? '',
            usage_terms: product.usage_terms ?? '',
            usage_location: product.usage_location ?? '',
            image_url: product.image_url ?? '',
            gallery_images: product.gallery_images ?? [],
            sold_count: product.sold_count ?? 0,
            deal_end_at: product.deal_end_at ? product.deal_end_at.slice(0, 16) : '',
            is_featured: Boolean(product.is_featured),
            sort_order: product.sort_order ?? 0,
            is_active: product.is_active ?? true,
            website_key: product.website_key ?? '',
            owner_key: product.owner_key ?? '',
            tenant_key: product.tenant_key ?? '',
        } : emptyProductForm);
        setProductModalOpen(true);
    };

    const openCategoryModal = (category = null) => {
        setEditingCategory(category ? {
            id: category.id,
            parent_id: category.parent_id ?? null,
            name: category.name,
            slug: category.slug ?? '',
            description: category.description ?? '',
            image_url: category.image_url ?? '',
            sort_order: category.sort_order ?? 0,
            is_active: category.is_active ?? true,
            website_key: category.website_key ?? '',
            owner_key: category.owner_key ?? '',
            tenant_key: category.tenant_key ?? '',
        } : emptyCategoryForm);
        setCategoryModalOpen(true);
    };

    const openBannerModal = (banner = null) => {
        setEditingBanner(banner ? {
            id: banner.id,
            theme_key: banner.theme_key ?? 'TH0001',
            placement: banner.placement ?? 'hero-side',
            title: banner.title ?? '',
            subtitle: banner.subtitle ?? '',
            image_url: banner.image_url ?? '',
            link_url: banner.link_url ?? '',
            badge: banner.badge ?? '',
            eyebrow: banner.eyebrow ?? '',
            summary: banner.summary ?? '',
            button_label: banner.button_label ?? '',
            sort_order: banner.sort_order ?? 0,
            is_active: banner.is_active ?? true,
            website_key: banner.website_key ?? '',
            owner_key: banner.owner_key ?? '',
            tenant_key: banner.tenant_key ?? '',
        } : emptyBannerForm);
        setBannerModalOpen(true);
    };

    const runCrud = async ({ endpoint, method, payload, successMessage }) => runAdminAction(
        () => callAdminApi(endpoint, { method, body: payload ? JSON.stringify(payload) : undefined }),
        successMessage,
        reload,
    );

    const productColumns = [
        { title: 'Tên', dataIndex: 'name', key: 'name' },
        { title: 'Danh mục', dataIndex: 'category_name', key: 'category_name', render: (value) => value || 'Chưa gắn' },
        { title: 'SKU', dataIndex: 'sku', key: 'sku' },
        { title: 'Giá', dataIndex: 'price', key: 'price', render: (value) => Number(value ?? 0).toLocaleString('vi-VN') },
        { title: 'Tồn', dataIndex: 'stock', key: 'stock' },
        { title: 'Đã mua', dataIndex: 'sold_count', key: 'sold_count' },
        { title: 'Nổi bật', dataIndex: 'is_featured', key: 'is_featured', render: (value) => value ? <Tag color="gold">featured</Tag> : <Tag>normal</Tag> },
        {
            title: 'Tác vụ',
            key: 'actions',
            render: (_, product) => (
                <Space wrap>
                    <Button size="small" disabled={!permissions.catalogUpdate} onClick={() => openProductModal(product)}>Sửa</Button>
                    <Popconfirm title="Xóa sản phẩm này?" disabled={!permissions.catalogDelete} onConfirm={() => runCrud({ endpoint: `/admin/api/catalog/products/${product.id}`, method: 'DELETE', successMessage: 'Đã xóa sản phẩm catalog.' })}>
                        <Button danger size="small" disabled={!permissions.catalogDelete}>Xóa</Button>
                    </Popconfirm>
                </Space>
            ),
        },
    ];

    const categoryColumns = [
        { title: 'Tên', dataIndex: 'name', key: 'name' },
        { title: 'Cha', dataIndex: 'parent_name', key: 'parent_name', render: (value) => value || 'Danh mục gốc' },
        { title: 'Slug', dataIndex: 'slug', key: 'slug' },
        { title: 'Con', dataIndex: 'children_count', key: 'children_count' },
        { title: 'Sản phẩm', dataIndex: 'products_count', key: 'products_count' },
        { title: 'Trạng thái', dataIndex: 'is_active', key: 'is_active', render: (value) => value ? <Tag color="green">active</Tag> : <Tag>hidden</Tag> },
        {
            title: 'Tác vụ',
            key: 'actions',
            render: (_, category) => (
                <Space wrap>
                    <Button size="small" disabled={!permissions.catalogUpdate} onClick={() => openCategoryModal(category)}>Sửa</Button>
                    <Popconfirm title="Xóa danh mục này?" disabled={!permissions.catalogDelete} onConfirm={() => runCrud({ endpoint: `/admin/api/catalog/categories/${category.id}`, method: 'DELETE', successMessage: 'Đã xóa danh mục catalog.' })}>
                        <Button danger size="small" disabled={!permissions.catalogDelete}>Xóa</Button>
                    </Popconfirm>
                </Space>
            ),
        },
    ];

    const bannerColumns = [
        { title: 'Theme', dataIndex: 'theme_key', key: 'theme_key', render: (value) => value || 'global' },
        { title: 'Vị trí', dataIndex: 'placement', key: 'placement' },
        { title: 'Tiêu đề', dataIndex: 'title', key: 'title', render: (value) => value || 'Không có' },
        { title: 'Link', dataIndex: 'link_url', key: 'link_url', render: (value) => value || 'Không có' },
        { title: 'Trạng thái', dataIndex: 'is_active', key: 'is_active', render: (value) => value ? <Tag color="green">active</Tag> : <Tag>hidden</Tag> },
        {
            title: 'Tác vụ',
            key: 'actions',
            render: (_, banner) => (
                <Space wrap>
                    <Button size="small" disabled={!permissions.catalogUpdate} onClick={() => openBannerModal(banner)}>Sửa</Button>
                    <Popconfirm title="Xóa banner này?" disabled={!permissions.catalogDelete} onConfirm={() => runCrud({ endpoint: `/admin/api/site-banners/${banner.id}`, method: 'DELETE', successMessage: 'Đã xóa banner.' })}>
                        <Button danger size="small" disabled={!permissions.catalogDelete}>Xóa</Button>
                    </Popconfirm>
                </Space>
            ),
        },
    ];

    if (loading) {
        return <Card loading title="Catalog" />;
    }

    if (error) {
        return <Alert type="error" showIcon message={error} />;
    }

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card>
                <Text className="card-label">Catalog</Text>
                <Title level={3}>Catalog, Category và Banner theo data scope</Title>
                <Paragraph style={{ marginBottom: 0 }}>Một màn để sếp chỉnh tay dữ liệu thương mại điện tử của TH0001: danh mục, sản phẩm và banner nhiều vị trí.</Paragraph>
            </Card>

            <Card>
                <div className="metric-grid">
                    {[
                        { label: 'Tổng sản phẩm', value: data?.products?.total ?? 0 },
                        { label: 'In stock', value: data?.products?.metrics?.in_stock ?? 0 },
                        { label: 'Inventory units', value: data?.products?.metrics?.inventory_units ?? 0 },
                        { label: 'Danh mục', value: data?.categories?.total ?? 0 },
                        { label: 'Banner', value: data?.banners?.total ?? 0 },
                    ].map((item) => (
                        <div key={item.label} className="metric-tile">
                            <Text className="metric-label">{item.label}</Text>
                            <Title level={3} style={{ margin: 0 }}>{item.value}</Title>
                        </div>
                    ))}
                </div>
            </Card>

            <Card className="admin-table-card">
                <Tabs
                    activeKey={activeTabKey}
                    onChange={setActiveTabKey}
                    items={[
                        {
                            key: 'products',
                            label: `Sản phẩm (${data?.products?.total ?? 0})`,
                            children: (
                                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                                    <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                                        <Button type="primary" disabled={!permissions.catalogCreate} onClick={() => openProductModal()}>
                                            Tạo sản phẩm
                                        </Button>
                                    </div>
                                    <Table rowKey="id" columns={productColumns} dataSource={data?.products?.items ?? []} pagination={false} scroll={{ x: 1100 }} />
                                </Space>
                            ),
                        },
                        {
                            key: 'categories',
                            label: `Danh mục (${data?.categories?.total ?? 0})`,
                            children: (
                                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                                    <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                                        <Button type="primary" disabled={!permissions.catalogCreate} onClick={() => openCategoryModal()}>
                                            Tạo danh mục
                                        </Button>
                                    </div>
                                    <Table rowKey="id" columns={categoryColumns} dataSource={data?.categories?.items ?? []} pagination={false} scroll={{ x: 1040 }} />
                                </Space>
                            ),
                        },
                        {
                            key: 'banners',
                            label: `Banner (${data?.banners?.total ?? 0})`,
                            children: (
                                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                                    <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                                        <Button type="primary" disabled={!permissions.catalogCreate} onClick={() => openBannerModal()}>
                                            Tạo banner
                                        </Button>
                                    </div>
                                    <Table rowKey="id" columns={bannerColumns} dataSource={data?.banners?.items ?? []} pagination={false} scroll={{ x: 1100 }} />
                                </Space>
                            ),
                        },
                    ]}
                />
            </Card>

            {productModalOpen ? (
                <Suspense fallback={null}>
                    <CatalogProductFormModal
                        open={productModalOpen}
                        canManage={editingProduct.id ? permissions.catalogUpdate : permissions.catalogCreate}
                        editingProduct={editingProduct}
                        categoryOptions={categoryOptions}
                        onCancel={() => {
                            setProductModalOpen(false);
                            setEditingProduct(emptyProductForm);
                        }}
                        onSubmit={async (payload) => {
                            const didSave = await runCrud({
                                endpoint: editingProduct.id ? `/admin/api/catalog/products/${editingProduct.id}` : '/admin/api/catalog/products',
                                method: editingProduct.id ? 'PUT' : 'POST',
                                payload,
                                successMessage: editingProduct.id ? 'Đã cập nhật sản phẩm catalog.' : 'Đã tạo sản phẩm catalog.',
                            });

                            if (didSave) {
                                setProductModalOpen(false);
                                setEditingProduct(emptyProductForm);
                            }

                            return didSave;
                        }}
                    />
                </Suspense>
            ) : null}

            {categoryModalOpen ? (
                <Suspense fallback={null}>
                    <CatalogCategoryFormModal
                        open={categoryModalOpen}
                        canManage={editingCategory.id ? permissions.catalogUpdate : permissions.catalogCreate}
                        editingCategory={editingCategory}
                        categoryOptions={categoryOptions.filter((option) => option.value !== editingCategory.id)}
                        onCancel={() => {
                            setCategoryModalOpen(false);
                            setEditingCategory(emptyCategoryForm);
                        }}
                        onSubmit={async (payload) => {
                            const didSave = await runCrud({
                                endpoint: editingCategory.id ? `/admin/api/catalog/categories/${editingCategory.id}` : '/admin/api/catalog/categories',
                                method: editingCategory.id ? 'PUT' : 'POST',
                                payload,
                                successMessage: editingCategory.id ? 'Đã cập nhật danh mục catalog.' : 'Đã tạo danh mục catalog.',
                            });

                            if (didSave) {
                                setCategoryModalOpen(false);
                                setEditingCategory(emptyCategoryForm);
                            }

                            return didSave;
                        }}
                    />
                </Suspense>
            ) : null}

            {bannerModalOpen ? (
                <Suspense fallback={null}>
                    <SiteBannerFormModal
                        open={bannerModalOpen}
                        canManage={editingBanner.id ? permissions.catalogUpdate : permissions.catalogCreate}
                        editingBanner={editingBanner}
                        onCancel={() => {
                            setBannerModalOpen(false);
                            setEditingBanner(emptyBannerForm);
                        }}
                        onSubmit={async (payload) => {
                            const didSave = await runCrud({
                                endpoint: editingBanner.id ? `/admin/api/site-banners/${editingBanner.id}` : '/admin/api/site-banners',
                                method: editingBanner.id ? 'PUT' : 'POST',
                                payload,
                                successMessage: editingBanner.id ? 'Đã cập nhật banner.' : 'Đã tạo banner.',
                            });

                            if (didSave) {
                                setBannerModalOpen(false);
                                setEditingBanner(emptyBannerForm);
                            }

                            return didSave;
                        }}
                    />
                </Suspense>
            ) : null}
        </Space>
    );
}
