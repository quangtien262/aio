import { Suspense, lazy, useEffect, useMemo, useState } from 'react';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Row from 'antd/es/row';
import { useSearchParams } from 'react-router-dom';

const AdminAccountsTableCard = lazy(() => import('../components/AdminAccountsTableCard'));
const AdminAccountFormModal = lazy(() => import('../components/AdminAccountFormModal'));
const AdminAccountDetailsDrawer = lazy(() => import('../components/AdminAccountDetailsDrawer'));
const AdminPasswordFormModal = lazy(() => import('../components/AdminPasswordFormModal'));
const AdminLockFormModal = lazy(() => import('../components/AdminLockFormModal'));
const emptyAccountForm = {
    id: null,
    name: '',
    username: '',
    email: '',
    status: 'active',
    is_system_owner: false,
    assignments: [],
};
const SYSTEM_OWNER_ADMIN_ID = 1;

function normalizeAssignment(assignment) {
    const scopeType = assignment?.scope_type ?? 'global';

    return {
        ...assignment,
        role_id: assignment?.role_id === undefined || assignment?.role_id === null ? undefined : Number(assignment.role_id),
        scope_type: scopeType,
        scope_value: scopeType === 'website' && assignment?.scope_value ? String(assignment.scope_value) : null,
    };
}

