import { Suspense, lazy, useCallback, useEffect, useMemo, useState } from 'react';
import App from 'antd/es/app';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Result from 'antd/es/result';
import Skeleton from 'antd/es/skeleton';
import { useLocation, useNavigate } from 'react-router-dom';
import { createFnbApi, createIdempotencyKey } from '../api/fnbApi';
import FnbConflictDrawer from '../components/FnbConflictDrawer';
import FnbCriticalActionDrawer from '../components/FnbCriticalActionDrawer';
import { FnbResourceError } from '../components/FnbResourceState';
import FnbWorkspaceShell from '../components/FnbWorkspaceShell';
import useFnbResource from '../hooks/useFnbResource';
import { FnbWorkspaceContext } from '../state/FnbWorkspaceContext';
import '../styles/fnb-pos.css';

const OnboardingPage = lazy(() => import('./sections/FnbOnboardingPage'));
const DashboardPage = lazy(() => import('./sections/FnbDashboardPage'));
const PosPage = lazy(() => import('./sections/FnbSalesPage'));
const KitchenPage = lazy(() => import('./sections/FnbKitchenPage'));
const MenuPage = lazy(() => import('./sections/FnbMenuPage'));
const ShiftsPage = lazy(() => import('./sections/FnbShiftsPage'));
const ReportsPage = lazy(() => import('./sections/FnbReportsPage'));
const StaffPage = lazy(() => import('./sections/FnbStaffPage'));
const SettingsPage = lazy(() => import('./sections/FnbSettingsPage'));

// AdminApp's BrowserRouter already owns the /admin basename.
const BASE_ROUTE = '/modules/fnb-pos';

const sections = [
    { key: 'onboarding', label: 'Khởi tạo quán', permissions: ['fnb.outlet.manage'], allowWithoutOutlet: true },
    { key: 'dashboard', label: 'Tổng quan', permissions: ['fnb.dashboard.view'] },
    { key: 'pos', label: 'Bán hàng', permissions: ['fnb.order.create'] },
    { key: 'kitchen', label: 'Bar / Bếp', permissions: ['fnb.kitchen.view'] },
    { key: 'menu', label: 'Thực đơn', permissions: ['fnb.menu.view'] },
    { key: 'shifts', label: 'Ca & két', permissions: ['fnb.shift.view'] },
    { key: 'reports', label: 'Báo cáo', permissions: ['fnb.report.operations.view', 'fnb.report.financial.view'] },
    { key: 'staff', label: 'Nhân viên', permissions: ['fnb.staff.view'] },
    { key: 'settings', label: 'Thiết lập', permissions: ['fnb.settings.view'] },
];

const sectionComponents = {
    onboarding: OnboardingPage,
    dashboard: DashboardPage,
    pos: PosPage,
    kitchen: KitchenPage,
    menu: MenuPage,
    shifts: ShiftsPage,
    reports: ReportsPage,
    staff: StaffPage,
    settings: SettingsPage,
};

const storedValue = (key) => {
    try {
        return window.localStorage.getItem(key) ?? '';
    } catch {
        return '';
    }
};

const persistValue = (key, value) => {
    try {
        if (value) {
            window.localStorage.setItem(key, String(value));
        } else {
            window.localStorage.removeItem(key);
        }
    } catch {
        // Storage is an optional convenience; server scope remains authoritative.
    }
};

