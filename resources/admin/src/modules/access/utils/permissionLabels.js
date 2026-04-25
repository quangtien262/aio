const ACRONYM_SEGMENTS = {
    api: 'API',
    cms: 'CMS',
    rbac: 'RBAC',
    seo: 'SEO',
    sms: 'SMS',
    ui: 'UI',
};

function formatSegment(segment) {
    const normalizedSegment = String(segment ?? '').trim().toLowerCase();

    if (ACRONYM_SEGMENTS[normalizedSegment]) {
        return ACRONYM_SEGMENTS[normalizedSegment];
    }

    return String(segment ?? '')
        .replace(/[-_]+/g, ' ')
        .replace(/\b\w/g, (character) => character.toUpperCase());
}

export function formatPermissionLabel(permissionKey) {
    return String(permissionKey ?? '')
        .split('.')
        .filter(Boolean)
        .map(formatSegment)
        .join(' ');
}