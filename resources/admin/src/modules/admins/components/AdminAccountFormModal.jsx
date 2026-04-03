import { useEffect } from 'react';
import Button from 'antd/es/button';
import Col from 'antd/es/col';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Switch from 'antd/es/switch';
import Typography from 'antd/es/typography';

const { Text } = Typography;

export const emptyAccountForm = {
    id: null,
    name: '',
    email: '',
    is_active: true,
    role_ids: [],
    scopes: [],
};

export default function AdminAccountFormModal({ open, canManageAdmins, editingAccount, roleOptions, scopeTypeOptions, onCancel, onSubmit }) {
    const [form] = Form.useForm();

    useEffect(() => {
        form.setFieldsValue(editingAccount);
    }, [editingAccount, form]);

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
            title={editingAccount.id ? 'Cập nhật admin' : 'Tạo admin mới'}
            open={open}
            onCancel={handleCancel}
            onOk={handleSubmit}
            okButtonProps={{ disabled: !canManageAdmins }}
            width={860}
            destroyOnHidden
        >
            <Form form={form} layout="vertical" initialValues={editingAccount}>
                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="name" label="Họ tên" rules={[{ required: true, message: 'Nhập họ tên admin' }]}>
                            <Input placeholder="VD: Nguyễn Văn A" />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="email" label="Email" rules={[{ required: true, message: 'Nhập email admin' }]}>
                            <Input placeholder="admin@aio.local" />
                        </Form.Item>
                    </Col>
                </Row>

                {!editingAccount.id ? (
                    <Row gutter={16}>
                        <Col span={12}>
                            <Form.Item name="password" label="Mật khẩu" rules={[{ required: true, message: 'Nhập mật khẩu' }]}>
                                <Input.Password />
                            </Form.Item>
                        </Col>
                        <Col span={12}>
                            <Form.Item name="password_confirmation" label="Xác nhận mật khẩu" dependencies={['password']} rules={[{ required: true, message: 'Xác nhận mật khẩu' }]}>
                                <Input.Password />
                            </Form.Item>
                        </Col>
                    </Row>
                ) : null}

                <Form.Item name="is_active" label="Kích hoạt tài khoản" valuePropName="checked">
                    <Switch checkedChildren="Bật" unCheckedChildren="Tắt" />
                </Form.Item>

                <Form.Item name="role_ids" label="Roles">
                    <Select mode="multiple" options={roleOptions} placeholder="Chon roles" />
                </Form.Item>

                <Form.List name="scopes">
                    {(fields, { add, remove }) => (
                        <Space direction="vertical" style={{ width: '100%' }} size={12}>
                            <Text strong>Data Scopes</Text>
                            {fields.map((field) => (
                                <Row gutter={12} key={field.key}>
                                    <Col span={7}>
                                        <Form.Item {...field} name={[field.name, 'role_id']} label="Role" rules={[{ required: true, message: 'Chon role' }]}>
                                            <Select options={roleOptions} placeholder="Role" />
                                        </Form.Item>
                                    </Col>
                                    <Col span={7}>
                                        <Form.Item {...field} name={[field.name, 'scope_type']} label="Loại scope" rules={[{ required: true, message: 'Chọn scope' }]}>
                                            <Select options={scopeTypeOptions} placeholder="Loại scope" />
                                        </Form.Item>
                                    </Col>
                                    <Col span={8}>
                                        <Form.Item {...field} name={[field.name, 'scope_value']} label="Giá trị" rules={[{ required: true, message: 'Nhập giá trị scope' }]}>
                                            <Input placeholder="VD: cms / tenant-a / website-main" />
                                        </Form.Item>
                                    </Col>
                                    <Col span={2}>
                                        <Button danger style={{ marginTop: 30 }} onClick={() => remove(field.name)}>
                                            Xóa
                                        </Button>
                                    </Col>
                                </Row>
                            ))}
                            <Button onClick={() => add({ role_id: undefined, scope_type: undefined, scope_value: '' })}>
                                Thêm scope
                            </Button>
                        </Space>
                    )}
                </Form.List>
            </Form>
        </Modal>
    );
}
