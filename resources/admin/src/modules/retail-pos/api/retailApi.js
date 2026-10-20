import { adminApi } from '../../../shared/config/routes';
import { appendQuery } from '../utils/retailFormat';
import { beginCommand, definitiveFailure, resolveCommand } from '../state/retailCommandJournal';

export function createRetailApi(callAdminApi, { identity, branchId, namespace = 'retail' } = {}) {
    const request = (path, options) => callAdminApi(`${adminApi(namespace)}/${path}`, options);
    return {
        read: (path, params = {}) => request(appendQuery(path, params)),
        command: async (path, payload = {}, method = 'POST') => {
            const entry = await beginCommand({ identity, branchId, namespace, path, method, payload });
            try {
                const result = await request(path, { method, body: JSON.stringify(payload), headers: { 'Idempotency-Key': entry.idempotencyKey } });
                await resolveCommand(entry);
                return result;
            } catch (error) {
                if (definitiveFailure(error)) await resolveCommand(entry);
                throw error;
            }
        },
        recover: async (entry) => {
            const result = await callAdminApi(`${adminApi(entry.namespace ?? namespace)}/commands/${encodeURIComponent(entry.idempotencyKey)}`);
            if (['committed', 'completed', 'failed', 'rejected'].includes(result?.data?.status)) await resolveCommand(entry);
            return result;
        },
    };
}
