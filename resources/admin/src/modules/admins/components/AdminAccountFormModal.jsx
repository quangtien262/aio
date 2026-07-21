import { useEffect } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Col from 'antd/es/col';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';

const { Text } = Typography;

export const emptyAccountForm = {
    id: null,
    name: '',
    username: '',
    email: '',
    status: 'active',
    is_system_owner: false,
    assignments: [],
};

export default function AdminAccountFormModal({ open, canManageAdmins, editingAccount, roleOptions, scopeTypeOptions, websiteOptions, onCancel, onSubmit }) {
    const [form] = Form.useForm();

    useEffect(() => {
        form.setFieldsValue(editingAccount);
    }, [editingAccount, form]);

    const handleSubmit = async () => {
        const payload = await form.validateFields();
        const didSave = await onSubmit?.(payload);
        if (didSave) form.resetFields();
    };

    const handleCancel = () => {
        form.resetFields();
        onCancel?.();
    };

    return (
        <Modal
            title={editingAccount.id ? 'Cập nhật tài khoản quản trị' : 'Tạo tài khoản quản trị'}
            open={open}
            onCancel={handleCancel}
            onOk={handleSubmit}
            okButtonProps={{ disabled: !canManageAdmins }}
            width={900}
            destroyOnHidden
        >
            <Form form={form} layout="vertical" initialValues={editingAccount}>
                {editingAccount.is_system_owner ? (
                    <Alert type="info" showIcon message="System Owner luôn hoạt động, luôn có toàn quyền và không thể thay đổi vai trò." style={{ marginBottom: 16 }} />
                ) : null}

                <Row gutter={16}>
                    <Col xs={24} md={8}>
                        <Form.Item name="name" label="Họ tên" rules={[{ required: true, message: 'Nhập họ tên' }]}>
                            <Input />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={8}>
                        <Form.Item name="username" label="Username" rules={[{ required: true }, { pattern: /^[A-Za-z0-9._-]+$/, message: 'Chỉ dùng chữ, số, dấu chấm, gạch dưới hoặc gạch ngang' }]}>
                            <Input />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={8}>
                        <Form.Item name="email" label="Email" rules={[{ required: true }, { type: 'email' }]}>
                            <Input />
                        </Form.Item>
                    </Col>
                </Row>

                {!editingAccount.id ? (
                    <Row gutter={16}>
                        <Col xs={24} md={12}>
                            <Form.Item name="password" label="Mật khẩu tạm thời" rules={[{ required: true }, { min: 12, message: 'Ít nhất 12 ký tự' }]}>
                                <Input.Password />
                            </Form.Item>
                        </Col>
                        <Col xs={24} md={12}>
                            <Form.Item name="password_confirmation" label="Xác nhận mật khẩu" dependencies={['password']} rules={[{ required: true }, ({ getFieldValue }) => ({ validator(_, value) { return !value || getFieldValue('password') === value ? Promise.resolve() : Promise.reject(new Error('Mật khẩu xác nhận không khớp')); } })]}>
                                <Input.Password />
                            </Form.Item>
                        </Col>
                    </Row>
                ) : null}

                <Form.Item name="status" label="Trạng thái">
                    <Select disabled={editingAccount.is_system_owner} options={[
                        { value: 'active', label: 'Đang hoạt động' },
                        { value: 'suspended', label: 'Tạm ngừng' },
                        { value: 'archived', label: 'Lưu trữ' },
                    ]} />
                </Form.Item>

                <Form.List name="assignments">
                    {(fields, { add, remove }) => (
                        <Space direction="vertical" style={{ width: '100%' }} size={12}>
                            <Text strong>Vai trò và phạm vi</Text>
                            {fields.map((field) => (
                                <Row gutter={12} key={field.key}>
                                    <Col xs={24} md={8}>
                                        <Form.Item {...field} name={[field.name, 'role_id']} label="Vai trò" rules={[{ required: true }]}>
                                            <Select options={roleOptions} />
                                        </Form.Item>
                                    </Col>
                                    <Col xs={24} md={6}>
                                        <Form.Item {...field} name={[field.name, 'scope_type']} label="Phạm vi" rules={[{ required: true }]}>
                                            <Select options={scopeTypeOptions} />
                                        </Form.Item>
                                    </Col>
                                    <Col xs={20} md={8}>
                                        <Form.Item {...field} name={[field.name, 'scope_value']} label="Website">
                                            <Select allowClear options={websiteOptions} placeholder="Chỉ chọn khi phạm vi là Website" />
                                        </Form.Item>
                                    </Col>
                                    <Col xs={4} md={2}>
                                        <Button danger style={{ marginTop: 30 }} disabled={editingAccount.is_system_owner} onClick={() => remove(field.name)}>Xóa</Button>
                                    </Col>
                                </Row>
                            ))}
                            <Button disabled={editingAccount.is_system_owner} onClick={() => add({ role_id: undefined, scope_type: 'global', scope_value: null })}>
                                Thêm vai trò
                            </Button>
                        </Space>
                    )}
                </Form.List>
            </Form>
        </Modal>
    );
}
