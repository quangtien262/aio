import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Descriptions from 'antd/es/descriptions';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Typography from 'antd/es/typography';
import { useNavigate } from 'react-router-dom';
import PharmacyRemoteSelect from '../components/PharmacyRemoteSelect';
import { PharmacyError, StatusTag } from '../components/PharmacyCommon';
import usePharmacyResource from '../hooks/usePharmacyResource';
import { usePharmacyWorkspace } from '../state/PharmacyWorkspaceContext';
import { quantityFormatter, unitLabel, dateLabel, money, quantity, recordId } from '../utils/pharmacyData';

function AllocationFields({ api, lines }) {
    return <Form.List name="allocations" rules={[{ validator: (_, values) => values?.length ? Promise.resolve() : Promise.reject(new Error('Chọn lô và số lượng thực lấy.')) }]}>{(fields, { add, remove }, { errors }) => <>
        <Alert type="info" showIcon message="Đối chiếu lô thực lấy" description="Quét mã hàng chưa đủ xác nhận lô. Chọn đúng lô và lượng thực lấy; một dòng hàng có thể dùng nhiều lô." style={{ marginBottom: 16 }} />
        {fields.map((field) => <div className="ph-allocation-row" key={field.key}>
            <Form.Item name={[field.name, 'line_id']} label={`Dòng cấp thuốc ${field.name + 1}`} rules={[{ required: true }]}><Select options={lines.map((line) => ({ value: recordId(line), label: `${line.product_name ?? line.name ?? line.product_id} · ${quantity(line.quantity)} ${unitLabel(line.unit)}` }))} /></Form.Item>
            <Form.Item name={[field.name, 'batch_id']} label="Lô thực lấy" rules={[{ required: true, message: 'Chọn lô thực lấy.' }]}><PharmacyRemoteSelect api={api} kind="inventory" pickable aria-label={`Lô thực lấy ${field.name + 1}`} /></Form.Item>
            <Form.Item name={[field.name, 'quantity']} label="Số lượng" rules={[{ required: true }]}><InputNumber stringMode min="0.000001" precision={6} formatter={quantityFormatter} controls={false} style={{ width: '100%' }} /></Form.Item>
            <Button danger type="text" onClick={() => remove(field.name)} aria-label={`Xóa phân bổ lô ${field.name + 1}`}>Xóa lô</Button>
        </div>)}<Form.ErrorList errors={errors} /><Button block type="dashed" onClick={() => add({ quantity: '1' })}>Thêm một lô</Button>
        <Form.Item name="reason" label="Ghi nhận chuẩn bị"><Input.TextArea rows={2} /></Form.Item>
    </>}</Form.List>;
}
function PaymentFields({ form, api, total }) {
    const method = Form.useWatch('method', form) ?? 'cash';
    return <><div className="ph-payment-amount"><span>Cần thu theo máy chủ</span><strong>{money(total)}</strong></div>
        <Form.Item name="shift_id" label="Ca thu tiền" rules={[{ required: true }]}><PharmacyRemoteSelect api={api} kind="shifts" params={{ status: 'open' }} label="Chọn ca đang mở" /></Form.Item>
        <Form.Item name="method" label="Phương thức" rules={[{ required: true }]}><Select options={[{ value: 'cash', label: 'Tiền mặt' }, { value: 'manual_transfer', label: 'Chuyển khoản xác nhận thủ công' }]} /></Form.Item>
        {method === 'cash' ? <Form.Item name="tendered_minor" label="Tiền mặt khách đưa" rules={[{ required: true }]}><InputNumber stringMode min="0" precision={0} suffix="₫" style={{ width: '100%' }} /></Form.Item> : <><Alert type="warning" showIcon message="Xác nhận thủ công" description="Chỉ người có quyền xác nhận sau khi đối chiếu bằng chứng. Đây không phải xác nhận tự động từ ngân hàng." /><Form.Item name="reference" label="Mã tham chiếu / bằng chứng" rules={[{ required: true }]}><Input /></Form.Item></>}
    </>;
}
export default function PharmacyDispensingPage({ id }) {
    const { api, pharmacyId, revision, can, openCommand, permissions } = usePharmacyWorkspace();
    const navigate = useNavigate();
    const resource = usePharmacyResource(api, `dispensings/${encodeURIComponent(id)}`, {}, Boolean(pharmacyId && id), revision);
    const record = resource.data?.dispensing ?? resource.data;
    const canReadPrescription = permissions.includes('pharmacy.*') || permissions.includes('pharmacy.prescription.view');
    const canReadSensitive = permissions.includes('pharmacy.*') || permissions.includes('pharmacy.patient.sensitive');
    const prescription = usePharmacyResource(api, record?.prescription_id ? `prescriptions/${encodeURIComponent(record.prescription_id)}` : null, {}, Boolean(record?.prescription_id && canReadPrescription), revision);
    const lines = record?.lines ?? [];
    const base = { dispensing_id: id, expected_version: record?.version };
    const open = (action, title, spec) => openCommand(action, record, base, { title, onReload: resource.reload, ...spec });
    const prepare = () => open('dispensing.prepare', 'Xác nhận hàng và lô thực lấy', { width: 780, initial: { allocations: lines.map((line) => ({ line_id: recordId(line), quantity: String(line.quantity) })) }, renderFields: ({ api: client }) => <AllocationFields api={client} lines={lines} /> });
    const review = () => open('dispensing.review', 'Kiểm tra và xác nhận chuyên môn', { description: 'Kiểm tra đơn, người bệnh, hàng và lô trước khi xác nhận. Hệ thống kiểm điều kiện chuyên môn tại thời điểm lưu.', fields: [
        { name: 'decision', label: 'Kết quả', type: 'select', required: true, options: [{ value: 'approved', label: 'Đã kiểm tra — đồng ý' }, { value: 'rejected', label: 'Từ chối / cần xử lý lại' }] }, { name: 'reason', label: 'Căn cứ kiểm tra', type: 'textarea', required: true }, { name: 'counseling', label: 'Ghi nhận hướng dẫn đã xác nhận', type: 'textarea' },
    ] });
    const finalize = () => open('dispensing.finalize', 'Xác nhận thu tiền và chốt giao dịch', {
        description: 'Chốt thu tiền, cấp thuốc và xuất kho khi đủ điều kiện. Bàn giao được xác nhận riêng sau bước này.', initial: { method: 'cash', tendered_minor: String(record.total_minor ?? '0') },
        renderFields: ({ form, api: client }) => <PaymentFields form={form} api={client} total={record.total_minor} />,
        normalize: (values) => ({ shift_id: values.shift_id, tenders: [{ method: values.method, amount_minor: String(record.total_minor ?? ''), ...(values.method === 'cash' ? { tendered_minor: String(values.tendered_minor ?? '') } : { reference: values.reference }) }] }),
    });
    const handover = () => open('dispensing.handover', 'Xác nhận bàn giao hàng', { description: 'Đối chiếu người nhận, hàng và lô. Máy chủ kiểm lại chặn thu hồi và điều kiện bàn giao.', fields: [{ name: 'recipient_name', label: 'Người nhận', required: true }, { name: 'reason', label: 'Ghi nhận bàn giao', type: 'textarea', required: true }] });
    const returnGoods = () => open('dispensing.return', 'Tiếp nhận hàng trả', { description: 'Hàng trả tiếp nhận vào cách ly. Tiếp nhận hàng không tự hoàn tiền hoặc khôi phục lượng được cấp theo đơn.', fields: [
        { name: 'line_id', label: 'Dòng hàng trả', type: 'select', required: true, options: lines.map((line) => ({ value: recordId(line), label: line.product_name ?? line.name ?? line.product_id })) }, { name: 'allocation_id', label: 'Lô giao ban đầu', type: 'select', required: true, options: lines.flatMap((line) => (line.allocations ?? []).map((allocation) => ({ value: recordId(allocation), label: `${line.name ?? line.product_name} · Lô ${allocation.batch_code ?? allocation.batch_id} · ${allocation.quantity}` }))) }, { name: 'quantity', label: 'Số lượng thực nhận theo đơn vị cơ sở', type: 'quantity', required: true }, { name: 'reason', label: 'Lý do', type: 'textarea', required: true },
    ], normalize: ({ line_id, allocation_id, quantity: amount, reason }) => ({ lines: [{ line_id, allocation_id, quantity: String(amount), disposition: 'quarantine' }], reason }) });
    const button = (action, label, click, { primary = false, allowedStates } = {}) => can(action, record) && (!allowedStates || allowedStates.includes(record?.status)) && <Button type={primary ? 'primary' : 'default'} size="large" onClick={click}>{label}</Button>;
    return <><div className="ph-detail-navigation"><Button onClick={() => navigate('/modules/pharmacy/work')}>← Hàng đợi</Button><Button onClick={resource.reload}>Tải bản mới nhất</Button></div><PharmacyError error={resource.error} onRetry={resource.reload} />
        <Card loading={resource.loading} title={<Space><span>{record?.code ?? 'Hồ sơ cấp thuốc'}</span><StatusTag value={record?.status} /></Space>}>
            {record && <><div className="ph-state-strip">{[
                ['Kiểm tra', record.review_status], ['Hàng / lô', record.inventory_status], ['Thanh toán', record.payment_status], ['Bàn giao', record.handover_status], ['Liên thông', record.sync_status],
            ].map(([label, value]) => <div key={label}><span>{label}</span><StatusTag value={value} /></div>)}</div>
            {record.blockers?.length > 0 && <Alert showIcon type="warning" message="Hồ sơ cần xử lý trước khi tiếp tục" description={<ul>{record.blockers.map((blocker, index) => <li key={index}>{typeof blocker === 'string' ? blocker : blocker.message ?? blocker.reason}</li>)}</ul>} />}
            <Descriptions className="ph-record-summary" column={{ xs: 1, sm: 2, lg: 3 }} items={[
                { key: 'patient', label: 'Người bệnh / khách', children: record.patient_display_name ?? record.patient_name ?? record.patient?.name ?? (record.patient_id ? `Hồ sơ ${record.patient_id}` : 'Khách lẻ') }, { key: 'mode', label: 'Loại hồ sơ', children: record.mode === 'rx' ? 'Cấp theo đơn' : 'OTC' },
                { key: 'date', label: 'Tiếp nhận', children: dateLabel(record.created_at) }, { key: 'rx', label: 'Đơn gốc', children: record.prescription_reference ?? record.prescription?.reference ?? '—' },
                { key: 'version', label: 'Phiên bản', children: record.version }, { key: 'total', label: 'Tổng tiền', children: <strong>{money(record.total_minor)}</strong> },
            ]} />
            {record.clinical_patient && <Card size="small" title="Thông tin cần đối chiếu" className="ph-clinical-summary"><Descriptions column={2} items={[
                { key: 'dob', label: 'Ngày sinh', children: dateLabel(record.clinical_patient.date_of_birth) },
                { key: 'allergies', label: 'Thông tin dị ứng đã ghi nhận', children: (record.clinical_patient.allergies ?? []).map((item) => typeof item === 'string' ? item : item.name ?? '').filter(Boolean).join(', ') || 'Chưa có ghi nhận' },
                { key: 'notes', label: 'Ghi nhận cần thiết', children: record.clinical_patient.notes ?? '—' },
            ]} /></Card>}
            {record.prescription_id && canReadPrescription && <Card size="small" title="Thông tin đơn gốc" loading={prescription.loading} className="ph-clinical-summary"><PharmacyError error={prescription.error} onRetry={prescription.reload} /><Descriptions column={2} items={[
                { key: 'prescriber', label: 'Người kê đơn', children: prescription.data?.prescriber_name ?? '—' }, { key: 'facility', label: 'Cơ sở', children: prescription.data?.facility_name ?? '—' },
                { key: 'original', label: 'Tham chiếu bản gốc', children: prescription.data?.original_reference ?? '—' }, { key: 'status', label: 'Xác minh', children: <StatusTag value={prescription.data?.status} /> },
            ]} /></Card>}
            <Table rowKey={recordId} dataSource={lines} pagination={false} scroll={{ x: 650 }} columns={[
                { title: 'Hàng hóa', render: (_, line) => <><strong>{line.product_name ?? line.name ?? line.product_id}</strong><div className="ph-muted">{[line.strength, line.dosage_form, unitLabel(line.unit)].filter(Boolean).join(' · ')}</div>{line.instructions && <div>{line.instructions}</div>}</> },
                { title: 'Số lượng', render: (_, line) => `${quantity(line.quantity)} ${unitLabel(line.unit)}` }, { title: 'Lô đã chọn', render: (_, line) => (line.allocations ?? line.stock_allocations ?? []).map((allocation, index) => <div key={index}>{allocation.batch_code ?? `Lô ${allocation.batch_id}`} · {quantity(allocation.quantity)}{allocation.expires_at && ` · HSD ${dateLabel(allocation.expires_at)}`} </div>) },
                { title: 'Thành tiền', render: (_, line) => money(line.total_minor) },
            ]} />
            <div className="ph-workflow-actions"><Space wrap>
                {button('dispensing.prepare', '1. Chuẩn bị hàng & lô', prepare, { primary: record.status === 'draft', allowedStates: ['draft'] })}
                {button('dispensing.review', '2. Kiểm tra chuyên môn', review, { primary: record.status === 'prepared', allowedStates: ['prepared'] })}
                {button('dispensing.finalize', '3. Thu tiền & chốt', finalize, { primary: record.status === 'reviewed', allowedStates: ['reviewed'] })}
                {button('dispensing.handover', '4. Bàn giao', handover, { primary: record.status === 'finalized', allowedStates: ['finalized'] })}
            </Space><Space wrap>
                {button('dispensing.assign', 'Phân công bước', () => open('dispensing.assign', 'Phân công người xử lý', { description: 'Máy chủ kiểm tra quyền hiện hành và điều kiện chuyên môn của người nhận nhiệm vụ.', fields: [{ name: 'assigned_to', label: 'ID tài khoản được phân công', type: 'integer', min: 1, required: true }, { name: 'stage', label: 'Bước xử lý', type: 'select', required: true, options: [{ value: 'prepare', label: 'Chuẩn bị hàng' }, { value: 'review', label: 'Kiểm tra chuyên môn' }, { value: 'payment', label: 'Thu tiền' }, { value: 'handover', label: 'Bàn giao' }] }, { name: 'priority', label: 'Ưu tiên công việc', type: 'select', required: true, options: [{ value: 0, label: 'Thường' }, { value: 1, label: 'Ưu tiên 1' }, { value: 2, label: 'Ưu tiên 2' }, { value: 3, label: 'Ưu tiên 3' }] }, { name: 'due_at', label: 'Cần xử lý trước', type: 'datetime-local' }], initial: { priority: 0 }, normalize: (values) => ({ ...values, due_at: values.due_at ? new Date(values.due_at).toISOString() : undefined }) }), { allowedStates: ['draft', 'prepared', 'reviewed', 'finalized'] })}
                {canReadSensitive && button('dispensing.parties', 'Người mua / nhận hộ', () => open('dispensing.parties', 'Ghi nhận các bên tham gia', { description: 'Phân biệt người bệnh, người mua, người trả tiền và người nhận. Ghi nhận trước khi chuẩn bị hàng.', fields: [{ name: 'buyer_name', label: 'Người mua' }, { name: 'payer_name', label: 'Người trả tiền' }, { name: 'recipient_name', label: 'Người nhận dự kiến' }, { name: 'relationship', label: 'Quan hệ với người bệnh', required: true }, { name: 'authority_evidence', label: 'Căn cứ đồng ý / đại diện', type: 'textarea', required: true }] }), { allowedStates: ['draft'] })}
                {canReadSensitive && button('dispensing.counsel', 'Ghi nhận hướng dẫn', () => open('dispensing.counsel', 'Ghi nhận hướng dẫn đã thực hiện', { description: 'Người có điều kiện chuyên môn ghi bằng chứng đối chiếu và hướng dẫn. Không tự sinh nội dung tư vấn.', fields: [{ name: 'recipient_name', label: 'Người được hướng dẫn' }, { name: 'reason', label: 'Nội dung, căn cứ và xác nhận đã thực hiện', type: 'textarea', required: true }] }), { allowedStates: ['draft', 'prepared', 'reviewed', 'finalized', 'handed_over'] })}
                {canReadSensitive && button('dispensing.handover-failed', 'Bàn giao chưa thành công', () => open('dispensing.handover-failed', 'Ghi nhận bàn giao chưa thành công', { description: 'Giữ trạng thái chờ bàn giao. Tiền và tồn kho không tự hoàn; thực hiện nhận lại / hoàn tiền riêng nếu cần.', fields: [{ name: 'recipient_name', label: 'Người nhận dự kiến' }, { name: 'reason', label: 'Lý do và cách xử lý tiếp', type: 'textarea', required: true }] }), { allowedStates: ['finalized'] })}
                {button('dispensing.return', 'Nhận hàng trả', returnGoods, { allowedStates: ['finalized', 'handed_over', 'partially_returned'] })}
                {button('dispensing.refund', 'Hoàn tiền', () => open('dispensing.refund', 'Xác nhận hoàn tiền', { initial: { method: 'cash' }, fields: [{ name: 'shift_id', label: 'Ca hoàn tiền', kind: 'shifts', params: { status: 'open' }, required: true }, { name: 'return_id', label: 'Mã phiếu nhận lại (nếu có)' }, { name: 'method', label: 'Phương thức', type: 'select', required: true, options: [{ value: 'cash', label: 'Tiền mặt' }] }, { name: 'amount_minor', label: 'Số tiền hoàn', type: 'money', required: true }, { name: 'reason', label: 'Lý do và căn cứ', type: 'textarea', required: true }] }), { allowedStates: ['finalized', 'handed_over', 'partially_returned', 'returned'] })}
                {button('dispensing.cancel', 'Hủy hồ sơ chưa chốt', () => open('dispensing.cancel', 'Hủy hồ sơ chưa chốt', { fields: [{ name: 'reason', label: 'Lý do', type: 'textarea', required: true }] }), { allowedStates: ['draft', 'prepared', 'reviewed'] })}
                {can('print.create', record) && <Button onClick={() => openCommand('print.create', record, {}, { title: 'Tạo bản in', initial: { kind: 'receipt', copies: '1' }, fields: [{ name: 'kind', label: 'Loại bản in', type: 'select', required: true, options: [{ value: 'receipt', label: 'Chứng từ bán hàng' }, { value: 'dispensing-label', label: 'Nhãn cấp thuốc (cần quyền riêng)' }, { value: 'handover', label: 'Bản bàn giao' }] }, { name: 'copies', label: 'Số bản', type: 'integer', required: true, min: 1, max: 3 }], normalize: (values) => ({ ...values, source_id: values.kind === 'receipt' ? record.sale_id ?? id : id }) })}>Yêu cầu bản in</Button>}
            </Space></div>
            <Typography.Paragraph className="ph-muted">Các bước có thể được thực hiện bởi những người khác nhau. Quyền và điều kiện chuyên môn được kiểm lại ở mỗi hành động.</Typography.Paragraph></>}
        </Card>
    </>;
}
