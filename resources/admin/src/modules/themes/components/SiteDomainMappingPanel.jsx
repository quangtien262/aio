import { useCallback, useEffect, useMemo, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Popconfirm from 'antd/es/popconfirm';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { CheckCircleOutlined, DeleteOutlined, EditOutlined, PlusOutlined, ReloadOutlined, StopOutlined } from '@ant-design/icons';

const { Paragraph, Text } = Typography;

function domainToWebsiteKey(domain) {
    return String(domain || '')
        .toLowerCase()
        .replace(/^https?:\/\//, '')
        .replace(/[/:?#].*$/, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 100);
}

function domainHref(domain) {
    const normalized = String(domain || '').trim();

    if (!normalized) {
        return null;
    }

    if (/^https?:\/\//i.test(normalized)) {
        return normalized;
    }

    return `https://${normalized}`;
}

export default function SiteDomainMappingPanel({ callAdminApi, runAdminAction, canManage = false, themes = [] }) {
    const [form] = Form.useForm();
    const [bulkForm] = Form.useForm();
    const [items, setItems] = useState([]);
    const [themeOptions, setThemeOptions] = useState(themes);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [editingItem, setEditingItem] = useState(null);
    const [modalOpen, setModalOpen] = useState(false);
    const [bulkModalOpen, setBulkModalOpen] = useState(false);
    const [selectedRowKeys, setSelectedRowKeys] = useState([]);

    const availableThemes = themeOptions.length ? themeOptions : themes;
    const themeNameMap = useMemo(() => new Map(availableThemes.map((theme) => [theme.key, theme.name])), [availableThemes]);

    const loadItems = useCallback(async () => {
        try {
            setLoading(true);
            setError(null);

            const payload = await callAdminApi('/admin/api/site-mappings');
            setItems(payload.data ?? []);
            setSelectedRowKeys([]);
            setThemeOptions(payload.meta?.themes ?? themes);
        } catch (loadError) {
            setError(loadError instanceof Error ? loadError.message : 'Không tải được cấu hình domain.');
        } finally {
            setLoading(false);
        }
    }, [callAdminApi, themes]);

    useEffect(() => {
        loadItems();
    }, [loadItems]);

    const openCreate = () => {
        setEditingItem(null);
        form.setFieldsValue({
            domain: '',
            website_key: '',
            theme_key: themes[0]?.key ?? themeOptions[0]?.key,
            name: '',
            status: 'active',
        });
        setModalOpen(true);
    };

    const openEdit = (item) => {
        setEditingItem(item);
        form.setFieldsValue({
            domain: item.domain,
            website_key: item.website_key,
            theme_key: item.theme_key,
            name: item.name,
            status: item.status ?? 'active',
        });
        setModalOpen(true);
    };

    const saveItem = async () => {
        const values = await form.validateFields();
        const url = editingItem ? `/admin/api/site-mappings/${editingItem.id}` : '/admin/api/site-mappings';
        const method = editingItem ? 'PUT' : 'POST';

        const ok = await runAdminAction(
            () => callAdminApi(url, { method, body: JSON.stringify(values) }),
            editingItem ? 'Đã cập nhật cấu hình domain.' : 'Đã thêm cấu hình domain.',
            loadItems,
        );

        if (ok) {
            setModalOpen(false);
            setEditingItem(null);
        }
    };

    const createBulkDomains = async () => {
        const values = await bulkForm.validateFields();
        const ok = await runAdminAction(
            () => callAdminApi('/admin/api/site-mappings/bulk', {
                method: 'POST',
                body: JSON.stringify(values),
            }),
            'Đã tạo nhanh cấu hình domain.',
            loadItems,
        );

        if (ok) {
            setBulkModalOpen(false);
            bulkForm.resetFields();
        }
    };

    const deleteItem = async (item) => {
        await runAdminAction(
            () => callAdminApi(`/admin/api/site-mappings/${item.id}`, { method: 'DELETE' }),
            'Đã xóa cấu hình domain.',
            loadItems,
        );
    };

    const bulkUpdateStatus = async (status) => {
        const ids = [...selectedRowKeys];

        if (!ids.length) {
            return;
        }

        await runAdminAction(
            () => callAdminApi('/admin/api/site-mappings/bulk/status', {
                method: 'PUT',
                body: JSON.stringify({ ids, status }),
            }),
            status === 'active' ? `Đã kích hoạt ${ids.length} domain.` : `Đã tạm tắt ${ids.length} domain.`,
            loadItems,
        );
    };

    const bulkDelete = async () => {
        const ids = [...selectedRowKeys];

        if (!ids.length) {
            return;
        }

        await runAdminAction(
            () => callAdminApi('/admin/api/site-mappings/bulk', {
                method: 'DELETE',
                body: JSON.stringify({ ids }),
            }),
            `Đã xóa ${ids.length} cấu hình domain.`,
            loadItems,
        );
    };

    const rowSelection = {
        selectedRowKeys,
        onChange: setSelectedRowKeys,
        getCheckboxProps: (item) => ({
            disabled: !canManage || !item.domain,
        }),
    };

    const selectedCount = selectedRowKeys.length;

    const columns = [
        {
            title: 'Domain',
            dataIndex: 'domain',
            key: 'domain',
            render: (domain) => {
                const href = domainHref(domain);

                if (!href) {
                    return <Text strong>Domain mặc định</Text>;
                }

                return (
                    <a href={href} target="_blank" rel="noreferrer">
                        <Text strong>{domain}</Text>
                    </a>
                );
            },
        },
        {
            title: 'Website key',
            dataIndex: 'website_key',
            key: 'website_key',
            render: (websiteKey) => <Tag color="blue">{websiteKey}</Tag>,
        },
        {
            title: 'Theme',
            dataIndex: 'theme_key',
            key: 'theme_key',
            render: (themeKey) => (
                <Space direction="vertical" size={0}>
                    <Text>{themeNameMap.get(themeKey) ?? themeKey}</Text>
                    <Text type="secondary">{themeKey}</Text>
                </Space>
            ),
        },
        {
            title: 'Trạng thái',
            dataIndex: 'status',
            key: 'status',
            width: 120,
            render: (status) => <Tag color={status === 'active' ? 'green' : 'default'}>{status}</Tag>,
        },
        {
            title: '',
            key: 'actions',
            width: 150,
            render: (_, item) => (
                <Space>
                    <Button icon={<EditOutlined />} disabled={!canManage} onClick={() => openEdit(item)} />
                    <Popconfirm
                        title="Xóa cấu hình domain?"
                        okText="Xóa"
                        cancelText="Hủy"
                        disabled={!canManage || !item.domain}
                        onConfirm={() => deleteItem(item)}
                    >
                        <Button danger icon={<DeleteOutlined />} disabled={!canManage || !item.domain} />
                    </Popconfirm>
                </Space>
            ),
        },
    ];

    return (
        <Card
            title="Cấu hình domain"
            extra={(
                <Space wrap>
                    <Button icon={<ReloadOutlined />} onClick={loadItems}>Tải lại</Button>
                    <Button disabled={!canManage} onClick={() => setBulkModalOpen(true)}>Tạo nhanh sub domain</Button>
                    <Button type="primary" icon={<PlusOutlined />} disabled={!canManage} onClick={openCreate}>Thêm domain</Button>
                </Space>
            )}
            bordered={false}
        >
            <Space direction="vertical" size={16} style={{ width: '100%' }}>
                <Paragraph style={{ marginBottom: 0 }}>
                    Mỗi dòng liên kết một domain với một website_key và theme riêng. Khi khách truy cập domain đó, frontend sẽ tự lấy đúng data và giao diện theo cấu hình này.
                </Paragraph>

                {!canManage ? (
                    <Alert type="info" showIcon message="Tài khoản hiện tại chỉ có quyền xem. Cần quyền theme.customize để thêm, sửa hoặc xóa cấu hình domain." />
                ) : null}

                {error ? <Alert type="error" showIcon message={error} /> : null}

                <Space wrap>
                    <Text type="secondary">Đã chọn {selectedCount} domain</Text>
                    <Button
                        icon={<CheckCircleOutlined />}
                        disabled={!canManage || !selectedCount}
                        onClick={() => bulkUpdateStatus('active')}
                    >
                        Kích hoạt
                    </Button>
                    <Button
                        icon={<StopOutlined />}
                        disabled={!canManage || !selectedCount}
                        onClick={() => bulkUpdateStatus('inactive')}
                    >
                        Tạm tắt
                    </Button>
                    <Popconfirm
                        title={`Xóa ${selectedCount} cấu hình domain đã chọn?`}
                        okText="Xóa"
                        cancelText="Hủy"
                        disabled={!canManage || !selectedCount}
                        onConfirm={bulkDelete}
                    >
                        <Button danger icon={<DeleteOutlined />} disabled={!canManage || !selectedCount}>
                            Xóa
                        </Button>
                    </Popconfirm>
                </Space>

                <Table
                    rowKey="id"
                    rowSelection={rowSelection}
                    loading={loading}
                    columns={columns}
                    dataSource={items}
                    pagination={false}
                    scroll={{ x: 860 }}
                />
            </Space>

            <Modal
                title={editingItem ? 'Sửa cấu hình domain' : 'Thêm cấu hình domain'}
                open={modalOpen}
                okText="Lưu"
                cancelText="Hủy"
                onOk={saveItem}
                onCancel={() => {
                    setModalOpen(false);
                    setEditingItem(null);
                }}
                destroyOnHidden
            >
                <Form form={form} layout="vertical">
                    <Form.Item
                        label="Domain"
                        name="domain"
                        rules={[
                            { required: true, message: 'Nhập domain.' },
                            { pattern: /^[A-Za-z0-9.-]+$/, message: 'Domain chỉ gồm chữ, số, dấu chấm và dấu gạch ngang.' },
                        ]}
                    >
                        <Input
                            placeholder="xd0313.demo.htvietnam.vn"
                            onBlur={(event) => {
                                const websiteKey = form.getFieldValue('website_key');
                                if (!websiteKey) {
                                    form.setFieldValue('website_key', domainToWebsiteKey(event.target.value));
                                }
                            }}
                        />
                    </Form.Item>
                    <Form.Item
                        label="Website key"
                        name="website_key"
                        extra="Dùng để tách data trong các bảng cms_ theo từng website."
                        rules={[
                            { required: true, message: 'Nhập website_key.' },
                            { pattern: /^[A-Za-z0-9_-]+$/, message: 'Chỉ dùng chữ, số, dấu gạch ngang hoặc gạch dưới.' },
                        ]}
                    >
                        <Input placeholder="xd0313-demo" />
                    </Form.Item>
                    <Form.Item
                        label="Theme"
                        name="theme_key"
                        rules={[{ required: true, message: 'Chọn theme cho domain.' }]}
                    >
                        <Select
                            showSearch
                            optionFilterProp="label"
                            options={availableThemes.map((theme) => ({
                                value: theme.key,
                                label: `${theme.key} - ${theme.name}`,
                            }))}
                        />
                    </Form.Item>
                    <Form.Item label="Tên hiển thị" name="name">
                        <Input placeholder="Demo XD0313" />
                    </Form.Item>
                    <Form.Item label="Trạng thái" name="status" rules={[{ required: true }]}>
                        <Select
                            options={[
                                { value: 'active', label: 'active' },
                                { value: 'inactive', label: 'inactive' },
                            ]}
                        />
                    </Form.Item>
                </Form>
            </Modal>

            <Modal
                title="Tạo nhanh sub domain"
                open={bulkModalOpen}
                okText="Tạo cấu hình"
                cancelText="Hủy"
                onOk={createBulkDomains}
                onCancel={() => {
                    setBulkModalOpen(false);
                    bulkForm.resetFields();
                }}
                destroyOnHidden
            >
                <Form form={bulkForm} layout="vertical">
                    <Alert
                        type="info"
                        showIcon
                        style={{ marginBottom: 16 }}
                        message="Hệ thống sẽ tạo cấu hình theo mã theme, ví dụ XD0301.demo.htvietnam.vn. Cấu hình đã tồn tại sẽ được bỏ qua."
                    />
                    <Form.Item
                        label="Domain chính"
                        name="root_domain"
                        extra={`Dự kiến tạo tối đa ${availableThemes.length} cấu hình theo danh sách theme hiện có.`}
                        rules={[
                            { required: true, message: 'Nhập domain chính.' },
                            { pattern: /^(https?:\/\/)?[A-Za-z0-9.-]+([/:?#].*)?$/, message: 'Domain chính không hợp lệ.' },
                        ]}
                    >
                        <Input placeholder="demo.htvietnam.vn" />
                    </Form.Item>
                </Form>
            </Modal>
        </Card>
    );
}
