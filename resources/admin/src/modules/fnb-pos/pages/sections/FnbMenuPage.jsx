import EditOutlined from '@ant-design/icons/EditOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Drawer from 'antd/es/drawer';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Popconfirm from 'antd/es/popconfirm';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Switch from 'antd/es/switch';
import Table from 'antd/es/table';
import Tabs from 'antd/es/tabs';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { useCallback, useMemo, useState } from 'react';
import { createIdempotencyKey } from '../../api/fnbApi';
import FnbPageHeader from '../../components/FnbPageHeader';
import { FnbResourceError } from '../../components/FnbResourceState';
import FnbStatusTag from '../../components/FnbStatusTag';
import useFnbResource from '../../hooks/useFnbResource';
import { useFnbWorkspace } from '../../state/FnbWorkspaceContext';
import { asArray, formatMinorMoney, formatQuantity } from '../../utils/fnbFormat';

const { Text } = Typography;

function itemPrice(item) {
    const variants = asArray(item.variants);
    const defaultVariant = variants.find((variant) => variant.is_default && variant.status !== 'inactive')
        ?? variants.find((variant) => variant.status !== 'inactive');

    return defaultVariant?.price_minor ?? defaultVariant?.base_price_minor ?? null;
}

function MenuItemDrawer({ open, item, catalog, form, saving, onClose, onSubmit }) {
    const stationOptions = asArray(catalog.stations).map((station) => ({
        value: station.id,
        label: `${station.name}${station.status === 'inactive' ? ' · tạm ngưng' : ''}`,
        disabled: station.status === 'inactive',
    }));
    const defaultStationId = stationOptions.find((station) => !station.disabled)?.value;
    const modifierOptions = asArray(catalog.modifier_groups).map((group) => ({ value: group.id, label: group.name }));
    const itemType = Form.useWatch('item_type', form);

    return (
        <Drawer
            title={item ? `Cập nhật món · ${item.name}` : 'Thêm món mới'}
            open={open}
            width="min(720px, 96vw)"
            onClose={onClose}
            destroyOnHidden
            extra={<Button type="primary" loading={saving} onClick={() => form.submit()}>Lưu món</Button>}
        >
            <Alert
                type="info"
                showIcon
                message="Giá trên bill luôn do máy chủ quyết định"
                description="Màn hình gửi mã món, size, topping và số lượng; không tự tính lại thuế hoặc tổng thanh toán."
                style={{ marginBottom: 18 }}
            />
            <Form form={form} layout="vertical" onFinish={onSubmit} initialValues={{ status: 'active', tax_inclusive: true, tax_rate_percent: 0 }}>
                <Row gutter={16}>
                    <Col xs={24} md={8}>
                        <Form.Item name="code" label="Mã món" rules={[{ required: true, message: 'Nhập mã món.' }]}>
                            <Input maxLength={50} />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={16}>
                        <Form.Item name="name" label="Tên món" rules={[{ required: true, message: 'Nhập tên món.' }]}>
                            <Input />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={12}>
                        <Form.Item name="sku" label="SKU"><Input /></Form.Item>
                    </Col>
                    <Col xs={24} md={12}>
                        <Form.Item name="item_type" label="Loại món" rules={[{ required: true }]}>
                            <Select options={[{ value: 'prepared', label: 'Pha chế' }, { value: 'packaged', label: 'Đóng gói sẵn' }, { value: 'service', label: 'Dịch vụ' }]} />
                        </Form.Item>
                    </Col>
                    <Col span={24}>
                        <Form.Item name="category_ids" label="Nhóm món">
                            <Select mode="multiple" allowClear showSearch optionFilterProp="label" options={asArray(catalog.categories).map((category) => ({ value: category.id, label: category.name }))} placeholder="Chọn một hoặc nhiều nhóm món" />
                        </Form.Item>
                    </Col>
                    <Col span={24}>
                        <Form.Item name="description" label="Mô tả"><Input.TextArea rows={3} /></Form.Item>
                    </Col>
                    <Col xs={24} md={8}>
                        <Form.Item name="tax_rate_percent" label="Thuế suất (%)">
                            <InputNumber min={0} max={100} precision={2} style={{ width: '100%' }} />
                        </Form.Item>
                    </Col>
                    <Col xs={24} md={8}>
                        <Form.Item name="tax_inclusive" label="Giá đã gồm thuế" valuePropName="checked"><Switch /></Form.Item>
                    </Col>
                    <Col xs={24} md={8}>
                        <Form.Item name="status" label="Trạng thái"><Select options={[{ value: 'active', label: 'Đang bán' }, { value: 'inactive', label: 'Tạm ngưng' }]} /></Form.Item>
                    </Col>
                    <Col xs={24} md={12}>
                        <Form.Item name="tax_category" label="Nhóm thuế"><Select options={[{ value: 'standard', label: 'Thuế suất thông thường' }, { value: 'zero_rated', label: 'Thuế suất 0%' }, { value: 'not_subject', label: 'Không chịu thuế' }, { value: 'exempt', label: 'Miễn thuế' }]} /></Form.Item>
                    </Col>
                    <Col xs={24} md={12}>
                        <Form.Item name="image_url" label="Ảnh món (URL)"><Input type="url" /></Form.Item>
                    </Col>
                    <Col span={24}>
                        <Form.Item name="modifier_group_ids" label="Nhóm topping áp dụng">
                            <Select mode="multiple" allowClear options={modifierOptions} placeholder="Chọn nhóm topping" />
                        </Form.Item>
                    </Col>
                    <Col span={24}>
                        <Form.List name="variants" rules={[{ validator: async (_, variants) => {
                            if (!variants?.some((variant) => variant?.status !== 'inactive')) throw new Error('Cần ít nhất một size đang bán.');
                        } }]}>
                            {(fields, { add, remove }, { errors }) => <Space direction="vertical" size={12} style={{ width: '100%' }}>
                                <div className="fnb-table-toolbar">
                                    <Space direction="vertical" size={0}><Text strong>Size / lựa chọn</Text><Text type="secondary">Bỏ một size cũ sẽ chuyển size đó sang tạm ngưng để bảo toàn giao dịch.</Text></Space>
                                    <Button icon={<PlusOutlined />} onClick={() => add({ code: '', name: '', base_price_minor: 0, status: 'active', station_id: defaultStationId })}>Thêm size</Button>
                                </div>
                                {fields.map((field) => <Card key={field.key} size="small">
                                    <Row gutter={12}>
                                        <Col xs={24} md={6}><Form.Item {...field} name={[field.name, 'code']} label="Mã size" rules={[{ required: true }, { pattern: /^[A-Za-z0-9_-]+$/, message: 'Chỉ dùng chữ, số, gạch ngang hoặc gạch dưới.' }]}><Input maxLength={60} /></Form.Item></Col>
                                        <Col xs={24} md={8}><Form.Item {...field} name={[field.name, 'name']} label="Tên size" rules={[{ required: true }]}><Input /></Form.Item></Col>
                                        <Col xs={24} md={7}><Form.Item {...field} name={[field.name, 'base_price_minor']} label="Giá bán (VND)" rules={[{ required: true }]}><InputNumber min={0} precision={0} controls={false} addonAfter="₫" style={{ width: '100%' }} /></Form.Item></Col>
                                        <Col xs={24} md={3} style={{ display: 'flex', alignItems: 'center' }}><Button danger type="text" onClick={() => remove(field.name)}>Bỏ</Button></Col>
                                        <Col xs={24} md={12}><Form.Item {...field} name={[field.name, 'station_id']} label="Trạm pha chế" rules={[{ validator: (_, value) => {
                                            const status = form.getFieldValue(['variants', field.name, 'status']);
                                            return itemType === 'prepared' && status !== 'inactive' && !value ? Promise.reject(new Error('Chọn trạm pha chế.')) : Promise.resolve();
                                        } }]}><Select allowClear showSearch optionFilterProp="label" options={stationOptions} placeholder="Chọn trạm" /></Form.Item></Col>
                                        <Col xs={12} md={6}><Form.Item {...field} name={[field.name, 'status']} label="Trạng thái"><Select options={[{ value: 'active', label: 'Đang bán' }, { value: 'inactive', label: 'Tạm ngưng' }]} /></Form.Item></Col>
                                        <Col xs={12} md={6}><Form.Item {...field} name={[field.name, 'sort_order']} label="Thứ tự"><InputNumber min={0} precision={0} style={{ width: '100%' }} /></Form.Item></Col>
                                        <Form.Item {...field} name={[field.name, 'id']} hidden><Input /></Form.Item>
                                    </Row>
                                </Card>)}
                                <Form.ErrorList errors={errors} />
                            </Space>}
                        </Form.List>
                    </Col>
                    <Col span={24}>
                        <Form.Item noStyle shouldUpdate={(previous, current) => previous.variants !== current.variants}>
                            {({ getFieldValue }) => {
                                const variants = asArray(getFieldValue('variants'));
                                return <Form.Item name="default_variant_index" label="Size mặc định" rules={[{ required: true, message: 'Chọn size mặc định.' }]}>
                                    <Select options={variants.map((variant, index) => ({ value: index, label: variant?.name || variant?.code || `Size ${index + 1}`, disabled: variant?.status === 'inactive' }))} />
                                </Form.Item>;
                            }}
                        </Form.Item>
                    </Col>
                </Row>
            </Form>
        </Drawer>
    );
}

