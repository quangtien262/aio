import { adminApi } from '../../../shared/config/routes.js';

const STORAGE_KEY = 'aio.manufacturing.commands.v1';
const memory = new Map();
let storageUnavailable = false;
const canonical = (value) => Array.isArray(value) ? value.map(canonical) : value && typeof value === 'object'
    ? Object.fromEntries(Object.keys(value).sort().map((key) => [key, canonical(value[key])])) : value;
const digest = async (value) => {
    if (!globalThis.crypto?.subtle) throw new Error('Trình duyệt cần kết nối an toàn để ghi nhận giao dịch.');
    const bytes = await globalThis.crypto.subtle.digest('SHA-256', new TextEncoder().encode(JSON.stringify(canonical(value))));
    return [...new Uint8Array(bytes)].map((byte) => byte.toString(16).padStart(2, '0')).join('');
};
const read = () => {
    if (storageUnavailable) return [...memory.values()];
    try {
        if (!globalThis.localStorage) return [...memory.values()];
        const saved = JSON.parse(globalThis.localStorage?.getItem(STORAGE_KEY) ?? '[]');
        if (Array.isArray(saved)) {
            memory.clear();
            saved.filter((row) => row && typeof row.hash === 'string' && typeof row.key === 'string' && typeof row.scope === 'string')
                .forEach((row) => memory.set(row.hash, row));
        }
    } catch { storageUnavailable = true; }
    return [...memory.values()];
};
const save = () => {
    try { globalThis.localStorage?.setItem(STORAGE_KEY, JSON.stringify([...memory.values()])); } catch { storageUnavailable = true; }
};
const locked = (callback) => globalThis.navigator?.locks
    ? globalThis.navigator.locks.request(STORAGE_KEY, async () => callback()) : callback();

export function createManufacturingApi(callAdminApi, actorId, factoryId = '') {
    const request = (path, options) => callAdminApi(adminApi(`manufacturing/${path}`), options);
    const context = () => digest({ actor: String(actorId ?? ''), factory: factoryId });
    const remove = (hash) => locked(() => { read(); memory.delete(hash); save(); });
    return {
        read: (path, params = {}) => {
            const query = new URLSearchParams(Object.entries({ factory_id: factoryId, ...params })
                .filter(([, value]) => value !== undefined && value !== null && value !== '')
                .map(([key, value]) => [key, String(value)]));
            return request(`${path}${query.size ? `?${query}` : ''}`);
        },
        pending: async () => {
            const contextHash = await context();
            return read().filter((row) => row.context === contextHash);
        },
        command: async (operation, resource, data) => {
            const payload = { factory_id: factoryId || null, resource: resource || null, data };
            const contextHash = await context();
            const scope = await digest({ context: contextHash, operation, resource: resource || null });
            const hash = await digest({ scope, payload });
            const entry = await locked(() => {
                const entries = read();
                const same = entries.find((row) => row.hash === hash);
                if (same) return same;
                if (entries.some((row) => row.scope === scope)) {
                    throw new Error('Thao tác trước chưa rõ kết quả. Hãy kiểm tra giao dịch đang chờ trước khi gửi nội dung khác.');
                }
                const next = { key: `manufacturing:${globalThis.crypto.randomUUID()}`, hash, scope, context: contextHash, operation, created_at: new Date().toISOString() };
                memory.set(hash, next); save(); return next;
            });
            try {
                const result = await request(`commands/${operation}`, { method: 'POST', headers: { 'Idempotency-Key': entry.key }, body: JSON.stringify(payload) });
                await remove(hash);
                return result;
            } catch (error) {
                if ([400, 401, 403, 404, 409, 422].includes(Number(error.status))) await remove(hash);
                throw error;
            }
        },
        recover: async (entry) => {
            const query = factoryId ? `?factory_id=${encodeURIComponent(factoryId)}` : '';
            const result = await request(`commands/${encodeURIComponent(entry.key)}${query}`);
            if (['committed', 'completed', 'failed', 'rejected'].includes(result?.data?.status)) await remove(entry.hash);
            return result;
        },
        uploadDocument: async (resourceType, resourceId, file) => {
            if (!file || file.size > 20 * 1024 * 1024) throw new Error('Tài liệu tối đa 20 MB.');
            const checksumBytes = await globalThis.crypto.subtle.digest('SHA-256', await file.arrayBuffer());
            const checksum = [...new Uint8Array(checksumBytes)].map((byte) => byte.toString(16).padStart(2, '0')).join('');
            const hash = await digest({ context: await context(), resourceType, resourceId, checksum, name: file.name, type: file.type });
            const body = new FormData();
            body.append('factory_id', factoryId);
            body.append('resource_type', resourceType);
            body.append('resource_id', resourceId);
            body.append('file', file);
            return request('documents', { method: 'POST', body, headers: { 'Idempotency-Key': `mfg-document:${hash}` } });
        },
        downloadDocument: async (id, filename) => {
            const blob = await request(`documents/${encodeURIComponent(id)}`, { responseType: 'blob' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url; link.download = filename || 'tai-lieu'; document.body.appendChild(link); link.click(); link.remove();
            globalThis.setTimeout(() => URL.revokeObjectURL(url), 1000);
        },
    };
}
