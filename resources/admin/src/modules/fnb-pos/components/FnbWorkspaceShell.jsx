import CoffeeOutlined from '@ant-design/icons/CoffeeOutlined';
import EnvironmentOutlined from '@ant-design/icons/EnvironmentOutlined';
import DesktopOutlined from '@ant-design/icons/DesktopOutlined';
import Alert from 'antd/es/alert';
import Select from 'antd/es/select';
import Typography from 'antd/es/typography';

const { Title } = Typography;

export default function FnbWorkspaceShell({
    children,
    outlets,
    terminals,
    outletId,
    terminalId,
    onOutletChange,
    onTerminalChange,
    operationalState,
    compact = false,
}) {
    return (
        <div className={`fnb-workspace${compact ? ' fnb-workspace-compact' : ''}`} data-testid="fnb-workspace">
            <header className="fnb-workspace-header">
                <div className="fnb-workspace-brand">
                    <div className="fnb-workspace-logo"><CoffeeOutlined /></div>
                    <Title level={4} style={{ margin: 0 }}>Quản lý quán Cafe</Title>
                </div>

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

            <div className="fnb-workspace-body">
                <main className="fnb-workspace-content">{children}</main>
            </div>
        </div>
    );
}
