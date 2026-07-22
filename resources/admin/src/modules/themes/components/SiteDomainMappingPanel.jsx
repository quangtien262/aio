import { useCallback, useEffect, useMemo, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Checkbox from 'antd/es/checkbox';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Popconfirm from 'antd/es/popconfirm';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Switch from 'antd/es/switch';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { CheckCircleOutlined, CopyOutlined, DatabaseOutlined, DeleteOutlined, EditOutlined, PlusOutlined, ReloadOutlined, StopOutlined } from '@ant-design/icons';

const { Paragraph, Text } = Typography;

const contentModeOptions = [
    { value: 'blank', label: 'Bỏ trống' },
    { value: 'sample', label: 'Tạo dữ liệu mẫu' },
    { value: 'copy_main', label: 'Copy từ website-main' },
];

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
    const [copyForm] = Form.useForm();
    const [demoForm] = Form.useForm();
    const [items, setItems] = useState([]);
    const [themeOptions, setThemeOptions] = useState(themes);
    const [demoPresetsByTheme, setDemoPresetsByTheme] = useState({});
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [editingItem, setEditingItem] = useState(null);
    const [modalOpen, setModalOpen] = useState(false);
    const [bulkModalOpen, setBulkModalOpen] = useState(false);
    const [bulkSubmitting, setBulkSubmitting] = useState(false);
    const [copySource, setCopySource] = useState(null);
    const [demoSite, setDemoSite] = useState(null);
    const [selectedRowKeys, setSelectedRowKeys] = useState([]);
    const [deleteContentOnRemove, setDeleteContentOnRemove] = useState(false);
    const [checklistUpdatingSiteId, setChecklistUpdatingSiteId] = useState(null);

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
            setDemoPresetsByTheme(payload.meta?.demo_presets_by_theme ?? {});
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
            content_mode: 'blank',
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
        setBulkSubmitting(true);

        try {
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
        } finally {
            setBulkSubmitting(false);
        }
    };

    const deleteItem = async (item) => {
        await runAdminAction(
            () => callAdminApi(`/admin/api/site-mappings/${item.id}`, {
                method: 'DELETE',
                body: JSON.stringify({ delete_content: deleteContentOnRemove }),
            }),
            'Đã xóa cấu hình domain.',
            loadItems,
        );
    };

    const openCopy = (item) => {
        setCopySource(item);
        copyForm.resetFields();
    };

    const openDemoData = (item) => {
        const presets = demoPresetsByTheme[item.theme_key] ?? [];
        setDemoSite(item);
        demoForm.setFieldsValue({ preset: presets[0]?.key });
    };

    const createDemoData = async () => {
        const values = await demoForm.validateFields();
        const ok = await runAdminAction(
            () => callAdminApi(`/admin/api/site-mappings/${demoSite.id}/demo-data`, {
                method: 'POST',
                body: JSON.stringify(values),
            }),
            `Đã tạo data test cho ${demoSite.domain || demoSite.website_key}.`,
            loadItems,
        );

        if (ok) {
            setDemoSite(null);
            demoForm.resetFields();
        }
    };

    const copyContent = async () => {
        const values = await copyForm.validateFields();
        const target = items.find((item) => item.id === values.target_site_id);
        const ok = await runAdminAction(
            () => callAdminApi(`/admin/api/site-mappings/${copySource.id}/copy-content`, {
                method: 'POST',
                body: JSON.stringify(values),
            }),
            `Đã sao chép dữ liệu sang ${target?.domain || target?.website_key || 'domain đã chọn'}.`,
            loadItems,
        );

        if (ok) {
            setCopySource(null);
            copyForm.resetFields();
        }
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

    const updateChecklist = async (item, field, checked) => {
        const previousItem = item;
        setChecklistUpdatingSiteId(item.id);
        setError(null);
        setItems((current) => current.map((entry) => (
            entry.id === item.id
                ? { ...entry, checklist: { ...entry.checklist, [field]: checked } }
                : entry
        )));

        try {
            const payload = await callAdminApi(`/admin/api/site-mappings/${item.id}/checklist`, {
                method: 'PATCH',
                body: JSON.stringify({ [field]: checked }),
            });
            setItems((current) => current.map((entry) => (
                entry.id === item.id ? payload.data : entry
            )));
        } catch (updateError) {
            setItems((current) => current.map((entry) => (
                entry.id === item.id ? previousItem : entry
            )));
            setError(updateError instanceof Error ? updateError.message : 'Không cập nhật được checklist domain.');
        } finally {
            setChecklistUpdatingSiteId(null);
        }
    };

    const bulkDelete = async () => {
        const ids = [...selectedRowKeys];

        if (!ids.length) {
            return;
        }

        await runAdminAction(
            () => callAdminApi('/admin/api/site-mappings/bulk', {
                method: 'DELETE',
                body: JSON.stringify({ ids, delete_content: deleteContentOnRemove }),
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
            title: 'Checklist',
            key: 'checklist',
            width: 220,
            align: 'center',
            render: (_, item) => (
                <Space size={8} wrap>
                    <Switch
                        checked={Boolean(item.checklist?.tested)}
                        checkedChildren="Đã test"
                        unCheckedChildren="Chưa test"
                        loading={checklistUpdatingSiteId === item.id}
                        disabled={!canManage || checklistUpdatingSiteId === item.id}
                        onChange={(checked) => updateChecklist(item, 'tested', checked)}
                    />
                    <Switch
                        checked={Boolean(item.checklist?.demo_data_created)}
                        checkedChildren="Có data"
                        unCheckedChildren="Chưa data"
                        loading={checklistUpdatingSiteId === item.id}
                        disabled={!canManage || checklistUpdatingSiteId === item.id}
                        onChange={(checked) => updateChecklist(item, 'demo_data_created', checked)}
                    />
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
            width: 300,
            render: (_, item) => (
                <Space>
                    <Button
                        title="Tạo data test"
                        icon={<DatabaseOutlined />}
                        disabled={!canManage || !(demoPresetsByTheme[item.theme_key]?.length)}
                        onClick={() => openDemoData(item)}
                    >
                        Tạo data
                    </Button>
                    <Button
                        title="Sao chép dữ liệu sang domain khác"
                        icon={<CopyOutlined />}
                        disabled={!canManage || items.length < 2}
                        onClick={() => openCopy(item)}
                    />
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
                    <Checkbox
                        checked={deleteContentOnRemove}
                        disabled={!canManage}
                        onChange={(event) => setDeleteContentOnRemove(event.target.checked)}
                    >
                        Xóa cả dữ liệu website_key khi xóa domain
                    </Checkbox>
                </Space>

                <Table
                    rowKey="id"
                    rowSelection={rowSelection}
                    loading={loading}
                    columns={columns}
                    dataSource={items}
                    pagination={false}
                    scroll={{ x: 1080 }}
                />
            </Space>

            <Modal
                title="Tạo data test cho domain"
                open={demoSite !== null}
                okText="Xác nhận tạo data"
                cancelText="Hủy"
                onOk={createDemoData}
                onCancel={() => {
                    setDemoSite(null);
                    demoForm.resetFields();
                }}
                destroyOnHidden
            >
                <Alert
                    type="warning"
                    showIcon
                    style={{ marginBottom: 16 }}
                    message="Dữ liệu test cũ do hệ thống tạo cho domain này sẽ được làm mới. Dữ liệu nhập thủ công không bị xóa."
                />
                <Form form={demoForm} layout="vertical">
                    <Form.Item label="Domain">
                        <Input
                            value={demoSite ? `${demoSite.domain || 'Domain mặc định'} (${demoSite.website_key})` : ''}
                            disabled
                        />
                    </Form.Item>
                    <Form.Item label="Theme">
                        <Input
                            value={demoSite ? `${demoSite.theme_key} - ${themeNameMap.get(demoSite.theme_key) ?? demoSite.theme_key}` : ''}
                            disabled
                        />
                    </Form.Item>
                    <Form.Item
                        label="Chọn lĩnh vực"
                        name="preset"
                        rules={[{ required: true, message: 'Chọn lĩnh vực dữ liệu test cần tạo.' }]}
                    >
                        <Select
                            showSearch
                            optionFilterProp="label"
                            placeholder="Chọn lĩnh vực"
                            options={(demoPresetsByTheme[demoSite?.theme_key] ?? []).map((preset) => ({
                                value: preset.key,
                                label: preset.label,
                                title: preset.description,
                            }))}
                        />
                    </Form.Item>
                </Form>
            </Modal>

            <Modal
                title="Sao chép dữ liệu sang domain khác"
                open={copySource !== null}
                okText="Sao chép dữ liệu"
                cancelText="Hủy"
                onOk={copyContent}
                onCancel={() => {
                    setCopySource(null);
                    copyForm.resetFields();
                }}
                destroyOnHidden
            >
                <Alert
                    type="warning"
                    showIcon
                    style={{ marginBottom: 16 }}
                    message="Dữ liệu trùng slug hoặc mã sản phẩm ở domain đích sẽ được cập nhật. Các dữ liệu khác vẫn được giữ nguyên."
                />
                <Form form={copyForm} layout="vertical">
                    <Form.Item label="Domain nguồn">
                        <Input
                            value={copySource ? `${copySource.domain || 'Domain mặc định'} (${copySource.website_key})` : ''}
                            disabled
                        />
                    </Form.Item>
                    <Form.Item
                        label="Chọn domain đích"
                        name="target_site_id"
                        rules={[{ required: true, message: 'Chọn domain nhận dữ liệu.' }]}
                    >
                        <Select
                            showSearch
                            optionFilterProp="label"
                            placeholder="Chọn domain đích"
                            options={items
                                .filter((item) => item.id !== copySource?.id)
                                .map((item) => ({
                                    value: item.id,
                                    label: `${item.domain || 'Domain mặc định'} (${item.website_key})`,
                                }))}
                        />
                    </Form.Item>
                    <Paragraph type="secondary" style={{ marginBottom: 0 }}>
                        Hệ thống sẽ sao chép landing page, page tĩnh, menu, banner, media, sản phẩm, tin tức, dự án, dịch vụ, team, testimonial và các danh mục tương ứng; dữ liệu trùng khóa ở domain đích sẽ được cập nhật.
                    </Paragraph>
                </Form>
            </Modal>

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
                    {!editingItem ? (
                        <Form.Item
                            label="Dữ liệu ban đầu"
                            name="content_mode"
                            extra="Bỏ trống chỉ tạo cấu hình domain. Tạo dữ liệu mẫu sẽ sinh data riêng cho website_key. Copy từ website-main sẽ sao chép dữ liệu hiện có của site mặc định."
                            rules={[{ required: true, message: 'Chọn cách khởi tạo dữ liệu.' }]}
                        >
                            <Select options={contentModeOptions} />
                        </Form.Item>
                    ) : null}
                </Form>
            </Modal>

            <Modal
                title="Tạo nhanh sub domain"
                open={bulkModalOpen}
                okText="Tạo cấu hình"
                cancelText="Hủy"
                confirmLoading={bulkSubmitting}
                okButtonProps={{ disabled: bulkSubmitting }}
                cancelButtonProps={{ disabled: bulkSubmitting }}
                onOk={createBulkDomains}
                onCancel={() => {
                    if (bulkSubmitting) {
                        return;
                    }

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
                    <Form.Item
                        label="Dữ liệu ban đầu"
                        name="content_mode"
                        initialValue="blank"
                        extra="Áp dụng cho tất cả domain mới được tạo. Các cấu hình đã tồn tại vẫn được bỏ qua."
                        rules={[{ required: true, message: 'Chọn cách khởi tạo dữ liệu.' }]}
                    >
                        <Select options={contentModeOptions} />
                    </Form.Item>
                </Form>
            </Modal>
        </Card>
    );
}
