import Space from 'antd/es/space';
import Typography from 'antd/es/typography';

const { Text, Title } = Typography;

export default function FnbPageHeader({ eyebrow, title, description, actions }) {
    return (
        <header className="fnb-page-header">
            <div>
                {eyebrow ? <div className="fnb-page-eyebrow">{eyebrow}</div> : null}
                <Title level={3}>{title}</Title>
                {description ? <Text type="secondary">{description}</Text> : null}
            </div>
            {actions ? <Space wrap>{actions}</Space> : null}
        </header>
    );
}

