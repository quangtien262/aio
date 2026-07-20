import { useEffect, useRef } from 'react';
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
import dayjs from 'dayjs';
import SingleMediaPicker from '../../../shared/components/SingleMediaPicker';

function toSlug(value, { trimEdges = true } = {}) {
    const slug = String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'd')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-');

    return trimEdges ? slug.replace(/^-+|-+$/g, '') : slug.replace(/^-+/g, '');
}

export default function CmsTeamMemberFormModal({ open, canManage, editingMember, mediaOptions = [], callAdminApi, onCancel, onSubmit }) {
    const [form] = Form.useForm();
    const slugEditedRef = useRef(Boolean(editingMember?.id));
    const nameValue = Form.useWatch('name', form) ?? '';

    useEffect(() => {
        const featuredImage = editingMember?.images?.find((image) => image?.is_featured)
            ?? editingMember?.images?.[0]
            ?? null;

        form.setFieldsValue({
            ...editingMember,
            images: editingMember?.images?.length ? editingMember.images : [],
            featured_image_url: editingMember?.featured_image_url || featuredImage?.image_url || '',
            featured_image_alt: editingMember?.featured_image_alt || featuredImage?.alt_text || '',
        });
        slugEditedRef.current = Boolean(editingMember?.id || editingMember?.slug);
    }, [editingMember, form]);

    useEffect(() => {
        if (slugEditedRef.current) {
            return;
        }

        form.setFieldValue('slug', toSlug(nameValue));
    }, [form, nameValue]);

    const handleSlugChange = (event) => {
        slugEditedRef.current = true;
        form.setFieldValue('slug', toSlug(event.target.value, { trimEdges: false }));
    };

    const handleSubmit = async () => {
        const values = await form.validateFields();
        const {
            featured_image_url: rawFeaturedImageUrl,
            featured_image_alt: featuredImageAlt,
            ...payloadValues
        } = values;
        const featuredImageUrl = String(rawFeaturedImageUrl ?? '').trim();
        const existingImages = editingMember?.images ?? [];
        const normalizedImages = featuredImageUrl
            ? [
                {
                    image_url: featuredImageUrl,
                    alt_text: featuredImageAlt || values.name || null,
                    caption: null,
                    is_featured: true,
                    sort_order: 0,
                },
                ...existingImages
                    .filter((image) => image?.image_url && image.image_url !== featuredImageUrl)
                    .map((image, index) => ({
                        ...image,
                        is_featured: false,
                        sort_order: Number(image.sort_order ?? index + 1),
                    })),
            ]
            : [];

        await onSubmit?.({
            ...payloadValues,
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
            images: normalizedImages,
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
                                    <Input placeholder="/vi/contact hoặc https://..." />
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

                    <Card size="small" className="cms-post-form-card" title="Anh dai dien nhan su">
                        <Form.Item name="featured_image_url" style={{ marginBottom: 0 }}>
                            <SingleMediaPicker
                                open={open}
                                canManage={canManage}
                                callAdminApi={callAdminApi}
                                mediaOptions={mediaOptions}
                                recordTitle={nameValue || 'Team member image'}
                                previewTitle="Anh dai dien nhan su"
                                uploadButtonLabel="Upload anh truc tiep"
                                uploadHint="Anh upload xong se tu duoc gan lam anh dai dien nhan su."
                                libraryButtonLabel="Mo thu vien media"
                                libraryHint="Chon lai tu media CMS da co san."
                                urlPlaceholder="https://example.com/member.jpg"
                                urlButtonLabel="Luu URL va gan anh"
                            />
                        </Form.Item>
                        <Form.Item name="featured_image_alt" label="Alt text" style={{ marginTop: 12, marginBottom: 0 }}>
                            <Input placeholder="Mo ta anh chan dung nhan su" />
                        </Form.Item>
                    </Card>
                </Space>
            </Form>
        </Drawer>
    );
}
