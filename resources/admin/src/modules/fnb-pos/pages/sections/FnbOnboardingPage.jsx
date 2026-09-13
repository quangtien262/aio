import CheckCircleOutlined from '@ant-design/icons/CheckCircleOutlined';
import CoffeeOutlined from '@ant-design/icons/CoffeeOutlined';
import RocketOutlined from '@ant-design/icons/RocketOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Result from 'antd/es/result';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Steps from 'antd/es/steps';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { useMemo, useState } from 'react';
import { createIdempotencyKey } from '../../api/fnbApi';
import FnbPageHeader from '../../components/FnbPageHeader';
import { useFnbWorkspace } from '../../state/FnbWorkspaceContext';
import { asArray } from '../../utils/fnbFormat';

const { Paragraph, Text, Title } = Typography;

const readinessLabel = {
    absent: 'Chưa cài',
    installed: 'Đã cài',
    enabled: 'Đã bật',
    configured: 'Đã cấu hình',
    healthy: 'Hoạt động tốt',
    ready: 'Sẵn sàng',
};

export default function FnbOnboardingPage() {
    const { api, bootstrap, reloadBootstrap, can, navigateSection, runCommand } = useFnbWorkspace();
    const [form] = Form.useForm();
    const [saving, setSaving] = useState(false);
    const completed = Boolean(bootstrap.onboarding?.completed ?? asArray(bootstrap.outlets).length);
    const integrations = asArray(bootstrap.integrations);
    const idempotencyKey = useMemo(() => createIdempotencyKey('cafe-onboarding'), []);

    const submit = async () => {
        const values = await form.validateFields();
        setSaving(true);

        try {
            const payload = {
                outlet: {
                    code: values.outlet_code.trim(),
                    name: values.outlet_name.trim(),
                    timezone: values.timezone,
                    currency: 'VND',
                },
                terminal: {
                    code: values.terminal_code.trim(),
                    name: values.terminal_name.trim(),
                    type: 'pos',
                },
                station: {
                    code: values.station_code.trim(),
                    name: values.station_name.trim(),
                },
                payment_methods: [
                    { code: 'CASH', name: 'Tiền mặt', kind: 'cash' },
                    { code: 'TRANSFER', name: 'Chuyển khoản', kind: 'transfer' },
                ],
            };
            await runCommand({
                execute: (auth) => api.onboard(payload, { idempotencyKey, ...auth }),
                successMessage: 'Đã khởi tạo quán Cafe.',
                onSuccess: async () => {
                    await reloadBootstrap();
                    navigateSection('dashboard');
                },
                onConflict: reloadBootstrap,
            });
        } finally {
            setSaving(false);
        }
    };

    if (completed) {
        const firstOutlet = asArray(bootstrap.outlets)[0];

        return (
            <div className="fnb-page-stack">
                <FnbPageHeader
                    eyebrow="Khởi tạo quán"
                    title="Nền vận hành đã sẵn sàng"
                    description="Dữ liệu được tạo theo website đang quản trị và giữ nguyên khi module tạm tắt."
                />
                <Card className="fnb-panel">
                    <Result
                        status="success"
                        icon={<CheckCircleOutlined />}
                        title="Đã hoàn tất cấu hình ban đầu"
                        subTitle={firstOutlet ? `${firstOutlet.name} · ${firstOutlet.code}` : 'Điểm bán đã được tạo.'}
                        extra={[
                            <Button key="pos" type="primary" className="fnb-touch-button" onClick={() => navigateSection('pos')}>Mở màn hình bán hàng</Button>,
                            <Button key="settings" className="fnb-touch-button" onClick={() => navigateSection('settings')}>Kiểm tra thiết lập</Button>,
                        ]}
                    />
                </Card>
                {integrations.length ? (
                    <Card className="fnb-panel" title="Ứng dụng kết nối tùy chọn">
                        <Row gutter={[12, 12]}>
                            {integrations.map((integration) => (
                                <Col xs={24} md={12} xl={8} key={integration.key ?? integration.module_key}>
                                    <div className="fnb-readiness-card">
                                        <Space direction="vertical" size={4}>
                                            <Text strong>{integration.name ?? integration.key ?? integration.module_key}</Text>
                                            <Tag color={integration.status === 'ready' ? 'green' : 'default'}>
                                                {readinessLabel[integration.status] ?? integration.status}
                                            </Tag>
                                        </Space>
                                    </div>
                                </Col>
                            ))}
                        </Row>
                    </Card>
                ) : null}
            </div>
        );
    }

    return (
        <div className="fnb-page-stack">
            <FnbPageHeader
                eyebrow="Khởi tạo quán"
                title="Sẵn sàng bán bill đầu tiên"
                description="Preset Cafe tạo điểm bán, quầy POS, khu vực bàn, trạm Bar và phương thức thanh toán cơ bản."
            />

            <div className="fnb-onboarding-layout">
                <Card className="fnb-panel fnb-onboarding-guide">
                    <div className="fnb-onboarding-hero-icon"><CoffeeOutlined /></div>
                    <Title level={4}>Thiết lập có chủ đích</Title>
                    <Paragraph type="secondary">
                        Wizard chỉ tạo dữ liệu sau khi Sếp xác nhận. Chạy lại cùng yêu cầu sẽ không nhân đôi dữ liệu.
                    </Paragraph>
                    <Steps
                        direction="vertical"
                        current={0}
                        items={[
                            { title: 'Thông tin điểm bán', description: 'Tên, mã và múi giờ vận hành.' },
                            { title: 'Quầy và Bar', description: 'Một thiết bị POS cùng trạm pha chế.' },
                            { title: 'Bàn và thanh toán', description: 'Khu vực mẫu, 5 bàn, tiền mặt và chuyển khoản.' },
                            { title: 'Kiểm tra bill đầu tiên', description: 'Mở ngày, mở ca rồi bắt đầu bán.' },
                        ]}
                    />
                </Card>

                <Card className="fnb-panel" title="Thông tin quán">
                    {!can('fnb.outlet.manage') ? (
                        <Alert type="warning" showIcon message="Bạn chưa có quyền khởi tạo điểm bán." style={{ marginBottom: 16 }} />
                    ) : null}
                    <Form
                        form={form}
                        layout="vertical"
                        initialValues={{
                            outlet_code: 'Q1',
                            timezone: 'Asia/Ho_Chi_Minh',
                            terminal_code: 'POS-01',
                            terminal_name: 'Quầy 1',
                            station_code: 'BAR',
                            station_name: 'Bar',
                        }}
                        onFinish={submit}
                    >
                        <Row gutter={16}>
                            <Col xs={24} md={16}>
                                <Form.Item name="outlet_name" label="Tên điểm bán" rules={[{ required: true, message: 'Nhập tên điểm bán.' }]}>
                                    <Input size="large" placeholder="Ví dụ: Cafe Nguyễn Huệ" autoFocus />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="outlet_code" label="Mã điểm bán" rules={[{ required: true, message: 'Nhập mã điểm bán.' }]}>
                                    <Input size="large" maxLength={30} />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item name="timezone" label="Múi giờ" rules={[{ required: true }]}>
                                    <Select size="large" options={[{ value: 'Asia/Ho_Chi_Minh', label: 'Việt Nam (UTC+7)' }]} />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item label="Tiền tệ">
                                    <Input size="large" value="VND · Đồng Việt Nam" disabled />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item name="terminal_name" label="Tên quầy POS" rules={[{ required: true }]}>
                                    <Input size="large" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item name="terminal_code" label="Mã quầy" rules={[{ required: true }]}>
                                    <Input size="large" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item name="station_name" label="Tên trạm pha chế" rules={[{ required: true }]}>
                                    <Input size="large" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item name="station_code" label="Mã trạm" rules={[{ required: true }]}>
                                    <Input size="large" />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Alert
                            type="info"
                            showIcon
                            message="Ứng dụng Kho, Kế toán, Hóa đơn điện tử và Nhân sự là tùy chọn"
                            description="Thiếu hoặc tắt các ứng dụng này không làm gián đoạn bán hàng và pha chế."
                            style={{ marginBottom: 18 }}
                        />
                        <Button
                            type="primary"
                            htmlType="submit"
                            size="large"
                            icon={<RocketOutlined />}
                            loading={saving}
                            disabled={!can('fnb.outlet.manage')}
                            className="fnb-touch-button"
                            block
                            data-testid="fnb-onboarding-submit"
                        >
                            Tạo quán Cafe
                        </Button>
                    </Form>
                </Card>
            </div>
        </div>
    );
}
