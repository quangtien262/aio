import { Suspense, lazy, useMemo, useState } from 'react';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Row from 'antd/es/row';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';

const { Paragraph, Text, Title } = Typography;
const RoleTableCard = lazy(() => import('../components/RoleTableCard'));
const PermissionCatalogCard = lazy(() => import('../components/PermissionCatalogCard'));
const RoleFormModal = lazy(() => import('../components/RoleFormModal'));
const emptyRoleForm = {
    id: null,
    name: '',
    key: '',
    description: '',
    permission_ids: [],
};

export default function AccessControlPage({ accessControl, onCreateRole, onUpdateRole, onDeleteRole, canManageRoles }) {
    const [roleModalOpen, setRoleModalOpen] = useState(false);
    const [editingRole, setEditingRole] = useState(emptyRoleForm);

    const permissions = accessControl?.permissions ?? [];
    const roles = accessControl?.roles ?? [];

    const permissionOptions = useMemo(() => permissions.map((permission) => ({
        label: `${permission.name} (${permission.key})`,
        value: permission.id,
    })), [permissions]);

    const groupedPermissions = useMemo(() => permissions.reduce((carry, permission) => {
        const groupKey = permission.module_key ?? 'platform';
        return {
            ...carry,
            [groupKey]: [...(carry[groupKey] ?? []), permission],
        };
    }, {}), [permissions]);

    const openCreateRole = () => {
        setEditingRole(emptyRoleForm);
        setRoleModalOpen(true);
    };

    const openEditRole = (role) => {
        setEditingRole({
            id: role.id,
            name: role.name,
            key: role.key,
            description: role.description,
            permission_ids: role.permission_ids ?? [],
        });
        setRoleModalOpen(true);
    };

    const handleCancelRoleModal = () => {
        setRoleModalOpen(false);
        setEditingRole(emptyRoleForm);
    };

    const handleSaveRole = async (payload) => {
        if (editingRole.id) {
            await onUpdateRole?.(editingRole.id, payload);
        } else {
            await onCreateRole?.(payload);
        }

        handleCancelRoleModal();
    };

    return (
        <Row gutter={[20, 20]}>
            <Col span={24}>
                <Card>
                    <Space direction="vertical" size={4}>
                        <Text className="card-label">Access Control</Text>
                        <Title level={3}>RBAC UI cho admin, role và permission</Title>
                        <Paragraph>
                            Quản lý role theo module, gán role cho admin và kiểm soát quyền truy cập thực tế ngay trong admin shell.
                        </Paragraph>
                    </Space>
                </Card>
            </Col>

            <Col xs={24} xl={15}>
                <Suspense fallback={<Card loading title="Role Management" />}>
                    <RoleTableCard
                        roles={roles}
                        canManageRoles={canManageRoles}
                        onCreateRole={openCreateRole}
                        onEditRole={openEditRole}
                        onDeleteRole={onDeleteRole}
                    />
                </Suspense>
            </Col>

            <Col xs={24} xl={9}>
                <Suspense fallback={<Card loading title="Permission Catalog" />}>
                    <PermissionCatalogCard groupedPermissions={groupedPermissions} />
                </Suspense>
            </Col>

            {roleModalOpen ? (
                <Suspense fallback={null}>
                    <RoleFormModal
                        open={roleModalOpen}
                        canManageRoles={canManageRoles}
                        editingRole={editingRole}
                        permissionOptions={permissionOptions}
                        onCancel={handleCancelRoleModal}
                        onSubmit={handleSaveRole}
                    />
                </Suspense>
            ) : null}
        </Row>
    );
}
