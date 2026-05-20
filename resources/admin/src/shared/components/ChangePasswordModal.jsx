import { useState } from 'react';
import Modal from 'antd/es/modal';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Button from 'antd/es/button';

export default function ChangePasswordModal({ open, onClose, callAdminApi, runAdminAction, adminId }) {
    const [loading, setLoading] = useState(false);

    const handleSubmit = async (values) => {
        setLoading(true);

        try {
            await runAdminAction(
                () => callAdminApi(`/admin/api/admins/${adminId}/password`, { method: 'PUT', body: JSON.stringify(values) }),
                'Đã cập nhật mật khẩu.',
                onClose,
            );
        } catch (err) {
            // runAdminAction will show message on success; on error we can show Modal error via catch
            // Convert to thrown Error so caller can inspect if needed
        } finally {
            setLoading(false);
        }
    };

    return (
        <Modal
            title="Đổi mật khẩu"
            open={open}
            onCancel={onClose}
            footer={null}
            destroyOnHidden
        >
            <Form layout="vertical" onFinish={handleSubmit}>
                <Form.Item name="current_password" label="Mật khẩu hiện tại" rules={[{ required: true, message: 'Vui lòng nhập mật khẩu hiện tại.' }]}>
                    <Input.Password />
                </Form.Item>

                <Form.Item name="password" label="Mật khẩu mới" rules={[{ required: true, message: 'Vui lòng nhập mật khẩu mới.' }, { min: 8, message: 'Mật khẩu ít nhất 8 ký tự.' }]}>
                    <Input.Password />
                </Form.Item>

                <Form.Item name="password_confirmation" label="Xác nhận mật khẩu" dependencies={["password"]} rules={[{ required: true, message: 'Vui lòng xác nhận mật khẩu.' }, ({ getFieldValue }) => ({ validator(_, value) { if (!value || getFieldValue('password') === value) { return Promise.resolve(); } return Promise.reject(new Error('Xác nhận mật khẩu không khớp.')); }, })]}>
                    <Input.Password />
                </Form.Item>

                <Form.Item>
                    <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                        <Button onClick={onClose}>Hủy</Button>
                        <Button type="primary" htmlType="submit" loading={loading}>Lưu</Button>
                    </div>
                </Form.Item>
            </Form>
        </Modal>
    );
}
