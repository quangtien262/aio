import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';
import { CapabilityCard, PharmacyError, PharmacyTable, StatusTag } from '../components/PharmacyCommon';
import usePharmacyResource from '../hooks/usePharmacyResource';
import { usePharmacyWorkspace } from '../state/PharmacyWorkspaceContext';
import { dateLabel, recordId } from '../utils/pharmacyData';
import PharmacyInternalAdapters from '../components/PharmacyInternalAdapters';

const kinds = [{ value: 'drug_database', label: 'CSDL dược' }, { value: 'erx', label: 'Đơn thuốc điện tử' }, { value: 'payment', label: 'Thanh toán' }, { value: 'accounting', label: 'Kế toán' }];
function CredentialsFields() {
    return <Form.List name="credential_entries">{(fields, { add, remove }) => <><div className="ph-section-heading"><strong>Thông tin xác thực mới</strong><Button onClick={() => add({})}>Thêm trường</Button></div><Typography.Paragraph className="ph-muted">Để trống để giữ thông tin đang lưu. Giá trị đã lưu không được trả về trình duyệt.</Typography.Paragraph>{fields.map((field) => <Space.Compact key={field.key} style={{ width: '100%', marginBottom: 12 }}><Form.Item name={[field.name, 'key']} rules={[{ required: true }]} style={{ flex: 1, marginBottom: 0 }}><Input placeholder="Tên trường theo contract" aria-label={`Tên trường xác thực ${field.name + 1}`} autoComplete="off" /></Form.Item><Form.Item name={[field.name, 'value']} rules={[{ required: true }]} style={{ flex: 1, marginBottom: 0 }}><Input.Password placeholder="Giá trị mới" aria-label={`Giá trị xác thực ${field.name + 1}`} autoComplete="new-password" /></Form.Item><Button danger aria-label={`Xóa trường xác thực ${field.name + 1}`} onClick={() => remove(field.name)}>×</Button></Space.Compact>)}</>}</Form.List>;
}
export default function PharmacyIntegrationsPage() {
    const { api, pharmacyId, bootstrap, revision, can, openCommand } = usePharmacyWorkspace();
    const resource = usePharmacyResource(api, 'integrations', {}, Boolean(pharmacyId), revision);
    const configure = (row = null) => openCommand('connection.save', row, {}, {
        title: row ? `Cấu hình ${row.name}` : 'Khai báo kết nối', width: 720,
        description: 'Khai báo cấu hình chưa chứng minh hệ thống ngoài đã sẵn sàng. Chỉ transport đã đăng ký, đúng contract và môi trường được phép mới có thể gửi.',
        initial: { environment: 'sandbox', ...(row ?? {}), ...row?.settings, enabled: row?.status === 'active' },
        fields: [
            { name: 'kind', label: 'Loại kết nối', type: 'select', options: kinds, required: true, disabled: Boolean(row) },
            { name: 'name', label: 'Tên kết nối', required: true }, { name: 'contract_version', label: 'Phiên bản contract đã xác minh', required: true },
            { name: 'environment', label: 'Môi trường', type: 'select', required: true, options: [{ value: 'sandbox', label: 'Sandbox / thử nghiệm' }, { value: 'production', label: 'Production' }] },
            { name: 'transport_key', label: 'Mã transport được đăng ký', required: true }, { name: 'endpoint', label: 'Endpoint theo contract' },
            { name: 'enabled', label: 'Đề nghị bật kết nối', type: 'checkbox' }, { name: 'confirmation', label: 'Xác nhận production khi bật', help: 'Chỉ nhập câu xác nhận theo mã chuỗi nếu đang mở production có chủ đích.' },
        ], renderFields: () => <CredentialsFields />,
        normalize: ({ credential_entries, ...values }) => ({ ...values, ...(credential_entries?.length ? { credentials: Object.fromEntries(credential_entries.map((item) => [item.key.trim(), item.value])) } : {}) }),
    });
    const queue = (row) => openCommand('integration.queue', null, { connection_id: recordId(row) }, { title: `Tạo yêu cầu liên thông · ${row.name}`, description: 'Yêu cầu được kiểm tra quyền và nguồn. Khi transport chưa sẵn sàng, trạng thái sẽ là bị chặn, không được coi đã gửi thành công.', fields: [
        { name: 'source_type', label: 'Loại nguồn', type: 'select', required: true, options: ({ drug_database: ['dispensing', 'receipt'], erx: ['prescription', 'dispensing'], accounting: ['sale', 'refund'], payment: [] }[row.kind] ?? []).map((value) => ({ value, label: ({ dispensing: 'Hồ sơ cấp thuốc', prescription: 'Đơn thuốc', receipt: 'Phiếu nhập', sale: 'Giao dịch bán', refund: 'Phiếu hoàn tiền' })[value] })) }, { name: 'source_id', label: 'Mã UUID chứng từ nguồn', required: true },
    ] });
    const data = resource.data ?? {};
    return <><PharmacyError error={resource.error} onRetry={resource.reload} /><PharmacyInternalAdapters adapters={data.internal_adapters} /><Card title="Kết nối nghiệp vụ" extra={<Space><Button onClick={resource.reload}>Làm mới</Button>{can('connection.save') && <Button type="primary" onClick={() => configure()}>Khai báo kết nối</Button>}</Space>}><PharmacyTable resource={resource} columns={[
        { title: 'Kết nối', render: (_, row) => <><strong>{row.name}</strong><div className="ph-muted">{kinds.find((kind) => kind.value === row.kind)?.label ?? row.kind}</div></> },
        { title: 'Môi trường', render: (_, row) => row.settings?.environment ?? '—' }, { title: 'Contract', dataIndex: 'contract_version' }, { title: 'Trạng thái', render: (_, row) => <StatusTag value={row.status} /> },
        { title: 'Thao tác', render: (_, row) => <Space wrap>{can('connection.save') && <Button size="small" onClick={() => configure(row)}>Cấu hình</Button>}{row.kind !== 'payment' && can('integration.queue') && <Button size="small" onClick={() => queue(row)}>Tạo yêu cầu</Button>}</Space> },
    ]} empty="Chưa khai báo kết nối. Không tự bật provider production." /></Card>
    <Card title="Kết quả và yêu cầu cần đối soát" className="ph-subsection"><PharmacyTable resource={{ data: data.attempts ?? [] }} columns={[
        { title: 'Nguồn', render: (_, row) => <><span>{row.source_type}</span><div className="ph-muted">{row.source_id}</div></> }, { title: 'Trạng thái thực', render: (_, row) => <StatusTag value={row.status} /> }, { title: 'Cập nhật', render: (_, row) => dateLabel(row.updated_at ?? row.created_at) },
        { title: 'Cần xử lý', render: (_, row) => row.error ?? row.reason ?? '—' }, { title: 'Đối soát', render: (_, row) => !['accepted', 'rejected'].includes(row.status) && can('integration.reconcile') && <Button onClick={() => openCommand('integration.reconcile', row, { attempt_id: recordId(row) }, { title: 'Yêu cầu kiểm tra kết quả', description: 'Dùng định danh gốc để đối soát. Không tạo lần gửi tài chính mới khi trạng thái chưa rõ.', fields: [] })}>Kiểm tra kết quả</Button> },
    ]} empty="Chưa có yêu cầu liên thông trong phạm vi." /></Card>
    <Card title="Gói nghiệp vụ có điều kiện" className="ph-subsection"><div className="ph-capability-grid">{[
        ['controlled', 'Thuốc kiểm soát đặc biệt'], ['ecommerce', 'Kênh online & chăm sóc'], ['cold_chain', 'Chuỗi lạnh & IoT'], ['clinical_knowledge', 'Kiến thức chuyên môn'],
    ].map(([key, title]) => <CapabilityCard key={key} title={title} capability={bootstrap.capabilities?.[key]}><p>{data.unsupported?.[key === 'ecommerce' ? 'online' : key] ?? 'Chỉ mở sau khi đáp ứng điều kiện cơ sở, quyền và bằng chứng nghiệm thu.'}</p></CapabilityCard>)}</div></Card></>;
}
