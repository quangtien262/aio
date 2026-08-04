import { Suspense, lazy, useMemo, useState } from 'react';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Row from 'antd/es/row';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';
import { formatPermissionLabel } from '../utils/permissionLabels';

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
    const [permissionCatalogOpen, setPermissionCatalogOpen] = useState(false);
    const [editingRole, setEditingRole] = useState(emptyRoleForm);

    const permissions = accessControl?.permissions ?? [];
    const roles = accessControl?.roles ?? [];

    const normalizedPermissions = useMemo(() => permissions.map((permission) => ({
        ...permission,
        display_name: formatPermissionLabel(permission.key),
    })), [permissions]);

    const groupedPermissions = useMemo(() => normalizedPermissions.reduce((carry, permission) => {
        const groupKey = permission.module_key ?? 'platform';

        return {
            ...carry,
            [groupKey]: [...(carry[groupKey] ?? []), permission],
        };
    }, {}), [normalizedPermissions]);

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
                        <Text className="card-label">Quản trị truy cập</Text>
                        <Title level={3}>Vai trò và quyền sử dụng hệ thống</Title>
                        <Paragraph>
                            Tạo vai trò theo từng nhóm công việc, sau đó gán cho tài khoản quản trị để kiểm soát phạm vi sử dụng.
                        </Paragraph>
                    </Space>
                </Card>
            </Col>

            <Col span={24}>
                <Suspense fallback={<Card loading title="Danh sách vai trò" />}>
                    <RoleTableCard
                        roles={roles}
                        canManageRoles={canManageRoles}
                        onCreateRole={openCreateRole}
                        onEditRole={openEditRole}
                        onDeleteRole={onDeleteRole}
                        onOpenPermissionCatalog={() => setPermissionCatalogOpen(true)}
                    />
                </Suspense>
            </Col>

            {permissionCatalogOpen ? (
                <Suspense fallback={<Card loading title="Danh mục quyền" />}>
                    <PermissionCatalogCard
                        groupedPermissions={groupedPermissions}
                        open={permissionCatalogOpen}
                        onClose={() => setPermissionCatalogOpen(false)}
                    />
                </Suspense>
            ) : null}

            {roleModalOpen ? (
                <Suspense fallback={null}>
                    <RoleFormModal
                        open={roleModalOpen}
                        canManageRoles={canManageRoles}
                        editingRole={editingRole}
                        permissions={normalizedPermissions}
                        onCancel={handleCancelRoleModal}
                        onSubmit={handleSaveRole}
                    />
                </Suspense>
            ) : null}
        </Row>
    );
}
