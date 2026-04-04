import { useEffect, useMemo, useState } from 'react';
import HeartOutlined from '@ant-design/icons/HeartOutlined';
import LogoutOutlined from '@ant-design/icons/LogoutOutlined';
import OrderedListOutlined from '@ant-design/icons/OrderedListOutlined';
import UserOutlined from '@ant-design/icons/UserOutlined';
import App from 'antd/es/app';
import Alert from 'antd/es/alert';
import Avatar from 'antd/es/avatar';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Layout from 'antd/es/layout';
import Menu from 'antd/es/menu';
import Space from 'antd/es/space';
import Statistic from 'antd/es/statistic';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { Navigate, Route, Routes, useLocation, useNavigate } from 'react-router-dom';

const { Content, Sider } = Layout;
const { Paragraph, Text, Title } = Typography;

function formatCurrency(value) {
    return `${Number(value ?? 0).toLocaleString('vi-VN')}đ`;
}

function formatDateTime(value) {
    if (!value) {
        return 'Chưa có';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString('vi-VN');
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

function OverviewPage({ data }) {
    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card className="customer-hero-card">
                <Title level={2} style={{ marginTop: 0, marginBottom: 8 }}>Xin chào {data.customer?.name}</Title>
                <Paragraph style={{ maxWidth: 700, marginBottom: 0 }}>
                    Theo dõi đơn hàng, cập nhật thông tin cá nhân và quản lý danh sách yêu thích tại một nơi dùng chung cho toàn bộ theme storefront.
                </Paragraph>
            </Card>

            <div className="customer-stat-grid">
                <Card><Statistic title="Tổng đơn" value={data.stats?.orders ?? 0} /></Card>
                <Card><Statistic title="Đơn mới đặt" value={data.stats?.placed ?? 0} /></Card>
                <Card><Statistic title="Đã yêu thích" value={data.stats?.favorites ?? 0} /></Card>
                <Card><Statistic title="Bản tin" value={data.newsletter?.is_subscribed ? 'Đã đăng ký' : 'Chưa đăng ký'} /></Card>
            </div>
        </Space>
    );
}

function OrdersPage({ orders }) {
    if (!orders.length) {
        return <Card><Empty description="Chưa có đơn hàng nào." /></Card>;
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
                                <Title level={4} style={{ marginTop: 6, marginBottom: 0 }}>{order.payment_label}</Title>
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
                            <Text type="secondary">Giao tới: {order.delivery_address}</Text>
                            <Text strong>{formatCurrency(order.subtotal)}</Text>
                        </div>
                    </Card>
                );
            })}
        </Space>
    );
}

function FavoritesPage({ favorites, onRemoveFavorite, pendingFavoriteId }) {
    if (!favorites.length) {
        return <Card><Empty description="Chưa có sản phẩm yêu thích nào." /></Card>;
    }

    return (
        <div className="customer-favorite-grid">
            {favorites.map((favorite) => (
                <Card key={favorite.id} cover={<img className="customer-favorite-image" src={favorite.image} alt={favorite.title} />} className="customer-panel-card">
                    <Space direction="vertical" size={12} style={{ width: '100%' }}>
                        <div>
                            <Title level={5} style={{ marginBottom: 6 }}>{favorite.title}</Title>
                            <Text strong>{formatCurrency(favorite.price)}</Text>
                        </div>
                        <Space wrap>
                            {favorite.url ? <Button type="primary" href={favorite.url}>Xem sản phẩm</Button> : null}
                            <Button danger loading={pendingFavoriteId === favorite.id} onClick={() => onRemoveFavorite(favorite.id)}>Bỏ yêu thích</Button>
                        </Space>
                    </Space>
                </Card>
            ))}
        </div>
    );
}

function ProfilePage({ customer, newsletter, onSaveProfile, saving }) {
    const [form] = Form.useForm();

    useEffect(() => {
        form.setFieldsValue({
            name: customer?.name,
            email: customer?.email,
            phone: customer?.phone,
        });
    }, [customer, form]);

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card className="customer-panel-card">
                <Form form={form} layout="vertical" onFinish={onSaveProfile}>
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

            <Card className="customer-panel-card">
                <Text className="customer-kicker">Newsletter</Text>
                <Title level={4} style={{ marginTop: 8 }}>Trạng thái nhận bản tin</Title>
                <Paragraph style={{ marginBottom: 0 }}>
                    {newsletter?.is_subscribed
                        ? `Email ${newsletter.email} đã được đăng ký nhận bản tin.`
                        : 'Tài khoản này chưa đăng ký nhận bản tin ở storefront.'}
                </Paragraph>
            </Card>
        </Space>
    );
}

