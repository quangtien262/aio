import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';

export default function AdminPasswordFormModal({ open, canResetPassword, passwordTarget, onCancel, onSubmit }) {
    const [form] = Form.useForm();

    const handleSubmit = async () => {
        const payload = await form.validateFields();
        const didReset = await onSubmit?.(payload);

        if (!didReset) {
            return;
        }

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
                <Form.Item name="password" label="Mật khẩu mới" rules={[{ required: true, message: 'Nhập mật khẩu mới' }, { min: 8, message: 'Mật khẩu phải có ít nhất 8 ký tự' }]}>
                    <Input.Password />
                </Form.Item>
                <Form.Item
                    name="password_confirmation"
                    label="Xác nhận mật khẩu"
                    dependencies={['password']}
                    rules={[
                        { required: true, message: 'Xác nhận mật khẩu' },
                        ({ getFieldValue }) => ({
                            validator(_, value) {
                                if (!value || getFieldValue('password') === value) {
                                    return Promise.resolve();
                                }

                                return Promise.reject(new Error('Xác nhận mật khẩu không khớp.'));
                            },
                        }),
                    ]}
                >
                    <Input.Password />
                </Form.Item>
            </Form>
        </Modal>
    );
}
