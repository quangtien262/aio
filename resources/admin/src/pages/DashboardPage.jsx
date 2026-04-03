import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Row from 'antd/es/row';
import Typography from 'antd/es/typography';

const { Title, Paragraph, Text } = Typography;

export default function DashboardPage({ overview }) {
    return (
        <Row gutter={[20, 20]}>
            <Col xs={24} xl={16}>
                <Card className="hero-card">
                    <Text className="card-label">Base Source Ready</Text>
                    <Title level={2}>Kiến trúc đã sẵn sàng cho mô hình module hóa và đổi theme không mất dữ liệu.</Title>
                    <Paragraph>
                        Laravel xử lý kernel, auth, settings, permissions và lifecycle. React chỉ đóng vai trò admin shell,
                        để sau này tách tiếp thành store, setup wizard, CMS và quản trị theme.
                    </Paragraph>
                </Card>
            </Col>
            <Col xs={24} xl={8}>
                <Card>
                    <Text strong>Trạng thái scaffold</Text>
                    <Paragraph>Laravel 13 đã khởi tạo vào root project.</Paragraph>
                    <Paragraph>Vite build riêng cho public và admin React shell.</Paragraph>
                    <Paragraph>Ant Design là UI framework mặc định cho khu vực quản trị.</Paragraph>
                    <Paragraph>Admins: {overview?.metrics?.admins ?? 0}</Paragraph>
                    <Paragraph>Customers: {overview?.metrics?.customers ?? 0}</Paragraph>
                    <Paragraph>Roles / Permissions: {overview?.metrics?.roles ?? 0} / {overview?.metrics?.permissions ?? 0}</Paragraph>
                </Card>
            </Col>
        </Row>
    );
}
