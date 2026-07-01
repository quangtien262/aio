import { useEffect, useMemo, useState } from 'react';
import BankOutlined from '@ant-design/icons/BankOutlined';
import EnvironmentOutlined from '@ant-design/icons/EnvironmentOutlined';
import HeartOutlined from '@ant-design/icons/HeartOutlined';
import HomeOutlined from '@ant-design/icons/HomeOutlined';
import LogoutOutlined from '@ant-design/icons/LogoutOutlined';
import OrderedListOutlined from '@ant-design/icons/OrderedListOutlined';
import SafetyCertificateOutlined from '@ant-design/icons/SafetyCertificateOutlined';
import ToolOutlined from '@ant-design/icons/ToolOutlined';
import UserOutlined from '@ant-design/icons/UserOutlined';
import App from 'antd/es/app';
import Alert from 'antd/es/alert';
import Avatar from 'antd/es/avatar';
import Badge from 'antd/es/badge';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Divider from 'antd/es/divider';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Layout from 'antd/es/layout';
import List from 'antd/es/list';
import Menu from 'antd/es/menu';
import Modal from 'antd/es/modal';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Statistic from 'antd/es/statistic';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom';

const { Content, Sider } = Layout;
const { Paragraph, Text, Title } = Typography;
const { TextArea } = Input;

function formatCurrency(value) {
    const numberValue = Number(value ?? 0);

    if (numberValue <= 0) {
        return 'Liên hệ';
    }

    return `${numberValue.toLocaleString('vi-VN')}đ`;
}

function formatDateTime(value) {
    if (!value) {
        return 'Chưa có';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString('vi-VN');
}

function fullAddress(address) {
    return [address?.address_line, address?.ward, address?.district, address?.province].filter(Boolean).join(', ');
}

function resolveOrderStatusMeta(status) {
    const statusMap = {
        placed: { color: 'blue', label: 'Mới đặt' },
        pending: { color: 'gold', label: 'Chờ xử lý' },
        processing: { color: 'processing', label: 'Đang xử lý' },
        completed: { color: 'green', label: 'Hoàn tất' },
        cancelled: { color: 'red', label: 'Đã hủy' },
    };

    return statusMap[status] ?? { color: 'default', label: status || 'Không rõ' };
}

function OverviewPage({ data, navigate }) {
    const recentOrders = (data.orders ?? []).slice(0, 3);

    return (
        <Space direction="vertical" size={18} style={{ width: '100%' }}>
            <Card className="customer-hero-card">
                <Text className="customer-kicker">Customer portal</Text>
                <Title level={2}>Xin chào, {data.customer?.name || 'khách hàng'}</Title>
                <Paragraph>
                    Đây là khu vực riêng để theo dõi đơn hàng, cập nhật địa chỉ nhận hàng,
                    lưu sản phẩm yêu thích và quản lý các dịch vụ đang quan tâm.
                </Paragraph>
                <Space wrap>
                    <Button type="primary" icon={<OrderedListOutlined />} onClick={() => navigate('/orders')}>
                        Xem đơn hàng
                    </Button>
                    <Button icon={<EnvironmentOutlined />} onClick={() => navigate('/addresses')}>
                        Cập nhật địa chỉ
                    </Button>
                </Space>
            </Card>

            <div className="customer-stat-grid">
                <Card><Statistic title="Tổng đơn" value={data.stats?.orders ?? 0} prefix={<OrderedListOutlined />} /></Card>
                <Card><Statistic title="Sản phẩm yêu thích" value={data.stats?.favorites ?? 0} prefix={<HeartOutlined />} /></Card>
                <Card><Statistic title="Địa chỉ nhận hàng" value={data.stats?.addresses ?? 0} prefix={<EnvironmentOutlined />} /></Card>
                <Card><Statistic title="Dịch vụ quan tâm" value={data.stats?.service_interests ?? 0} prefix={<ToolOutlined />} /></Card>
            </div>

            <div className="customer-action-grid">
                <Card className="customer-panel-card">
                    <Text className="customer-kicker">Chi tiêu</Text>
                    <Title level={3}>{formatCurrency(data.stats?.total_spent)}</Title>
                    <Paragraph type="secondary">Tổng tạm tính từ các đơn chưa hủy.</Paragraph>
                </Card>
                <Card className="customer-panel-card">
                    <Text className="customer-kicker">Bản tin</Text>
                    <Title level={3}>{data.newsletter?.is_subscribed ? 'Đã đăng ký' : 'Chưa đăng ký'}</Title>
                    <Paragraph type="secondary">{data.newsletter?.email || 'Có thể đăng ký ở footer website.'}</Paragraph>
                </Card>
            </div>

            <Card className="customer-panel-card" title="Đơn gần đây">
                {recentOrders.length ? (
                    <List
                        dataSource={recentOrders}
                        renderItem={(order) => {
                            const statusMeta = resolveOrderStatusMeta(order.status);

                            return (
                                <List.Item actions={[<Button key="detail" type="link" onClick={() => navigate('/orders')}>Xem</Button>]}>
                                    <List.Item.Meta
                                        title={<Space><Text strong>{order.order_code}</Text><Tag color={statusMeta.color}>{statusMeta.label}</Tag></Space>}
                                        description={`${formatDateTime(order.placed_at)} - ${formatCurrency(order.subtotal)}`}
                                    />
                                </List.Item>
                            );
                        }}
                    />
                ) : <Empty description="Chưa có đơn hàng." />}
            </Card>
        </Space>
    );
}

function OrdersPage({ orders }) {
    if (!orders.length) {
        return <Card className="customer-panel-card"><Empty description="Chưa có đơn hàng nào." /></Card>;
    }

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            {orders.map((order) => {
                const statusMeta = resolveOrderStatusMeta(order.status);

                return (
                    <Card key={order.id} className="customer-panel-card">
                        <div className="customer-order-head">
                            <div>
                                <Text className="customer-kicker">{order.order_code}</Text>
                                <Title level={4}>{order.payment_label || 'Đơn hàng'}</Title>
                            </div>
                            <div className="customer-order-meta">
                                <Tag color={statusMeta.color}>{statusMeta.label}</Tag>
                                <Text type="secondary">{formatDateTime(order.placed_at)}</Text>
                            </div>
                        </div>
                        <div className="customer-order-items">
                            {(order.items ?? []).map((item) => (
                                <div key={item.id} className="customer-order-item">
                                    <div>
                                        <Text strong>{item.product_name}</Text>
                                        <div><Text type="secondary">Số lượng: {item.quantity}</Text></div>
                                    </div>
                                    <Text strong>{formatCurrency(item.line_total)}</Text>
                                </div>
                            ))}
                        </div>
                        <div className="customer-order-foot">
                            <Text type="secondary">Giao tới: {order.delivery_address || 'Chưa có địa chỉ'}</Text>
                            <Text strong>{formatCurrency(order.subtotal)}</Text>
                        </div>
                    </Card>
                );
            })}
        </Space>
    );
}