function ModifierDrawer({ open, group, form, saving, onClose, onSubmit }) {
    return (
        <Drawer
            title={group ? `Cập nhật nhóm topping · ${group.name}` : 'Thêm nhóm topping'}
            open={open}
            width="min(680px, 96vw)"
            onClose={onClose}
            destroyOnHidden
            extra={<Button type="primary" loading={saving} onClick={() => form.submit()}>Lưu nhóm</Button>}
        >
            <Form form={form} layout="vertical" onFinish={onSubmit} initialValues={{ min_select: 0, max_select: 1, free_quantity: 0, status: 'active' }}>
                <Row gutter={16}>
                    <Col xs={24} md={9}><Form.Item name="code" label="Mã nhóm" rules={[{ required: true }]}><Input /></Form.Item></Col>
                    <Col xs={24} md={15}><Form.Item name="name" label="Tên nhóm" rules={[{ required: true }]}><Input /></Form.Item></Col>
                    <Col xs={8}><Form.Item name="min_select" label="Chọn tối thiểu" rules={[{ required: true }]}><InputNumber min={0} precision={0} style={{ width: '100%' }} /></Form.Item></Col>
                    <Col xs={8}><Form.Item name="max_select" label="Chọn tối đa" rules={[{ required: true }]}><InputNumber min={1} precision={0} style={{ width: '100%' }} /></Form.Item></Col>
                    <Col xs={8}><Form.Item name="free_quantity" label="Số lượng miễn phí"><InputNumber min={0} precision={0} style={{ width: '100%' }} /></Form.Item></Col>
                </Row>
                <Form.List name="options">
                    {(fields, { add, remove }) => (
                        <Space direction="vertical" size={12} style={{ width: '100%' }}>
                            <Space style={{ width: '100%', justifyContent: 'space-between' }}><Text strong>Các lựa chọn</Text><Button icon={<PlusOutlined />} onClick={() => add({ status: 'active' })}>Thêm topping</Button></Space>
                            {fields.map((field) => (
                                <Card key={field.key} size="small">
                                    <Row gutter={12}>
                                        <Col xs={24} md={7}><Form.Item {...field} name={[field.name, 'code']} label="Mã" rules={[{ required: true }]}><Input /></Form.Item></Col>
                                        <Col xs={24} md={10}><Form.Item {...field} name={[field.name, 'name']} label="Tên" rules={[{ required: true }]}><Input /></Form.Item></Col>
                                        <Col xs={12} md={5}><Form.Item {...field} name={[field.name, 'base_price_delta_minor']} label="Giá thêm"><InputNumber min={0} precision={0} addonAfter="₫" style={{ width: '100%' }} /></Form.Item></Col>
                                        <Col xs={8} md={3}><Form.Item {...field} name={[field.name, 'status']} label="Trạng thái"><Select options={[{ value: 'active', label: 'Bán' }, { value: 'inactive', label: 'Ngưng' }]} /></Form.Item></Col>
                                        <Col xs={4} md={1} style={{ display: 'flex', alignItems: 'center' }}><Button danger type="text" onClick={() => remove(field.name)}>×</Button></Col>
                                        <Form.Item {...field} name={[field.name, 'id']} hidden><Input /></Form.Item>
                                        <Form.Item {...field} name={[field.name, 'version']} hidden><Input /></Form.Item>
                                    </Row>
                                </Card>
                            ))}
                        </Space>
                    )}
                </Form.List>
            </Form>
        </Drawer>
    );
}

