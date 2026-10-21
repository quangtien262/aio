import ShopOutlined from '@ant-design/icons/ShopOutlined';
import CoffeeOutlined from '@ant-design/icons/CoffeeOutlined';
import DownOutlined from '@ant-design/icons/DownOutlined';
import DeleteOutlined from '@ant-design/icons/DeleteOutlined';
import EditOutlined from '@ant-design/icons/EditOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import SettingOutlined from '@ant-design/icons/SettingOutlined';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import PictureOutlined from '@ant-design/icons/PictureOutlined';
import Alert from 'antd/es/alert';
import App from 'antd/es/app';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Collapse from 'antd/es/collapse';
import Drawer from 'antd/es/drawer';
import Dropdown from 'antd/es/dropdown';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Image from 'antd/es/image';
import Modal from 'antd/es/modal';
import Radio from 'antd/es/radio';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Switch from 'antd/es/switch';
import Table from 'antd/es/table';
import Tabs from 'antd/es/tabs';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { createIdempotencyKey } from '../../api/fnbApi';
import FnbMenuChoiceManagerModal from '../../components/FnbMenuChoiceManagerModal';
import FnbUnitManagerModal from '../../components/FnbUnitManagerModal';
import FnbCategoryList from '../../components/FnbCategoryList';
import FnbPageHeader from '../../components/FnbPageHeader';
import FnbItemImages from '../../components/FnbItemImages';
import { FnbResourceError } from '../../components/FnbResourceState';
import FnbStatusTag from '../../components/FnbStatusTag';
import useFnbResource from '../../hooks/useFnbResource';
import { useFnbWorkspace } from '../../state/FnbWorkspaceContext';
import { asArray, formatMinorMoney, formatQuantity } from '../../utils/fnbFormat';

const { Text } = Typography;

function MenuItemCover({ item }) {
    const [failedUrl, setFailedUrl] = useState(null);
    const url = item.image_url || asArray(item.gallery_images)[0];
    return url && failedUrl !== url
        ? <Image className="fnb-menu-cover-thumb" src={url} alt={`Ảnh đại diện ${item.name}`} width={52} height={52} loading="lazy" preview={{ mask: 'Xem ảnh' }} onError={() => setFailedUrl(url)} />
        : <span className="fnb-menu-cover-placeholder" role="img" aria-label={`Chưa có ảnh đại diện ${item.name}`} title="Chưa có ảnh đại diện"><PictureOutlined /></span>;
}

function itemPrice(item) {
    const variants = asArray(item.variants);
    const defaultVariant = variants.find((variant) => variant.is_default && variant.status !== 'inactive')
        ?? variants.find((variant) => variant.status !== 'inactive');

    return defaultVariant?.price_minor ?? defaultVariant?.base_price_minor ?? null;
}

function choiceOptions(definitions, current = []) {
    const byName = new Map(asArray(definitions).map((row) => [row.name, { value: row.name, label: `${row.name} · ${row.code}`, code: row.code }]));
    current.forEach((row) => { if (row?.name && !byName.has(row.name)) byName.set(row.name, { value: row.name, label: `${row.name} · ${row.code}`, code: row.code }); });
    return [...byName.values()];
}