function AddressesPage({ addresses, onCreate, onUpdate, onDelete, onSetDefault, saving }) {
    const [form] = Form.useForm();
    const [editingAddress, setEditingAddress] = useState(null);
    const [open, setOpen] = useState(false);

    const openModal = (address = null) => {
        setEditingAddress(address);
        form.setFieldsValue(address ?? { is_default: addresses.length === 0 });
        setOpen(true);
    };

    const submit = async (payload) => {
        const ok = editingAddress ? await onUpdate(editingAddress.id, payload) : await onCreate(payload);
        if (ok) {
            setOpen(false);
            form.resetFields();
        }
    };

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <div className="customer-section-head">
                <div>
                    <Text className="customer-kicker">Địa chỉ</Text>
                    <Title level={3}>Địa chỉ nhận hàng</Title>
                </div>
                <Button type="primary" icon={<EnvironmentOutlined />} onClick={() => openModal()}>Thêm địa chỉ</Button>
            </div>

            {addresses.length ? (
                <div className="customer-card-grid">
                    {addresses.map((address) => (
                        <Card key={address.id} className="customer-address-card">
                            <Space direction="vertical" size={10} style={{ width: '100%' }}>
                                <Space>
                                    <Text strong>{address.receiver_name}</Text>
                                    {address.is_default ? <Tag color="green">Mặc định</Tag> : null}
                                </Space>
                                <Text>{address.phone}</Text>
                                <Paragraph>{fullAddress(address)}</Paragraph>
                                {address.note ? <Text type="secondary">{address.note}</Text> : null}
                                <Space wrap>
                                    <Button onClick={() => openModal(address)}>Sửa</Button>
                                    {!address.is_default ? <Button onClick={() => onSetDefault(address.id)}>Đặt mặc định</Button> : null}
                                    <Button danger onClick={() => onDelete(address.id)}>Xóa</Button>
                                </Space>
                            </Space>
                        </Card>
                    ))}
                </div>
            ) : <Card className="customer-panel-card"><Empty description="Chưa có địa chỉ nhận hàng." /></Card>}

            <Modal title={editingAddress ? 'Cập nhật địa chỉ' : 'Thêm địa chỉ nhận hàng'} open={open} onCancel={() => setOpen(false)} footer={null} destroyOnClose>
                <Form form={form} layout="vertical" onFinish={submit}>
                    <Form.Item name="receiver_name" label="Người nhận" rules={[{ required: true, message: 'Nhập tên người nhận' }]}>
                        <Input />
                    </Form.Item>
                    <Form.Item name="phone" label="Số điện thoại" rules={[{ required: true, message: 'Nhập số điện thoại' }]}>
                        <Input />
                    </Form.Item>
                    <Form.Item name="email" label="Email">
                        <Input />
                    </Form.Item>
                    <div className="customer-form-grid">
                        <Form.Item name="province" label="Tỉnh / thành">
                            <Input />
                        </Form.Item>
                        <Form.Item name="district" label="Quận / huyện">
                            <Input />
                        </Form.Item>
                    </div>
                    <Form.Item name="ward" label="Phường / xã">
                        <Input />
                    </Form.Item>
                    <Form.Item name="address_line" label="Địa chỉ chi tiết" rules={[{ required: true, message: 'Nhập địa chỉ chi tiết' }]}>
                        <TextArea rows={3} />
                    </Form.Item>
                    <Form.Item name="note" label="Ghi chú">
                        <TextArea rows={2} />
                    </Form.Item>
                    <Space>
                        <Button onClick={() => setOpen(false)}>Hủy</Button>
                        <Button type="primary" htmlType="submit" loading={saving}>Lưu địa chỉ</Button>
                    </Space>
                </Form>
            </Modal>
        </Space>
    );
}

