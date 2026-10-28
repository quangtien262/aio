import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import Alert from 'antd/es/alert';
import App from 'antd/es/app';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Empty from 'antd/es/empty';
import Modal from 'antd/es/modal';
import Typography from 'antd/es/typography';
import { Navigate, useLocation } from 'react-router-dom';
import { createPharmacyApi, isPharmacyPrintJobUrl } from '../api/pharmacyApi';
import PharmacyCommandDrawer from '../components/PharmacyCommandDrawer';
import PharmacyContextSwitcher from '../components/PharmacyContextSwitcher';
import { PendingCommands, PharmacyError } from '../components/PharmacyCommon';
import { PharmacyWorkspaceContext } from '../state/PharmacyWorkspaceContext';
import { actionAllowed, canViewPharmacySection, hasActionPermission, recordId, resourceRows, sectionForPath, sections } from '../utils/pharmacyData';
import { forms } from '../utils/pharmacyForms';
import PharmacyDispensingPage from './PharmacyDispensingPage';
import PharmacyOperationsPage from './PharmacyOperationsPage';
import PharmacyPosPage from './PharmacyPosPage';
import PharmacyQualityPage from './PharmacyQualityPage';
import PharmacyIntegrationsPage from './PharmacyIntegrationsPage';
import PharmacyResourcePage from './PharmacyResourcePage';
import '../styles/pharmacy.css';

