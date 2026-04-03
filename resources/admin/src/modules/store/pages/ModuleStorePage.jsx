import { Button, Card, Col, Row, Space, Tag, Typography } from 'antd';

const { Paragraph, Text } = Typography;

const statusColorMap = {
    available: 'default',
    installed: 'blue',
    enabled: 'green',
    disabled: 'orange',
    upgrade_pending: 'gold',
};

export default function ModuleStorePage({ modules, onAction }) {
    return (
        <Card title="Module Store Flow">
            <Row gutter={[16, 16]}>
                {modules.map((moduleCard) => (
                    <Col xs={24} md={8} key={moduleCard.key}>
                        <Card size="small">
                            <Tag color={statusColorMap[moduleCard.status] ?? 'default'}>{moduleCard.status}</Tag>
                            <Paragraph>
                                <Text strong>{moduleCard.name}</Text>
                            </Paragraph>
                            <Paragraph>{moduleCard.description}</Paragraph>
                            <Paragraph>Version: {moduleCard.version}</Paragraph>
                            <Paragraph>Website types: {(moduleCard.website_types ?? []).join(', ') || 'N/A'}</Paragraph>
                            <Space wrap>
                                {!moduleCard.is_installed ? (
                                    <Button size="small" onClick={() => onAction?.(moduleCard.key, 'install')}>
                                        Cai dat
                                    </Button>
                                ) : null}
                                {moduleCard.status !== 'enabled' ? (
                                    <Button size="small" type="primary" onClick={() => onAction?.(moduleCard.key, 'enable')}>
                                        Bat
                                    </Button>
                                ) : null}
                                {moduleCard.status === 'enabled' ? (
                                    <Button size="small" onClick={() => onAction?.(moduleCard.key, 'disable')}>
                                        Tat
                                    </Button>
                                ) : null}
                                {moduleCard.is_installed ? (
                                    <Button danger size="small" onClick={() => onAction?.(moduleCard.key, 'uninstall')}>
                                        Go bo
                                    </Button>
                                ) : null}
                            </Space>
                        </Card>
                    </Col>
                ))}
            </Row>
        </Card>
    );
}