function RecipeDrawer({ open, recipe, ingredients, variants, form, saving, onClose, onSubmit }) {
    return (
        <Drawer
            title={recipe ? `Tạo phiên bản mới · ${recipe.name}` : 'Thêm công thức'}
            open={open}
            width="min(760px, 96vw)"
            onClose={onClose}
            destroyOnHidden
            extra={<Button type="primary" loading={saving} onClick={() => form.submit()}>Lưu công thức</Button>}
        >
            <Alert type="info" showIcon message="Công thức đã phát hành không được sửa" description="Khi thay đổi định lượng, hệ thống tạo phiên bản mới để bill cũ giữ đúng snapshot." style={{ marginBottom: 18 }} />
            <Form form={form} layout="vertical" onFinish={onSubmit} initialValues={{ yield_quantity: '1.000000', yield_unit: 'portion', status: 'draft' }}>
                <Row gutter={16}>
                    <Col xs={24} md={8}><Form.Item name="code" label="Mã công thức" rules={[{ required: true }]}><Input /></Form.Item></Col>
                    <Col xs={24} md={16}><Form.Item name="name" label="Tên công thức" rules={[{ required: true }]}><Input /></Form.Item></Col>
                    <Col xs={24}><Form.Item name="variant_id" label="Áp dụng cho món / size" rules={[{ required: true, message: 'Chọn món và size.' }]}><Select showSearch optionFilterProp="label" options={variants} /></Form.Item></Col>
                    <Col xs={24} md={12}><Form.Item name="yield_quantity" label="Thành phẩm" rules={[{ required: true }]}><Input inputMode="decimal" /></Form.Item></Col>
                    <Col xs={24} md={12}><Form.Item name="yield_unit" label="Đơn vị thành phẩm" rules={[{ required: true }]}><Input placeholder="Ví dụ: ly, phần" /></Form.Item></Col>
                </Row>
                <Form.List name="lines">
                    {(fields, { add, remove }) => (
                        <Space direction="vertical" size={12} style={{ width: '100%' }}>
                            <Space style={{ width: '100%', justifyContent: 'space-between' }}><Text strong>Nguyên liệu</Text><Button icon={<PlusOutlined />} onClick={() => add({ quantity: '1.000000' })}>Thêm nguyên liệu</Button></Space>
                            {fields.map((field) => (
                                <Card key={field.key} size="small">
                                    <Row gutter={12}>
                                        <Col xs={24} md={11}><Form.Item {...field} name={[field.name, 'ingredient_id']} label="Nguyên liệu" rules={[{ required: true }]}><Select showSearch optionFilterProp="label" options={ingredients.map((item) => ({ value: item.id, label: item.name }))} /></Form.Item></Col>
                                        <Col xs={8} md={5}><Form.Item {...field} name={[field.name, 'quantity']} label="Số lượng" rules={[{ required: true }]}><Input inputMode="decimal" /></Form.Item></Col>
                                        <Col xs={7} md={4}><Form.Item {...field} name={[field.name, 'unit']} label="Đơn vị" rules={[{ required: true }]}><Select options={['g', 'kg', 'ml', 'l', 'piece'].map((unit) => ({ value: unit, label: unit }))} /></Form.Item></Col>
                                        <Col xs={6} md={3}><Form.Item {...field} name={[field.name, 'loss_rate']} label="Hao hụt"><Input inputMode="decimal" /></Form.Item></Col>
                                        <Col xs={3} md={1} style={{ display: 'flex', alignItems: 'center' }}><Button danger type="text" onClick={() => remove(field.name)}>×</Button></Col>
                                    </Row>
                                </Card>
                            ))}
                        </Space>
                    )}
                </Form.List>
            </Form>
        </Drawer>
    );
}

