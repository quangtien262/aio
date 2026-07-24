import { useMemo, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Modal from 'antd/es/modal';
import Popconfirm from 'antd/es/popconfirm';
import Radio from 'antd/es/radio';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Switch from 'antd/es/switch';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import CollapsibleFormCard from '../../../shared/components/CollapsibleFormCard';
import MultiMediaPicker from '../../../shared/components/MultiMediaPicker';
import RichContentEditor from '../../../shared/components/RichContentEditor';
import useAdminRouteResource from '../../../shared/hooks/useAdminRouteResource';
import useAutoSlug from '../../../shared/hooks/useAutoSlug';
import { adminApi } from '../../../shared/config/routes';

const { Paragraph, Text, Title } = Typography;
const { TextArea } = Input;

const EMPTY_LISTING = {
    title: '',
    slug: '',
    code: '',
    property_type_id: null,
    transaction_type: 'sale',
    publication_status: 'draft',
    availability_status: 'available',
    price: null,
    price_unit: 'tổng',
    currency: 'VND',
    province: '',
    district: '',
    ward: '',
    address: '',
    bedrooms: null,
    bathrooms: null,
    floors: null,
    land_area: null,
    floor_area: null,
    direction: '',
    legal_status: '',
    furnishing_status: '',
    virtual_tour_url: '',
    video_url: '',
    summary: '',
    content: '',
    meta_title: '',
    meta_description: '',
    meta_keywords: '',
    gallery_images: [],
    is_featured: false,
    is_hot: false,
    sort_order: 0,
};

export default function RealEstateManagerPage({ callAdminApi, runAdminAction, currentPermissions }) {
    const [form] = Form.useForm();
    const [typeForm] = Form.useForm();
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [typeModalOpen, setTypeModalOpen] = useState(false);
    const [editing, setEditing] = useState(null);
    const [collapsedSections, setCollapsedSections] = useState({ specifications: true });
    const galleryImages = Form.useWatch('gallery_images', form) ?? [];
    const title = Form.useWatch('title', form) ?? '';
    const content = Form.useWatch('content', form) ?? '';
    const canCreate = (currentPermissions ?? []).includes('real-estate.create');
    const canUpdate = (currentPermissions ?? []).includes('real-estate.update');
    const canDelete = (currentPermissions ?? []).includes('real-estate.delete');
    const canManageTypes = (currentPermissions ?? []).includes('real-estate.type.manage');

    const { data, loading, error, reload } = useAdminRouteResource({
        enabled: true,
        loader: async () => (await callAdminApi(adminApi('real-estate/listings'))).data,
        deps: [],
    });

    const typeOptions = useMemo(() => (data?.types ?? []).map((type) => ({
        value: type.id,
        label: `${type.name} (${type.listings_count ?? 0})`,
    })), [data?.types]);
    const syncSlugSource = useAutoSlug({ form, sourceValue: title });

    const openListing = (record = null) => {
        setEditing(record);
        form.setFieldsValue(record ? { ...EMPTY_LISTING, ...record } : EMPTY_LISTING);
        syncSlugSource(record?.title ?? '');
        setCollapsedSections({ specifications: true });
        setDrawerOpen(true);
    };

    const toggleSection = (sectionKey) => {
        setCollapsedSections((current) => ({
            ...current,
            [sectionKey]: !current[sectionKey],
        }));
    };

    const submitListing = async () => {
        const values = await form.validateFields();
        const endpoint = editing
            ? adminApi(`real-estate/listings/${editing.id}`)
            : adminApi('real-estate/listings');
        await runAdminAction(
            () => callAdminApi(endpoint, {
                method: editing ? 'PUT' : 'POST',
                body: JSON.stringify(values),
            }),
            editing ? 'Đã cập nhật tin bất động sản.' : 'Đã tạo tin bất động sản.',
            reload,
        );
        setDrawerOpen(false);
    };

    const submitType = async () => {
        const values = await typeForm.validateFields();
        await runAdminAction(
            () => callAdminApi(adminApi('real-estate/property-types'), {
                method: 'POST',
                body: JSON.stringify(values),
            }),
            'Đã tạo loại hình bất động sản.',
            reload,
        );
        setTypeModalOpen(false);
        typeForm.resetFields();
    };

    const columns = [
        {
            title: 'Tin đăng',
            dataIndex: 'title',
            render: (_, record) => (
                <Space>
                    {record.image_url ? <img src={record.image_url} alt="" style={{ width: 72, height: 52, objectFit: 'cover', borderRadius: 8 }} /> : null}
                    <Space direction="vertical" size={0}>
                        <Button type="link" style={{ padding: 0 }} onClick={() => openListing(record)}>{record.title}</Button>
                        <Text type="secondary">{record.code} · {record.property_type_name}</Text>
                    </Space>
                </Space>
            ),
        },
        {
            title: 'Giao dịch',
            dataIndex: 'transaction_type',
            width: 110,
            render: (value) => <Tag color={value === 'rent' ? 'orange' : 'blue'}>{value === 'rent' ? 'Cho thuê' : 'Bán'}</Tag>,
        },
        {
            title: 'Giá',
            dataIndex: 'price',
            width: 170,
            render: (value, record) => value ? `${Number(value).toLocaleString('vi-VN')} ${record.currency}${record.price_unit === 'tháng' ? '/tháng' : ''}` : 'Liên hệ',
        },
        {
            title: 'Trạng thái',
            dataIndex: 'publication_status',
            width: 130,
            render: (value) => <Tag color={value === 'published' ? 'green' : 'default'}>{value === 'published' ? 'Đã xuất bản' : 'Bản nháp'}</Tag>,
        },
        {
            title: 'Tác vụ',
            key: 'actions',
            width: 150,
            render: (_, record) => (
                <Space>
                    <Button size="small" disabled={!canUpdate} onClick={() => openListing(record)}>Sửa</Button>
                    <Popconfirm title="Xóa tin đăng này?" onConfirm={() => runAdminAction(
                        () => callAdminApi(adminApi(`real-estate/listings/${record.id}`), { method: 'DELETE' }),
                        'Đã xóa tin bất động sản.',
                        reload,
                    )}>
                        <Button size="small" danger disabled={!canDelete}>Xóa</Button>
                    </Popconfirm>
                </Space>
            ),
        },
    ];

    if (error) return <Alert type="error" showIcon message={error} />;

    return (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card>
                <Row justify="space-between" align="middle" gutter={[16, 16]}>
                    <Col>
                        <Title level={3} style={{ margin: 0 }}>Bất động sản</Title>
                        <Paragraph style={{ margin: '4px 0 0' }}>Quản lý tin bán, cho thuê, thông số và gallery nhiều ảnh theo từng website.</Paragraph>
                    </Col>
                    <Col>
                        <Space>
                            <Button disabled={!canManageTypes} onClick={() => setTypeModalOpen(true)}>Thêm loại hình</Button>
                            <Button type="primary" disabled={!canCreate} onClick={() => openListing()}>Thêm tin đăng</Button>
                        </Space>
                    </Col>
                </Row>
            </Card>
            <Row gutter={16}>
                <Col xs={24} md={8}><Card><Text type="secondary">Tổng tin</Text><Title level={3}>{data?.total ?? 0}</Title></Card></Col>
                <Col xs={24} md={8}><Card><Text type="secondary">Đang bán</Text><Title level={3}>{data?.metrics?.for_sale ?? 0}</Title></Card></Col>
                <Col xs={24} md={8}><Card><Text type="secondary">Cho thuê</Text><Title level={3}>{data?.metrics?.for_rent ?? 0}</Title></Card></Col>
            </Row>
            <Card>
                <Table rowKey="id" loading={loading} columns={columns} dataSource={data?.items ?? []} scroll={{ x: 980 }} pagination={{ pageSize: 10 }} />
            </Card>

            <Drawer
                open={drawerOpen}
                onClose={() => setDrawerOpen(false)}
                width="min(1160px, 96vw)"
                className="cms-page-drawer real-estate-listing-drawer"
                destroyOnHidden
                maskClosable={false}
                title={editing ? `Sửa tin: ${editing.title}` : 'Tạo tin bất động sản'}
                extra={<Space><Button onClick={() => setDrawerOpen(false)}>Hủy</Button><Button type="primary" onClick={submitListing}>Lưu tin</Button></Space>}
            >
                <Form form={form} layout="vertical" className="real-estate-listing-form">
                    <div className="cms-post-form-shell">
                        <CollapsibleFormCard
                            sectionKey="basic"
                            title="Thông tin cơ bản"
                            collapsed={Boolean(collapsedSections.basic)}
                            onToggle={() => toggleSection('basic')}
                        >
                            <Row gutter={[16, 0]}>
                                <Col xs={24} md={16}><Form.Item name="title" label="Tiêu đề tin đăng" rules={[{ required: true, message: 'Vui lòng nhập tiêu đề tin đăng' }]}><Input placeholder="Ví dụ: Bán biệt thự hiện đại tại Thảo Điền" /></Form.Item></Col>
                                <Col xs={24} md={8}><Form.Item name="code" label="Mã tin" extra="Để trống để hệ thống tự sinh."><Input placeholder="BDS701-001" /></Form.Item></Col>
                                <Col xs={24} md={12}><Form.Item name="slug" label="Đường dẫn (slug)"><Input placeholder="Tự sinh từ tiêu đề" /></Form.Item></Col>
                                <Col xs={24} md={12}><Form.Item name="property_type_id" label="Loại hình" rules={[{ required: true, message: 'Vui lòng chọn loại hình' }]}><Select showSearch optionFilterProp="label" placeholder="Chọn loại hình bất động sản" options={typeOptions} /></Form.Item></Col>
                            </Row>
                            <div className="real-estate-status-grid">
                                <div className="real-estate-choice-panel">
                                    <Text strong>Giao dịch</Text>
                                    <Form.Item name="transaction_type" noStyle><Radio.Group buttonStyle="solid" optionType="button" options={[{ label: 'Bán', value: 'sale' }, { label: 'Cho thuê', value: 'rent' }]} /></Form.Item>
                                </div>
                                <div className="real-estate-choice-panel">
                                    <Text strong>Xuất bản</Text>
                                    <Form.Item name="publication_status" noStyle><Radio.Group buttonStyle="solid" optionType="button" options={[{ label: 'Bản nháp', value: 'draft' }, { label: 'Đã xuất bản', value: 'published' }]} /></Form.Item>
                                </div>
                                <div className="real-estate-choice-panel">
                                    <Text strong>Tình trạng</Text>
                                    <Form.Item name="availability_status" noStyle><Select options={[
                                        { label: 'Đang giao dịch', value: 'available' }, { label: 'Đã giữ chỗ', value: 'reserved' },
                                        { label: 'Đã bán', value: 'sold' }, { label: 'Đã cho thuê', value: 'rented' },
                                    ]} /></Form.Item>
                                </div>
                            </div>
                        </CollapsibleFormCard>

                        <CollapsibleFormCard
                            sectionKey="pricing"
                            title="Giá và điều kiện giao dịch"
                            collapsed={Boolean(collapsedSections.pricing)}
                            onToggle={() => toggleSection('pricing')}
                        >
                            <Row gutter={[16, 0]}>
                                <Col xs={24} md={12}><Form.Item name="price" label="Giá"><InputNumber min={0} controls={false} placeholder="Nhập giá bất động sản" style={{ width: '100%' }} /></Form.Item></Col>
                                <Col xs={12} md={6}><Form.Item name="currency" label="Tiền tệ"><Input placeholder="VND" /></Form.Item></Col>
                                <Col xs={12} md={6}><Form.Item name="price_unit" label="Đơn vị giá"><Input placeholder="tổng, tháng, m²..." /></Form.Item></Col>
                            </Row>
                        </CollapsibleFormCard>

                        <CollapsibleFormCard
                            sectionKey="location"
                            title="Vị trí bất động sản"
                            collapsed={Boolean(collapsedSections.location)}
                            onToggle={() => toggleSection('location')}
                        >
                            <Row gutter={[16, 0]}>
                                <Col xs={24} md={12}><Form.Item name="province" label="Tỉnh / Thành phố"><Input placeholder="Hồ Chí Minh" /></Form.Item></Col>
                                <Col xs={24} md={12}><Form.Item name="ward" label="Phường / Xã"><Input placeholder="Phường Thảo Điền" /></Form.Item></Col>
                                <Col span={24}><Form.Item name="address" label="Địa chỉ chi tiết" style={{ marginBottom: 0 }}><Input placeholder="Số nhà, tên đường, khu dân cư hoặc dự án" /></Form.Item></Col>
                            </Row>
                        </CollapsibleFormCard>

                        <CollapsibleFormCard
                            sectionKey="specifications"
                            title="Thông số và đặc điểm"
                            collapsed={Boolean(collapsedSections.specifications)}
                            onToggle={() => toggleSection('specifications')}
                        >
                            <Row gutter={[16, 0]}>
                                {[
                                    ['bedrooms', 'Phòng ngủ'], ['bathrooms', 'Phòng tắm'], ['floors', 'Số tầng'],
                                ].map(([name, label]) => <Col xs={12} md={8} key={name}><Form.Item name={name} label={label}><InputNumber min={0} precision={0} controls={false} style={{ width: '100%' }} /></Form.Item></Col>)}
                                <Col xs={24} md={12}><Form.Item name="land_area" label="Diện tích đất (m²)"><InputNumber min={0} controls={false} style={{ width: '100%' }} /></Form.Item></Col>
                                <Col xs={24} md={12}><Form.Item name="floor_area" label="Diện tích sàn (m²)"><InputNumber min={0} controls={false} style={{ width: '100%' }} /></Form.Item></Col>
                                <Col xs={24} md={8}><Form.Item name="direction" label="Hướng"><Input placeholder="Đông Nam" /></Form.Item></Col>
                                <Col xs={24} md={8}><Form.Item name="legal_status" label="Pháp lý"><Input placeholder="Sổ hồng riêng" /></Form.Item></Col>
                                <Col xs={24} md={8}><Form.Item name="furnishing_status" label="Nội thất"><Input placeholder="Nội thất đầy đủ" /></Form.Item></Col>
                            </Row>
                        </CollapsibleFormCard>

                        <CollapsibleFormCard
                            sectionKey="media"
                            title="Hình ảnh và nội dung đa phương tiện"
                            collapsed={Boolean(collapsedSections.media)}
                            onToggle={() => toggleSection('media')}
                        >
                            <Row gutter={[16, 0]}>
                                <Col xs={24} md={12}><Form.Item name="virtual_tour_url" label="Đường dẫn Tour 360"><Input placeholder="https://..." /></Form.Item></Col>
                                <Col xs={24} md={12}><Form.Item name="video_url" label="Đường dẫn video"><Input placeholder="YouTube hoặc URL video" /></Form.Item></Col>
                            </Row>
                            <Form.Item name="gallery_images" hidden><Input /></Form.Item>
                            <Form.Item label="Thư viện ảnh bất động sản" style={{ marginBottom: 0 }}>
                                <MultiMediaPicker
                                    open={drawerOpen}
                                    value={galleryImages}
                                    onChange={(next) => form.setFieldValue('gallery_images', next)}
                                    canManage={editing ? canUpdate : canCreate}
                                    callAdminApi={callAdminApi}
                                    recordTitle={title || 'Bất động sản'}
                                    previewTitle="Gallery bất động sản"
                                    uploadButtonLabel="Upload nhiều ảnh"
                                    compactPreview
                                />
                            </Form.Item>
                        </CollapsibleFormCard>

                        <CollapsibleFormCard
                            sectionKey="content"
                            title="Nội dung tin đăng"
                            collapsed={Boolean(collapsedSections.content)}
                            onToggle={() => toggleSection('content')}
                            className="cms-post-form-card-editor"
                        >
                            <Form.Item name="summary" label="Mô tả ngắn"><TextArea rows={3} placeholder="Tóm tắt những điểm nổi bật để hiển thị trên danh sách." /></Form.Item>
                            <Form.Item label="Nội dung chi tiết" style={{ marginBottom: 0 }}>
                                <RichContentEditor
                                    value={content}
                                    onChange={(nextContent) => form.setFieldValue('content', nextContent)}
                                    disabled={editing ? !canUpdate : !canCreate}
                                    callAdminApi={callAdminApi}
                                    recordKey={editing?.id ?? 'new'}
                                    open={drawerOpen}
                                    htmlPlaceholder="<section>Nhập mã HTML nội dung tin bất động sản...</section>"
                                />
                            </Form.Item>
                            <Form.Item name="content" hidden><TextArea /></Form.Item>
                        </CollapsibleFormCard>

                        <CollapsibleFormCard
                            sectionKey="display"
                            title="Thiết lập hiển thị"
                            collapsed={Boolean(collapsedSections.display)}
                            onToggle={() => toggleSection('display')}
                        >
                            <Row gutter={[16, 0]} align="middle">
                                <Col xs={24} md={8}>
                                    <div className="real-estate-switch-panel">
                                        <div><Text strong>Đánh dấu nổi bật</Text><Text type="secondary">Ưu tiên tại các khối nổi bật.</Text></div>
                                        <Form.Item name="is_featured" valuePropName="checked" noStyle><Switch checkedChildren="Bật" unCheckedChildren="Tắt" /></Form.Item>
                                    </div>
                                </Col>
                                <Col xs={24} md={8}>
                                    <div className="real-estate-switch-panel">
                                        <div><Text strong>Tin hot</Text><Text type="secondary">Gắn nhãn nổi bật trên tin.</Text></div>
                                        <Form.Item name="is_hot" valuePropName="checked" noStyle><Switch checkedChildren="Bật" unCheckedChildren="Tắt" /></Form.Item>
                                    </div>
                                </Col>
                                <Col xs={24} md={8}><Form.Item name="sort_order" label="Thứ tự ưu tiên" style={{ marginBottom: 0 }}><InputNumber min={0} precision={0} style={{ width: '100%' }} /></Form.Item></Col>
                            </Row>
                        </CollapsibleFormCard>

                        <CollapsibleFormCard
                            sectionKey="seo"
                            title="Tối ưu tìm kiếm (SEO)"
                            collapsed={Boolean(collapsedSections.seo)}
                            onToggle={() => toggleSection('seo')}
                        >
                            <Form.Item name="meta_title" label="SEO Title"><Input placeholder="Tiêu đề hiển thị trên công cụ tìm kiếm" /></Form.Item>
                            <Row gutter={[16, 0]}>
                                <Col xs={24} md={12}><Form.Item name="meta_description" label="SEO Description" style={{ marginBottom: 0 }}><TextArea rows={3} placeholder="Mô tả ngắn cho kết quả tìm kiếm" /></Form.Item></Col>
                                <Col xs={24} md={12}><Form.Item name="meta_keywords" label="SEO Keywords" style={{ marginBottom: 0 }}><TextArea rows={3} placeholder="biệt thự, căn hộ, bất động sản..." /></Form.Item></Col>
                            </Row>
                        </CollapsibleFormCard>
                    </div>
                </Form>
            </Drawer>

            <Modal open={typeModalOpen} title="Thêm loại hình bất động sản" onCancel={() => setTypeModalOpen(false)} onOk={submitType} okText="Tạo loại hình">
                <Form form={typeForm} layout="vertical" initialValues={{ is_active: true, sort_order: 0 }}>
                    <Form.Item name="name" label="Tên loại hình" rules={[{ required: true }]}><Input placeholder="Biệt thự, Căn hộ..." /></Form.Item>
                    <Form.Item name="slug" label="Slug"><Input placeholder="Tự sinh từ tên" /></Form.Item>
                    <Form.Item name="image_url" label="Ảnh đại diện"><Input /></Form.Item>
                    <Form.Item name="description" label="Mô tả"><TextArea rows={3} /></Form.Item>
                    <Form.Item name="sort_order" label="Thứ tự"><InputNumber min={0} /></Form.Item>
                    <Form.Item name="is_active" valuePropName="checked"><Switch checkedChildren="Kích hoạt" unCheckedChildren="Tạm ẩn" /></Form.Item>
                </Form>
            </Modal>
        </Space>
    );
}
