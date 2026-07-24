import { DownOutlined } from '@ant-design/icons';
import Card from 'antd/es/card';

export function CollapsibleCardTitle({
    sectionKey,
    title,
    collapsed = false,
    onToggle,
    leading = null,
    extra = null,
}) {
    return (
        <button
            type="button"
            className="shared-section-toggle"
            aria-expanded={!collapsed}
            aria-controls={`shared-form-section-${sectionKey}`}
            onClick={onToggle}
        >
            <span className="shared-section-toggle__title">
                {leading}
                <span>{title}</span>
                {extra}
            </span>
            <span className="shared-section-toggle__state">
                {collapsed ? 'Mở rộng' : 'Thu gọn'}
                <DownOutlined />
            </span>
        </button>
    );
}

export default function CollapsibleFormCard({
    sectionKey,
    title,
    collapsed = false,
    onToggle,
    className = '',
    children,
    collapsible = true,
    size = 'small',
}) {
    if (!collapsible) {
        return (
            <Card size={size} className={`cms-post-form-card ${className}`.trim()} title={title}>
                {children}
            </Card>
        );
    }

    return (
        <Card
            size={size}
            className={`cms-post-form-card shared-collapsible-card ${collapsed ? 'is-collapsed' : 'is-expanded'} ${className}`.trim()}
            title={(
                <CollapsibleCardTitle
                    sectionKey={sectionKey}
                    title={title}
                    collapsed={collapsed}
                    onToggle={onToggle}
                />
            )}
        >
            {!collapsed ? <div id={`shared-form-section-${sectionKey}`}>{children}</div> : null}
        </Card>
    );
}
