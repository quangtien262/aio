import { Suspense, lazy, useEffect, useMemo, useState } from 'react';
import CopyOutlined from '@ant-design/icons/CopyOutlined';
import DeleteOutlined from '@ant-design/icons/DeleteOutlined';
import EditOutlined from '@ant-design/icons/EditOutlined';
import EyeOutlined from '@ant-design/icons/EyeOutlined';
import FolderOpenOutlined from '@ant-design/icons/FolderOpenOutlined';
import FolderOutlined from '@ant-design/icons/FolderOutlined';
import HolderOutlined from '@ant-design/icons/HolderOutlined';
import InboxOutlined from '@ant-design/icons/InboxOutlined';
import MoreOutlined from '@ant-design/icons/MoreOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import UploadOutlined from '@ant-design/icons/UploadOutlined';
import { DndContext, PointerSensor, closestCenter, useSensor, useSensors } from '@dnd-kit/core';
import { SortableContext, arrayMove, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import DatePicker from 'antd/es/date-picker';
import Drawer from 'antd/es/drawer';
import Dropdown from 'antd/es/dropdown';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import message from 'antd/es/message';
import Modal from 'antd/es/modal';
import Pagination from 'antd/es/pagination';
import Radio from 'antd/es/radio';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import Upload from 'antd/es/upload';
import dayjs from 'dayjs';
import useAdminRouteResource from '../../../shared/hooks/useAdminRouteResource';
import { ADMIN_API_ROUTES, adminApi } from '../../../shared/config/routes';

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
const { RangePicker } = DatePicker;
const { Dragger } = Upload;

function CmsMediaThumbnail({ src, alt, size = 64, radius = 12 }) {
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        setFailed(false);
    }, [src]);

    const style = {
        width: size,
        height: size,
        borderRadius: radius,
        border: '1px solid #dbe7e4',
        background: '#f4f7f6',
        display: 'block',
    };

    if (!src || failed) {
        return (
            <div style={{ ...style, display: 'grid', placeItems: 'center', color: '#8aa19a', fontSize: 12, fontWeight: 600 }}>
                No Img
            </div>
        );
    }

    return (
        <img
            src={src}
            alt={alt}
            loading="lazy"
            referrerPolicy="no-referrer"
            onError={() => setFailed(true)}
            style={{ ...style, objectFit: 'cover' }}
        />
    );
}

function resolveRecordImageUrl(record) {
    if (record?.featured_image_url) {
        return record.featured_image_url;
    }

    if (record?.image_url) {
        return record.image_url;
    }

    const featuredImage = (record?.images ?? []).find((image) => image?.is_featured && image?.image_url);
    const firstImage = (record?.images ?? []).find((image) => image?.image_url);

    return featuredImage?.image_url || firstImage?.image_url || record?.file_url || '';
}

const orderStatusOptions = [
    { label: 'Tất cả trạng thái', value: 'all' },
    { label: 'Mới đặt', value: 'placed' },
    { label: 'Chờ xử lý', value: 'pending' },
    { label: 'Đang xử lý', value: 'processing' },
    { label: 'Hoàn tất', value: 'completed' },
    { label: 'Đã hủy', value: 'cancelled' },
];

const orderDatePresetOptions = [
    { label: 'Tùy chọn', value: 'custom' },
    { label: 'Hôm nay', value: 'today' },
    { label: 'Hôm qua', value: 'yesterday' },
    { label: 'Tuần này', value: 'this_week' },
    { label: 'Tháng này', value: 'this_month' },
    { label: 'Tháng trước', value: 'last_month' },
    { label: 'Quý này', value: 'this_quarter' },
    { label: 'Quý trước', value: 'last_quarter' },
    { label: 'Năm nay', value: 'this_year' },
    { label: 'Năm ngoái', value: 'last_year' },
];

const resolveOrderDatePresetRange = (preset) => {
    const now = dayjs();
    const quarterStartMonth = Math.floor(now.month() / 3) * 3;
    const thisQuarterStart = now.month(quarterStartMonth).startOf('month');
    const lastQuarterStart = thisQuarterStart.subtract(3, 'month');

    switch (preset) {
        case 'today':
            return [now.startOf('day'), now.endOf('day')];
        case 'yesterday': {
            const yesterday = now.subtract(1, 'day');
            return [yesterday.startOf('day'), yesterday.endOf('day')];
        }
        case 'this_week':
            return [now.startOf('week'), now.endOf('week')];
        case 'this_month':
            return [now.startOf('month'), now.endOf('month')];
        case 'last_month': {
            const lastMonth = now.subtract(1, 'month');
            return [lastMonth.startOf('month'), lastMonth.endOf('month')];
        }
        case 'this_quarter':
            return [thisQuarterStart.startOf('day'), thisQuarterStart.add(2, 'month').endOf('month')];
        case 'last_quarter':
            return [lastQuarterStart.startOf('day'), lastQuarterStart.add(2, 'month').endOf('month')];
        case 'this_year':
            return [now.startOf('year'), now.endOf('year')];
        case 'last_year': {
            const lastYear = now.subtract(1, 'year');
            return [lastYear.startOf('year'), lastYear.endOf('year')];
        }
        default:
            return null;
    }
};

const sectionConfigMap = {
    'cms-pages': {
        title: 'Pages',
        description: 'Quản lý page công khai, SEO field cơ bản và preview unpublished.',
        endpoint: ADMIN_API_ROUTES.cms.pages.collection,
        permissionView: 'cms.view',
        permissionCreate: 'cms.create',
        permissionUpdate: 'cms.update',
        permissionDelete: 'cms.delete',
        permissionPublish: 'cms.publish',
    },
    'cms-landing-pages': {
        title: 'Landing pages',
        description: 'Quản lý trang chủ và các landingpage chiến dịch theo từng khối nội dung của theme.',
        endpoint: adminApi('landing/pages'),
        permissionView: 'cms.view',
        permissionCreate: 'cms.create',
        permissionUpdate: 'cms.update',
        permissionDelete: 'cms.delete',
        permissionPublish: 'cms.publish',
    },
    'cms-posts': {
        title: 'Tin tức',
        description: 'Quản lý bài viết, category, featured media và public blog.',
        endpoint: adminApi('cms/posts'),
        permissionView: 'cms.post.view',
        permissionCreate: 'cms.post.create',
        permissionUpdate: 'cms.post.update',
        permissionDelete: 'cms.post.delete',
        permissionPublish: 'cms.publish',
    },
    'cms-services': {
        title: 'Services',
        description: 'Quan ly dich vu, gallery anh, alt text va du lieu dong cho cac block dich vu.',
        endpoint: adminApi('cms/services'),
        permissionView: 'cms.view',
        permissionCreate: 'cms.create',
        permissionUpdate: 'cms.update',
        permissionDelete: 'cms.delete',
        permissionPublish: 'cms.publish',
    },
    'cms-projects': {
        title: 'Projects',
        description: 'Quan ly du an, gallery anh, alt text va du lieu dong cho cac block du an.',
        endpoint: adminApi('cms/projects'),
        permissionView: 'cms.view',
        permissionCreate: 'cms.create',
        permissionUpdate: 'cms.update',
        permissionDelete: 'cms.delete',
        permissionPublish: 'cms.publish',
    },
    'cms-testimonials': {
        title: 'Cảm nhận khách hàng',
        description: 'Quản lý cảm nhận khách hàng dùng chung cho các theme.',
        endpoint: adminApi('cms/testimonials'),
        permissionView: 'cms.view',
        permissionCreate: 'cms.create',
        permissionUpdate: 'cms.update',
        permissionDelete: 'cms.delete',
        permissionPublish: 'cms.publish',
    },
    'cms-team-members': {
        title: 'Team Members',
        description: 'Quan ly doi ngu nhan su, gallery anh va anh dai dien dung chung cho cac theme.',
        endpoint: adminApi('cms/team-members'),
        permissionView: 'cms.view',
        permissionCreate: 'cms.create',
        permissionUpdate: 'cms.update',
        permissionDelete: 'cms.delete',
        permissionPublish: 'cms.publish',
    },
    'cms-partners': {
        title: 'Partners',
        description: 'Quan ly logo va thong tin doi tac dung chung cho cac theme.',
        endpoint: adminApi('cms/partners'),
        permissionView: 'cms.view',
        permissionCreate: 'cms.create',
        permissionUpdate: 'cms.update',
        permissionDelete: 'cms.delete',
        permissionPublish: 'cms.publish',
    },
    'cms-products': {
        title: 'Products',
        description: 'Quản lý sản phẩm ecommerce ngay trong workspace CMS.',
        endpoint: adminApi('cms/products'),
        permissionView: 'cms.product.view',
        permissionCreate: 'cms.product.create',
        permissionUpdate: 'cms.product.update',
        permissionDelete: 'cms.product.delete',
        permissionPublish: null,
    },
    'cms-orders': {
        title: 'Đơn đặt hàng',
        description: 'Theo dõi đơn hàng từ storefront, khách hàng và line-item ngay trong CMS.',
        endpoint: adminApi('cms/orders'),
        permissionView: 'cms.order.view',
        permissionCreate: null,
        permissionUpdate: 'cms.order.view',
        permissionDelete: 'cms.order.view',
        permissionPublish: null,
    },
    'cms-categories': {
        title: 'Categories',
        description: 'Quản lý taxonomy cho post và nội dung phân loại.',
        endpoint: adminApi('cms/categories'),
        permissionView: 'cms.view',
        permissionCreate: 'cms.category.manage',
        permissionUpdate: 'cms.category.manage',
        permissionDelete: 'cms.category.manage',
        permissionPublish: null,
    },
    'cms-menus': {
        title: 'Chi tiết menu',
        description: 'Xem và chỉnh cấu trúc menu hiển thị trên website theo từng vị trí.',
        endpoint: adminApi('cms/menus'),
        permissionView: 'cms.view',
        permissionCreate: 'cms.menu.manage',
        permissionUpdate: 'cms.menu.manage',
        permissionDelete: 'cms.menu.manage',
        permissionPublish: 'cms.menu.manage',
    },
    'cms-featured-categories': {
        title: 'Danh mục nổi bật',
        description: 'Quản lý các cụm danh mục nổi bật dùng chung cho storefront theo từng vị trí.',
        endpoint: adminApi('cms/featured-categories'),
        permissionView: 'cms.view',
        permissionCreate: 'cms.menu.manage',
        permissionUpdate: 'cms.menu.manage',
        permissionDelete: 'cms.menu.manage',
        permissionPublish: null,
    },
    'cms-side-promos': {
        title: 'Side promos',
        description: 'Quản lý block promo dọc kiểu CMS cũ cạnh hero. Đây không phải nơi quản lý slide banner hero của theme SER0101; slide hero đang nằm ở Catalog > Slide banner.',
        endpoint: adminApi('cms/side-promos'),
        permissionView: 'cms.view',
        permissionCreate: 'cms.menu.manage',
        permissionUpdate: 'cms.menu.manage',
        permissionDelete: 'cms.menu.manage',
        permissionPublish: null,
    },
    'cms-media': {
        title: 'Ảnh',
        description: 'Upload và chọn media cơ bản cho page/post.',
        endpoint: adminApi('cms/media'),
        permissionView: 'cms.view',
        permissionCreate: 'cms.media.manage',
        permissionUpdate: 'cms.media.manage',
        permissionDelete: 'cms.media.manage',
        permissionPublish: null,
    },
};

const localizedContentResourceMap = {
    'cms-posts': 'cms_post',
    'cms-services': 'cms_service',
    'cms-projects': 'cms_project',
    'cms-testimonials': 'cms_testimonial',
    'cms-team-members': 'cms_team_member',
    'cms-partners': 'cms_partner',
    'cms-products': 'catalog_product',
    'cms-categories': 'cms_category',
    'cms-menus': 'cms_menu',
    'cms-media': 'cms_media',
};

const localizedContentFieldsMap = {
    'cms-posts': ['title', 'slug', 'excerpt', 'body', 'meta_title', 'meta_description', 'meta_keywords'],
    'cms-services': ['title', 'slug', 'summary', 'content', 'button_label', 'meta_title', 'meta_description', 'meta_keywords'],
    'cms-projects': ['title', 'slug', 'summary', 'content', 'button_label', 'meta_title', 'meta_description'],
    'cms-team-members': ['name', 'slug', 'role', 'department', 'summary', 'bio'],
    'cms-partners': ['title', 'slug', 'description', 'image_alt'],
    'cms-testimonials': ['name', 'role', 'company', 'quote', 'image_alt'],
    'cms-products': ['name', 'slug', 'short_description', 'detail_content', 'meta_title', 'meta_description', 'meta_keywords', 'highlights', 'usage_terms', 'usage_location'],
    'cms-menus': ['items'],
};

const localizedListSectionKeys = new Set([
    'cms-pages',
    'cms-posts',
    'cms-products',
    'cms-services',
    'cms-projects',
    'cms-team-members',
    'cms-partners',
    'cms-testimonials',
    'cms-menus',
]);

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
    status: 'published',
    excerpt: '',
    body: '',
    meta_title: '',
    meta_description: '',
    featured_media_id: null,
    category_id: null,
    publish_at: null,
    is_highlight: false,
    website_key: '',
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
};

const emptyServiceCategory = {
    id: null,
    parent_id: null,
    name: '',
    slug: '',
    description: '',
    meta_title: '',
    meta_description: '',
    image_url: '',
    sort_order: 0,
    is_active: true,
};

const emptyProjectCategory = {
    id: null,
    parent_id: null,
    name: '',
    slug: '',
    description: '',
    meta_title: '',
    meta_description: '',
    image_url: '',
    sort_order: 0,
    is_active: true,
};

const emptyProject = {
    id: null,
    cms_project_category_id: null,
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
};

const emptyPartner = {
    id: null,
    title: '',
    slug: '',
    description: '',
    meta_title: '',
    meta_description: '',
    image_url: '',
    image_alt: '',
    link_url: '',
    status: 'draft',
    publish_at: null,
    is_featured: true,
    sort_order: 0,
    website_key: '',
};

const emptyProduct = {
    id: null,
    catalog_category_id: null,
    name: '',
    slug: '',
    sku: '',
    price: 0,
    original_price: null,
    stock: 1000,
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
};

const emptyMenu = {
    id: null,
    name: '',
    location: 'primary',
    items: [{ label: '', url: '', target: '_self', link_type: 'custom', link_value: null, custom_url: '', children: [] }],
    website_key: '',
};

const emptyFeaturedCategory = {
    id: null,
    name: '',
    location: 'home-featured-categories',
    items: [{ label: '', url: '', target: '_self', link_type: 'custom', link_value: null, custom_url: '' }],
    website_key: '',
};

const emptySidePromo = {
    id: null,
    name: '',
    location: 'home-hero-side-promos',
    items: [{ title: '', subtitle: '', image: '', url: '', target: '_self', link_type: 'custom', link_value: null, custom_url: '' }],
    website_key: '',
};

const BULK_KEEP_VALUE = '__KEEP__';
const BULK_CLEAR_VALUE = '__CLEAR__';

function countMenuItems(items = []) {
    return (items ?? []).reduce((total, item) => total + 1 + countMenuItems(item?.children ?? []), 0);
}

function blankMenuTranslationTree(items = []) {
    return (items ?? []).map((item) => ({
        ...item,
        _source_label: item?._source_label ?? item?.label ?? '',
        label: '',
        children: blankMenuTranslationTree(item?.children ?? []),
    }));
}

function hasMenuTranslationContent(items = []) {
    return (items ?? []).some((item) => (
        String(item?.label ?? '').trim() !== ''
        || hasMenuTranslationContent(item?.children ?? [])
    ));
}