export default function PharmacyManagerPage({ callAdminApi, currentAdmin, currentPermissions = [] }) {
    const { message, modal } = App.useApp();
    const location = useLocation();
    const [bootstrap, setBootstrap] = useState(null);
    const [pharmacyId, setPharmacyId] = useState('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [request, setRequest] = useState(null);
    const [revision, setRevision] = useState(0);
    const [pending, setPending] = useState([]);
    const [recovering, setRecovering] = useState('');
    const [commandBusy, setCommandBusy] = useState(false);
    const [outcome, setOutcome] = useState(null);
    const [draftDirty, setDraftDirty] = useState(false);
    const [contextOpen, setContextOpen] = useState(false);
    const generation = useRef(0);
    const actorId = currentAdmin?.id ?? currentAdmin?.admin?.id;
    const api = useMemo(() => createPharmacyApi(callAdminApi, actorId, pharmacyId), [callAdminApi, actorId, pharmacyId]);
    const permissions = bootstrap?.permissions ?? currentPermissions;
    const requestedSection = location.pathname.split('/modules/pharmacy/')[1]?.split('/')[0];
    const allowedNavigation = (bootstrap?.navigation ?? bootstrap?.module_navigation ?? currentAdmin?.module_navigation ?? []).filter((item) => typeof item.route === 'string' && item.route.includes('/modules/pharmacy/') && canViewPharmacySection(permissions, sectionForPath(item.route)));
    const firstRoute = (allowedNavigation.find((item) => item.permission && item.permission !== 'pharmacy.view') ?? allowedNavigation[0])?.route;
    const section = requestedSection || (firstRoute ? sectionForPath(firstRoute) : 'none');
    const title = sections.find(([key]) => key === section) ?? (section === 'dispensings' ? ['dispensings', 'Hồ sơ cấp thuốc', 'Đối chiếu từng bước từ tiếp nhận đến bàn giao'] : ['none', 'Nhà thuốc', 'Các nhiệm vụ theo phạm vi được phân công']);
    const can = useCallback((action, record) => hasActionPermission(permissions, action) && actionAllowed(record, action) && !bootstrap?.runtime?.paused && bootstrap?.readiness?.ready !== false && (action !== 'setup' || bootstrap?.can_setup !== false) && !commandBusy && pending.length === 0, [permissions, bootstrap?.runtime?.paused, bootstrap?.readiness?.ready, bootstrap?.can_setup, commandBusy, pending.length]);
    const loadBootstrap = useCallback(async () => {
        const ticket = ++generation.current;
        setError(null); setLoading(true);
        try {
            const response = await api.read('bootstrap');
            if (ticket !== generation.current) return;
            const data = response.data ?? {};
            setBootstrap(data);
            const pharmacies = resourceRows(data.pharmacies);
            setPharmacyId((current) => pharmacies.some((item) => recordId(item) === current) ? current : recordId(data.current_pharmacy) || recordId(pharmacies[0]));
        } catch (failure) { if (ticket === generation.current) setError(failure); }
        finally { if (ticket === generation.current) setLoading(false); }
    }, [api]);
    useEffect(() => { loadBootstrap(); return () => { generation.current += 1; }; }, [loadBootstrap]);
    const refreshPending = useCallback(async () => {
        try { setPending(await api.pending()); } catch (failure) { setError(failure); }
    }, [api]);
    useEffect(() => { setRequest(null); setOutcome(null); setPending([]); refreshPending(); }, [refreshPending]);
    useEffect(() => {
        const listener = () => refreshPending();
        globalThis.addEventListener('storage', listener);
        return () => globalThis.removeEventListener('storage', listener);
    }, [refreshPending]);
    const runCommand = useCallback(async (action, payload) => {
        setCommandBusy(true);
        try {
            const response = await api.command(action, payload);
            setRevision((value) => value + 1);
            return response;
        } finally { await refreshPending(); setCommandBusy(false); }
    }, [api, refreshPending]);
    const openCommand = useCallback((action, record = null, payload = {}, overrides = {}) => {
        if (!can(action, record)) return;
        const spec = forms[action];
        const fields = (overrides.fields ?? spec?.fields)?.map((field) => field.kind === 'chains' ? { ...field, kind: null, type: 'select', options: resourceRows(bootstrap?.chains).map((chain) => ({ value: recordId(chain), label: chain.name })) } : action === 'membership.save' && field.name === 'permissions' ? { ...field, options: (bootstrap?.module?.permissions ?? bootstrap?.navigation_permissions ?? []).filter((permission) => typeof permission === 'string' && permission.startsWith('pharmacy.')).map((value) => ({ value, label: value })) } : field);
        const initial = { ...spec?.initial, ...(record ?? {}), ...overrides.initial };
        fields?.forEach((field) => { if (field.type === 'date' && typeof initial[field.name] === 'string') initial[field.name] = initial[field.name].slice(0, 10); });
        setRequest({ ...spec, ...overrides, fields, action, payload: { ...(record?.version != null ? { expected_version: record.version } : {}), ...payload }, initial });
    }, [can, bootstrap?.chains]);
    const submit = async (action, values, spec) => {
        const response = await runCommand(action, spec.normalize ? { ...spec.payload, ...spec.normalize(values) } : values);
        message.success('Đã ghi nhận thao tác.');
        if (['setup', 'pharmacy.create', 'counter.save', 'settings.save', 'membership.save', 'credential.save'].includes(action)) await loadBootstrap();
        if (action === 'setup' && response.data?.pharmacy) setPharmacyId(recordId(response.data.pharmacy));
        if (spec.onCompleted) await spec.onCompleted(response);
        if (['print.create', 'dispensing.return', 'accounting.draft'].includes(action)) setOutcome({ action, data: response.data });
        return response;
    };
    const recover = async (entry) => {
        setRecovering(entry.key);
        try {
            const response = await api.recover(entry);
            if (['committed', 'completed'].includes(response.data?.status)) { message.success('Máy chủ đã ghi nhận thao tác. Dữ liệu đã được làm mới.'); setRevision((value) => value + 1); }
            else message.info(response.data?.status === 'unknown' ? 'Chưa có kết quả xác nhận. Giữ mã đối soát và kiểm tra trước khi thao tác lại.' : `Trạng thái: ${response.data?.status ?? 'Chưa xác định'}`);
            await refreshPending();
        } catch (failure) { setError(failure); }
        finally { setRecovering(''); }
    };
    const pharmacy = resourceRows(bootstrap?.pharmacies).find((item) => recordId(item) === pharmacyId);
    const contextDisabled = loading || Boolean(request) || commandBusy || pending.length > 0;
    const changePharmacy = (value) => {
        if (contextDisabled || !resourceRows(bootstrap?.pharmacies).some((item) => recordId(item) === value)) return;
        if (value === pharmacyId) { setContextOpen(false); return; }
        const change = () => { setContextOpen(false); setDraftDirty(false); setPharmacyId(value); setRevision((current) => current + 1); };
        if (draftDirty && value !== pharmacyId) modal.confirm({ title: 'Chuyển nhà thuốc và bỏ hồ sơ chưa lưu?', content: 'Hồ sơ đang lập chưa được lưu trên máy chủ. Chuyển nhà thuốc sẽ xóa những dòng này khỏi màn hình.', okText: 'Bỏ bản nháp và chuyển', cancelText: 'Tiếp tục hồ sơ hiện tại', onOk: change });
        else change();
    };
    const context = { api, pharmacyId, pharmacy, bootstrap: bootstrap ?? {}, revision, can, openCommand, runCommand, permissions, setDraftDirty };
    const detailId = section === 'dispensings' ? location.pathname.split('/dispensings/')[1]?.split('/')[0] : null;
    if (bootstrap && pharmacyId && !requestedSection && section !== 'none') return <Navigate replace to={`/modules/pharmacy/${section}`} />;
    return <PharmacyWorkspaceContext.Provider value={context}><div className="ph-workspace">
        <header className="ph-workspace-heading">
            <div className="ph-workspace-title">
                <Typography.Title level={2}>{title[1]}</Typography.Title>
                <Typography.Paragraph className="ph-workspace-description">{title[2]}</Typography.Paragraph>
            </div>
            <PharmacyContextSwitcher pharmacies={resourceRows(bootstrap?.pharmacies)} currentId={pharmacyId} disabled={contextDisabled} open={contextOpen} onOpen={() => setContextOpen(true)} onClose={() => setContextOpen(false)} onChoose={changePharmacy} />
        </header>
        <PharmacyError error={error} onRetry={loadBootstrap} /><PendingCommands entries={pending} onRecover={recover} recovering={recovering} />
        {bootstrap?.readiness?.ready === false && <Alert className="ph-resource-error" type="warning" showIcon message="Nền tảng nghiệp vụ chưa sẵn sàng" description={<ul>{(bootstrap.readiness.issues ?? []).map((issue, index) => <li key={index}>{issue.message ?? issue.reason}</li>)}</ul>} />}
        {bootstrap?.runtime?.paused && <Alert type="warning" showIcon message="Đang tạm dừng ghi nghiệp vụ" description="Có thể xem dữ liệu trong phạm vi quyền. Hoàn tất đối soát hoặc liên hệ quản trị trước khi mở lại." />}
        {!bootstrap && loading ? <Card loading /> : !pharmacyId ? <Card className="ph-onboarding"><Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description={<><Typography.Title level={4}>Bắt đầu với nhà thuốc đầu tiên</Typography.Title><p>Thiết lập cơ sở, kho và quầy. Dữ liệu nghiệp vụ được tạo bằng thao tác có xác nhận.</p></>}><Button type="primary" disabled={!can('setup')} onClick={() => openCommand('setup')}>Thiết lập nhà thuốc</Button></Empty>{!can('setup') && <Alert type="info" message="Liên hệ quản trị để thiết lập hoặc phân công phạm vi nhà thuốc." />}</Card>
            : <div key={`${actorId}:${pharmacyId}:${section}`} className="ph-page-content">{!canViewPharmacySection(permissions, section) ? <Card><Empty description="Chưa có nhiệm vụ được phân công. Liên hệ quản trị để kiểm tra quyền và phạm vi nhà thuốc." /></Card> : section === 'pos' ? <PharmacyPosPage /> : section === 'dispensings' && detailId ? <PharmacyDispensingPage id={detailId} /> : section === 'integrations' ? <PharmacyIntegrationsPage /> : ['operations', 'reports', 'settings'].includes(section) ? <PharmacyOperationsPage section={section} /> : section === 'quality' ? <PharmacyQualityPage /> : <PharmacyResourcePage section={section} />}</div>}
        <PharmacyCommandDrawer request={request} api={api} onClose={() => setRequest(null)} onSubmit={submit} />
        <Modal open={Boolean(outcome)} title={outcome?.action === 'print.create' ? 'Bản in đã sẵn sàng' : outcome?.action === 'accounting.draft' ? 'Chứng từ kế toán nháp' : 'Đã ghi nhận hàng trả'} onCancel={() => setOutcome(null)} footer={<Button onClick={() => setOutcome(null)}>Đóng</Button>} destroyOnHidden>
            {outcome?.action === 'print.create' ? <><Typography.Paragraph>Mở bản in riêng để kiểm tra và in. Mở cửa sổ không xác nhận máy in đã in thành công.</Typography.Paragraph>{isPharmacyPrintJobUrl(outcome?.data?.download_url) && <Button type="primary" href={outcome.data.download_url} target="_blank" rel="noopener noreferrer">Mở bản in</Button>}</> : outcome?.action === 'accounting.draft' ? <><Typography.Paragraph>Đã tạo hoặc liên kết chứng từ nháp. Người có quyền kế toán kiểm tra và ghi sổ riêng.</Typography.Paragraph><Typography.Text copyable>{String(outcome?.data?.document_id ?? outcome?.data?.id ?? '')}</Typography.Text></> : <><Typography.Paragraph>Giữ mã phiếu để đối soát hoàn tiền. Hàng trả vẫn ở khu vực cách ly.</Typography.Paragraph><Typography.Text copyable>{outcome?.data?.return_id ?? outcome?.data?.id}</Typography.Text></>}
        </Modal>
    </div></PharmacyWorkspaceContext.Provider>;
}