function CategoryDrawer({ open, category, menus, form, saving, onClose, onSubmit }) {
    return <Drawer title={category ? `Cập nhật nhóm món · ${category.name}` : 'Thêm nhóm món'} open={open} width="min(560px, 96vw)" onClose={onClose} destroyOnHidden extra={<Button type="primary" loading={saving} onClick={() => form.submit()}>Lưu nhóm</Button>}>
        <Form form={form} layout="vertical" onFinish={onSubmit}>
            <Form.Item name="menu_id" label="Thực đơn" rules={[{ required: true }]}><Select options={menus.map((menu) => ({ value: menu.id, label: menu.name }))} /></Form.Item>
            <Row gutter={14}><Col xs={24} md={9}><Form.Item name="code" label="Mã" rules={[{ required: true }]}><Input /></Form.Item></Col><Col xs={24} md={15}><Form.Item name="name" label="Tên nhóm" rules={[{ required: true }]}><Input /></Form.Item></Col></Row>
            <Row gutter={14}><Col xs={12}><Form.Item name="sort_order" label="Thứ tự"><InputNumber min={0} max={9999} precision={0} style={{ width: '100%' }} /></Form.Item></Col><Col xs={12}><Form.Item name="status" label="Trạng thái"><Select options={[{ value: 'active', label: 'Đang dùng' }, { value: 'inactive', label: 'Tạm ngưng' }]} /></Form.Item></Col></Row>
        </Form>
    </Drawer>;
}

function IngredientDrawer({ open, ingredient, form, saving, onClose, onSubmit }) {
    return <Drawer title={ingredient ? `Cập nhật nguyên liệu · ${ingredient.name}` : 'Thêm nguyên liệu'} open={open} width="min(560px, 96vw)" onClose={onClose} destroyOnHidden extra={<Button type="primary" loading={saving} onClick={() => form.submit()}>Lưu nguyên liệu</Button>}>
        <Form form={form} layout="vertical" onFinish={onSubmit}>
            <Row gutter={14}><Col xs={24} md={9}><Form.Item name="code" label="Mã" rules={[{ required: true }]}><Input /></Form.Item></Col><Col xs={24} md={15}><Form.Item name="name" label="Tên nguyên liệu" rules={[{ required: true }]}><Input /></Form.Item></Col></Row>
            <Form.Item name="base_unit" label="Đơn vị gốc" rules={[{ required: true }]}><Select disabled={Boolean(ingredient)} options={['g', 'kg', 'ml', 'l', 'piece'].map((unit) => ({ value: unit, label: unit }))} /></Form.Item>
            <Form.Item name="status" label="Trạng thái"><Select options={[{ value: 'active', label: 'Đang dùng' }, { value: 'inactive', label: 'Tạm ngưng' }]} /></Form.Item>
            {ingredient ? <Alert type="info" showIcon message="Đơn vị gốc không thể đổi sau khi tạo" description="Hãy tạo nguyên liệu mới nếu cần một loại đơn vị khác." /> : null}
        </Form>
    </Drawer>;
}