function MenuItemDrawer({ open, item, catalog, form, saving, onClose, onSubmit, onCatalogChanged }) {
    const { can } = useFnbWorkspace();
    const manageable = can('fnb.menu.manage') && can('fnb.menu.master.manage');
    const [sizeManager, setSizeManager] = useState(null);
    const [uploadingImages, setUploadingImages] = useState(false);
    const busy = saving || uploadingImages;
    const variants = Form.useWatch('variants', form) ?? [];
    const sizeOptions = choiceOptions(catalog.size_definitions, variants);
    const sizeLabel = (row) => <Space size={4}>Size{manageable ? <Button type="text" size="small" icon={<PlusOutlined />} aria-label="Quản lý size" disabled={saving} onClick={() => setSizeManager({ row })} /> : null}</Space>;

    const [additionalOpen, setAdditionalOpen] = useState([]);
    const stationOptions = asArray(catalog.stations).map((station) => ({
        value: station.id,
        label: `${station.name}${station.status === 'inactive' ? ' · tạm ngưng' : ''}`,
        disabled: station.status === 'inactive',
    }));
    const defaultStationId = stationOptions.find((station) => !station.disabled)?.value;
    const modifierOptions = asArray(catalog.modifier_groups).map((group) => ({ value: group.id, label: group.name }));
    const itemType = Form.useWatch('item_type', form);
    const moneyProps = {
        formatter: (value) => value === undefined || value === null || value === '' ? '' : String(value).replace(/\B(?=(\d{3})+(?!\d))/g, '.'),
        parser: (value) => (value ?? '').replace(/\./g, ''),
    };

    return (
        <><FnbMenuChoiceManagerModal open={Boolean(sizeManager)} kind="size" definitions={asArray(catalog.size_definitions)} onClose={() => setSizeManager(null)} onSaved={onCatalogChanged} onCreated={(record) => {
            if (record && Number.isInteger(sizeManager?.row)) {
                form.setFieldValue(['variants', sizeManager.row, 'name'], record.name);
                form.setFieldValue(['variants', sizeManager.row, 'code'], record.code);
                setSizeManager(null);
            }
        }} />
        <Drawer
            title={<span className="fnb-menu-editor-title"><ShopOutlined />{item ? `Cập nhật món · ${item.name}` : 'Thêm món mới'}</span>}
            open={open}
            width="min(860px, 100vw)"
            className="fnb-menu-item-editor"
            onClose={busy ? undefined : onClose}
            closable={!busy}
            maskClosable={!busy}
            keyboard={!busy}
            destroyOnHidden
            footer={<div className="fnb-menu-editor-footer"><Button disabled={busy} onClick={onClose}>Hủy</Button><Button type="primary" loading={saving} disabled={uploadingImages} onClick={() => form.submit()}>Lưu món</Button></div>}
        >
            <Form disabled={saving} form={form} layout="vertical" onFinish={(values) => { if (!uploadingImages) onSubmit(values); }}
                onFinishFailed={({ errorFields }) => {
                    if (errorFields.some(({ name }) => ['modifier_group_ids', 'description', 'sku', 'tax_category', 'tax_rate_percent', 'tax_inclusive'].includes(name[0]))) setAdditionalOpen(['additional']);
                    if (errorFields[0]) window.requestAnimationFrame(() => form.scrollToField(errorFields[0].name, { block: 'center' }));
                }}
                initialValues={{ status: 'active', tax_inclusive: true, tax_rate_percent: 0 }}>
                <section className="fnb-menu-editor-section">
                    <div className="fnb-menu-editor-section-heading"><span><ShopOutlined /> Thông tin món</span></div>
                    <Row gutter={[16, 0]}>
                        <Col xs={24} sm={8}><Form.Item name="code" label="Mã món" rules={[{ required: true, message: 'Nhập mã món.' }]}><Input maxLength={50} placeholder="Ví dụ: CAFE01" /></Form.Item></Col>
                        <Col xs={24} sm={16}><Form.Item name="name" label="Tên món" rules={[{ required: true, message: 'Nhập tên món.' }]}><Input placeholder="Ví dụ: Cà phê sữa" /></Form.Item></Col>
                        <Col xs={24} sm={12}><Form.Item name="category_ids" label="Nhóm món" rules={[{ required: true, type: 'array', min: 1, message: 'Chọn nhóm để đưa món vào thực đơn phát hành.' }]}>
                            <Select mode="multiple" allowClear showSearch optionFilterProp="label" options={asArray(catalog.categories).map((category) => ({ value: category.id, label: category.name }))} placeholder="Chọn nhóm món" />
                        </Form.Item></Col>
                        <Col xs={12} sm={6}><Form.Item name="item_type" label="Loại món" rules={[{ required: true }]}><Select options={[{ value: 'prepared', label: 'Pha chế' }, { value: 'packaged', label: 'Đóng gói sẵn' }, { value: 'service', label: 'Dịch vụ' }]} /></Form.Item></Col>
                        <Col xs={12} sm={6}><Form.Item name="status" label="Trạng thái"><Select options={[{ value: 'active', label: 'Đang bán' }, { value: 'inactive', label: 'Tạm ngưng' }]} /></Form.Item></Col>
                    </Row>
                </section>

                <section className="fnb-menu-editor-section">
                    <Form.List name="variants" rules={[{ validator: async (_, variants) => {
                        if (!variants?.some((variant) => variant?.status !== 'inactive')) throw new Error('Cần ít nhất một size đang bán.');
                    } }]}>
                        {(fields, { add, remove }, { errors }) => <>
                            <div className="fnb-menu-editor-section-heading"><span><CoffeeOutlined /> Size & giá bán <span className="fnb-menu-editor-count">{fields.length}</span></span>
                                <Button icon={<PlusOutlined />} onClick={() => add({ code: '', name: '', base_price_minor: 0, status: 'active', station_id: defaultStationId })}>Thêm size</Button>
                            </div>
                            <Text type="secondary" className="fnb-menu-editor-hint">Mỗi size có giá bán và trạm xử lý riêng.</Text>
                            <div className="fnb-menu-editor-variants">
                                {fields.map((field, index) => <div key={field.key} className="fnb-menu-editor-variant">
                                    <div className="fnb-menu-editor-variant-heading"><Text strong>Size {index + 1}</Text><Button danger type="text" size="small" disabled={saving} onClick={() => remove(field.name)}>Bỏ size</Button></div>
                                    <Row gutter={[12, 0]}>
                                        <Col xs={24} sm={6}><Form.Item {...field} name={[field.name, 'code']} label="Mã size" rules={[{ required: true, message: 'Nhập mã size.' }, { pattern: /^[A-Za-z0-9_-]+$/, message: 'Chỉ dùng chữ, số, gạch ngang hoặc gạch dưới.' }]}><Input readOnly maxLength={60} placeholder="Tự điền khi chọn size" /></Form.Item></Col>
                                        <Col xs={14} sm={10}><Form.Item {...field} name={[field.name, 'name']} label={sizeLabel(field.name)} rules={[{ required: true, message: 'Chọn size.' }]}><Select showSearch optionFilterProp="label" placeholder="Chọn size" options={sizeOptions.map((option) => ({ ...option, disabled: variants.some((row, index) => index !== field.name && (row?.name === option.value || row?.code === option.code)) }))} onChange={(value) => form.setFieldValue(['variants', field.name, 'code'], sizeOptions.find((option) => option.value === value)?.code)} /></Form.Item></Col>
                                        <Col xs={10} sm={8}><Form.Item {...field} name={[field.name, 'base_price_minor']} label="Giá bán" rules={[{ required: true, message: 'Nhập giá bán.' }]}><InputNumber {...moneyProps} min={0} precision={0} controls={false} addonAfter="₫" inputMode="numeric" style={{ width: '100%' }} /></Form.Item></Col>
                                        <Col xs={24} sm={12}><Form.Item {...field} name={[field.name, 'station_id']} label="Trạm pha chế" rules={[{ validator: (_, value) => {
                                            const status = form.getFieldValue(['variants', field.name, 'status']);
                                            return itemType === 'prepared' && status !== 'inactive' && !value ? Promise.reject(new Error('Chọn trạm pha chế.')) : Promise.resolve();
                                        } }]}><Select allowClear showSearch optionFilterProp="label" options={stationOptions} placeholder="Chọn trạm" /></Form.Item></Col>
                                        <Col xs={14} sm={7}><Form.Item {...field} name={[field.name, 'status']} label="Trạng thái"><Select options={[{ value: 'active', label: 'Đang bán' }, { value: 'inactive', label: 'Tạm ngưng' }]} /></Form.Item></Col>
                                        <Col xs={10} sm={5}><Form.Item {...field} name={[field.name, 'sort_order']} label="Thứ tự"><InputNumber min={0} precision={0} style={{ width: '100%' }} /></Form.Item></Col>
                                        <Form.Item {...field} name={[field.name, 'id']} hidden><Input /></Form.Item>
                                    </Row>
                                </div>)}
                            </div>
                            <Form.ErrorList errors={errors} />
                        </>}
                    </Form.List>
                    <Form.Item noStyle shouldUpdate={(previous, current) => previous.variants !== current.variants}>
                        {({ getFieldValue }) => {
                            const variants = asArray(getFieldValue('variants'));
                            return <Form.Item name="default_variant_index" label={<Space size={4}>Size mặc định{manageable ? <Button type="text" size="small" icon={<PlusOutlined />} aria-label="Quản lý danh mục size" disabled={saving} onClick={() => setSizeManager({})} /> : null}</Space>} extra="Chọn trong các size của món." className="fnb-menu-editor-default" rules={[{ required: true, message: 'Chọn size mặc định.' }]}>
                                <Select options={variants.map((variant, index) => ({ value: index, label: variant?.name || variant?.code || `Size ${index + 1}`, disabled: variant?.status === 'inactive' }))} />
                            </Form.Item>;
                        }}
                    </Form.Item>
                </section>

                <section className="fnb-menu-editor-section">
                    <div className="fnb-menu-editor-section-heading"><span><PictureOutlined /> Hình ảnh món</span></div>
                    <Form.Item name="images" style={{ marginBottom: 0 }}><FnbItemImages disabled={saving} onUploadingChange={setUploadingImages} /></Form.Item>
                </section>

                <Collapse activeKey={additionalOpen} onChange={setAdditionalOpen} className="fnb-menu-editor-additional" items={[{
                    key: 'additional', forceRender: true,
                    label: <span><SettingOutlined /> Thiết lập thêm</span>,
                    extra: <Text type="secondary">Topping, mô tả, thuế</Text>,
                    children: <Row gutter={[16, 0]}>
                        <Col span={24}><Form.Item name="modifier_group_ids" label="Nhóm topping áp dụng"><Select mode="multiple" allowClear showSearch optionFilterProp="label" options={modifierOptions} placeholder="Chọn nhóm topping (nếu có)" /></Form.Item></Col>
                        <Col span={24}><Form.Item name="description" label="Mô tả"><Input.TextArea autoSize={{ minRows: 2, maxRows: 4 }} placeholder="Giới thiệu ngắn về món" /></Form.Item></Col>
                        <Col xs={24} sm={8}><Form.Item name="sku" label="SKU"><Input placeholder="Mã quản lý nội bộ" /></Form.Item></Col>
                        <Col xs={24} sm={10}><Form.Item name="tax_category" label="Nhóm thuế"><Select options={[{ value: 'standard', label: 'Thuế suất thông thường' }, { value: 'zero_rated', label: 'Thuế suất 0%' }, { value: 'not_subject', label: 'Không chịu thuế' }, { value: 'exempt', label: 'Miễn thuế' }]} /></Form.Item></Col>
                        <Col xs={12} sm={7}><Form.Item name="tax_rate_percent" label="Thuế suất"><InputNumber min={0} max={100} precision={2} addonAfter="%" style={{ width: '100%' }} /></Form.Item></Col>
                        <Col xs={12} sm={7}><Form.Item name="tax_inclusive" label="Giá đã gồm thuế" valuePropName="checked"><Switch /></Form.Item></Col>
                    </Row>,
                }]} />
            </Form>
        </Drawer></>
    );
}