export default function AdminAccountsPage({
    adminAccounts,
    roles,
    scopeTypes,
    websites,
    currentAdmin,
    canManageAdmins,
    canResetPassword,
    canLockAdmins,
    onCreateAdmin,
    onUpdateAdmin,
    onResetPassword,
    onLockAdmin,
    onUnlockAdmin,
}) {
    const [searchParams, setSearchParams] = useSearchParams();
    const [accountModalOpen, setAccountModalOpen] = useState(false);
    const [passwordModalOpen, setPasswordModalOpen] = useState(false);
    const [lockModalOpen, setLockModalOpen] = useState(false);
    const [editingAccount, setEditingAccount] = useState(emptyAccountForm);
    const [passwordTarget, setPasswordTarget] = useState(null);
    const [lockTarget, setLockTarget] = useState(null);
    const drawerAdminId = searchParams.get('admin');
    const visibleAdminAccounts = useMemo(
        () => (adminAccounts ?? []).filter((admin) => Number(admin.id) !== SYSTEM_OWNER_ADMIN_ID),
        [adminAccounts],
    );

    const roleOptions = useMemo(() => (roles ?? []).map((role) => ({
        label: role.name,
        value: role.id,
    })), [roles]);

    const scopeTypeOptions = useMemo(() => Object.entries(scopeTypes ?? {}).map(([value, label]) => ({
        label,
        value,
    })), [scopeTypes]);
    const websiteOptions = useMemo(() => {
        const optionsByValue = new Map();

        (websites ?? []).forEach((website) => {
            if (!website?.website_key) {
                return;
            }

            optionsByValue.set(String(website.website_key), {
                value: String(website.website_key),
                label: website.name || website.domain || website.website_key,
            });
        });

        (editingAccount.assignments ?? [])
            .filter((assignment) => assignment.scope_type === 'website' && assignment.scope_value)
            .forEach((assignment) => {
                const value = String(assignment.scope_value);

                if (!optionsByValue.has(value)) {
                    optionsByValue.set(value, {
                        value,
                        label: value,
                    });
                }
            });

        return Array.from(optionsByValue.values());
    }, [editingAccount.assignments, websites]);

    const openCreateModal = () => {
        setEditingAccount(emptyAccountForm);
        setAccountModalOpen(true);
    };

    const openEditModal = (admin) => {
        setEditingAccount({
            id: admin.id,
            name: admin.name,
            username: admin.username,
            email: admin.email,
            status: admin.status,
            is_system_owner: admin.is_system_owner,
            assignments: (admin.assignments ?? []).map(normalizeAssignment),
        });
        setAccountModalOpen(true);
    };

    const openDetailsDrawer = (admin) => {
        const nextParams = new URLSearchParams(searchParams);
        nextParams.set('admin', String(admin.id));
        setSearchParams(nextParams);
    };

    const openPasswordModal = (admin) => {
        setPasswordTarget(admin);
        setPasswordModalOpen(true);
    };

    const openLockModal = (admin) => {
        setLockTarget(admin);
        setLockModalOpen(true);
    };

    const handleCloseAccountModal = () => {
        setAccountModalOpen(false);
        setEditingAccount(emptyAccountForm);
    };

    const handleCloseDetailsDrawer = () => {
        const nextParams = new URLSearchParams(searchParams);
        nextParams.delete('admin');
        setSearchParams(nextParams, { replace: true });
    };

    const handleClosePasswordModal = () => {
        setPasswordModalOpen(false);
        setPasswordTarget(null);
    };

    const handleCloseLockModal = () => {
        setLockModalOpen(false);
        setLockTarget(null);
    };

    const handleSaveAccount = async (payload) => {
        const didSave = editingAccount.id
            ? await onUpdateAdmin?.(editingAccount.id, payload)
            : await onCreateAdmin?.(payload);

        if (!didSave) {
            return;
        }

        handleCloseAccountModal();
    };

    const handleResetPassword = async (payload) => {
        if (!passwordTarget) {
            return;
        }

        const didReset = await onResetPassword?.(passwordTarget.id, payload);

        if (!didReset) {
            return;
        }

        handleClosePasswordModal();
    };

    const handleLockAdmin = async (payload) => {
        if (!lockTarget) {
            return;
        }

        const didLock = await onLockAdmin?.(lockTarget.id, payload);

        if (!didLock) {
            return false;
        }

        handleCloseLockModal();
        return true;
    };

    const handleUnlockAdmin = async (adminId) => {
        await onUnlockAdmin?.(adminId);
    };

    const detailsTarget = useMemo(() => {
        if (!drawerAdminId) {
            return null;
        }

        return visibleAdminAccounts.find((admin) => String(admin.id) === drawerAdminId) ?? null;
    }, [drawerAdminId, visibleAdminAccounts]);

    useEffect(() => {
        if (!drawerAdminId || detailsTarget || visibleAdminAccounts.length === 0) {
            return;
        }

        const nextParams = new URLSearchParams(searchParams);
        nextParams.delete('admin');
        setSearchParams(nextParams, { replace: true });
    }, [detailsTarget, drawerAdminId, searchParams, setSearchParams, visibleAdminAccounts]);

    const adminStats = useMemo(() => {
        const accounts = visibleAdminAccounts;

        return {
            total: accounts.length,
            active: accounts.filter((admin) => admin.is_active).length,
            locked: accounts.filter((admin) => admin.is_locked).length,
            withScopes: accounts.filter((admin) => (admin.assignments ?? []).some((assignment) => assignment.scope_type === 'website')).length,
        };
    }, [visibleAdminAccounts]);

    return (
        <Row gutter={[16, 16]}>
            <Col span={24}>
                <Suspense fallback={<Card loading title="Admin Accounts" />}>
                    <AdminAccountsTableCard
                        adminAccounts={visibleAdminAccounts}
                        roles={roles}
                        scopeTypes={scopeTypes}
                        stats={adminStats}
                        currentAdmin={currentAdmin}
                        canManageAdmins={canManageAdmins}
                        canResetPassword={canResetPassword}
                        canLockAdmins={canLockAdmins}
                        onCreateAdmin={openCreateModal}
                        onOpenDetailsDrawer={openDetailsDrawer}
                        onEditAdmin={openEditModal}
                        onOpenPasswordModal={openPasswordModal}
                        onOpenLockModal={openLockModal}
                        onUnlockAdmin={handleUnlockAdmin}
                    />
                </Suspense>
            </Col>

            {detailsTarget ? (
                <Suspense fallback={null}>
                    <AdminAccountDetailsDrawer
                        open={Boolean(detailsTarget)}
                        admin={detailsTarget}
                        scopeTypes={scopeTypes}
                        canManageAdmins={canManageAdmins}
                        canResetPassword={canResetPassword}
                        canLockAdmins={canLockAdmins}
                        isCurrentAdmin={currentAdmin?.id === detailsTarget?.id}
                        onEditAdmin={() => {
                            handleCloseDetailsDrawer();
                            openEditModal(detailsTarget);
                        }}
                        onOpenPasswordModal={() => {
                            handleCloseDetailsDrawer();
                            openPasswordModal(detailsTarget);
                        }}
                        onOpenLockModal={() => {
                            handleCloseDetailsDrawer();
                            openLockModal(detailsTarget);
                        }}
                        onUnlockAdmin={async () => {
                            await handleUnlockAdmin(detailsTarget.id);
                        }}
                        onClose={handleCloseDetailsDrawer}
                    />
                </Suspense>
            ) : null}

            {accountModalOpen ? (
                <Suspense fallback={null}>
                    <AdminAccountFormModal
                        open={accountModalOpen}
                        canManageAdmins={canManageAdmins}
                        editingAccount={editingAccount}
                        roleOptions={roleOptions}
                        scopeTypeOptions={scopeTypeOptions}
                        websiteOptions={websiteOptions}
                        onCancel={handleCloseAccountModal}
                        onSubmit={handleSaveAccount}
                    />
                </Suspense>
            ) : null}

            {passwordModalOpen ? (
                <Suspense fallback={null}>
                    <AdminPasswordFormModal
                        open={passwordModalOpen}
                        canResetPassword={canResetPassword}
                        passwordTarget={passwordTarget}
                        onCancel={handleClosePasswordModal}
                        onSubmit={handleResetPassword}
                    />
                </Suspense>
            ) : null}

            {lockModalOpen ? (
                <Suspense fallback={null}>
                    <AdminLockFormModal
                        open={lockModalOpen}
                        canLockAdmins={canLockAdmins}
                        lockTarget={lockTarget}
                        onCancel={handleCloseLockModal}
                        onSubmit={handleLockAdmin}
                    />
                </Suspense>
            ) : null}
        </Row>
    );
}
