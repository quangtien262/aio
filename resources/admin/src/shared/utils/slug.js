export function toSlug(value, { trimEdges = true } = {}) {
    const slug = String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/gi, 'd')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-');

    return trimEdges ? slug.replace(/^-+|-+$/g, '') : slug.replace(/^-+/g, '');
}
