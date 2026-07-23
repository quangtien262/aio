import { adminApi } from '../config/routes';
import { useState } from 'react';
import Modal from 'antd/es/modal';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Button from 'antd/es/button';

export default function ChangePasswordModal({ open, onClose, callAdminApi, runAdminAction, forceChange = false }) {
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (values) => {
        setLoading(true);

        try {
            await runAdminAction(
                () => callAdminApi(adminApi('me/password'), { method: 'PUT', body: JSON.stringify(values) }),
                'Đã cập nhật mật khẩu.',
                onClose,
            );
        } finally {
            setLoading(false);
        }
    };

    return (
        <Modal
            title={forceChange ? 'Đổi mật khẩu trước khi tiếp tục' : 'Đổi mật khẩu'}
            open={open}
            onCancel={forceChange ? undefined : onClose}
            closable={!forceChange}
            maskClosable={!forceChange}
            keyboard={!forceChange}
            footer={null}
            destroyOnHidden
        >
            <Form layout="vertical" onFinish={handleSubmit}>
                <Form.Item name="current_password" label="Mật khẩu hiện tại" rules={[{ required: true, message: 'Vui lòng nhập mật khẩu hiện tại.' }]}>
                    <Input.Password />
                </Form.Item>

                <Form.Item
                    name="password"
                    label="Mật khẩu mới"
                    extra="Ít nhất 12 ký tự, gồm chữ hoa, chữ thường, số và ký tự đặc biệt."
                    rules={[
                        { required: true, message: 'Vui lòng nhập mật khẩu mới.' },
                        { min: 12, message: 'Mật khẩu cần ít nhất 12 ký tự.' },
                        { pattern: /[a-z]/, message: 'Mật khẩu cần có ít nhất một chữ thường.' },
                        { pattern: /[A-Z]/, message: 'Mật khẩu cần có ít nhất một chữ hoa.' },
                        { pattern: /[0-9]/, message: 'Mật khẩu cần có ít nhất một chữ số.' },
                        { pattern: /[^A-Za-z0-9]/, message: 'Mật khẩu cần có ít nhất một ký tự đặc biệt.' },
                        ({ getFieldValue }) => ({
                            validator(_, value) {
                                if (!value || value !== getFieldValue('current_password')) return Promise.resolve();
                                return Promise.reject(new Error('Mật khẩu mới phải khác mật khẩu hiện tại.'));
                            },
                        }),
                    ]}
                >
                    <Input.Password />
                </Form.Item>

                <Form.Item name="password_confirmation" label="Xác nhận mật khẩu" dependencies={['password']} rules={[{ required: true, message: 'Vui lòng xác nhận mật khẩu.' }, ({ getFieldValue }) => ({ validator(_, value) { if (!value || getFieldValue('password') === value) { return Promise.resolve(); } return Promise.reject(new Error('Xác nhận mật khẩu không khớp.')); }, })]}>
                    <Input.Password />
                </Form.Item>

                <Form.Item>
                    <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                        {!forceChange ? <Button onClick={onClose}>Hủy</Button> : null}
                        <Button type="primary" htmlType="submit" loading={loading}>Lưu</Button>
                    </div>
                </Form.Item>
            </Form>
        </Modal>
    );
}
