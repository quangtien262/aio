import { useEffect } from 'react';
import Checkbox from 'antd/es/checkbox';
import Col from 'antd/es/col';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Modal from 'antd/es/modal';
import Row from 'antd/es/row';
import Select from 'antd/es/select';

const { TextArea } = Input;

export const emptyCatalogProductForm = {
    id: null,
    catalog_category_id: null,
    name: '',
    slug: '',
    sku: '',
    price: 0,
    original_price: null,
    stock: 0,
    short_description: '',
    detail_content: '',
    highlights: '',
    usage_terms: '',
    usage_location: '',
    image_url: '',
    gallery_images: [],
    sold_count: 0,
    deal_end_at: '',
    is_featured: false,
    sort_order: 0,
    is_active: true,
};

export default function CatalogProductFormModal({ open, canManage, editingProduct, categoryOptions = [], onCancel, onSubmit }) {
    const [form] = Form.useForm();

    useEffect(() => {
        form.setFieldsValue(editingProduct);
    }, [editingProduct, form]);

    const handleSubmit = async () => {
        const values = await form.validateFields();

        await onSubmit?.({
            ...values,
            catalog_category_id: values.catalog_category_id || null,
            slug: values.slug || null,
            original_price: values.original_price ?? null,
            short_description: values.short_description || null,
            detail_content: values.detail_content || null,
            highlights: values.highlights || null,
            usage_terms: values.usage_terms || null,
            usage_location: values.usage_location || null,
            image_url: values.image_url || null,
            gallery_images: String(values.gallery_images || '')
                .split(/\r?\n/)
                .map((item) => item.trim())
                .filter(Boolean),
            sold_count: values.sold_count ?? 0,
            deal_end_at: values.deal_end_at || null,
            is_featured: Boolean(values.is_featured),
            is_active: Boolean(values.is_active),
        });

        form.resetFields();
    };

    const handleCancel = () => {
        form.resetFields();
        onCancel?.();
    };

    return (
        <Modal
            title={editingProduct?.id ? 'Cập nhật sản phẩm' : 'Tạo sản phẩm'}
            open={open}
            onCancel={handleCancel}
            onOk={handleSubmit}
            okButtonProps={{ disabled: !canManage }}
            width={860}
            destroyOnHidden
        >
            <Form form={form} layout="vertical" initialValues={editingProduct}>
                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="name" label="Tên sản phẩm" rules={[{ required: true, message: 'Nhập tên sản phẩm' }]}>
                            <Input placeholder="Áo sơ mi xanh" />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="sku" label="SKU" rules={[{ required: true, message: 'Nhập SKU' }]}>
                            <Input placeholder="SKU-001" />
                        </Form.Item>
                    </Col>
                </Row>

                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="catalog_category_id" label="Danh mục">
                            <Select allowClear options={categoryOptions} placeholder="Chọn danh mục" />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="slug" label="Slug public">
                            <Input placeholder="san-pham-noi-bat" />
                        </Form.Item>
                    </Col>
                </Row>

                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="price" label="Giá" rules={[{ required: true, message: 'Nhập giá' }]}>
                            <InputNumber min={0} style={{ width: '100%' }} />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="stock" label="Tồn kho" rules={[{ required: true, message: 'Nhập tồn kho' }]}>
                            <InputNumber min={0} precision={0} style={{ width: '100%' }} />
                        </Form.Item>
                    </Col>
                </Row>

                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="original_price" label="Giá gốc">
                            <InputNumber min={0} style={{ width: '100%' }} />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="sort_order" label="Thứ tự">
                            <InputNumber min={0} precision={0} style={{ width: '100%' }} />
                        </Form.Item>
                    </Col>
                </Row>

                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="image_url" label="Ảnh cover sản phẩm">
                            <Input placeholder="https://cdn.example.com/product.jpg" />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="short_description" label="Mô tả ngắn">
                            <Input placeholder="Mô tả cho card và detail page" />
                        </Form.Item>
                    </Col>
                </Row>

                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="sold_count" label="Đã mua">
                            <InputNumber min={0} precision={0} style={{ width: '100%' }} />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="deal_end_at" label="Hết hạn deal">
                            <Input type="datetime-local" />
                        </Form.Item>
                    </Col>
                </Row>

                <Form.Item
                    name="gallery_images"
                    label="Gallery ảnh sản phẩm"
                    extra="Mỗi dòng một URL ảnh. Ảnh đầu tiên sẽ là thumbnail đầu tiên ngoài ảnh cover."
                    getValueProps={(value) => ({ value: Array.isArray(value) ? value.join('\n') : (value ?? '') })}
                >
                    <TextArea rows={5} placeholder={['https://cdn.example.com/product-1.jpg', 'https://cdn.example.com/product-2.jpg'].join('\n')} />
                </Form.Item>

                <Form.Item name="highlights" label="Điểm nổi bật" extra="Mỗi dòng là một ý nổi bật hiển thị dạng bullet.">
                    <TextArea rows={5} placeholder={['Buffet hải sản 5 sao', 'Không gian sang trọng', 'Dùng vào tối thứ 7'].join('\n')} />
                </Form.Item>

                <Form.Item name="usage_terms" label="Điều kiện sử dụng" extra="Mỗi dòng là một điều kiện hoặc ghi chú sử dụng.">
                    <TextArea rows={6} placeholder={['Thời hạn sử dụng đến 30/06/2026', 'Áp dụng ăn tại chỗ', 'Đặt chỗ trước khi đến'].join('\n')} />
                </Form.Item>

                <Form.Item name="detail_content" label="Thông tin chi tiết" extra="Nội dung mô tả dài cho phần thông tin chi tiết. Có thể ngăn đoạn bằng dòng trống.">
                    <TextArea rows={8} placeholder="Nhập mô tả dài cho trang chi tiết sản phẩm" />
                </Form.Item>

                <Form.Item name="usage_location" label="Địa điểm sử dụng" extra="Ví dụ: tên địa điểm, địa chỉ, hotline.">
                    <TextArea rows={4} placeholder="La Brasserie - Hotel Nikko HaiPhong..." />
                </Form.Item>
                <Row gutter={16}>
                    <Col span={8}>
                        <Form.Item name="is_featured" valuePropName="checked" label=" " colon={false}>
                            <Checkbox>Đánh dấu nổi bật</Checkbox>
                        </Form.Item>
                    </Col>
                    <Col span={8}>
                        <Form.Item name="is_active" valuePropName="checked" label=" " colon={false}>
                            <Checkbox>Kích hoạt sản phẩm</Checkbox>
                        </Form.Item>
                    </Col>
                </Row>
            </Form>
        </Modal>
    );
}
