import { useMemo, useState } from 'react';
import DeleteOutlined from '@ant-design/icons/DeleteOutlined';
import EditOutlined from '@ant-design/icons/EditOutlined';
import MoreOutlined from '@ant-design/icons/MoreOutlined';
import CopyOutlined from '@ant-design/icons/CopyOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Dropdown from 'antd/es/dropdown';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import message from 'antd/es/message';
import Modal from 'antd/es/modal';
import Pagination from 'antd/es/pagination';
import Space from 'antd/es/space';
import Statistic from 'antd/es/statistic';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import useAdminRouteResource from '../../shared/hooks/useAdminRouteResource';

const { Paragraph, Text, Title } = Typography;
const { TextArea } = Input;
const EMAIL_COPY_BATCH_SIZE = 200;

function formatDateTime(value) {
    if (!value) {
        return 'Chưa có';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString('vi-VN');
}

function normalizeEmailList(value) {
    return String(value ?? '')
        .split(/[\n,;]+/)
        .map((email) => email.trim().toLowerCase())
        .filter(Boolean);
}

export default function NewsletterSubscribersRoutePage({ canAccess, callAdminApi }) {
    const [messageApi, messageContextHolder] = message.useMessage();
    const [editForm] = Form.useForm();
    const [bulkEditForm] = Form.useForm();
    const [keyword, setKeyword] = useState('');
    const [selectedRowKeys, setSelectedRowKeys] = useState([]);
    const [editingSubscriber, setEditingSubscriber] = useState(null);
    const [bulkEditOpen, setBulkEditOpen] = useState(false);
    const [copyModalOpen, setCopyModalOpen] = useState(false);
    const [copyPage, setCopyPage] = useState(1);
    const { data, loading, error, reload } = useAdminRouteResource({
        enabled: canAccess,
        loader: async () => {
            const payload = await callAdminApi('/admin/api/newsletter-subscribers');

            return payload.data ?? { stats: {}, subscribers: [] };
        },
        cacheKey: 'admin.route.newsletter',
    });

    const subscribers = data?.subscribers ?? [];
    const selectedSubscribers = useMemo(
        () => subscribers.filter((subscriber) => selectedRowKeys.includes(subscriber.id)),
        [selectedRowKeys, subscribers],
    );

    const filteredSubscribers = useMemo(() => {
        const normalizedKeyword = keyword.trim().toLowerCase();

        return subscribers.filter((subscriber) => {
            if (normalizedKeyword === '') {
                return true;
            }

            return [subscriber.email, subscriber.name, subscriber.phone, subscriber.source]
                .some((value) => String(value ?? '').toLowerCase().includes(normalizedKeyword));
        });
    }, [keyword, subscribers]);

    const copyEmails = useMemo(() => {
        const source = selectedSubscribers.length ? selectedSubscribers : filteredSubscribers;
        const uniqueEmails = new Set();

        source.forEach((subscriber) => {
            if (subscriber.email) {
                uniqueEmails.add(String(subscriber.email).trim().toLowerCase());
            }
        });

        return [...uniqueEmails];
    }, [filteredSubscribers, selectedSubscribers]);
    const copyPageCount = Math.max(1, Math.ceil(copyEmails.length / EMAIL_COPY_BATCH_SIZE));
    const copyPageEmails = copyEmails.slice((copyPage - 1) * EMAIL_COPY_BATCH_SIZE, copyPage * EMAIL_COPY_BATCH_SIZE);
    const copyText = copyPageEmails.join(', ');

    const runAction = async (action, successMessage) => {
        try {
            await action();
            messageApi.success(successMessage);
            await reload();
            return true;
        } catch (actionError) {
            messageApi.error(actionError instanceof Error ? actionError.message : 'Thao tác không thành công.');
            return false;
        }
    };

    const openEditSubscriber = (subscriber) => {
        setEditingSubscriber(subscriber);
        editForm.setFieldsValue({
            email: subscriber.email ?? '',
            name: subscriber.name ?? '',
            phone: subscriber.phone ?? '',
        });
    };

    const handleSaveSubscriber = async () => {
        if (!editingSubscriber?.id) {
            return;
        }

        const values = await editForm.validateFields();
        const didSave = await runAction(
            () => callAdminApi(`/admin/api/newsletter-subscribers/${editingSubscriber.id}`, {
                method: 'PUT',
                body: JSON.stringify(values),
            }),
            'Đã cập nhật email nhận tin.',
        );

        if (didSave) {
            setEditingSubscriber(null);
            editForm.resetFields();
        }
    };

    const openBulkEdit = () => {
        if (!selectedSubscribers.length) {
            return;
        }

        bulkEditForm.setFieldsValue({
            emails: selectedSubscribers.map((subscriber) => subscriber.email).join('\n'),
        });
        setBulkEditOpen(true);
    };

    const handleBulkEdit = async () => {
        const values = await bulkEditForm.validateFields();
        const emails = normalizeEmailList(values.emails);

        if (emails.length !== selectedSubscribers.length) {
            messageApi.warning(`Cần nhập đúng ${selectedSubscribers.length} email, mỗi dòng tương ứng một email đang chọn.`);
            return;
        }

        const didSave = await runAction(async () => {
            for (const [index, subscriber] of selectedSubscribers.entries()) {
                await callAdminApi(`/admin/api/newsletter-subscribers/${subscriber.id}`, {
                    method: 'PUT',
                    body: JSON.stringify({
                        email: emails[index],
                        name: subscriber.name ?? '',
                        phone: subscriber.phone ?? '',
                    }),
                });
            }
        }, `Đã cập nhật ${selectedSubscribers.length} email.`);

        if (didSave) {
            setBulkEditOpen(false);
            setSelectedRowKeys([]);
            bulkEditForm.resetFields();
        }
    };

    const deleteSubscribers = async (items) => {
        const didDelete = await runAction(async () => {
            for (const subscriber of items) {
                await callAdminApi(`/admin/api/newsletter-subscribers/${subscriber.id}`, { method: 'DELETE' });
            }
        }, `Đã xóa ${items.length} email nhận tin.`);

        if (didDelete) {
            setSelectedRowKeys([]);
        }
    };

    const confirmDeleteSubscribers = (items) => {
        if (!items.length) {
            return;
        }

        Modal.confirm({
            title: items.length === 1 ? 'Xóa email nhận tin này?' : `Xóa ${items.length} email nhận tin đã chọn?`,
            content: 'Thao tác này không thể hoàn tác.',
            okText: 'Xóa',
            okButtonProps: { danger: true },
            cancelText: 'Hủy',
            onOk: () => deleteSubscribers(items),
        });
    };

    const openCopyModal = () => {
        setCopyPage(1);
        setCopyModalOpen(true);
    };

    const handleCopyEmails = async () => {
        if (!copyText) {
            messageApi.warning('Không có email để copy.');
            return;
        }

        await navigator.clipboard.writeText(copyText);
        messageApi.success(`Đã copy ${copyPageEmails.length} email.`);
    };

    if (loading && !data) {
        return <Card loading title="Newsletter" />;
    }

    if (error) {
        return <Alert type="error" showIcon message={error} />;
    }

    const stats = data?.stats ?? {};
    const rowSelection = {
        selectedRowKeys,
        onChange: setSelectedRowKeys,
        preserveSelectedRowKeys: true,
    };
    const bulkMenuItems = [
        {
            key: 'bulk-edit',
            label: 'Sửa nhiều email',
            icon: <EditOutlined />,
            disabled: !selectedRowKeys.length,
        },
        {
            key: 'bulk-delete',
            label: 'Xóa email đã chọn',
            icon: <DeleteOutlined />,
            danger: true,
            disabled: !selectedRowKeys.length,
        },
    ];
    const columns = [
        {
            title: 'Email',
            dataIndex: 'email',
            key: 'email',
            render: (value) => <Text strong>{value}</Text>,
        },
        {
            title: 'Thông tin',
            key: 'identity',
            render: (_, record) => (
                <Space direction="vertical" size={0}>
                    <Text strong>{record.name || 'Khách vãng lai'}</Text>
                    <Text type="secondary">{record.phone || 'Chưa có số điện thoại'}</Text>
                </Space>
            ),
        },
        {
            title: 'Nguồn',
            dataIndex: 'source',
            key: 'source',
            render: (value) => <Tag>{value}</Tag>,
        },
        {
            title: 'Liên kết',
            dataIndex: 'customer_id',
            key: 'customer_id',
            render: (value) => value ? <Tag color="green">Customer #{value}</Tag> : <Tag>Guest</Tag>,
        },
        {
            title: 'Thời gian đăng ký',
            dataIndex: 'subscribed_at',
            key: 'subscribed_at',
            render: (value) => formatDateTime(value),
        },
        {
            title: 'Tác vụ',
            key: 'actions',
            render: (_, record) => (
                <Dropdown
                    trigger={['click']}
                    menu={{
                        items: [
                            { key: 'edit', label: 'Sửa email', icon: <EditOutlined /> },
                            { key: 'delete', label: 'Xóa email', icon: <DeleteOutlined />, danger: true },
                        ],
                        onClick: ({ key }) => {
                            if (key === 'edit') {
                                openEditSubscriber(record);
                            }

                            if (key === 'delete') {
                                confirmDeleteSubscribers([record]);
                            }
                        },
                    }}
                >
                    <Button size="small" icon={<MoreOutlined />}>Tác vụ</Button>
                </Dropdown>
            ),
        },
    ];

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            {messageContextHolder}
            <Card className="hero-card">
                <Text className="header-label">Retention</Text>
                <Title level={2} style={{ marginTop: 0 }}>Danh sách khách hàng đăng ký nhận tin</Title>
                <Paragraph style={{ maxWidth: 760, marginBottom: 0 }}>
                    Tất cả email đăng ký từ header storefront sẽ được gom về đây để đội vận hành theo dõi và tái sử dụng cho các chiến dịch nội dung.
                </Paragraph>
            </Card>

            <div className="metric-grid">
                <Card><Statistic title="Tổng subscriber" value={stats.total_subscribers ?? 0} /></Card>
                <Card><Statistic title="Đã liên kết customer" value={stats.linked_customers ?? 0} /></Card>
            </div>

            <Card
                title="Subscriber"
                extra={(
                    <Space wrap>
                        <Input
                            allowClear
                            value={keyword}
                            onChange={(event) => setKeyword(event.target.value)}
                            placeholder="Tìm theo email, tên, điện thoại..."
                            style={{ width: 300 }}
                        />
                        <Button icon={<CopyOutlined />} onClick={openCopyModal} disabled={!copyEmails.length}>
                            Copy email
                        </Button>
                        <Dropdown
                            trigger={['click']}
                            menu={{
                                items: bulkMenuItems,
                                onClick: ({ key }) => {
                                    if (key === 'bulk-edit') {
                                        openBulkEdit();
                                    }

                                    if (key === 'bulk-delete') {
                                        confirmDeleteSubscribers(selectedSubscribers);
                                    }
                                },
                            }}
                        >
                            <Button icon={<MoreOutlined />} disabled={!selectedRowKeys.length}>
                                Thao tác đã chọn
                            </Button>
                        </Dropdown>
                    </Space>
                )}
            >
                <Table
                    rowKey="id"
                    rowSelection={rowSelection}
                    dataSource={filteredSubscribers}
                    locale={{ emptyText: <Empty description="Chưa có subscriber nào." /> }}
                    pagination={{ pageSize: 10 }}
                    columns={columns}
                />
            </Card>

            <Modal
                title={editingSubscriber ? `Sửa ${editingSubscriber.email}` : 'Sửa email nhận tin'}
                open={Boolean(editingSubscriber)}
                onCancel={() => {
                    setEditingSubscriber(null);
                    editForm.resetFields();
                }}
                onOk={handleSaveSubscriber}
                okText="Lưu"
                cancelText="Hủy"
                destroyOnHidden
            >
                <Form form={editForm} layout="vertical">
                    <Form.Item name="email" label="Email" rules={[{ required: true, type: 'email', message: 'Nhập email hợp lệ' }]}>
                        <Input placeholder="customer@example.com" />
                    </Form.Item>
                    <Form.Item name="name" label="Tên">
                        <Input placeholder="Tên khách hàng" />
                    </Form.Item>
                    <Form.Item name="phone" label="Số điện thoại" style={{ marginBottom: 0 }}>
                        <Input placeholder="Số điện thoại" />
                    </Form.Item>
                </Form>
            </Modal>

            <Modal
                title={`Sửa ${selectedRowKeys.length} email đã chọn`}
                open={bulkEditOpen}
                onCancel={() => {
                    setBulkEditOpen(false);
                    bulkEditForm.resetFields();
                }}
                onOk={handleBulkEdit}
                okText="Lưu email"
                cancelText="Hủy"
                destroyOnHidden
            >
                <Alert
                    type="info"
                    showIcon
                    style={{ marginBottom: 16 }}
                    message="Mỗi dòng tương ứng một email đang chọn theo đúng thứ tự hiện tại."
                />
                <Form form={bulkEditForm} layout="vertical">
                    <Form.Item name="emails" label="Danh sách email" rules={[{ required: true, message: 'Nhập danh sách email' }]}>
                        <TextArea rows={Math.min(12, Math.max(5, selectedRowKeys.length))} />
                    </Form.Item>
                </Form>
            </Modal>

            <Modal
                title="Copy danh sách email"
                open={copyModalOpen}
                onCancel={() => setCopyModalOpen(false)}
                footer={[
                    <Button key="close" onClick={() => setCopyModalOpen(false)}>Đóng</Button>,
                    <Button key="copy" type="primary" icon={<CopyOutlined />} onClick={handleCopyEmails} disabled={!copyText}>
                        Copy lô hiện tại
                    </Button>,
                ]}
                width={760}
                destroyOnHidden
            >
                <Space direction="vertical" size={12} style={{ width: '100%' }}>
                    <Alert
                        type="info"
                        showIcon
                        message={`${copyEmails.length} email ${selectedRowKeys.length ? 'đang chọn' : 'trong danh sách đang lọc'} được chia thành ${copyPageCount} lô, tối đa ${EMAIL_COPY_BATCH_SIZE} email mỗi lô để tránh clipboard quá dài.`}
                    />
                    <div style={{ maxHeight: 240, overflowY: 'auto', border: '1px solid #dbe7e4', borderRadius: 8, padding: 12, background: '#fafafa' }}>
                        <Text copyable={false}>{copyText || 'Không có email.'}</Text>
                    </div>
                    <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
                        <Text type="secondary">
                            Lô {copyPage}/{copyPageCount}: {copyPageEmails.length} email
                        </Text>
                        <Pagination
                            size="small"
                            current={copyPage}
                            pageSize={1}
                            total={copyPageCount}
                            onChange={setCopyPage}
                            showSizeChanger={false}
                        />
                    </Space>
                </Space>
            </Modal>
        </Space>
    );
}