export default function CustomerLayout() {
    const { message } = App.useApp();
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [saving, setSaving] = useState(false);
    const [pendingFavoriteId, setPendingFavoriteId] = useState(null);
    const location = useLocation();
    const navigate = useNavigate();

    const activeMenuKey = useMemo(() => {
        if (location.pathname.startsWith('/orders')) {
            return 'orders';
        }

        if (location.pathname.startsWith('/favorites')) {
            return 'favorites';
        }

        if (location.pathname.startsWith('/profile')) {
            return 'profile';
        }

        return 'overview';
    }, [location.pathname]);

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
                // Ignore invalid JSON body.
            }

            throw new Error(errorMessage);
        }

        return response.status === 204 ? null : response.json();
    };

    const loadOverview = async () => {
        try {
            setLoading(true);
            setError(null);
            const payload = await callCustomerApi('/account/api/overview');
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

    const handleSaveProfile = async (payload) => {
        try {
            setSaving(true);
            await callCustomerApi('/account/api/profile', { method: 'PUT', body: JSON.stringify(payload) });
            await loadOverview();
            message.success('Đã cập nhật thông tin cá nhân.');
        } catch (nextError) {
            message.error(nextError instanceof Error ? nextError.message : 'Không cập nhật được thông tin.');
        } finally {
            setSaving(false);
        }
    };

    const handleRemoveFavorite = async (favoriteId) => {
        try {
            setPendingFavoriteId(favoriteId);
            await callCustomerApi(`/account/api/favorites/${favoriteId}`, { method: 'DELETE' });
            await loadOverview();
            message.success('Đã xóa khỏi danh sách yêu thích.');
        } catch (nextError) {
            message.error(nextError instanceof Error ? nextError.message : 'Không thể xóa sản phẩm yêu thích.');
        } finally {
            setPendingFavoriteId(null);
        }
    };

    if (loading) {
        return <Card loading title="Đang tải trang cá nhân" style={{ margin: 24 }} />;
    }

    if (error) {
        return <Alert type="error" showIcon message={error} style={{ margin: 24 }} />;
    }

    const menuItems = [
        { key: 'overview', icon: <UserOutlined />, label: 'Tổng quan', path: '/' },
        { key: 'orders', icon: <OrderedListOutlined />, label: 'Đơn hàng', path: '/orders' },
        { key: 'favorites', icon: <HeartOutlined />, label: 'Yêu thích', path: '/favorites' },
        { key: 'profile', icon: <UserOutlined />, label: 'Hồ sơ', path: '/profile' },
    ];

    return (
        <Layout className="customer-shell">
            <div className="customer-topbar">
                <div />
                <Space wrap>
                    <Button href="/">Trang chủ</Button>
                    <Button icon={<LogoutOutlined />} danger htmlType="button" onClick={() => {
                        document.getElementById('customer-logout-form')?.submit();
                    }}>
                        Đăng xuất
                    </Button>
                </Space>
            </div>

            <Layout className="customer-main-layout">
                <Sider width={280} theme="light" breakpoint="lg" collapsedWidth={0} className="customer-sider">
                    <div className="customer-profile-card">
                        <Avatar size={72} icon={<UserOutlined />} style={{ background: '#fed7aa', color: '#9a3412' }} />
                        <Title level={4} style={{ marginBottom: 4 }}>{data.customer?.name}</Title>
                        <Text type="secondary">{data.customer?.email}</Text>
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
                        <Route path="/" element={<OverviewPage data={data} />} />
                        <Route path="/orders" element={<OrdersPage orders={data.orders ?? []} />} />
                        <Route path="/favorites" element={<FavoritesPage favorites={data.favorites ?? []} onRemoveFavorite={handleRemoveFavorite} pendingFavoriteId={pendingFavoriteId} />} />
                        <Route path="/profile" element={<ProfilePage customer={data.customer} newsletter={data.newsletter} onSaveProfile={handleSaveProfile} saving={saving} />} />
                        <Route path="*" element={<Navigate to="/" replace />} />
                    </Routes>
                </Content>
            </Layout>

            <form id="customer-logout-form" action="/logout" method="POST" style={{ display: 'none' }}>
                <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''} />
            </form>
        </Layout>
    );
}
