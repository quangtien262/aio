import { quantityFormatter, unitLabel } from '../utils/pharmacyData';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Select from 'antd/es/select';
import Table from 'antd/es/table';

export function StocktakeFields({ counts }) {
    return <Form.List name="counts">{(fields) => <Table rowKey="key" size="small" dataSource={fields} pagination={false} scroll={{ x: 450 }} columns={[
        { title: 'Dòng kiểm kê', render: (_, field) => <><strong>{counts[field.name]?.product_name ?? counts[field.name]?.name ?? `Dòng tồn ${counts[field.name]?.balance_id}`}</strong><div className="ph-muted">{counts[field.name]?.batch_code ?? `Lô ${counts[field.name]?.batch_id ?? '—'}`}</div><Form.Item name={[field.name, 'balance_id']} hidden><Input /></Form.Item></> },
        { title: 'Số thực đếm', width: 180, render: (_, field) => <Form.Item name={[field.name, 'quantity']} rules={[{ required: true, message: 'Nhập số thực đếm.' }]}><InputNumber stringMode min="0" precision={6} formatter={quantityFormatter} controls={false} aria-label={`Số đếm dòng ${field.name + 1}`} style={{ width: '100%' }} /></Form.Item> },
    ]} />}</Form.List>;
}
export function PurchasingReceiveFields({ lines }) {
    return <Form.List name="lines">{(fields) => <div>{fields.map((field) => <div className="ph-allocation-row" key={field.key}>
        <Form.Item name={[field.name, 'order_line_id']} label={`Dòng đơn mua ${field.name + 1}`} rules={[{ required: true }]}><Select options={lines.map((line) => ({ value: String(line.public_id ?? line.id), label: line.product_name ?? line.name ?? line.sku_snapshot ?? `Dòng ${line.public_id ?? line.id}` }))} /></Form.Item>
        <Form.Item name={[field.name, 'quantity']} label="Số thực nhận" rules={[{ required: true }]}><InputNumber stringMode min="0.000001" precision={6} formatter={quantityFormatter} controls={false} style={{ width: '100%' }} /></Form.Item>
        <Form.Item name={[field.name, 'batch_code']} label="Mã lô" rules={[{ required: true }]}><Input /></Form.Item>
        <Form.Item name={[field.name, 'expires_at']} label="Hạn dùng" rules={[{ required: true }]}><Input type="date" /></Form.Item>
    </div>)}</div>}</Form.List>;
}
export function TransferReceiveFields({ lines }) {
    return <Form.List name="lines">{(fields) => <Table rowKey="key" size="small" dataSource={fields} pagination={false} scroll={{ x: 450 }} columns={[
        { title: 'Dòng hàng đang chuyển', render: (_, field) => <><strong>{lines[field.name]?.name ?? lines[field.name]?.product_name ?? `Dòng ${lines[field.name]?.id}`}</strong><div className="ph-muted">Lô {lines[field.name]?.batch_code ?? lines[field.name]?.batch_id ?? '—'}</div><Form.Item name={[field.name, 'line_id']} hidden><Input /></Form.Item></> },
        { title: 'Số lượng thực nhận', width: 190, render: (_, field) => <Form.Item name={[field.name, 'quantity']}><InputNumber stringMode min="0.000001" precision={6} formatter={quantityFormatter} controls={false} placeholder="Bỏ trống nếu chưa nhận" aria-label={`Số thực nhận dòng ${field.name + 1}`} style={{ width: '100%' }} /></Form.Item> },
    ]} />}</Form.List>;
}
