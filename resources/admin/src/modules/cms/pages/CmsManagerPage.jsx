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
import Drawer from 'antd/es/drawer';
import Dropdown from 'antd/es/dropdown';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import useAdminRouteResource from '../../../shared/hooks/useAdminRouteResource';

const CmsPageFormModal = lazy(() => import('../components/CmsPageFormModal'));
const CmsPartnerFormModal = lazy(() => import('../components/CmsPartnerFormModal'));
const CmsPostFormModal = lazy(() => import('../components/CmsPostFormModal'));
const CmsProjectFormModal = lazy(() => import('../components/CmsProjectFormModal'));
const CmsServiceFormModal = lazy(() => import('../components/CmsServiceFormModal'));
const CmsTeamMemberFormModal = lazy(() => import('../components/CmsTeamMemberFormModal'));
const CmsTestimonialFormModal = lazy(() => import('../components/CmsTestimonialFormModal'));
const CmsCategoryFormModal = lazy(() => import('../components/CmsCategoryFormModal'));
const CmsFeaturedCategoryFormModal = lazy(() => import('../components/CmsFeaturedCategoryFormModal'));
const LandingBlockManagerDrawer = lazy(() => import('../components/LandingBlockManagerDrawer'));
const LandingPageFormModal = lazy(() => import('../components/LandingPageFormModal'));
const CmsMenuFormModal = lazy(() => import('../components/CmsMenuFormModal'));
const CmsSidePromoFormModal = lazy(() => import('../components/CmsSidePromoFormModal'));
const CatalogCategoryFormModal = lazy(() => import('../../catalog/components/CatalogCategoryFormModal'));
const CatalogProductFormModal = lazy(() => import('../../catalog/components/CatalogProductFormModal'));
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
    'cms-landing-pages': {
        title: 'Landing pages',
        description: 'Quản lý trang chủ và các landingpage chiến dịch theo từng khối nội dung của theme.',
        endpoint: '/admin/api/landing/pages',
        permissionView: 'cms.view',
        permissionCreate: 'cms.create',
        permissionUpdate: 'cms.update',
        permissionDelete: 'cms.delete',
        permissionPublish: 'cms.publish',
    },
    'cms-posts': {
        title: 'Tin tức',
        description: 'Quản lý bài viết, category, featured media và public blog.',
        endpoint: '/admin/api/cms/posts',
        permissionView: 'cms.post.view',
        permissionCreate: 'cms.post.create',
        permissionUpdate: 'cms.post.update',
        permissionDelete: 'cms.post.delete',
        permissionPublish: 'cms.publish',
    },
    'cms-services': {
        title: 'Services',
        description: 'Quan ly dich vu, gallery anh, alt text va du lieu dong cho cac block dich vu.',
        endpoint: '/admin/api/cms/services',
        permissionView: 'cms.view',
        permissionCreate: 'cms.create',
        permissionUpdate: 'cms.update',
        permissionDelete: 'cms.delete',
        permissionPublish: 'cms.publish',
    },
    'cms-projects': {
        title: 'Projects',
        description: 'Quan ly du an, gallery anh, alt text va du lieu dong cho cac block du an.',
        endpoint: '/admin/api/cms/projects',
        permissionView: 'cms.view',
        permissionCreate: 'cms.create',
        permissionUpdate: 'cms.update',
        permissionDelete: 'cms.delete',
        permissionPublish: 'cms.publish',
    },
    'cms-testimonials': {
        title: 'Testimonials',
        description: 'Quan ly nhan xet khach hang dung chung cho cac theme.',
        endpoint: '/admin/api/cms/testimonials',
        permissionView: 'cms.view',
        permissionCreate: 'cms.create',
        permissionUpdate: 'cms.update',
        permissionDelete: 'cms.delete',
        permissionPublish: 'cms.publish',
    },
    'cms-team-members': {
        title: 'Team Members',
        description: 'Quan ly doi ngu nhan su, gallery anh va anh dai dien dung chung cho cac theme.',
        endpoint: '/admin/api/cms/team-members',
        permissionView: 'cms.view',
        permissionCreate: 'cms.create',
        permissionUpdate: 'cms.update',
        permissionDelete: 'cms.delete',
        permissionPublish: 'cms.publish',
    },
    'cms-partners': {
        title: 'Partners',
        description: 'Quan ly logo va thong tin doi tac dung chung cho cac theme.',
        endpoint: '/admin/api/cms/partners',
        permissionView: 'cms.view',
        permissionCreate: 'cms.create',
        permissionUpdate: 'cms.update',
        permissionDelete: 'cms.delete',
        permissionPublish: 'cms.publish',
    },
    'cms-products': {
        title: 'Products',
        description: 'Quản lý sản phẩm ecommerce ngay trong workspace CMS.',
        endpoint: '/admin/api/cms/products',
        permissionView: 'cms.product.view',
        permissionCreate: 'cms.product.create',
        permissionUpdate: 'cms.product.update',
        permissionDelete: 'cms.product.delete',
        permissionPublish: null,
    },
    'cms-orders': {
        title: 'Orders',
        description: 'Theo dõi đơn hàng từ storefront, khách hàng và line-item ngay trong CMS.',
        endpoint: '/admin/api/cms/orders',
        permissionView: 'cms.order.view',
        permissionCreate: null,
        permissionUpdate: null,
        permissionDelete: null,
        permissionPublish: null,
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
        title: 'Chi tiết menu',
        description: 'Xem và chỉnh cấu trúc menu hiển thị trên website theo từng vị trí.',
        endpoint: '/admin/api/cms/menus',
        permissionView: 'cms.view',
        permissionCreate: 'cms.menu.manage',
        permissionUpdate: 'cms.menu.manage',
        permissionDelete: 'cms.menu.manage',
        permissionPublish: null,
    },
    'cms-featured-categories': {
        title: 'Danh mục nổi bật',
        description: 'Quản lý các cụm danh mục nổi bật dùng chung cho storefront theo từng vị trí.',
        endpoint: '/admin/api/cms/featured-categories',
        permissionView: 'cms.view',
        permissionCreate: 'cms.menu.manage',
        permissionUpdate: 'cms.menu.manage',
        permissionDelete: 'cms.menu.manage',
        permissionPublish: null,
    },
    'cms-side-promos': {
        title: 'Side promos',
        description: 'Quản lý block promo dọc kiểu CMS cũ cạnh hero. Đây không phải nơi quản lý slide banner hero của theme SER0101; slide hero đang nằm ở Catalog > Slide banner.',
        endpoint: '/admin/api/cms/side-promos',
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

const emptyLandingPage = {
    id: null,
    title: '',
    slug: '',
    status: 'draft',
    sort_order: 0,
    is_home: false,
    data_by_locale: {},
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
    is_highlight: false,
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

const emptyService = {
    id: null,
    cms_service_category_id: null,
    title: '',
    slug: '',
    status: 'draft',
    summary: '',
    content: '',
    icon: '▦',
    button_label: 'Tìm hiểu ngay',
    link_url: '',
    meta_title: '',
    meta_description: '',
    publish_at: null,
    is_featured: true,
    is_highlight: true,
    sort_order: 0,
    images: [],
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

const emptyServiceCategory = {
    id: null,
    parent_id: null,
    name: '',
    slug: '',
    description: '',
    image_url: '',
    sort_order: 0,
    is_active: true,
};

const emptyProject = {
    id: null,
    title: '',
    slug: '',
    status: 'draft',
    summary: '',
    content: '',
    button_label: 'Xem chi tiết',
    link_url: '',
    meta_title: '',
    meta_description: '',
    publish_at: null,
    is_featured: true,
    is_highlight: true,
    sort_order: 0,
    images: [],
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

const emptyTestimonial = {
    id: null,
    name: '',
    role: '',
    company: '',
    quote: '',
    image_url: '',
    image_alt: '',
    link_url: '',
    status: 'draft',
    publish_at: null,
    is_featured: true,
    sort_order: 0,
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

const emptyTeamMember = {
    id: null,
    name: '',
    slug: '',
    role: '',
    department: '',
    summary: '',
    bio: '',
    email: '',
    phone: '',
    link_url: '',
    status: 'draft',
    publish_at: null,
    is_featured: true,
    sort_order: 0,
    images: [],
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

const emptyPartner = {
    id: null,
    title: '',
    slug: '',
    description: '',
    image_url: '',
    image_alt: '',
    link_url: '',
    status: 'draft',
    publish_at: null,
    is_featured: true,
    sort_order: 0,
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

const emptyProduct = {
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
    is_highlight: false,
    sort_order: 0,
    is_active: true,
};

const emptyProductCategory = {
    id: null,
    parent_id: null,
    name: '',
    slug: '',
    description: '',
    image_url: '',
    sort_order: 0,
    is_active: true,
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
    items: [{ label: '', url: '', target: '_self', link_type: 'custom', link_value: null, custom_url: '', children: [] }],
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

const emptyFeaturedCategory = {
    id: null,
    name: '',
    location: 'home-featured-categories',
    items: [{ label: '', url: '', target: '_self', link_type: 'custom', link_value: null, custom_url: '' }],
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

const emptySidePromo = {
    id: null,
    name: '',
    location: 'home-hero-side-promos',
    items: [{ title: '', subtitle: '', image: '', url: '', target: '_self', link_type: 'custom', link_value: null, custom_url: '' }],
    website_key: '',
    owner_key: '',
    tenant_key: '',
};

const BULK_KEEP_VALUE = '__KEEP__';
const BULK_CLEAR_VALUE = '__CLEAR__';

function countMenuItems(items = []) {
    return (items ?? []).reduce((total, item) => total + 1 + countMenuItems(item?.children ?? []), 0);
}

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
    const frontendLocale = window.localStorage.getItem('aio.frontendLocale') || 'vi';
    const homeAdminUrl = `/${encodeURIComponent(frontendLocale)}?mod=admin`;
    const [bulkProductEditForm] = Form.useForm();
    const [mediaEditForm] = Form.useForm();
    const [modalOpen, setModalOpen] = useState(false);
    const [editingRecord, setEditingRecord] = useState(emptyPage);
    const [blockManagerOpen, setBlockManagerOpen] = useState(false);
    const [selectedLandingPage, setSelectedLandingPage] = useState(null);
    const [selectedPost, setSelectedPost] = useState(null);
    const [selectedProduct, setSelectedProduct] = useState(null);
    const [selectedOrder, setSelectedOrder] = useState(null);
    const [selectedProductRowKeys, setSelectedProductRowKeys] = useState([]);
    const [selectedPartnerRowKeys, setSelectedPartnerRowKeys] = useState([]);
    const [bulkProductEditOpen, setBulkProductEditOpen] = useState(false);
    const [keyword, setKeyword] = useState('');
    const [productCategoryFilter, setProductCategoryFilter] = useState('all');
    const [productFeaturedFilter, setProductFeaturedFilter] = useState('all');
    const [productActiveFilter, setProductActiveFilter] = useState('all');
    const [productPublishFilter, setProductPublishFilter] = useState('all');
    const [productSort, setProductSort] = useState('newest');
    const [mediaUpload, setMediaUpload] = useState({ title: '', alt_text: '' });
    const [mediaFile, setMediaFile] = useState(null);
    const [editingMediaRecord, setEditingMediaRecord] = useState(null);
    const [categoryManagerOpen, setCategoryManagerOpen] = useState(false);
    const [categoryFormOpen, setCategoryFormOpen] = useState(false);
    const [categoryItems, setCategoryItems] = useState([]);
    const [categoryLoading, setCategoryLoading] = useState(false);
    const [editingCategoryRecord, setEditingCategoryRecord] = useState(emptyCategory);
    const [serviceCategoryManagerOpen, setServiceCategoryManagerOpen] = useState(false);
    const [serviceCategoryFormOpen, setServiceCategoryFormOpen] = useState(false);
    const [serviceCategoryItems, setServiceCategoryItems] = useState([]);
    const [serviceCategoryLoading, setServiceCategoryLoading] = useState(false);
    const [editingServiceCategoryRecord, setEditingServiceCategoryRecord] = useState(emptyServiceCategory);
    const [productCategoryManagerOpen, setProductCategoryManagerOpen] = useState(false);
    const [productCategoryFormOpen, setProductCategoryFormOpen] = useState(false);
    const [productCategoryItems, setProductCategoryItems] = useState([]);
    const [productCategoryLoading, setProductCategoryLoading] = useState(false);
    const [editingProductCategoryRecord, setEditingProductCategoryRecord] = useState(emptyProductCategory);
    const createButtonLabel = sectionKey === 'cms-menus'
        ? 'Thêm menu'
        : sectionKey === 'cms-landing-pages'
            ? 'Thêm landingpage'
        : sectionKey === 'cms-featured-categories'
            ? 'Thêm danh mục nổi bật'
            : sectionKey === 'cms-side-promos'
                ? 'Thêm side promo'
            : `Tạo ${sectionConfig.title}`;

    const sectionPermissions = useMemo(() => ({
        canView: (currentPermissions ?? []).includes(sectionConfig.permissionView),
        canCreate: sectionConfig.permissionCreate ? (currentPermissions ?? []).includes(sectionConfig.permissionCreate) : false,
        canUpdate: sectionConfig.permissionUpdate ? (currentPermissions ?? []).includes(sectionConfig.permissionUpdate) : false,
        canDelete: sectionConfig.permissionDelete ? (currentPermissions ?? []).includes(sectionConfig.permissionDelete) : false,
        canPublish: sectionConfig.permissionPublish ? (currentPermissions ?? []).includes(sectionConfig.permissionPublish) : false,
    }), [currentPermissions, sectionConfig]);
    const canManageCategories = (currentPermissions ?? []).includes('cms.category.manage');

    const { data, loading, error, reload } = useAdminRouteResource({
        enabled: sectionPermissions.canView,
        loader: async () => {
            if (sectionKey === 'cms-products') {
                const [productsPayload, categoriesPayload] = await Promise.all([
                    callAdminApi('/admin/api/cms/products'),
                    callAdminApi('/admin/api/cms/product-categories'),
                ]);

                return {
                    ...(productsPayload.data ?? { items: [], total: 0, metrics: {} }),
                    categories: categoriesPayload.data?.items ?? [],
                };
            }

            if (sectionKey === 'cms-services') {
                const [servicesPayload, categoriesPayload] = await Promise.all([
                    callAdminApi('/admin/api/cms/services'),
                    callAdminApi('/admin/api/cms/service-categories'),
                ]);

                return {
                    ...(servicesPayload.data ?? { items: [], total: 0, metrics: {}, media: [] }),
                    categories: categoriesPayload.data?.items ?? [],
                };
            }

            if (sectionKey === 'cms-side-promos') {
                const [sidePromosPayload, mediaPayload] = await Promise.all([
                    callAdminApi('/admin/api/cms/side-promos'),
                    callAdminApi('/admin/api/cms/media'),
                ]);

                return {
                    ...(sidePromosPayload.data ?? { items: [], total: 0 }),
                    media: mediaPayload.data?.items ?? [],
                };
            }

            const payload = await callAdminApi(sectionConfig.endpoint);
            return payload.data ?? null;
        },
        deps: [sectionConfig.endpoint, sectionPermissions.canView],
    });

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

        if (sectionKey === 'cms-landing-pages') {
            return [
                { label: 'Tổng landingpage', value: data.total ?? 0 },
                { label: 'Đã xuất bản', value: data.metrics?.published ?? 0 },
                { label: 'Bản nháp', value: data.metrics?.draft ?? 0 },
            ];
        }

        if (sectionKey === 'cms-services') {
            return [
                {
                    title: 'Dịch vụ',
                    dataIndex: 'title',
                    key: 'title',
                    render: (value, record) => (
                        <Space size={12} align="start">
                            {record.featured_image_url ? (
                                <img
                                    src={record.featured_image_url}
                                    alt={record.featured_image_alt || value}
                                    style={{ width: 64, height: 64, objectFit: 'cover', borderRadius: 12, border: '1px solid #dbe7e4', display: 'block' }}
                                />
                            ) : (
                                <div style={{ width: 64, height: 64, borderRadius: 12, border: '1px solid #dbe7e4', background: '#f4f7f6', display: 'grid', placeItems: 'center', color: '#8aa19a', fontSize: 12, fontWeight: 600 }}>
                                    No Img
                                </div>
                            )}
                            <Space direction="vertical" size={2} align="start">
                                <Button type="link" style={{ paddingInline: 0, height: 'auto' }} onClick={() => openEditModal(record)}>
                                    <Text strong style={{ color: '#1677ff' }}>{value}</Text>
                                </Button>
                                <Text type="secondary">{record.summary || 'Chưa có mô tả ngắn'}</Text>
                                <Space size={6} wrap>
                                    {record.category_name ? <Tag color="blue">{record.category_name}</Tag> : <Tag>Chưa phân loại</Tag>}
                                    {record.is_highlight ? <Tag color="gold">Nổi bật</Tag> : null}
                                    <Tag>{`${record.images?.length ?? 0} ảnh`}</Tag>
                                </Space>
                            </Space>
                        </Space>
                    ),
                },
                { title: 'Danh mục', dataIndex: 'category_name', key: 'category_name', render: (value) => value || 'Chưa phân loại' },
                { title: 'Slug', dataIndex: 'slug', key: 'slug' },
                { title: 'Status', dataIndex: 'status', key: 'status', render: renderStatusTag },
                { title: 'Thứ tự', dataIndex: 'sort_order', key: 'sort_order' },
                { title: 'Tác vụ', key: 'actions', render: (_, record) => renderActions(record) },
            ];
        }

        if (sectionKey === 'cms-services' || sectionKey === 'cms-projects') {
            const entityTitle = sectionKey === 'cms-projects' ? 'Project' : 'Service';

            return [
                {
                    title: entityTitle,
                    dataIndex: 'title',
                    key: 'title',
                    render: (value, record) => (
                        <Space size={12} align="start">
                            {record.featured_image_url ? (
                                <img
                                    src={record.featured_image_url}
                                    alt={record.featured_image_alt || value}
                                    style={{ width: 64, height: 64, objectFit: 'cover', borderRadius: 12, border: '1px solid #dbe7e4', display: 'block' }}
                                />
                            ) : (
                                <div style={{ width: 64, height: 64, borderRadius: 12, border: '1px solid #dbe7e4', background: '#f4f7f6', display: 'grid', placeItems: 'center', color: '#8aa19a', fontSize: 12, fontWeight: 600 }}>
                                    No Img
                                </div>
                            )}
                            <Space direction="vertical" size={2} align="start">
                                <Button type="link" style={{ paddingInline: 0, height: 'auto' }} onClick={() => openEditModal(record)}>
                                    <Text strong style={{ color: '#1677ff' }}>{value}</Text>
                                </Button>
                                <Text type="secondary">{record.summary || 'Chua co mo ta ngan'}</Text>
                                <Space size={6} wrap>
                                    {record.is_highlight ? <Tag color="gold">Noi bat</Tag> : null}
                                    <Tag>{`${record.images?.length ?? 0} anh`}</Tag>
                                </Space>
                            </Space>
                        </Space>
                    ),
                },
                { title: 'Slug', dataIndex: 'slug', key: 'slug' },
                { title: 'Status', dataIndex: 'status', key: 'status', render: renderStatusTag },
                { title: 'Thu tu', dataIndex: 'sort_order', key: 'sort_order' },
                { title: 'Tac vu', key: 'actions', render: (_, record) => renderActions(record) },
            ];
        }

        if (sectionKey === 'cms-testimonials') {
            return [
                {
                    title: 'Khach hang',
                    dataIndex: 'name',
                    key: 'name',
                    render: (value, record) => (
                        <Space size={12} align="start">
                            {record.image_url ? (
                                <img
                                    src={record.image_url}
                                    alt={record.image_alt || value}
                                    style={{ width: 64, height: 64, objectFit: 'cover', borderRadius: 999, border: '1px solid #dbe7e4', display: 'block' }}
                                />
                            ) : (
                                <div style={{ width: 64, height: 64, borderRadius: 999, border: '1px solid #dbe7e4', background: '#f4f7f6', display: 'grid', placeItems: 'center', color: '#8aa19a', fontSize: 12, fontWeight: 600 }}>
                                    No Img
                                </div>
                            )}
                            <Space direction="vertical" size={2} align="start">
                                <Button type="link" style={{ paddingInline: 0, height: 'auto' }} onClick={() => openEditModal(record)}>
                                    <Text strong style={{ color: '#1677ff' }}>{value}</Text>
                                </Button>
                                <Text type="secondary">{record.company || record.role || 'Chua co thong tin phu'}</Text>
                                <Paragraph ellipsis={{ rows: 2 }} style={{ margin: 0, maxWidth: 420 }}>{record.quote}</Paragraph>
                                {record.is_featured ? <Tag color="gold">Noi bat</Tag> : null}
                            </Space>
                        </Space>
                    ),
                },
                { title: 'Status', dataIndex: 'status', key: 'status', render: renderStatusTag },
                { title: 'Thu tu', dataIndex: 'sort_order', key: 'sort_order' },
                { title: 'Tac vu', key: 'actions', render: (_, record) => renderActions(record) },
            ];
        }

        if (sectionKey === 'cms-team-members') {
            return [
                {
                    title: 'Nhan su',
                    dataIndex: 'name',
                    key: 'name',
                    render: (value, record) => (
                        <Space size={12} align="start">
                            {record.featured_image_url ? (
                                <img
                                    src={record.featured_image_url}
                                    alt={record.featured_image_alt || value}
                                    style={{ width: 64, height: 64, objectFit: 'cover', borderRadius: 12, border: '1px solid #dbe7e4', display: 'block' }}
                                />
                            ) : (
                                <div style={{ width: 64, height: 64, borderRadius: 12, border: '1px solid #dbe7e4', background: '#f4f7f6', display: 'grid', placeItems: 'center', color: '#8aa19a', fontSize: 12, fontWeight: 600 }}>
                                    No Img
                                </div>
                            )}
                            <Space direction="vertical" size={2} align="start">
                                <Button type="link" style={{ paddingInline: 0, height: 'auto' }} onClick={() => openEditModal(record)}>
                                    <Text strong style={{ color: '#1677ff' }}>{value}</Text>
                                </Button>
                                <Text type="secondary">{record.role || record.department || 'Chua co chuc danh'}</Text>
                                <Space size={6} wrap>
                                    {record.is_featured ? <Tag color="gold">Noi bat</Tag> : null}
                                    <Tag>{`${record.images?.length ?? 0} anh`}</Tag>
                                </Space>
                            </Space>
                        </Space>
                    ),
                },
                { title: 'Slug', dataIndex: 'slug', key: 'slug' },
                { title: 'Status', dataIndex: 'status', key: 'status', render: renderStatusTag },
                { title: 'Thu tu', dataIndex: 'sort_order', key: 'sort_order' },
                { title: 'Tac vu', key: 'actions', render: (_, record) => renderActions(record) },
            ];
        }

        if (sectionKey === 'cms-partners') {
            return [
                {
                    title: 'Doi tac',
                    dataIndex: 'title',
                    key: 'title',
                    render: (value, record) => (
                        <Space size={12} align="start">
                            {record.image_url ? (
                                <img
                                    src={record.image_url}
                                    alt={record.image_alt || value}
                                    style={{ width: 88, height: 56, objectFit: 'contain', borderRadius: 12, border: '1px solid #dbe7e4', padding: 8, background: '#fff', display: 'block' }}
                                />
                            ) : (
                                <div style={{ width: 88, height: 56, borderRadius: 12, border: '1px solid #dbe7e4', background: '#f4f7f6', display: 'grid', placeItems: 'center', color: '#8aa19a', fontSize: 12, fontWeight: 600 }}>
                                    No Logo
                                </div>
                            )}
                            <Space direction="vertical" size={2} align="start">
                                <Button type="link" style={{ paddingInline: 0, height: 'auto' }} onClick={() => openEditModal(record)}>
                                    <Text strong style={{ color: '#1677ff' }}>{value}</Text>
                                </Button>
                                <Text type="secondary">{record.description || record.slug}</Text>
                                {record.is_featured ? <Tag color="gold">Noi bat</Tag> : null}
                            </Space>
                        </Space>
                    ),
                },
                { title: 'Slug', dataIndex: 'slug', key: 'slug' },
                { title: 'Status', dataIndex: 'status', key: 'status', render: renderStatusTag },
                { title: 'Thu tu', dataIndex: 'sort_order', key: 'sort_order' },
                { title: 'Tac vu', key: 'actions', render: (_, record) => renderActions(record) },
            ];
        }

        if (sectionKey === 'cms-products') {
            return [
                { label: 'Tổng sản phẩm', value: data.total ?? 0 },
                { label: 'Còn hàng', value: data.metrics?.in_stock ?? 0 },
                { label: 'Tồn kho', value: data.metrics?.inventory_units ?? 0 },
            ];
        }

        if (sectionKey === 'cms-orders') {
            return [
                { label: 'Tổng đơn', value: data.stats?.total_orders ?? 0 },
                { label: 'Doanh thu tạm tính', value: `${Number(data.stats?.gross_revenue ?? 0).toLocaleString('vi-VN')}đ` },
                { label: 'Đơn mới đặt', value: data.stats?.status_counts?.placed ?? 0 },
            ];
        }

        if (sectionKey === 'cms-categories' || sectionKey === 'cms-menus' || sectionKey === 'cms-featured-categories' || sectionKey === 'cms-side-promos') {
            return [];
        }

        return [
            { label: 'Đã xuất bản', value: data.metrics?.published ?? 0 },
            { label: 'Bản nháp', value: data.metrics?.draft ?? 0 },
        ];
    }, [data, sectionKey]);

    const productCategoryOptions = useMemo(() => (data?.categories ?? []).map((category) => ({
        label: category.parent_name ? `${category.parent_name} / ${category.name}` : category.name,
        value: category.id,
    })), [data?.categories]);

    const serviceCategoryOptions = useMemo(() => (data?.categories ?? []).map((category) => ({
        label: category.parent_name ? `${category.parent_name} / ${category.name}` : category.name,
        value: category.id,
    })), [data?.categories]);

    const categoryParentOptions = useMemo(() => categoryItems
        .filter((category) => category.id !== editingCategoryRecord?.id)
        .map((category) => ({
            label: category.name,
            value: category.id,
        })), [categoryItems, editingCategoryRecord?.id]);

    const productCategoryParentOptions = useMemo(() => productCategoryItems
        .filter((category) => category.id !== editingProductCategoryRecord?.id)
        .map((category) => ({
            label: category.parent_name ? `${category.parent_name} / ${category.name}` : category.name,
            value: category.id,
        })), [editingProductCategoryRecord?.id, productCategoryItems]);

    const serviceCategoryParentOptions = useMemo(() => serviceCategoryItems
        .filter((category) => category.id !== editingServiceCategoryRecord?.id)
        .map((category) => ({
            label: category.parent_name ? `${category.parent_name} / ${category.name}` : category.name,
            value: category.id,
        })), [editingServiceCategoryRecord?.id, serviceCategoryItems]);

    const selectedProducts = useMemo(() => {
        if (sectionKey !== 'cms-products') {
            return [];
        }

        return (data?.items ?? []).filter((product) => selectedProductRowKeys.includes(product.id));
    }, [data?.items, sectionKey, selectedProductRowKeys]);

    const filteredItems = useMemo(() => {
        const normalizedKeyword = keyword.trim().toLowerCase();

        if (sectionKey === 'cms-orders') {
            return (data?.orders ?? []).filter((order) => {
                if (normalizedKeyword === '') {
                    return true;
                }

                return [
                    order.order_code,
                    order.customer_name,
                    order.customer_phone,
                    order.customer_email,
                    order.delivery_address,
                ].some((value) => String(value ?? '').toLowerCase().includes(normalizedKeyword));
            });
        }

        if (sectionKey === 'cms-products') {
            const filteredProducts = (data?.items ?? []).filter((product) => {
                const matchesKeyword = normalizedKeyword === '' || [
                    product.name,
                    product.slug,
                    product.category_name,
                    product.sku,
                    product.short_description,
                ].some((value) => String(value ?? '').toLowerCase().includes(normalizedKeyword));
                const matchesCategory = productCategoryFilter === 'all' || String(product.catalog_category_id ?? '') === String(productCategoryFilter);
                const matchesFeatured = productFeaturedFilter === 'all'
                    || (productFeaturedFilter === 'featured' && product.is_highlight)
                    || (productFeaturedFilter === 'normal' && !product.is_highlight);
                const matchesActive = productActiveFilter === 'all'
                    || (productActiveFilter === 'active' && product.is_active)
                    || (productActiveFilter === 'inactive' && !product.is_active);
                const isPublicProduct = Boolean(product.public_url) && Boolean(product.is_active);
                const matchesPublish = productPublishFilter === 'all'
                    || (productPublishFilter === 'public' && isPublicProduct)
                    || (productPublishFilter === 'private' && !isPublicProduct);

                return matchesKeyword && matchesCategory && matchesFeatured && matchesActive && matchesPublish;
            });

            return [...filteredProducts].sort((left, right) => {
                const leftName = String(left.name ?? '').toLowerCase();
                const rightName = String(right.name ?? '').toLowerCase();
                const leftPrice = Number(left.price ?? 0);
                const rightPrice = Number(right.price ?? 0);
                const leftStock = Number(left.stock ?? 0);
                const rightStock = Number(right.stock ?? 0);
                const leftNewest = Date.parse(left.created_at ?? left.updated_at ?? '') || Number(left.id ?? 0);
                const rightNewest = Date.parse(right.created_at ?? right.updated_at ?? '') || Number(right.id ?? 0);

                switch (productSort) {
                    case 'name_asc':
                        return leftName.localeCompare(rightName, 'vi');
                    case 'name_desc':
                        return rightName.localeCompare(leftName, 'vi');
                    case 'price_desc':
                        return rightPrice - leftPrice;
                    case 'price_asc':
                        return leftPrice - rightPrice;
                    case 'stock_desc':
                        return rightStock - leftStock;
                    case 'stock_asc':
                        return leftStock - rightStock;
                    case 'oldest':
                        return leftNewest - rightNewest;
                    case 'newest':
                    default:
                        return rightNewest - leftNewest;
                }
            });
        }

        return data?.items ?? [];
    }, [data?.items, data?.orders, keyword, productActiveFilter, productCategoryFilter, productFeaturedFilter, productPublishFilter, productSort, sectionKey]);

    const openCreateModal = () => {
        if (sectionKey === 'cms-landing-pages') {
            setEditingRecord(emptyLandingPage);
        } else if (sectionKey === 'cms-posts') {
            setEditingRecord(emptyPost);
        } else if (sectionKey === 'cms-services') {
            setEditingRecord(emptyService);
        } else if (sectionKey === 'cms-projects') {
            setEditingRecord(emptyProject);
        } else if (sectionKey === 'cms-testimonials') {
            setEditingRecord(emptyTestimonial);
        } else if (sectionKey === 'cms-team-members') {
            setEditingRecord(emptyTeamMember);
        } else if (sectionKey === 'cms-partners') {
            setEditingRecord(emptyPartner);
        } else if (sectionKey === 'cms-products') {
            setEditingRecord(emptyProduct);
        } else if (sectionKey === 'cms-categories') {
            setEditingRecord(emptyCategory);
        } else if (sectionKey === 'cms-featured-categories') {
            setEditingRecord(emptyFeaturedCategory);
        } else if (sectionKey === 'cms-side-promos') {
            setEditingRecord(emptySidePromo);
        } else if (sectionKey === 'cms-menus') {
            setEditingRecord(emptyMenu);
        } else {
            setEditingRecord(emptyPage);
        }

        setModalOpen(true);
    };

    const openBlockManager = (record) => {
        setSelectedLandingPage(record);
        setBlockManagerOpen(true);
    };

    const openEditModal = (record) => {
        setEditingRecord(sectionKey === 'cms-posts'
            ? {
                ...record,
                publish_at: normalizeDatetimeLocal(record.publish_at),
            }
            : false && sectionKey === 'cms-products'
                ? {
                    ...record,
                    deal_end_at: normalizeDatetimeLocal(record.deal_end_at),
                }
            : record);
        setModalOpen(true);
    };

    const openPostDetailsDrawer = (record) => {
        setSelectedPost(record);
    };

    const handleEditPostFromDrawer = () => {
        if (!selectedPost) {
            return;
        }

        setSelectedPost(null);
        openEditModal(selectedPost);
    };

    const handleSaveRecord = async (payload) => {
        const didSave = editingRecord?.id
            ? await runAdminAction(() => callAdminApi(`${sectionConfig.endpoint}/${editingRecord.id}`, { method: 'PUT', body: JSON.stringify(payload) }), `Đã cập nhật ${sectionConfig.title}.`, reload)
            : await runAdminAction(() => callAdminApi(sectionConfig.endpoint, { method: 'POST', body: JSON.stringify(payload) }), `Đã tạo ${sectionConfig.title}.`, reload);

        if (didSave) {
            setModalOpen(false);
        }

        return didSave;
    };

    const handleDeleteRecord = async (recordId) => {
        await runAdminAction(() => callAdminApi(`${sectionConfig.endpoint}/${recordId}`, { method: 'DELETE' }), `Đã xóa ${sectionConfig.title}.`, reload);
    };

    const loadCategoryItems = async () => {
        setCategoryLoading(true);

        try {
            const payload = await callAdminApi('/admin/api/cms/categories');
            setCategoryItems(payload.data?.items ?? []);
        } finally {
            setCategoryLoading(false);
        }
    };

    const openCategoryManager = async () => {
        setCategoryManagerOpen(true);
        await loadCategoryItems();
    };

    const openCreateCategory = () => {
        setEditingCategoryRecord(emptyCategory);
        setCategoryFormOpen(true);
    };

    const openEditCategory = (record) => {
        setEditingCategoryRecord(record);
        setCategoryFormOpen(true);
    };

    const handleSaveCategory = async (payload) => {
        const didSave = editingCategoryRecord?.id
            ? await runAdminAction(
                () => callAdminApi(`/admin/api/cms/categories/${editingCategoryRecord.id}`, { method: 'PUT', body: JSON.stringify(payload) }),
                'Đã cập nhật danh mục tin tức.',
                async () => {
                    await loadCategoryItems();
                    await reload();
                },
            )
            : await runAdminAction(
                () => callAdminApi('/admin/api/cms/categories', { method: 'POST', body: JSON.stringify(payload) }),
                'Đã tạo danh mục tin tức.',
                async () => {
                    await loadCategoryItems();
                    await reload();
                },
            );

        if (didSave) {
            setCategoryFormOpen(false);
            setEditingCategoryRecord(emptyCategory);
        }
    };

    const handleDeleteCategory = (record) => {
        Modal.confirm({
            title: 'Xóa danh mục tin tức?',
            content: `Danh mục "${record.name}" sẽ bị xóa khỏi CMS. Các bài viết đang gắn danh mục này có thể cần gắn lại danh mục khác.`,
            okText: 'Xóa',
            okButtonProps: { danger: true },
            cancelText: 'Hủy',
            onOk: async () => {
                await runAdminAction(
                    () => callAdminApi(`/admin/api/cms/categories/${record.id}`, { method: 'DELETE' }),
                    'Đã xóa danh mục tin tức.',
                    async () => {
                        await loadCategoryItems();
                        await reload();
                    },
                );
            },
        });
    };

    const loadServiceCategoryItems = async () => {
        setServiceCategoryLoading(true);

        try {
            const payload = await callAdminApi('/admin/api/cms/service-categories');
            setServiceCategoryItems(payload.data?.items ?? []);
        } finally {
            setServiceCategoryLoading(false);
        }
    };

    const openServiceCategoryManager = async () => {
        setServiceCategoryManagerOpen(true);
        await loadServiceCategoryItems();
    };

    const openCreateServiceCategory = () => {
        setEditingServiceCategoryRecord(emptyServiceCategory);
        setServiceCategoryFormOpen(true);
    };

    const openEditServiceCategory = (record) => {
        setEditingServiceCategoryRecord({
            ...emptyServiceCategory,
            ...record,
        });
        setServiceCategoryFormOpen(true);
    };

    const handleSaveServiceCategory = async (payload) => {
        const didSave = editingServiceCategoryRecord?.id
            ? await runAdminAction(
                () => callAdminApi(`/admin/api/cms/service-categories/${editingServiceCategoryRecord.id}`, { method: 'PUT', body: JSON.stringify(payload) }),
                'ÄÃ£ cáº­p nháº­t danh má»¥c dá»‹ch vá»¥.',
                async () => {
                    await loadServiceCategoryItems();
                    await reload();
                },
            )
            : await runAdminAction(
                () => callAdminApi('/admin/api/cms/service-categories', { method: 'POST', body: JSON.stringify(payload) }),
                'ÄÃ£ táº¡o danh má»¥c dá»‹ch vá»¥.',
                async () => {
                    await loadServiceCategoryItems();
                    await reload();
                },
            );

        if (didSave) {
            setServiceCategoryFormOpen(false);
            setEditingServiceCategoryRecord(emptyServiceCategory);
        }
    };

    const handleDeleteServiceCategory = (record) => {
        Modal.confirm({
            title: 'XÃ³a danh má»¥c dá»‹ch vá»¥?',
            content: `Danh má»¥c "${record.name}" sáº½ bá»‹ xÃ³a. CÃ¡c dá»‹ch vá»¥ Ä‘ang gáº¯n danh má»¥c nÃ y cÃ³ thá»ƒ cáº§n cáº­p nháº­t láº¡i.`,
            okText: 'XÃ³a',
            okButtonProps: { danger: true },
            cancelText: 'Há»§y',
            onOk: async () => {
                await runAdminAction(
                    () => callAdminApi(`/admin/api/cms/service-categories/${record.id}`, { method: 'DELETE' }),
                    'ÄÃ£ xÃ³a danh má»¥c dá»‹ch vá»¥.',
                    async () => {
                        await loadServiceCategoryItems();
                        await reload();
                    },
                );
            },
        });
    };

    const loadProductCategoryItems = async ({ silent = false } = {}) => {
        if (! silent) {
            setProductCategoryLoading(true);
        }

        try {
            const payload = await callAdminApi('/admin/api/cms/product-categories');
            setProductCategoryItems(payload.data?.items ?? []);
        } finally {
            if (! silent) {
                setProductCategoryLoading(false);
            }
        }
    };

    const openProductCategoryManager = async () => {
        setProductCategoryManagerOpen(true);
        await loadProductCategoryItems();
    };

    const openCreateProductCategory = () => {
        setEditingProductCategoryRecord(emptyProductCategory);
        setProductCategoryFormOpen(true);
    };

    const openEditProductCategory = (record) => {
        setEditingProductCategoryRecord({
            ...emptyProductCategory,
            ...record,
        });
        setProductCategoryFormOpen(true);
    };

    const handleSaveProductCategory = async (payload) => {
        const didSave = editingProductCategoryRecord?.id
            ? await runAdminAction(
                () => callAdminApi(`/admin/api/cms/product-categories/${editingProductCategoryRecord.id}`, { method: 'PUT', body: JSON.stringify(payload) }),
                'Đã cập nhật danh mục sản phẩm.',
                async () => {
                    await loadProductCategoryItems({ silent: true });
                },
            )
            : await runAdminAction(
                () => callAdminApi('/admin/api/cms/product-categories', { method: 'POST', body: JSON.stringify(payload) }),
                'Đã tạo danh mục sản phẩm.',
                async () => {
                    await loadProductCategoryItems({ silent: true });
                },
            );

        if (didSave) {
            setProductCategoryFormOpen(false);
            setEditingProductCategoryRecord(emptyProductCategory);
        }
    };

    const handleDeleteProductCategory = (record) => {
        Modal.confirm({
            title: 'Xóa danh mục sản phẩm?',
            content: `Danh mục "${record.name}" sẽ bị xóa. Sản phẩm hoặc danh mục con đang liên kết có thể cần cập nhật lại.`,
            okText: 'Xóa',
            okButtonProps: { danger: true },
            cancelText: 'Hủy',
            onOk: async () => {
                await runAdminAction(
                    () => callAdminApi(`/admin/api/cms/product-categories/${record.id}`, { method: 'DELETE' }),
                    'Đã xóa danh mục sản phẩm.',
                    async () => {
                        await loadProductCategoryItems({ silent: true });
                    },
                );
            },
        });
    };

    const buildBulkProductPayload = (product, values) => ({
        catalog_category_id: values.catalog_category_id === BULK_KEEP_VALUE
            ? product.catalog_category_id
            : values.catalog_category_id === BULK_CLEAR_VALUE
                ? null
                : values.catalog_category_id,
        name: product.name,
        slug: product.slug,
        sku: product.sku,
        price: product.price,
        original_price: product.original_price,
        stock: product.stock,
        short_description: product.short_description,
        detail_content: product.detail_content,
        highlights: product.highlights,
        usage_terms: product.usage_terms,
        usage_location: product.usage_location,
        image_url: product.image_url,
        gallery_images: product.gallery_images ?? [],
        sold_count: product.sold_count,
        deal_end_at: product.deal_end_at,
        is_featured: product.is_featured,
        is_highlight: values.is_featured === BULK_KEEP_VALUE ? product.is_highlight : values.is_featured === 'true',
        sort_order: product.sort_order,
        is_active: values.is_active === BULK_KEEP_VALUE ? product.is_active : values.is_active === 'true',
    });

    const handleBulkDeleteProducts = async () => {
        const ids = [...selectedProductRowKeys];

        const didDelete = await runAdminAction(async () => {
            for (const id of ids) {
                await callAdminApi(`${sectionConfig.endpoint}/${id}`, { method: 'DELETE' });
            }
        }, `Đã xóa ${ids.length} sản phẩm.`, reload);

        if (didDelete) {
            setSelectedProductRowKeys([]);
        }
    };

    const confirmBulkDeleteProducts = () => {
        if (!selectedProductRowKeys.length) {
            return;
        }

        Modal.confirm({
            title: `Xóa ${selectedProductRowKeys.length} sản phẩm đã chọn?`,
            content: 'Thao tác này không thể hoàn tác.',
            okText: 'Xóa tất cả',
            okButtonProps: { danger: true },
            cancelText: 'Hủy',
            onOk: handleBulkDeleteProducts,
        });
    };

    const handleBulkDeletePartners = async () => {
        const ids = [...selectedPartnerRowKeys];

        const didDelete = await runAdminAction(async () => {
            for (const id of ids) {
                await callAdminApi(`${sectionConfig.endpoint}/${id}`, { method: 'DELETE' });
            }
        }, `Đã xóa ${ids.length} đối tác.`, reload);

        if (didDelete) {
            setSelectedPartnerRowKeys([]);
        }
    };

    const confirmBulkDeletePartners = () => {
        if (!selectedPartnerRowKeys.length) {
            return;
        }

        Modal.confirm({
            title: `Xóa ${selectedPartnerRowKeys.length} đối tác đã chọn?`,
            content: 'Thao tác này không thể hoàn tác.',
            okText: 'Xóa tất cả',
            okButtonProps: { danger: true },
            cancelText: 'Hủy',
            onOk: handleBulkDeletePartners,
        });
    };

    const openBulkEditProducts = () => {
        if (!selectedProductRowKeys.length) {
            return;
        }

        bulkProductEditForm.setFieldsValue({
            catalog_category_id: BULK_KEEP_VALUE,
            is_featured: BULK_KEEP_VALUE,
            is_active: BULK_KEEP_VALUE,
        });
        setBulkProductEditOpen(true);
    };

    const handleBulkEditProducts = async () => {
        const values = await bulkProductEditForm.validateFields();

        if (
            values.catalog_category_id === BULK_KEEP_VALUE
            && values.is_featured === BULK_KEEP_VALUE
            && values.is_active === BULK_KEEP_VALUE
        ) {
            Modal.warning({
                title: 'Chưa có thay đổi để áp dụng',
                content: 'Chọn ít nhất một trường cần cập nhật cho các sản phẩm đã chọn.',
            });
            return;
        }

        const products = [...selectedProducts];
        const didUpdate = await runAdminAction(async () => {
            for (const product of products) {
                await callAdminApi(`${sectionConfig.endpoint}/${product.id}`, {
                    method: 'PUT',
                    body: JSON.stringify(buildBulkProductPayload(product, values)),
                });
            }
        }, `Đã cập nhật ${products.length} sản phẩm.`, reload);

        if (didUpdate) {
            setBulkProductEditOpen(false);
            setSelectedProductRowKeys([]);
            bulkProductEditForm.resetFields();
        }
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
            setMediaUpload({ title: '', alt_text: '' });
        }
    };

    const openEditMediaTitle = (record) => {
        setEditingMediaRecord(record);
        mediaEditForm.setFieldsValue({
            title: record.title ?? '',
            alt_text: record.alt_text ?? '',
        });
    };

    const handleSaveMediaTitle = async () => {
        if (!editingMediaRecord?.id) {
            return false;
        }

        const values = await mediaEditForm.validateFields();
        const didSave = await runAdminAction(
            () => callAdminApi(`/admin/api/cms/media/${editingMediaRecord.id}`, {
                method: 'PUT',
                body: JSON.stringify({
                    title: values.title,
                    alt_text: values.alt_text || null,
                }),
            }),
            'Da cap nhat ten hien thi media.',
            reload,
        );

        if (didSave) {
            setEditingMediaRecord(null);
            mediaEditForm.resetFields();
        }
    };

    const renderActions = (record) => {
        const actionItems = [];

        if (sectionKey === 'cms-orders') {
            actionItems.push({
                key: 'detail',
                label: 'Xem chi tiết',
                icon: <EyeOutlined />,
            });

            const handleOrderActionClick = ({ key }) => {
                if (key === 'detail') {
                    setSelectedOrder(record);
                }
            };

            return (
                <Dropdown menu={{ items: actionItems, onClick: handleOrderActionClick }} trigger={['click']}>
                    <Button size="small" icon={<MoreOutlined />}>Tác vụ</Button>
                </Dropdown>
            );
        }

        if (sectionKey === 'cms-landing-pages') {
            actionItems.push({
                key: 'blocks',
                label: 'Quản lý khối',
                icon: <EditOutlined />,
            });

            if (record.admin_url) {
                actionItems.push({
                    key: 'visual',
                    label: 'Sửa trực quan',
                    icon: <EyeOutlined />,
                });
            }
        }

        if (record.public_url) {
            actionItems.push({
                key: 'public',
                label: 'Mở public',
                icon: <EyeOutlined />,
            });
        }

        if (record.preview_url && (sectionPermissions.canPublish || sectionKey === 'cms-products')) {
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
            actionItems.push({
                key: 'edit-media-title',
                label: 'Sửa tên hiển thị',
                icon: <EditOutlined />,
                disabled: !sectionPermissions.canUpdate,
            });
        }

        actionItems.push({
            key: 'delete',
            label: 'Xóa',
            icon: <DeleteOutlined />,
            danger: true,
            disabled: !sectionPermissions.canDelete || (sectionKey === 'cms-landing-pages' && record.is_home),
        });

        const handleActionClick = ({ key }) => {
            if (key === 'blocks') {
                openBlockManager(record);
                return;
            }

            if (key === 'visual' && record.admin_url) {
                window.open(record.admin_url, '_blank', 'noopener,noreferrer');
                return;
            }

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

            if (key === 'edit-media-title') {
                openEditMediaTitle(record);
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

    const productRowSelection = sectionKey === 'cms-products' && (sectionPermissions.canUpdate || sectionPermissions.canDelete)
        ? {
            selectedRowKeys: selectedProductRowKeys,
            onChange: (nextSelectedRowKeys) => setSelectedProductRowKeys(nextSelectedRowKeys),
            preserveSelectedRowKeys: true,
        }
        : undefined;
    const partnerRowSelection = sectionKey === 'cms-partners' && sectionPermissions.canDelete
        ? {
            selectedRowKeys: selectedPartnerRowKeys,
            onChange: (nextSelectedRowKeys) => setSelectedPartnerRowKeys(nextSelectedRowKeys),
            preserveSelectedRowKeys: true,
        }
        : undefined;
    const productBulkActions = sectionKey === 'cms-products' ? (
        <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
            <Space wrap>
                <Dropdown
                    trigger={['click']}
                    menu={{
                        items: [
                            {
                                key: 'bulk-edit',
                                label: 'Sửa đã chọn',
                                icon: <EditOutlined />,
                                disabled: !sectionPermissions.canUpdate || !selectedProductRowKeys.length,
                            },
                            {
                                key: 'bulk-delete',
                                label: 'Xóa đã chọn',
                                icon: <DeleteOutlined />,
                                danger: true,
                                disabled: !sectionPermissions.canDelete || !selectedProductRowKeys.length,
                            },
                        ],
                        onClick: ({ key }) => {
                            if (key === 'bulk-edit') {
                                openBulkEditProducts();
                            }

                            if (key === 'bulk-delete') {
                                confirmBulkDeleteProducts();
                            }
                        },
                    }}
                >
                    <Button icon={<MoreOutlined />} disabled={!selectedProductRowKeys.length}>
                        Thao tác đã chọn
                    </Button>
                </Dropdown>
                {selectedProductRowKeys.length ? (
                    <Text type="secondary">Đã chọn {selectedProductRowKeys.length} sản phẩm.</Text>
                ) : null}
            </Space>
            {selectedProductRowKeys.length ? (
                <Button size="small" type="link" onClick={() => setSelectedProductRowKeys([])}>
                    Bỏ chọn
                </Button>
            ) : null}
        </Space>
    ) : null;
    const partnerBulkActions = sectionKey === 'cms-partners' ? (
        <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
            <Space wrap>
                <Dropdown
                    trigger={['click']}
                    menu={{
                        items: [
                            {
                                key: 'bulk-delete',
                                label: 'Xóa đã chọn',
                                icon: <DeleteOutlined />,
                                danger: true,
                                disabled: !sectionPermissions.canDelete || !selectedPartnerRowKeys.length,
                            },
                        ],
                        onClick: ({ key }) => {
                            if (key === 'bulk-delete') {
                                confirmBulkDeletePartners();
                            }
                        },
                    }}
                >
                    <Button icon={<MoreOutlined />} disabled={!selectedPartnerRowKeys.length}>
                        Thao tác đã chọn
                    </Button>
                </Dropdown>
                {selectedPartnerRowKeys.length ? (
                    <Text type="secondary">Đã chọn {selectedPartnerRowKeys.length} đối tác.</Text>
                ) : null}
            </Space>
            {selectedPartnerRowKeys.length ? (
                <Button size="small" type="link" onClick={() => setSelectedPartnerRowKeys([])}>
                    Bỏ chọn
                </Button>
            ) : null}
        </Space>
    ) : null;

    const columns = useMemo(() => {
        if (sectionKey === 'cms-landing-pages') {
            return [
                {
                    title: 'Landingpage',
                    dataIndex: 'title',
                    key: 'title',
                    render: (value, record) => (
                        <Space direction="vertical" size={2}>
                            <Space wrap>
                                <Button type="link" style={{ paddingInline: 0, height: 'auto' }} onClick={() => openBlockManager(record)}>
                                    <Text strong style={{ color: '#1677ff' }}>{value || record.path}</Text>
                                </Button>
                                {record.is_home ? <Tag color="green">Trang chủ</Tag> : null}
                            </Space>
                            <Text type="secondary">{record.excerpt || 'Quản lý các khối nội dung landingpage.'}</Text>
                        </Space>
                    ),
                },
                { title: 'Đường dẫn', dataIndex: 'path', key: 'path', render: (value) => <Text code>{value}</Text> },
                { title: 'Theme', dataIndex: 'theme_key', key: 'theme_key', render: (value) => <Tag>{value}</Tag> },
                { title: 'Trạng thái', dataIndex: 'status', key: 'status', render: renderStatusTag },
                {
                    title: 'Khối',
                    key: 'blocks',
                    render: (_, record) => (
                        <Space direction="vertical" size={0}>
                            <Text strong>{record.block_count ?? 0} khối</Text>
                            <Text type="secondary">{record.visible_block_count ?? 0} đang hiển thị</Text>
                        </Space>
                    ),
                },
                { title: 'Thứ tự', dataIndex: 'sort_order', key: 'sort_order' },
                { title: 'Tác vụ', key: 'actions', render: (_, record) => renderActions(record) },
            ];
        }

        if (sectionKey === 'cms-pages') {
            return [
                { title: 'Title', dataIndex: 'title', key: 'title' },
                { title: 'Slug', dataIndex: 'slug', key: 'slug' },
                { title: 'Status', dataIndex: 'status', key: 'status', render: renderStatusTag },
                { title: 'SEO', key: 'seo', render: (_, record) => record.meta_title || record.meta_description ? <Text type="secondary">{record.meta_title || record.meta_description}</Text> : 'Chưa có' },
                { title: 'Tác vụ', key: 'actions', render: (_, record) => renderActions(record) },
            ];
        }

        if (sectionKey === 'cms-posts') {
            return [
                {
                    title: 'Post',
                    dataIndex: 'title',
                    key: 'title',
                    render: (value, record) => (
                        <Space size={12} align="start">
                            <Button type="text" style={{ padding: 0, width: 56, height: 56 }} onClick={() => openPostDetailsDrawer(record)}>
                                {record.featured_media_url ? (
                                    <img
                                        src={record.featured_media_url}
                                        alt={value}
                                        style={{ width: 56, height: 56, objectFit: 'cover', borderRadius: 12, border: '1px solid #dbe7e4', display: 'block' }}
                                    />
                                ) : (
                                    <div
                                        style={{
                                            width: 56,
                                            height: 56,
                                            borderRadius: 12,
                                            border: '1px solid #dbe7e4',
                                            background: '#f4f7f6',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            color: '#8aa19a',
                                            fontSize: 12,
                                            fontWeight: 600,
                                        }}
                                    >
                                        No Img
                                    </div>
                                )}
                            </Button>
                            <Space direction="vertical" size={2} align="start">
                                <Button type="link" style={{ paddingInline: 0, height: 'auto' }} onClick={() => openPostDetailsDrawer(record)}>
                                    <Text strong style={{ color: '#1677ff' }}>{value}</Text>
                                </Button>
                                <Text type="secondary">{record.category_name || 'Chưa phân loại'}</Text>
                            </Space>
                        </Space>
                    ),
                },
                { title: 'Slug', dataIndex: 'slug', key: 'slug' },
                { title: 'Category', dataIndex: 'category_name', key: 'category_name', render: (value) => value || 'Chưa phân loại' },
                { title: 'Status', dataIndex: 'status', key: 'status', render: renderStatusTag },
                { title: 'Publish At', dataIndex: 'publish_at', key: 'publish_at', render: formatPublishAt },
                { title: 'Tác vụ', key: 'actions', render: (_, record) => renderActions(record) },
            ];
        }

        if (sectionKey === 'cms-products') {
            return [
                {
                    title: 'Sản phẩm',
                    dataIndex: 'name',
                    key: 'name',
                    render: (value, record) => (
                        <Button type="link" style={{ paddingInline: 0, height: 'auto', textAlign: 'left' }} onClick={() => setSelectedProduct(record)}>
                            <Space size={12} align="center">
                                {record.image_url ? (
                                    <img
                                        src={record.image_url}
                                        alt={value}
                                        style={{
                                            width: 56,
                                            height: 56,
                                            objectFit: 'cover',
                                            borderRadius: 10,
                                            border: '1px solid #edf0f2',
                                            background: '#f6f8f9',
                                            flexShrink: 0,
                                        }}
                                    />
                                ) : (
                                    <div
                                        style={{
                                            width: 56,
                                            height: 56,
                                            display: 'grid',
                                            placeItems: 'center',
                                            borderRadius: 10,
                                            border: '1px solid #edf0f2',
                                            background: '#f6f8f9',
                                            color: '#8c9aa5',
                                            fontSize: 11,
                                            fontWeight: 700,
                                            flexShrink: 0,
                                        }}
                                    >
                                        No Img
                                    </div>
                                )}
                                <Space direction="vertical" size={4} align="start">
                                <Space size={8} wrap>
                                    <Text strong style={{ color: '#1677ff' }}>{value}</Text>
                                    {record.is_highlight ? <Tag color="gold">Nổi bật</Tag> : null}
                                </Space>
                                <Text type="secondary">{record.category_name || 'Chưa gắn danh mục'}</Text>
                                </Space>
                            </Space>
                        </Button>
                    ),
                },
                { title: 'Giá', dataIndex: 'price', key: 'price', render: (value) => Number(value ?? 0).toLocaleString('vi-VN') },
                {
                    title: 'Kho / Đã mua',
                    key: 'inventory',
                    render: (_, record) => (
                        <Space direction="vertical" size={0}>
                            <Text strong>{`Kho: ${record.stock ?? 0}`}</Text>
                            <Text type="secondary">{`Đã mua: ${record.sold_count ?? 0}`}</Text>
                        </Space>
                    ),
                },
                { title: 'Tác vụ', key: 'actions', render: (_, record) => renderActions(record) },
            ];
        }

        if (sectionKey === 'cms-orders') {
            return [
                { title: 'Mã đơn', dataIndex: 'order_code', key: 'order_code', render: (value) => <Text strong>{value}</Text> },
                {
                    title: 'Khách hàng',
                    key: 'customer',
                    render: (_, record) => (
                        <Space direction="vertical" size={0}>
                            <Text strong>{record.customer_name}</Text>
                            <Text type="secondary">{record.customer_phone}</Text>
                            <Text type="secondary">{record.customer_email || 'Chưa có email'}</Text>
                        </Space>
                    ),
                },
                { title: 'Trạng thái', dataIndex: 'status', key: 'status', render: (value) => renderOrderStatusTag(value) },
                { title: 'Thanh toán', dataIndex: 'payment_label', key: 'payment_label' },
                { title: 'Tổng tiền', dataIndex: 'subtotal', key: 'subtotal', render: (value) => `${Number(value ?? 0).toLocaleString('vi-VN')}đ` },
                { title: 'Thời gian', dataIndex: 'placed_at', key: 'placed_at', render: formatPublishAt },
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
                {
                    title: 'Menu',
                    dataIndex: 'name',
                    key: 'name',
                    render: (value, record) => (
                        <Button type="link" style={{ paddingInline: 0, height: 'auto' }} onClick={() => openEditModal(record)}>
                            <Text strong style={{ color: '#1677ff' }}>{value}</Text>
                        </Button>
                    ),
                },
                { title: 'Location', dataIndex: 'location', key: 'location', render: (value) => <Tag>{value}</Tag> },
                { title: 'Items', key: 'items', render: (_, record) => countMenuItems(record.items ?? []) },
                { title: 'Tác vụ', key: 'actions', render: (_, record) => renderActions(record) },
            ];
        }

        if (sectionKey === 'cms-featured-categories') {
            return [
                {
                    title: 'Nhóm',
                    dataIndex: 'name',
                    key: 'name',
                    render: (value, record) => (
                        <Button type="link" style={{ paddingInline: 0, height: 'auto' }} onClick={() => openEditModal(record)}>
                            <Text strong style={{ color: '#1677ff' }}>{value}</Text>
                        </Button>
                    ),
                },
                { title: 'Vị trí', dataIndex: 'location', key: 'location', render: (value) => <Tag>{value}</Tag> },
                { title: 'Items', key: 'items', render: (_, record) => (record.items ?? []).length },
                { title: 'Tác vụ', key: 'actions', render: (_, record) => renderActions(record) },
            ];
        }

        if (sectionKey === 'cms-side-promos') {
            return [
                {
                    title: 'Block',
                    dataIndex: 'name',
                    key: 'name',
                    render: (value, record) => (
                        <Button type="link" style={{ paddingInline: 0, height: 'auto' }} onClick={() => openEditModal(record)}>
                            <Text strong style={{ color: '#1677ff' }}>{value}</Text>
                        </Button>
                    ),
                },
                { title: 'Vị trí', dataIndex: 'location', key: 'location', render: (value) => <Tag>{value}</Tag> },
                { title: 'Promo', key: 'items', render: (_, record) => (record.items ?? []).length },
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

        if (sectionKey === 'cms-landing-pages') {
            return (
                <Suspense fallback={null}>
                    <LandingPageFormModal
                        open={modalOpen}
                        canManage={sectionPermissions.canCreate || sectionPermissions.canUpdate}
                        editingPage={editingRecord}
                        locales={data?.locales ?? []}
                        defaultLocale={frontendLocale}
                        onCancel={() => setModalOpen(false)}
                        onSubmit={handleSaveRecord}
                    />
                </Suspense>
            );
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
                        callAdminApi={callAdminApi}
                        onCancel={() => setModalOpen(false)}
                        onSubmit={handleSaveRecord}
                    />
                </Suspense>
            );
        }

        if (sectionKey === 'cms-services') {
            return (
                <Suspense fallback={null}>
                    <CmsServiceFormModal
                        open={modalOpen}
                        canManage={sectionPermissions.canCreate || sectionPermissions.canUpdate}
                        editingService={editingRecord}
                        mediaOptions={data?.media ?? []}
                        categoryOptions={serviceCategoryOptions}
                        onCancel={() => setModalOpen(false)}
                        onSubmit={handleSaveRecord}
                    />
                </Suspense>
            );
        }

        if (sectionKey === 'cms-projects') {
            return (
                <Suspense fallback={null}>
                    <CmsProjectFormModal
                        open={modalOpen}
                        canManage={sectionPermissions.canCreate || sectionPermissions.canUpdate}
                        editingProject={editingRecord}
                        mediaOptions={data?.media ?? []}
                        onCancel={() => setModalOpen(false)}
                        onSubmit={handleSaveRecord}
                    />
                </Suspense>
            );
        }

        if (sectionKey === 'cms-testimonials') {
            return (
                <Suspense fallback={null}>
                    <CmsTestimonialFormModal
                        open={modalOpen}
                        canManage={sectionPermissions.canCreate || sectionPermissions.canUpdate}
                        editingTestimonial={editingRecord}
                        mediaOptions={data?.media ?? []}
                        onCancel={() => setModalOpen(false)}
                        onSubmit={handleSaveRecord}
                    />
                </Suspense>
            );
        }

        if (sectionKey === 'cms-team-members') {
            return (
                <Suspense fallback={null}>
                    <CmsTeamMemberFormModal
                        open={modalOpen}
                        canManage={sectionPermissions.canCreate || sectionPermissions.canUpdate}
                        editingMember={editingRecord}
                        mediaOptions={data?.media ?? []}
                        onCancel={() => setModalOpen(false)}
                        onSubmit={handleSaveRecord}
                    />
                </Suspense>
            );
        }

        if (sectionKey === 'cms-partners') {
            return (
                <Suspense fallback={null}>
                    <CmsPartnerFormModal
                        open={modalOpen}
                        canManage={sectionPermissions.canCreate || sectionPermissions.canUpdate}
                        editingPartner={editingRecord}
                        mediaOptions={data?.media ?? []}
                        callAdminApi={callAdminApi}
                        onCancel={() => setModalOpen(false)}
                        onSubmit={handleSaveRecord}
                    />
                </Suspense>
            );
        }

        if (sectionKey === 'cms-products') {
            return (
                <Suspense fallback={null}>
                    <CatalogProductFormModal
                        open={modalOpen}
                        canManage={sectionPermissions.canCreate || sectionPermissions.canUpdate}
                        editingProduct={editingRecord}
                        categoryOptions={productCategoryOptions}
                        callAdminApi={callAdminApi}
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

        if (sectionKey === 'cms-featured-categories') {
            return (
                <Suspense fallback={null}>
                    <CmsFeaturedCategoryFormModal
                        open={modalOpen}
                        canManage={sectionPermissions.canCreate || sectionPermissions.canUpdate}
                        editingGroup={editingRecord}
                        locationOptions={data?.locations ?? []}
                        linkOptions={data?.linkOptions ?? {}}
                        onCancel={() => setModalOpen(false)}
                        onSubmit={handleSaveRecord}
                    />
                </Suspense>
            );
        }

        if (sectionKey === 'cms-side-promos') {
            return (
                <Suspense fallback={null}>
                    <CmsSidePromoFormModal
                        open={modalOpen}
                        canManage={sectionPermissions.canCreate || sectionPermissions.canUpdate}
                        editingGroup={editingRecord}
                        locationOptions={data?.locations ?? []}
                        linkOptions={data?.linkOptions ?? {}}
                        mediaOptions={data?.media ?? []}
                        callAdminApi={callAdminApi}
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
                        linkOptions={data?.linkOptions ?? {}}
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

    const tableExtra = sectionKey === 'cms-orders'
        ? <Input allowClear value={keyword} onChange={(event) => setKeyword(event.target.value)} placeholder="TÃ¬m theo mÃ£ Ä‘Æ¡n, khÃ¡ch hÃ ng, Ä‘iá»‡n thoáº¡i..." style={{ width: 320 }} />
        : sectionKey === 'cms-menus'
            ? (
                <Space wrap>
                    <Button href={homeAdminUrl}>Cài đặt trang chủ</Button>
                    <Button type="primary" icon={<PlusOutlined />} disabled={!sectionPermissions.canCreate} onClick={openCreateModal}>{createButtonLabel}</Button>
                </Space>
            )
            : sectionKey === 'cms-posts'
                ? (
                    <Space wrap>
                        <Button onClick={openCategoryManager}>Cài đặt danh mục tin tức</Button>
                        <Button type="primary" icon={<PlusOutlined />} disabled={!sectionPermissions.canCreate} onClick={openCreateModal}>{createButtonLabel}</Button>
                    </Space>
                )
            : sectionKey === 'cms-products'
                ? (
                    <Space wrap>
                        <Button onClick={openProductCategoryManager}>Cài đặt danh mục SP</Button>
                        <Button type="primary" icon={<PlusOutlined />} disabled={!sectionPermissions.canCreate} onClick={openCreateModal}>{createButtonLabel}</Button>
                    </Space>
                )
            : sectionKey === 'cms-services'
                ? (
                    <Space wrap>
                        <Button onClick={openServiceCategoryManager}>Cài đặt danh mục dịch vụ</Button>
                        <Button type="primary" icon={<PlusOutlined />} disabled={!sectionPermissions.canCreate} onClick={openCreateModal}>{createButtonLabel}</Button>
                    </Space>
                )
            : sectionKey === 'cms-partners'
                ? (
                    <Space wrap>
                        {partnerBulkActions}
                        <Button type="primary" icon={<PlusOutlined />} disabled={!sectionPermissions.canCreate} onClick={openCreateModal}>{createButtonLabel}</Button>
                    </Space>
                )
            : sectionKey !== 'cms-media' && sectionKey !== 'cms-products'
                ? <Button type="primary" icon={<PlusOutlined />} disabled={!sectionPermissions.canCreate} onClick={openCreateModal}>{createButtonLabel}</Button>
                : null;

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
                </Row>
            ) : null}

            {sectionKey === 'cms-media' ? (
                <Card title="Upload Media" extra={<Button type="primary" icon={<UploadOutlined />} disabled={!sectionPermissions.canCreate || !mediaFile} onClick={handleUploadMedia}>Upload media</Button>}>
                    <Row gutter={[12, 12]}>
                        <Col xs={24} md={8}><Input value={mediaUpload.title} onChange={(event) => setMediaUpload((current) => ({ ...current, title: event.target.value }))} placeholder="Tiêu đề media" /></Col>
                        <Col xs={24} md={8}><Input value={mediaUpload.alt_text} onChange={(event) => setMediaUpload((current) => ({ ...current, alt_text: event.target.value }))} placeholder="Alt text" /></Col>
                        <Col xs={24} md={8}><input type="file" onChange={(event) => setMediaFile(event.target.files?.[0] ?? null)} /></Col>
                    </Row>
                </Card>
            ) : null}

            <Card
                className="admin-table-card"
                title={`${sectionConfig.title} (${sectionKey === 'cms-orders' ? (data?.stats?.total_orders ?? 0) : (data?.total ?? 0)})`}
                extra={tableExtra}
            >
                {sectionKey === 'cms-products' ? (
                    <Row gutter={[16, 16]} align="top">
                        <Col xs={24} xl={7}>
                            <Card size="small" title="Tìm kiếm và lọc" className="admin-table-filters">
                                <Space direction="vertical" size={12} style={{ width: '100%' }}>
                                    <Space direction="vertical" size={4} style={{ width: '100%' }}>
                                        <Text strong>Tìm kiếm</Text>
                                        <Input
                                            allowClear
                                            value={keyword}
                                            onChange={(event) => setKeyword(event.target.value)}
                                            placeholder="Tìm theo tên, slug, danh mục, mã sản phẩm..."
                                        />
                                    </Space>
                                    <Space direction="vertical" size={4} style={{ width: '100%' }}>
                                        <Text strong>Danh mục</Text>
                                        <Select
                                            value={productCategoryFilter}
                                            onChange={setProductCategoryFilter}
                                            options={[{ label: 'Tất cả danh mục', value: 'all' }, ...productCategoryOptions]}
                                            style={{ width: '100%' }}
                                        />
                                    </Space>
                                    <Space direction="vertical" size={4} style={{ width: '100%' }}>
                                        <Text strong>Nổi bật</Text>
                                        <Select
                                            value={productFeaturedFilter}
                                            onChange={setProductFeaturedFilter}
                                            options={[
                                                { label: 'Mọi loại', value: 'all' },
                                                { label: 'Nổi bật', value: 'featured' },
                                                { label: 'Thường', value: 'normal' },
                                            ]}
                                            style={{ width: '100%' }}
                                        />
                                    </Space>
                                    <Space direction="vertical" size={4} style={{ width: '100%' }}>
                                        <Text strong>Kích hoạt</Text>
                                        <Select
                                            value={productActiveFilter}
                                            onChange={setProductActiveFilter}
                                            options={[
                                                { label: 'Mọi trạng thái', value: 'all' },
                                                { label: 'Đang kích hoạt', value: 'active' },
                                                { label: 'Đã tắt', value: 'inactive' },
                                            ]}
                                            style={{ width: '100%' }}
                                        />
                                    </Space>
                                    <Space direction="vertical" size={4} style={{ width: '100%' }}>
                                        <Text strong>Public</Text>
                                        <Select
                                            value={productPublishFilter}
                                            onChange={setProductPublishFilter}
                                            options={[
                                                { label: 'Mọi trạng thái public', value: 'all' },
                                                { label: 'Đang public', value: 'public' },
                                                { label: 'Chưa public', value: 'private' },
                                            ]}
                                            style={{ width: '100%' }}
                                        />
                                    </Space>
                                    <Space direction="vertical" size={4} style={{ width: '100%' }}>
                                        <Text strong>Sắp xếp</Text>
                                        <Select
                                            value={productSort}
                                            onChange={setProductSort}
                                            options={[
                                                { label: 'Mới nhất', value: 'newest' },
                                                { label: 'Cũ nhất', value: 'oldest' },
                                                { label: 'Tên sản phẩm A-Z', value: 'name_asc' },
                                                { label: 'Tên sản phẩm Z-A', value: 'name_desc' },
                                                { label: 'Giá giảm dần', value: 'price_desc' },
                                                { label: 'Giá tăng dần', value: 'price_asc' },
                                                { label: 'Tồn kho giảm dần', value: 'stock_desc' },
                                                { label: 'Tồn kho tăng dần', value: 'stock_asc' },
                                            ]}
                                            style={{ width: '100%' }}
                                        />
                                    </Space>
                                </Space>
                            </Card>
                        </Col>
                        <Col xs={24} xl={17}>
                            <Space direction="vertical" size={12} style={{ width: '100%' }}>
                                {productBulkActions}
                                {filteredItems.length ? (
                                    <Table
                                        rowKey="id"
                                        rowSelection={productRowSelection}
                                        columns={columns}
                                        dataSource={filteredItems}
                                        pagination={{ pageSize: 10, hideOnSinglePage: true }}
                                        scroll={{ x: 980 }}
                                    />
                                ) : (
                                    <Empty description={`Chưa có dữ liệu cho ${sectionConfig.title}.`} />
                                )}
                            </Space>
                        </Col>
                    </Row>
                ) : null}

                {sectionKey !== 'cms-products' && filteredItems.length ? (
                    <Table
                        rowKey="id"
                        rowSelection={partnerRowSelection}
                        columns={columns}
                        dataSource={filteredItems}
                        pagination={{ pageSize: 10, hideOnSinglePage: true }}
                        scroll={{ x: 980 }}
                        onRow={sectionKey === 'cms-orders' ? (record) => ({ onClick: () => setSelectedOrder(record), style: { cursor: 'pointer' } }) : undefined}
                    />
                ) : sectionKey !== 'cms-products' ? (
                    <Empty description={`Chưa có dữ liệu cho ${sectionConfig.title}.`} />
                ) : null}
            </Card>

            <Suspense fallback={null}>
                <LandingBlockManagerDrawer
                    open={sectionKey === 'cms-landing-pages' && blockManagerOpen}
                    page={selectedLandingPage}
                    locale={frontendLocale}
                    canCreate={sectionPermissions.canCreate}
                    canUpdate={sectionPermissions.canUpdate}
                    canDelete={sectionPermissions.canDelete}
                    callAdminApi={callAdminApi}
                    runAdminAction={runAdminAction}
                    onClose={() => {
                        setBlockManagerOpen(false);
                        setSelectedLandingPage(null);
                    }}
                    onChanged={reload}
                />
            </Suspense>

            <Drawer
                title={selectedOrder ? `Chi tiết ${selectedOrder.order_code}` : 'Chi tiết đơn hàng'}
                open={sectionKey === 'cms-orders' && Boolean(selectedOrder)}
                onClose={() => setSelectedOrder(null)}
                width={520}
                destroyOnHidden
            >
                {selectedOrder ? (
                    <Space direction="vertical" size={16} style={{ width: '100%' }}>
                        <Card size="small">
                            <div className="detail-grid detail-grid-2">
                                <div className="detail-tile">
                                    <Text className="detail-label">Khách hàng</Text>
                                    <Text strong>{selectedOrder.customer_name}</Text>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Trạng thái</Text>
                                    {renderOrderStatusTag(selectedOrder.status)}
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Điện thoại</Text>
                                    <Text strong>{selectedOrder.customer_phone}</Text>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Email</Text>
                                    <Text strong>{selectedOrder.customer_email || 'Chưa có email'}</Text>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Địa chỉ</Text>
                                    <Text strong>{selectedOrder.delivery_address}</Text>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Mail xác nhận</Text>
                                    <Text strong>{selectedOrder.email_queued_at ? 'Đã xếp hàng gửi' : 'Chưa xếp hàng'}</Text>
                                </div>
                            </div>
                        </Card>

                        <Card size="small" title="Sản phẩm trong đơn">
                            <Space direction="vertical" size={12} style={{ width: '100%' }}>
                                {(selectedOrder.items ?? []).map((item) => (
                                    <div key={item.id} style={{ display: 'flex', justifyContent: 'space-between', gap: 12, paddingBottom: 12, borderBottom: '1px solid #f0f0f0' }}>
                                        <div>
                                            <Text strong>{item.product_name}</Text>
                                            <div><Text type="secondary">Số lượng: {item.quantity}</Text></div>
                                        </div>
                                        <Text strong>{`${Number(item.line_total ?? 0).toLocaleString('vi-VN')}đ`}</Text>
                                    </div>
                                ))}
                            </Space>
                        </Card>
                    </Space>
                ) : null}
            </Drawer>

            <Modal
                title="Sửa tên hiển thị media"
                open={sectionKey === 'cms-media' && Boolean(editingMediaRecord)}
                onCancel={() => {
                    setEditingMediaRecord(null);
                    mediaEditForm.resetFields();
                }}
                onOk={handleSaveMediaTitle}
                okText="Lưu"
                cancelText="Hủy"
                destroyOnHidden
            >
                <Form form={mediaEditForm} layout="vertical">
                    <Form.Item
                        name="title"
                        label="Tên hiển thị"
                        rules={[{ required: true, message: 'Nhập tên hiển thị cho file' }]}
                    >
                        <Input placeholder="Tên hiển thị" />
                    </Form.Item>
                    <Form.Item name="alt_text" label="Alt text" style={{ marginBottom: 0 }}>
                        <Input placeholder="Mô tả ngắn cho ảnh/file" />
                    </Form.Item>
                </Form>
            </Modal>

            <Modal
                title={`Cập nhật ${selectedProductRowKeys.length} sản phẩm đã chọn`}
                open={sectionKey === 'cms-products' && bulkProductEditOpen}
                onCancel={() => {
                    setBulkProductEditOpen(false);
                    bulkProductEditForm.resetFields();
                }}
                onOk={handleBulkEditProducts}
                okText="Lưu thay đổi"
                cancelText="Hủy"
                destroyOnHidden
            >
                <Form form={bulkProductEditForm} layout="vertical">
                    <Form.Item name="catalog_category_id" label="Danh mục">
                        <Select
                            options={[
                                { label: 'Giữ nguyên danh mục hiện tại', value: BULK_KEEP_VALUE },
                                { label: 'Bỏ danh mục', value: BULK_CLEAR_VALUE },
                                ...productCategoryOptions,
                            ]}
                        />
                    </Form.Item>
                    <Form.Item name="is_featured" label="Nổi bật">
                        <Select
                            options={[
                                { label: 'Giữ nguyên trạng thái hiện tại', value: BULK_KEEP_VALUE },
                                { label: 'Đánh dấu nổi bật', value: 'true' },
                                { label: 'Bỏ nổi bật', value: 'false' },
                            ]}
                        />
                    </Form.Item>
                    <Form.Item name="is_active" label="Trạng thái hiển thị" style={{ marginBottom: 0 }}>
                        <Select
                            options={[
                                { label: 'Giữ nguyên trạng thái hiện tại', value: BULK_KEEP_VALUE },
                                { label: 'Kích hoạt sản phẩm', value: 'true' },
                                { label: 'Tắt sản phẩm', value: 'false' },
                            ]}
                        />
                    </Form.Item>
                </Form>
            </Modal>

            <Drawer
                title={selectedPost?.title ?? 'Chi tiết bài viết'}
                open={sectionKey === 'cms-posts' && Boolean(selectedPost)}
                onClose={() => setSelectedPost(null)}
                width={760}
                destroyOnHidden
                extra={sectionPermissions.canUpdate ? (
                    <Button type="primary" icon={<EditOutlined />} onClick={handleEditPostFromDrawer}>
                        Sửa bài viết
                    </Button>
                ) : null}
            >
                {selectedPost ? (
                    <Space direction="vertical" size={16} style={{ width: '100%' }}>
                        <Card size="small">
                            <div className="detail-grid detail-grid-2">
                                <div className="detail-tile">
                                    <Text className="detail-label">Trạng thái</Text>
                                    {renderStatusTag(selectedPost.status)}
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Danh mục</Text>
                                    <Text strong>{selectedPost.category_name || 'Chưa phân loại'}</Text>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Slug</Text>
                                    <Text strong>{selectedPost.slug || 'Chưa có'}</Text>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Thời gian xuất bản</Text>
                                    <Text strong>{formatPublishAt(selectedPost.publish_at)}</Text>
                                </div>
                            </div>
                        </Card>

                        {selectedPost.featured_media_url ? (
                            <img
                                src={selectedPost.featured_media_url}
                                alt={selectedPost.title}
                                style={{ width: '100%', maxHeight: 320, objectFit: 'cover', borderRadius: 16, border: '1px solid #dbe7e4' }}
                            />
                        ) : null}

                        <Card size="small" title="Mô tả ngắn">
                            <Paragraph style={{ marginBottom: 0 }}>
                                {selectedPost.excerpt || 'Chưa có mô tả ngắn.'}
                            </Paragraph>
                        </Card>

                        <Card size="small" title="SEO">
                            <div className="detail-grid detail-grid-2">
                                <div className="detail-tile">
                                    <Text className="detail-label">SEO title</Text>
                                    <Text strong>{selectedPost.meta_title || 'Chưa có'}</Text>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">SEO description</Text>
                                    <Text strong>{selectedPost.meta_description || 'Chưa có'}</Text>
                                </div>
                            </div>
                        </Card>

                        <Card size="small" title="Nội dung chi tiết">
                            {selectedPost.body ? (
                                <div dangerouslySetInnerHTML={{ __html: selectedPost.body }} />
                            ) : (
                                <Paragraph style={{ marginBottom: 0 }}>Chưa có nội dung chi tiết.</Paragraph>
                            )}
                        </Card>
                    </Space>
                ) : null}
            </Drawer>

            <Drawer
                title={selectedProduct?.name ?? 'Chi tiết sản phẩm'}
                open={sectionKey === 'cms-products' && Boolean(selectedProduct)}
                onClose={() => setSelectedProduct(null)}
                width={620}
                destroyOnHidden
            >
                {selectedProduct ? (
                    <Space direction="vertical" size={16} style={{ width: '100%' }}>
                        {selectedProduct.image_url ? (
                            <img
                                src={selectedProduct.image_url}
                                alt={selectedProduct.name}
                                style={{ width: '100%', maxHeight: 280, objectFit: 'cover', borderRadius: 16, border: '1px solid #dbe7e4' }}
                            />
                        ) : null}

                        <Card size="small">
                            <div className="detail-grid detail-grid-2">
                                <div className="detail-tile">
                                    <Text className="detail-label">Danh mục</Text>
                                    <Text strong>{selectedProduct.category_name || 'Chưa gắn'}</Text>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Mã sản phẩm</Text>
                                    <Text strong>{selectedProduct.sku || 'Chưa có'}</Text>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Giá bán</Text>
                                    <Text strong>{`${Number(selectedProduct.price ?? 0).toLocaleString('vi-VN')}đ`}</Text>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Giá gốc</Text>
                                    <Text strong>{selectedProduct.original_price ? `${Number(selectedProduct.original_price).toLocaleString('vi-VN')}đ` : 'Chưa có'}</Text>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Tồn kho</Text>
                                    <Text strong>{selectedProduct.stock ?? 0}</Text>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Đã mua</Text>
                                    <Text strong>{selectedProduct.sold_count ?? 0}</Text>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Nổi bật</Text>
                                    {selectedProduct.is_highlight ? <Tag color="gold">highlight</Tag> : <Tag>normal</Tag>}
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Hạn deal</Text>
                                    <Text strong>{formatPublishAt(selectedProduct.deal_end_at)}</Text>
                                </div>
                            </div>
                        </Card>

                        <Card size="small" title="Mô tả ngắn">
                            <Paragraph style={{ marginBottom: 0 }}>
                                {selectedProduct.short_description || 'Chưa có mô tả ngắn.'}
                            </Paragraph>
                        </Card>

                        <Card size="small" title="Chi tiết sản phẩm">
                            <Paragraph style={{ marginBottom: 0, whiteSpace: 'pre-wrap' }}>
                                {selectedProduct.detail_content || 'Chưa có nội dung chi tiết.'}
                            </Paragraph>
                        </Card>

                        <Card size="small" title="Thông tin sử dụng">
                            <Space direction="vertical" size={12} style={{ width: '100%' }}>
                                <div>
                                    <Text className="detail-label">Điểm nổi bật</Text>
                                    <Paragraph style={{ marginBottom: 0, whiteSpace: 'pre-wrap' }}>
                                        {selectedProduct.highlights || 'Chưa cấu hình'}
                                    </Paragraph>
                                </div>
                                <div>
                                    <Text className="detail-label">Điều kiện áp dụng</Text>
                                    <Paragraph style={{ marginBottom: 0, whiteSpace: 'pre-wrap' }}>
                                        {selectedProduct.usage_terms || 'Chưa cấu hình'}
                                    </Paragraph>
                                </div>
                                <div>
                                    <Text className="detail-label">Khu vực áp dụng</Text>
                                    <Paragraph style={{ marginBottom: 0, whiteSpace: 'pre-wrap' }}>
                                        {selectedProduct.usage_location || 'Chưa cấu hình'}
                                    </Paragraph>
                                </div>
                            </Space>
                        </Card>
                    </Space>
                ) : null}
            </Drawer>

            <Modal
                title="Cài đặt danh mục tin tức"
                open={categoryManagerOpen}
                onCancel={() => setCategoryManagerOpen(false)}
                footer={null}
                width={980}
                destroyOnHidden
            >
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
                        <Text type="secondary">Quản lý nhanh danh mục để gắn cho bài viết mà không cần rời màn Tin tức.</Text>
                        <Button type="primary" icon={<PlusOutlined />} disabled={!canManageCategories} onClick={openCreateCategory}>
                            Thêm danh mục
                        </Button>
                    </Space>

                    <Table
                        rowKey="id"
                        loading={categoryLoading}
                        dataSource={categoryItems}
                        pagination={{ pageSize: 8, hideOnSinglePage: true }}
                        columns={[
                            {
                                title: 'Danh mục',
                                dataIndex: 'name',
                                key: 'name',
                                render: (value, record) => (
                                    <Space direction="vertical" size={0}>
                                        <Text strong>{value}</Text>
                                        <Text type="secondary">{record.description || record.slug}</Text>
                                    </Space>
                                ),
                            },
                            { title: 'Slug', dataIndex: 'slug', key: 'slug' },
                            {
                                title: 'Danh mục cha',
                                dataIndex: 'parent_id',
                                key: 'parent_id',
                                render: (value) => categoryItems.find((category) => category.id === value)?.name || '-',
                            },
                            {
                                title: 'SEO',
                                key: 'seo',
                                render: (_, record) => record.meta_title || record.meta_description ? <Text type="secondary">{record.meta_title || record.meta_description}</Text> : 'Chưa có',
                            },
                            {
                                title: 'Tác vụ',
                                key: 'actions',
                                render: (_, record) => (
                                    <Space>
                                        <Button size="small" icon={<EditOutlined />} disabled={!canManageCategories} onClick={() => openEditCategory(record)}>
                                            Sửa
                                        </Button>
                                        <Button size="small" danger icon={<DeleteOutlined />} disabled={!canManageCategories} onClick={() => handleDeleteCategory(record)}>
                                            Xóa
                                        </Button>
                                    </Space>
                                ),
                            },
                        ]}
                    />
                </Space>
            </Modal>

            <Suspense fallback={null}>
                <CmsCategoryFormModal
                    open={categoryFormOpen}
                    canManage={canManageCategories}
                    editingCategory={editingCategoryRecord}
                    parentOptions={categoryParentOptions}
                    onCancel={() => {
                        setCategoryFormOpen(false);
                        setEditingCategoryRecord(emptyCategory);
                    }}
                    onSubmit={handleSaveCategory}
                />
            </Suspense>

            <Modal
                title="Cài đặt danh mục dịch vụ"
                open={serviceCategoryManagerOpen}
                onCancel={() => setServiceCategoryManagerOpen(false)}
                footer={null}
                width={1040}
                destroyOnHidden
            >
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
                        <Text type="secondary">Quản lý danh mục dịch vụ ngay trong màn Services để tiện tạo, sửa và gắn danh mục.</Text>
                        <Button type="primary" icon={<PlusOutlined />} disabled={!sectionPermissions.canCreate} onClick={openCreateServiceCategory}>
                            Thêm danh mục dịch vụ
                        </Button>
                    </Space>

                    <Table
                        rowKey="id"
                        loading={serviceCategoryLoading}
                        dataSource={serviceCategoryItems}
                        pagination={{ pageSize: 8, hideOnSinglePage: true }}
                        columns={[
                            {
                                title: 'Danh mục dịch vụ',
                                dataIndex: 'name',
                                key: 'name',
                                render: (value, record) => (
                                    <Space size={12} align="start">
                                        {record.image_url ? (
                                            <img src={record.image_url} alt={value} style={{ width: 56, height: 56, objectFit: 'cover', borderRadius: 12, border: '1px solid #dbe7e4' }} />
                                        ) : (
                                            <div style={{ width: 56, height: 56, borderRadius: 12, border: '1px solid #dbe7e4', background: '#f4f7f6', display: 'grid', placeItems: 'center', color: '#8aa19a', fontSize: 12, fontWeight: 600 }}>
                                                No Img
                                            </div>
                                        )}
                                        <Space direction="vertical" size={0}>
                                            <Text strong>{value}</Text>
                                            <Text type="secondary">{record.description || record.slug}</Text>
                                        </Space>
                                    </Space>
                                ),
                            },
                            { title: 'Slug', dataIndex: 'slug', key: 'slug' },
                            { title: 'Danh mục cha', dataIndex: 'parent_name', key: 'parent_name', render: (value) => value || '-' },
                            { title: 'Dịch vụ', dataIndex: 'services_count', key: 'services_count', render: (value) => value ?? 0 },
                            { title: 'Thứ tự', dataIndex: 'sort_order', key: 'sort_order' },
                            { title: 'Trạng thái', dataIndex: 'is_active', key: 'is_active', render: (value) => value ? <Tag color="green">Đang bật</Tag> : <Tag>Tắt</Tag> },
                            {
                                title: 'Tác vụ',
                                key: 'actions',
                                render: (_, record) => (
                                    <Space>
                                        <Button size="small" icon={<EditOutlined />} disabled={!sectionPermissions.canUpdate} onClick={() => openEditServiceCategory(record)}>
                                            Sửa
                                        </Button>
                                        <Button size="small" danger icon={<DeleteOutlined />} disabled={!sectionPermissions.canDelete} onClick={() => handleDeleteServiceCategory(record)}>
                                            Xóa
                                        </Button>
                                    </Space>
                                ),
                            },
                        ]}
                    />
                </Space>
            </Modal>

            <Suspense fallback={null}>
                <CatalogCategoryFormModal
                    open={serviceCategoryFormOpen}
                    canManage={sectionPermissions.canCreate || sectionPermissions.canUpdate}
                    editingCategory={editingServiceCategoryRecord}
                    categoryOptions={serviceCategoryParentOptions}
                    callAdminApi={callAdminApi}
                    onCancel={() => {
                        setServiceCategoryFormOpen(false);
                        setEditingServiceCategoryRecord(emptyServiceCategory);
                    }}
                    onSubmit={handleSaveServiceCategory}
                />
            </Suspense>

            <Modal
                title="Cài đặt danh mục SP"
                open={productCategoryManagerOpen}
                onCancel={() => setProductCategoryManagerOpen(false)}
                footer={null}
                width={1040}
                destroyOnHidden
            >
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
                        <Text type="secondary">Quản lý danh mục sản phẩm ngay trong màn Products để tiện tạo, sửa và gắn danh mục.</Text>
                        <Button type="primary" icon={<PlusOutlined />} disabled={!sectionPermissions.canCreate} onClick={openCreateProductCategory}>
                            Thêm danh mục SP
                        </Button>
                    </Space>

                    <Table
                        rowKey="id"
                        loading={productCategoryLoading}
                        dataSource={productCategoryItems}
                        pagination={{ pageSize: 8, hideOnSinglePage: true }}
                        columns={[
                            {
                                title: 'Danh mục SP',
                                dataIndex: 'name',
                                key: 'name',
                                render: (value, record) => (
                                    <Space size={12} align="start">
                                        {record.image_url ? (
                                            <img src={record.image_url} alt={value} style={{ width: 56, height: 56, objectFit: 'cover', borderRadius: 12, border: '1px solid #dbe7e4' }} />
                                        ) : (
                                            <div style={{ width: 56, height: 56, borderRadius: 12, border: '1px solid #dbe7e4', background: '#f4f7f6', display: 'grid', placeItems: 'center', color: '#8aa19a', fontSize: 12, fontWeight: 600 }}>
                                                No Img
                                            </div>
                                        )}
                                        <Space direction="vertical" size={0}>
                                            <Text strong>{value}</Text>
                                            <Text type="secondary">{record.description || record.slug}</Text>
                                        </Space>
                                    </Space>
                                ),
                            },
                            { title: 'Slug', dataIndex: 'slug', key: 'slug' },
                            { title: 'Danh mục cha', dataIndex: 'parent_name', key: 'parent_name', render: (value) => value || '-' },
                            { title: 'Sản phẩm', dataIndex: 'products_count', key: 'products_count', render: (value) => value ?? 0 },
                            { title: 'Thứ tự', dataIndex: 'sort_order', key: 'sort_order' },
                            { title: 'Trạng thái', dataIndex: 'is_active', key: 'is_active', render: (value) => value ? <Tag color="green">Đang bật</Tag> : <Tag>Tắt</Tag> },
                            {
                                title: 'Tác vụ',
                                key: 'actions',
                                render: (_, record) => (
                                    <Space>
                                        <Button size="small" icon={<EditOutlined />} disabled={!sectionPermissions.canUpdate} onClick={() => openEditProductCategory(record)}>
                                            Sửa
                                        </Button>
                                        <Button size="small" danger icon={<DeleteOutlined />} disabled={!sectionPermissions.canDelete} onClick={() => handleDeleteProductCategory(record)}>
                                            Xóa
                                        </Button>
                                    </Space>
                                ),
                            },
                        ]}
                    />
                </Space>
            </Modal>

            <Suspense fallback={null}>
                <CatalogCategoryFormModal
                    open={productCategoryFormOpen}
                    canManage={sectionPermissions.canCreate || sectionPermissions.canUpdate}
                    editingCategory={editingProductCategoryRecord}
                    categoryOptions={productCategoryParentOptions}
                    callAdminApi={callAdminApi}
                    onCancel={() => {
                        setProductCategoryFormOpen(false);
                        setEditingProductCategoryRecord(emptyProductCategory);
                    }}
                    onSubmit={handleSaveProductCategory}
                />
            </Suspense>

            {renderModal()}
        </Space>
    );
}

function renderOrderStatusTag(status) {
    const statusMap = {
        placed: { color: 'blue', label: 'Mới đặt' },
        pending: { color: 'gold', label: 'Chờ xử lý' },
        processing: { color: 'processing', label: 'Đang xử lý' },
        completed: { color: 'green', label: 'Hoàn tất' },
        cancelled: { color: 'red', label: 'Đã hủy' },
    };

    const statusMeta = statusMap[status] ?? { color: 'default', label: status || 'Không rõ' };

    return <Tag color={statusMeta.color}>{statusMeta.label}</Tag>;
}
