import { useEffect, useState } from 'react';
import { Button, Card, Form, Input, List, Select, Space, Tag, Typography } from 'antd';

const { Paragraph, Text } = Typography;

export default function SetupWizardPage({ setup, onSaveProfile, onCompleteStep }) {
    const [siteName, setSiteName] = useState('');
    const [websiteType, setWebsiteType] = useState('');

    useEffect(() => {
        setSiteName(setup?.site_name ?? '');
        setWebsiteType(setup?.website_type ?? '');
    }, [setup]);

    if (! setup) {
        return <Card loading title="Setup Wizard" />;
    }

    const websiteTypeOptions = [
        { value: 'ecommerce', label: 'Thuong mai dien tu' },
        { value: 'corporate', label: 'Gioi thieu doanh nghiep' },
        { value: 'service', label: 'Website dich vu' },
        { value: 'news', label: 'Website tin tuc' },
        { value: 'landingpage', label: 'Landing page' },
        { value: 'backoffice', label: 'Backoffice / Admin utility' },
    ];

    return (
        <Card title="Setup Wizard State">
            <Paragraph>
                <Text strong>Website type:</Text> {setup.website_type || 'Chua chon'}
            </Paragraph>
            <Paragraph>
                <Text strong>Active theme:</Text> {setup.active_theme_key || 'Chua kich hoat'}
            </Paragraph>
            <Form layout="vertical" onFinish={() => onSaveProfile?.({ site_name: siteName, website_type: websiteType })}>
                <Form.Item label="Ten website" required>
                    <Input value={siteName} onChange={(event) => setSiteName(event.target.value)} placeholder="VD: HTV Corporate Site" />
                </Form.Item>
                <Form.Item label="Loai website" required>
                    <Select value={websiteType || undefined} options={websiteTypeOptions} onChange={setWebsiteType} placeholder="Chon loai website" />
                </Form.Item>
                <Button htmlType="submit" type="primary">
                    Luu profile
                </Button>
            </Form>
            <List
                dataSource={setup.steps}
                renderItem={(item) => (
                    <List.Item>
                        <Space style={{ width: '100%', justifyContent: 'space-between' }} wrap>
                            <Text>{item.key}</Text>
                            <Space>
                                <Tag color={item.is_completed ? 'green' : 'default'}>
                                    {item.is_completed ? 'done' : 'pending'}
                                </Tag>
                                {!item.is_completed ? (
                                    <Button size="small" onClick={() => onCompleteStep?.(item.key)}>
                                        Hoan thanh
                                    </Button>
                                ) : null}
                            </Space>
                        </Space>
                    </List.Item>
                )}
            />
        </Card>
    );
}
