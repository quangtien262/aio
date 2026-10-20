import { adminApi } from '../../../shared/config/routes.js';

const JOURNAL = 'aio.customers.commands.v1';
const memory = new Map();
const canonical = (value) => Array.isArray(value) ? value.map(canonical) : value && typeof value === 'object'
    ? Object.fromEntries(Object.keys(value).sort().map((key) => [key, canonical(value[key])])) : value;
const read = () => {
    try {
        const saved = JSON.parse(globalThis.localStorage?.getItem(JOURNAL) ?? '[]');
        if (Array.isArray(saved)) { memory.clear(); saved.forEach((entry) => memory.set(entry.hash, entry)); }
    } catch { /* Memory still protects this session when storage is unavailable. */ }
    return [...memory.values()];
};
const save = () => { try { globalThis.localStorage?.setItem(JOURNAL, JSON.stringify([...memory.values()])); } catch { /* No customer payload is persisted. */ } };
const locked = (callback) => globalThis.navigator?.locks ? globalThis.navigator.locks.request(JOURNAL, async () => callback()) : callback();
const digest = async (value) => {
    const bytes = await globalThis.crypto.subtle.digest('SHA-256', new TextEncoder().encode(JSON.stringify(canonical(value))));
    return [...new Uint8Array(bytes)].map((byte) => byte.toString(16).padStart(2, '0')).join('');
};
export const pendingCustomerCommands = (identity) => read().filter((entry) => entry.identity === identity);
export function createCustomersApi(callAdminApi, identity, bookId = '') {
    const request = (path, options) => callAdminApi(adminApi(`customers/${path}`), options);
    return {
        read: (path, params = {}) => {
            const query = new URLSearchParams(Object.entries(params).filter(([, value]) => value != null && value !== '').map(([key, value]) => [key, String(value)]));
            return request(`${path}${query.size ? `?${query}` : ''}`);
        },
        preview: (path, payload) => request(path, { method: 'POST', body: JSON.stringify(payload), headers: { 'Idempotency-Key': `customers-preview:${globalThis.crypto.randomUUID()}` } }),
        readNative: (path) => callAdminApi(adminApi(path)),
        command: async (path, payload = {}, method = 'POST') => {
            const scope = { identity, bookId, path, method };
            const hash = await digest({ ...scope, payload });
            const entry = await locked(() => {
                const entries = read();
                const same = entries.find((item) => item.hash === hash);
                if (same) return same;
                if (entries.some((item) => item.identity === identity && item.bookId === bookId && item.path === path && item.method === method)) {
                    throw new Error('Thao tác trước chưa rõ kết quả. Kiểm tra kết quả trước khi gửi nội dung khác.');
                }
                const next = { ...scope, hash, key: `customers:${globalThis.crypto.randomUUID()}`, created_at: new Date().toISOString() };
                memory.set(hash, next); save(); return next;
            });
            const resolve = () => locked(() => { read(); memory.delete(hash); save(); });
            try {
                const response = await request(path, { method, body: JSON.stringify(payload), headers: { 'Idempotency-Key': entry.key } });
                await resolve();
                return response;
            } catch (error) {
                if ([400, 401, 403, 404, 409, 422].includes(Number(error.status))) await resolve();
                throw error;
            }
        },
        recover: async (entry) => {
            const response = await request(`commands/${encodeURIComponent(entry.key)}`);
            if (['committed', 'completed', 'failed', 'rejected'].includes(response?.data?.status)) await locked(() => { read(); memory.delete(entry.hash); save(); });
            return response;
        },
        download: async (path, filename) => {
            const blob = await request(path, { responseType: 'blob' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url; link.download = filename; document.body.appendChild(link); link.click(); link.remove();
            globalThis.setTimeout(() => URL.revokeObjectURL(url), 1000);
        },
    };
}
