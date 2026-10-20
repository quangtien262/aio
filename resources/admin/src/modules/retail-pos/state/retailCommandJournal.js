// The durable journal stores operation identities and hashes, never tender or customer payloads.
const STORAGE_KEY = 'aio.retail.commands.v1';
const memory = new Map();
const withLock = (callback) => globalThis.navigator?.locks
    ? globalThis.navigator.locks.request('aio-retail-command-journal', async () => callback()) : callback();
const canonical = (value) => {
    if (Array.isArray(value)) return `[${value.map(canonical).join(',')}]`;
    if (value && typeof value === 'object') return `{${Object.keys(value).sort().map((key) => `${JSON.stringify(key)}:${canonical(value[key])}`).join(',')}}`;
    return JSON.stringify(value);
};
const read = () => {
    try {
        const storage = globalThis.localStorage;
        const saved = storage ? JSON.parse(storage.getItem(STORAGE_KEY) ?? '[]') : null;
        if (Array.isArray(saved)) { memory.clear(); saved.forEach((entry) => memory.set(entry.fingerprint, entry)); }
    } catch { /* In-memory identities still protect retries for this page. */ }
    return [...memory.values()];
};
const save = () => {
    try { globalThis.localStorage?.setItem(STORAGE_KEY, JSON.stringify([...memory.values()])); } catch { /* Storage may be disabled. */ }
};
const fingerprint = async (input) => {
    if (!globalThis.crypto?.subtle) throw new Error('Cần HTTPS hoặc localhost để bảo vệ định danh giao dịch.');
    const digest = await globalThis.crypto.subtle.digest('SHA-256', new TextEncoder().encode(canonical(input)));
    return [...new Uint8Array(digest)].map((byte) => byte.toString(16).padStart(2, '0')).join('');
};
export const pendingCommands = (identity) => read().filter((entry) => entry.identity === identity);
export const newCommandKey = () => `retail:${globalThis.crypto.randomUUID()}`;
export async function beginCommand({ identity, branchId, namespace = 'retail', path, method = 'POST', payload }) {
    const scope = { identity, branchId: String(branchId ?? ''), namespace, path, method };
    const hash = await fingerprint({ ...scope, payload });
    return withLock(() => {
        const existing = read().find((entry) => entry.fingerprint === hash);
        if (existing) return existing;
        const protectedPath = /(?:\/complete|\/returns|\/refund|\/close|\/reconcile|\/payments|\/receive|\/dispatch|\/post)$/.test(path);
        const financialKind = (route) => route.endsWith('/complete') ? 'collect' : route.endsWith('/refund') ? 'refund' : route;
        const unresolved = protectedPath && read().find((entry) => entry.identity === identity && entry.branchId === scope.branchId && (entry.namespace ?? 'retail') === namespace && financialKind(entry.path) === financialKind(path) && entry.method === method);
        if (unresolved) {
            const error = new Error('Giao dịch trước chưa rõ kết quả. Hãy đối soát trước khi thay đổi nội dung và gửi lại.');
            error.pendingCommand = unresolved;
            error.code = 'RETAIL_COMMAND_UNCERTAIN';
            throw error;
        }
        const entry = { ...scope, fingerprint: hash, idempotencyKey: newCommandKey(), createdAt: new Date().toISOString() };
        memory.set(hash, entry);
        save();
        return entry;
    });
}
export async function resolveCommand(entry) {
    if (!entry) return;
    return withLock(() => { read(); memory.delete(entry.fingerprint); save(); });
}
export function definitiveFailure(error) {
    return [400, 401, 403, 404, 409, 422].includes(error?.status)
        && !/UNCERTAIN|UNKNOWN|FUNDS_RECEIVED|STOCK_ATTENTION/.test(String(error?.code ?? ''));
}
