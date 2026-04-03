import { Card, Col, Row, Typography } from 'antd';

const { Title, Paragraph, Text } = Typography;

export default function DashboardPage({ overview }) {
    return (
        <Row gutter={[20, 20]}>
            <Col xs={24} xl={16}>
                <Card className="hero-card">
                    <Text className="card-label">Base Source Ready</Text>
                    <Title level={2}>Kien truc da san sang cho mo hinh module hoa va doi theme khong mat data.</Title>
                    <Paragraph>
                        Laravel xu ly kernel, auth, settings, permissions va lifecycle. React chi dong vai tro admin shell,
                        de sau nay tach tiep thanh store, setup wizard, CMS va quan tri theme.
                    </Paragraph>
                </Card>
            </Col>
            <Col xs={24} xl={8}>
                <Card>
                    <Text strong>Trang thai scaffold</Text>
                    <Paragraph>Laravel 13 da khoi tao vao root project.</Paragraph>
                    <Paragraph>Vite build rieng cho public va admin React shell.</Paragraph>
                    <Paragraph>Ant Design la UI framework mac dinh cho khu vuc quan tri.</Paragraph>
                    <Paragraph>Admins: {overview?.metrics?.admins ?? 0}</Paragraph>
                    <Paragraph>Customers: {overview?.metrics?.customers ?? 0}</Paragraph>
                    <Paragraph>Roles / Permissions: {overview?.metrics?.roles ?? 0} / {overview?.metrics?.permissions ?? 0}</Paragraph>
                </Card>
            </Col>
        </Row>
    );
}
