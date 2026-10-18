import { useEffect, useRef, useState } from 'react';
import Alert from 'antd/es/alert';
import App from 'antd/es/app';
import Button from 'antd/es/button';
import Typography from 'antd/es/typography';
import { finishCommand, pendingCommands } from '../state/fnbCommandJournal';
import { useFnbWorkspace } from '../state/FnbWorkspaceContext';
import { formatDateTime } from '../utils/fnbFormat';

export default function FnbPendingCommands({ identity, outletId, terminalId, includeHq = false }) {
    const { api } = useFnbWorkspace();
    const { message } = App.useApp();
    const [entries, setEntries] = useState([]);
    const [checking, setChecking] = useState(false);
    const mountedAt = useRef(Date.now());
    useEffect(() => {
        const refresh = () => setEntries(pendingCommands(identity).filter((entry) => (entry.status === 'uncertain' || new Date(entry.createdAt).getTime() < mountedAt.current)
            && ((String(entry.outletId ?? '') === String(outletId ?? '') && String(entry.terminalId ?? '') === String(terminalId ?? ''))
                || (includeHq && !entry.terminalId && (!entry.outletId || String(entry.outletId) === String(outletId))))));
        refresh();
        window.addEventListener('fnb-command-journal', refresh);
        window.addEventListener('storage', refresh);
        return () => { window.removeEventListener('fnb-command-journal', refresh); window.removeEventListener('storage', refresh); };
    }, [identity, outletId, terminalId, includeHq]);
    if (!entries.length) return null;
    const checkResults = async () => {
        setChecking(true);
        try {
            let confirmed = 0;
            for (const entry of entries) {
                const result = await api.commandStatus(entry);
                if (result.data?.status === 'committed') { await finishCommand(entry); confirmed += 1; }
            }
            if (confirmed) message.success(`Đã xác nhận ${confirmed} thao tác được máy chủ ghi nhận. Đồng bộ màn hình để xem kết quả.`);
            else message.info('Máy chủ chưa xác nhận kết quả. Giữ mã đối soát và kiểm tra lại trước khi thực hiện thao tác mới.');
        } catch (error) { message.error(error.message || 'Chưa kiểm tra được kết quả.'); }
        finally { setChecking(false); }
    };
    return <Alert type="warning" showIcon className="fnb-pending-commands" message={`${entries.length} thao tác chưa xác nhận kết quả`} action={<Button loading={checking} onClick={checkResults}>Kiểm tra kết quả</Button>} description={<>
        <div>Đồng bộ dữ liệu và kiểm tra kết quả trước khi thao tác tiếp. Khi gửi lại cùng nội dung, hệ thống dùng lại mã chống trùng đã lưu, kể cả sau khi tải lại trang.</div>
        {entries.map((entry) => <div key={entry.fingerprint}>{formatDateTime(entry.createdAt)} · Mã đối soát: <Typography.Text copyable>{entry.requestId ?? entry.idempotencyKey}</Typography.Text></div>)}
    </>} />;
}
