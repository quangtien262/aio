import { useEffect } from 'react';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';

export default function AdminLockFormModal({ open, canLockAdmins, lockTarget, onCancel, onSubmit }) {
    const [form] = Form.useForm();

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setFieldsValue({
            reason: lockTarget?.locked_reason || 'Khóa bởi quản trị viên.',
        });
    }, [form, lockTarget, open]);

    const handleSubmit = async () => {
        const payload = await form.validateFields();
        const didLock = await onSubmit?.(payload);

        if (didLock) {
            form.resetFields();
        }
    };

    const handleCancel = () => {
        form.resetFields();
        onCancel?.();
    };

    return (
        <Modal
            title={lockTarget ? `Khóa admin: ${lockTarget.name}` : 'Khóa tài khoản admin'}
            open={open}
            onCancel={handleCancel}
            onOk={handleSubmit}
            okText="Khóa tài khoản"
            okButtonProps={{ danger: true, disabled: !canLockAdmins }}
            destroyOnHidden
        >
            <Form form={form} layout="vertical">
                <Form.Item
                    name="reason"
                    label="Lý do khóa"
                    rules={[{ required: true, message: 'Nhập lý do khóa tài khoản admin' }]}
                >
                    <Input.TextArea
                        rows={3}
                        maxLength={255}
                        placeholder="VD: Tạm khóa để rà soát phân quyền và bảo mật."
                        showCount
                    />
                </Form.Item>
            </Form>
        </Modal>
    );
}
