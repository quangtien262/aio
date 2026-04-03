import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';

export default function AdminPasswordFormModal({ open, canResetPassword, passwordTarget, onCancel, onSubmit }) {
    const [form] = Form.useForm();

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
            title={passwordTarget ? `Đặt lại mật khẩu: ${passwordTarget.name}` : 'Đặt lại mật khẩu'}
            open={open}
            onCancel={handleCancel}
            onOk={handleSubmit}
            okButtonProps={{ disabled: !canResetPassword }}
            destroyOnHidden
        >
            <Form form={form} layout="vertical">
                <Form.Item name="password" label="Mật khẩu mới" rules={[{ required: true, message: 'Nhập mật khẩu mới' }]}>
                    <Input.Password />
                </Form.Item>
                <Form.Item name="password_confirmation" label="Xác nhận mật khẩu" dependencies={['password']} rules={[{ required: true, message: 'Xác nhận mật khẩu' }]}>
                    <Input.Password />
                </Form.Item>
            </Form>
        </Modal>
    );
}
