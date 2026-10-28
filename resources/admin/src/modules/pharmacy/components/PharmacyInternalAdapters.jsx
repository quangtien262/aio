import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import PharmacyRemoteSelect from './PharmacyRemoteSelect';
import { PharmacyTable, StatusTag } from './PharmacyCommon';
import { usePharmacyWorkspace } from '../state/PharmacyWorkspaceContext';

function CustomerBookFields({ form, api }) {
    return <><Form.Item name="book_id" label="Nhóm khách hàng được ủy quyền" rules={[{ required: true }]}><PharmacyRemoteSelect api={api} kind="customer-books" onRecord={(row) => form.setFieldValue('website_key', row?.website_key)} /></Form.Item><Form.Item hidden name="website_key" rules={[{ required: true }]}><Input /></Form.Item></>;
}
function AccountingMappingFields({ form, api, existing }) {
    const organization = Form.useWatch('organization_id', form);
    return <><Form.Item name="organization_id" label="Pháp nhân kế toán được ủy quyền" rules={[{ required: true }]}><PharmacyRemoteSelect disabled={Boolean(existing)} api={api} kind="accounting-organizations" /></Form.Item><Form.List name="item_mappings" rules={[{ validator: (_, lines) => lines?.length ? Promise.resolve() : Promise.reject(new Error('Thêm ít nhất một ánh xạ hàng hóa.')) }]}>{(fields, { add, remove }, { errors }) => <><div className="ph-section-heading"><strong>Đối chiếu hàng hóa</strong><Button disabled={!organization || fields.length >= 1000} onClick={() => add({})}>Thêm dòng</Button></div><Table rowKey="key" size="small" dataSource={fields} pagination={false} scroll={{ x: 650 }} columns={[
        { title: 'Hàng hóa nhà thuốc', render: (_, field) => <Form.Item name={[field.name, 'product_id']} rules={[{ required: true }]}><PharmacyRemoteSelect api={api} kind="products" aria-label={`Hàng ánh xạ ${field.name + 1}`} /></Form.Item> },
        { title: 'Hàng hóa kế toán cùng pháp nhân', render: (_, field) => <Form.Item name={[field.name, 'accounting_item_id']} rules={[{ required: true }]}><PharmacyRemoteSelect api={api} kind="accounting-items" params={{ organization_id: organization }} disabled={!organization} aria-label={`Hàng kế toán ${field.name + 1}`} /></Form.Item> },
        { title: '', width: 50, render: (_, field) => <Button danger onClick={() => remove(field.name)} aria-label={`Xóa ánh xạ ${field.name + 1}`}>×</Button> },
    ]} /><Form.ErrorList errors={errors} /></>}</Form.List></>;
}
const mappingLines = (value) => {
    let map = value ?? {}; if (typeof map === 'string') { try { map = JSON.parse(map); } catch { throw new Error('Không đọc được ánh xạ hiện tại. Tải lại trước khi thay đổi.'); } }
    return Array.isArray(map) ? map : Object.entries(map).map(([product_id, accounting_item_id]) => ({ product_id, accounting_item_id: String(accounting_item_id) }));
};
export default function PharmacyInternalAdapters({ adapters = {} }) {
    const { api, can, openCommand } = usePharmacyWorkspace();
    const customers = adapters.customers ?? {}; const accounting = adapters.accounting ?? {};
    const customerMap = customers.mappings?.[0];
    const mapCustomers = (row = null) => openCommand('customer.mapping.save', row, {}, { title: 'Ủy quyền nhóm khách hàng thương mại', description: 'Chỉ nối nhóm mà người cấu hình có quyền Customers tương ứng. Đổi nhóm đã gắn cần quy trình chuyển đổi riêng. Không chia sẻ đơn thuốc, dị ứng hoặc dữ liệu sức khỏe.', fields: [], initial: { book_id: row?.book_public_id, website_key: row?.website_key }, renderFields: ({ form }) => <CustomerBookFields form={form} api={api} /> });
    const mapAccounting = (row = null) => openCommand('accounting.mapping.save', row, {}, { title: 'Ánh xạ hàng hóa và pháp nhân kế toán', width: 900, description: 'Người cấu hình cần đồng thời quyền Nhà thuốc và quyền kế toán của pháp nhân. Ánh xạ này thay thế tập dòng hiện tại; không tự đổi pháp nhân.', fields: [], initial: { organization_id: row?.organization_id != null ? String(row.organization_id) : undefined, item_mappings: row ? mappingLines(row.item_mappings) : [{}] }, renderFields: ({ form }) => <AccountingMappingFields form={form} api={api} existing={row} />, normalize: (values) => ({ organization_id: Number(values.organization_id), item_mappings: values.item_mappings.map((item) => ({ product_id: item.product_id, accounting_item_id: Number(item.accounting_item_id) })) }) });
    const draft = (row) => openCommand('accounting.draft', null, { organization_id: Number(row.organization_id) }, { title: 'Tạo chứng từ kế toán nháp', description: 'Tạo từ giao dịch bán / hoàn tiền đã chốt. Chứng từ chỉ ở dạng nháp, không tự ghi sổ và không tự phát hành hóa đơn.', fields: [{ name: 'source_type', label: 'Chứng từ nguồn', type: 'select', required: true, options: [{ value: 'sale', label: 'Giao dịch bán đã chốt' }, { value: 'refund', label: 'Phiếu hoàn tiền đã chốt' }] }, { name: 'source_id', label: 'Mã UUID chứng từ nguồn', required: true }] });
    return <Card title="Tích hợp nội bộ" className="ph-subsection"><div className="ph-settings-grid"><Card size="small" title="Customers · dữ liệu thương mại"><Alert type="info" showIcon message={customers.available ? 'Có module Customers tương thích' : 'Module Customers chưa sẵn sàng'} description="Dữ liệu thương mại được tách khỏi hồ sơ sức khỏe. Liên kết người bệnh cần căn cứ xử lý riêng; ánh xạ nhóm không tự đồng bộ hồ sơ." /><PharmacyTable resource={{ data: customers.mappings ?? [] }} columns={[{ title: 'Nhóm khách', render: (_, row) => row.book_name ?? row.book_public_id ?? `Nhóm ${row.book_id}` }, { title: 'Trạng thái', render: (_, row) => <StatusTag value={row.status} /> }, { title: 'Phiên bản', dataIndex: 'version' }]} />{customers.available && can('customer.mapping.save') && <Button onClick={() => mapCustomers(customerMap)}>Cấu hình ủy quyền nhóm</Button>}</Card><Card size="small" title="Accounting · chứng từ nháp"><Alert type="info" showIcon message={accounting.available ? 'Có module kế toán tương thích' : 'Module kế toán chưa sẵn sàng'} description="Tạo chứng từ nháp từ nguồn đã chốt; người có quyền kế toán kiểm tra trước khi ghi sổ. Không gửi trực tiếp đến hệ thống bên ngoài." /><PharmacyTable resource={{ data: accounting.mappings ?? [] }} columns={[{ title: 'Pháp nhân', render: (_, row) => row.organization_name ?? `Pháp nhân ${row.organization_id}` }, { title: 'Phiên bản', dataIndex: 'version' }, { title: 'Thao tác', render: (_, row) => <Space wrap>{can('accounting.mapping.save') && <Button onClick={() => mapAccounting(row)}>Sửa ánh xạ</Button>}{can('accounting.draft') && <Button onClick={() => draft(row)}>Tạo chứng từ nháp</Button>}</Space> }]} />{accounting.available && can('accounting.mapping.save') && <Button onClick={() => mapAccounting()}>Thêm ánh xạ pháp nhân</Button>}</Card></div></Card>;
}
