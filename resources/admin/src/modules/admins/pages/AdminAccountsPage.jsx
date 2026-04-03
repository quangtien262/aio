import { Suspense, lazy, useMemo, useState } from 'react';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Row from 'antd/es/row';

const AdminAccountsTableCard = lazy(() => import('../components/AdminAccountsTableCard'));
const AdminAccountFormModal = lazy(() => import('../components/AdminAccountFormModal'));
const AdminPasswordFormModal = lazy(() => import('../components/AdminPasswordFormModal'));
const emptyAccountForm = {
    id: null,
    name: '',
    email: '',
    is_active: true,
    role_ids: [],
    scopes: [],
};

export default function AdminAccountsPage({
    adminAccounts,
    roles,
    scopeTypes,
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
    const [accountForm] = Form.useForm();
    const [passwordForm] = Form.useForm();
    const [accountModalOpen, setAccountModalOpen] = useState(false);
    const [passwordModalOpen, setPasswordModalOpen] = useState(false);
    const [editingAccount, setEditingAccount] = useState(emptyAccountForm);
    const [passwordTarget, setPasswordTarget] = useState(null);

    const roleOptions = useMemo(() => (roles ?? []).map((role) => ({
        label: role.name,
        value: role.id,
    })), [roles]);

    const scopeTypeOptions = useMemo(() => Object.entries(scopeTypes ?? {}).map(([value, label]) => ({
        label,
        value,
    })), [scopeTypes]);

    const openCreateModal = () => {
        setEditingAccount(emptyAccountForm);
        setAccountModalOpen(true);
    };

    const openEditModal = (admin) => {
        setEditingAccount({
            id: admin.id,
            name: admin.name,
            email: admin.email,
            is_active: admin.is_active,
            role_ids: admin.role_ids ?? [],
            scopes: admin.scopes ?? [],
        });
        setAccountModalOpen(true);
    };

    const openPasswordModal = (admin) => {
        setPasswordTarget(admin);
        setPasswordModalOpen(true);
    };

    const handleCloseAccountModal = () => {
        setAccountModalOpen(false);
        setEditingAccount(emptyAccountForm);
    };

    const handleClosePasswordModal = () => {
        setPasswordModalOpen(false);
        setPasswordTarget(null);
    };

    const handleSaveAccount = async (payload) => {
        if (editingAccount.id) {
            await onUpdateAdmin?.(editingAccount.id, payload);
        } else {
            await onCreateAdmin?.(payload);
        }

        handleCloseAccountModal();
    };

    const handleResetPassword = async (payload) => {
        await onResetPassword?.(passwordTarget.id, payload);
        handleClosePasswordModal();
    };

    return (
        <Row gutter={[20, 20]}>
            <Col span={24}>
                <Suspense fallback={<Card loading title="Admin Accounts" />}>
                    <AdminAccountsTableCard
                        adminAccounts={adminAccounts}
                        currentAdmin={currentAdmin}
                        canManageAdmins={canManageAdmins}
                        canResetPassword={canResetPassword}
                        canLockAdmins={canLockAdmins}
                        onCreateAdmin={openCreateModal}
                        onEditAdmin={openEditModal}
                        onOpenPasswordModal={openPasswordModal}
                        onLockAdmin={onLockAdmin}
                        onUnlockAdmin={onUnlockAdmin}
                    />
                </Suspense>
            </Col>

            {accountModalOpen ? (
                <Suspense fallback={null}>
                    <AdminAccountFormModal
                        open={accountModalOpen}
                        canManageAdmins={canManageAdmins}
                        editingAccount={editingAccount}
                        roleOptions={roleOptions}
                        scopeTypeOptions={scopeTypeOptions}
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
        </Row>
    );
}