function ModifierModal({ open, group, catalog, form, saving, onClose, onSubmit, onCatalogChanged }) {
    const { can } = useFnbWorkspace();
    const manageable = can('fnb.menu.manage') && can('fnb.menu.master.manage');
    const [nameManager, setNameManager] = useState(false);
    const rows = Form.useWatch('options', form) ?? [];
    const names = choiceOptions(catalog.topping_definitions, rows);

    return (
        <><FnbMenuChoiceManagerModal open={nameManager} kind="topping" definitions={asArray(catalog.topping_definitions)} onClose={() => setNameManager(false)} onSaved={onCatalogChanged} onCreated={(record) => {
            if (!record) return;
            const current = asArray(form.getFieldValue('options'));
            const empty = current.findIndex((row) => !row?.name && !row?.code);
            if (empty >= 0) {
                form.setFieldValue(['options', empty, 'name'], record.name);
                form.setFieldValue(['options', empty, 'code'], record.code);
            } else form.setFieldValue('options', [...current, { name: record.name, code: record.code, status: 'active', base_price_delta_minor: 0 }]);
            setNameManager(false);
        }} />
        <Modal
            title={group ? `Cập nhật nhóm topping · ${group.name}` : 'Thêm nhóm topping'}
            open={open}
            width={760}
            centered
            onCancel={saving ? undefined : onClose}
            onOk={() => form.submit()}
            confirmLoading={saving}
            okText="Lưu nhóm"
            cancelText="Hủy"
            cancelButtonProps={{ disabled: saving }}
            closable={!saving}
            maskClosable={!saving}
            destroyOnHidden
            styles={{ body: { maxHeight: '65vh', overflowY: 'auto' } }}
        >
            <Alert type="info" showIcon message="Topping là lựa chọn thêm đi kèm món"
                description="Ví dụ: nhóm Topping trà sữa gồm trân châu, thạch và kem. Gắn nhóm vào món để nhân viên chọn khi gọi món; mỗi lựa chọn có giá thêm riêng."
                style={{ marginBottom: 16 }} />
            <Form disabled={saving} form={form} layout="vertical" onFinish={onSubmit} initialValues={{ min_select: 0, max_select: 1, free_quantity: 0, status: 'active' }}>
                <Row gutter={16}>
                    <Col xs={24} md={9}><Form.Item name="code" label="Mã nhóm" normalize={(value) => value.trim()} extra={group ? 'Để trống để giữ mã hiện tại.' : 'Tự tạo theo tên nhóm nếu để trống.'} rules={[{ max: 40, message: 'Mã tối đa 40 ký tự.' }, { pattern: /^[A-Za-z0-9_-]+$/, message: 'Chỉ dùng chữ, số, gạch ngang hoặc gạch dưới.' }]}><Input maxLength={40} placeholder={group ? 'Giữ mã hiện tại' : 'Để trống để tự tạo'} /></Form.Item></Col>
                    <Col xs={24} md={15}><Form.Item name="name" label="Tên nhóm" rules={[{ required: true }]}><Input /></Form.Item></Col>
                    <Col xs={24} sm={8}><Form.Item name="min_select" label="Số loại chọn tối thiểu" extra="0 = không bắt buộc chọn." rules={[{ required: true }]}><InputNumber min={0} precision={0} addonAfter="loại" style={{ width: '100%' }} /></Form.Item></Col>
                    <Col xs={24} sm={8}><Form.Item name="max_select" label="Số loại chọn tối đa" extra="Giới hạn cho mỗi món, không phải số tiền." rules={[{ required: true }]}><InputNumber min={1} precision={0} addonAfter="loại" style={{ width: '100%' }} /></Form.Item></Col>
                    <Col xs={24} sm={8}><Form.Item name="free_quantity" label="Số loại miễn phí" extra="Chưa áp dụng khi tính tiền. Topping miễn phí cần đặt Giá thêm = 0 ₫."><InputNumber disabled min={0} precision={0} addonAfter="loại" style={{ width: '100%' }} /></Form.Item></Col>
                </Row>
                <Form.Item name="status" label="Trạng thái"><Radio.Group options={[{ value: 'active', label: 'Đang dùng' }, { value: 'inactive', label: 'Tạm ngưng' }]} /></Form.Item>
                <Form.List name="options">
                    {(fields, { add, remove }) => (
                        <Space direction="vertical" size={12} style={{ width: '100%' }}>
                            <Space style={{ width: '100%', justifyContent: 'space-between' }}><Text strong>Các lựa chọn</Text><Button icon={<PlusOutlined />} onClick={() => add({ status: 'active', base_price_delta_minor: 0 })}>Thêm topping</Button></Space>
                            <Table
                                size="small"
                                rowKey="key"
                                pagination={false}
                                dataSource={fields}
                                scroll={{ x: 680 }}
                                locale={{ emptyText: 'Chưa có lựa chọn topping' }}
                                columns={[
                                    { title: 'Mã', width: 140, render: (_, field) => <>
                                        <Form.Item {...field} name={[field.name, 'code']} className="fnb-topping-cell-field" style={{ marginBottom: 0 }} rules={[{ required: true, message: 'Nhập mã topping.' }]}><Input aria-label={`Mã topping ${field.name + 1}`} /></Form.Item>
                                        <Form.Item {...field} name={[field.name, 'id']} hidden><Input /></Form.Item>
                                        <Form.Item {...field} name={[field.name, 'version']} hidden><Input /></Form.Item>
                                    </> },
                                    { title: <Space size={4}>Tên{manageable ? <Button type="text" size="small" icon={<PlusOutlined />} aria-label="Thêm nhanh tên topping" disabled={saving} onClick={() => setNameManager(true)} /> : null}</Space>, width: 220, render: (_, field) => <Form.Item {...field} name={[field.name, 'name']} className="fnb-topping-cell-field" style={{ marginBottom: 0 }} rules={[{ required: true, message: 'Nhập tên topping.' }]}><Select aria-label={`Tên topping ${field.name + 1}`} showSearch optionFilterProp="label" placeholder="Chọn topping" options={names.map((option) => ({ ...option, disabled: rows.some((row, index) => index !== field.name && (row?.name === option.value || row?.code === option.code)) }))} onChange={(value) => form.setFieldValue(['options', field.name, 'code'], names.find((option) => option.value === value)?.code)} /></Form.Item> },
                                    { title: 'Giá thêm', width: 150, render: (_, field) => <Form.Item {...field} name={[field.name, 'base_price_delta_minor']} style={{ marginBottom: 0 }}><InputNumber aria-label={`Giá thêm topping ${field.name + 1}`} min={0} precision={0} controls={false} addonAfter="₫" style={{ width: '100%' }} /></Form.Item> },
                                    { title: 'Trạng thái', width: 110, render: (_, field) => <Form.Item {...field} name={[field.name, 'status']} style={{ marginBottom: 0 }}><Select aria-label={`Trạng thái topping ${field.name + 1}`} options={[{ value: 'active', label: 'Bán' }, { value: 'inactive', label: 'Ngưng' }]} /></Form.Item> },
                                    { key: 'remove', width: 44, render: (_, field) => <Button danger type="text" aria-label={`Bỏ topping ${field.name + 1}`} onClick={() => remove(field.name)}>×</Button> },
                                ]}
                            />
                        </Space>
                    )}
                </Form.List>
            </Form>
        </Modal></>
    );
}

