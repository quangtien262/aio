import { ADMIN_API_ROUTES, adminApi } from '../../../shared/config/routes.js';

export const isPharmacyPrintJobUrl = (url) => typeof url === 'string' && url.startsWith(`${ADMIN_API_ROUTES.pharmacy.printJobs.collection}/`) && /^[0-9a-f-]{36}$/i.test(url.slice(ADMIN_API_ROUTES.pharmacy.printJobs.collection.length + 1));

const JOURNAL = 'aio.pharmacy.commands.v1';
const memory = new Map();
let storageUnavailable = false;
const canonical = (value) => Array.isArray(value) ? value.map(canonical) : value && typeof value === 'object'
    ? Object.fromEntries(Object.keys(value).sort().map((key) => [key, canonical(value[key])])) : value;
const hash = async (value) => {
    if (!globalThis.crypto?.subtle) throw new Error('Cần HTTPS hoặc localhost để bảo vệ định danh giao dịch.');
    const bytes = await globalThis.crypto.subtle.digest('SHA-256', new TextEncoder().encode(JSON.stringify(canonical(value))));
    return [...new Uint8Array(bytes)].map((byte) => byte.toString(16).padStart(2, '0')).join('');
};
const read = () => {
    if (storageUnavailable || !globalThis.localStorage) return [...memory.values()];
    try {
        const saved = JSON.parse(globalThis.localStorage?.getItem(JOURNAL) ?? '[]');
        if (Array.isArray(saved)) {
            memory.clear();
            saved.filter((item) => item && typeof item.key === 'string' && typeof item.hash === 'string' && typeof item.scope === 'string' && typeof item.context === 'string')
                .forEach(({ key, hash: digest, scope, context: ctx, action, created_at }) => memory.set(digest, { key, hash: digest, scope, context: ctx, action: typeof action === 'string' ? action : '', created_at: typeof created_at === 'string' ? created_at : '' }));
        }
    } catch { storageUnavailable = true; }
    return [...memory.values()];
};
const save = () => { try { globalThis.localStorage?.setItem(JOURNAL, JSON.stringify([...memory.values()])); } catch { storageUnavailable = true; } };
const locked = (callback) => globalThis.navigator?.locks ? globalThis.navigator.locks.request(JOURNAL, async () => callback()) : callback();
const definitive = (error) => [400, 401, 403, 404, 409, 422].includes(Number(error?.status)) && !/UNCERTAIN|UNKNOWN|PAID|FUNDS_RECEIVED|STOCK_ATTENTION/.test(String(error?.code ?? ''));

// Persistent records contain hashes, keys and non-sensitive operation names only.
// Patient, prescription, tender, credentials and task payloads stay out of browser storage.
export function createPharmacyApi(callAdminApi, actorId, pharmacyId = '') {
    const request = (path, options) => callAdminApi(adminApi(`pharmacy/${path}`), options);
    const context = () => hash({ actor: String(actorId ?? ''), pharmacy: String(pharmacyId) });
    const remove = (digest) => locked(() => { read(); memory.delete(digest); save(); });
    return {
        read: (path, params = {}) => {
            const query = new URLSearchParams(Object.entries({ pharmacy_id: pharmacyId, ...params }).filter(([, value]) => value != null && value !== '').map(([key, value]) => [key, String(value)]));
            return request(`${path}${query.size ? `?${query}` : ''}`);
        },
        lookupPatients: (phone) => request('commands', { method: 'POST', headers: { 'Idempotency-Key': `pharmacy:lookup:${globalThis.crypto.randomUUID()}` }, body: JSON.stringify({ action: 'patient.lookup', pharmacy_id: pharmacyId, phone }) }),
        pending: async () => { const selected = await context(); return read().filter((item) => item.context === selected); },
        command: async (action, data = {}) => {
            const payload = { ...data, action, pharmacy_id: pharmacyId || null };
            const ctx = await context();
            const resource = data.dispensing_id ?? data.shift_id ?? data.transfer_id ?? data.receipt_id ?? data.batch_id ?? data.id ?? null;
            const scope = await hash({ context: ctx, action, resource });
            const digest = await hash({ scope, payload });
            const entry = await locked(() => {
                const entries = read();
                const same = entries.find((item) => item.hash === digest);
                if (same) return same;
                if (entries.some((item) => item.scope === scope)) {
                    const error = new Error('Thao tác trước chưa rõ kết quả. Kiểm tra kết quả trước khi gửi nội dung khác.');
                    error.code = 'PHARMACY_COMMAND_UNCERTAIN'; throw error;
                }
                const item = { key: `pharmacy:${globalThis.crypto.randomUUID()}`, hash: digest, scope, context: ctx, action, created_at: new Date().toISOString() };
                memory.set(digest, item); save(); return item;
            });
            try {
                const response = await request('commands', { method: 'POST', headers: { 'Idempotency-Key': entry.key }, body: JSON.stringify(payload) });
                await remove(digest); return response;
            } catch (error) {
                if (definitive(error)) await remove(digest);
                throw error;
            }
        },
        recover: async (entry) => {
            if (entry.context !== await context()) throw new Error('Mã đối soát thuộc phạm vi khác.');
            const response = await request(`commands/${encodeURIComponent(entry.key)}${pharmacyId ? `?pharmacy_id=${encodeURIComponent(pharmacyId)}` : ''}`);
            if (['committed', 'completed', 'failed', 'rejected'].includes(response?.data?.status)) await remove(entry.hash);
            return response;
        },
    };
}
