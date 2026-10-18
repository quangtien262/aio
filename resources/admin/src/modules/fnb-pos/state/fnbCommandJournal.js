// Store command identities, never customer data, passwords or payment payloads.
const STORAGE_KEY = 'aio.fnb.commands.v1';
const memory = new Map();
let storageWritable = true;
const journalLock = (callback) => globalThis.navigator?.locks ? globalThis.navigator.locks.request('fnb-command-journal', async () => callback()) : callback();
const isMonetaryCollection = (path, method) => method === 'POST' && /^(checks\/[^/]+\/payments|payments\/[^/]+\/refunds)$/.test(path);
const canonical = (value) => {
    if (Array.isArray(value)) return `[${value.map(canonical).join(',')}]`;
    if (value && typeof value === 'object') return `{${Object.keys(value).sort().map((key) => `${JSON.stringify(key)}:${canonical(value[key])}`).join(',')}}`;
    return JSON.stringify(value);
};

export const commandFingerprint = async (value) => {
    const input = canonical(value);
    if (!globalThis.crypto?.subtle) throw new Error('Trình duyệt cần kết nối HTTPS để ghi nhận giao dịch an toàn.');
    const bytes = await globalThis.crypto.subtle.digest('SHA-256', new TextEncoder().encode(input));
    return Array.from(new Uint8Array(bytes), (byte) => byte.toString(16).padStart(2, '0')).join('');
};

function read() {
    if (!storageWritable) return [...memory.values()];
    try {
        const entries = JSON.parse(globalThis.localStorage?.getItem(STORAGE_KEY) ?? '[]');
        if (Array.isArray(entries)) {
            memory.clear();
            entries.forEach((entry) => memory.set(entry.fingerprint, entry));
        }
    } catch { /* Memory still prevents duplicate retries when storage is unavailable. */ }
    return [...memory.values()];
}

function publish() {
    try { globalThis.localStorage?.setItem(STORAGE_KEY, JSON.stringify([...memory.values()])); } catch { storageWritable = false; }
    globalThis.dispatchEvent?.(new Event('fnb-command-journal'));
}

export function pendingCommands(identity) {
    return read().filter((entry) => entry.identity === identity).sort((a, b) => a.createdAt.localeCompare(b.createdAt));
}

export async function beginCommand({ identity, outletId, terminalId, path, method, payload, idempotencyKey }) {
    const normalizedPayload = JSON.parse(JSON.stringify(payload ?? {}));
    const scope = { identity, outletId: String(outletId ?? ''), terminalId: String(terminalId ?? ''), path, method };
    const fingerprint = await commandFingerprint({ ...scope, payload: normalizedPayload });
    const protectedRoute = isMonetaryCollection(path, method);
    const { expected_version, expected_versions, ...intent } = normalizedPayload;
    const intentFingerprint = protectedRoute ? await commandFingerprint({ ...scope, payload: intent }) : undefined;
    // Only numeric optimistic versions are retained, never the payment/refund body.
    const versions = {};
    if (Number.isSafeInteger(expected_version)) versions.expected_version = expected_version;
    if (expected_versions && typeof expected_versions === 'object' && !Array.isArray(expected_versions)
        && Object.values(expected_versions).every(Number.isSafeInteger)) versions.expected_versions = expected_versions;
    const reserve = () => {
        const entries = read();
        const existing = entries.find((entry) => entry.fingerprint === fingerprint);
        if (existing) return existing;
        const pending = protectedRoute && entries.find((entry) => entry.identity === identity
            && String(entry.outletId ?? '') === scope.outletId && String(entry.terminalId ?? '') === scope.terminalId
            && entry.path === path && entry.method === method);
        if (pending) {
            const error = new Error('Giao dịch trước chưa rõ kết quả. Cần đối soát trước khi thay đổi nội dung và gửi lại.');
            Object.assign(error, { status: 409, code: 'FNB_PREVIOUS_COMMAND_UNCERTAIN', pendingCommand: pending, intentFingerprint });
            throw error;
        }
        const entry = { fingerprint, identity, outletId, terminalId, path, method, idempotencyKey, createdAt: new Date().toISOString(), status: 'sending',
            ...(protectedRoute ? { intentFingerprint, versions } : {}) };
        memory.set(fingerprint, entry);
        publish();
        return entry;
    };
    return journalLock(reserve);
}

export async function prepareCommand(input, resolveStatus) {
    try { return { entry: await beginCommand(input), payload: input.payload }; }
    catch (error) {
        const pending = error.pendingCommand;
        if (!pending || !resolveStatus) throw error;
        const result = await resolveStatus(pending);
        const status = result?.data?.status ?? result?.status;
        if (status === 'committed') {
            await finishCommand(pending);
            throw Object.assign(new Error('Giao dịch trước đã được ghi nhận. Hãy đồng bộ, kiểm tra số tiền đã thu hoặc đã hoàn trước khi tạo giao dịch tiếp theo.'),
                { status: 409, code: 'FNB_PREVIOUS_COMMAND_COMMITTED' });
        }
        if (status === 'unknown' && pending.intentFingerprint === error.intentFingerprint && pending.versions) {
            const { expected_version, expected_versions, ...body } = input.payload ?? {};
            const retryPayload = { ...body, ...pending.versions };
            // A version-only refresh may retry the original command, never a new
            // payment. Verification also protects older/incomplete journal rows.
            const fingerprint = await commandFingerprint({ identity: input.identity, outletId: String(input.outletId ?? ''), terminalId: String(input.terminalId ?? ''), path: input.path, method: input.method, payload: JSON.parse(JSON.stringify(retryPayload)) });
            if (fingerprint === pending.fingerprint) return {
                entry: await beginCommand({ ...input, payload: retryPayload, idempotencyKey: pending.idempotencyKey }),
                payload: retryPayload,
            };
        }
        throw error;
    }
}

export function finishCommand(entry, error = null) {
    if (!entry) return;
    return journalLock(() => {
        read();
        // A late response must not recreate a server-confirmed entry or delete
        // a later command with the same body and a different key.
        if (memory.get(entry.fingerprint)?.idempotencyKey !== entry.idempotencyKey) return;
        const definitive = !error || (Number(error.status) >= 400 && Number(error.status) < 500 && ![408, 429].includes(Number(error.status)));
        if (definitive) memory.delete(entry.fingerprint);
        else memory.set(entry.fingerprint, { ...entry, status: 'uncertain', requestId: error?.requestId ?? null });
        publish();
    });
}
