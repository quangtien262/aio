export const ADMIN_BASE_PATH = '/admin';
const ADMIN_API_PREFIX = `${ADMIN_BASE_PATH}/api`;

const normalizePath = (path = '') => String(path).replace(/^\/+/, '');

export const adminApi = (path = '') => {
    const normalizedPath = normalizePath(path);

    return normalizedPath ? `${ADMIN_API_PREFIX}/${normalizedPath}` : ADMIN_API_PREFIX;
};

export const adminPath = (path = '') => {
    const normalizedPath = normalizePath(path);

    return normalizedPath ? `${ADMIN_BASE_PATH}/${normalizedPath}` : ADMIN_BASE_PATH;
};

const resource = (path) => {
    const collection = adminApi(path);

    return Object.freeze({
        collection,
        item: (id) => `${collection}/${encodeURIComponent(id)}`,
    });
};

const cmsPages = resource('/cms/pages');

export const ADMIN_API_ROUTES = Object.freeze({
    cms: Object.freeze({
        pages: Object.freeze({
            ...cmsPages,
            bulk: `${cmsPages.collection}/bulk`,
            transition: (id, locale) => `${cmsPages.item(id)}/translations/${encodeURIComponent(locale)}/transition`,
        }),
    }),
});

export const STOREFRONT_ROUTES = Object.freeze({
    home: '/',
    landing: (slug) => `/land/${encodeURIComponent(String(slug ?? '').replace(/^\/+|\/+$/g, ''))}`,
    page: (slug) => `/p/${encodeURIComponent(String(slug ?? '').replace(/^\/+|\/+$/g, ''))}`,
    category: (slug) => `/danh-muc/${encodeURIComponent(String(slug ?? '').replace(/^\/+|\/+$/g, ''))}`,
    product: (slug) => `/san-pham/${encodeURIComponent(String(slug ?? '').replace(/^\/+|\/+$/g, ''))}`,
    blog: '/c',
    blogCategory: (slug) => `/c/${encodeURIComponent(String(slug ?? '').replace(/^\/+|\/+$/g, ''))}`,
    post: (slug) => `/n/${encodeURIComponent(String(slug ?? '').replace(/^\/+|\/+$/g, ''))}`,
    services: '/s',
    serviceCategory: (slug) => `/s/${encodeURIComponent(String(slug ?? '').replace(/^\/+|\/+$/g, ''))}`,
    service: (slug) => `/ser/${encodeURIComponent(String(slug ?? '').replace(/^\/+|\/+$/g, ''))}`,
    projects: '/pj',
    projectCategory: (slug) => `/pj/${encodeURIComponent(String(slug ?? '').replace(/^\/+|\/+$/g, ''))}`,
    project: (slug) => `/prj/${encodeURIComponent(String(slug ?? '').replace(/^\/+|\/+$/g, ''))}`,
    realEstate: '/bds',
    realEstateListing: (slug) => `/bds/${encodeURIComponent(String(slug ?? '').replace(/^\/+|\/+$/g, ''))}`,
    contact: '/contact',
    cart: '/gio-hang',
    checkout: '/thanh-toan',
    search: '/tim-kiem',
});