function RecipeDrawer({ open, recipe, ingredients, units, variants, form, saving, onClose, onSubmit }) {
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
                                        <Col xs={7} md={4}><Form.Item {...field} name={[field.name, 'unit']} label="Đơn vị" rules={[{ required: true }]}><Select options={units.map((unit) => ({ value: unit.code, label: `${unit.name} (${unit.code})`, disabled: unit.status !== 'active' }))} /></Form.Item></Col>
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

function CategoryModal({ open, category, menus, form, saving, onClose, onSubmit }) {
    return <Modal title={category ? `Cập nhật nhóm món · ${category.name}` : 'Thêm nhóm món'} open={open} width={560} centered onCancel={saving ? undefined : onClose} onOk={() => form.submit()} confirmLoading={saving} okText="Lưu nhóm" cancelText="Hủy" cancelButtonProps={{ disabled: saving }} closable={!saving} maskClosable={!saving} destroyOnHidden>
        <Form disabled={saving} form={form} layout="vertical" onFinish={onSubmit}>
            <Form.Item name="menu_id" label="Thực đơn" rules={[{ required: true }]}><Select options={menus.map((menu) => ({ value: menu.id, label: menu.name }))} /></Form.Item>
            <Row gutter={14}>
                <Col xs={24} md={9}>
                    <Form.Item name="code" label="Mã" normalize={(value) => value.trim()} extra={category ? 'Để trống để giữ mã hiện tại.' : 'Tự tạo theo tên nhóm nếu để trống.'} rules={[{ max: 40, message: 'Mã tối đa 40 ký tự.' }, { pattern: /^[A-Za-z0-9_-]+$/, message: 'Chỉ dùng chữ, số, gạch ngang hoặc gạch dưới.' }]}>
                        <Input maxLength={40} placeholder={category ? 'Giữ mã hiện tại' : 'Để trống để tự tạo'} />
                    </Form.Item>
                </Col>
                <Col xs={24} md={15}><Form.Item name="name" label="Tên nhóm" rules={[{ required: true, message: 'Nhập tên nhóm.' }]}><Input /></Form.Item></Col>
            </Row>
            <Form.Item name="status" label="Trạng thái"><Radio.Group options={[{ value: 'active', label: 'Đang dùng' }, { value: 'inactive', label: 'Tạm ngưng' }]} /></Form.Item>
        </Form>
    </Modal>;
}

function IngredientModal({ open, ingredient, units, unitsLoading, onUnitsSaved, form, saving, onClose, onSubmit }) {
    const [unitsOpen, setUnitsOpen] = useState(false);
    return <><Modal title={ingredient ? `Cập nhật nguyên liệu · ${ingredient.name}` : 'Thêm nguyên liệu'} open={open && !unitsOpen} width={560} centered onCancel={saving ? undefined : onClose} onOk={() => form.submit()} confirmLoading={saving} okText="Lưu nguyên liệu" cancelText="Hủy" cancelButtonProps={{ disabled: saving }} closable={!saving} maskClosable={!saving} destroyOnHidden>
        <Form disabled={saving} form={form} layout="vertical" onFinish={onSubmit}>
            <Row gutter={14}><Col xs={24} md={9}><Form.Item name="code" label="Mã" normalize={(value) => value.trim()} extra={ingredient ? 'Để trống để giữ mã hiện tại.' : 'Tự tạo theo tên nếu để trống.'} rules={[{ max: 40, message: 'Mã tối đa 40 ký tự.' }, { pattern: /^[A-Za-z0-9_-]+$/, message: 'Chỉ dùng chữ, số, gạch ngang hoặc gạch dưới.' }]}><Input maxLength={40} placeholder={ingredient ? 'Giữ mã hiện tại' : 'Để trống để tự tạo'} /></Form.Item></Col><Col xs={24} md={15}><Form.Item name="name" label="Tên nguyên liệu" rules={[{ required: true }]}><Input /></Form.Item></Col></Row>
            <Form.Item name="base_unit" label={<Space size={4}>Đơn vị gốc<Button type="text" size="small" icon={<SettingOutlined />} aria-label="Quản lý đơn vị" disabled={saving || unitsLoading} onClick={() => setUnitsOpen(true)} /></Space>} rules={[{ required: true }]}><Select showSearch optionFilterProp="label" loading={unitsLoading} disabled={Boolean(ingredient)} options={units.map((unit) => ({ value: unit.code, label: `${unit.name} (${unit.code})`, disabled: unit.status !== 'active' }))} /></Form.Item>
            <Form.Item name="status" label="Trạng thái"><Radio.Group options={[{ value: 'active', label: 'Đang dùng' }, { value: 'inactive', label: 'Tạm ngưng' }]} /></Form.Item>
            {ingredient ? <Alert type="info" showIcon message="Đơn vị gốc không thể đổi sau khi tạo" description="Hãy tạo nguyên liệu mới nếu cần một loại đơn vị khác." /> : null}
        </Form>
    </Modal><FnbUnitManagerModal open={open && unitsOpen} units={units} loading={unitsLoading} onClose={() => setUnitsOpen(false)} onSaved={onUnitsSaved} /></>;
}

export default function FnbMenuPage() {
    const { modal } = App.useApp();
    const { api, outletId, selectedOutlet, can, runCommand, navigateSection } = useFnbWorkspace();
    const [activeTab, setActiveTab] = useState('items');
    const [managerType, setManagerType] = useState(null);
    const [drawer, setDrawer] = useState(null);
    const [saving, setSaving] = useState(false);
    const [archivingItems, setArchivingItems] = useState(false);
    const [selectedItemKeys, setSelectedItemKeys] = useState([]);
    const [itemForm] = Form.useForm();
    const [modifierForm] = Form.useForm();
    const [recipeForm] = Form.useForm();
    const [categoryForm] = Form.useForm();
    const [ingredientForm] = Form.useForm();
    const loadCatalog = useCallback(() => api.catalog({ include_inactive: can('fnb.menu.manage'), draft: can('fnb.menu.master.manage') ? 1 : undefined }), [api, can]);
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
            images: { urls: asArray(item.gallery_images).length ? item.gallery_images : item.image_url ? [item.image_url] : [], cover: item.image_url },
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
            variants: [{ code: asArray(catalog.size_definitions).find((row) => row.code === 'DEFAULT')?.code ?? asArray(catalog.size_definitions)[0]?.code ?? '', name: asArray(catalog.size_definitions).find((row) => row.code === 'DEFAULT')?.name ?? asArray(catalog.size_definitions)[0]?.name ?? '', base_price_minor: 0, is_default: true, sort_order: 0, status: 'active', station_id: asArray(catalog.stations).find((station) => station.status !== 'inactive')?.id }],
            category_ids: [],
            modifier_group_ids: [],
            images: { urls: [], cover: null },
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
            image_url: values.images?.cover || values.images?.urls?.[0] || null,
            gallery_images: asArray(values.images?.urls),
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

    const openCategory = (category = null, menuId = null) => {
        categoryForm.resetFields();
        categoryForm.setFieldsValue(category ?? { menu_id: menuId ?? asArray(catalog.menus)[0]?.id, status: 'active' });
        setDrawer({ type: 'category', record: category });
    };

    const reorderCategories = async (menuId, rows) => {
        const key = createIdempotencyKey('category-order');
        await runCommand({
            execute: (auth) => api.command('menu/categories/reorder', {
                menu_id: menuId,
                items: rows.map((row) => ({ id: row.id, expected_version: row.version })),
            }, { idempotencyKey: key, ...auth }),
            successMessage: 'Đã lưu thứ tự nhóm món.',
            onSuccess: async (result) => {
                catalogResource.setData({ ...catalog, categories: [...asArray(catalog.categories).filter((row) => row.menu_id !== menuId), ...result.data.items] });
            },
            onConflict: catalogResource.reload,
        });
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
        ingredientForm.setFieldsValue(ingredient ?? { base_unit: asArray(recipeData.units).find((unit) => unit.code === 'g' && unit.status === 'active')?.code ?? asArray(recipeData.units).find((unit) => unit.status === 'active')?.code, status: 'active' });
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

    const items = useMemo(() => asArray(catalog.items), [catalog.items]);
    const canArchiveItems = can('fnb.menu.manage') && can('fnb.menu.master.manage');
    const selectedItems = items.filter((item) => selectedItemKeys.includes(item.public_id ?? item.id));
    useEffect(() => {
        const currentKeys = new Set(items.map((item) => item.public_id ?? item.id));
        setSelectedItemKeys((keys) => keys.filter((key) => currentKeys.has(key)));
    }, [items]);
    const confirmArchiveItems = () => {
        if (!canArchiveItems || archivingItems || !selectedItems.length) return;
        const payload = { items: selectedItems.map((item) => ({ id: item.id, expected_version: item.version })) };
        const idempotencyKey = createIdempotencyKey('archive-menu-items');
        modal.confirm({
            title: `Xóa ${selectedItems.length} món đã chọn?`,
            content: 'Các món sẽ được xóa khỏi bản soạn thực đơn. Bill, order và bản thực đơn đã phát hành vẫn được giữ nguyên. Phát hành lại thực đơn để áp dụng cho điểm bán.',
            okText: 'Xóa đã chọn',
            cancelText: 'Hủy',
            okButtonProps: { danger: true },
            onOk: async () => {
                setArchivingItems(true);
                try {
                    await runCommand({
                        execute: (auth) => api.command('menu/items/archive', payload, { idempotencyKey, ...auth }),
                        successMessage: `Đã xóa ${payload.items.length} món khỏi bản soạn.`,
                        onSuccess: async () => {
                            setSelectedItemKeys([]);
                            await catalogResource.reload({ silent: true });
                        },
                        onConflict: catalogResource.reload,
                    });
                } finally {
                    setArchivingItems(false);
                }
            },
        });
    };
    const menus = asArray(catalog.menus);
    const modifierGroups = asArray(catalog.modifier_groups);
    const priceBooks = asArray(catalog.price_books);

    const managementSections = [
        {
            key: 'categories',
            label: `Nhóm món (${asArray(catalog.categories).length})`,
            children: <FnbCategoryList menus={menus} categories={asArray(catalog.categories)} editable={can('fnb.menu.manage') && can('fnb.menu.master.manage')} loading={catalogResource.loading || catalogResource.refreshing} onAdd={(menuId) => openCategory(null, menuId)} onEdit={openCategory} onReorder={reorderCategories} />,
        },
        {
            key: 'modifiers',
            label: `Nhóm topping (${modifierGroups.length})`,
            children: <>
                <div className="fnb-table-toolbar"><Text type="secondary">Gắn nhóm topping vào món; giới hạn chọn tính theo số loại topping cho mỗi món, không phải số tiền.</Text>{can('fnb.menu.manage') && can('fnb.menu.master.manage') ? <Button type="primary" icon={<PlusOutlined />} onClick={() => openModifier()}>Thêm nhóm</Button> : null}</div>
                {modifierGroups.length ? <Table rowKey="id" dataSource={modifierGroups} columns={[
                    { title: 'Nhóm', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.name}</Text><Text type="secondary">{record.code}</Text></Space> },
                    { title: 'Quy tắc', render: (_, record) => `Chọn ${record.min_select ?? 0}–${record.max_select ?? 1} loại / món` },
                    { title: 'Lựa chọn', render: (_, record) => asArray(record.options).map((option) => <Tag key={option.id}>{option.name} · {formatMinorMoney(option.price_delta_minor ?? option.base_price_delta_minor, currency)}</Tag>) },
                    { title: 'Trạng thái', dataIndex: 'status', render: (value) => <FnbStatusTag status={value} /> },
                    { title: '', render: (_, record) => can('fnb.menu.manage') && can('fnb.menu.master.manage') ? <Button size="small" onClick={() => openModifier(record)}>Sửa</Button> : null },
                ]} /> : <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Chưa có nhóm topping" />}
            </>,
        },
        {
            key: 'ingredients',
            label: `Nguyên liệu (${asArray(recipeData.ingredients).length})`,
            children: can('fnb.recipe.view') ? <>
                <div className="fnb-table-toolbar"><Text type="secondary">Đơn vị gốc không đổi sau khi nguyên liệu đã được tạo.</Text>{can('fnb.recipe.manage') && can('fnb.recipe.master.manage') ? <Button type="primary" icon={<PlusOutlined />} onClick={() => openIngredient()}>Thêm nguyên liệu</Button> : null}</div>
                <Table rowKey="id" loading={recipeResource.loading} dataSource={asArray(recipeData.ingredients)} columns={[
                    { title: 'Nguyên liệu', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.name}</Text><Text type="secondary">{record.code}</Text></Space> },
                    { title: 'Đơn vị gốc', dataIndex: 'base_unit', render: (value) => <Tag>{asArray(recipeData.units).find((unit) => unit.code === value)?.name ?? value} ({value})</Tag> },
                    { title: 'Trạng thái', dataIndex: 'status', render: (value) => <FnbStatusTag status={value} /> },
                    { title: '', render: (_, record) => can('fnb.recipe.manage') && can('fnb.recipe.master.manage') ? <Button size="small" onClick={() => openIngredient(record)}>Sửa</Button> : null },
                ]} />
            </> : <Alert type="info" showIcon message="Bạn chưa có quyền xem nguyên liệu." />,
        },
    ];
    const manager = managementSections.find((section) => section.key === managerType);
    const editingManagedResource = ['category', 'modifier', 'ingredient'].includes(drawer?.type);
    const managerResource = managerType === 'ingredients' ? recipeResource : catalogResource;

    return (
        <div className="fnb-page-stack">
            <FnbPageHeader
                eyebrow="Thực đơn vận hành"
                title="Món, size, topping và công thức"
                description="Giữ một nguồn giá và định lượng chuẩn; giao dịch cũ luôn dùng snapshot đã khóa."
                actions={<Button icon={<ReloadOutlined />} loading={catalogResource.refreshing} onClick={() => Promise.all([catalogResource.reload({ silent: true }), can('fnb.recipe.view') ? recipeResource.reload({ silent: true }) : null])}>Làm mới</Button>}
            />

            {can('fnb.menu.master.manage') ? <Alert type="info" showIcon message="Bạn đang chỉnh bản soạn của thực đơn" description="Phát hành sau khi kiểm tra để áp dụng thay đổi cho các điểm bán đã chọn. Order đã chốt vẫn giữ nguyên nội dung và giá cũ." action={<Button onClick={() => navigateSection('chain')}>Phát hành cho điểm bán</Button>} /> : null}

            <Card className="fnb-panel">
                <Space wrap style={{ marginBottom: 20 }} aria-label="Quản lý danh mục thực đơn">
                    {managementSections.map((section) => <Button key={section.key} onClick={() => setManagerType(section.key)}>{section.label}</Button>)}
                </Space>
                <Tabs
                    activeKey={activeTab}
                    onChange={setActiveTab}
                    items={[
                        {
                            key: 'items',
                            label: `Món & size (${items.length})`,
                            children: <>
                                <div className="fnb-table-toolbar"><Text type="secondary">Giá hiển thị theo VND, tổng bill do máy chủ tính.</Text>{can('fnb.menu.manage') ? <Button type="primary" icon={<PlusOutlined />} disabled={archivingItems} onClick={() => openItem()}>Thêm món</Button> : null}</div>
                                {canArchiveItems && selectedItems.length > 0 ? <div className="fnb-table-toolbar">
                                    <Space wrap>
                                        <Text>Đã chọn {selectedItems.length} món</Text>
                                        <Button danger icon={<DeleteOutlined />} loading={archivingItems} disabled={saving || catalogResource.refreshing} onClick={confirmArchiveItems}>Xóa đã chọn</Button>
                                        <Button type="text" disabled={archivingItems} onClick={() => setSelectedItemKeys([])}>Bỏ chọn</Button>
                                    </Space>
                                </div> : null}
                                <Table
                                    rowKey={(record) => record.public_id ?? record.id}
                                    loading={catalogResource.loading}
                                    dataSource={items}
                                    rowSelection={canArchiveItems ? {
                                        selectedRowKeys: selectedItems.map((item) => item.public_id ?? item.id),
                                        onChange: setSelectedItemKeys,
                                        selections: [Table.SELECTION_ALL, Table.SELECTION_INVERT, Table.SELECTION_NONE],
                                        getCheckboxProps: () => ({ disabled: saving || archivingItems || catalogResource.refreshing }),
                                    } : undefined}
                                    scroll={{ x: 940 }}
                                    columns={[
                                        { title: 'Ảnh', key: 'cover', width: 84, align: 'center', render: (_, record) => <MenuItemCover item={record} /> },
                                        { title: 'Món', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.name}</Text><Text type="secondary">{record.code}{record.sku ? ` · ${record.sku}` : ''}</Text></Space> },
                                        { title: 'Size', render: (_, record) => asArray(record.variants).map((variant) => <Tag color={variant.status === 'inactive' ? 'default' : variant.is_default ? 'green' : undefined} key={variant.id}>{variant.name}{variant.is_default ? ' · mặc định' : ''}</Tag>) },
                                        { title: 'Giá từ', render: (_, record) => formatMinorMoney(itemPrice(record), currency) },
                                        { title: 'Trạm', render: (_, record) => {
                                            const stationNames = [...new Set(asArray(record.station_routes).map((route) => asArray(catalog.stations).find((station) => station.id === route.prep_station_id)?.name).filter(Boolean))];
                                            return stationNames.join(', ') || '—';
                                        } },
                                        { title: 'Trạng thái', render: (_, record) => <Space direction="vertical" size={4}><FnbStatusTag status={record.status} />{record.available === false ? <Tag color="red">Hết món</Tag> : null}</Space> },
                                        {
                                            title: 'Thao tác', fixed: 'right', width: 140,
                                            render: (_, record) => can('fnb.menu.manage') || can('fnb.menu.availability.update') ? <Dropdown trigger={['click']} menu={{
                                                items: [
                                                    ...(can('fnb.menu.manage') ? [{ key: 'edit', label: 'Sửa', icon: <EditOutlined /> }] : []),
                                                    ...(can('fnb.menu.availability.update') ? [{ key: 'availability', label: record.available === false ? 'Mở bán' : 'Hết món', danger: record.available !== false }] : []),
                                                ],
                                                onClick: ({ key }) => {
                                                    if (key === 'edit' && can('fnb.menu.manage')) openItem(record);
                                                    if (key === 'availability' && can('fnb.menu.availability.update')) modal.confirm({
                                                        title: record.available === false ? 'Mở bán lại món này?' : 'Báo hết món tại điểm bán?',
                                                        content: record.name,
                                                        okText: record.available === false ? 'Mở bán' : 'Hết món',
                                                        cancelText: 'Hủy',
                                                        okButtonProps: { danger: record.available !== false },
                                                        onOk: () => setAvailability(record, record.available === false),
                                                    });
                                                },
                                            }}><Button size="small" disabled={saving || archivingItems} aria-label={`Thao tác món ${record.name}`}>Thao tác <DownOutlined /></Button></Dropdown> : null,
                                        },
                                    ]}
                                />
                            </>,
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

            <Modal
                title={manager?.label}
                open={Boolean(manager) && !editingManagedResource}
                width={1000}
                centered
                onCancel={() => setManagerType(null)}
                footer={<Button onClick={() => setManagerType(null)}>Đóng</Button>}
                styles={{ body: { maxHeight: '65vh', overflowY: 'auto' } }}
            >
                {managerResource.error ? <Alert type="error" showIcon message={managerResource.error.message} action={<Button onClick={() => managerResource.reload()}>Thử lại</Button>} style={{ marginBottom: 16 }} /> : null}
                {manager?.children}
            </Modal>

            <MenuItemDrawer onCatalogChanged={() => catalogResource.reload({ silent: true })} open={drawer?.type === 'item'} item={drawer?.record} catalog={catalog} form={itemForm} saving={saving} onClose={() => setDrawer(null)} onSubmit={saveItem} />
            <ModifierModal catalog={catalog} onCatalogChanged={() => catalogResource.reload({ silent: true })} open={drawer?.type === 'modifier'} group={drawer?.record} form={modifierForm} saving={saving} onClose={() => setDrawer(null)} onSubmit={saveModifier} />
            <RecipeDrawer units={asArray(recipeData.units)} open={drawer?.type === 'recipe'} recipe={drawer?.record} ingredients={asArray(recipeData.ingredients)} variants={variantOptions} form={recipeForm} saving={saving} onClose={() => setDrawer(null)} onSubmit={saveRecipe} />
            <CategoryModal open={drawer?.type === 'category'} category={drawer?.record} menus={menus} form={categoryForm} saving={saving} onClose={() => setDrawer(null)} onSubmit={saveCategory} />
            <IngredientModal units={asArray(recipeData.units)} unitsLoading={recipeResource.loading || recipeResource.refreshing} onUnitsSaved={() => recipeResource.reload({ silent: true })} open={drawer?.type === 'ingredient'} ingredient={drawer?.record} form={ingredientForm} saving={saving} onClose={() => setDrawer(null)} onSubmit={saveIngredient} />
        </div>
    );
}