export default function FnbMenuPage() {
    const { api, outletId, selectedOutlet, can, runCommand } = useFnbWorkspace();
    const [activeTab, setActiveTab] = useState('items');
    const [drawer, setDrawer] = useState(null);
    const [saving, setSaving] = useState(false);
    const [itemForm] = Form.useForm();
    const [modifierForm] = Form.useForm();
    const [recipeForm] = Form.useForm();
    const [categoryForm] = Form.useForm();
    const [ingredientForm] = Form.useForm();
    const loadCatalog = useCallback(() => api.catalog({ include_inactive: true }), [api]);
    const loadRecipes = useCallback(() => api.recipes(), [api]);
    const catalogResource = useFnbResource({ enabled: Boolean(outletId), loader: loadCatalog, deps: [outletId] });
    const recipeResource = useFnbResource({ enabled: Boolean(outletId && can('fnb.recipe.view')), loader: loadRecipes, deps: [outletId] });
    const catalog = catalogResource.data ?? {};
    const recipeData = recipeResource.data ?? {};
    const currency = catalog.outlet?.currency ?? selectedOutlet?.currency ?? 'VND';
    const variantOptions = asArray(catalog.items).flatMap((item) => asArray(item.variants).map((variant) => ({ value: variant.id, label: `${item.name} · ${variant.name}` })));

    const openItem = (item = null) => {
        const variants = asArray(item?.variants).map((variant, index) => ({
            ...variant,
            station_id: asArray(item?.station_routes).find((route) => String(route.variant_id) === String(variant.id))?.prep_station_id ?? null,
            sort_order: variant.sort_order ?? index,
            status: variant.status ?? 'active',
        }));
        const storedDefaultIndex = variants.findIndex((entry) => entry.is_default && entry.status !== 'inactive');
        const firstActiveIndex = variants.findIndex((entry) => entry.status !== 'inactive');
        const defaultVariantIndex = storedDefaultIndex >= 0 ? storedDefaultIndex : Math.max(0, firstActiveIndex);
        itemForm.resetFields();
        itemForm.setFieldsValue(item ? {
            ...item,
            variants,
            default_variant_index: defaultVariantIndex,
            tax_rate_percent: Number(item.tax_rate_bps ?? 0) / 100,
            modifier_group_ids: asArray(item.modifier_group_ids).length ? item.modifier_group_ids : asArray(item.modifier_groups).map((group) => group.id),
            category_ids: asArray(item.category_ids),
        } : {
            status: 'active',
            item_type: 'prepared',
            tax_category: 'standard',
            tax_inclusive: true,
            tax_rate_percent: 0,
            default_variant_index: 0,
            variants: [{ code: 'DEFAULT', name: 'Mặc định', base_price_minor: 0, is_default: true, sort_order: 0, status: 'active', station_id: asArray(catalog.stations).find((station) => station.status !== 'inactive')?.id }],
            category_ids: [],
            modifier_group_ids: [],
        });
        setDrawer({ type: 'item', record: item });
    };

    const saveItem = async (values) => {
        const record = drawer?.record;
        const idempotencyKey = createIdempotencyKey('menu-item');
        const defaultVariantIndex = Number(values.default_variant_index);
        const payload = {
            id: record?.id ?? null,
            code: values.code,
            sku: values.sku || null,
            name: values.name,
            description: values.description || null,
            item_type: values.item_type ?? record?.item_type ?? 'prepared',
            tax_category: values.tax_category ?? record?.tax_category ?? 'standard',
            tax_rate_bps: Math.round(Number(values.tax_rate_percent ?? 0) * 100),
            tax_inclusive: Boolean(values.tax_inclusive),
            image_url: values.image_url || null,
            status: values.status,
            category_ids: asArray(values.category_ids),
            modifier_group_ids: values.modifier_group_ids ?? [],
            variants: asArray(values.variants).map((variant, index) => ({
                ...(variant.id ? { id: variant.id } : {}),
                code: variant.code,
                name: variant.name,
                base_price_minor: variant.base_price_minor,
                is_default: index === defaultVariantIndex && variant.status !== 'inactive',
                sort_order: variant.sort_order ?? index,
                status: variant.status ?? 'active',
                station_id: variant.station_id ?? null,
            })),
            ...(record?.version ? { expected_version: record.version, expected_versions: { [`item:${record.public_id ?? record.id}`]: record.version } } : {}),
        };
        setSaving(true);
        try {
            await runCommand({
                execute: (auth) => api.command(record ? `menu/items/${record.id}` : 'menu/items', payload, { method: record ? 'PUT' : 'POST', idempotencyKey, ...auth }),
                successMessage: record ? 'Đã cập nhật món.' : 'Đã thêm món.',
                onSuccess: async () => { setDrawer(null); await catalogResource.reload({ silent: true }); },
                onConflict: catalogResource.reload,
            });
        } finally {
            setSaving(false);
        }
    };

    const setAvailability = async (item, available) => {
        const idempotencyKey = createIdempotencyKey('availability');
        await runCommand({
            execute: (auth) => api.command(api.outletPath(`items/${item.id}/availability`), {
                available,
                sold_out_until: null,
                reason: available ? 'Mở bán lại tại quầy' : 'Tạm hết món tại điểm bán',
                expected_version: item.availability_version,
                expected_versions: { [`outlet_item_state:${item.public_id ?? item.id}`]: item.availability_version },
            }, { idempotencyKey, ...auth }),
            successMessage: available ? 'Đã mở bán lại món.' : 'Đã báo hết món.',
            onSuccess: () => catalogResource.reload({ silent: true }),
            onConflict: catalogResource.reload,
        });
    };

    const openModifier = (group = null) => {
        modifierForm.resetFields();
        modifierForm.setFieldsValue(group ? { ...group, options: asArray(group.options) } : { min_select: 0, max_select: 1, free_quantity: 0, status: 'active', options: [] });
        setDrawer({ type: 'modifier', record: group });
    };

    const saveModifier = async (values) => {
        const record = drawer?.record;
        const groupKey = createIdempotencyKey('modifier-group');
        const optionValues = asArray(values.options);
        const removedOptions = asArray(record?.options).filter((option) => !optionValues.some((candidate) => candidate.id === option.id));
        const pendingOptions = [...optionValues, ...removedOptions.map((option) => ({ ...option, status: 'inactive' }))];
        const optionKeys = pendingOptions.map(() => createIdempotencyKey('modifier-option'));
        setSaving(true);
        try {
            await runCommand({
                execute: async (auth) => {
                    const saved = await api.command(record ? `menu/modifier-groups/${record.id}` : 'menu/modifier-groups', {
                        code: values.code,
                        name: values.name,
                        min_select: values.min_select,
                        max_select: values.max_select,
                        free_quantity: values.free_quantity ?? 0,
                        status: values.status ?? 'active',
                        ...(record?.version ? { expected_version: record.version } : {}),
                    }, { method: record ? 'PUT' : 'POST', idempotencyKey: groupKey, ...auth });
                    const group = saved.data;
                    for (let index = 0; index < pendingOptions.length; index += 1) {
                        const option = pendingOptions[index];
                        await api.command(option.id ? `menu/modifier-options/${option.id}` : 'menu/modifier-options', {
                            group_id: group.id,
                            code: option.code,
                            name: option.name,
                            base_price_delta_minor: option.base_price_delta_minor ?? 0,
                            status: option.status ?? 'active',
                            ...(option.version ? { expected_version: option.version } : {}),
                        }, { method: option.id ? 'PUT' : 'POST', idempotencyKey: optionKeys[index], ...auth });
                    }
                    return saved;
                },
                successMessage: 'Đã lưu nhóm topping.',
                onSuccess: async () => { setDrawer(null); await catalogResource.reload({ silent: true }); },
                onConflict: catalogResource.reload,
            });
        } finally {
            setSaving(false);
        }
    };

    const openRecipe = (recipe = null) => {
        recipeForm.resetFields();
        recipeForm.setFieldsValue(recipe ? {
            ...recipe,
            variant_id: asArray(recipe.variant_bindings)[0]?.variant_id,
            lines: asArray(recipe.lines).map((line) => ({ ...line, loss_rate: line.loss_rate ?? '0' })),
        } : { yield_quantity: '1.000000', yield_unit: 'ly', lines: [] });
        setDrawer({ type: 'recipe', record: recipe });
    };

    const saveRecipe = async (values) => {
        const record = drawer?.record;
        const idempotencyKey = createIdempotencyKey('recipe');
        setSaving(true);
        try {
            await runCommand({
                execute: (auth) => api.command('recipes/publish', {
                    code: values.code,
                    name: values.name,
                    variant_id: values.variant_id,
                    expected_recipe_version: Number(record?.recipe_version ?? 0),
                    yield_quantity: String(values.yield_quantity),
                    yield_unit: values.yield_unit,
                    lines: asArray(values.lines).map((line) => ({
                        ingredient_id: line.ingredient_id,
                        quantity: String(line.quantity),
                        unit: line.unit,
                        loss_rate: String(line.loss_rate ?? '0'),
                    })),
                }, { idempotencyKey, ...auth }),
                successMessage: 'Đã phát hành phiên bản công thức.',
                onSuccess: async () => { setDrawer(null); await recipeResource.reload({ silent: true }); },
                onConflict: recipeResource.reload,
            });
        } finally {
            setSaving(false);
        }
    };

    const openCategory = (category = null) => {
        categoryForm.resetFields();
        categoryForm.setFieldsValue(category ?? { menu_id: asArray(catalog.menus)[0]?.id, sort_order: 0, status: 'active' });
        setDrawer({ type: 'category', record: category });
    };

    const saveCategory = async (values) => {
        const record = drawer?.record;
        const key = createIdempotencyKey('menu-category');
        setSaving(true);
        try {
            await runCommand({
                execute: (auth) => api.command(record ? `menu/categories/${record.id}` : 'menu/categories', {
                    ...values,
                    ...(record?.version ? { expected_version: record.version } : {}),
                }, { method: record ? 'PUT' : 'POST', idempotencyKey: key, ...auth }),
                successMessage: 'Đã lưu nhóm món.',
                onSuccess: async () => { setDrawer(null); await catalogResource.reload({ silent: true }); },
                onConflict: catalogResource.reload,
            });
        } finally {
            setSaving(false);
        }
    };

    const openIngredient = (ingredient = null) => {
        ingredientForm.resetFields();
        ingredientForm.setFieldsValue(ingredient ?? { base_unit: 'g', status: 'active' });
        setDrawer({ type: 'ingredient', record: ingredient });
    };

    const saveIngredient = async (values) => {
        const record = drawer?.record;
        const key = createIdempotencyKey('ingredient');
        setSaving(true);
        try {
            await runCommand({
                execute: (auth) => api.command(record ? `ingredients/${record.id}` : 'ingredients', {
                    ...values,
                    ...(record?.version ? { expected_version: record.version } : {}),
                }, { method: record ? 'PUT' : 'POST', idempotencyKey: key, ...auth }),
                successMessage: 'Đã lưu nguyên liệu.',
                onSuccess: async () => { setDrawer(null); await recipeResource.reload({ silent: true }); },
                onConflict: recipeResource.reload,
            });
        } finally {
            setSaving(false);
        }
    };

    if (catalogResource.error && !catalogResource.data) {
        return <FnbResourceError error={catalogResource.error} onRetry={catalogResource.reload} title="Không tải được thực đơn" />;
    }

    const items = asArray(catalog.items);
    const menus = asArray(catalog.menus);
    const modifierGroups = asArray(catalog.modifier_groups);
    const priceBooks = asArray(catalog.price_books);

    return (
        <div className="fnb-page-stack">
            <FnbPageHeader
                eyebrow="Thực đơn vận hành"
                title="Món, size, topping và công thức"
                description="Giữ một nguồn giá và định lượng chuẩn; giao dịch cũ luôn dùng snapshot đã khóa."
                actions={<Button icon={<ReloadOutlined />} loading={catalogResource.refreshing} onClick={() => Promise.all([catalogResource.reload({ silent: true }), can('fnb.recipe.view') ? recipeResource.reload({ silent: true }) : null])}>Làm mới</Button>}
            />

            <Card className="fnb-panel">
                <Tabs
                    activeKey={activeTab}
                    onChange={setActiveTab}
                    items={[
                        {
                            key: 'categories',
                            label: `Nhóm món (${asArray(catalog.categories).length})`,
                            children: <>
                                <div className="fnb-table-toolbar"><Text type="secondary">Nhóm món giúp tìm nhanh trên POS; thứ tự do máy chủ lưu.</Text>{can('fnb.menu.manage') ? <Button type="primary" icon={<PlusOutlined />} loading={catalogResource.loading} disabled={catalogResource.loading || !menus.length} onClick={() => openCategory()}>Thêm nhóm</Button> : null}</div>
                                <Table rowKey="id" dataSource={asArray(catalog.categories)} columns={[
                                    { title: 'Nhóm', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.name}</Text><Text type="secondary">{record.code}</Text></Space> },
                                    { title: 'Thực đơn', render: (_, record) => menus.find((menu) => menu.id === record.menu_id)?.name ?? `#${record.menu_id}` },
                                    { title: 'Thứ tự', dataIndex: 'sort_order' },
                                    { title: 'Trạng thái', dataIndex: 'status', render: (value) => <FnbStatusTag status={value} /> },
                                    { title: '', render: (_, record) => can('fnb.menu.manage') ? <Button size="small" onClick={() => openCategory(record)}>Sửa</Button> : null },
                                ]} />
                            </>,
                        },
                        {
                            key: 'items',
                            label: `Món & size (${items.length})`,
                            children: <>
                                <div className="fnb-table-toolbar"><Text type="secondary">Giá hiển thị theo VND, tổng bill do máy chủ tính.</Text>{can('fnb.menu.manage') ? <Button type="primary" icon={<PlusOutlined />} onClick={() => openItem()}>Thêm món</Button> : null}</div>
                                <Table
                                    rowKey={(record) => record.public_id ?? record.id}
                                    loading={catalogResource.loading}
                                    dataSource={items}
                                    scroll={{ x: 860 }}
                                    columns={[
                                        { title: 'Món', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.name}</Text><Text type="secondary">{record.code}{record.sku ? ` · ${record.sku}` : ''}</Text></Space> },
                                        { title: 'Size', render: (_, record) => asArray(record.variants).map((variant) => <Tag color={variant.status === 'inactive' ? 'default' : variant.is_default ? 'green' : undefined} key={variant.id}>{variant.name}{variant.is_default ? ' · mặc định' : ''}</Tag>) },
                                        { title: 'Giá từ', render: (_, record) => formatMinorMoney(itemPrice(record), currency) },
                                        { title: 'Trạm', render: (_, record) => {
                                            const stationNames = [...new Set(asArray(record.station_routes).map((route) => asArray(catalog.stations).find((station) => station.id === route.prep_station_id)?.name).filter(Boolean))];
                                            return stationNames.join(', ') || '—';
                                        } },
                                        { title: 'Trạng thái', render: (_, record) => <Space direction="vertical" size={4}><FnbStatusTag status={record.status} />{record.available === false ? <Tag color="red">Hết món</Tag> : null}</Space> },
                                        { title: 'Thao tác', fixed: 'right', width: 180, render: (_, record) => <Space>{can('fnb.menu.manage') ? <Button size="small" icon={<EditOutlined />} onClick={() => openItem(record)}>Sửa</Button> : null}{can('fnb.menu.availability.update') ? <Popconfirm title={record.available === false ? 'Mở bán lại món này?' : 'Báo hết món tại điểm bán?'} onConfirm={() => setAvailability(record, record.available === false)}><Button size="small" danger={record.available !== false}>{record.available === false ? 'Mở bán' : 'Hết món'}</Button></Popconfirm> : null}</Space> },
                                    ]}
                                />
                            </>,
                        },
                        {
                            key: 'modifiers',
                            label: `Nhóm topping (${modifierGroups.length})`,
                            children: <>
                                <div className="fnb-table-toolbar"><Text type="secondary">Min/max và số lượng miễn phí được máy chủ kiểm tra khi gọi món.</Text>{can('fnb.menu.manage') ? <Button type="primary" icon={<PlusOutlined />} onClick={() => openModifier()}>Thêm nhóm</Button> : null}</div>
                                {modifierGroups.length ? <Table rowKey="id" dataSource={modifierGroups} columns={[
                                    { title: 'Nhóm', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.name}</Text><Text type="secondary">{record.code}</Text></Space> },
                                    { title: 'Quy tắc', render: (_, record) => `Chọn ${record.min_select ?? 0}–${record.max_select ?? 1}` },
                                    { title: 'Lựa chọn', render: (_, record) => asArray(record.options).map((option) => <Tag key={option.id}>{option.name} · {formatMinorMoney(option.price_delta_minor ?? option.base_price_delta_minor, currency)}</Tag>) },
                                    { title: 'Trạng thái', dataIndex: 'status', render: (value) => <FnbStatusTag status={value} /> },
                                    { title: '', render: (_, record) => can('fnb.menu.manage') ? <Button size="small" onClick={() => openModifier(record)}>Sửa</Button> : null },
                                ]} /> : <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Chưa có nhóm topping" />}
                            </>,
                        },
                        {
                            key: 'ingredients',
                            label: `Nguyên liệu (${asArray(recipeData.ingredients).length})`,
                            children: can('fnb.recipe.view') ? <>
                                <div className="fnb-table-toolbar"><Text type="secondary">Đơn vị gốc không đổi sau khi nguyên liệu đã được tạo.</Text>{can('fnb.recipe.manage') ? <Button type="primary" icon={<PlusOutlined />} onClick={() => openIngredient()}>Thêm nguyên liệu</Button> : null}</div>
                                <Table rowKey="id" loading={recipeResource.loading} dataSource={asArray(recipeData.ingredients)} columns={[
                                    { title: 'Nguyên liệu', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.name}</Text><Text type="secondary">{record.code}</Text></Space> },
                                    { title: 'Đơn vị gốc', dataIndex: 'base_unit', render: (value) => <Tag>{value}</Tag> },
                                    { title: 'Trạng thái', dataIndex: 'status', render: (value) => <FnbStatusTag status={value} /> },
                                    { title: '', render: (_, record) => can('fnb.recipe.manage') ? <Button size="small" onClick={() => openIngredient(record)}>Sửa</Button> : null },
                                ]} />
                            </> : <Alert type="info" showIcon message="Bạn chưa có quyền xem nguyên liệu." />,
                        },
                        {
                            key: 'recipes',
                            label: `Công thức (${asArray(recipeData.recipes).length})`,
                            children: can('fnb.recipe.view') ? <>
                                {recipeResource.error ? <Alert type="warning" showIcon message={recipeResource.error.message} style={{ marginBottom: 12 }} /> : null}
                                <div className="fnb-table-toolbar"><Text type="secondary">Định lượng dùng decimal string; mỗi lần lưu sẽ phát hành revision bất biến mới.</Text>{can('fnb.recipe.manage') ? <Button type="primary" icon={<PlusOutlined />} onClick={() => openRecipe()}>Phát hành công thức</Button> : null}</div>
                                <Table rowKey="id" loading={recipeResource.loading} dataSource={asArray(recipeData.recipes)} columns={[
                                    { title: 'Công thức', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.name}</Text><Text type="secondary">{record.code} · v{record.recipe_version}</Text></Space> },
                                    { title: 'Thành phẩm', render: (_, record) => `${formatQuantity(record.yield_quantity)} ${record.yield_unit ?? ''}` },
                                    { title: 'Trạng thái', dataIndex: 'status', render: (value) => <FnbStatusTag status={value} /> },
                                    { title: '', render: (_, record) => can('fnb.recipe.manage') ? <Button size="small" onClick={() => openRecipe(record)}>Tạo phiên bản</Button> : null },
                                ]} />
                            </> : <Alert type="info" showIcon message="Bạn chưa có quyền xem công thức." />,
                        },
                        {
                            key: 'price-books',
                            label: `Bảng giá (${priceBooks.length})`,
                            children: priceBooks.length ? <Table rowKey="id" dataSource={priceBooks} columns={[
                                { title: 'Bảng giá', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.name}</Text><Text type="secondary">{record.code}</Text></Space> },
                                { title: 'Tiền tệ', dataIndex: 'currency' },
                                { title: 'Hiệu lực', render: (_, record) => [record.valid_from, record.valid_to].filter(Boolean).join(' – ') || 'Không giới hạn' },
                                { title: 'Trạng thái', dataIndex: 'status', render: (value) => <FnbStatusTag status={value} /> },
                            ]} /> : <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Chưa có bảng giá riêng; món dùng giá size cơ bản" />,
                        },
                    ]}
                />
            </Card>

            <MenuItemDrawer open={drawer?.type === 'item'} item={drawer?.record} catalog={catalog} form={itemForm} saving={saving} onClose={() => setDrawer(null)} onSubmit={saveItem} />
            <ModifierDrawer open={drawer?.type === 'modifier'} group={drawer?.record} form={modifierForm} saving={saving} onClose={() => setDrawer(null)} onSubmit={saveModifier} />
            <RecipeDrawer open={drawer?.type === 'recipe'} recipe={drawer?.record} ingredients={asArray(recipeData.ingredients)} variants={variantOptions} form={recipeForm} saving={saving} onClose={() => setDrawer(null)} onSubmit={saveRecipe} />
            <CategoryDrawer open={drawer?.type === 'category'} category={drawer?.record} menus={menus} form={categoryForm} saving={saving} onClose={() => setDrawer(null)} onSubmit={saveCategory} />
            <IngredientDrawer open={drawer?.type === 'ingredient'} ingredient={drawer?.record} form={ingredientForm} saving={saving} onClose={() => setDrawer(null)} onSubmit={saveIngredient} />
        </div>
    );
}