export default function FnbPosManagerPage({ callAdminApi, currentPermissions = [], modulePayload }) {
    const { message } = App.useApp();
    const location = useLocation();
    const navigate = useNavigate();
    const [outletId, setOutletId] = useState(() => storedValue('aio.fnb.outlet'));
    const [terminalId, setTerminalId] = useState(() => storedValue('aio.fnb.terminal'));
    const [criticalRequest, setCriticalRequest] = useState(null);
    const [conflict, setConflict] = useState(null);
    const baseApi = useMemo(() => createFnbApi(callAdminApi), [callAdminApi]);
    const api = useMemo(() => createFnbApi(callAdminApi, { outletId, terminalId }), [callAdminApi, outletId, terminalId]);
    const can = useCallback((permission) => currentPermissions.includes(permission), [currentPermissions]);
    const bootstrapResource = useFnbResource({
        loader: baseApi.bootstrap,
        deps: [baseApi],
    });
    const bootstrap = bootstrapResource.data ?? {};
    const outlets = Array.isArray(bootstrap.outlets) ? bootstrap.outlets : [];
    const selectedOutlet = outlets.find((item) => String(item.id ?? item.public_id) === String(outletId)) ?? null;
    const terminals = Array.isArray(selectedOutlet?.terminals)
        ? selectedOutlet.terminals
        : (Array.isArray(bootstrap.terminals)
            ? bootstrap.terminals.filter((item) => !outletId || String(item.outlet_id) === String(outletId))
            : []);
    const onboardingComplete = Boolean(bootstrap.onboarding?.completed ?? outlets.length);

    useEffect(() => {
        if (!outlets.length) {
            if (outletId) {
                setOutletId('');
                setTerminalId('');
            }
            return;
        }

        if (!selectedOutlet) {
            const firstOutletId = String(outlets[0].id ?? outlets[0].public_id);
            setOutletId(firstOutletId);
            persistValue('aio.fnb.outlet', firstOutletId);
        }
    }, [outletId, outlets, selectedOutlet]);

    useEffect(() => {
        if (!terminals.length) {
            if (terminalId) {
                setTerminalId('');
                persistValue('aio.fnb.terminal', '');
            }
            return;
        }

        if (!terminals.some((item) => String(item.id ?? item.public_id) === String(terminalId))) {
            const firstTerminalId = String(terminals[0].id ?? terminals[0].public_id);
            setTerminalId(firstTerminalId);
            persistValue('aio.fnb.terminal', firstTerminalId);
        }
    }, [terminalId, terminals]);

    const visibleSections = useMemo(() => sections.filter((item) => (
        item.permissions.some(can)
        && (item.allowWithoutOutlet || outlets.length > 0)
        && (item.key !== 'onboarding' || !onboardingComplete || can('fnb.outlet.manage'))
    )), [can, onboardingComplete, outlets.length]);

    const requestedSection = location.pathname
        .slice(BASE_ROUTE.length)
        .split('/')
        .filter(Boolean)[0];
    const defaultSection = !onboardingComplete && visibleSections.some((item) => item.key === 'onboarding')
        ? 'onboarding'
        : (visibleSections.find((item) => item.key === 'dashboard')?.key ?? visibleSections[0]?.key ?? 'dashboard');
    const activeSection = visibleSections.some((item) => item.key === requestedSection) ? requestedSection : defaultSection;

    useEffect(() => {
        if (!bootstrapResource.loading && requestedSection !== activeSection) {
            navigate(`${BASE_ROUTE}/${activeSection}`, { replace: true });
        }
    }, [activeSection, bootstrapResource.loading, navigate, requestedSection]);

    const handleOutletChange = (value) => {
        setOutletId(value);
        setTerminalId('');
        persistValue('aio.fnb.outlet', value);
        persistValue('aio.fnb.terminal', '');
    };

    const handleTerminalChange = (value) => {
        setTerminalId(value);
        persistValue('aio.fnb.terminal', value);
    };

    const runCommand = useCallback(async ({
        execute,
        successMessage,
        onSuccess,
        onConflict,
        title,
        action,
        subject,
        payloadHash,
        policyKey,
        reason,
    }) => {
        const request = {
            execute,
            successMessage,
            onSuccess,
            onConflict,
            title,
            action,
            subject,
            payloadHash,
            policyKey,
            reason,
            reauthIdempotencyKey: createIdempotencyKey('reauth'),
            approvalIdempotencyKey: createIdempotencyKey('approval'),
            approvalClaimIdempotencyKey: createIdempotencyKey('approval-claim'),
        };

        try {
            const result = await execute({});

            if (onSuccess) {
                await onSuccess(result);
            }
            if (successMessage) {
                message.success(successMessage);
            }

            return result;
        } catch (error) {
            if (error.status === 423 || error.code === 'FNB_REAUTH_REQUIRED' || error.code === 'FNB_APPROVAL_REQUIRED') {
                setCriticalRequest({ ...request, error });
                return null;
            }

            if (error.status === 409) {
                setConflict({ error, onConflict });
                return null;
            }

            message.error(error.message || 'Không thực hiện được thao tác.');
            return null;
        }
    }, [message]);

    const finishCriticalAction = async (result) => {
        const request = criticalRequest;
        setCriticalRequest(null);

        if (request?.onSuccess) {
            await request.onSuccess(result);
        }
        if (request?.successMessage) {
            message.success(request.successMessage);
        }
    };

    const reloadConflict = async () => {
        const handler = conflict?.onConflict;
        setConflict(null);

        if (handler) {
            await handler();
        }
    };

    const contextValue = useMemo(() => ({
        api,
        baseApi,
        bootstrap,
        reloadBootstrap: bootstrapResource.reload,
        outletId,
        terminalId,
        selectedOutlet,
        selectedTerminal: terminals.find((item) => String(item.id ?? item.public_id) === String(terminalId)) ?? null,
        currentPermissions,
        can,
        runCommand,
        navigateSection: (key) => navigate(`${BASE_ROUTE}/${key}`),
    }), [api, baseApi, bootstrap, bootstrapResource.reload, can, currentPermissions, navigate, outletId, selectedOutlet, terminalId, terminals, runCommand]);

    if (bootstrapResource.loading) {
        return <Card><Skeleton active paragraph={{ rows: 8 }} /></Card>;
    }

    if (bootstrapResource.error) {
        return <FnbResourceError error={bootstrapResource.error} onRetry={bootstrapResource.reload} title="Không mở được Quản lý quán Cafe" />;
    }

    if (!visibleSections.length) {
        return (
            <Result
                status="403"
                title="Chưa có quyền vào khu vực quán Cafe"
                subTitle="Quyền hiển thị chỉ hỗ trợ điều hướng; máy chủ vẫn kiểm tra mọi thao tác theo website và điểm bán."
            />
        );
    }

    const ActivePage = sectionComponents[activeSection];

    return (
        <FnbWorkspaceContext.Provider value={contextValue}>
            <FnbWorkspaceShell
                navigation={visibleSections}
                activeSection={activeSection}
                onNavigate={(key) => navigate(`${BASE_ROUTE}/${key}`)}
                outlets={outlets}
                terminals={terminals}
                outletId={outletId}
                terminalId={terminalId}
                onOutletChange={handleOutletChange}
                onTerminalChange={handleTerminalChange}
                operationalState={bootstrap.operational_state ?? bootstrap.site_settings?.operational_state}
                installedVersion={bootstrap.installed_version ?? modulePayload?.installed_version}
            >
                {!onboardingComplete && activeSection !== 'onboarding' ? (
                    <Alert
                        type="warning"
                        showIcon
                        message="Cần hoàn tất khởi tạo quán trước khi vận hành"
                        action={<Button onClick={() => navigate(`${BASE_ROUTE}/onboarding`)}>Khởi tạo ngay</Button>}
                        style={{ marginBottom: 16 }}
                    />
                ) : null}
                <Suspense fallback={<Card><Skeleton active paragraph={{ rows: 10 }} /></Card>}>
                    <ActivePage />
                </Suspense>
            </FnbWorkspaceShell>

            <FnbCriticalActionDrawer
                request={criticalRequest}
                api={api}
                onClose={() => setCriticalRequest(null)}
                onCompleted={finishCriticalAction}
                onConflict={(error, handler) => {
                    setCriticalRequest(null);
                    setConflict({ error, onConflict: handler });
                }}
            />
            <FnbConflictDrawer
                conflict={conflict}
                onClose={() => setConflict(null)}
                onReload={reloadConflict}
            />
        </FnbWorkspaceContext.Provider>
    );
}
