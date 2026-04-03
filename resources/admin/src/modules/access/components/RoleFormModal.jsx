import { useEffect } from 'react';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Select from 'antd/es/select';

export const emptyRoleForm = {
    id: null,
    name: '',
    key: '',
    description: '',
    permission_ids: [],
};

export default function RoleFormModal({ open, canManageRoles, editingRole, permissionOptions, onCancel, onSubmit }) {
    const [form] = Form.useForm();

    useEffect(() => {
        form.setFieldsValue(editingRole);
    }, [editingRole, form]);

    const handleSubmit = async () => {
        const payload = await form.validateFields();
        await onSubmit?.(payload);
        form.resetFields();
    };

    const handleCancel = () => {
        form.resetFields();
        onCancel?.();
    };

    return (
        <Modal
            title={editingRole.id ? 'Cập nhật role' : 'Tạo role mới'}
            open={open}
            onCancel={handleCancel}
            onOk={handleSubmit}
            okButtonProps={{ disabled: !canManageRoles }}
            width={760}
            destroyOnHidden
        >
            <Form form={form} layout="vertical" initialValues={editingRole}>
                <Form.Item label="Tên role" name="name" rules={[{ required: true, message: 'Nhập tên role' }]}>
                    <Input placeholder="VD: Module Manager" />
                </Form.Item>
                <Form.Item label="Key" name="key" rules={[{ required: true, message: 'Nhập key role' }]}>
                    <Input placeholder="vd: module-manager" disabled={editingRole.key === 'super-admin'} />
                </Form.Item>
                <Form.Item label="Mô tả" name="description">
                    <Input.TextArea rows={3} placeholder="Mô tả phạm vi của role" />
                </Form.Item>
                <Form.Item label="Permissions" name="permission_ids">
                    <Select mode="multiple" options={permissionOptions} placeholder="Chọn permissions" />
                </Form.Item>
            </Form>
        </Modal>
    );
}