function FavoritesPage({ favorites, onRemoveFavorite, pendingFavoriteId }) {
    if (!favorites.length) {
        return <Card className="customer-panel-card"><Empty description="Chưa có sản phẩm yêu thích nào." /></Card>;
    }

    return (
        <div className="customer-card-grid">
            {favorites.map((favorite) => (
                <Card key={favorite.id} className="customer-panel-card" cover={<img className="customer-thumb" src={favorite.image} alt={favorite.title} />}>
                    <Title level={5}>{favorite.title}</Title>
                    <Text strong>{formatCurrency(favorite.price)}</Text>
                    <Divider />
                    <Space wrap>
                        {favorite.url ? <Button type="primary" href={favorite.url}>Xem sản phẩm</Button> : null}
                        <Button danger loading={pendingFavoriteId === favorite.id} onClick={() => onRemoveFavorite(favorite.id)}>Bỏ yêu thích</Button>
                    </Space>
                </Card>
            ))}
        </div>
    );
}

function ServicesPage({ interests, services, onCreate, onDelete, saving }) {
    const [form] = Form.useForm();
    const [open, setOpen] = useState(false);

    const submit = async (payload) => {
        const selectedService = services.find((service) => service.id === payload.cms_service_id);
        const ok = await onCreate({
            ...payload,
            title: payload.title || selectedService?.title,
        });

        if (ok) {
            form.resetFields();
            setOpen(false);
        }
    };

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <div className="customer-section-head">
                <div>
                    <Text className="customer-kicker">Dịch vụ</Text>
                    <Title level={3}>Dịch vụ đang quan tâm</Title>
                </div>
                <Button type="primary" icon={<ToolOutlined />} onClick={() => setOpen(true)}>Thêm dịch vụ</Button>
            </div>

            {interests.length ? (
                <div className="customer-card-grid">
                    {interests.map((interest) => (
                        <Card key={interest.id} className="customer-panel-card">
                            <Space direction="vertical" size={10} style={{ width: '100%' }}>
                                {interest.image ? <img className="customer-service-image" src={interest.image} alt={interest.title} /> : null}
                                <Space>
                                    <Title level={5}>{interest.title}</Title>
                                    <Tag>{interest.status}</Tag>
                                </Space>
                                {interest.message ? <Paragraph>{interest.message}</Paragraph> : null}
                                <Space wrap>
                                    {interest.service_url ? <Button href={interest.service_url}>Xem dịch vụ</Button> : null}
                                    <Button danger onClick={() => onDelete(interest.id)}>Xóa</Button>
                                </Space>
                            </Space>
                        </Card>
                    ))}
                </div>
            ) : <Card className="customer-panel-card"><Empty description="Chưa có dịch vụ quan tâm." /></Card>}

            <Modal title="Thêm dịch vụ quan tâm" open={open} onCancel={() => setOpen(false)} footer={null} destroyOnClose>
                <Form form={form} layout="vertical" onFinish={submit}>
                    <Form.Item name="cms_service_id" label="Chọn dịch vụ có sẵn">
                        <Select allowClear placeholder="Chọn dịch vụ" options={services.map((service) => ({ value: service.id, label: service.title }))} />
                    </Form.Item>
                    <Form.Item name="title" label="Hoặc nhập nhu cầu riêng">
                        <Input placeholder="Ví dụ: Tư vấn thiết kế văn phòng" />
                    </Form.Item>
                    <Form.Item name="message" label="Ghi chú nhu cầu">
                        <TextArea rows={4} placeholder="Mô tả nhanh nhu cầu để đội ngũ tư vấn chuẩn bị tốt hơn." />
                    </Form.Item>
                    <Space>
                        <Button onClick={() => setOpen(false)}>Hủy</Button>
                        <Button type="primary" htmlType="submit" loading={saving}>Lưu dịch vụ</Button>
                    </Space>
                </Form>
            </Modal>
        </Space>
    );
}

