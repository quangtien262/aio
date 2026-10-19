import AppstoreOutlined from '@ant-design/icons/AppstoreOutlined';
import CoffeeOutlined from '@ant-design/icons/CoffeeOutlined';
import DownOutlined from '@ant-design/icons/DownOutlined';
import DashboardOutlined from '@ant-design/icons/DashboardOutlined';
import EnvironmentOutlined from '@ant-design/icons/EnvironmentOutlined';
import DesktopOutlined from '@ant-design/icons/DesktopOutlined';
import MenuOutlined from '@ant-design/icons/MenuOutlined';
import SettingOutlined from '@ant-design/icons/SettingOutlined';
import ShopOutlined from '@ant-design/icons/ShopOutlined';
import TeamOutlined from '@ant-design/icons/TeamOutlined';
import WalletOutlined from '@ant-design/icons/WalletOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Drawer from 'antd/es/drawer';
import Dropdown from 'antd/es/dropdown';
import Grid from 'antd/es/grid';
import Select from 'antd/es/select';
import Typography from 'antd/es/typography';
import { useState } from 'react';

const { Title } = Typography;
const { useBreakpoint } = Grid;

const iconMap = {
    onboarding: <AppstoreOutlined />,
    dashboard: <DashboardOutlined />,
    pos: <ShopOutlined />,
    kitchen: <CoffeeOutlined />,
    menu: <MenuOutlined />,
    shifts: <WalletOutlined />,
    staff: <TeamOutlined />,
    settings: <SettingOutlined />,
    chain: <AppstoreOutlined />,
    integrations: <SettingOutlined />,
};

const configurationKeys = new Set(['onboarding', 'staff', 'chain', 'settings', 'integrations']);

function Navigation({ items, activeKey, onSelect, mobile = false }) {
    const configurationItems = items.filter((item) => configurationKeys.has(item.key));
    const configurationActive = configurationItems.some((item) => item.key === activeKey);
    return (
        <nav className="fnb-workspace-nav" aria-label="Điều hướng Quản lý quán Cafe">
            {items.filter((item) => !configurationKeys.has(item.key)).map((item) => (
                <button
                    key={item.key}
                    type="button"
                    className={`fnb-workspace-nav-item${activeKey === item.key ? ' is-active' : ''}`}
                    onClick={() => onSelect(item.key)}
                    data-testid={`fnb-nav-${item.key}`}
                    aria-current={activeKey === item.key ? 'page' : undefined}
                >
                    <span className="fnb-workspace-nav-icon">{iconMap[item.key]}</span>
                    <span>{item.label}</span>
                </button>
            ))}
            {configurationItems.length && mobile ? <div className="fnb-workspace-nav-group">
                <div className="fnb-workspace-nav-group-label">Cấu hình</div>
                {configurationItems.map((item) => <button key={item.key} type="button"
                    className={`fnb-workspace-nav-item${activeKey === item.key ? ' is-active' : ''}`}
                    onClick={() => onSelect(item.key)} data-testid={`fnb-nav-${item.key}`}
                    aria-current={activeKey === item.key ? 'page' : undefined}>
                    <span className="fnb-workspace-nav-icon">{iconMap[item.key]}</span><span>{item.label}</span>
                </button>)}
            </div> : null}
            {configurationItems.length && !mobile ? <Dropdown
                trigger={['click']}
                menu={{
                    items: configurationItems.map((item) => ({ key: item.key, icon: iconMap[item.key], label: <span data-testid={`fnb-nav-${item.key}`}>{item.label}</span> })),
                    selectedKeys: configurationActive ? [activeKey] : [],
                    onClick: ({ key }) => onSelect(key),
                }}
            >
                <button type="button" className={`fnb-workspace-nav-item fnb-workspace-nav-configuration${configurationActive ? ' is-active' : ''}`} data-testid="fnb-nav-configuration" aria-label="Cấu hình" aria-haspopup="menu">
                    <span className="fnb-workspace-nav-icon"><SettingOutlined /></span>
                    <span>Cấu hình</span>
                    <DownOutlined />
                </button>
            </Dropdown> : null}
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
}) {
    const screens = useBreakpoint();
    const [mobileNavOpen, setMobileNavOpen] = useState(false);
    const isMobile = !screens.lg;
    const handleNavigate = (key) => {
        setMobileNavOpen(false);
        onNavigate(key);
    };

    return (
        <div className="fnb-workspace" data-testid="fnb-workspace">
            <header className="fnb-workspace-header">
                <div className="fnb-workspace-brand">
                    <div className="fnb-workspace-logo"><CoffeeOutlined /></div>
                    <Title level={4} style={{ margin: 0 }}>Quản lý quán Cafe</Title>
                </div>

                {isMobile ? <Button className="fnb-workspace-menu-trigger" type="text" icon={<MenuOutlined />} aria-label="Mở menu quán Cafe" onClick={() => setMobileNavOpen(true)} /> : null}
                <div className="fnb-workspace-selectors">
                    <div className="fnb-workspace-select-field">
                    <span className="fnb-workspace-select-label"><EnvironmentOutlined /> Điểm bán</span>
                    <Select
                        value={outletId || undefined}
                        placeholder="Chọn điểm bán"
                        aria-label="Điểm bán"
                        onChange={onOutletChange}
                        options={outlets.map((item) => ({
                            value: String(item.id ?? item.public_id),
                            label: item.name ?? item.code,
                        }))}
                        variant="borderless"
                        style={{ width: '100%' }}
                    />
                    </div>
                    <div className="fnb-workspace-select-field">
                    <span className="fnb-workspace-select-label"><DesktopOutlined /> Quầy bán hàng</span>
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
                        variant="borderless"
                        style={{ width: '100%' }}
                    />
                    </div>
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

            {!isMobile ? <div className="fnb-workspace-topnav">
                <Navigation items={navigation} activeKey={activeSection} onSelect={handleNavigate} />
            </div> : null}
            <div className="fnb-workspace-body">
                <main className="fnb-workspace-content">{children}</main>
            </div>

            <Drawer
                title={<span className="fnb-workspace-mobile-title"><CoffeeOutlined /> Quản lý quán Cafe</span>}
                className="fnb-workspace-mobile-nav"
                placement="left"
                width="min(330px, 88vw)"
                open={mobileNavOpen}
                onClose={() => setMobileNavOpen(false)}
                styles={{ body: { padding: 12 } }}
            >
                <Navigation items={navigation} activeKey={activeSection} onSelect={handleNavigate} mobile />
            </Drawer>
        </div>
    );
}
