import { useEffect, useRef } from 'react';
import DeleteOutlined from '@ant-design/icons/DeleteOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Switch from 'antd/es/switch';
import Typography from 'antd/es/typography';
import dayjs from 'dayjs';

const { Text } = Typography;

function toSlug(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'd')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export default function CmsTeamMemberFormModal({ open, canManage, editingMember, mediaOptions = [], onCancel, onSubmit }) {
    const [form] = Form.useForm();
    const slugEditedRef = useRef(Boolean(editingMember?.id));
    const nameValue = Form.useWatch('name', form) ?? '';

    useEffect(() => {
        form.setFieldsValue({
            ...editingMember,
            images: editingMember?.images?.length ? editingMember.images : [],
        });
        slugEditedRef.current = Boolean(editingMember?.id || editingMember?.slug);
    }, [editingMember, form]);

    useEffect(() => {
        if (slugEditedRef.current) {
            return;
        }

        form.setFieldValue('slug', toSlug(nameValue));
    }, [form, nameValue]);

    const mediaSelectOptions = mediaOptions.map((item) => ({
        label: item.title || item.file_url,
        value: item.id,
        media: item,
    }));

    const handleMediaChange = (fieldName, mediaId) => {
        const selected = mediaOptions.find((item) => item.id === mediaId);

        if (!selected) {
            return;
        }

        form.setFieldValue(['images', fieldName, 'image_url'], selected.file_url);

        if (!form.getFieldValue(['images', fieldName, 'alt_text'])) {
            form.setFieldValue(['images', fieldName, 'alt_text'], selected.alt_text || selected.title || '');
        }
    };

    const handleSlugChange = (event) => {
        slugEditedRef.current = true;
        form.setFieldValue('slug', toSlug(event.target.value));
    };

    const handleSubmit = async () => {
        const values = await form.validateFields();

        await onSubmit?.({
            ...values,
            role: values.role || null,
            department: values.department || null,
            summary: values.summary || null,
            bio: values.bio || null,
            email: values.email || null,
            phone: values.phone || null,
            link_url: values.link_url || null,
            sort_order: Number(values.sort_order ?? 0),
            is_featured: Boolean(values.is_featured),
            publish_at: values.status === 'published' ? (values.publish_at || dayjs().format('YYYY-MM-DDTHH:mm:ss')) : null,
            images: (values.images ?? []).filter((image) => image?.image_url),
        });

        form.resetFields();
    };

    const handleCancel = () => {
        form.resetFields();
        onCancel?.();
    };

    return (
        <Drawer
            title={editingMember?.id ? 'Cap nhat nhan su CMS' : 'Tao nhan su CMS'}
            open={open}
            onClose={handleCancel}
            width={960}
            destroyOnHidden
            className="cms-page-drawer"
            extra={(
                <Space>
                    <Button onClick={handleCancel}>Huy</Button>
                    <Button type="primary" disabled={!canManage} onClick={handleSubmit}>Luu nhan su</Button>
                </Space>
            )}
        >
            <Form form={form} layout="vertical" initialValues={editingMember}>
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Card size="small" title="Thong tin nhan su">
                        <Row gutter={16}>
                            <Col xs={24} md={14}>
                                <Form.Item name="name" label="Ho ten" rules={[{ required: true, message: 'Nhap ho ten nhan su' }]}>
                                    <Input placeholder="Jhon Castellon" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={10}>
                                <Form.Item name="slug" label="Slug" rules={[{ required: true, message: 'Nhap slug nhan su' }]}>
                                    <Input placeholder="jhon-castellon" onChange={handleSlugChange} />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Row gutter={16}>
                            <Col xs={24} md={8}>
                                <Form.Item name="status" label="Trang thai" rules={[{ required: true, message: 'Chon trang thai' }]}>
                                    <Select options={[{ label: 'Ban nhap', value: 'draft' }, { label: 'Da xuat ban', value: 'published' }]} />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="role" label="Chuc danh">
                                    <Input placeholder="Giam sat" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="department" label="Phong ban">
                                    <Input placeholder="Kien truc" />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Row gutter={16}>
                            <Col xs={24} md={8}>
                                <Form.Item name="sort_order" label="Thu tu">
                                    <InputNumber min={0} style={{ width: '100%' }} />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="is_featured" label="Nhan su noi bat" valuePropName="checked">
                                    <Switch />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={8}>
                                <Form.Item name="link_url" label="Link click">
                                    <Input placeholder="/vi/lien-he hoac https://..." />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Row gutter={16}>
                            <Col xs={24} md={12}>
                                <Form.Item name="email" label="Email">
                                    <Input placeholder="name@example.com" />
                                </Form.Item>
                            </Col>
                            <Col xs={24} md={12}>
                                <Form.Item name="phone" label="Dien thoai">
                                    <Input placeholder="090..." />
                                </Form.Item>
                            </Col>
                        </Row>
                        <Form.Item name="summary" label="Mo ta ngan">
                            <Input.TextArea rows={3} placeholder="Mo ta ngan ve kinh nghiem hoac vai tro." />
                        </Form.Item>
                    </Card>

                    <Card size="small" title="Tieu su / gioi thieu">
                        <Form.Item name="bio" label="Noi dung" style={{ marginBottom: 0 }}>
                            <Input.TextArea rows={7} placeholder="Thong tin gioi thieu chi tiet ve nhan su." />
                        </Form.Item>
                    </Card>

                    <Card size="small" title="Gallery anh nhan su">
                        <Form.List name="images">
                            {(fields, { add, remove }) => (
                                <Space direction="vertical" size={12} style={{ width: '100%' }}>
                                    {fields.map((field) => {
                                        const imageUrl = form.getFieldValue(['images', field.name, 'image_url']);

                                        return (
                                            <Card key={field.key} size="small" type="inner">
                                                <Row gutter={12}>
                                                    <Col xs={24} md={8}>
                                                        {imageUrl ? (
                                                            <img src={imageUrl} alt="" style={{ width: '100%', height: 180, objectFit: 'cover', borderRadius: 12, border: '1px solid #dbe7e4' }} />
                                                        ) : (
                                                            <div style={{ height: 180, borderRadius: 12, border: '1px dashed #cbd5d1', display: 'grid', placeItems: 'center', color: '#8aa19a' }}>Chua co anh</div>
                                                        )}
                                                    </Col>
                                                    <Col xs={24} md={16}>
                                                        <Row gutter={12}>
                                                            <Col xs={24} md={12}>
                                                                <Form.Item name={[field.name, 'cms_media_id']} label="Chon tu media">
                                                                    <Select
                                                                        allowClear
                                                                        showSearch
                                                                        optionFilterProp="label"
                                                                        options={mediaSelectOptions}
                                                                        placeholder="Chon anh co san"
                                                                        onChange={(value) => handleMediaChange(field.name, value)}
                                                                    />
                                                                </Form.Item>
                                                            </Col>
                                                            <Col xs={24} md={12}>
                                                                <Form.Item name={[field.name, 'sort_order']} label="Thu tu">
                                                                    <InputNumber min={0} style={{ width: '100%' }} />
                                                                </Form.Item>
                                                            </Col>
                                                        </Row>
                                                        <Form.Item name={[field.name, 'image_url']} label="URL anh" rules={[{ required: true, message: 'Nhap URL anh' }]}>
                                                            <Input placeholder="https://example.com/member.jpg" />
                                                        </Form.Item>
                                                        <Row gutter={12}>
                                                            <Col xs={24} md={12}>
                                                                <Form.Item name={[field.name, 'alt_text']} label="Alt text">
                                                                    <Input placeholder="Mo ta anh cho SEO/accessibility" />
                                                                </Form.Item>
                                                            </Col>
                                                            <Col xs={24} md={12}>
                                                                <Form.Item name={[field.name, 'caption']} label="Caption">
                                                                    <Input placeholder="Chu thich anh" />
                                                                </Form.Item>
                                                            </Col>
                                                        </Row>
                                                        <Space wrap>
                                                            <Form.Item name={[field.name, 'is_featured']} valuePropName="checked" style={{ marginBottom: 0 }}>
                                                                <Switch checkedChildren="Dai dien" unCheckedChildren="Anh phu" />
                                                            </Form.Item>
                                                            <Button danger icon={<DeleteOutlined />} onClick={() => remove(field.name)}>Xoa anh</Button>
                                                        </Space>
                                                    </Col>
                                                </Row>
                                            </Card>
                                        );
                                    })}
                                    <Button type="dashed" icon={<PlusOutlined />} onClick={() => add({ image_url: '', alt_text: '', caption: '', is_featured: fields.length === 0, sort_order: fields.length })}>
                                        Them anh nhan su
                                    </Button>
                                    <Text type="secondary">Neu khong chon anh dai dien, he thong tu lay anh dau tien lam anh dai dien ngoai website.</Text>
                                </Space>
                            )}
                        </Form.List>
                    </Card>
                </Space>
            </Form>
        </Drawer>
    );
}
