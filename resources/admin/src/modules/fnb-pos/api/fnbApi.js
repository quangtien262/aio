import { adminApi } from '../../../shared/config/routes';

const FNB_BASE = adminApi('fnb');

const normalizeSegment = (value) => encodeURIComponent(String(value ?? ''));

export const createIdempotencyKey = (scope = 'fnb') => {
    const uuid = globalThis.crypto?.randomUUID?.();

    return uuid ? `${scope}:${uuid}` : `${scope}:${Date.now()}:${Math.random().toString(16).slice(2)}`;
};

export const appendQuery = (path, params = {}) => {
    const search = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            search.set(key, String(value));
        }
    });

    const query = search.toString();

    return query ? `${path}${path.includes('?') ? '&' : '?'}${query}` : path;
};

const unwrap = (payload) => {
    if (payload === null || payload === undefined) {
        return { data: null, meta: {} };
    }

    if (Object.prototype.hasOwnProperty.call(payload, 'data')) {
        return { data: payload.data, meta: payload.meta ?? {} };
    }

    if (Object.prototype.hasOwnProperty.call(payload, 'resource')) {
        return {
            data: payload.resource,
            meta: {
                ...(payload.meta ?? {}),
                replayed: payload.replayed ?? false,
                events: payload.events ?? [],
            },
        };
    }

    return { data: payload, meta: {} };
};

export function createFnbApi(callAdminApi, scope = {}) {
    const scopeHeaders = () => ({
        ...(scope.outletId ? { 'X-FNB-Outlet': String(scope.outletId) } : {}),
        ...(scope.terminalId ? { 'X-FNB-Terminal': String(scope.terminalId) } : {}),
    });

    const request = async (path, options = {}) => {
        const response = await callAdminApi(`${FNB_BASE}/${String(path).replace(/^\/+/, '')}`, {
            ...options,
            headers: {
                ...scopeHeaders(),
                ...(options.headers ?? {}),
            },
        });

        return unwrap(response);
    };

    const read = async (path, params = {}) => request(appendQuery(path, params));

    const command = async (path, payload = {}, options = {}) => {
        const idempotencyKey = options.idempotencyKey ?? createIdempotencyKey(options.scope ?? path);
        const headers = {
            'Idempotency-Key': idempotencyKey,
            ...(options.reauthProof ? { 'X-FNB-Reauth-Proof': options.reauthProof } : {}),
            ...(options.approvalToken ? { 'X-FNB-Approval-Token': options.approvalToken } : {}),
            ...(options.headers ?? {}),
        };
        const body = payload instanceof FormData ? payload : JSON.stringify(payload ?? {});

        return request(path, {
            method: options.method ?? 'POST',
            body,
            headers,
        });
    };

    const download = (path) => callAdminApi(`${FNB_BASE}/${String(path).replace(/^\/+/, '')}`, {
        credentials: 'same-origin',
        responseType: 'blob',
        headers: scopeHeaders(),
    });

    const outletPath = (suffix = '') => {
        if (!scope.outletId) {
            throw new Error('Vui lòng chọn điểm bán trước khi tiếp tục.');
        }

        return `outlets/${normalizeSegment(scope.outletId)}${suffix ? `/${suffix}` : ''}`;
    };

    return {
        read,
        request,
        command,
        outletPath,
        bootstrap: () => read('bootstrap'),
        onboard: (payload, options) => command('onboarding/presets/cafe', payload, options),
        dashboard: (filters) => read(outletPath('dashboard'), filters),
        pos: () => read(outletPath('pos'), { terminal_id: scope.terminalId }),
        catalog: (filters) => read(outletPath('menu'), filters),
        recipes: (filters) => read(outletPath('recipes'), filters),
        kitchen: (filters) => read(outletPath('kitchen/tickets'), filters),
        shifts: (filters) => read(outletPath('shifts'), filters),
        currentBusinessDay: () => read(outletPath('business-days/current')),
        report: (filters) => read(outletPath('reports/summary'), filters),
        reportExports: (filters) => read(outletPath('reports/exports'), filters),
        downloadExport: (id) => download(`reports/exports/${normalizeSegment(id)}/download`),
        settings: () => read(outletPath('settings')),
        staff: () => read(outletPath('staff')),
    };
}