function ProfilePage({ customer, newsletter, onSaveProfile, onSavePassword, saving }) {
    const [profileForm] = Form.useForm();
    const [passwordForm] = Form.useForm();

    useEffect(() => {
        profileForm.setFieldsValue({
            name: customer?.name,
            email: customer?.email,
            phone: customer?.phone,
        });
    }, [customer, profileForm]);

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card className="customer-panel-card" title="Thông tin tài khoản">
                <Form form={profileForm} layout="vertical" onFinish={onSaveProfile}>
                    <div className="customer-form-grid">
                        <Form.Item name="name" label="Họ và tên" rules={[{ required: true, message: 'Nhập họ tên' }]}>
                            <Input />
                        </Form.Item>
                        <Form.Item name="phone" label="Số điện thoại">
                            <Input />
                        </Form.Item>
                        <Form.Item name="email" label="Email" className="customer-form-full">
                            <Input disabled />
                        </Form.Item>
                    </div>
                    <Button htmlType="submit" type="primary" loading={saving}>Lưu thông tin</Button>
                </Form>
            </Card>

            <Card className="customer-panel-card" title="Đổi mật khẩu">
                <Form form={passwordForm} layout="vertical" onFinish={async (payload) => {
                    const ok = await onSavePassword(payload);
                    if (ok) {
                        passwordForm.resetFields();
                    }
                }}>
                    <div className="customer-form-grid">
                        <Form.Item name="current_password" label="Mật khẩu hiện tại" rules={[{ required: true, message: 'Nhập mật khẩu hiện tại' }]}>
                            <Input.Password />
                        </Form.Item>
                        <Form.Item name="password" label="Mật khẩu mới" rules={[{ required: true, min: 8, message: 'Mật khẩu tối thiểu 8 ký tự' }]}>
                            <Input.Password />
                        </Form.Item>
                        <Form.Item name="password_confirmation" label="Nhập lại mật khẩu mới" dependencies={['password']} rules={[
                            { required: true, message: 'Nhập lại mật khẩu mới' },
                            ({ getFieldValue }) => ({
                                validator(_, value) {
                                    return !value || getFieldValue('password') === value
                                        ? Promise.resolve()
                                        : Promise.reject(new Error('Mật khẩu nhập lại chưa khớp'));
                                },
                            }),
                        ]}>
                            <Input.Password />
                        </Form.Item>
                    </div>
                    <Button htmlType="submit" type="primary" loading={saving}>Đổi mật khẩu</Button>
                </Form>
            </Card>

            <Card className="customer-panel-card">
                <Text className="customer-kicker">Newsletter</Text>
                <Title level={4}>Trạng thái nhận bản tin</Title>
                <Paragraph>
                    {newsletter?.is_subscribed
                        ? `Email ${newsletter.email} đã được đăng ký nhận bản tin.`
                        : 'Tài khoản này chưa đăng ký nhận bản tin ở storefront.'}
                </Paragraph>
            </Card>
        </Space>
    );
}

