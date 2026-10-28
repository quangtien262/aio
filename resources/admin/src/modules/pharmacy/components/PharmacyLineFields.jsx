import { quantityFormatter, unitLabel } from '../utils/pharmacyData';
import Button from 'antd/es/button';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Table from 'antd/es/table';
import PharmacyRemoteSelect from './PharmacyRemoteSelect';

function LineBatchSelect({ api, name, index, ...props }) {
    const form = Form.useFormInstance();
    const product = Form.useWatch([name, index, 'product_id'], form);
    return <PharmacyRemoteSelect {...props} api={api} kind="inventory" pickable disabled={!product} params={{ product_id: product }} aria-label={`Lô điều chuyển ${index + 1}`} />;
}
export default function PharmacyLineFields({ api, name = 'lines', prescription = false, costed = false, batched = false }) {
    const form = Form.useFormInstance();
    return <Form.List name={name} rules={[{ validator: (_, lines) => lines?.length ? Promise.resolve() : Promise.reject(new Error('Thêm ít nhất một dòng hàng.')) }]}>{(fields, { add, remove }, { errors }) => <div className="ph-form-lines">
        <div className="ph-section-heading"><strong>Dòng hàng</strong><Button onClick={() => add({ quantity: '1' })} disabled={fields.length >= 100}>Thêm dòng</Button></div>
        <Table rowKey="key" size="small" dataSource={fields} pagination={false} scroll={{ x: 650 }} columns={[
            { title: 'Thuốc / hàng hóa', width: 260, render: (_, field) => <Form.Item name={[field.name, 'product_id']} rules={[{ required: true, message: 'Chọn hàng hóa.' }]}><PharmacyRemoteSelect api={api} kind="products" aria-label={`Hàng hóa dòng ${field.name + 1}`} onRecord={(record) => { form.setFieldValue([name, field.name, 'unit'], record?.base_unit); if (batched) form.setFieldValue([name, field.name, 'batch_id'], null); }} /></Form.Item> },
            { title: 'Số lượng', width: 120, render: (_, field) => <Form.Item name={[field.name, 'quantity']} rules={[{ required: true }]}><InputNumber stringMode min="0.000001" precision={6} formatter={quantityFormatter} controls={false} aria-label={`Số lượng dòng ${field.name + 1}`} style={{ width: '100%' }} /></Form.Item> },
            ...(costed ? [{ title: 'Giá mua / đơn vị', width: 150, render: (_, field) => <Form.Item name={[field.name, 'unit_cost']} rules={[{ required: true, message: 'Nhập giá mua.' }]}><InputNumber stringMode min="0" precision={6} formatter={quantityFormatter} controls={false} aria-label={`Giá mua dòng ${field.name + 1}`} style={{ width: '100%' }} /></Form.Item> }] : []),
            ...(batched ? [{ title: 'Lô thực xuất', width: 280, render: (_, field) => <Form.Item name={[field.name, 'batch_id']} rules={[{ required: true, message: 'Chọn lô thực xuất.' }]}><LineBatchSelect api={api} name={name} index={field.name} /></Form.Item> }] : []),
            { title: prescription ? 'Hướng dẫn trên đơn' : 'Đơn vị cơ sở', width: 180, render: (_, field) => <Form.Item name={[field.name, prescription ? 'instructions' : 'unit']} rules={prescription ? [{ required: true, message: 'Nhập hướng dẫn trên đơn gốc.' }] : []}><Input readOnly={!prescription} aria-label={`${prescription ? 'Hướng dẫn' : 'Đơn vị'} dòng ${field.name + 1}`} /></Form.Item> },
            { title: '', width: 50, render: (_, field) => <Button type="text" danger aria-label={`Xóa dòng ${field.name + 1}`} onClick={() => remove(field.name)}>×</Button> },
        ]} />{errors.length > 0 && <Form.ErrorList errors={errors} />}
    </div>}</Form.List>;
}
