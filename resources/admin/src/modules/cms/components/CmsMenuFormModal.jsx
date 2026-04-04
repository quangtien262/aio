import { EditOutlined, PlusOutlined, SettingOutlined } from '@ant-design/icons';
import { useEffect, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Col from 'antd/es/col';
import Divider from 'antd/es/divider';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import List from 'antd/es/list';
import Modal from 'antd/es/modal';
import Popconfirm from 'antd/es/popconfirm';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';

export const emptyCmsMenuForm = {
    id: null,
    name: '',
    location: 'primary',
    items: [{ label: '', url: '', target: '_self' }],
};

const emptyLocationForm = { label: '', value: '' };
const { Text } = Typography;

export default function CmsMenuFormModal({ open, canManage, editingMenu, locationOptions = [], callAdminApi, runAdminAction, onLocationsChanged, onCancel, onSubmit }) {
    const [form] = Form.useForm();
    const [locationModalOpen, setLocationModalOpen] = useState(false);
    const [locationForm] = Form.useForm();
    const [editingLocation, setEditingLocation] = useState(null);
    const [locationError, setLocationError] = useState('');

    useEffect(() => {
        form.setFieldsValue({
            ...editingMenu,
            items: editingMenu?.items?.length ? editingMenu.items : [{ label: '', url: '', target: '_self' }],
        });
    }, [editingMenu, form]);

    const handleSubmit = async () => {
        const values = await form.validateFields();

        await onSubmit?.({
            ...values,
            items: (values.items ?? []).filter((item) => item?.label),
        });

        form.resetFields();
    };

    const handleCancel = () => {
        form.resetFields();
        onCancel?.();
    };

    const openLocationModal = () => {
        setEditingLocation(null);
        setLocationError('');
        locationForm.setFieldsValue(emptyLocationForm);
        setLocationModalOpen(true);
    };

    const startEditLocation = (location) => {
        setEditingLocation(location);
        setLocationError('');
        locationForm.setFieldsValue(location);
        setLocationModalOpen(true);
    };

    const handleSaveLocation = async () => {
        const values = await locationForm.validateFields();
        setLocationError('');

        const method = editingLocation ? 'PUT' : 'POST';
        const endpoint = editingLocation ? `/admin/api/cms/menu-locations/${editingLocation.value}` : '/admin/api/cms/menu-locations';
        const didSave = await runAdminAction(
            () => callAdminApi(endpoint, { method, body: JSON.stringify(values) }),
            editingLocation ? 'Đã cập nhật vị trí menu.' : 'Đã tạo vị trí menu.',
            onLocationsChanged,
        );

        if (didSave) {
            setLocationModalOpen(false);
            setEditingLocation(null);
            locationForm.resetFields();
        }
    };

    const handleDeleteLocation = async (location) => {
        await runAdminAction(
            () => callAdminApi(`/admin/api/cms/menu-locations/${location.value}`, { method: 'DELETE' }),
            'Đã xóa vị trí menu.',
            onLocationsChanged,
        );
    };

    return (
        <>
            <Modal
                title={editingMenu?.id ? 'Cập nhật menu CMS' : 'Tạo menu CMS'}
                open={open}
                onCancel={handleCancel}
                onOk={handleSubmit}
                okButtonProps={{ disabled: !canManage }}
                width={920}
                destroyOnHidden
            >
                <Form form={form} layout="vertical" initialValues={editingMenu}>
                    <Row gutter={16}>
                        <Col span={12}>
                            <Form.Item name="name" label="Tên menu" rules={[{ required: true, message: 'Nhập tên menu' }]}>
                                <Input placeholder="Main Navigation" />
                            </Form.Item>
                        </Col>
                        <Col span={12}>
                            <Form.Item
                                name="location"
                                label={(
                                    <Space size={8}>
                                        <span>Vị trí</span>
                                        <Button type="text" size="small" icon={<SettingOutlined />} onClick={openLocationModal} />
                                    </Space>
                                )}
                                rules={[{ required: true, message: 'Chọn vị trí menu' }]}
                            >
                                <Select options={locationOptions} />
                            </Form.Item>
                        </Col>
                    </Row>

                    <Form.List name="items">
                        {(fields, { add, remove }) => (
                            <Space direction="vertical" size={12} style={{ width: '100%' }}>
                                {fields.map((field, index) => (
                                    <Row gutter={12} key={field.key} align="middle">
                                        <Col span={7}>
                                            <Form.Item {...field} name={[field.name, 'label']} label={index === 0 ? 'Label' : undefined} rules={[{ required: true, message: 'Nhập label' }]}>
                                                <Input placeholder="Giới thiệu" />
                                            </Form.Item>
                                        </Col>
                                        <Col span={11}>
                                            <Form.Item {...field} name={[field.name, 'url']} label={index === 0 ? 'URL' : undefined}>
                                                <Input placeholder="/gioi-thieu" />
                                            </Form.Item>
                                        </Col>
                                        <Col span={4}>
                                            <Form.Item {...field} name={[field.name, 'target']} label={index === 0 ? 'Target' : undefined}>
                                                <Select options={[{ label: 'Self', value: '_self' }, { label: 'Blank', value: '_blank' }]} />
                                            </Form.Item>
                                        </Col>
                                        <Col span={2}>
                                            <Button danger onClick={() => remove(field.name)} style={{ marginTop: index === 0 ? 29 : 0 }}>
                                                Xóa
                                            </Button>
                                        </Col>
                                    </Row>
                                ))}

                                <Button onClick={() => add({ label: '', url: '', target: '_self' })}>Thêm item</Button>
                            </Space>
                        )}
                    </Form.List>
                </Form>
            </Modal>

            <Modal
                title="Quản lý vị trí menu"
                open={locationModalOpen}
                onCancel={() => {
                    setLocationModalOpen(false);
                    setEditingLocation(null);
                    setLocationError('');
                    locationForm.resetFields();
                }}
                onOk={handleSaveLocation}
                okText={editingLocation ? 'Cập nhật' : 'Tạo vị trí'}
                width={720}
                destroyOnHidden
            >
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Alert type="info" showIcon message="Vị trí menu là danh mục dùng chung cho toàn website. Khi đang có menu sử dụng một vị trí, hệ thống sẽ chặn xóa hoặc đổi mã vị trí đó." />

                    {locationError ? <Alert type="error" showIcon message={locationError} /> : null}

                    <Form form={locationForm} layout="vertical" initialValues={emptyLocationForm}>
                        <Row gutter={16}>
                            <Col span={12}>
                                <Form.Item name="label" label="Tên hiển thị" rules={[{ required: true, message: 'Nhập tên hiển thị vị trí' }]}>
                                    <Input placeholder="Primary Header" />
                                </Form.Item>
                            </Col>
                            <Col span={12}>
                                <Form.Item name="value" label="Mã vị trí" extra="Để trống nếu muốn hệ thống tự slug từ tên hiển thị.">
                                    <Input placeholder="primary-header" />
                                </Form.Item>
                            </Col>
                        </Row>
                    </Form>

                    <Divider style={{ margin: 0 }} />

                    <List
                        size="small"
                        bordered
                        dataSource={locationOptions}
                        locale={{ emptyText: 'Chưa có vị trí menu nào.' }}
                        renderItem={(item) => (
                            <List.Item
                                actions={[
                                    <Button key={`edit-${item.value}`} type="text" icon={<EditOutlined />} onClick={() => startEditLocation(item)} />,
                                    <Popconfirm key={`delete-${item.value}`} title="Xóa vị trí menu này?" onConfirm={() => handleDeleteLocation(item)}>
                                        <Button danger type="text" icon={<PlusOutlined rotate={45} />} />
                                    </Popconfirm>,
                                ]}
                            >
                                <Space direction="vertical" size={0}>
                                    <Text strong>{item.label}</Text>
                                    <Text type="secondary">{item.value}</Text>
                                </Space>
                            </List.Item>
                        )}
                    />
                </Space>
            </Modal>
        </>
    );
}