export default function CustomerLayout({ apiBase = '/account/api', homeUrl = '/', logoutUrl = '/logout' }) {
    const { message, modal } = App.useApp();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [saving, setSaving] = useState(false);
    const [pendingFavoriteId, setPendingFavoriteId] = useState(null);
    const location = useLocation();
    const navigate = useNavigate();

    const menuItems = useMemo(() => [
        { key: 'overview', icon: <HomeOutlined />, label: 'Tổng quan', path: '/' },
        { key: 'orders', icon: <OrderedListOutlined />, label: 'Đơn hàng', path: '/orders' },
        { key: 'addresses', icon: <EnvironmentOutlined />, label: 'Địa chỉ nhận hàng', path: '/addresses' },
        { key: 'favorites', icon: <HeartOutlined />, label: 'Sản phẩm yêu thích', path: '/favorites' },
        { key: 'services', icon: <ToolOutlined />, label: 'Dịch vụ quan tâm', path: '/services' },
        { key: 'profile', icon: <UserOutlined />, label: 'Hồ sơ & bảo mật', path: '/profile' },
    ], []);

    const activeMenuKey = useMemo(() => {
        const found = menuItems.find((item) => item.path !== '/' && location.pathname.startsWith(item.path));
        return found?.key ?? 'overview';
    }, [location.pathname, menuItems]);

    const callCustomerApi = async (url, options = {}) => {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': token ?? '',
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(options.headers ?? {}),
            },
            ...options,
        });

        if (!response.ok) {
            let errorMessage = 'Không thực hiện được thao tác.';
            try {
                const payload = await response.json();
                errorMessage = payload.message ?? errorMessage;
            } catch {
                // Response may not be JSON.
            }
            throw new Error(errorMessage);
        }

        return response.status === 204 ? null : response.json();
    };

    const loadOverview = async () => {
        try {
            setLoading(true);
            setError(null);
            const payload = await callCustomerApi(`${apiBase}/overview`);
            setData(payload.data ?? null);
        } catch (nextError) {
            setError(nextError instanceof Error ? nextError.message : 'Không tải được dữ liệu tài khoản.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadOverview();
    }, []);

    const mutate = async (action, successMessage) => {
        try {
            setSaving(true);
            const result = await action();
            await loadOverview();
            if (successMessage) {
                message.success(successMessage);
            }
            return result ?? true;
        } catch (nextError) {
            message.error(nextError instanceof Error ? nextError.message : 'Không thực hiện được thao tác.');
            return false;
        } finally {
            setSaving(false);
        }
    };

    const handleSaveProfile = (payload) => mutate(
        () => callCustomerApi(`${apiBase}/profile`, { method: 'PUT', body: JSON.stringify(payload) }),
        'Đã cập nhật thông tin cá nhân.',
    );

    const handleSavePassword = (payload) => mutate(
        () => callCustomerApi(`${apiBase}/password`, { method: 'PUT', body: JSON.stringify(payload) }),
        'Đã đổi mật khẩu.',
    );

    const handleCreateAddress = (payload) => mutate(
        () => callCustomerApi(`${apiBase}/addresses`, { method: 'POST', body: JSON.stringify(payload) }),
        'Đã thêm địa chỉ nhận hàng.',
    );

    const handleUpdateAddress = (addressId, payload) => mutate(
        () => callCustomerApi(`${apiBase}/addresses/${addressId}`, { method: 'PUT', body: JSON.stringify(payload) }),
        'Đã cập nhật địa chỉ nhận hàng.',
    );

    const handleSetDefaultAddress = (addressId) => mutate(
        () => callCustomerApi(`${apiBase}/addresses/${addressId}/default`, { method: 'PUT', body: JSON.stringify({}) }),
        'Đã đặt địa chỉ mặc định.',
    );

    const handleDeleteAddress = (addressId) => {
        modal.confirm({
            title: 'Xóa địa chỉ này?',
            content: 'Địa chỉ sẽ bị xóa khỏi tài khoản khách hàng.',
            okText: 'Xóa',
            cancelText: 'Hủy',
            okButtonProps: { danger: true },
            onOk: () => mutate(
                () => callCustomerApi(`${apiBase}/addresses/${addressId}`, { method: 'DELETE' }),
                'Đã xóa địa chỉ.',
            ),
        });
    };

    const handleRemoveFavorite = async (favoriteId) => {
        try {
            setPendingFavoriteId(favoriteId);
            await callCustomerApi(`${apiBase}/favorites/${favoriteId}`, { method: 'DELETE' });
            await loadOverview();
            message.success('Đã xóa khỏi danh sách yêu thích.');
        } catch (nextError) {
            message.error(nextError instanceof Error ? nextError.message : 'Không thể xóa sản phẩm yêu thích.');
        } finally {
            setPendingFavoriteId(null);
        }
    };

    const handleCreateServiceInterest = (payload) => mutate(
        () => callCustomerApi(`${apiBase}/service-interests`, { method: 'POST', body: JSON.stringify(payload) }),
        'Đã lưu dịch vụ quan tâm.',
    );

    const handleDeleteServiceInterest = (interestId) => mutate(
        () => callCustomerApi(`${apiBase}/service-interests/${interestId}`, { method: 'DELETE' }),
        'Đã xóa dịch vụ quan tâm.',
    );

    if (loading) {
        return <Card loading title="Đang tải trang cá nhân" style={{ margin: 24 }} />;
    }

    if (error) {
        return <Alert type="error" showIcon message={error} style={{ margin: 24 }} />;
    }

    return (
        <Layout className="customer-shell">
            <div className="customer-topbar">
                <div className="customer-brand">
                    <Badge count={data.stats?.orders ?? 0} color="#0f766e">
                        <Avatar size={48} icon={<UserOutlined />} />
                    </Badge>
                    <div>
                        <Text className="customer-kicker">Tài khoản khách</Text>
                        <Title level={4}>{data.customer?.name}</Title>
                    </div>
                </div>
                <Space wrap>
                    <Button href={homeUrl} icon={<HomeOutlined />}>Về website</Button>
                    <Button icon={<LogoutOutlined />} danger htmlType="button" onClick={() => {
                        document.getElementById('customer-logout-form')?.submit();
                    }}>
                        Đăng xuất
                    </Button>
                </Space>
            </div>

            <Layout className="customer-main-layout">
                <Sider width={300} theme="light" breakpoint="lg" collapsedWidth={0} className="customer-sider">
                    <div className="customer-profile-card">
                        <Avatar size={76} icon={<UserOutlined />} />
                        <Title level={4}>{data.customer?.name}</Title>
                        <Text type="secondary">{data.customer?.email}</Text>
                        <Divider />
                        <Space direction="vertical" size={8}>
                            <Text><SafetyCertificateOutlined /> Thành viên từ {formatDateTime(data.customer?.created_at)}</Text>
                            <Text><BankOutlined /> {formatCurrency(data.stats?.total_spent)} đã đặt</Text>
                        </Space>
                    </div>

                    <Menu
                        mode="inline"
                        selectedKeys={[activeMenuKey]}
                        items={menuItems.map((item) => ({ key: item.key, icon: item.icon, label: item.label }))}
                        onClick={({ key }) => navigate(menuItems.find((item) => item.key === key)?.path ?? '/')}
                        className="customer-menu"
                    />
                </Sider>

                <Content className="customer-content">
                    <Routes>
                        <Route path="/" element={<OverviewPage data={data} navigate={navigate} />} />
                        <Route path="/orders" element={<OrdersPage orders={data.orders ?? []} />} />
                        <Route path="/addresses" element={<AddressesPage addresses={data.addresses ?? []} onCreate={handleCreateAddress} onUpdate={handleUpdateAddress} onDelete={handleDeleteAddress} onSetDefault={handleSetDefaultAddress} saving={saving} />} />
                        <Route path="/favorites" element={<FavoritesPage favorites={data.favorites ?? []} onRemoveFavorite={handleRemoveFavorite} pendingFavoriteId={pendingFavoriteId} />} />
                        <Route path="/services" element={<ServicesPage interests={data.service_interests ?? []} services={data.available_services ?? []} onCreate={handleCreateServiceInterest} onDelete={handleDeleteServiceInterest} saving={saving} />} />
                        <Route path="/profile" element={<ProfilePage customer={data.customer} newsletter={data.newsletter} onSaveProfile={handleSaveProfile} onSavePassword={handleSavePassword} saving={saving} />} />
                        <Route path="*" element={<Navigate to="/" replace />} />
                    </Routes>
                </Content>
            </Layout>

            <form id="customer-logout-form" action={logoutUrl} method="POST" style={{ display: 'none' }}>
                <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''} />
            </form>
        </Layout>
    );
}
