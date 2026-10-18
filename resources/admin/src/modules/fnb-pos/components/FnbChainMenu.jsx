import { useCallback, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tabs from 'antd/es/tabs';
import { createIdempotencyKey } from '../api/fnbApi';
import useFnbResource from '../hooks/useFnbResource';
import { useFnbWorkspace } from '../state/FnbWorkspaceContext';
import { asArray, formatDateTime, formatMinorMoney } from '../utils/fnbFormat';

export default function FnbChainMenu({ outlets }) {
    const { baseApi: api, runCommand, can } = useFnbWorkspace();
    const load = useCallback(() => api.read('chain/menu'), [api]);
    const resource = useFnbResource({ loader: load, deps: [api] });
    const data = resource.data ?? {};
    const [editor, setEditor] = useState(null);
    const [saving, setSaving] = useState(false);
    const [form] = Form.useForm();
    const open = (kind, record = null) => { form.resetFields(); form.setFieldsValue({ outlet_ids: [], prices: [{}] }); setEditor({ kind, record }); };
    const save = async (values) => {
        const idempotencyKey = createIdempotencyKey(`hq-${editor.kind}`);
        let path;
        let payload;
        if (editor.kind === 'menu') {
            path = 'chain/menus';
            payload = { code: values.code, name: values.name };
        } else if (editor.kind === 'publication') {
            const menu = asArray(data.menus).find((item) => String(item.id) === String(values.menu_id));
            path = 'chain/menu/publications';
            payload = { menu_id: values.menu_id, outlet_ids: values.outlet_ids, expected_version: menu?.version };
        } else if (editor.kind === 'rollback') {
            path = `chain/menu/publications/${editor.record.id}/rollback`;
            payload = { outlet_ids: values.outlet_ids, reason: values.reason };
        } else {
            path = 'chain/price-books';
            payload = { ...values, currency: 'VND', valid_from: values.valid_from ? new Date(values.valid_from).toISOString() : null, valid_to: values.valid_to ? new Date(values.valid_to).toISOString() : null };
        }
        setSaving(true);
        try {
            await runCommand({ execute: (auth) => api.command(path, payload, { idempotencyKey, ...auth }), authApi: api, title: editor.kind === 'pricebook' ? 'Phát hành bảng giá' : 'Phát hành thực đơn', reason: values.reason,
                successMessage: editor.kind === 'menu' ? 'Đã tạo thực đơn. Thêm nhóm món và món ở màn Thực đơn trước khi phát hành.' : 'Đã ghi nhận phát hành cho các điểm bán đã chọn.',
                onSuccess: async () => { setEditor(null); await resource.reload(); }, onConflict: resource.reload });
        } finally { setSaving(false); }
    };
    const outletOptions = outlets.filter((item) => item.status === 'active').map((item) => ({ value: item.id, label: `${item.code} · ${item.name}` }));
    return <>
        {resource.error ? <Alert type="warning" showIcon message={resource.error.message} /> : null}
        <Alert type="info" showIcon message="Phát hành theo nhóm điểm bán" description="Mỗi bản thực đơn và bảng giá giữ lịch sử riêng. Việc áp dụng lại bản cũ không thay đổi món và giá đã chốt trong order trước đó." style={{ marginBottom: 16 }} />
        <Tabs items={[
            { key: 'publications', label: 'Bản phát hành thực đơn', children: <>
                {can('fnb.menu.master.manage') && can('fnb.menu.publish') ? <Space wrap style={{ marginBottom: 12 }}><Button type="primary" onClick={() => open('publication')}>Phát hành thực đơn</Button><Button onClick={() => open('menu')}>Tạo thực đơn</Button></Space> : null}
                <Table rowKey="id" loading={resource.loading} dataSource={asArray(data.publications)} scroll={{ x: 750 }} columns={[
                    { title: 'Thực đơn', render: (_, item) => asArray(data.menus).find((menu) => String(menu.id) === String(item.menu_id))?.name ?? `#${item.menu_id}` },
                    { title: 'Phiên bản', dataIndex: 'revision' }, { title: 'Phát hành lúc', dataIndex: 'published_at', render: formatDateTime },
                    { title: '', render: (_, item) => can('fnb.menu.master.manage') && can('fnb.menu.publish') ? <Button onClick={() => open('rollback', item)}>Áp dụng bản này</Button> : null },
                ]} />
            </> },
            { key: 'prices', label: 'Bảng giá theo điểm bán', children: <>
                {can('fnb.menu.master.manage') ? <Button type="primary" onClick={() => open('pricebook')} style={{ marginBottom: 12 }}>Phát hành bảng giá</Button> : null}
                <Table rowKey="id" dataSource={asArray(data.price_books)} scroll={{ x: 750 }} columns={[
                    { title: 'Mã', dataIndex: 'code' }, { title: 'Tên', dataIndex: 'name' }, { title: 'Điểm bán', render: (_, item) => asArray(item.outlet_ids).map((id) => outlets.find((outlet) => String(outlet.id) === String(id))?.name ?? id).join(', ') },
                    { title: 'Số dòng giá', render: (_, item) => asArray(item.prices).length }, { title: 'Từ', dataIndex: 'valid_from', render: formatDateTime }, { title: 'Đến', dataIndex: 'valid_to', render: formatDateTime },
                ]} />
            </> },
        ]} />
        <Drawer open={Boolean(editor)} destroyOnHidden width="min(760px, 96vw)" onClose={saving ? undefined : () => setEditor(null)} title={editor?.kind === 'menu' ? 'Tạo thực đơn' : editor?.kind === 'pricebook' ? 'Phát hành bảng giá' : editor?.kind === 'rollback' ? 'Áp dụng bản thực đơn đã phát hành' : 'Phát hành thực đơn'} extra={<Button type="primary" loading={saving} onClick={() => form.submit()}>{editor?.kind === 'menu' ? 'Tạo thực đơn' : 'Phát hành'}</Button>}>
            <Form form={form} layout="vertical" onFinish={save}>
                {editor?.kind === 'menu' ? <><Form.Item name="code" label="Mã thực đơn" rules={[{ required: true }, { pattern: /^[A-Za-z0-9_-]+$/, message: 'Chỉ dùng chữ, số, gạch ngang hoặc gạch dưới.' }]}><Input maxLength={40} /></Form.Item><Form.Item name="name" label="Tên thực đơn" rules={[{ required: true }]}><Input /></Form.Item></> : null}
                {editor?.kind === 'publication' ? <Form.Item name="menu_id" label="Thực đơn" rules={[{ required: true }]}><Select options={asArray(data.menus).map((item) => ({ value: item.id, label: item.name }))} /></Form.Item> : null}
                {editor?.kind === 'rollback' ? <><Alert type="warning" message={`Áp dụng phiên bản ${editor.record.revision} cho các điểm bán đã chọn.`} style={{ marginBottom: 12 }} /><Form.Item name="reason" label="Lý do" rules={[{ required: true, min: 3 }]}><Input.TextArea maxLength={500} /></Form.Item></> : null}
                {editor?.kind !== 'menu' ? <Form.Item name="outlet_ids" label="Điểm bán áp dụng" rules={[{ required: true, type: 'array', min: 1, max: 100, message: 'Chọn từ 1 đến 100 điểm bán.' }]}><Select mode="multiple" showSearch optionFilterProp="label" options={outletOptions} /></Form.Item> : null}
                {editor?.kind === 'pricebook' ? <>
                    <Form.Item name="code" label="Mã bảng giá mới" rules={[{ required: true }]}><Input /></Form.Item><Form.Item name="name" label="Tên bảng giá" rules={[{ required: true }]}><Input /></Form.Item>
                    <Space wrap><Form.Item name="valid_from" label="Hiệu lực từ"><Input type="datetime-local" /></Form.Item><Form.Item name="valid_to" label="Hiệu lực đến"><Input type="datetime-local" /></Form.Item></Space>
                    <Form.List name="prices" rules={[{ validator: (_, lines) => lines?.length ? Promise.resolve() : Promise.reject(new Error('Cần ít nhất một dòng giá.')) }]}>{(fields, { add, remove }, { errors }) => <Space direction="vertical" style={{ width: '100%' }}>
                        {fields.map((field) => <Space key={field.key} wrap align="start"><Form.Item name={[field.name, 'variant_id']} label="Món / size" rules={[{ required: true }]}><Select showSearch optionFilterProp="label" style={{ width: 280 }} options={asArray(data.variants).map((variant) => ({ value: variant.id, label: `${variant.item_name ?? variant.code} · ${variant.name} (${formatMinorMoney(variant.base_price_minor)})` }))} /></Form.Item><Form.Item name={[field.name, 'amount_minor']} label="Giá bán VND" rules={[{ required: true }]}><InputNumber min={0} max={9000000000000} precision={0} /></Form.Item><Button danger onClick={() => remove(field.name)} style={{ marginTop: 30 }}>Xóa</Button></Space>)}
                        <Form.ErrorList errors={errors} /><Button onClick={() => add({})}>Thêm dòng giá</Button>
                    </Space>}</Form.List>
                </> : null}
            </Form>
        </Drawer>
    </>;
}
