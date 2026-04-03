import { Button, Card, Col, Row, Tag, Typography } from 'antd';

const { Paragraph, Text } = Typography;

export default function ThemeManagerPage({ themes, onActivate }) {
    return (
        <Card title="Theme Engine Preview">
            <Row gutter={[16, 16]}>
                {themes.map((theme) => (
                    <Col xs={24} md={12} key={theme.key}>
                        <Card size="small">
                            <Tag color="gold">{theme.website_type}</Tag>
                            {theme.is_active ? <Tag color="green">active</Tag> : null}
                            <Paragraph>
                                <Text strong>{theme.name}</Text>
                            </Paragraph>
                            <Paragraph>{theme.description}</Paragraph>
                            <Paragraph>Version: {theme.version}</Paragraph>
                            <Paragraph>Blocks: {(theme.blocks ?? []).join(', ') || 'N/A'}</Paragraph>
                            {!theme.is_active ? (
                                <Button type="primary" size="small" onClick={() => onActivate?.(theme.key)}>
                                    Kich hoat
                                </Button>
                            ) : null}
                        </Card>
                    </Col>
                ))}
            </Row>
        </Card>
    );
}
