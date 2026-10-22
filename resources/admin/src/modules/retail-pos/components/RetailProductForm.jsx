import AppstoreOutlined from '@ant-design/icons/AppstoreOutlined';
import DeleteOutlined from '@ant-design/icons/DeleteOutlined';
import InboxOutlined from '@ant-design/icons/InboxOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import SettingOutlined from '@ant-design/icons/SettingOutlined';
import Tooltip from 'antd/es/tooltip';
import RetailUnitManager from './RetailUnitManager';
import TagsOutlined from '@ant-design/icons/TagsOutlined';
import Button from 'antd/es/button';
import Col from 'antd/es/col';
import Collapse from 'antd/es/collapse';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Switch from 'antd/es/switch';
import Tag from 'antd/es/tag';
import Table from 'antd/es/table';
import { useCallback, useEffect, useState } from 'react';

const required = (message) => [{ required: true, message }];
const fullWidth = { width: '100%' };

export default function RetailProductForm({ form, onSubmit, disabled, api, branchId, onCommand }) {
    const [expanded, setExpanded] = useState([]);
    const [units, setUnits] = useState([]);
    const [unitManagerOpen, setUnitManagerOpen] = useState(false);
    const [unitError, setUnitError] = useState(null);
    const [unitsLoading, setUnitsLoading] = useState(false);
    const loadUnits = useCallback(async () => {
        setUnitsLoading(true); setUnitError(null);
        try {
            const result = await api.read('units', { branch_id: branchId });
            const items = result.data ?? [];
            setUnits(items);
            const current = form.getFieldValue('base_unit');
            if (current && !items.some((unit) => unit.code === current && unit.is_active)) form.setFieldValue('base_unit', undefined);
        } catch (failure) { setUnitError(failure.message); }
        finally { setUnitsLoading(false); }
    }, [api, branchId, form]);
    useEffect(() => { loadUnits(); }, [loadUnits]);
    const trackingMode = Form.useWatch('tracking_mode', form);
    const requiresExpiry = Form.useWatch('requires_expiry', form);
    const openingQuantity = Form.useWatch('opening_quantity', form);
    const packagings = Form.useWatch('packagings', form) ?? [];
    const positiveOpening = Number(openingQuantity ?? 0) > 0;

    const revealErrors = ({ errorFields }) => {
        const sections = errorFields.flatMap(({ name }) => name[0] === 'packagings' ? ['packagings']
            : ['opening_quantity', 'unit_cost', 'batch_code', 'expires_at'].includes(name[0]) ? ['opening'] : []);
        setExpanded((current) => [...new Set([...current, ...sections])]);
        if (errorFields[0]) window.requestAnimationFrame(() => form.scrollToField(errorFields[0].name, { block: 'center' }));
    };

    return <><Form form={form} layout="vertical" className="retail-product-form" disabled={disabled} onFinish={onSubmit} onFinishFailed={revealErrors}>
        <section className="retail-product-section">
            <div className="retail-product-section-title"><span><TagsOutlined /></span><div><strong>Thông tin hàng hóa</strong><small>Thông tin nhận diện dùng chung trong chuỗi</small></div></div>
            <Row gutter={[16, 0]}>
                <Col xs={24} sm={8}><Form.Item name="sku" label="Mã SKU" tooltip="Bỏ trống để tự tạo mã bắt đầu bằng HH."><Input placeholder="Tự tạo mã HH nếu bỏ trống" maxLength={80} /></Form.Item></Col>
                <Col xs={24} sm={16}><Form.Item name="name" label="Tên hàng hóa" rules={required('Nhập tên hàng hóa.')}><Input placeholder="Nhập tên hàng hóa" /></Form.Item></Col>
                <Col xs={24} sm={12}><Form.Item name="barcode" label="Mã vạch" tooltip="Nhập hoặc quét mã vạch; các số 0 ở đầu được giữ nguyên."><Input placeholder="Nhập hoặc quét mã vạch" /></Form.Item></Col>
                <Col xs={24} sm={12}><Form.Item label="Đơn vị cơ sở" required validateStatus={unitError ? 'error' : undefined} help={unitError}><div className="retail-unit-picker"><Form.Item name="base_unit" noStyle rules={required('Chọn đơn vị cơ sở.')}><Select aria-label="Đơn vị cơ sở" loading={unitsLoading} disabled={disabled || unitsLoading} placeholder="Chọn đơn vị" showSearch optionFilterProp="label" options={units.filter((unit) => unit.is_active).map((unit) => ({ value: unit.code, label: unit.name }))} /></Form.Item><Tooltip title="Quản lý đơn vị"><Button icon={<SettingOutlined />} aria-label="Quản lý đơn vị cơ sở" onClick={() => setUnitManagerOpen(true)} disabled={disabled || unitsLoading || Boolean(unitError)} /></Tooltip></div></Form.Item></Col>
            </Row>
        </section>

        <section className="retail-product-section">
            <div className="retail-product-section-title"><span><InboxOutlined /></span><div><strong>Giá bán & quản lý kho</strong><small>Giá bán áp dụng tại chi nhánh đang chọn</small></div></div>
            <Row gutter={[16, 0]}>
                <Col span={24}><Form.Item name="price_minor" label="Giá bán" rules={required('Nhập giá bán.')}><InputNumber stringMode min="0" precision={0} suffix="₫" style={fullWidth} formatter={(value) => value == null || value === '' ? '' : String(value).replace(/\B(?=(\d{3})+(?!\d))/g, '.')} parser={(value) => (value ?? '').replace(/\./g, '')} /></Form.Item></Col>
                <Col xs={24} sm={12}><Form.Item name="tracking_mode" label="Theo dõi lô" rules={required('Chọn cách theo dõi lô.')}><Select options={[{ value: 'none', label: 'Không theo lô' }, { value: 'batch', label: 'Theo lô hàng' }]} /></Form.Item></Col>
                <Col xs={24} sm={12}><Form.Item label="Hạn sử dụng" className="retail-product-expiry"><div className="retail-product-switch-row"><Form.Item name="requires_expiry" valuePropName="checked" noStyle><Switch aria-label="Bắt buộc hạn sử dụng" /></Form.Item><span className="retail-product-switch-label">Bắt buộc khi nhập kho</span></div></Form.Item></Col>
            </Row>
        </section>

        <Collapse className="retail-product-options" activeKey={expanded} onChange={setExpanded} bordered={false} items={[
            {
                key: 'opening', forceRender: true,
                label: <span className="retail-product-option-label"><InboxOutlined /><strong>Tồn kho ban đầu</strong><Tag>{positiveOpening ? `${openingQuantity} đơn vị` : 'Tùy chọn'}</Tag></span>,
                children: <>
                    <p className="retail-product-help">Có thể tạo hàng hóa trước, sau đó nhập hàng vào kho khi cần.</p>
                    <Row gutter={[16, 0]}>
                        <Col xs={24} sm={12}><Form.Item name="opening_quantity" label="Số lượng nhập ban đầu"><InputNumber stringMode min="0" precision={0} style={fullWidth} /></Form.Item></Col>
                        <Col xs={24} sm={12}><Form.Item name="unit_cost" label="Giá vốn / đơn vị"><InputNumber stringMode min="0" precision={0} suffix="₫" style={fullWidth} /></Form.Item></Col>
                        {trackingMode === 'batch' || requiresExpiry ? <>
                            <Col xs={24} sm={12}><Form.Item name="batch_code" label="Mã lô nhập ban đầu"><Input placeholder="VD: LOT001" /></Form.Item></Col>
                            <Col xs={24} sm={12}><Form.Item name="expires_at" label="Hạn dùng lô nhập ban đầu"><Input type="date" /></Form.Item></Col>
                        </> : null}
                    </Row>
                </>,
            },
            {
                key: 'packagings', forceRender: true,
                label: <span className="retail-product-option-label"><AppstoreOutlined /><strong>Quy cách đóng gói</strong><Tag>{packagings.length ? `${packagings.length} quy cách` : 'Tùy chọn'}</Tag></span>,
                children: <>
                    <p className="retail-product-help">Thêm lốc, hộp hoặc thùng và số đơn vị cơ sở tương ứng.</p>
                    <Form.List name="packagings">{(fields, { add, remove }) => <>
                        <Table className="retail-packaging-table" size="small" bordered rowKey="key" dataSource={fields} pagination={false} scroll={{ x: 790 }} locale={{ emptyText: 'Chưa có quy cách đóng gói.' }} columns={[
                            { title: '#', width: 40, align: 'center', render: (_, record, index) => index + 1 },
                            { title: 'Mã quy cách', width: 145, render: (_, { name }) => <Form.Item name={[name, 'code']} rules={required('Nhập mã quy cách.')}><Input aria-label={`Mã quy cách dòng ${name + 1}`} placeholder="VD: CARTON" /></Form.Item> },
                            { title: 'Tên quy cách', width: 220, render: (_, { name }) => <Form.Item name={[name, 'name']} rules={required('Nhập tên quy cách.')}><Input aria-label={`Tên quy cách dòng ${name + 1}`} placeholder="VD: Thùng 24 chai" /></Form.Item> },
                            { title: 'Số đơn vị cơ sở', width: 160, render: (_, { name }) => <Form.Item name={[name, 'quantity_multiplier']} rules={required('Nhập số đơn vị cơ sở.')}><InputNumber aria-label={`Số đơn vị cơ sở dòng ${name + 1}`} stringMode min="0.000001" precision={6} controls={false} style={fullWidth} formatter={(value) => String(value ?? '').replace(/(\.\d*?[1-9])0+$|\.0+$/, '$1')} /></Form.Item> },
                            { title: 'Mã vạch quy cách', width: 185, render: (_, { name }) => <Form.Item name={[name, 'barcode']}><Input aria-label={`Mã vạch quy cách dòng ${name + 1}`} placeholder="Không bắt buộc" /></Form.Item> },
                            { title: '', width: 40, align: 'center', render: (_, { name }) => <Button danger type="text" size="small" icon={<DeleteOutlined />} aria-label={`Xóa quy cách ${name + 1}`} onClick={() => remove(name)} /> },
                        ]} />
                        <Button block type="dashed" icon={<PlusOutlined />} disabled={fields.length >= 30} style={{ marginTop: 12 }} onClick={() => add({ quantity_multiplier: '1' })}>Thêm quy cách đóng gói</Button>
                    </>}</Form.List>
                </>,
            },
        ]} />
    </Form><RetailUnitManager open={unitManagerOpen} onClose={() => setUnitManagerOpen(false)} units={units} api={api} branchId={branchId} disabled={disabled} onCommand={onCommand} onChanged={async (created) => {
        await loadUnits();
        if (created?.is_active) form.setFieldsValue({ base_unit: created.code });
    }} /></>;
}
