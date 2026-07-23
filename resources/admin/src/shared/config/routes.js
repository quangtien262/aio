const ADMIN_API_PREFIX = '/admin/api';

const resource = (path) => {
    const collection = `${ADMIN_API_PREFIX}${path}`;

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
        }),
    }),
});

export const STOREFRONT_ROUTES = Object.freeze({
    page: (slug) => `/p/${encodeURIComponent(String(slug ?? '').replace(/^\/+|\/+$/g, ''))}`,
});
