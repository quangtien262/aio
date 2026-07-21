import { useEffect, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';

const { Paragraph, Text } = Typography;

export default function TwoFactorModal({ open, enabled, onClose, onChanged, callAdminApi }) {
    const [setup, setSetup] = useState(null);
    const [recoveryCodes, setRecoveryCodes] = useState([]);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!open) {
            setSetup(null);
            setRecoveryCodes([]);
            setError('');
        }
    }, [open]);

    const beginSetup = async ({ current_password }) => {
        setLoading(true);
        setError('');
        try {
            const payload = await callAdminApi('/admin/api/me/two-factor/setup', { method: 'POST', body: JSON.stringify({ current_password }) });
            setSetup(payload.data);
        } catch (exception) {
            setError(exception instanceof Error ? exception.message : 'Không thể khởi tạo xác thực hai lớp.');
        } finally {
            setLoading(false);
        }
    };

    const confirmSetup = async ({ code }) => {
        setLoading(true);
        setError('');
        try {
            const payload = await callAdminApi('/admin/api/me/two-factor/confirm', { method: 'POST', body: JSON.stringify({ code }) });
            setRecoveryCodes(payload.data?.recovery_codes ?? []);
            onChanged(true);
        } catch (exception) {
            setError(exception instanceof Error ? exception.message : 'Mã xác thực không chính xác.');
        } finally {
            setLoading(false);
        }
    };

    const disable = async (values) => {
        setLoading(true);
        setError('');
        try {
            await callAdminApi('/admin/api/me/two-factor', { method: 'DELETE', body: JSON.stringify(values) });
            onChanged(false);
            onClose();
        } catch (exception) {
            setError(exception instanceof Error ? exception.message : 'Không thể tắt xác thực hai lớp.');
        } finally {
            setLoading(false);
        }
    };

    return (
        <Modal title="Xác thực hai lớp" open={open} onCancel={onClose} footer={null} destroyOnHidden>
            <Space direction="vertical" size={16} style={{ width: '100%' }}>
                {error ? <Alert type="error" showIcon message={error} /> : null}

                {recoveryCodes.length ? (
                    <>
                        <Alert type="success" showIcon message="Đã bật xác thực hai lớp" description="Hãy lưu các mã khôi phục ở nơi an toàn. Mỗi mã chỉ dùng được một lần." />
                        <Paragraph copyable={{ text: recoveryCodes.join('\n') }}><Text code>{recoveryCodes.join('\n')}</Text></Paragraph>
                        <Button type="primary" onClick={onClose}>Hoàn tất</Button>
                    </>
                ) : enabled ? (
                    <>
                        <Alert type="info" showIcon message="Xác thực hai lớp đang bật" description="Để tắt, cần xác nhận mật khẩu hiện tại và mã từ ứng dụng xác thực hoặc mã khôi phục." />
                        <Form layout="vertical" onFinish={disable}>
                            <Form.Item name="current_password" label="Mật khẩu hiện tại" rules={[{ required: true }]}><Input.Password /></Form.Item>
                            <Form.Item name="code" label="Mã xác thực hoặc mã khôi phục" rules={[{ required: true }]}><Input autoComplete="one-time-code" /></Form.Item>
                            <Button danger htmlType="submit" loading={loading}>Tắt xác thực hai lớp</Button>
                        </Form>
                    </>
                ) : setup ? (
                    <>
                        <Alert type="warning" showIcon message="Thêm tài khoản vào ứng dụng xác thực" description="Nhập khóa bên dưới vào Google Authenticator, Microsoft Authenticator, 1Password hoặc ứng dụng tương thích TOTP." />
                        <Paragraph copyable><Text code>{setup.secret}</Text></Paragraph>
                        <Form layout="vertical" onFinish={confirmSetup}>
                            <Form.Item name="code" label="Mã xác thực 6 số" rules={[{ required: true }, { len: 6 }]}><Input inputMode="numeric" maxLength={6} autoComplete="one-time-code" /></Form.Item>
                            <Button type="primary" htmlType="submit" loading={loading}>Xác nhận và bật</Button>
                        </Form>
                    </>
                ) : (
                    <>
                        <Paragraph>Xác thực hai lớp bảo vệ tài khoản quản trị ngay cả khi mật khẩu bị lộ.</Paragraph>
                        <Form layout="vertical" onFinish={beginSetup}>
                            <Form.Item name="current_password" label="Xác nhận mật khẩu hiện tại" rules={[{ required: true }]}><Input.Password /></Form.Item>
                            <Button type="primary" htmlType="submit" loading={loading}>Thiết lập ngay</Button>
                        </Form>
                    </>
                )}
            </Space>
        </Modal>
    );
}
