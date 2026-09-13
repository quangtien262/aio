import AppstoreOutlined from '@ant-design/icons/AppstoreOutlined';
import BarChartOutlined from '@ant-design/icons/BarChartOutlined';
import CoffeeOutlined from '@ant-design/icons/CoffeeOutlined';
import DashboardOutlined from '@ant-design/icons/DashboardOutlined';
import MenuOutlined from '@ant-design/icons/MenuOutlined';
import SettingOutlined from '@ant-design/icons/SettingOutlined';
import ShopOutlined from '@ant-design/icons/ShopOutlined';
import TeamOutlined from '@ant-design/icons/TeamOutlined';
import WalletOutlined from '@ant-design/icons/WalletOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Drawer from 'antd/es/drawer';
import Grid from 'antd/es/grid';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { useState } from 'react';

const { Text, Title } = Typography;
const { useBreakpoint } = Grid;

const iconMap = {
    onboarding: <AppstoreOutlined />,
    dashboard: <DashboardOutlined />,
    pos: <ShopOutlined />,
    kitchen: <CoffeeOutlined />,
    menu: <MenuOutlined />,
    shifts: <WalletOutlined />,
    reports: <BarChartOutlined />,
    staff: <TeamOutlined />,
    settings: <SettingOutlined />,
};

function Navigation({ items, activeKey, onSelect }) {
    return (
        <nav className="fnb-workspace-nav" aria-label="Điều hướng Quản lý quán Cafe">
            {items.map((item) => (
                <button
                    key={item.key}
                    type="button"
                    className={`fnb-workspace-nav-item${activeKey === item.key ? ' is-active' : ''}`}
                    onClick={() => onSelect(item.key)}
                    data-testid={`fnb-nav-${item.key}`}
                >
                    <span className="fnb-workspace-nav-icon">{iconMap[item.key]}</span>
                    <span>{item.label}</span>
                </button>
            ))}
        </nav>
    );
}

export default function FnbWorkspaceShell({
    children,
    navigation,
    activeSection,
    onNavigate,
    outlets,
    terminals,
    outletId,
    terminalId,
    onOutletChange,
    onTerminalChange,
    operationalState,
    installedVersion,
}) {
    const screens = useBreakpoint();
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const isMobile = !screens.lg;
    const selectedOutlet = outlets.find((item) => String(item.id ?? item.public_id) === String(outletId));
    const handleNavigate = (key) => {
        setMobileNavOpen(false);
        onNavigate(key);
    };

    return (
        <div className="fnb-workspace" data-testid="fnb-workspace">
            <header className="fnb-workspace-header">
                <div className="fnb-workspace-brand">
                    {isMobile ? (
                        <Button
                            type="text"
                            icon={<MenuOutlined />}
                            aria-label="Mở menu quán Cafe"
                            onClick={() => setMobileNavOpen(true)}
                        />
                    ) : null}
                    <div className="fnb-workspace-logo"><CoffeeOutlined /></div>
                    <div>
                        <Space size={8} wrap>
                            <Title level={4} style={{ margin: 0 }}>Quản lý quán Cafe</Title>
                            {installedVersion ? <Tag color="cyan">{installedVersion}</Tag> : null}
                        </Space>
                        <Text type="secondary">Bán hàng, pha chế và đối soát trong một luồng</Text>
                    </div>
                </div>

                <div className="fnb-workspace-selectors">
                    <Select
                        value={outletId || undefined}
                        placeholder="Chọn điểm bán"
                        aria-label="Điểm bán"
                        onChange={onOutletChange}
                        options={outlets.map((item) => ({
                            value: String(item.id ?? item.public_id),
                            label: item.name ?? item.code,
                        }))}
                        style={{ minWidth: 190 }}
                    />
                    <Select
                        value={terminalId || undefined}
                        placeholder="Chọn thiết bị"
                        aria-label="Thiết bị bán hàng"
                        onChange={onTerminalChange}
                        disabled={!outletId || !terminals.length}
                        options={terminals.map((item) => ({
                            value: String(item.id ?? item.public_id),
                            label: `${item.name ?? item.code}${item.type ? ` · ${String(item.type).toUpperCase()}` : ''}`,
                        }))}
                        style={{ minWidth: 190 }}
                    />
                </div>
            </header>

            {operationalState && operationalState !== 'active' ? (
                <Alert
                    banner
                    showIcon
                    type={operationalState === 'unconfigured' ? 'info' : operationalState === 'draining' ? 'warning' : 'error'}
                    message={operationalState === 'unconfigured'
                        ? 'Quán chưa được khởi tạo. Hoàn tất cấu hình ban đầu để bắt đầu vận hành.'
                        : operationalState === 'draining'
                            ? 'Module đang chuẩn bị bảo trì. Các thao tác ghi mới tạm dừng.'
                            : 'Module đang tắt vận hành. Dữ liệu vẫn được giữ nguyên.'}
                />
            ) : null}

            <div className="fnb-workspace-body">
                {!isMobile ? (
                    <aside className="fnb-workspace-sidebar">
                        <Navigation items={navigation} activeKey={activeSection} onSelect={handleNavigate} />
                        {selectedOutlet ? (
                            <div className="fnb-outlet-note">
                                <Text type="secondary">Đang vận hành</Text>
                                <Text strong>{selectedOutlet.name}</Text>
                                <Text type="secondary">{selectedOutlet.code}</Text>
                            </div>
                        ) : null}
                    </aside>
                ) : null}
                <main className="fnb-workspace-content">{children}</main>
            </div>

            <Drawer
                title="Quản lý quán Cafe"
                placement="left"
                width="min(330px, 88vw)"
                open={mobileNavOpen}
                onClose={() => setMobileNavOpen(false)}
                styles={{ body: { padding: 12 } }}
            >
                <Navigation items={navigation} activeKey={activeSection} onSelect={handleNavigate} />
            </Drawer>
        </div>
    );
}