function renderStatusTag(status) {
    const colorMap = {
        published: 'green',
        ready: 'blue',
        in_review: 'gold',
        machine_draft: 'cyan',
        outdated: 'orange',
        draft: 'default',
        missing: 'default',
    };
    const labelMap = {
        published: 'Đã xuất bản',
        ready: 'Sẵn sàng',
        in_review: 'Đang duyệt',
        machine_draft: 'Bản dịch máy',
        outdated: 'Cần cập nhật',
        draft: 'Bản nháp',
        missing: 'Chưa có',
    };
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
    const [messageApi, messageContextHolder] = message.useMessage();
    const [contentLocale, setContentLocale] = useState(frontendLocale);
    const [contentLocaleOptions, setContentLocaleOptions] = useState([]);
    const [contentSourceLocale, setContentSourceLocale] = useState('vi');
    const [bulkProductEditForm] = Form.useForm();
    const [bulkProductStockForm] = Form.useForm();
    const [bulkProductActiveForm] = Form.useForm();
    const [bulkOrderStatusForm] = Form.useForm();
    const [bulkServiceCategoryForm] = Form.useForm();
    const [bulkContentCategoryForm] = Form.useForm();
    const [mediaEditForm] = Form.useForm();
    const [modalOpen, setModalOpen] = useState(false);
    const [editingRecord, setEditingRecord] = useState(emptyPage);
    const [localizedCreateDrafts, setLocalizedCreateDrafts] = useState({});
    const [blockManagerOpen, setBlockManagerOpen] = useState(false);
    const [selectedLandingPage, setSelectedLandingPage] = useState(null);
    const [selectedPage, setSelectedPage] = useState(null);
    const [selectedPost, setSelectedPost] = useState(null);
    const [selectedProduct, setSelectedProduct] = useState(null);
    const [selectedOrder, setSelectedOrder] = useState(null);
    const [selectedProductRowKeys, setSelectedProductRowKeys] = useState([]);
    const [selectedPageRowKeys, setSelectedPageRowKeys] = useState([]);
    const [selectedOrderRowKeys, setSelectedOrderRowKeys] = useState([]);
    const [selectedPartnerRowKeys, setSelectedPartnerRowKeys] = useState([]);
    const [selectedServiceRowKeys, setSelectedServiceRowKeys] = useState([]);
    const [selectedPostRowKeys, setSelectedPostRowKeys] = useState([]);
    const [selectedProjectRowKeys, setSelectedProjectRowKeys] = useState([]);
    const [selectedTeamMemberRowKeys, setSelectedTeamMemberRowKeys] = useState([]);
    const [selectedTestimonialRowKeys, setSelectedTestimonialRowKeys] = useState([]);
    const [bulkProductEditOpen, setBulkProductEditOpen] = useState(false);
    const [bulkProductStockOpen, setBulkProductStockOpen] = useState(false);
    const [bulkProductActiveOpen, setBulkProductActiveOpen] = useState(false);
    const [bulkOrderStatusOpen, setBulkOrderStatusOpen] = useState(false);
    const [bulkServiceCategoryOpen, setBulkServiceCategoryOpen] = useState(false);
    const [bulkContentCategoryOpen, setBulkContentCategoryOpen] = useState(false);
    const [keyword, setKeyword] = useState('');
    const [orderStatusFilter, setOrderStatusFilter] = useState('all');
    const [orderDatePreset, setOrderDatePreset] = useState('custom');
    const [orderDateRange, setOrderDateRange] = useState(null);
    const [productCategoryFilter, setProductCategoryFilter] = useState('all');
    const [productFeaturedFilter, setProductFeaturedFilter] = useState('all');
    const [productActiveFilter, setProductActiveFilter] = useState('all');
    const [productPublishFilter, setProductPublishFilter] = useState('all');
    const [productSort, setProductSort] = useState('newest');
    const [serviceCategoryFilter, setServiceCategoryFilter] = useState('all');
    const [serviceStatusFilter, setServiceStatusFilter] = useState('all');
    const [serviceFeaturedFilter, setServiceFeaturedFilter] = useState('all');
    const [productPagination, setProductPagination] = useState({ current: 1, pageSize: 10 });
    const [mediaPagination, setMediaPagination] = useState({ current: 1, pageSize: 30 });
    const [mediaUpload, setMediaUpload] = useState({ title: '', alt_text: '', folder_path: null });
    const [mediaFiles, setMediaFiles] = useState([]);
    const [mediaUploadOpen, setMediaUploadOpen] = useState(false);
    const [editingMediaRecord, setEditingMediaRecord] = useState(null);
    const [activeMediaFolder, setActiveMediaFolder] = useState('all');
    const [mediaShowAll, setMediaShowAll] = useState(false);
    const [mediaDragActive, setMediaDragActive] = useState(false);
    const [categoryManagerOpen, setCategoryManagerOpen] = useState(false);
    const [categoryFormOpen, setCategoryFormOpen] = useState(false);
    const [categoryItems, setCategoryItems] = useState([]);
    const [categoryLoading, setCategoryLoading] = useState(false);
    const [categorySaving, setCategorySaving] = useState(false);
    const [editingCategoryRecord, setEditingCategoryRecord] = useState(emptyCategory);
    const [serviceCategoryManagerOpen, setServiceCategoryManagerOpen] = useState(false);
    const [serviceCategoryFormOpen, setServiceCategoryFormOpen] = useState(false);
    const [serviceCategoryItems, setServiceCategoryItems] = useState([]);
    const [serviceCategoryLoading, setServiceCategoryLoading] = useState(false);
    const [serviceCategorySaving, setServiceCategorySaving] = useState(false);
    const [editingServiceCategoryRecord, setEditingServiceCategoryRecord] = useState(emptyServiceCategory);
    const [projectCategoryManagerOpen, setProjectCategoryManagerOpen] = useState(false);
    const [projectCategoryFormOpen, setProjectCategoryFormOpen] = useState(false);
    const [projectCategoryItems, setProjectCategoryItems] = useState([]);
    const [projectCategoryLoading, setProjectCategoryLoading] = useState(false);
    const [projectCategorySaving, setProjectCategorySaving] = useState(false);
    const [editingProjectCategoryRecord, setEditingProjectCategoryRecord] = useState(emptyProjectCategory);
    const [productCategoryManagerOpen, setProductCategoryManagerOpen] = useState(false);
    const [productCategoryFormOpen, setProductCategoryFormOpen] = useState(false);
    const [productCategoryItems, setProductCategoryItems] = useState([]);
    const [productCategoryLoading, setProductCategoryLoading] = useState(false);
    const [productCategorySaving, setProductCategorySaving] = useState(false);
    const [editingProductCategoryRecord, setEditingProductCategoryRecord] = useState(emptyProductCategory);
    const productCategorySensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }));
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
    const supportsLocalizedList = localizedListSectionKeys.has(sectionKey);
    const withContentLocale = (endpoint, locale = contentLocale) => {
        if (!supportsLocalizedList || !locale) {
            return endpoint;
        }

        const separator = endpoint.includes('?') ? '&' : '?';
        return `${endpoint}${separator}locale=${encodeURIComponent(locale)}`;
    };

    const { data, loading, error, reload, mutateData } = useAdminRouteResource({
        enabled: sectionPermissions.canView,
        loader: async () => {
            if (sectionKey === 'cms-products') {
                const [productsPayload, categoriesPayload] = await Promise.all([
                    callAdminApi(withContentLocale(adminApi('cms/products'))),
                    callAdminApi(withContentLocale(adminApi('cms/product-categories'))),
                ]);

                return {
                    ...(productsPayload.data ?? { items: [], total: 0, metrics: {} }),
                    categories: categoriesPayload.data?.items ?? [],
                };
            }

            if (sectionKey === 'cms-services') {
                const [servicesPayload, categoriesPayload] = await Promise.all([
                    callAdminApi(withContentLocale(adminApi('cms/services'))),
                    callAdminApi(withContentLocale(adminApi('cms/service-categories'))),
                ]);

                return {
                    ...(servicesPayload.data ?? { items: [], total: 0, metrics: {}, media: [] }),
                    categories: categoriesPayload.data?.items ?? [],
                };
            }

            if (sectionKey === 'cms-projects') {
                const [projectsPayload, categoriesPayload] = await Promise.all([
                    callAdminApi(withContentLocale(adminApi('cms/projects'))),
                    callAdminApi(withContentLocale(adminApi('cms/project-categories'))),
                ]);

                return {
                    ...(projectsPayload.data ?? { items: [], total: 0, metrics: {}, media: [] }),
                    categories: categoriesPayload.data?.items ?? [],
                };
            }

            if (sectionKey === 'cms-side-promos') {
                const [sidePromosPayload, mediaPayload] = await Promise.all([
                    callAdminApi(adminApi('cms/side-promos')),
                    callAdminApi(adminApi('cms/media')),
                ]);

                return {
                    ...(sidePromosPayload.data ?? { items: [], total: 0 }),
                    media: mediaPayload.data?.items ?? [],
                };
            }

            if (sectionKey === 'cms-media') {
                const payload = await callAdminApi(adminApi(`cms/media${mediaShowAll ? '?scope=all' : ''}`));
                return payload.data ?? null;
            }

            const payload = await callAdminApi(withContentLocale(sectionConfig.endpoint));
            return payload.data ?? null;
        },
        deps: [sectionConfig.endpoint, sectionPermissions.canView, mediaShowAll, contentLocale],
    });

    useEffect(() => {
        if (!supportsLocalizedList || !sectionPermissions.canView) {
            return;
        }

        let active = true;

        callAdminApi(adminApi('themes/locales'))
            .then((payload) => {
                if (!active) return;

                const localePayload = payload.data ?? {};
                const editableLocales = (localePayload.locales ?? [])
                    .filter((locale) => locale.is_enabled_for_editing !== false);
                const sourceLocale = localePayload.source_locale || 'vi';

                setContentLocaleOptions(editableLocales);
                setContentSourceLocale(sourceLocale);
                setContentLocale((current) => (
                    editableLocales.some((locale) => locale.code === current)
                        ? current
                        : sourceLocale
                ));
            })
            .catch(() => {
                if (!active) return;

                setContentLocaleOptions([
                    { code: 'vi', name: 'Tiếng Việt', native_name: 'Tiếng Việt', is_source: true },
                ]);
                setContentSourceLocale('vi');
                setContentLocale('vi');
            });

        return () => {
            active = false;
        };
    }, [callAdminApi, sectionKey, sectionPermissions.canView, supportsLocalizedList]);

    const mediaItems = data?.items ?? [];
    const mediaFolders = data?.folders ?? [];
    const mediaFolderOptions = useMemo(() => (
        mediaFolders.map((folder) => ({ label: folder.name, value: folder.path }))
    ), [mediaFolders]);
    const mediaFolderCounts = useMemo(() => {
        const counts = new Map([
            ['all', mediaItems.length],
            ['uncategorized', mediaItems.filter((item) => !item.folder_path).length],
        ]);

        mediaFolders.forEach((folder) => {
            counts.set(folder.path, mediaItems.filter((item) => item.folder_path === folder.path).length);
        });

        return counts;
    }, [mediaFolders, mediaItems]);

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

        if (['cms-services', 'cms-projects', 'cms-testimonials', 'cms-team-members', 'cms-partners'].includes(sectionKey)) {
            return [];
        }

        if (sectionKey === 'cms-services') {
            return [
                {
                    title: 'Dịch vụ',
                    dataIndex: 'title',
                    key: 'title',
                    render: (value, record) => (
                        <Space size={12} align="start">
                            <CmsMediaThumbnail src={resolveRecordImageUrl(record)} alt={record.featured_image_alt || value} />
                            <Space direction="vertical" size={2} align="start">
                                <Button type="link" style={{ paddingInline: 0, height: 'auto' }} onClick={() => openEditModal(record)}>
                                    <Text strong style={{ color: '#1677ff' }}>{value}</Text>
                                </Button>
                                <Text type="secondary">{record.summary || 'Chưa có mô tả ngắn'}</Text>
                                <Space size={6} wrap>
                                    {record.category_name ? <Tag color="blue">{record.category_name}</Tag> : <Tag>Chưa phân loại</Tag>}
                                    <Tag>{`${record.images?.length ?? 0} ảnh`}</Tag>
                                </Space>
                            </Space>
                        </Space>
                    ),
                },
                { title: 'Danh mục', dataIndex: 'category_name', key: 'category_name', render: (value) => value || 'Chưa phân loại' },
                { title: 'Slug', dataIndex: 'slug', key: 'slug' },
                {
                    title: 'Trạng thái',
                    key: 'status',
                    render: (_, record) => (
                        <Space size={[4, 4]} wrap>
                            {renderStatusTag(record.status)}
                            {record.is_featured ? <Tag color="gold">Dịch vụ nổi bật</Tag> : null}
                            {record.is_highlight ? <Tag color="blue">Ưu tiên block động</Tag> : null}
                        </Space>
                    ),
                },
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
                            <CmsMediaThumbnail src={resolveRecordImageUrl(record)} alt={record.featured_image_alt || value} />
                            <Space direction="vertical" size={2} align="start">
                                <Button type="link" style={{ paddingInline: 0, height: 'auto' }} onClick={() => openEditModal(record)}>
                                    <Text strong style={{ color: '#1677ff' }}>{value}</Text>
                                </Button>
                                <Text type="secondary">{record.summary || 'Chua co mo ta ngan'}</Text>
                                <Space size={6} wrap>
                                    {sectionKey === 'cms-projects' ? (record.category_name ? <Tag color="blue">{record.category_name}</Tag> : <Tag>Chua phan loai</Tag>) : null}
                                    {record.is_highlight ? <Tag color="gold">Noi bat</Tag> : null}
                                    <Tag>{`${record.images?.length ?? 0} anh`}</Tag>
                                </Space>
                            </Space>
                        </Space>
                    ),
                },
                ...(sectionKey === 'cms-projects' ? [{ title: 'Danh muc', dataIndex: 'category_name', key: 'category_name', render: (value) => value || 'Chua phan loai' }] : []),
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
                            <CmsMediaThumbnail src={resolveRecordImageUrl(record)} alt={record.featured_image_alt || value} />
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

    const projectCategoryOptions = useMemo(() => (data?.categories ?? []).map((category) => ({
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

    const projectCategoryParentOptions = useMemo(() => projectCategoryItems
        .filter((category) => category.id !== editingProjectCategoryRecord?.id)
        .map((category) => ({
            label: category.parent_name ? `${category.parent_name} / ${category.name}` : category.name,
            value: category.id,
        })), [editingProjectCategoryRecord?.id, projectCategoryItems]);

    const selectedProducts = useMemo(() => {
        if (sectionKey !== 'cms-products') {
            return [];
        }

        return (data?.items ?? []).filter((product) => selectedProductRowKeys.includes(product.id));
    }, [data?.items, sectionKey, selectedProductRowKeys]);

    const selectedOrders = useMemo(() => {
        if (sectionKey !== 'cms-orders') {
            return [];
        }

        return (data?.orders ?? []).filter((order) => selectedOrderRowKeys.includes(order.id));
    }, [data?.orders, sectionKey, selectedOrderRowKeys]);

    const selectedServices = useMemo(() => {
        if (sectionKey !== 'cms-services') {
            return [];
        }

        return (data?.items ?? []).filter((service) => selectedServiceRowKeys.includes(service.id));
    }, [data?.items, sectionKey, selectedServiceRowKeys]);

    const selectedPosts = useMemo(() => {
        if (sectionKey !== 'cms-posts') {
            return [];
        }

        return (data?.items ?? []).filter((post) => selectedPostRowKeys.includes(post.id));
    }, [data?.items, sectionKey, selectedPostRowKeys]);

    const selectedProjects = useMemo(() => {
        if (sectionKey !== 'cms-projects') {
            return [];
        }

        return (data?.items ?? []).filter((project) => selectedProjectRowKeys.includes(project.id));
    }, [data?.items, sectionKey, selectedProjectRowKeys]);

    useEffect(() => {
        if (sectionKey !== 'cms-products') {
            return;
        }

        setProductPagination((current) => ({ ...current, current: 1 }));
    }, [keyword, productActiveFilter, productCategoryFilter, productFeaturedFilter, productPublishFilter, productSort, sectionKey]);

    useEffect(() => {
        if (sectionKey !== 'cms-media') {
            return;
        }

        setMediaPagination((current) => ({ ...current, current: 1 }));
    }, [activeMediaFolder, keyword, mediaShowAll, sectionKey]);

    const filteredItems = useMemo(() => {
        const normalizedKeyword = keyword.trim().toLowerCase();

        if (sectionKey === 'cms-orders') {
            return (data?.orders ?? []).filter((order) => {
                const matchesKeyword = normalizedKeyword === '' || [
                    order.order_code,
                    order.customer_name,
                    order.customer_phone,
                    order.customer_email,
                    order.delivery_address,
                ].some((value) => String(value ?? '').toLowerCase().includes(normalizedKeyword));
                const matchesStatus = orderStatusFilter === 'all' || order.status === orderStatusFilter;
                const placedAt = order.placed_at ? dayjs(order.placed_at) : null;
                const matchesDateRange = !orderDateRange?.[0] || !orderDateRange?.[1]
                    || (placedAt && !placedAt.isBefore(orderDateRange[0], 'day') && !placedAt.isAfter(orderDateRange[1], 'day'));

                return matchesKeyword && matchesStatus && matchesDateRange;
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

        if (sectionKey === 'cms-services') {
            return (data?.items ?? []).filter((service) => {
                const matchesKeyword = normalizedKeyword === '' || [
                    service.title,
                    service.slug,
                    service.category_name,
                    service.summary,
                    service.meta_keywords,
                ].some((value) => String(value ?? '').toLowerCase().includes(normalizedKeyword));
                const matchesCategory = serviceCategoryFilter === 'all'
                    || String(service.cms_service_category_id ?? '') === String(serviceCategoryFilter);
                const matchesStatus = serviceStatusFilter === 'all' || service.status === serviceStatusFilter;
                const matchesFeatured = serviceFeaturedFilter === 'all'
                    || (serviceFeaturedFilter === 'featured' && service.is_featured)
                    || (serviceFeaturedFilter === 'normal' && !service.is_featured);

                return matchesKeyword && matchesCategory && matchesStatus && matchesFeatured;
            });
        }

        if (sectionKey === 'cms-media') {
            return (data?.items ?? []).filter((mediaItem) => {
                const folderMatches = activeMediaFolder === 'all'
                    || (activeMediaFolder === 'uncategorized' ? !mediaItem.folder_path : mediaItem.folder_path === activeMediaFolder);

                if (!folderMatches) {
                    return false;
                }

                if (normalizedKeyword === '') {
                    return true;
                }

                return [
                    mediaItem.title,
                    mediaItem.alt_text,
                    mediaItem.mime_type,
                    mediaItem.file_url,
                ].some((value) => String(value ?? '').toLowerCase().includes(normalizedKeyword));
            });
        }

        return data?.items ?? [];
    }, [activeMediaFolder, data?.items, data?.orders, keyword, orderDateRange, orderStatusFilter, productActiveFilter, productCategoryFilter, productFeaturedFilter, productPublishFilter, productSort, sectionKey, serviceCategoryFilter, serviceFeaturedFilter, serviceStatusFilter]);

    const paginatedMediaItems = useMemo(() => {
        if (sectionKey !== 'cms-media') {
            return [];
        }

        const start = (mediaPagination.current - 1) * mediaPagination.pageSize;

        return filteredItems.slice(start, start + mediaPagination.pageSize);
    }, [filteredItems, mediaPagination.current, mediaPagination.pageSize, sectionKey]);

    useEffect(() => {
        if (sectionKey !== 'cms-media') {
            return;
        }

        const lastPage = Math.max(1, Math.ceil(filteredItems.length / mediaPagination.pageSize));

        if (mediaPagination.current > lastPage) {
            setMediaPagination((current) => ({ ...current, current: lastPage }));
        }
    }, [filteredItems.length, mediaPagination.current, mediaPagination.pageSize, sectionKey]);

    const openCreateModal = () => {
        if (supportsLocalizedList && contentLocale !== contentSourceLocale) {
            setContentLocale(contentSourceLocale);
        }

        setLocalizedCreateDrafts({});

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

    const loadLocalizedRecord = async (record, targetLocale = contentLocale) => {
        const resourceType = localizedContentResourceMap[sectionKey];

        if (!resourceType || !record?.id) {
            return record;
        }

        try {
            const response = await callAdminApi(
                adminApi(`localization/content/${resourceType}/${record.id}`),
            );
            const localization = response.data ?? {};
            const translations = localization.translations ?? {};
            const translation = translations[targetLocale] ?? null;
            const sourceLocale = localization.source_locale ?? contentSourceLocale;
            const translationStatus = translation?.translation_status
                ?? (targetLocale === sourceLocale
                    ? (record?.status ?? (record?.is_active ? 'published' : 'draft'))
                    : 'missing');
            const emptyTranslationPayload = targetLocale !== sourceLocale && !translation
                ? Object.fromEntries((localization.fields ?? []).map((field) => {
                    const sourceValue = record?.[field];

                    if (Array.isArray(sourceValue)) {
                        return [
                            field,
                            sectionKey === 'cms-menus' && field === 'items'
                                ? (localization.translation_template?.items
                                    ?? blankMenuTranslationTree(sourceValue))
                                : [],
                        ];
                    }

                    if (sourceValue && typeof sourceValue === 'object') {
                        return [field, {}];
                    }

                    return [field, ''];
                }))
                : {};

            return {
                ...record,
                ...emptyTranslationPayload,
                ...(translation?.payload ?? {}),
                ...(targetLocale !== sourceLocale && Object.hasOwn(record ?? {}, 'status')
                    ? { status: translationStatus === 'published' ? 'published' : 'draft' }
                    : {}),
                ...(targetLocale !== sourceLocale && Object.hasOwn(record ?? {}, 'is_active')
                    ? { is_active: translationStatus === 'published' }
                    : {}),
                _content_locale: targetLocale,
                _content_source_locale: sourceLocale,
                _translation_status: translationStatus,
                _translation_statuses: Object.fromEntries(
                    Object.entries(translations).map(([locale, item]) => [
                        locale,
                        item?.translation_status ?? 'missing',
                    ]),
                ),
                _allowed_transitions: translation?.allowed_transitions ?? [],
                _translation_progress: translation?.translation_progress
                    ?? record?._translation_progress
                    ?? null,
            };
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : 'Không thể tải bản dịch.');
            return null;
        }
    };

    const prepareEditingRecord = (localizedRecord) => (
        sectionKey === 'cms-posts'
            ? {
                ...localizedRecord,
                publish_at: normalizeDatetimeLocal(localizedRecord.publish_at),
            }
            : false && sectionKey === 'cms-products'
                ? {
                    ...localizedRecord,
                    deal_end_at: normalizeDatetimeLocal(localizedRecord.deal_end_at),
                }
                : localizedRecord
    );

    const openEditModal = async (record) => {
        setLocalizedCreateDrafts({});
        const localizedRecord = await loadLocalizedRecord(
            record,
            record?._content_locale ?? contentLocale,
        );

        if (!localizedRecord) {
            return;
        }

        setEditingRecord(prepareEditingRecord(localizedRecord));
        setModalOpen(true);
    };

    const handleFormLocaleChange = async (nextLocale, currentFormValues = null) => {
        if (nextLocale === contentLocale) {
            return true;
        }

        if (!editingRecord?.id) {
            const localizedFields = localizedContentFieldsMap[sectionKey] ?? [];
            const nextDrafts = {
                ...localizedCreateDrafts,
                [contentLocale]: currentFormValues ?? editingRecord,
            };
            const sourceDraft = nextDrafts[contentSourceLocale] ?? editingRecord;
            const targetDraft = nextDrafts[nextLocale] ?? null;
            const localizedValues = Object.fromEntries(localizedFields.map((field) => {
                const targetValue = targetDraft?.[field];

                if (targetValue !== undefined && targetValue !== null) {
                    return [field, targetValue];
                }

                const sourceValue = sourceDraft?.[field];

                if (Array.isArray(sourceValue)) {
                    return [
                        field,
                        sectionKey === 'cms-menus' && field === 'items'
                            ? blankMenuTranslationTree(sourceValue)
                            : [],
                    ];
                }

                if (sourceValue && typeof sourceValue === 'object') {
                    return [field, {}];
                }

                return [field, ''];
            }));
            const translationStatuses = {
                ...(editingRecord?._translation_statuses ?? {}),
                [contentSourceLocale]: 'draft',
            };

            Object.entries(nextDrafts).forEach(([locale, draft]) => {
                if (locale === contentSourceLocale) {
                    return;
                }

                translationStatuses[locale] = localizedFields.some((field) => {
                    const value = draft?.[field];

                    if (sectionKey === 'cms-menus' && field === 'items') {
                        return hasMenuTranslationContent(value);
                    }

                    return Array.isArray(value)
                        ? value.length > 0
                        : String(value ?? '').trim() !== '';
                })
                    ? (draft?.status === 'published' || draft?.is_active === true
                        ? 'published'
                        : 'draft')
                    : 'missing';
            });

            setLocalizedCreateDrafts(nextDrafts);
            setContentLocale(nextLocale);
            setEditingRecord({
                ...sourceDraft,
                ...(nextLocale === contentSourceLocale ? sourceDraft : localizedValues),
                ...(nextLocale !== contentSourceLocale && Object.hasOwn(sourceDraft ?? {}, 'status')
                    ? { status: targetDraft?.status ?? 'draft' }
                    : {}),
                ...(nextLocale !== contentSourceLocale && Object.hasOwn(sourceDraft ?? {}, 'is_active')
                    ? { is_active: targetDraft?.is_active ?? false }
                    : {}),
                id: null,
                _content_locale: nextLocale,
                _content_source_locale: contentSourceLocale,
                _translation_status: translationStatuses[nextLocale]
                    ?? (nextLocale === contentSourceLocale ? 'draft' : 'missing'),
                _translation_statuses: translationStatuses,
            });

            return true;
        }

        const sourceRecord = (data?.items ?? []).find((item) => item.id === editingRecord.id)
            ?? editingRecord;
        const localizedRecord = await loadLocalizedRecord(sourceRecord, nextLocale);

        if (!localizedRecord) {
            return false;
        }

        setContentLocale(nextLocale);
        setEditingRecord(prepareEditingRecord(localizedRecord));

        return true;
    };

    const openPostDetailsDrawer = (record) => {
        setSelectedPost(record);
    };

    const openPageDetailsDrawer = (record) => {
        setSelectedPage(record);
    };

    const handleEditPostFromDrawer = () => {
        if (!selectedPost) {
            return;
        }

        setSelectedPost(null);
        openEditModal(selectedPost);
    };

    const refreshCurrentSectionDataSilently = async (locale = contentLocale) => {
        if (sectionKey === 'cms-products') {
            const [itemsPayload, categoriesPayload] = await Promise.all([
                callAdminApi(withContentLocale(adminApi('cms/products'), locale)),
                callAdminApi(withContentLocale(adminApi('cms/product-categories'), locale)),
            ]);

            mutateData({
                ...(itemsPayload.data ?? { items: [], total: 0, metrics: {} }),
                categories: categoriesPayload.data?.items ?? [],
            });
            return;
        }

        if (sectionKey === 'cms-projects') {
            const [itemsPayload, categoriesPayload] = await Promise.all([
                callAdminApi(withContentLocale(adminApi('cms/projects'), locale)),
                callAdminApi(withContentLocale(adminApi('cms/project-categories'), locale)),
            ]);

            mutateData({
                ...(itemsPayload.data ?? { items: [], total: 0, metrics: {}, media: [] }),
                categories: categoriesPayload.data?.items ?? [],
            });
            return;
        }

        if (sectionKey === 'cms-services') {
            const [itemsPayload, categoriesPayload] = await Promise.all([
                callAdminApi(withContentLocale(adminApi('cms/services'), locale)),
                callAdminApi(withContentLocale(adminApi('cms/service-categories'), locale)),
            ]);

            mutateData({
                ...(itemsPayload.data ?? { items: [], total: 0, metrics: {}, media: [] }),
                categories: categoriesPayload.data?.items ?? [],
            });
            return;
        }

        const payload = await callAdminApi(withContentLocale(sectionConfig.endpoint, locale));
        mutateData(payload.data ?? null);
    };

    const handleSaveRecord = async (payload, submitOptions = {}) => {
        const resourceType = localizedContentResourceMap[sectionKey];
        const pageCreateDrafts = submitOptions.pageCreateDrafts ?? {};
        const isLocalizedPageCreate = sectionKey === 'cms-pages'
            && !editingRecord?.id
            && Object.keys(pageCreateDrafts).length > 0;

        if (isLocalizedPageCreate) {
            const sourceLocale = submitOptions.pageSourceLocale ?? contentSourceLocale;

            try {
                const createdResponse = await callAdminApi(sectionConfig.endpoint, {
                    method: 'POST',
                    body: JSON.stringify({
                        ...payload,
                        locale: sourceLocale,
                    }),
                });
                const createdPage = createdResponse.data ?? null;
                const createdPageId = createdPage?.id ?? null;
                let savedTranslationCount = 0;
                const failedTranslationLocales = [];

                if (createdPageId) {
                    for (const [locale, draft] of Object.entries(pageCreateDrafts)) {
                        if (
                            locale === sourceLocale
                            || !String(draft?.title ?? '').trim()
                            || !String(draft?.slug ?? '').trim()
                        ) {
                            continue;
                        }

                        try {
                            await callAdminApi(`${sectionConfig.endpoint}/${createdPageId}`, {
                                method: 'PUT',
                                body: JSON.stringify({
                                    ...draft,
                                    locale,
                                    status: draft.status === 'published' ? 'published' : 'draft',
                                    translation_status: draft.status === 'published' ? 'published' : 'draft',
                                }),
                            });
                            savedTranslationCount += 1;
                        } catch {
                            failedTranslationLocales.push(locale);
                        }
                    }
                }

                await refreshCurrentSectionDataSilently(sourceLocale);
                setModalOpen(false);

                if (failedTranslationLocales.length) {
                    messageApi.warning(
                        `Đã tạo Page gốc nhưng chưa lưu được: ${failedTranslationLocales.join(', ')}. Có thể mở lại Page để nhập tiếp.`,
                    );
                } else {
                    messageApi.success(savedTranslationCount
                        ? `Đã tạo Page và lưu ${savedTranslationCount} bản dịch.`
                        : 'Đã tạo Page.');
                }

                return true;
            } catch (error) {
                messageApi.error(error instanceof Error ? error.message : 'Không thể tạo Page đa ngôn ngữ.');
                return false;
            }
        }

        const isLocalizedUpdate = Boolean(
            resourceType
            && editingRecord?.id
            && contentLocale !== contentSourceLocale,
        );

        if (isLocalizedUpdate) {
            const shouldPublish = payload.status === 'published'
                || (payload.status === undefined && payload.is_active !== false);

            try {
                await callAdminApi(
                    adminApi(`localization/content/${resourceType}/${editingRecord.id}/${contentLocale}`),
                    {
                        method: 'PUT',
                        body: JSON.stringify({
                            payload,
                            publish: shouldPublish,
                        }),
                    },
                );
                await refreshCurrentSectionDataSilently();
                messageApi.success(
                    shouldPublish
                        ? `Đã lưu và xuất bản nội dung ${contentLocale.toUpperCase()}.`
                        : `Đã lưu bản nháp nội dung ${contentLocale.toUpperCase()}.`,
                );
                setModalOpen(false);
                return true;
            } catch (error) {
                messageApi.error(error instanceof Error ? error.message : 'Không thể lưu bản dịch.');
                return false;
            }
        }

        const localizedFields = localizedContentFieldsMap[sectionKey] ?? [];
        const isLocalizedCreate = Boolean(resourceType && !editingRecord?.id && localizedFields.length);

        if (isLocalizedCreate) {
            if (contentLocale !== contentSourceLocale) {
                messageApi.warning('Hãy quay lại ngôn ngữ gốc để tạo nội dung và lưu các bản dịch đã nhập.');
                return false;
            }

            try {
                const createdResponse = await callAdminApi(sectionConfig.endpoint, {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
                const createdRecord = createdResponse.data?.item ?? createdResponse.data ?? null;
                const createdRecordId = createdRecord?.id ?? null;
                let savedTranslationCount = 0;
                const failedTranslationLocales = [];

                if (createdRecordId) {
                    for (const [locale, draft] of Object.entries(localizedCreateDrafts)) {
                        if (locale === contentSourceLocale) {
                            continue;
                        }

                        const localizedPayload = Object.fromEntries(
                            localizedFields.map((field) => [field, draft?.[field] ?? null]),
                        );
                        const hasLocalizedContent = Object.values(localizedPayload).some((value) => (
                            sectionKey === 'cms-menus' && Array.isArray(value)
                                ? hasMenuTranslationContent(value)
                                : (Array.isArray(value)
                                    ? value.length > 0
                                    : String(value ?? '').trim() !== '')
                        ));

                        if (!hasLocalizedContent) {
                            continue;
                        }

                        try {
                            await callAdminApi(
                                adminApi(`localization/content/${resourceType}/${createdRecordId}/${locale}`),
                                {
                                    method: 'PUT',
                                    body: JSON.stringify({
                                        payload: localizedPayload,
                                        publish: draft?.status === 'published'
                                            || draft?.is_active === true,
                                    }),
                                },
                            );
                            savedTranslationCount += 1;
                        } catch {
                            failedTranslationLocales.push(locale);
                        }
                    }
                }

                await refreshCurrentSectionDataSilently();
                setLocalizedCreateDrafts({});
                setContentLocale(contentSourceLocale);
                setModalOpen(false);

                if (failedTranslationLocales.length) {
                    messageApi.warning(
                        `Đã tạo bản gốc nhưng chưa lưu được bản nháp: ${failedTranslationLocales.join(', ')}. Có thể mở lại nội dung để nhập tiếp.`,
                    );
                } else {
                    messageApi.success(savedTranslationCount
                        ? `Đã tạo ${sectionConfig.title} và lưu ${savedTranslationCount} bản dịch nháp.`
                        : `Đã tạo ${sectionConfig.title}.`);
                }

                return true;
            } catch (error) {
                messageApi.error(error instanceof Error ? error.message : 'Không thể tạo dữ liệu.');
                return false;
            }
        }

        if (['cms-pages', 'cms-posts', 'cms-products', 'cms-projects'].includes(sectionKey)) {
            const isUpdate = Boolean(editingRecord?.id);

            try {
                await callAdminApi(
                    isUpdate ? `${sectionConfig.endpoint}/${editingRecord.id}` : sectionConfig.endpoint,
                    {
                        method: isUpdate ? 'PUT' : 'POST',
                        body: JSON.stringify(payload),
                    },
                );
                await refreshCurrentSectionDataSilently();
                messageApi.success(isUpdate ? `Đã cập nhật ${sectionConfig.title}.` : `Đã tạo ${sectionConfig.title}.`);
                setModalOpen(false);
                return true;
            } catch (error) {
                messageApi.error(error instanceof Error ? error.message : 'Không thể lưu dữ liệu.');
                return false;
            }
        }

        const didSave = editingRecord?.id
            ? await runAdminAction(() => callAdminApi(`${sectionConfig.endpoint}/${editingRecord.id}`, { method: 'PUT', body: JSON.stringify(payload) }), `Đã cập nhật ${sectionConfig.title}.`, reload)
            : await runAdminAction(() => callAdminApi(sectionConfig.endpoint, { method: 'POST', body: JSON.stringify(payload) }), `Đã tạo ${sectionConfig.title}.`, reload);

        if (didSave) {
            setModalOpen(false);
        }

        return didSave;
    };

    const handlePageTranslationTransition = async (locale, translationStatus) => {
        if (!editingRecord?.id) return false;

        try {
            const payload = await callAdminApi(
                ADMIN_API_ROUTES.cms.pages.transition(editingRecord.id, locale),
                {
                    method: 'POST',
                    body: JSON.stringify({ translation_status: translationStatus }),
                },
            );
            const updatedPage = payload.data?.page;

            if (updatedPage) {
                setEditingRecord(updatedPage);
                mutateData((currentData) => currentData ? ({
                    ...currentData,
                    items: (currentData.items ?? []).map((item) => (
                        item.id === updatedPage.id ? updatedPage : item
                    )),
                }) : currentData);
            } else {
                await refreshCurrentSectionDataSilently();
            }

            messageApi.success('Đã chuyển trạng thái bản dịch Page.');
            return true;
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : 'Không thể chuyển trạng thái bản dịch.');
            return false;
        }
    };

    const handleDeleteRecord = async (recordId) => {
        return runAdminAction(() => callAdminApi(`${sectionConfig.endpoint}/${recordId}`, { method: 'DELETE' }), `Đã xóa ${sectionConfig.title}.`, reload);
    };

    const loadCategoryItems = async ({ showLoading = true, locale = contentLocale } = {}) => {
        if (showLoading) {
            setCategoryLoading(true);
        }

        try {
            const payload = await callAdminApi(withContentLocale(adminApi('cms/categories'), locale));
            const items = payload.data?.items ?? [];
            setCategoryItems(items);

            if (sectionKey === 'cms-posts') {
                mutateData((currentData) => currentData ? ({
                    ...currentData,
                    categories: items,
                }) : currentData);
            }

            return items;
        } finally {
            if (showLoading) {
                setCategoryLoading(false);
            }
        }
    };

    const openCategoryManager = async () => {
        setContentLocale(contentSourceLocale);
        setCategoryManagerOpen(true);
        await loadCategoryItems({ locale: contentSourceLocale });
    };

    const openCreateCategory = () => {
        if (contentLocale !== contentSourceLocale) {
            messageApi.warning('Hãy tạo danh mục ở ngôn ngữ gốc trước.');
            return;
        }

        setEditingCategoryRecord(emptyCategory);
        setCategoryFormOpen(true);
    };

    const localizedCategoryRecord = async (resourceType, record, targetLocale = contentLocale) => {
        if (!record?.id) return record;

        try {
            const response = await callAdminApi(
                adminApi(`localization/content/${resourceType}/${record.id}`),
            );
            const translations = response.data?.translations ?? {};
            const translation = translations[targetLocale] ?? null;
            const translationFields = response.data?.fields ?? [];
            const emptyTranslationPayload = targetLocale !== contentSourceLocale && !translation
                ? Object.fromEntries(translationFields.map((field) => [field, '']))
                : {};

            return {
                ...record,
                ...emptyTranslationPayload,
                ...(translation?.payload ?? {}),
                _translation_status: translation?.translation_status ?? 'missing',
                _translation_statuses: Object.fromEntries(
                    Object.entries(translations).map(([locale, item]) => [
                        locale,
                        item?.translation_status ?? 'missing',
                    ]),
                ),
            };
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : 'Không thể tải bản dịch danh mục.');

            return record;
        }
    };

    const switchCategoryLocale = async (resourceType, sourceItems, editingCategory, setEditingCategory, nextLocale, emptyRecord) => {
        if (!editingCategory?.id || nextLocale === contentLocale) return true;

        const sourceRecord = sourceItems.find((record) => record.id === editingCategory.id) ?? editingCategory;

        try {
            const localized = await localizedCategoryRecord(resourceType, sourceRecord, nextLocale);
            setContentLocale(nextLocale);
            setEditingCategory({ ...emptyRecord, ...localized });

            return true;
        } catch {
            return false;
        }
    };

    const switchManagedCategoryLocale = async (
        resourceType,
        sourceItems,
        editingCategory,
        setEditingCategory,
        nextLocale,
        emptyRecord,
        reloadCategories,
    ) => {
        const didSwitch = await switchCategoryLocale(
            resourceType,
            sourceItems,
            editingCategory,
            setEditingCategory,
            nextLocale,
            emptyRecord,
        );

        if (didSwitch) await reloadCategories(nextLocale);

        return didSwitch;
    };

    const openEditCategory = async (record) => {
        setEditingCategoryRecord(
            await localizedCategoryRecord('cms_category', record),
        );
        setCategoryFormOpen(true);
    };

    const handleSaveCategory = async (payload, { publish = true } = {}) => {
        if (categorySaving) {
            return false;
        }

        const isUpdate = Boolean(editingCategoryRecord?.id);
        setCategorySaving(true);

        let didSaveWithoutPageReload = false;

        try {
            if (isUpdate && contentLocale !== contentSourceLocale) {
                await callAdminApi(
                    adminApi(`localization/content/cms_category/${editingCategoryRecord.id}/${contentLocale}`),
                    {
                        method: 'PUT',
                        body: JSON.stringify({ payload, publish }),
                    },
                );
            } else {
                await callAdminApi(
                    isUpdate ? adminApi(`cms/categories/${editingCategoryRecord.id}`) : adminApi('cms/categories'),
                    {
                        method: isUpdate ? 'PUT' : 'POST',
                        body: JSON.stringify(payload),
                    },
                );
            }
            await loadCategoryItems({ showLoading: false });
            messageApi.success(contentLocale !== contentSourceLocale
                ? `Đã cập nhật danh mục tin tức ${contentLocale.toUpperCase()}.`
                : (isUpdate ? 'Đã cập nhật danh mục tin tức.' : 'Đã tạo danh mục tin tức.'));
            didSaveWithoutPageReload = true;
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : 'Không thể lưu danh mục tin tức.');
        } finally {
            setCategorySaving(false);
        }

        if (didSaveWithoutPageReload) {
            setContentLocale(contentSourceLocale);
            await loadCategoryItems({ showLoading: false, locale: contentSourceLocale });
            setCategoryFormOpen(false);
            setEditingCategoryRecord(emptyCategory);
        }

        return didSaveWithoutPageReload;
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
                    () => callAdminApi(adminApi(`cms/categories/${record.id}`), { method: 'DELETE' }),
                    'Đã xóa danh mục tin tức.',
                    async () => {
                        await loadCategoryItems();
                        await reload();
                    },
                );
            },
        });
    };

    const loadServiceCategoryItems = async ({ showLoading = true, locale = contentLocale } = {}) => {
        if (showLoading) {
            setServiceCategoryLoading(true);
        }

        try {
            const payload = await callAdminApi(withContentLocale(adminApi('cms/service-categories'), locale));
            const items = payload.data?.items ?? [];
            setServiceCategoryItems(items);

            if (sectionKey === 'cms-services') {
                mutateData((currentData) => currentData ? ({
                    ...currentData,
                    categories: items,
                }) : currentData);
            }

            return items;
        } finally {
            if (showLoading) {
                setServiceCategoryLoading(false);
            }
        }
    };

    const openServiceCategoryManager = async () => {
        setContentLocale(contentSourceLocale);
        setServiceCategoryManagerOpen(true);
        await loadServiceCategoryItems({ locale: contentSourceLocale });
    };

    const openCreateServiceCategory = () => {
        if (contentLocale !== contentSourceLocale) {
            messageApi.warning('Hãy tạo danh mục dịch vụ ở ngôn ngữ gốc trước.');
            return;
        }

        setEditingServiceCategoryRecord(emptyServiceCategory);
        setServiceCategoryFormOpen(true);
    };

    const openEditServiceCategory = async (record) => {
        setEditingServiceCategoryRecord({
            ...emptyServiceCategory,
            ...await localizedCategoryRecord('cms_service_category', record),
        });
        setServiceCategoryFormOpen(true);
    };

    const handleSaveServiceCategory = async (payload, { publish = true } = {}) => {
        if (serviceCategorySaving) {
            return false;
        }

        const isUpdate = Boolean(editingServiceCategoryRecord?.id);
        setServiceCategorySaving(true);

        let didSaveWithoutPageReload = false;

        try {
            if (isUpdate && contentLocale !== contentSourceLocale) {
                await callAdminApi(
                    adminApi(`localization/content/cms_service_category/${editingServiceCategoryRecord.id}/${contentLocale}`),
                    {
                        method: 'PUT',
                        body: JSON.stringify({
                            payload,
                            publish,
                        }),
                    },
                );
            } else {
                await callAdminApi(
                    isUpdate ? adminApi(`cms/service-categories/${editingServiceCategoryRecord.id}`) : adminApi('cms/service-categories'),
                    {
                        method: isUpdate ? 'PUT' : 'POST',
                        body: JSON.stringify(payload),
                    },
                );
            }
            await loadServiceCategoryItems({ showLoading: false });
            messageApi.success(contentLocale !== contentSourceLocale
                ? `Đã cập nhật danh mục dịch vụ ${contentLocale.toUpperCase()}.`
                : (isUpdate ? 'Đã cập nhật danh mục dịch vụ.' : 'Đã tạo danh mục dịch vụ.'));
            didSaveWithoutPageReload = true;
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : 'Không thể lưu danh mục dịch vụ.');
        } finally {
            setServiceCategorySaving(false);
        }

        if (didSaveWithoutPageReload) {
            setContentLocale(contentSourceLocale);
            await loadServiceCategoryItems({ showLoading: false, locale: contentSourceLocale });
            setServiceCategoryFormOpen(false);
            setEditingServiceCategoryRecord(emptyServiceCategory);
        }

        return didSaveWithoutPageReload;
    };

    const handleDeleteServiceCategory = (record) => {
        Modal.confirm({
            title: 'Xóa danh mục dịch vụ?',
            content: `Danh mục "${record.name}" sẽ bị xóa. Các dịch vụ đang gắn danh mục này có thể cần cập nhật lại.`,
            okText: 'Xóa',
            okButtonProps: { danger: true },
            cancelText: 'Hủy',
            onOk: async () => {
                await runAdminAction(
                    () => callAdminApi(adminApi(`cms/service-categories/${record.id}`), { method: 'DELETE' }),
                    'Đã xóa danh mục dịch vụ.',
                    async () => {
                        await loadServiceCategoryItems();
                        await reload();
                    },
                );
            },
        });
    };

    const loadProjectCategoryItems = async ({ showLoading = true, locale = contentLocale } = {}) => {
        if (showLoading) {
            setProjectCategoryLoading(true);
        }

        try {
            const payload = await callAdminApi(withContentLocale(adminApi('cms/project-categories'), locale));
            const items = payload.data?.items ?? [];
            setProjectCategoryItems(items);

            if (sectionKey === 'cms-projects') {
                mutateData((currentData) => currentData ? ({
                    ...currentData,
                    categories: items,
                }) : currentData);
            }

            return items;
        } finally {
            if (showLoading) {
                setProjectCategoryLoading(false);
            }
        }
    };

    const openProjectCategoryManager = async () => {
        setContentLocale(contentSourceLocale);
        setProjectCategoryManagerOpen(true);
        await loadProjectCategoryItems({ locale: contentSourceLocale });
    };

    const openCreateProjectCategory = () => {
        if (contentLocale !== contentSourceLocale) {
            messageApi.warning('Hãy tạo danh mục dự án ở ngôn ngữ gốc trước.');
            return;
        }

        setEditingProjectCategoryRecord(emptyProjectCategory);
        setProjectCategoryFormOpen(true);
    };

    const openEditProjectCategory = async (record) => {
        setEditingProjectCategoryRecord({
            ...emptyProjectCategory,
            ...await localizedCategoryRecord('cms_project_category', record),
        });
        setProjectCategoryFormOpen(true);
    };

    const handleSaveProjectCategory = async (payload, { publish = true } = {}) => {
        if (projectCategorySaving) {
            return false;
        }

        const isUpdate = Boolean(editingProjectCategoryRecord?.id);
        setProjectCategorySaving(true);

        let didSaveWithoutPageReload = false;

        try {
            if (isUpdate && contentLocale !== contentSourceLocale) {
                await callAdminApi(
                    adminApi(`localization/content/cms_project_category/${editingProjectCategoryRecord.id}/${contentLocale}`),
                    {
                        method: 'PUT',
                        body: JSON.stringify({
                            payload,
                            publish,
                        }),
                    },
                );
            } else {
                await callAdminApi(
                    isUpdate ? adminApi(`cms/project-categories/${editingProjectCategoryRecord.id}`) : adminApi('cms/project-categories'),
                    {
                        method: isUpdate ? 'PUT' : 'POST',
                        body: JSON.stringify(payload),
                    },
                );
            }
            await loadProjectCategoryItems({ showLoading: false });
            messageApi.success(contentLocale !== contentSourceLocale
                ? `Đã cập nhật danh mục dự án ${contentLocale.toUpperCase()}.`
                : (isUpdate ? 'Đã cập nhật danh mục dự án.' : 'Đã tạo danh mục dự án.'));
            didSaveWithoutPageReload = true;
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : 'Không thể lưu danh mục dự án.');
        } finally {
            setProjectCategorySaving(false);
        }

        if (didSaveWithoutPageReload) {
            setContentLocale(contentSourceLocale);
            await loadProjectCategoryItems({ showLoading: false, locale: contentSourceLocale });
            setProjectCategoryFormOpen(false);
            setEditingProjectCategoryRecord(emptyProjectCategory);
        }

        return didSaveWithoutPageReload;
    };

    const handleDeleteProjectCategory = (record) => {
        Modal.confirm({
            title: 'Xóa danh mục dự án?',
            content: `Danh mục "${record.name}" sẽ bị xóa. Các dự án đang gắn danh mục này có thể cần cập nhật lại.`,
            okText: 'Xóa',
            okButtonProps: { danger: true },
            cancelText: 'Hủy',
            onOk: async () => {
                await runAdminAction(
                    () => callAdminApi(adminApi(`cms/project-categories/${record.id}`), { method: 'DELETE' }),
                    'Đã xóa danh mục dự án.',
                    async () => {
                        await loadProjectCategoryItems();
                        await reload();
                    },
                );
            },
        });
    };

    const loadProductCategoryItems = async ({ silent = false, locale = contentLocale } = {}) => {
        if (! silent) {
            setProductCategoryLoading(true);
        }

        try {
            const payload = await callAdminApi(withContentLocale(adminApi('cms/product-categories'), locale));
            const items = payload.data?.items ?? [];
            setProductCategoryItems(items);

            if (sectionKey === 'cms-products') {
                mutateData((currentData) => currentData ? ({
                    ...currentData,
                    categories: items,
                }) : currentData);
            }

            return items;
        } finally {
            if (! silent) {
                setProductCategoryLoading(false);
            }
        }
    };

    const openProductCategoryManager = async () => {
        setContentLocale(contentSourceLocale);
        setProductCategoryManagerOpen(true);
        await loadProductCategoryItems({ locale: contentSourceLocale });
    };

    const openCreateProductCategory = () => {
        if (contentLocale !== contentSourceLocale) {
            messageApi.warning('Hãy tạo danh mục sản phẩm ở ngôn ngữ gốc trước.');
            return;
        }

        setEditingProductCategoryRecord(emptyProductCategory);
        setProductCategoryFormOpen(true);
    };

    const openEditProductCategory = async (record) => {
        setEditingProductCategoryRecord({
            ...emptyProductCategory,
            ...await localizedCategoryRecord('catalog_category', record),
        });
        setProductCategoryFormOpen(true);
    };

    const handleSaveProductCategory = async (payload, { publish = true } = {}) => {
        if (productCategorySaving) {
            return false;
        }

        const isUpdate = Boolean(editingProductCategoryRecord?.id);
        setProductCategorySaving(true);

        let didSaveWithoutPageReload = false;

        try {
            if (isUpdate && contentLocale !== contentSourceLocale) {
                await callAdminApi(
                    adminApi(`localization/content/catalog_category/${editingProductCategoryRecord.id}/${contentLocale}`),
                    {
                        method: 'PUT',
                        body: JSON.stringify({
                            payload,
                            publish,
                        }),
                    },
                );
            } else {
                await callAdminApi(
                    isUpdate ? adminApi(`cms/product-categories/${editingProductCategoryRecord.id}`) : adminApi('cms/product-categories'),
                    {
                        method: isUpdate ? 'PUT' : 'POST',
                        body: JSON.stringify(payload),
                    },
                );
            }
            await loadProductCategoryItems({ silent: true });
            messageApi.success(contentLocale !== contentSourceLocale
                ? `Đã cập nhật danh mục sản phẩm ${contentLocale.toUpperCase()}.`
                : (isUpdate ? 'Đã cập nhật danh mục sản phẩm.' : 'Đã tạo danh mục sản phẩm.'));
            didSaveWithoutPageReload = true;
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : 'Không thể lưu danh mục sản phẩm.');
        } finally {
            setProductCategorySaving(false);
        }

        if (didSaveWithoutPageReload) {
            setContentLocale(contentSourceLocale);
            await loadProductCategoryItems({ silent: true, locale: contentSourceLocale });
            setProductCategoryFormOpen(false);
            setEditingProductCategoryRecord(emptyProductCategory);
        }

        return didSaveWithoutPageReload;
    };

    const buildProductCategoryPayload = (category, sortOrder = category.sort_order) => ({
        parent_id: category.parent_id ?? null,
        name: category.name,
        slug: category.slug ?? '',
        description: category.description ?? '',
        image_url: category.image_url ?? '',
        sort_order: sortOrder,
        is_active: Boolean(category.is_active),
    });

    const handleProductCategoryDragEnd = async ({ active, over }) => {
        if (!over || active.id === over.id || !sectionPermissions.canUpdate) {
            return;
        }

        const activeCategoryId = Number(String(active.id).replace('product-category-', ''));
        const overCategoryId = Number(String(over.id).replace('product-category-', ''));
        const activeIndex = productCategoryItems.findIndex((category) => category.id === activeCategoryId);
        const overIndex = productCategoryItems.findIndex((category) => category.id === overCategoryId);

        if (activeIndex < 0 || overIndex < 0 || activeIndex === overIndex) {
            return;
        }

        const previousItems = productCategoryItems;
        const nextItems = arrayMove(productCategoryItems, activeIndex, overIndex).map((category, index) => ({
            ...category,
            sort_order: (index + 1) * 10,
        }));

        setProductCategoryItems(nextItems);
        setProductCategoryLoading(true);

        const didSave = await runAdminAction(async () => {
            for (const category of nextItems) {
                const previousCategory = previousItems.find((item) => item.id === category.id);

                if (!previousCategory || previousCategory.sort_order === category.sort_order) {
                    continue;
                }

                await callAdminApi(adminApi(`cms/product-categories/${category.id}`), {
                    method: 'PUT',
                    body: JSON.stringify(buildProductCategoryPayload(category, category.sort_order)),
                });
            }
        }, 'Đã cập nhật thứ tự danh mục sản phẩm.', async () => {
            await loadProductCategoryItems({ silent: true });
        });

        if (!didSave) {
            setProductCategoryItems(previousItems);
        }

        setProductCategoryLoading(false);
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
                    () => callAdminApi(adminApi(`cms/product-categories/${record.id}`), { method: 'DELETE' }),
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
        stock: values.stock === undefined || values.stock === null || values.stock === ''
            ? product.stock
            : Number(values.stock),
        short_description: product.short_description,
        detail_content: product.detail_content,
        highlights: product.highlights,
        usage_terms: product.usage_terms,
        usage_location: product.usage_location,
        image_url: product.image_url,
        gallery_images: product.gallery_images ?? [],
        sold_count: product.sold_count,
        deal_end_at: product.deal_end_at,
        is_featured: values.is_featured === BULK_KEEP_VALUE ? product.is_featured : values.is_featured === 'true',
        is_highlight: values.is_featured === BULK_KEEP_VALUE ? product.is_highlight : values.is_featured === 'true',
        sort_order: product.sort_order,
        is_active: values.is_active === BULK_KEEP_VALUE ? product.is_active : values.is_active === 'true',
    });

    const buildBulkServicePayload = (service, values = {}) => {
        const hasCategoryChange = Object.prototype.hasOwnProperty.call(values, 'cms_service_category_id');
        const hasStatusChange = Object.prototype.hasOwnProperty.call(values, 'status');
        const hasFeaturedChange = Object.prototype.hasOwnProperty.call(values, 'is_featured');
        const hasHighlightChange = Object.prototype.hasOwnProperty.call(values, 'is_highlight');
        const nextStatus = hasStatusChange ? values.status : service.status;
        const nextFeatured = hasFeaturedChange
            ? (values.is_featured === true || values.is_featured === 'true')
            : service.is_featured;
        const nextHighlight = hasHighlightChange
            ? (values.is_highlight === true || values.is_highlight === 'true')
            : service.is_highlight;

        return {
            cms_service_category_id: hasCategoryChange
                ? (values.cms_service_category_id === BULK_CLEAR_VALUE ? null : values.cms_service_category_id)
                : service.cms_service_category_id,
            title: service.title,
            slug: service.slug,
            status: nextStatus,
            summary: service.summary,
            content: service.content,
            icon: service.icon,
            button_label: service.button_label,
            link_url: service.link_url,
            meta_title: service.meta_title,
            meta_description: service.meta_description,
            meta_keywords: service.meta_keywords,
            publish_at: nextStatus === 'published' ? (service.publish_at || dayjs().format('YYYY-MM-DDTHH:mm:ss')) : null,
            is_featured: nextFeatured,
            is_highlight: nextHighlight,
            sort_order: service.sort_order,
            website_key: service.website_key,
            images: service.images ?? [],
        };
    };

    const handleBulkDeletePages = async () => {
        const ids = [...selectedPageRowKeys];

        const didDelete = await runAdminAction(
            () => callAdminApi(ADMIN_API_ROUTES.cms.pages.bulk, {
                method: 'DELETE',
                body: JSON.stringify({ ids }),
            }),
            `Đã xóa ${ids.length} page.`,
            refreshCurrentSectionDataSilently,
        );

        if (didDelete) {
            setSelectedPageRowKeys([]);
        }
    };

    const confirmBulkDeletePages = () => {
        if (!selectedPageRowKeys.length) {
            return;
        }

        Modal.confirm({
            title: `Xóa ${selectedPageRowKeys.length} page đã chọn?`,
            content: 'Các page đã chọn sẽ bị xóa vĩnh viễn. Thao tác này không thể hoàn tác.',
            okText: 'Xóa tất cả',
            okButtonProps: { danger: true },
            cancelText: 'Hủy',
            onOk: handleBulkDeletePages,
        });
    };

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

    const handleBulkDeleteServices = async () => {
        const ids = [...selectedServiceRowKeys];

        const didDelete = await runAdminAction(async () => {
            for (const id of ids) {
                await callAdminApi(`${sectionConfig.endpoint}/${id}`, { method: 'DELETE' });
            }
        }, `Đã xóa ${ids.length} dịch vụ.`, reload);

        if (didDelete) {
            setSelectedServiceRowKeys([]);
        }
    };

    const confirmBulkDeleteServices = () => {
        if (!selectedServiceRowKeys.length) {
            return;
        }

        Modal.confirm({
            title: `Xóa ${selectedServiceRowKeys.length} dịch vụ đã chọn?`,
            content: 'Thao tác này không thể hoàn tác.',
            okText: 'Xóa tất cả',
            okButtonProps: { danger: true },
            cancelText: 'Hủy',
            onOk: handleBulkDeleteServices,
        });
    };

    const openBulkServiceCategory = () => {
        if (!selectedServiceRowKeys.length) {
            return;
        }

        bulkServiceCategoryForm.setFieldsValue({
            cms_service_category_id: null,
        });
        setBulkServiceCategoryOpen(true);
    };

    const handleBulkServiceCategory = async () => {
        const values = await bulkServiceCategoryForm.validateFields();
        const services = [...selectedServices];

        Modal.confirm({
            title: `Đổi danh mục ${services.length} dịch vụ đã chọn?`,
            content: 'Danh mục mới sẽ được áp dụng cho toàn bộ dịch vụ đang chọn.',
            okText: 'Đổi danh mục',
            cancelText: 'Hủy',
            onOk: async () => {
                const didUpdate = await runAdminAction(async () => {
                    for (const service of services) {
                        await callAdminApi(`${sectionConfig.endpoint}/${service.id}`, {
                            method: 'PUT',
                            body: JSON.stringify(buildBulkServicePayload(service, values)),
                        });
                    }
                }, `Đã cập nhật danh mục cho ${services.length} dịch vụ.`, reload);

                if (didUpdate) {
                    setBulkServiceCategoryOpen(false);
                    setSelectedServiceRowKeys([]);
                    bulkServiceCategoryForm.resetFields();
                }
            },
        });
    };

    const handleBulkUpdateServices = async (values, successMessage) => {
        if (!selectedServiceRowKeys.length) {
            return;
        }

        const services = [...selectedServices];
        const didUpdate = await runAdminAction(async () => {
            for (const service of services) {
                await callAdminApi(`${sectionConfig.endpoint}/${service.id}`, {
                    method: 'PUT',
                    body: JSON.stringify(buildBulkServicePayload(service, values)),
                });
            }
        }, successMessage(services.length), reload);

        if (didUpdate) {
            setSelectedServiceRowKeys([]);
        }
    };

    const handleBulkFeatureServices = (isFeatured) => {
        const count = selectedServiceRowKeys.length;

        if (!count) {
            return;
        }

        Modal.confirm({
            title: isFeatured ? `Đánh dấu nổi bật ${count} dịch vụ đã chọn?` : `Bỏ nổi bật ${count} dịch vụ đã chọn?`,
            content: isFeatured
                ? 'Các dịch vụ đang chọn sẽ được ưu tiên hiển thị trong các khối nổi bật.'
                : 'Các dịch vụ đang chọn sẽ không còn được ưu tiên trong các khối nổi bật.',
            okText: isFeatured ? 'Đánh dấu nổi bật' : 'Bỏ nổi bật',
            cancelText: 'Hủy',
            onOk: () => handleBulkUpdateServices(
                { is_featured: isFeatured },
                (updatedCount) => isFeatured ? `Đã đánh dấu nổi bật ${updatedCount} dịch vụ.` : `Đã bỏ nổi bật ${updatedCount} dịch vụ.`,
            ),
        });
    };

    const handleBulkPublishServices = (status) => {
        const count = selectedServiceRowKeys.length;
        const isPublishing = status === 'published';

        if (!count) {
            return;
        }

        Modal.confirm({
            title: isPublishing ? `Xuất bản ${count} dịch vụ đã chọn?` : `Chuyển ${count} dịch vụ đã chọn về bản nháp?`,
            content: isPublishing
                ? 'Các dịch vụ này sẽ được phép hiển thị ngoài website nếu theme/block đang sử dụng.'
                : 'Các dịch vụ này sẽ bị ẩn khỏi các danh sách công khai ngoài website.',
            okText: isPublishing ? 'Xuất bản' : 'Chuyển bản nháp',
            okButtonProps: isPublishing ? undefined : { danger: true },
            cancelText: 'Hủy',
            onOk: () => handleBulkUpdateServices(
                { status },
                (updatedCount) => isPublishing ? `Đã xuất bản ${updatedCount} dịch vụ.` : `Đã chuyển ${updatedCount} dịch vụ về bản nháp.`,
            ),
        });
    };

    const currentContentBulkState = () => {
        if (sectionKey === 'cms-posts') {
            return {
                endpoint: adminApi('cms/posts'),
                ids: selectedPostRowKeys,
                label: 'bài viết',
                categoryField: 'category_id',
                clearSelection: () => setSelectedPostRowKeys([]),
            };
        }

        if (sectionKey === 'cms-projects') {
            return {
                endpoint: adminApi('cms/projects'),
                ids: selectedProjectRowKeys,
                label: 'dự án',
                categoryField: 'cms_project_category_id',
                clearSelection: () => setSelectedProjectRowKeys([]),
            };
        }

        return null;
    };

    const handleBulkDeleteContentItems = async () => {
        const state = currentContentBulkState();

        if (!state?.ids.length) {
            return;
        }

        const ids = [...state.ids];
        const didDelete = await runAdminAction(
            () => callAdminApi(`${state.endpoint}/bulk`, {
                method: 'DELETE',
                body: JSON.stringify({ ids }),
            }),
            `Đã xóa ${ids.length} ${state.label}.`,
            refreshCurrentSectionDataSilently,
        );

        if (didDelete) {
            state.clearSelection();
        }
    };

    const confirmBulkDeleteContentItems = () => {
        const state = currentContentBulkState();

        if (!state?.ids.length) {
            return;
        }

        Modal.confirm({
            title: `Xóa ${state.ids.length} ${state.label} đã chọn?`,
            content: 'Thao tác này không thể hoàn tác.',
            okText: 'Xóa tất cả',
            okButtonProps: { danger: true },
            cancelText: 'Hủy',
            onOk: handleBulkDeleteContentItems,
        });
    };

    const openBulkContentCategory = () => {
        const state = currentContentBulkState();

        if (!state?.ids.length) {
            return;
        }

        bulkContentCategoryForm.setFieldsValue({
            category_id: null,
        });
        setBulkContentCategoryOpen(true);
    };

    const handleBulkContentCategory = async () => {
        const state = currentContentBulkState();

        if (!state?.ids.length) {
            return false;
        }

        const values = await bulkContentCategoryForm.validateFields();
        const didUpdate = await runAdminAction(
            () => callAdminApi(`${state.endpoint}/bulk`, {
                method: 'PUT',
                body: JSON.stringify({
                    ids: state.ids,
                    [state.categoryField]: values.category_id ?? null,
                }),
            }),
            `Đã cập nhật danh mục cho ${state.ids.length} ${state.label}.`,
            refreshCurrentSectionDataSilently,
        );

        if (didUpdate) {
            setBulkContentCategoryOpen(false);
            state.clearSelection();
            bulkContentCategoryForm.resetFields();
        }

        return didUpdate;
    };

    const handleBulkFeatureContentItems = (isFeatured) => {
        const state = currentContentBulkState();

        if (!state?.ids.length) {
            return;
        }

        const body = sectionKey === 'cms-projects'
            ? { ids: state.ids, is_featured: isFeatured, is_highlight: isFeatured }
            : { ids: state.ids, is_highlight: isFeatured };

        runAdminAction(
            () => callAdminApi(`${state.endpoint}/bulk`, {
                method: 'PUT',
                body: JSON.stringify(body),
            }),
            isFeatured ? `Đã đánh dấu nổi bật ${state.ids.length} ${state.label}.` : `Đã bỏ nổi bật ${state.ids.length} ${state.label}.`,
            refreshCurrentSectionDataSilently,
        ).then((didUpdate) => {
            if (didUpdate) {
                state.clearSelection();
            }
        });
    };

    const currentFeaturedBulkState = () => {
        if (sectionKey === 'cms-team-members') {
            return {
                endpoint: adminApi('cms/team-members'),
                ids: selectedTeamMemberRowKeys,
                label: 'nhân sự',
                clearSelection: () => setSelectedTeamMemberRowKeys([]),
            };
        }

        if (sectionKey === 'cms-partners') {
            return {
                endpoint: adminApi('cms/partners'),
                ids: selectedPartnerRowKeys,
                label: 'đối tác',
                clearSelection: () => setSelectedPartnerRowKeys([]),
            };
        }

        if (sectionKey === 'cms-testimonials') {
            return {
                endpoint: adminApi('cms/testimonials'),
                ids: selectedTestimonialRowKeys,
                label: 'nhận xét',
                clearSelection: () => setSelectedTestimonialRowKeys([]),
            };
        }

        return null;
    };

    const handleBulkDeleteFeaturedItems = async () => {
        const state = currentFeaturedBulkState();

        if (!state?.ids.length) {
            return;
        }

        const ids = [...state.ids];
        const didDelete = await runAdminAction(
            () => callAdminApi(`${state.endpoint}/bulk`, {
                method: 'DELETE',
                body: JSON.stringify({ ids }),
            }),
            `Đã xóa ${ids.length} ${state.label}.`,
            refreshCurrentSectionDataSilently,
        );

        if (didDelete) {
            state.clearSelection();
        }
    };

    const confirmBulkDeleteFeaturedItems = () => {
        const state = currentFeaturedBulkState();

        if (!state?.ids.length) {
            return;
        }

        Modal.confirm({
            title: `Xóa ${state.ids.length} ${state.label} đã chọn?`,
            content: 'Thao tác này không thể hoàn tác.',
            okText: 'Xóa tất cả',
            okButtonProps: { danger: true },
            cancelText: 'Hủy',
            onOk: handleBulkDeleteFeaturedItems,
        });
    };

    const handleBulkFeatureFeaturedItems = (isFeatured) => {
        const state = currentFeaturedBulkState();

        if (!state?.ids.length) {
            return;
        }

        runAdminAction(
            () => callAdminApi(`${state.endpoint}/bulk`, {
                method: 'PUT',
                body: JSON.stringify({ ids: state.ids, is_featured: isFeatured }),
            }),
            isFeatured ? `Đã đánh dấu nổi bật ${state.ids.length} ${state.label}.` : `Đã bỏ nổi bật ${state.ids.length} ${state.label}.`,
            refreshCurrentSectionDataSilently,
        ).then((didUpdate) => {
            if (didUpdate) {
                state.clearSelection();
            }
        });
    };

    const openBulkEditProducts = () => {
        if (!selectedProductRowKeys.length) {
            return;
        }

        bulkProductEditForm.setFieldsValue({
            catalog_category_id: BULK_KEEP_VALUE,
            stock: null,
            is_featured: BULK_KEEP_VALUE,
            is_active: BULK_KEEP_VALUE,
        });
        setBulkProductEditOpen(true);
    };

    const openBulkStockProducts = () => {
        if (!selectedProductRowKeys.length) {
            return;
        }

        bulkProductStockForm.setFieldsValue({
            stock: 1000,
        });
        setBulkProductStockOpen(true);
    };

    const openBulkActiveProducts = () => {
        if (!selectedProductRowKeys.length) {
            return;
        }

        bulkProductActiveForm.setFieldsValue({
            is_active: 'true',
        });
        setBulkProductActiveOpen(true);
    };

    const handleBulkEditProducts = async () => {
        const values = await bulkProductEditForm.validateFields();

        if (
            values.catalog_category_id === BULK_KEEP_VALUE
            && (values.stock === undefined || values.stock === null || values.stock === '')
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

    const handleBulkStockProducts = async () => {
        const values = await bulkProductStockForm.validateFields();
        const products = [...selectedProducts];

        const didUpdate = await runAdminAction(async () => {
            for (const product of products) {
                await callAdminApi(`${sectionConfig.endpoint}/${product.id}`, {
                    method: 'PUT',
                    body: JSON.stringify(buildBulkProductPayload(product, {
                        catalog_category_id: BULK_KEEP_VALUE,
                        stock: Number(values.stock),
                        is_featured: BULK_KEEP_VALUE,
                        is_active: BULK_KEEP_VALUE,
                    })),
                });
            }
        }, `Đã cập nhật tồn kho cho ${products.length} sản phẩm.`, reload);

        if (didUpdate) {
            setBulkProductStockOpen(false);
            setSelectedProductRowKeys([]);
            bulkProductStockForm.resetFields();
        }
    };

    const handleBulkActiveProducts = async () => {
        const values = await bulkProductActiveForm.validateFields();
        const products = [...selectedProducts];
        const nextActive = values.is_active === 'true';

        const didUpdate = await runAdminAction(async () => {
            for (const product of products) {
                await callAdminApi(`${sectionConfig.endpoint}/${product.id}`, {
                    method: 'PUT',
                    body: JSON.stringify(buildBulkProductPayload(product, {
                        catalog_category_id: BULK_KEEP_VALUE,
                        stock: null,
                        is_featured: BULK_KEEP_VALUE,
                        is_active: nextActive ? 'true' : 'false',
                    })),
                });
            }
        }, `Đã ${nextActive ? 'active' : 'unactive'} ${products.length} sản phẩm.`, reload);

        if (didUpdate) {
            setBulkProductActiveOpen(false);
            setSelectedProductRowKeys([]);
            bulkProductActiveForm.resetFields();
        }
    };

    const openOrderDetails = async (order) => {
        setSelectedOrder({
            ...order,
            is_read: true,
            read_at: order.read_at || new Date().toISOString(),
        });

        if (!order.is_read) {
            await callAdminApi(adminApi(`cms/orders/${order.id}/read`), { method: 'PUT' });
            await reload();
        }
    };

    const handleBulkMarkOrdersRead = async () => {
        const orders = [...selectedOrders];

        const didUpdate = await runAdminAction(async () => {
            for (const order of orders) {
                await callAdminApi(adminApi(`cms/orders/${order.id}/read`), { method: 'PUT' });
            }
        }, `Đã đánh dấu đã xem ${orders.length} đơn hàng.`, reload);

        if (didUpdate) {
            setSelectedOrderRowKeys([]);
        }
    };

    const openBulkOrderStatus = () => {
        if (!selectedOrderRowKeys.length) {
            return;
        }

        bulkOrderStatusForm.setFieldsValue({ status: 'processing' });
        setBulkOrderStatusOpen(true);
    };

    const handleBulkOrderStatus = async () => {
        const values = await bulkOrderStatusForm.validateFields();
        const orders = [...selectedOrders];

        const didUpdate = await runAdminAction(async () => {
            for (const order of orders) {
                await callAdminApi(adminApi(`cms/orders/${order.id}`), {
                    method: 'PUT',
                    body: JSON.stringify({ status: values.status }),
                });
            }
        }, `Đã đổi trạng thái ${orders.length} đơn hàng.`, reload);

        if (didUpdate) {
            setBulkOrderStatusOpen(false);
            setSelectedOrderRowKeys([]);
            bulkOrderStatusForm.resetFields();
        }
    };

    const handleBulkDeleteOrders = async () => {
        const ids = [...selectedOrderRowKeys];

        const didDelete = await runAdminAction(async () => {
            for (const id of ids) {
                await callAdminApi(adminApi(`cms/orders/${id}`), { method: 'DELETE' });
            }
        }, `Đã xóa ${ids.length} đơn hàng.`, reload);

        if (didDelete) {
            setSelectedOrderRowKeys([]);
            setSelectedOrder(null);
        }
    };

    const confirmBulkDeleteOrders = () => {
        if (!selectedOrderRowKeys.length) {
            return;
        }

        Modal.confirm({
            title: `Xóa ${selectedOrderRowKeys.length} đơn hàng đã chọn?`,
            content: 'Thao tác này không thể hoàn tác.',
            okText: 'Xóa tất cả',
            okButtonProps: { danger: true },
            cancelText: 'Hủy',
            onOk: handleBulkDeleteOrders,
        });
    };

    const confirmDeleteRecord = (recordId, onDeleted = null) => {
        Modal.confirm({
            title: 'Xóa bản ghi này?',
            content: 'Thao tác này không thể hoàn tác.',
            okText: 'Xóa',
            okButtonProps: { danger: true },
            cancelText: 'Hủy',
            onOk: async () => {
                const didDelete = await handleDeleteRecord(recordId);

                if (didDelete !== false) {
                    onDeleted?.();
                }
            },
        });
    };

    const openMediaUploadModal = () => {
        setMediaUpload((current) => ({
            ...current,
            folder_path: activeMediaFolder === 'all' || activeMediaFolder === 'uncategorized' ? null : activeMediaFolder,
        }));
        setMediaUploadOpen(true);
    };

    const handleCreateMediaFolder = () => {
        let folderName = '';

        Modal.confirm({
            title: 'Tạo thư mục media',
            icon: <FolderOutlined />,
            content: <Input autoFocus placeholder="VD: Banner trang chủ" onChange={(event) => { folderName = event.target.value; }} />,
            okText: 'Tạo thư mục',
            cancelText: 'Hủy',
            onOk: async () => {
                const name = folderName.trim();

                if (!name) {
                    messageApi.warning('Nhập tên thư mục trước khi tạo.');
                    return Promise.reject();
                }

                const created = await callAdminApi(adminApi('cms/media/folders'), {
                    method: 'POST',
                    body: JSON.stringify({ name }),
                });

                if (created?.data?.path) {
                    messageApi.success('Đã tạo thư mục media.');
                    await reload();
                    setActiveMediaFolder(created.data.path);
                }
            },
        });
    };

    const handleRenameMediaFolder = (folder) => {
        let folderName = folder.name;

        Modal.confirm({
            title: 'Đổi tên thư mục media',
            icon: <EditOutlined />,
            content: (
                <Input
                    autoFocus
                    defaultValue={folder.name}
                    placeholder="Nhập tên thư mục mới"
                    onChange={(event) => { folderName = event.target.value; }}
                    onPressEnter={() => {}}
                />
            ),
            okText: 'Lưu tên mới',
            cancelText: 'Hủy',
            onOk: async () => {
                const name = folderName.trim();

                if (!name) {
                    messageApi.warning('Tên thư mục không được để trống.');
                    return Promise.reject();
                }

                const updated = await callAdminApi(adminApi(`cms/media/folders/${folder.id}`), {
                    method: 'PUT',
                    body: JSON.stringify({ name }),
                });

                if (updated?.data?.path) {
                    if (activeMediaFolder === folder.path) {
                        setActiveMediaFolder(updated.data.path);
                    }
                    messageApi.success('Đã đổi tên thư mục media.');
                    await reload();
                }
            },
        });
    };

    const handleDeleteMediaFolder = (folder) => {
        Modal.confirm({
            title: `Xóa thư mục “${folder.name}”?`,
            icon: <DeleteOutlined />,
            content: 'Media trong thư mục sẽ được đưa về Chưa phân loại. File và URL ảnh vẫn được giữ nguyên.',
            okText: 'Xóa thư mục',
            okButtonProps: { danger: true },
            cancelText: 'Hủy',
            onOk: async () => {
                const deleted = await callAdminApi(adminApi(`cms/media/folders/${folder.id}`), {
                    method: 'DELETE',
                });

                if (deleted?.data?.deleted_folder_id) {
                    if (activeMediaFolder === folder.path) {
                        setActiveMediaFolder('all');
                    }
                    messageApi.success('Đã xóa thư mục; media bên trong được đưa về Chưa phân loại.');
                    await reload();
                }
            },
        });
    };

    const handleUploadMedia = async () => {
        if (!mediaFiles.length) {
            return;
        }

        let uploadedCount = 0;
        for (const [index, file] of mediaFiles.entries()) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('title', mediaUpload.title || file.name.replace(/\.[^.]+$/, ''));
            if (mediaFiles.length > 1 && mediaUpload.title) {
                formData.set('title', `${mediaUpload.title} ${index + 1}`);
            }
            if (mediaUpload.alt_text) {
                formData.append('alt_text', mediaUpload.alt_text);
            }
            if (mediaUpload.folder_path) {
                formData.append('folder_path', mediaUpload.folder_path);
            }

            const payload = await callAdminApi(adminApi('cms/media'), { method: 'POST', body: formData });
            if (payload?.data?.id) {
                uploadedCount++;
            }
        }

        if (uploadedCount > 0) {
            messageApi.success(`Đã upload ${uploadedCount} media.`);
            await reload();
            setMediaFiles([]);
            setMediaUpload({ title: '', alt_text: '', folder_path: null });
            setMediaUploadOpen(false);
        }
    };

    const updateMediaFolder = async (record, folderPath) => {
        if (record?.is_current_website === false) {
            messageApi.warning('Media này thuộc website khác. Bạn chỉ nên copy URL để dùng chung.');

            return false;
        }

        const didSave = await runAdminAction(
            () => callAdminApi(adminApi(`cms/media/${record.id}`), {
                method: 'PUT',
                body: JSON.stringify({
                    title: record.title || `Media #${record.id}`,
                    alt_text: record.alt_text || null,
                    folder_path: folderPath || null,
                }),
            }),
            folderPath ? 'Đã chuyển media vào thư mục.' : 'Đã đưa media về thư mục gốc.',
            reload,
        );

        return didSave;
    };

    const mediaActionMenu = (record) => ({
        items: [
            { key: 'open', label: 'Mở media', icon: <EyeOutlined /> },
            { key: 'copy-url', label: 'Copy URL', icon: <CopyOutlined /> },
            { key: 'edit-media-title', label: 'Sửa thông tin', icon: <EditOutlined />, disabled: !sectionPermissions.canUpdate },
            {
                key: 'move-media',
                label: 'Chuyển thư mục',
                icon: <FolderOpenOutlined />,
                disabled: !sectionPermissions.canUpdate,
                children: [
                    { key: 'move-media:root', label: 'Thư mục gốc' },
                    ...mediaFolders.map((folder) => ({ key: `move-media:${folder.path}`, label: folder.name })),
                ],
            },
            { key: 'delete', label: 'Xóa', icon: <DeleteOutlined />, danger: true, disabled: !sectionPermissions.canDelete },
        ],
        onClick: ({ key }) => {
            if (key === 'open' && record.file_url) {
                window.open(record.file_url, '_blank', 'noopener,noreferrer');
                return;
            }

            if (key === 'copy-url') {
                handleCopyMediaUrl(record);
                return;
            }

            if (key === 'edit-media-title') {
                if (record?.is_current_website === false) {
                    messageApi.warning('Media này thuộc website khác. Bạn chỉ nên copy URL để dùng chung.');

                    return;
                }

                openEditMediaTitle(record);
                return;
            }

            if (key.startsWith('move-media:')) {
                const nextFolder = key.replace('move-media:', '');
                updateMediaFolder(record, nextFolder === 'root' ? null : nextFolder);
                return;
            }

            if (key === 'delete') {
                if (record?.is_current_website === false) {
                    messageApi.warning('Media này thuộc website khác. Bạn chỉ nên copy URL để dùng chung.');

                    return;
                }

                confirmDeleteRecord(record.id);
            }
        },
    });

    const resolveFullUrl = (url) => {
        const trimmedUrl = String(url ?? '').trim();

        if (!trimmedUrl) {
            return '';
        }

        try {
            return new URL(trimmedUrl, window.location.origin).href;
        } catch {
            return trimmedUrl;
        }
    };

    const copyTextToClipboard = async (value) => {
        if (navigator.clipboard?.writeText) {
            try {
                await navigator.clipboard.writeText(value);
                return true;
            } catch {
                // Fallback below handles browsers or contexts that block Clipboard API.
            }
        }

        const textarea = document.createElement('textarea');
        textarea.value = value;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.top = '-9999px';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        const didCopy = document.execCommand('copy');
        document.body.removeChild(textarea);

        return didCopy;
    };

    const handleCopyMediaUrl = async (record) => {
        const fullUrl = resolveFullUrl(record?.file_url);

        if (!fullUrl) {
            return;
        }

        const didCopy = await copyTextToClipboard(fullUrl);

        if (didCopy) {
            messageApi.success('Đã copy đường dẫn ảnh.');
        } else {
            messageApi.error('Không thể copy đường dẫn ảnh.');
        }
    };

    const handleCopyPublicUrl = async (url) => {
        if (!url) {
            return;
        }

        const didCopy = await copyTextToClipboard(resolveFullUrl(url));

        if (didCopy) {
            messageApi.success('Đã copy link website.');
        } else {
            messageApi.error('Không thể copy link website.');
        }
    };

    const openEditMediaTitle = async (record) => {
        try {
            const response = await callAdminApi(
                adminApi(`localization/content/cms_media/${record.id}`),
            );
            const translation = response.data?.translations?.[contentLocale] ?? null;
            const localizedRecord = {
                ...record,
                ...(translation?.payload ?? {}),
                _translation_status: translation?.translation_status ?? 'missing',
            };

            setEditingMediaRecord(localizedRecord);
            mediaEditForm.setFieldsValue({
                title: localizedRecord.title ?? '',
                alt_text: localizedRecord.alt_text ?? '',
                folder_path: record.folder_path ?? null,
            });
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : 'Không thể tải bản dịch media.');
        }
    };

    const handleSaveMediaTitle = async () => {
        if (!editingMediaRecord?.id) {
            return false;
        }

        const values = await mediaEditForm.validateFields();
        const isSourceLocale = contentLocale === contentSourceLocale;
        const didSave = await runAdminAction(
            () => callAdminApi(
                isSourceLocale
                    ? adminApi(`cms/media/${editingMediaRecord.id}`)
                    : adminApi(`localization/content/cms_media/${editingMediaRecord.id}/${contentLocale}`),
                {
                    method: 'PUT',
                    body: JSON.stringify(isSourceLocale
                        ? {
                            title: values.title,
                            alt_text: values.alt_text || null,
                            folder_path: values.folder_path || null,
                        }
                        : {
                            payload: {
                                title: values.title,
                                alt_text: values.alt_text || null,
                            },
                            publish: true,
                        }),
                },
            ),
            'Da cap nhat ten hien thi media.',
            reload,
        );

        if (didSave) {
            setEditingMediaRecord(null);
            mediaEditForm.resetFields();
        }
    };

    const renderActions = (record, { productDrawer = false, pageDrawer = false } = {}) => {
        const actionItems = [];
        const drawerAction = productDrawer || pageDrawer;

        if (sectionKey === 'cms-orders') {
            actionItems.push({
                key: 'detail',
                label: 'Xem chi tiết',
                icon: <EyeOutlined />,
            });

            const handleOrderActionClick = ({ key }) => {
                if (key === 'detail') {
                    openOrderDetails(record);
                }
            };

            return (
                <Dropdown menu={{ items: actionItems, onClick: handleOrderActionClick }} trigger={['click']}>
                    <Button size="small" icon={<MoreOutlined />} onClick={(event) => event.stopPropagation()}>Tác vụ</Button>
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
            actionItems.push({
                key: 'copy-public-url',
                label: 'Copy link',
                icon: <CopyOutlined />,
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
                key: 'copy-url',
                label: 'Copy URL',
                icon: <CopyOutlined />,
            });
            actionItems.push({
                key: 'edit-media-title',
                label: 'Sửa tên hiển thị',
                icon: <EditOutlined />,
                disabled: !sectionPermissions.canUpdate,
            });
            actionItems.push({
                key: 'move-media',
                label: 'Chuyển thư mục',
                icon: <FolderOpenOutlined />,
                disabled: !sectionPermissions.canUpdate,
                children: [
                    { key: 'move-media:root', label: 'Thư mục gốc' },
                    ...mediaFolders.map((folder) => ({
                        key: `move-media:${folder.path}`,
                        label: folder.name,
                    })),
                ],
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

            if (key === 'copy-public-url') {
                handleCopyPublicUrl(record.public_url);
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

            if (key === 'copy-url') {
                handleCopyMediaUrl(record);
                return;
            }

            if (key === 'edit-media-title') {
                openEditMediaTitle(record);
                return;
            }

            if (key.startsWith('move-media:')) {
                const nextFolder = key.replace('move-media:', '');
                updateMediaFolder(record, nextFolder === 'root' ? null : nextFolder);
                return;
            }

            if (key === 'edit') {
                if (productDrawer) {
                    setSelectedProduct(null);
                }

                if (pageDrawer) {
                    setSelectedPage(null);
                }

                openEditModal(record);
                return;
            }

            if (key === 'delete') {
                const closeDrawer = productDrawer
                    ? () => setSelectedProduct(null)
                    : (pageDrawer ? () => setSelectedPage(null) : null);
                confirmDeleteRecord(record.id, closeDrawer);
            }
        };

        return (
            <Dropdown menu={{ items: actionItems, onClick: handleActionClick }} trigger={['click']}>
                <Button size={drawerAction ? 'middle' : 'small'} icon={<MoreOutlined />}>
                    {drawerAction ? 'Thao tác' : 'Tác vụ'}
                </Button>
            </Dropdown>
        );
    };

    const pageRowSelection = sectionKey === 'cms-pages' && sectionPermissions.canDelete
        ? {
            selectedRowKeys: selectedPageRowKeys,
            onChange: (nextSelectedRowKeys) => setSelectedPageRowKeys(nextSelectedRowKeys),
            preserveSelectedRowKeys: true,
        }
        : undefined;
    const productRowSelection = sectionKey === 'cms-products' && (sectionPermissions.canUpdate || sectionPermissions.canDelete)
        ? {
            selectedRowKeys: selectedProductRowKeys,
            onChange: (nextSelectedRowKeys) => setSelectedProductRowKeys(nextSelectedRowKeys),
            preserveSelectedRowKeys: true,
        }
        : undefined;
    const orderRowSelection = sectionKey === 'cms-orders' && (sectionPermissions.canUpdate || sectionPermissions.canDelete)
        ? {
            selectedRowKeys: selectedOrderRowKeys,
            onChange: (nextSelectedRowKeys) => setSelectedOrderRowKeys(nextSelectedRowKeys),
            preserveSelectedRowKeys: true,
        }
        : undefined;
    const partnerRowSelection = sectionKey === 'cms-partners' && (sectionPermissions.canUpdate || sectionPermissions.canDelete)
        ? {
            selectedRowKeys: selectedPartnerRowKeys,
            onChange: (nextSelectedRowKeys) => setSelectedPartnerRowKeys(nextSelectedRowKeys),
            preserveSelectedRowKeys: true,
        }
        : undefined;
    const teamMemberRowSelection = sectionKey === 'cms-team-members' && (sectionPermissions.canUpdate || sectionPermissions.canDelete)
        ? {
            selectedRowKeys: selectedTeamMemberRowKeys,
            onChange: (nextSelectedRowKeys) => setSelectedTeamMemberRowKeys(nextSelectedRowKeys),
            preserveSelectedRowKeys: true,
        }
        : undefined;
    const testimonialRowSelection = sectionKey === 'cms-testimonials' && (sectionPermissions.canUpdate || sectionPermissions.canDelete)
        ? {
            selectedRowKeys: selectedTestimonialRowKeys,
            onChange: (nextSelectedRowKeys) => setSelectedTestimonialRowKeys(nextSelectedRowKeys),
            preserveSelectedRowKeys: true,
        }
        : undefined;
    const serviceRowSelection = sectionKey === 'cms-services' && (sectionPermissions.canUpdate || sectionPermissions.canDelete)
        ? {
            selectedRowKeys: selectedServiceRowKeys,
            onChange: (nextSelectedRowKeys) => setSelectedServiceRowKeys(nextSelectedRowKeys),
            preserveSelectedRowKeys: true,
        }
        : undefined;
    const postRowSelection = sectionKey === 'cms-posts' && (sectionPermissions.canUpdate || sectionPermissions.canDelete)
        ? {
            selectedRowKeys: selectedPostRowKeys,
            onChange: (nextSelectedRowKeys) => setSelectedPostRowKeys(nextSelectedRowKeys),
            preserveSelectedRowKeys: true,
        }
        : undefined;
    const projectRowSelection = sectionKey === 'cms-projects' && (sectionPermissions.canUpdate || sectionPermissions.canDelete)
        ? {
            selectedRowKeys: selectedProjectRowKeys,
            onChange: (nextSelectedRowKeys) => setSelectedProjectRowKeys(nextSelectedRowKeys),
            preserveSelectedRowKeys: true,
        }
        : undefined;
    const pageBulkActions = sectionKey === 'cms-pages' ? (
        <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
            <Space wrap>
                <Button
                    danger
                    icon={<DeleteOutlined />}
                    disabled={!sectionPermissions.canDelete || !selectedPageRowKeys.length}
                    onClick={confirmBulkDeletePages}
                >
                    Xóa đã chọn
                </Button>
                <Text type="secondary">Đã chọn {selectedPageRowKeys.length} page.</Text>
            </Space>
            {selectedPageRowKeys.length ? (
                <Button size="small" type="link" onClick={() => setSelectedPageRowKeys([])}>
                    Bỏ chọn
                </Button>
            ) : null}
        </Space>
    ) : null;
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
                                key: 'bulk-stock',
                                label: 'Điều chỉnh tồn kho',
                                icon: <EditOutlined />,
                                disabled: !sectionPermissions.canUpdate || !selectedProductRowKeys.length,
                            },
                            {
                                key: 'bulk-active',
                                label: 'Active / unactive sản phẩm',
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

                            if (key === 'bulk-stock') {
                                openBulkStockProducts();
                            }

                            if (key === 'bulk-active') {
                                openBulkActiveProducts();
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
    const orderBulkActions = sectionKey === 'cms-orders' ? (
        <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
            <Space wrap>
                <Dropdown
                    trigger={['click']}
                    menu={{
                        items: [
                            {
                                key: 'mark-read',
                                label: 'Đánh dấu đã xem',
                                icon: <EyeOutlined />,
                                disabled: !sectionPermissions.canUpdate || !selectedOrderRowKeys.length,
                            },
                            {
                                key: 'change-status',
                                label: 'Đổi trạng thái',
                                icon: <EditOutlined />,
                                disabled: !sectionPermissions.canUpdate || !selectedOrderRowKeys.length,
                            },
                            {
                                key: 'delete',
                                label: 'Xóa đã chọn',
                                icon: <DeleteOutlined />,
                                danger: true,
                                disabled: !sectionPermissions.canDelete || !selectedOrderRowKeys.length,
                            },
                        ],
                        onClick: ({ key }) => {
                            if (key === 'mark-read') {
                                handleBulkMarkOrdersRead();
                            }

                            if (key === 'change-status') {
                                openBulkOrderStatus();
                            }

                            if (key === 'delete') {
                                confirmBulkDeleteOrders();
                            }
                        },
                    }}
                >
                    <Button icon={<MoreOutlined />} disabled={!selectedOrderRowKeys.length}>
                        Thao tác đã chọn
                    </Button>
                </Dropdown>
                {selectedOrderRowKeys.length ? (
                    <Text type="secondary">Đã chọn {selectedOrderRowKeys.length} đơn hàng.</Text>
                ) : null}
            </Space>
            {selectedOrderRowKeys.length ? (
                <Button size="small" type="link" onClick={() => setSelectedOrderRowKeys([])}>
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
    const featuredBulkActions = ['cms-partners', 'cms-team-members', 'cms-testimonials'].includes(sectionKey) ? (() => {
        const selectedKeys = sectionKey === 'cms-partners'
            ? selectedPartnerRowKeys
            : sectionKey === 'cms-team-members'
                ? selectedTeamMemberRowKeys
                : selectedTestimonialRowKeys;
        const clearSelection = sectionKey === 'cms-partners'
            ? () => setSelectedPartnerRowKeys([])
            : sectionKey === 'cms-team-members'
                ? () => setSelectedTeamMemberRowKeys([])
                : () => setSelectedTestimonialRowKeys([]);
        const label = sectionKey === 'cms-partners'
            ? 'đối tác'
            : sectionKey === 'cms-team-members'
                ? 'nhân sự'
                : 'nhận xét';

        return (
            <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
                <Space wrap>
                    <Dropdown
                        trigger={['click']}
                        menu={{
                            items: [
                                {
                                    key: 'bulk-feature',
                                    label: 'Đánh dấu nổi bật',
                                    icon: <EditOutlined />,
                                    disabled: !sectionPermissions.canUpdate || !selectedKeys.length,
                                },
                                {
                                    key: 'bulk-unfeature',
                                    label: 'Bỏ nổi bật',
                                    icon: <EditOutlined />,
                                    disabled: !sectionPermissions.canUpdate || !selectedKeys.length,
                                },
                                {
                                    key: 'bulk-delete',
                                    label: 'Xóa đã chọn',
                                    icon: <DeleteOutlined />,
                                    danger: true,
                                    disabled: !sectionPermissions.canDelete || !selectedKeys.length,
                                },
                            ],
                            onClick: ({ key }) => {
                                if (key === 'bulk-feature') {
                                    handleBulkFeatureFeaturedItems(true);
                                }

                                if (key === 'bulk-unfeature') {
                                    handleBulkFeatureFeaturedItems(false);
                                }

                                if (key === 'bulk-delete') {
                                    confirmBulkDeleteFeaturedItems();
                                }
                            },
                        }}
                    >
                        <Button icon={<MoreOutlined />} disabled={!selectedKeys.length}>
                            Thao tác đã chọn
                        </Button>
                    </Dropdown>
                    {selectedKeys.length ? (
                        <Text type="secondary">Đã chọn {selectedKeys.length} {label}.</Text>
                    ) : null}
                </Space>
                {selectedKeys.length ? (
                    <Button size="small" type="link" onClick={clearSelection}>
                        Bỏ chọn
                    </Button>
                ) : null}
            </Space>
        );
    })() : null;

    const serviceBulkActions = sectionKey === 'cms-services' ? (
        <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
            <Space wrap>
                <Dropdown
                    trigger={['click']}
                    menu={{
                        items: [
                            {
                                key: 'bulk-feature',
                                label: 'Đánh dấu nổi bật',
                                icon: <EditOutlined />,
                                disabled: !sectionPermissions.canUpdate || !selectedServiceRowKeys.length,
                            },
                            {
                                key: 'bulk-unfeature',
                                label: 'Bỏ nổi bật',
                                icon: <EditOutlined />,
                                disabled: !sectionPermissions.canUpdate || !selectedServiceRowKeys.length,
                            },
                            {
                                key: 'bulk-publish',
                                label: 'Xuất bản đã chọn',
                                icon: <EditOutlined />,
                                disabled: !sectionPermissions.canUpdate || !selectedServiceRowKeys.length,
                            },
                            {
                                key: 'bulk-draft',
                                label: 'Chuyển về bản nháp',
                                icon: <EditOutlined />,
                                disabled: !sectionPermissions.canUpdate || !selectedServiceRowKeys.length,
                            },
                            {
                                key: 'bulk-category',
                                label: 'Đổi danh mục đã chọn',
                                icon: <EditOutlined />,
                                disabled: !sectionPermissions.canUpdate || !selectedServiceRowKeys.length,
                            },
                            {
                                key: 'bulk-delete',
                                label: 'Xóa đã chọn',
                                icon: <DeleteOutlined />,
                                danger: true,
                                disabled: !sectionPermissions.canDelete || !selectedServiceRowKeys.length,
                            },
                        ],
                        onClick: ({ key }) => {
                            if (key === 'bulk-feature') {
                                handleBulkFeatureServices(true);
                            }

                            if (key === 'bulk-unfeature') {
                                handleBulkFeatureServices(false);
                            }

                            if (key === 'bulk-publish') {
                                handleBulkPublishServices('published');
                            }

                            if (key === 'bulk-draft') {
                                handleBulkPublishServices('draft');
                            }

                            if (key === 'bulk-category') {
                                openBulkServiceCategory();
                            }

                            if (key === 'bulk-delete') {
                                confirmBulkDeleteServices();
                            }
                        },
                    }}
                >
                    <Button icon={<MoreOutlined />} disabled={!selectedServiceRowKeys.length}>
                        Thao tác đã chọn
                    </Button>
                </Dropdown>
                {selectedServiceRowKeys.length ? (
                    <Text type="secondary">Đã chọn {selectedServiceRowKeys.length} dịch vụ.</Text>
                ) : null}
            </Space>
            {selectedServiceRowKeys.length ? (
                <Button size="small" type="link" onClick={() => setSelectedServiceRowKeys([])}>
                    Bỏ chọn
                </Button>
            ) : null}
        </Space>
    ) : null;
    const contentBulkActions = ['cms-posts', 'cms-projects'].includes(sectionKey) ? (() => {
        const selectedKeys = sectionKey === 'cms-posts' ? selectedPostRowKeys : selectedProjectRowKeys;
        const clearSelection = sectionKey === 'cms-posts' ? () => setSelectedPostRowKeys([]) : () => setSelectedProjectRowKeys([]);
        const label = sectionKey === 'cms-posts' ? 'bài viết' : 'dự án';

        return (
            <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
                <Space wrap>
                    <Dropdown
                        trigger={['click']}
                        menu={{
                            items: [
                                {
                                    key: 'bulk-feature',
                                    label: 'Đánh dấu nổi bật',
                                    icon: <EditOutlined />,
                                    disabled: !sectionPermissions.canUpdate || !selectedKeys.length,
                                },
                                {
                                    key: 'bulk-unfeature',
                                    label: 'Bỏ nổi bật',
                                    icon: <EditOutlined />,
                                    disabled: !sectionPermissions.canUpdate || !selectedKeys.length,
                                },
                                {
                                    key: 'bulk-category',
                                    label: 'Đổi danh mục đã chọn',
                                    icon: <EditOutlined />,
                                    disabled: !sectionPermissions.canUpdate || !selectedKeys.length,
                                },
                                {
                                    key: 'bulk-delete',
                                    label: 'Xóa đã chọn',
                                    icon: <DeleteOutlined />,
                                    danger: true,
                                    disabled: !sectionPermissions.canDelete || !selectedKeys.length,
                                },
                            ],
                            onClick: ({ key }) => {
                                if (key === 'bulk-feature') {
                                    handleBulkFeatureContentItems(true);
                                }

                                if (key === 'bulk-unfeature') {
                                    handleBulkFeatureContentItems(false);
                                }

                                if (key === 'bulk-category') {
                                    openBulkContentCategory();
                                }

                                if (key === 'bulk-delete') {
                                    confirmBulkDeleteContentItems();
                                }
                            },
                        }}
                    >
                        <Button icon={<MoreOutlined />} disabled={!selectedKeys.length}>
                            Thao tác đã chọn
                        </Button>
                    </Dropdown>
                    {selectedKeys.length ? (
                        <Text type="secondary">Đã chọn {selectedKeys.length} {label}.</Text>
                    ) : null}
                </Space>
                {selectedKeys.length ? (
                    <Button size="small" type="link" onClick={clearSelection}>
                        Bỏ chọn
                    </Button>
                ) : null}
            </Space>
        );
    })() : null;

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
                {
                    title: 'Title',
                    dataIndex: 'title',
                    key: 'title',
                    render: (value, record) => (
                        <Button type="link" style={{ paddingInline: 0, height: 'auto', textAlign: 'left' }} onClick={() => openPageDetailsDrawer(record)}>
                            <Text strong style={{ color: '#1677ff' }}>{value}</Text>
                        </Button>
                    ),
                },
                { title: 'Slug', dataIndex: 'slug', key: 'slug' },
                { title: 'Status', dataIndex: 'status', key: 'status', render: renderStatusTag },
                {
                    title: 'Ngôn ngữ',
                    key: 'translations',
                    render: (_, record) => (
                        <Space wrap size={[4, 4]}>
                            {Object.values(record.translations ?? {}).map((translation) => (
                                <Tag key={translation.locale}>
                                    {translation.locale}: {translation.translation_status}
                                </Tag>
                            ))}
                        </Space>
                    ),
                },
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

        if (sectionKey === 'cms-testimonials') {
            return [
                {
                    title: 'Khách hàng',
                    dataIndex: 'name',
                    key: 'name',
                    render: (value, record) => (
                        <Space size={12} align="start">
                            {record.image_url ? (
                                <img
                                    src={record.image_url}
                                    alt={record.image_alt || value}
                                    style={{ width: 56, height: 56, objectFit: 'cover', borderRadius: 999, border: '1px solid #dbe7e4', display: 'block' }}
                                />
                            ) : (
                                <div
                                    style={{
                                        width: 56,
                                        height: 56,
                                        borderRadius: 999,
                                        border: '1px solid #dbe7e4',
                                        background: '#f4f7f6',
                                        display: 'grid',
                                        placeItems: 'center',
                                        color: '#8aa19a',
                                        fontSize: 12,
                                        fontWeight: 600,
                                    }}
                                >
                                    No Img
                                </div>
                            )}
                            <Space direction="vertical" size={2} align="start">
                                <Button type="link" style={{ paddingInline: 0, height: 'auto' }} onClick={() => openEditModal(record)}>
                                    <Text strong style={{ color: '#1677ff' }}>{value}</Text>
                                </Button>
                                <Text type="secondary">{record.company || record.role || 'Chưa có thông tin phụ'}</Text>
                                <Paragraph ellipsis={{ rows: 2 }} style={{ margin: 0, maxWidth: 460 }}>
                                    {record.quote}
                                </Paragraph>
                                {record.is_featured ? <Tag color="gold">Nổi bật</Tag> : null}
                            </Space>
                        </Space>
                    ),
                },
                { title: 'Trạng thái', dataIndex: 'status', key: 'status', render: renderStatusTag },
                { title: 'Thứ tự', dataIndex: 'sort_order', key: 'sort_order' },
                { title: 'Tác vụ', key: 'actions', render: (_, record) => renderActions(record) },
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
                            <CmsMediaThumbnail src={record.featured_image_url} alt={record.featured_image_alt || value} size={56} />
                            <Space direction="vertical" size={2} align="start">
                                <Button type="link" style={{ paddingInline: 0, height: 'auto' }} onClick={() => openEditModal(record)}>
                                    <Text strong style={{ color: '#1677ff' }}>{value}</Text>
                                </Button>
                                <Text type="secondary">{record.summary || record.slug || 'Chưa có mô tả ngắn'}</Text>
                            </Space>
                        </Space>
                    ),
                },
                { title: 'Danh mục', dataIndex: 'category_name', key: 'category_name', render: (value) => value || 'Chưa phân loại' },
                {
                    title: 'Trạng thái',
                    key: 'status',
                    render: (_, record) => (
                        <Space size={[4, 4]} wrap>
                            {renderStatusTag(record.status)}
                            {record.is_featured ? <Tag color="gold">Nổi bật</Tag> : null}
                            {record.is_highlight ? <Tag color="green">Ưu tiên</Tag> : null}
                        </Space>
                    ),
                },
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
                                    {renderStatusTag(record._translation_status)}
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
                {
                    title: 'Mã đơn',
                    dataIndex: 'order_code',
                    key: 'order_code',
                    render: (value, record) => (
                        <Space direction="vertical" size={4}>
                            <Text strong={!record.is_read}>{value}</Text>
                            {!record.is_read ? <Tag color="red">Chưa đọc</Tag> : <Tag>Đã đọc</Tag>}
                        </Space>
                    ),
                },
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
                {
                    title: 'Trạng thái',
                    key: 'translation_status',
                    render: (_, record) => renderStatusTag(record._translation_status),
                },
                {
                    title: 'Tiến độ dịch',
                    key: 'translation_progress',
                    render: (_, record) => {
                        const progress = record._translation_progress;

                        return progress
                            ? `${progress.translated}/${progress.total}`
                            : '-';
                    },
                },
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
                title: 'Ảnh',
                key: 'media',
                render: (_, record) => (
                    <Space>
                        <CmsMediaThumbnail src={resolveRecordImageUrl(record)} alt={record.title} size={56} />
                        <Space direction="vertical" size={0}>
                            <Text strong>{record.title}</Text>
                            <Text type="secondary">{record.alt_text || record.mime_type || 'Media asset'}</Text>
                        </Space>
                    </Space>
                ),
            },
            { title: 'Dung lượng', dataIndex: 'size', key: 'size', render: formatBytes },
            {
                title: 'Tác vụ',
                key: 'actions',
                render: (_, record) => renderActions(record),
            },
        ];
    }, [contentLocale, sectionKey, sectionPermissions.canDelete, sectionPermissions.canPublish, sectionPermissions.canUpdate]);

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
                        defaultLocale={data?.default_locale ?? frontendLocale}
                        sourceLocale={data?.source_locale ?? 'vi'}
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
                        translationMode={contentLocale !== contentSourceLocale}
                        editingPost={editingRecord}
                        mediaOptions={data?.media ?? []}
                        categoryOptions={data?.categories ?? []}
                        localeOptions={contentLocaleOptions}
                        contentLocale={contentLocale}
                        sourceLocale={contentSourceLocale}
                        callAdminApi={callAdminApi}
                        onCancel={() => setModalOpen(false)}
                        onSubmit={handleSaveRecord}
                        onLocaleChange={handleFormLocaleChange}
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
                        translationMode={contentLocale !== contentSourceLocale}
                        editingService={editingRecord}
                        mediaOptions={data?.media ?? []}
                        categoryOptions={serviceCategoryOptions}
                        localeOptions={contentLocaleOptions}
                        contentLocale={contentLocale}
                        sourceLocale={contentSourceLocale}
                        callAdminApi={callAdminApi}
                        onCancel={() => setModalOpen(false)}
                        onSubmit={handleSaveRecord}
                        onLocaleChange={handleFormLocaleChange}
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
                        translationMode={contentLocale !== contentSourceLocale}
                        editingProject={editingRecord}
                        mediaOptions={data?.media ?? []}
                        categoryOptions={projectCategoryOptions}
                        localeOptions={contentLocaleOptions}
                        contentLocale={contentLocale}
                        sourceLocale={contentSourceLocale}
                        callAdminApi={callAdminApi}
                        onCancel={() => setModalOpen(false)}
                        onSubmit={handleSaveRecord}
                        onLocaleChange={handleFormLocaleChange}
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
                        translationMode={contentLocale !== contentSourceLocale}
                        editingTestimonial={editingRecord}
                        mediaOptions={data?.media ?? []}
                        localeOptions={contentLocaleOptions}
                        contentLocale={contentLocale}
                        sourceLocale={contentSourceLocale}
                        callAdminApi={callAdminApi}
                        onCancel={() => setModalOpen(false)}
                        onSubmit={handleSaveRecord}
                        onLocaleChange={handleFormLocaleChange}
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
                        translationMode={contentLocale !== contentSourceLocale}
                        editingMember={editingRecord}
                        mediaOptions={data?.media ?? []}
                        localeOptions={contentLocaleOptions}
                        contentLocale={contentLocale}
                        sourceLocale={contentSourceLocale}
                        callAdminApi={callAdminApi}
                        onCancel={() => setModalOpen(false)}
                        onSubmit={handleSaveRecord}
                        onLocaleChange={handleFormLocaleChange}
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
                        translationMode={contentLocale !== contentSourceLocale}
                        editingPartner={editingRecord}
                        mediaOptions={data?.media ?? []}
                        localeOptions={contentLocaleOptions}
                        contentLocale={contentLocale}
                        sourceLocale={contentSourceLocale}
                        callAdminApi={callAdminApi}
                        onCancel={() => setModalOpen(false)}
                        onSubmit={handleSaveRecord}
                        onLocaleChange={handleFormLocaleChange}
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
                        translationMode={contentLocale !== contentSourceLocale}
                        editingProduct={editingRecord}
                        categoryOptions={productCategoryOptions}
                        localeOptions={contentLocaleOptions}
                        contentLocale={contentLocale}
                        sourceLocale={contentSourceLocale}
                        allowLocaleSwitchOnCreate
                        callAdminApi={callAdminApi}
                        onCancel={() => setModalOpen(false)}
                        onSubmit={handleSaveRecord}
                        onLocaleChange={handleFormLocaleChange}
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
                        translationMode={contentLocale !== contentSourceLocale}
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
                        canPublish={sectionPermissions.canPublish}
                        translationMode={contentLocale !== contentSourceLocale}
                        editingMenu={editingRecord}
                        localeOptions={contentLocaleOptions}
                        contentLocale={contentLocale}
                        sourceLocale={contentSourceLocale}
                        locationOptions={data?.locations ?? []}
                        linkOptions={data?.linkOptions ?? {}}
                        callAdminApi={callAdminApi}
                        runAdminAction={runAdminAction}
                        onLocationsChanged={reload}
                        onCancel={() => setModalOpen(false)}
                        onSubmit={handleSaveRecord}
                        onLocaleChange={handleFormLocaleChange}
                    />
                </Suspense>
            );
        }

        return (
            <Suspense fallback={null}>
                <CmsPageFormModal
                    open={modalOpen}
                    canManage={sectionPermissions.canCreate || sectionPermissions.canUpdate}
                    canPublish={sectionPermissions.canPublish}
                    editingPage={editingRecord}
                    localeOptions={data?.locales ?? []}
                    contentLocale={contentLocale}
                    sourceLocale={contentSourceLocale}
                    mediaOptions={data?.media ?? []}
                    callAdminApi={callAdminApi}
                    onCancel={() => setModalOpen(false)}
                    onSubmit={handleSaveRecord}
                    onTransition={handlePageTranslationTransition}
                />
            </Suspense>
        );
    };

    const handleOrderDatePresetChange = (preset) => {
        setOrderDatePreset(preset);
        setOrderDateRange(resolveOrderDatePresetRange(preset));
    };

    const orderTableFilters = (
        <Space wrap size={8} style={{ justifyContent: 'flex-end' }}>
            <Input
                allowClear
                value={keyword}
                onChange={(event) => setKeyword(event.target.value)}
                placeholder="Tìm theo mã đơn, khách hàng, điện thoại..."
                style={{ width: 300 }}
            />
            <Select
                value={orderStatusFilter}
                onChange={setOrderStatusFilter}
                options={orderStatusOptions}
                style={{ width: 160 }}
            />
            <Select
                value={orderDatePreset}
                onChange={handleOrderDatePresetChange}
                options={orderDatePresetOptions}
                style={{ width: 140 }}
            />
            <RangePicker
                value={orderDateRange}
                onChange={(nextRange) => {
                    setOrderDateRange(nextRange);
                    setOrderDatePreset('custom');
                }}
                format="DD/MM/YYYY"
                placeholder={['Từ ngày', 'Đến ngày']}
                allowClear
                style={{ width: 240 }}
            />
        </Space>
    );

    const localeEditor = supportsLocalizedList ? (
        <Space wrap>
            <Text type="secondary">Ngôn ngữ nội dung</Text>
            <Select
                value={contentLocale}
                onChange={setContentLocale}
                style={{ minWidth: 170 }}
                options={contentLocaleOptions.map((locale) => ({
                    value: locale.code,
                    label: `${locale.native_name || locale.name || locale.code}${locale.is_source ? ' · Gốc' : ''}`,
                }))}
            />
        </Space>
    ) : null;

    const tableExtra = sectionKey === 'cms-orders'
        ? orderTableFilters
        : sectionKey === 'cms-media'
            ? (
                <Space wrap>
                    <Input
                        allowClear
                        value={keyword}
                        onChange={(event) => setKeyword(event.target.value)}
                        placeholder="Tìm theo tên file, alt text, loại file..."
                        style={{ width: 320 }}
                    />
                    <Button icon={<FolderOutlined />} disabled={!sectionPermissions.canCreate} onClick={handleCreateMediaFolder}>
                        Tạo thư mục
                    </Button>
                    <Button type="primary" icon={<UploadOutlined />} disabled={!sectionPermissions.canCreate} onClick={openMediaUploadModal}>
                        Upload media
                    </Button>
                </Space>
            )
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
            : sectionKey === 'cms-projects'
                ? (
                    <Space wrap>
                        <Button onClick={openProjectCategoryManager}>Cài đặt danh mục dự án</Button>
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

    const renderMediaFolderButton = (folder) => {
        const isActive = activeMediaFolder === folder.path;
        const count = mediaFolderCounts.get(folder.path) ?? 0;
        const isManagedFolder = Number.isInteger(Number(folder.id));

        const folderButton = (
            <div
                key={folder.path}
                title={isManagedFolder ? 'Nhấp chuột phải để đổi tên hoặc xóa thư mục' : undefined}
                onDragOver={(event) => event.preventDefault()}
                onDrop={(event) => {
                    event.preventDefault();
                    const mediaId = Number(event.dataTransfer.getData('application/x-cms-media-id'));
                    const record = mediaItems.find((item) => Number(item.id) === mediaId);
                    if (record) updateMediaFolder(record, folder.path === 'uncategorized' ? null : folder.path);
                }}
                style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 4,
                    width: '100%',
                    minHeight: 42,
                    padding: '0 6px 0 12px',
                    border: isActive ? '1px solid #0f766e' : '1px solid #e7ecef',
                    borderRadius: 12,
                    background: isActive ? '#ecfdf5' : '#fff',
                    color: isActive ? '#0f766e' : '#344054',
                    fontWeight: 700,
                }}
            >
                <button
                    type="button"
                    onClick={() => setActiveMediaFolder(folder.path)}
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        minWidth: 0,
                        flex: 1,
                        padding: 0,
                        border: 0,
                        background: 'transparent',
                        color: 'inherit',
                        font: 'inherit',
                        cursor: 'pointer',
                        textAlign: 'left',
                    }}
                >
                    {isActive ? <FolderOpenOutlined /> : <FolderOutlined />}
                    <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{folder.name}</span>
                </button>
                <Tag color={isActive ? 'green' : 'default'}>{count}</Tag>
            </div>
        );

        if (!isManagedFolder) {
            return folderButton;
        }

        return (
            <Dropdown
                key={folder.path}
                trigger={['contextMenu']}
                menu={{
                    items: [
                        {
                            key: 'rename',
                            icon: <EditOutlined />,
                            label: 'Đổi tên thư mục',
                            disabled: !sectionPermissions.canUpdate,
                        },
                        { type: 'divider' },
                        {
                            key: 'delete',
                            icon: <DeleteOutlined />,
                            label: 'Xóa thư mục',
                            danger: true,
                            disabled: !sectionPermissions.canDelete,
                        },
                    ],
                    onClick: ({ key }) => {
                        if (key === 'rename') handleRenameMediaFolder(folder);
                        if (key === 'delete') handleDeleteMediaFolder(folder);
                    },
                }}
            >
                {folderButton}
            </Dropdown>
        );
    };

    const renderMediaLibrary = () => (
        <Row gutter={[16, 16]} align="top">
            <Col xs={24} lg={6} xl={5}>
                <Card size="small" title="Thư mục" extra={<Button size="small" type="link" onClick={handleCreateMediaFolder}>Tạo</Button>}>
                    <Space direction="vertical" size={8} style={{ width: '100%' }}>
                        {renderMediaFolderButton({ name: 'Tất cả media', path: 'all' })}
                        {renderMediaFolderButton({ name: 'Chưa phân loại', path: 'uncategorized' })}
                        {mediaFolders.map(renderMediaFolderButton)}
                    </Space>
                    <Alert
                        type="info"
                        showIcon
                        style={{ marginTop: 14 }}
                        message="Nhấp chuột phải vào thư mục để đổi tên hoặc xóa. URL ảnh không thay đổi."
                    />
                </Card>
            </Col>
            <Col xs={24} lg={18} xl={19}>
                <Space direction="vertical" size={14} style={{ width: '100%' }}>
                    <Card size="small">
                        <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
                            <Space direction="vertical" size={0}>
                                <Text strong>Phạm vi media</Text>
                                <Text type="secondary">
                                    Mặc định chỉ hiện media của website đang quản trị. Bật show toàn bộ để copy URL ảnh từ website khác khi cần.
                                </Text>
                            </Space>
                            <Space wrap>
                                <Tag color="blue">{data?.current_website_key ?? 'website-main'}</Tag>
                                {(data?.unused_total ?? 0) > 0 ? <Tag color="warning">{data.unused_total} file chưa dùng</Tag> : <Tag color="green">Không có file thừa</Tag>}
                                <Button
                                    type={mediaShowAll ? 'primary' : 'default'}
                                    onClick={() => {
                                        setMediaShowAll((current) => !current);
                                        setActiveMediaFolder('all');
                                    }}
                                >
                                    {mediaShowAll ? 'Đang show toàn bộ' : 'Show toàn bộ'}
                                </Button>
                            </Space>
                        </Space>
                    </Card>

                    <div
                        onDragOver={(event) => {
                            event.preventDefault();
                            if (sectionPermissions.canCreate) setMediaDragActive(true);
                        }}
                        onDragLeave={() => setMediaDragActive(false)}
                        onDrop={(event) => {
                            event.preventDefault();
                            setMediaDragActive(false);
                            const files = Array.from(event.dataTransfer.files ?? []).filter((file) => file.type.startsWith('image/'));
                            if (!files.length || !sectionPermissions.canCreate) return;
                            setMediaFiles(files);
                            setMediaUpload((current) => ({
                                ...current,
                                folder_path: activeMediaFolder === 'all' || activeMediaFolder === 'uncategorized' ? null : activeMediaFolder,
                            }));
                            setMediaUploadOpen(true);
                        }}
                        style={{
                            display: 'grid',
                            placeItems: 'center',
                            minHeight: 118,
                            border: mediaDragActive ? '2px solid #0f766e' : '1px dashed #b7c4c2',
                            borderRadius: 18,
                            background: mediaDragActive ? '#ecfdf5' : '#f8faf9',
                            color: '#475467',
                            textAlign: 'center',
                            transition: 'border-color .18s ease, background .18s ease',
                        }}
                    >
                        <Space direction="vertical" size={4} align="center">
                            <InboxOutlined style={{ fontSize: 28, color: '#0f766e' }} />
                            <Text strong>Kéo thả ảnh vào đây để upload nhanh</Text>
                            <Text type="secondary">Có thể chọn nhiều ảnh cùng lúc. Ảnh sẽ vào thư mục đang mở.</Text>
                        </Space>
                    </div>

                    {filteredItems.length ? (
                        <Space direction="vertical" size={18} style={{ width: '100%' }}>
                            <Row gutter={[14, 14]}>
                                {paginatedMediaItems.map((record) => (
                                    <Col key={record.id} xs={24} sm={12} md={8} xl={6} xxl={4}>
                                        <Dropdown menu={mediaActionMenu(record)} trigger={['contextMenu']}>
                                            <Card
                                            hoverable
                                            size="small"
                                            draggable={record?.is_current_website !== false}
                                            onDragStart={(event) => event.dataTransfer.setData('application/x-cms-media-id', String(record.id))}
                                            cover={(
                                                <div style={{ height: 150, overflow: 'hidden', background: '#f4f7f6' }}>
                                                    <CmsMediaThumbnail src={record.file_url} alt={record.title} size="100%" radius={0} />
                                                </div>
                                            )}
                                            actions={[
                                                <Button
                                                    key="copy"
                                                    size="small"
                                                    type="primary"
                                                    ghost
                                                    icon={<CopyOutlined />}
                                                    onClick={() => handleCopyMediaUrl(record)}
                                                >
                                                    Copy
                                                </Button>,
                                                <Button
                                                    key="edit"
                                                    size="small"
                                                    icon={<EditOutlined />}
                                                    disabled={!sectionPermissions.canUpdate || record?.is_current_website === false}
                                                    onClick={() => {
                                                        if (record?.is_current_website === false) {
                                                            messageApi.warning('Media này thuộc website khác. Bạn chỉ nên copy URL để dùng chung.');

                                                            return;
                                                        }

                                                        openEditMediaTitle(record);
                                                    }}
                                                >
                                                    Sửa
                                                </Button>,
                                            ]}
                                            style={{ overflow: 'hidden' }}
                                        >
                                            <Space direction="vertical" size={2} style={{ width: '100%' }}>
                                                <Text strong ellipsis title={record.title}>{record.title || `Media #${record.id}`}</Text>
                                                <Text type="secondary" ellipsis title={record.alt_text || record.file_url}>{record.alt_text || record.mime_type || 'Media asset'}</Text>
                                                <Space size={6} wrap>
                                                    <Tag>{formatBytes(record.size)}</Tag>
                                                    {record.website_key ? <Tag color={record.is_current_website ? 'blue' : 'purple'}>{record.website_key}</Tag> : null}
                                                    {record.is_unused ? <Tag color="warning">Chưa dùng</Tag> : <Tag color="green">{record.usage_count} nơi dùng</Tag>}
                                                    {record.folder_path ? <Tag color="green">{mediaFolders.find((folder) => folder.path === record.folder_path)?.name || record.folder_path}</Tag> : <Tag>Gốc</Tag>}
                                                </Space>
                                            </Space>
                                            </Card>
                                        </Dropdown>
                                    </Col>
                                ))}
                            </Row>
                            <Pagination
                                current={mediaPagination.current}
                                pageSize={mediaPagination.pageSize}
                                total={filteredItems.length}
                                showSizeChanger
                                pageSizeOptions={[30, 60, 90]}
                                showTotal={(total, range) => `${range[0]}-${range[1]} / ${total} media`}
                                onChange={(current, pageSize) => setMediaPagination({ current, pageSize })}
                                style={{ alignSelf: 'flex-end' }}
                            />
                        </Space>
                    ) : (
                        <Empty description="Chưa có media trong thư mục này." />
                    )}
                </Space>
            </Col>
        </Row>
    );

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

            <Card
                className="admin-table-card"
                title={`${sectionConfig.title} (${sectionKey === 'cms-orders' || sectionKey === 'cms-media' || sectionKey === 'cms-services' ? filteredItems.length : (data?.total ?? 0)})`}
                extra={localeEditor || tableExtra ? <Space wrap>{localeEditor}{tableExtra}</Space> : null}
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
                                        pagination={{
                                            current: productPagination.current,
                                            pageSize: productPagination.pageSize,
                                            total: filteredItems.length,
                                            showSizeChanger: true,
                                            pageSizeOptions: ['10', '20', '50', '100'],
                                            showTotal: (total, range) => `${range[0]}-${range[1]} / ${total} sản phẩm`,
                                            onChange: (current, pageSize) => {
                                                setProductPagination({ current, pageSize });
                                            },
                                        }}
                                        scroll={{ x: 980 }}
                                    />
                                ) : (
                                    <Empty description={`Chưa có dữ liệu cho ${sectionConfig.title}.`} />
                                )}
                            </Space>
                        </Col>
                    </Row>
                ) : null}

                {sectionKey === 'cms-services' ? (
                    <Row gutter={[16, 16]} align="top">
                        <Col xs={24} xl={7}>
                            <Card size="small" title="Tìm kiếm dịch vụ" className="admin-table-filters">
                                <Space direction="vertical" size={12} style={{ width: '100%' }}>
                                    <Space direction="vertical" size={4} style={{ width: '100%' }}>
                                        <Text strong>Từ khóa</Text>
                                        <Input
                                            allowClear
                                            value={keyword}
                                            onChange={(event) => setKeyword(event.target.value)}
                                            placeholder="Tìm theo tên, slug, danh mục, mô tả..."
                                        />
                                    </Space>
                                    <Space direction="vertical" size={4} style={{ width: '100%' }}>
                                        <Text strong>Danh mục</Text>
                                        <Select
                                            value={serviceCategoryFilter}
                                            onChange={setServiceCategoryFilter}
                                            options={[{ label: 'Tất cả danh mục', value: 'all' }, ...serviceCategoryOptions]}
                                            style={{ width: '100%' }}
                                        />
                                    </Space>
                                    <Space direction="vertical" size={4} style={{ width: '100%' }}>
                                        <Text strong>Trạng thái</Text>
                                        <Select
                                            value={serviceStatusFilter}
                                            onChange={setServiceStatusFilter}
                                            options={[
                                                { label: 'Tất cả trạng thái', value: 'all' },
                                                { label: 'Đã xuất bản', value: 'published' },
                                                { label: 'Bản nháp', value: 'draft' },
                                            ]}
                                            style={{ width: '100%' }}
                                        />
                                    </Space>
                                    <Space direction="vertical" size={4} style={{ width: '100%' }}>
                                        <Text strong>Nổi bật</Text>
                                        <Select
                                            value={serviceFeaturedFilter}
                                            onChange={setServiceFeaturedFilter}
                                            options={[
                                                { label: 'Tất cả', value: 'all' },
                                                { label: 'Nổi bật', value: 'featured' },
                                                { label: 'Thường', value: 'normal' },
                                            ]}
                                            style={{ width: '100%' }}
                                        />
                                    </Space>
                                    <Button
                                        block
                                        onClick={() => {
                                            setKeyword('');
                                            setServiceCategoryFilter('all');
                                            setServiceStatusFilter('all');
                                            setServiceFeaturedFilter('all');
                                        }}
                                    >
                                        Xóa bộ lọc
                                    </Button>
                                </Space>
                            </Card>
                        </Col>
                        <Col xs={24} xl={17}>
                            <Space direction="vertical" size={12} style={{ width: '100%' }}>
                                {serviceBulkActions}
                                {filteredItems.length ? (
                                    <Table
                                        rowKey="id"
                                        rowSelection={serviceRowSelection}
                                        columns={columns}
                                        dataSource={filteredItems}
                                        pagination={{ pageSize: 10, hideOnSinglePage: true }}
                                    />
                                ) : (
                                    <Empty description="Không có dịch vụ phù hợp với bộ lọc." />
                                )}
                            </Space>
                        </Col>
                    </Row>
                ) : null}

                {sectionKey === 'cms-media' ? renderMediaLibrary() : null}

                {sectionKey !== 'cms-products' && sectionKey !== 'cms-services' && sectionKey !== 'cms-media' && filteredItems.length ? (
                    <Space direction="vertical" size={12} style={{ width: '100%' }}>
                        {sectionKey === 'cms-pages' ? pageBulkActions : null}
                        {sectionKey === 'cms-orders' ? orderBulkActions : null}
                        {['cms-posts', 'cms-projects'].includes(sectionKey) ? contentBulkActions : null}
                        {['cms-partners', 'cms-team-members', 'cms-testimonials'].includes(sectionKey) ? featuredBulkActions : null}
                        <Table
                            rowKey="id"
                            rowSelection={sectionKey === 'cms-orders'
                                ? orderRowSelection
                                : sectionKey === 'cms-pages'
                                    ? pageRowSelection
                                : sectionKey === 'cms-services'
                                    ? serviceRowSelection
                                    : sectionKey === 'cms-posts'
                                        ? postRowSelection
                                        : sectionKey === 'cms-projects'
                                            ? projectRowSelection
                                            : sectionKey === 'cms-team-members'
                                                ? teamMemberRowSelection
                                                : sectionKey === 'cms-testimonials'
                                                    ? testimonialRowSelection
                                                    : partnerRowSelection}
                            columns={columns}
                            dataSource={filteredItems}
                            pagination={{ pageSize: 10, hideOnSinglePage: true }}
                            scroll={{ x: 980 }}
                            onRow={sectionKey === 'cms-orders' ? (record) => ({
                                onClick: () => openOrderDetails(record),
                                style: {
                                    cursor: 'pointer',
                                    background: record.is_read ? undefined : '#fffbe6',
                                },
                            }) : undefined}
                        />
                    </Space>
                ) : sectionKey !== 'cms-products' && sectionKey !== 'cms-services' && sectionKey !== 'cms-media' ? (
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
                    onChanged={refreshCurrentSectionDataSilently}
                />
            </Suspense>

            <Modal
                title={`Đổi trạng thái ${selectedOrderRowKeys.length} đơn hàng`}
                open={sectionKey === 'cms-orders' && bulkOrderStatusOpen}
                onCancel={() => {
                    setBulkOrderStatusOpen(false);
                    bulkOrderStatusForm.resetFields();
                }}
                onOk={handleBulkOrderStatus}
                okText="Áp dụng"
                cancelText="Hủy"
                destroyOnHidden
            >
                <Form form={bulkOrderStatusForm} layout="vertical">
                    <Alert
                        type="info"
                        showIcon
                        style={{ marginBottom: 16 }}
                        message={`Trạng thái mới sẽ được áp dụng cho ${selectedOrderRowKeys.length} đơn hàng đang chọn.`}
                    />
                    <Form.Item
                        name="status"
                        label="Trạng thái đơn hàng"
                        rules={[{ required: true, message: 'Chọn trạng thái cần áp dụng' }]}
                    >
                        <Radio.Group
                            optionType="button"
                            buttonStyle="solid"
                            options={orderStatusOptions.filter((option) => option.value !== 'all')}
                        />
                    </Form.Item>
                </Form>
            </Modal>

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
                title="Upload media"
                open={sectionKey === 'cms-media' && mediaUploadOpen}
                onCancel={() => {
                    setMediaUploadOpen(false);
                    setMediaFiles([]);
                    setMediaUpload({ title: '', alt_text: '', folder_path: null });
                }}
                onOk={handleUploadMedia}
                okText={`Upload ${mediaFiles.length || ''} media`}
                okButtonProps={{ disabled: !sectionPermissions.canCreate || !mediaFiles.length }}
                cancelText="Hủy"
                destroyOnHidden
            >
                <Space direction="vertical" size={12} style={{ width: '100%' }}>
                    <Select
                        allowClear
                        value={mediaUpload.folder_path}
                        onChange={(value) => setMediaUpload((current) => ({ ...current, folder_path: value ?? null }))}
                        placeholder="Chọn thư mục"
                        options={mediaFolderOptions}
                    />
                    <Input
                        value={mediaUpload.title}
                        onChange={(event) => setMediaUpload((current) => ({ ...current, title: event.target.value }))}
                        placeholder="Tiêu đề media. Nếu bỏ trống sẽ lấy theo tên file."
                    />
                    <Input
                        value={mediaUpload.alt_text}
                        onChange={(event) => setMediaUpload((current) => ({ ...current, alt_text: event.target.value }))}
                        placeholder="Alt text"
                    />
                    <Dragger
                        accept="image/*"
                        multiple
                        beforeUpload={() => false}
                        fileList={mediaFiles.map((file, index) => ({
                            uid: `${file.name}-${file.lastModified}-${index}`,
                            name: file.name,
                            status: 'done',
                            originFileObj: file,
                        }))}
                        onChange={({ fileList }) => {
                            setMediaFiles(fileList.map((item) => item.originFileObj).filter(Boolean));
                        }}
                        onRemove={(file) => {
                            setMediaFiles((current) => current.filter((item) => item !== file.originFileObj));
                        }}
                    >
                        <p className="ant-upload-drag-icon"><InboxOutlined /></p>
                        <p className="ant-upload-text">Kéo thả ảnh vào đây hoặc bấm để chọn ảnh</p>
                        <p className="ant-upload-hint">Có thể upload nhiều ảnh cùng lúc. URL ảnh sau khi upload không thay đổi theo thư mục.</p>
                    </Dragger>
                    {mediaFiles.length ? (
                        <Alert
                            type="success"
                            showIcon
                            message={`Đã chọn ${mediaFiles.length} file`}
                            description={mediaFiles.slice(0, 5).map((file) => file.name).join(', ')}
                        />
                    ) : null}
                </Space>
            </Modal>

            <Modal
                title={`Sửa thông tin media · ${contentLocale.toUpperCase()}`}
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
                    <Form.Item
                        name="folder_path"
                        label="Thư mục"
                        style={{ marginTop: 16, marginBottom: 0 }}
                        hidden={contentLocale !== contentSourceLocale}
                    >
                        <Select allowClear placeholder="Thư mục gốc" options={mediaFolderOptions} />
                    </Form.Item>
                </Form>
            </Modal>

            <Modal
                title={`Điều chỉnh tồn kho cho ${selectedProductRowKeys.length} sản phẩm`}
                open={sectionKey === 'cms-products' && bulkProductStockOpen}
                onCancel={() => {
                    setBulkProductStockOpen(false);
                    bulkProductStockForm.resetFields();
                }}
                onOk={handleBulkStockProducts}
                okText="Cập nhật tồn kho"
                cancelText="Hủy"
                destroyOnHidden
            >
                <Form form={bulkProductStockForm} layout="vertical">
                    <Alert
                        type="info"
                        showIcon
                        style={{ marginBottom: 16 }}
                        message={`Tồn kho mới sẽ được áp dụng cho ${selectedProductRowKeys.length} sản phẩm đang chọn.`}
                    />
                    <Form.Item
                        name="stock"
                        label="Tồn kho mới"
                        rules={[
                            { required: true, message: 'Nhập tồn kho mới' },
                            { type: 'number', min: 0, message: 'Tồn kho phải lớn hơn hoặc bằng 0' },
                        ]}
                    >
                        <InputNumber
                            min={0}
                            precision={0}
                            style={{ width: '100%' }}
                            placeholder="Ví dụ: 1000"
                        />
                    </Form.Item>
                </Form>
            </Modal>

            <Modal
                title={`Active / unactive ${selectedProductRowKeys.length} sản phẩm`}
                open={sectionKey === 'cms-products' && bulkProductActiveOpen}
                onCancel={() => {
                    setBulkProductActiveOpen(false);
                    bulkProductActiveForm.resetFields();
                }}
                onOk={handleBulkActiveProducts}
                okText="Áp dụng"
                cancelText="Hủy"
                destroyOnHidden
            >
                <Form form={bulkProductActiveForm} layout="vertical">
                    <Alert
                        type="info"
                        showIcon
                        style={{ marginBottom: 16 }}
                        message={`Trạng thái mới sẽ được áp dụng cho ${selectedProductRowKeys.length} sản phẩm đang chọn.`}
                    />
                    <Form.Item
                        name="is_active"
                        label="Trạng thái sản phẩm"
                        rules={[{ required: true, message: 'Chọn trạng thái cần áp dụng' }]}
                    >
                        <Radio.Group
                            optionType="button"
                            buttonStyle="solid"
                            options={[
                                { label: 'Active sản phẩm', value: 'true' },
                                { label: 'Unactive sản phẩm', value: 'false' },
                            ]}
                        />
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
                    <Form.Item
                        name="stock"
                        label="Tồn kho mới"
                        extra="Để trống nếu muốn giữ nguyên tồn kho hiện tại."
                        rules={[{ type: 'number', min: 0, message: 'Tồn kho phải lớn hơn hoặc bằng 0' }]}
                    >
                        <InputNumber
                            min={0}
                            precision={0}
                            style={{ width: '100%' }}
                            placeholder="Nhập tồn kho muốn áp dụng"
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

            <Modal
                title={`Đổi danh mục ${selectedServiceRowKeys.length} dịch vụ`}
                open={sectionKey === 'cms-services' && bulkServiceCategoryOpen}
                onCancel={() => {
                    setBulkServiceCategoryOpen(false);
                    bulkServiceCategoryForm.resetFields();
                }}
                onOk={handleBulkServiceCategory}
                okText="Lưu thay đổi"
                cancelText="Hủy"
                destroyOnHidden
            >
                <Form form={bulkServiceCategoryForm} layout="vertical">
                    <Alert
                        type="info"
                        showIcon
                        style={{ marginBottom: 16 }}
                        message={`Danh mục mới sẽ được áp dụng cho ${selectedServiceRowKeys.length} dịch vụ đang chọn.`}
                    />
                    <Form.Item
                        name="cms_service_category_id"
                        label="Danh mục dịch vụ"
                        rules={[{ required: true, message: 'Chọn danh mục cần áp dụng' }]}
                    >
                        <Select
                            showSearch
                            optionFilterProp="label"
                            options={[
                                { label: 'Bỏ danh mục', value: BULK_CLEAR_VALUE },
                                ...serviceCategoryOptions,
                            ]}
                            placeholder="Chọn danh mục dịch vụ"
                        />
                    </Form.Item>
                </Form>
            </Modal>

            <Modal
                title={`Đổi danh mục ${sectionKey === 'cms-posts' ? selectedPostRowKeys.length : selectedProjectRowKeys.length} ${sectionKey === 'cms-posts' ? 'bài viết' : 'dự án'}`}
                open={['cms-posts', 'cms-projects'].includes(sectionKey) && bulkContentCategoryOpen}
                onCancel={() => {
                    setBulkContentCategoryOpen(false);
                    bulkContentCategoryForm.resetFields();
                }}
                onOk={handleBulkContentCategory}
                okText="Áp dụng"
                cancelText="Hủy"
                destroyOnHidden
            >
                <Form form={bulkContentCategoryForm} layout="vertical">
                    <Alert
                        type="info"
                        showIcon
                        message={`Danh mục mới sẽ được áp dụng cho ${sectionKey === 'cms-posts' ? selectedPostRowKeys.length : selectedProjectRowKeys.length} ${sectionKey === 'cms-posts' ? 'bài viết' : 'dự án'} đang chọn.`}
                        style={{ marginBottom: 16 }}
                    />
                    <Form.Item name="category_id" label="Danh mục">
                        <Select
                            allowClear
                            placeholder="Chọn danh mục"
                            options={(data?.categories ?? []).map((category) => ({
                                label: category.parent_name ? `${category.parent_name} / ${category.name}` : category.name,
                                value: category.id,
                            }))}
                        />
                    </Form.Item>
                </Form>
            </Modal>

            <Drawer
                title="Chi tiết trang"
                open={sectionKey === 'cms-pages' && Boolean(selectedPage)}
                onClose={() => setSelectedPage(null)}
                width="min(1000px, 100vw)"
                destroyOnHidden
                className="cms-page-detail-drawer"
                extra={selectedPage ? renderActions(selectedPage, { pageDrawer: true }) : null}
            >
                {selectedPage ? (
                    <Space direction="vertical" size={20} style={{ width: '100%' }}>
                        <section className="cms-page-detail-hero">
                            <div className="cms-page-detail-hero__copy">
                                <Space size={[8, 8]} wrap>
                                    {renderStatusTag(selectedPage.status)}
                                    {selectedPage.template ? <Tag color="blue">{selectedPage.template}</Tag> : null}
                                </Space>
                                <Title level={2}>{selectedPage.title}</Title>
                                <Paragraph>{selectedPage.excerpt || 'Trang chưa có mô tả ngắn.'}</Paragraph>
                            </div>
                            {selectedPage.featured_media_url ? (
                                <img
                                    src={selectedPage.featured_media_url}
                                    alt={selectedPage.title}
                                    className="cms-page-detail-hero__image"
                                />
                            ) : (
                                <div className="cms-page-detail-hero__placeholder">Chưa có ảnh đại diện</div>
                            )}
                        </section>

                        <Card size="small" title="Thông tin trang">
                            <div className="detail-grid detail-grid-2">
                                <div className="detail-tile">
                                    <Text className="detail-label">Slug</Text>
                                    <Text strong code>{selectedPage.slug || 'Chưa có'}</Text>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Thời gian xuất bản</Text>
                                    <Text strong>{formatPublishAt(selectedPage.publish_at)}</Text>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Public URL</Text>
                                    <Text strong copyable={selectedPage.public_url ? { text: selectedPage.public_url } : false} ellipsis>
                                        {selectedPage.public_url || 'Chưa có'}
                                    </Text>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">Preview URL</Text>
                                    <Text strong copyable={selectedPage.preview_url ? { text: selectedPage.preview_url } : false} ellipsis>
                                        {selectedPage.preview_url || 'Chưa có'}
                                    </Text>
                                </div>
                            </div>
                        </Card>

                        <Card size="small" title="SEO">
                            <div className="detail-grid detail-grid-2">
                                <div className="detail-tile">
                                    <Text className="detail-label">SEO title</Text>
                                    <Text strong>{selectedPage.meta_title || 'Chưa có'}</Text>
                                </div>
                                <div className="detail-tile">
                                    <Text className="detail-label">SEO description</Text>
                                    <Text strong>{selectedPage.meta_description || 'Chưa có'}</Text>
                                </div>
                                <div className="detail-tile detail-tile-wide">
                                    <Text className="detail-label">SEO keywords</Text>
                                    <Text strong>{selectedPage.meta_keywords || 'Chưa có'}</Text>
                                </div>
                            </div>
                        </Card>

                        <Card size="small" title="Nội dung trang" className="cms-page-detail-content-card">
                            {selectedPage.body ? (
                                <div className="cms-page-detail-content" dangerouslySetInnerHTML={{ __html: selectedPage.body }} />
                            ) : (
                                <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Trang chưa có nội dung." />
                            )}
                        </Card>
                    </Space>
                ) : null}
            </Drawer>

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
                extra={selectedProduct ? renderActions(selectedProduct, { productDrawer: true }) : null}
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
                onCancel={() => {
                    setContentLocale(contentSourceLocale);
                    setCategoryManagerOpen(false);
                }}
                footer={null}
                width={980}
                destroyOnHidden
            >
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
                        <Text type="secondary">Quản lý nhanh danh mục để gắn cho bài viết mà không cần rời màn Tin tức.</Text>
                        <Button type="primary" icon={<PlusOutlined />} disabled={!canManageCategories || contentLocale !== contentSourceLocale} onClick={openCreateCategory}>
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
                                        <Button size="small" danger icon={<DeleteOutlined />} disabled={!canManageCategories || contentLocale !== contentSourceLocale} onClick={() => handleDeleteCategory(record)}>
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
                    translationMode={contentLocale !== contentSourceLocale}
                    editingCategory={editingCategoryRecord}
                    parentOptions={categoryParentOptions}
                    localeOptions={contentLocaleOptions}
                    contentLocale={contentLocale}
                    sourceLocale={contentSourceLocale}
                    submitLoading={categorySaving}
                    onCancel={() => {
                        setContentLocale(contentSourceLocale);
                        loadCategoryItems({ showLoading: false, locale: contentSourceLocale });
                        setCategoryFormOpen(false);
                        setEditingCategoryRecord(emptyCategory);
                    }}
                    onSubmit={handleSaveCategory}
                    onLocaleChange={(nextLocale) => switchManagedCategoryLocale(
                        'cms_category',
                        categoryItems,
                        editingCategoryRecord,
                        setEditingCategoryRecord,
                        nextLocale,
                        emptyCategory,
                        (locale) => loadCategoryItems({ showLoading: false, locale }),
                    )}
                />
            </Suspense>

            <Modal
                title="Cài đặt danh mục dịch vụ"
                open={serviceCategoryManagerOpen}
                onCancel={() => {
                    setContentLocale(contentSourceLocale);
                    setServiceCategoryManagerOpen(false);
                }}
                footer={null}
                width={1040}
                destroyOnHidden
            >
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
                        <Text type="secondary">Quản lý danh mục dịch vụ ngay trong màn Services để tiện tạo, sửa và gắn danh mục.</Text>
                        <Button type="primary" icon={<PlusOutlined />} disabled={!sectionPermissions.canCreate || contentLocale !== contentSourceLocale} onClick={openCreateServiceCategory}>
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
                                        <Button size="small" danger icon={<DeleteOutlined />} disabled={!sectionPermissions.canDelete || contentLocale !== contentSourceLocale} onClick={() => handleDeleteServiceCategory(record)}>
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
                    translationMode={contentLocale !== contentSourceLocale}
                    editingCategory={editingServiceCategoryRecord}
                    categoryOptions={serviceCategoryParentOptions}
                    localeOptions={contentLocaleOptions}
                    contentLocale={contentLocale}
                    sourceLocale={contentSourceLocale}
                    entityLabel="danh mục dịch vụ"
                    callAdminApi={callAdminApi}
                    submitLoading={serviceCategorySaving}
                    onCancel={() => {
                        setContentLocale(contentSourceLocale);
                        loadServiceCategoryItems({ showLoading: false, locale: contentSourceLocale });
                        setServiceCategoryFormOpen(false);
                        setEditingServiceCategoryRecord(emptyServiceCategory);
                    }}
                    onSubmit={handleSaveServiceCategory}
                    onLocaleChange={(nextLocale) => switchManagedCategoryLocale(
                        'cms_service_category',
                        serviceCategoryItems,
                        editingServiceCategoryRecord,
                        setEditingServiceCategoryRecord,
                        nextLocale,
                        emptyServiceCategory,
                        (locale) => loadServiceCategoryItems({ showLoading: false, locale }),
                    )}
                />
            </Suspense>

            <Modal
                title="Cài đặt danh mục SP"
                open={productCategoryManagerOpen}
                onCancel={() => {
                    setContentLocale(contentSourceLocale);
                    setProductCategoryManagerOpen(false);
                }}
                footer={null}
                width={1040}
                destroyOnHidden
            >
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
                        <Text type="secondary">Quản lý danh mục sản phẩm ngay trong màn Products để tiện tạo, sửa và gắn danh mục.</Text>
                        <Button type="primary" icon={<PlusOutlined />} disabled={!sectionPermissions.canCreate || contentLocale !== contentSourceLocale} onClick={openCreateProductCategory}>
                            Thêm danh mục SP
                        </Button>
                    </Space>

                    <DndContext sensors={productCategorySensors} collisionDetection={closestCenter} onDragEnd={handleProductCategoryDragEnd}>
                        <SortableContext items={productCategoryItems.map((category) => `product-category-${category.id}`)} strategy={verticalListSortingStrategy}>
                            <Table
                                rowKey="id"
                                loading={productCategoryLoading}
                                dataSource={productCategoryItems}
                                pagination={false}
                                scroll={{ y: 520 }}
                                components={{
                                    body: {
                                        row: (rowProps) => (
                                            <SortableProductCategoryTableRow
                                                {...rowProps}
                                                disabled={!sectionPermissions.canUpdate || productCategoryLoading || contentLocale !== contentSourceLocale}
                                            />
                                        ),
                                    },
                                }}
                                columns={[
                            {
                                title: '',
                                key: 'drag',
                                width: 44,
                                render: () => (
                                    <Button
                                        type="text"
                                        size="small"
                                        icon={<HolderOutlined />}
                                        disabled={!sectionPermissions.canUpdate || productCategoryLoading || contentLocale !== contentSourceLocale}
                                        aria-label="Kéo thả để sắp xếp danh mục"
                                    />
                                ),
                            },
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
                                            <Text type="secondary">{record.description || 'Chưa có mô tả'}</Text>
                                        </Space>
                                    </Space>
                                ),
                            },
                            { title: 'Danh mục cha', dataIndex: 'parent_name', key: 'parent_name', render: (value) => value || '-' },
                            { title: 'Sản phẩm', dataIndex: 'products_count', key: 'products_count', render: (value) => value ?? 0 },
                            { title: 'Trạng thái', dataIndex: 'is_active', key: 'is_active', render: (value) => value ? <Tag color="green">Đang bật</Tag> : <Tag>Tắt</Tag> },
                            {
                                title: 'Tác vụ',
                                key: 'actions',
                                render: (_, record) => (
                                    <Space>
                                        <Button size="small" icon={<EditOutlined />} disabled={!sectionPermissions.canUpdate} onClick={() => openEditProductCategory(record)}>
                                            Sửa
                                        </Button>
                                        <Button size="small" danger icon={<DeleteOutlined />} disabled={!sectionPermissions.canDelete || contentLocale !== contentSourceLocale} onClick={() => handleDeleteProductCategory(record)}>
                                            Xóa
                                        </Button>
                                    </Space>
                                ),
                            },
                                ]}
                            />
                        </SortableContext>
                    </DndContext>
                </Space>
            </Modal>

            <Suspense fallback={null}>
                <CatalogCategoryFormModal
                    open={productCategoryFormOpen}
                    canManage={sectionPermissions.canCreate || sectionPermissions.canUpdate}
                    translationMode={contentLocale !== contentSourceLocale}
                    editingCategory={editingProductCategoryRecord}
                    categoryOptions={productCategoryParentOptions}
                    localeOptions={contentLocaleOptions}
                    contentLocale={contentLocale}
                    sourceLocale={contentSourceLocale}
                    entityLabel="danh mục sản phẩm"
                    callAdminApi={callAdminApi}
                    submitLoading={productCategorySaving}
                    onCancel={() => {
                        setContentLocale(contentSourceLocale);
                        loadProductCategoryItems({ silent: true, locale: contentSourceLocale });
                        setProductCategoryFormOpen(false);
                        setEditingProductCategoryRecord(emptyProductCategory);
                    }}
                    onSubmit={handleSaveProductCategory}
                    onLocaleChange={(nextLocale) => switchManagedCategoryLocale(
                        'catalog_category',
                        productCategoryItems,
                        editingProductCategoryRecord,
                        setEditingProductCategoryRecord,
                        nextLocale,
                        emptyProductCategory,
                        (locale) => loadProductCategoryItems({ silent: true, locale }),
                    )}
                />
            </Suspense>

            <Modal
                title="Cài đặt danh mục dự án"
                open={projectCategoryManagerOpen}
                onCancel={() => {
                    setContentLocale(contentSourceLocale);
                    setProjectCategoryManagerOpen(false);
                }}
                footer={null}
                width={1040}
                destroyOnHidden
            >
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
                        <Text type="secondary">Quản lý danh mục dự án ngay trong màn Projects để tiện tạo, sửa và gắn danh mục.</Text>
                        <Button type="primary" icon={<PlusOutlined />} disabled={!sectionPermissions.canCreate || contentLocale !== contentSourceLocale} onClick={openCreateProjectCategory}>
                            Thêm danh mục dự án
                        </Button>
                    </Space>

                    <Table
                        rowKey="id"
                        loading={projectCategoryLoading}
                        dataSource={projectCategoryItems}
                        pagination={{ pageSize: 8, hideOnSinglePage: true }}
                        columns={[
                            {
                                title: 'Danh mục dự án',
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
                            { title: 'Dự án', dataIndex: 'projects_count', key: 'projects_count', render: (value) => value ?? 0 },
                            { title: 'Thứ tự', dataIndex: 'sort_order', key: 'sort_order' },
                            { title: 'Trạng thái', dataIndex: 'is_active', key: 'is_active', render: (value) => value ? <Tag color="green">Đang bật</Tag> : <Tag>Tắt</Tag> },
                            {
                                title: 'Tác vụ',
                                key: 'actions',
                                render: (_, record) => (
                                    <Space>
                                        <Button size="small" icon={<EditOutlined />} disabled={!sectionPermissions.canUpdate} onClick={() => openEditProjectCategory(record)}>
                                            Sửa
                                        </Button>
                                        <Button size="small" danger icon={<DeleteOutlined />} disabled={!sectionPermissions.canDelete || contentLocale !== contentSourceLocale} onClick={() => handleDeleteProjectCategory(record)}>
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
                    open={projectCategoryFormOpen}
                    canManage={sectionPermissions.canCreate || sectionPermissions.canUpdate}
                    translationMode={contentLocale !== contentSourceLocale}
                    editingCategory={editingProjectCategoryRecord}
                    categoryOptions={projectCategoryParentOptions}
                    localeOptions={contentLocaleOptions}
                    contentLocale={contentLocale}
                    sourceLocale={contentSourceLocale}
                    entityLabel="danh mục dự án"
                    callAdminApi={callAdminApi}
                    submitLoading={projectCategorySaving}
                    onCancel={() => {
                        setContentLocale(contentSourceLocale);
                        loadProjectCategoryItems({ showLoading: false, locale: contentSourceLocale });
                        setProjectCategoryFormOpen(false);
                        setEditingProjectCategoryRecord(emptyProjectCategory);
                    }}
                    onSubmit={handleSaveProjectCategory}
                    onLocaleChange={(nextLocale) => switchManagedCategoryLocale(
                        'cms_project_category',
                        projectCategoryItems,
                        editingProjectCategoryRecord,
                        setEditingProjectCategoryRecord,
                        nextLocale,
                        emptyProjectCategory,
                        (locale) => loadProjectCategoryItems({ showLoading: false, locale }),
                    )}
                />
            </Suspense>

            {renderModal()}
        </Space>
    );
}

function SortableProductCategoryTableRow(props) {
    const { children, disabled, ...rowProps } = props;
    const rowKey = rowProps['data-row-key'];
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: `product-category-${rowKey}`,
        disabled,
    });

    const style = {
        ...rowProps.style,
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.72 : undefined,
        position: isDragging ? 'relative' : undefined,
        zIndex: isDragging ? 1 : undefined,
    };

    return (
        <tr
            {...rowProps}
            ref={setNodeRef}
            style={style}
            {...attributes}
            {...listeners}
        >
            {children}
        </tr>
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
