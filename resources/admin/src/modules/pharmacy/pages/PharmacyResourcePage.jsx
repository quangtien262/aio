import { useEffect, useState } from 'react';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Descriptions from 'antd/es/descriptions';
import Drawer from 'antd/es/drawer';
import Input from 'antd/es/input';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';
import { useNavigate } from 'react-router-dom';
import usePharmacyResource from '../hooks/usePharmacyResource';
import { usePharmacyWorkspace } from '../state/PharmacyWorkspaceContext';
import { PharmacyError, PharmacyTable, StatusTag } from '../components/PharmacyCommon';
import { StocktakeFields, PurchasingReceiveFields, TransferReceiveFields } from '../components/PharmacyOperationalFields';
import PharmacyWorkTasks from '../components/PharmacyWorkTasks';
import PharmacyCatalogTools from '../components/PharmacyCatalogTools';
import PharmacyPricePublicationFields from '../components/PharmacyPricePublicationFields';
import PharmacySavedFilters from '../components/PharmacySavedFilters';
import PharmacyOperationalHistory from '../components/PharmacyOperationalHistory';
import PharmacyRecoveryCases from '../components/PharmacyRecoveryCases';
import { dateLabel, money, pharmacyFlag, quantity, unitLabel, recordId, recordLabel, statusLabels } from '../utils/pharmacyData';

const { Text } = Typography;
const config = {
    work: { kind: 'dispensings', create: 'dispensing.create', createLabel: 'Bắt đầu tại quầy', columns: 'work' },
    sales: { kind: 'sales', columns: 'sale' },
    prescriptions: { kind: 'prescriptions', create: 'prescription.save', createLabel: 'Tiếp nhận đơn thuốc', columns: 'prescription' },
    products: { kind: 'products', create: 'product.save', createLabel: 'Thêm hàng hóa', columns: 'product' },
    prices: { kind: 'prices', columns: 'price' },
    inventory: { kind: 'inventory', columns: 'inventory' },
    receipts: { kind: 'receipts', create: 'receipt.create', createLabel: 'Nhập hàng', columns: 'receipt' },
    transfers: { kind: 'transfers', create: 'transfer.create', createLabel: 'Yêu cầu điều chuyển', columns: 'transfer' },
    stocktakes: { kind: 'stocktakes', create: 'stocktake.create', createLabel: 'Mở kiểm kê', columns: 'stocktake' },
    quality: { kind: 'quality', create: 'quality.hold', createLabel: 'Giữ / cách ly lô', columns: 'quality' },
    purchasing: { kind: 'purchasing', create: 'purchasing.create', createLabel: 'Lập yêu cầu mua', columns: 'purchase' },
    customers: { kind: 'patients', create: 'patient.save', createLabel: 'Thêm hồ sơ', columns: 'patient' },
    shifts: { kind: 'shifts', create: 'shift.open', createLabel: 'Mở ca', columns: 'shift' },
};
const descriptions = {
    product: ['name', 'Tên hàng'], patient: ['name', 'Họ tên'], prescription: ['reference', 'Mã đơn'],
};
export default function PharmacyResourcePage({ section }) {
    const { api, pharmacyId, bootstrap, revision, can, openCommand, permissions } = usePharmacyWorkspace();
    const navigate = useNavigate();
    const [query, setQuery] = useState('');
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(30);
    const [detail, setDetail] = useState(null);
    const [from, setFrom] = useState('');
    const [to, setTo] = useState('');
    const [lookup, setLookup] = useState(null);
    const [lookupError, setLookupError] = useState(null);
    const [lookupBusy, setLookupBusy] = useState(false);
    const spec = config[section] ?? config.work;
    const resource = usePharmacyResource(api, spec.kind, { ...(section === 'customers' ? {} : { q: search }), status, from, to, page, per_page: perPage }, Boolean(pharmacyId), revision);
    const suppliers = usePharmacyResource(api, 'suppliers', {}, Boolean(pharmacyId && section === 'purchasing'), revision);
    const trace = usePharmacyResource(api, 'trace', { product_id: detail?.product_id, batch_id: detail?.batch_id }, Boolean(section === 'inventory' && detail?.product_id), revision);
    const patient = usePharmacyResource(api, detail && section === 'customers' ? `patients/${encodeURIComponent(recordId(detail))}` : null, {}, Boolean(pharmacyId && detail && section === 'customers'), revision);
    useEffect(() => { const size = resource.meta?.per_page ?? resource.data?.meta?.per_page; if (size && Number(size) !== perPage) setPerPage(Number(size)); }, [resource.meta?.per_page, resource.data?.meta?.per_page, perPage]);
    const action = (operation, label, row, payload = {}) => can(operation, row) && <Button key={operation} size="small" onClick={() => openCommand(operation, row, payload)}>{label}</Button>;
    const operations = (row) => {
        if (spec.columns === 'work') return <Button type="link" onClick={() => navigate(`/modules/pharmacy/dispensings/${encodeURIComponent(recordId(row))}`)}>Mở hồ sơ</Button>;
        const id = recordId(row);
        const list = [];
        if (spec.columns === 'sale') {
            if (row.dispensing_id && (permissions.includes('pharmacy.*') || permissions.includes('pharmacy.dispensing.view'))) list.push(<Button key="dispensing" onClick={() => navigate(`/modules/pharmacy/dispensings/${encodeURIComponent(row.dispensing_id)}`)}>Hồ sơ cấp thuốc</Button>);
            if (can('print.create')) list.push(<Button key="print" onClick={() => openCommand('print.create', row, { source_id: id, kind: 'receipt', copies: '1' }, { title: 'Tạo bản in chứng từ', description: 'Bản in riêng được tạo theo dữ liệu giao dịch đã chốt.', fields: [] })}>Bản in</Button>);
        }
        if (spec.columns === 'prescription') {
            if (!['verified', 'cancelled', 'expired'].includes(row.status)) list.push(action('prescription.verify', 'Xác minh', row, { prescription_id: id, verification_method: 'manual_review' }));
            list.push(<Button key="dispense" size="small" onClick={() => navigate(`/modules/pharmacy/pos?prescription=${encodeURIComponent(id)}`)}>Lập hồ sơ cấp thuốc</Button>);
            if ((!row.origin_pharmacy_id || row.origin_pharmacy_id === pharmacyId) && row.status === 'verified' && can('prescription.transfer')) list.push(<Button key="rx-transfer" onClick={() => openCommand('prescription.transfer', row, { prescription_id: id }, { title: 'Cho phép cấp tiếp tại nhà thuốc khác', description: 'Giữ cùng đơn gốc và lượng đã cấp trong toàn chuỗi. Chỉ cấp quyền khi có bằng chứng đồng ý; nhà thuốc đích vẫn kiểm tra chuyên môn riêng.', fields: [{ name: 'target_pharmacy_id', label: 'Nhà thuốc đích', type: 'select', required: true, options: (bootstrap.pharmacies ?? []).filter((item) => recordId(item) !== pharmacyId).map((item) => ({ value: recordId(item), label: recordLabel(item) })) }, { name: 'consented_at', label: 'Thời điểm đồng ý', type: 'datetime-local', required: true }, { name: 'consent_evidence', label: 'Căn cứ và phạm vi đồng ý', type: 'textarea', required: true }, { name: 'expires_at', label: 'Quyền có hiệu lực đến', type: 'datetime-local', help: 'Không vượt quá hạn dùng của đơn gốc.' }], normalize: (values) => ({ ...values, consented_at: new Date(values.consented_at).toISOString(), expires_at: values.expires_at ? new Date(values.expires_at).toISOString() : undefined }) })}>Cấp tiếp tại điểm khác</Button>);
        }
        if (spec.columns === 'product') list.push(action('product.save', 'Sửa', row, { product_id: id }));
        if (spec.columns === 'price' && can('price.save')) list.push(<Button key="price" onClick={() => openCommand('price.save', row, { product_id: id, expected_version: row.price_version }, { initial: { product_id: id, price_minor: String(row.price_minor ?? '') } })}>Sửa giá</Button>);
        if (spec.columns === 'price' && can('price.publish')) list.push(<Button key="publish" onClick={() => openCommand('price.publish', null, { product_id: id }, { title: 'Áp dụng giá trong chuỗi', width: 740, description: 'Đối chiếu giá và phiên bản ở từng nhà thuốc. Nếu một điểm xung đột, toàn bộ đợt áp dụng bị từ chối để kiểm tra lại.', initial: { price_minor: String(row.price_minor ?? '') }, fields: [{ name: 'price_minor', label: 'Giá bán mới', type: 'money', required: true }], renderFields: ({ form, api: client }) => <PharmacyPricePublicationFields form={form} api={client} pharmacies={bootstrap.pharmacies ?? []} product={row} />, normalize: ({ price_minor, targets }) => ({ price_minor, targets }) })}>Áp dụng trong chuỗi</Button>);
        if (spec.columns === 'inventory') {
            list.push(<Button key="trace" onClick={() => setDetail(row)}>Truy xuất lô</Button>);
            if (can('quality.disposition')) list.push(<Button key="disposition" onClick={() => openCommand('quality.disposition', null, { batch_id: row.batch_id })}>Tình trạng lô</Button>);
        }
        if (spec.columns === 'receipt' && !['released', 'rejected'].includes(row.status)) list.push(action('receipt.inspect', 'Kiểm tra', row, { receipt_id: id }));
        if (spec.columns === 'quality') list.push(action('quality.release', 'Xử lý lý do giữ', row, { hold_id: id, batch_id: row.batch_id }));
        if (spec.columns === 'transfer') {
            if (row.status === 'draft' || row.status === 'requested') list.push(action('transfer.approve', 'Duyệt', row, { transfer_id: id }));
            if (row.status === 'approved') list.push(action('transfer.dispatch', 'Xuất hàng', row, { transfer_id: id }));
            if (['dispatched', 'in_transit', 'partially_received'].includes(row.status) && can('transfer.receive')) list.push(<Button key="receive-transfer" onClick={() => openCommand('transfer.receive', row, { transfer_id: id }, { title: 'Đối chiếu hàng thực nhận', width: 850, description: 'Nhập số thực nhận theo từng dòng. Bỏ trống dòng chưa nhận. Hàng nhận vào cách ly để kiểm tra riêng.', initial: { lines: (row.lines ?? []).map((line) => ({ line_id: line.id, quantity: null })) }, fields: [{ name: 'reason', label: 'Ghi nhận giao nhận', type: 'textarea', required: true }], renderFields: () => <TransferReceiveFields lines={row.lines ?? []} />, normalize: (values) => ({ ...values, lines: values.lines.filter((line) => line.quantity != null && line.quantity !== '').map((line) => ({ line_id: Number(line.line_id), quantity: String(line.quantity), disposition: 'quarantine' })) }) })}>Nhận hàng</Button>);
        }
        if (spec.columns === 'stocktake' && !['posted', 'completed', 'cancelled'].includes(row.status) && can('stocktake.post')) list.push(<Button key="count" disabled={!row.counts?.length} onClick={() => openCommand('stocktake.post', row, { stocktake_id: id }, { title: 'Kiểm đếm và ghi nhận kiểm kê', width: 800, description: 'Nhập đủ số thực đếm. Người mở kiểm kê không tự duyệt chênh lệch; máy chủ kiểm tra phân tách trách nhiệm.', initial: { counts: row.counts.map((line) => ({ balance_id: line.balance_id, quantity: line.counted_quantity ?? null })) }, fields: [], renderFields: () => <StocktakeFields counts={row.counts} /> })}>Ghi số đếm</Button>);
        if (spec.columns === 'purchase') {
            if (row.status === 'draft') list.push(action('purchasing.approve', 'Duyệt', { ...row, version: row.link_version ?? row.version }, { order_id: row.link_id ?? id }));
            if (['approved', 'ordered', 'partially_received'].includes(row.status) && can('purchasing.receive')) list.push(<Button key="receive" disabled={!row.lines?.length} onClick={() => openCommand('purchasing.receive', { ...row, version: row.link_version ?? row.version }, { order_id: row.link_id ?? id }, { title: 'Nhận hàng theo đơn mua', width: 900, initial: { lines: row.lines.map((line) => ({ order_line_id: String(line.public_id ?? line.id), quantity: null })) }, fields: [{ name: 'reference', label: 'Chứng từ giao hàng' }], renderFields: () => <PurchasingReceiveFields lines={row.lines} /> })}>Nhận hàng</Button>);
        }
        if (spec.columns === 'shift') {
            if (row.status === 'open') list.push(action('shift.close', 'Đóng ca', row, { shift_id: id }));
            if (row.status === 'open' && can('shift.cash-movement')) list.push(<Button key="cash" onClick={() => openCommand('shift.cash-movement', row, { shift_id: id }, { title: 'Ghi thu / chi tiền quầy', description: 'Chỉ ghi vào ca đang mở của chính mình. Khoản thu chi được tách khỏi doanh thu bán hàng.', fields: [{ name: 'direction', label: 'Chiều tiền', type: 'select', required: true, options: [{ value: 'in', label: 'Nộp vào quầy' }, { value: 'out', label: 'Chi khỏi quầy' }] }, { name: 'amount_minor', label: 'Số tiền', type: 'money', required: true }, { name: 'reason', label: 'Lý do và chứng từ', type: 'textarea', required: true }] })}>Thu / chi quầy</Button>);
        }
        list.push(<Button key="details" type="link" size="small" onClick={() => setDetail(row)}>Chi tiết</Button>);
        return <Space wrap>{list}</Space>;
    };
    const base = [{ title: 'Hồ sơ', key: 'identity', render: (_, row) => <div><Text strong>{recordLabel(row)}</Text>{row.created_at && <div className="ph-muted">{dateLabel(row.created_at)}</div>}</div> }];
    const columns = {
        work: [...base, { title: 'Người bệnh', render: (_, row) => row.patient_display_name ?? row.patient_name ?? row.patient?.name ?? (row.patient_id ? `Hồ sơ ${row.patient_id}` : 'Khách lẻ') }, { title: 'Kiểm tra', render: (_, row) => <StatusTag value={row.review_status} /> }, { title: 'Chuẩn bị', render: (_, row) => <StatusTag value={row.inventory_status} /> }, { title: 'Thanh toán', render: (_, row) => <StatusTag value={row.payment_status} /> }, { title: 'Bàn giao', render: (_, row) => <StatusTag value={row.handover_status} /> }, { title: 'Tổng tiền', render: (_, row) => money(row.total_minor) }],
        product: [...base, { title: 'Hoạt chất / hàm lượng', render: (_, row) => [Array.isArray(row.ingredients) ? row.ingredients.map((item) => typeof item === 'string' ? item : [item.name, item.strength, item.unit].filter(Boolean).join(' ')).join(', ') : row.ingredients, row.strength].filter(Boolean).join(' · ') || '—' }, { title: 'Dạng / đơn vị', render: (_, row) => [row.dosage_form, unitLabel(row.base_unit)].filter(Boolean).join(' · ') }, { title: 'Giá bán', render: (_, row) => money(row.price_minor) }, { title: 'Phân loại', render: (_, row) => <Space wrap>{pharmacyFlag(row.requires_prescription) && <StatusTag value="Rx" />}{pharmacyFlag(row.controlled) && <StatusTag value="Kiểm soát đặc biệt" />}{pharmacyFlag(row.cold_chain) && <StatusTag value="Chuỗi lạnh" />}</Space> }],
        price: [...base, { title: 'Giá', render: (_, row) => money(row.price_minor) }, { title: 'Phiên bản', dataIndex: 'price_version' }, { title: 'Hiệu lực', render: (_, row) => dateLabel(row.effective_at) }],
        sale: [...base, { title: 'Tổng tiền', render: (_, row) => money(row.total_minor) }, { title: 'Hàng trả', render: (_, row) => money(row.returned_minor) }, { title: 'Tiền hoàn', render: (_, row) => money(row.refunded_minor) }, { title: 'Trạng thái', render: (_, row) => <StatusTag value={row.status} /> }],
        inventory: [...base, { title: 'Lô', dataIndex: 'batch_code' }, { title: 'Hạn dùng', render: (_, row) => dateLabel(row.expires_at) }, { title: 'Thực tế', render: (_, row) => quantity(row.on_hand ?? row.quantity_on_hand) }, { title: 'Đang giữ', render: (_, row) => quantity(row.reserved ?? row.quantity_reserved) }, { title: 'Khả dụng', render: (_, row) => quantity(row.available) }, { title: 'Tình trạng', render: (_, row) => <StatusTag value={row.disposition ?? row.status} /> }],
        prescription: [...base, { title: 'Người bệnh', render: (_, row) => row.patient_display_name ?? row.patient_name ?? row.patient?.name ?? (row.patient_id ? `Hồ sơ ${row.patient_id}` : '—') }, { title: 'Ngày kê', render: (_, row) => dateLabel(row.issued_at) }, { title: 'Hiệu lực', render: (_, row) => dateLabel(row.expires_at) }, { title: 'Xác minh', render: (_, row) => <StatusTag value={row.verification_status ?? row.status} /> }],
        patient: [...base, { title: 'Trạng thái', render: (_, row) => <StatusTag value={row.status} /> }],
        shift: [...base, { title: 'Quầy', render: (_, row) => row.counter_name ?? row.counter_id }, { title: 'Đầu ca', render: (_, row) => money(row.opening_minor) }, { title: 'Hệ thống', render: (_, row) => money(row.expected_minor) }, { title: 'Thực đếm', render: (_, row) => money(row.closing_minor ?? row.counted_minor) }, { title: 'Chênh lệch', render: (_, row) => money(row.difference_minor ?? row.variance_minor) }, { title: 'Trạng thái', render: (_, row) => <StatusTag value={row.status} /> }],
    };
    const genericColumns = [...base, { title: 'Lô / tham chiếu', render: (_, row) => row.batch_code ?? row.reference ?? '—' }, { title: 'Số lượng', render: (_, row) => quantity(row.quantity ?? row.total_quantity) }, { title: 'Trạng thái', render: (_, row) => <StatusTag value={row.status} /> }, { title: 'Lý do', render: (_, row) => row.reason ?? row.note ?? '—' }];
    const displayColumns = [...(columns[spec.columns] ?? genericColumns), { title: 'Thao tác', key: 'actions', render: (_, row) => operations(row) }];
    const searchRows = async (value) => { if (section !== 'customers') { setSearch(value.trim()); setPage(1); return; } setLookupError(null); setLookup(null); if (!value.trim()) return; setLookupBusy(true); try { setLookup((await api.lookupPatients(value.trim())).data); } catch (error) { setLookupError(error); } finally { setLookupBusy(false); } };
    return <><Card className="ph-list-card"><div className="ph-toolbar"><Input.Search allowClear value={query} loading={lookupBusy} onChange={(event) => setQuery(event.target.value)} onSearch={searchRows} placeholder={section === 'customers' ? 'Tra cứu số điện thoại chính xác (không tự gộp hồ sơ)' : section === 'inventory' ? 'Tìm hàng hóa, mã lô…' : 'Tìm theo tên, mã hoặc tham chiếu…'} aria-label="Tìm trong danh sách" />
        <Select aria-label="Lọc trạng thái" value={status} onChange={(value) => { setStatus(value); setPage(1); }} options={[{ value: '', label: 'Tất cả trạng thái' }, ...(section === 'inventory' ? ['saleable', 'quarantine', 'damaged', 'rejected'] : ['draft', 'pending', 'prepared', 'reviewed', 'finalized', 'handed_over', 'open', 'closed', 'quarantine', 'released', 'cancelled']).map((value) => ({ value, label: statusLabels[value] ?? value }))]} style={{ minWidth: 160 }} />
        <Button onClick={resource.reload}>Làm mới</Button>{spec.create && can(spec.create) && <Button type="primary" onClick={() => spec.create === 'dispensing.create' ? navigate('/modules/pharmacy/pos') : openCommand(spec.create)}>{spec.createLabel}</Button>}
        {section === 'quality' && can('recall.create') && <Button danger onClick={() => openCommand('recall.create')}>Mở thu hồi</Button>}</div>
        {section === 'purchasing' && can('supplier.save') && <Button onClick={() => openCommand('supplier.save')} style={{ marginBottom: 16 }}>Thêm nhà cung cấp đã xác minh</Button>}
        {resource.data?.available === false && <Typography.Paragraph type="warning">{resource.data.reason ?? 'Module nghiệp vụ liên quan chưa được cài hoặc bật.'}</Typography.Paragraph>}
        <PharmacyError error={lookupError} />{lookup && <Typography.Paragraph>{lookup.records?.length ? 'Đối chiếu từng hồ sơ trước khi chọn. Cùng số điện thoại không đồng nghĩa cùng người bệnh.' : 'Chưa có hồ sơ khớp số điện thoại.'}</Typography.Paragraph>}
        {['products', 'inventory', 'sales', 'receipts', 'transfers', 'stocktakes'].includes(section) && <PharmacySavedFilters resource={section} filters={{ status, from, to, per_page: perPage }} onApply={(filters) => { setStatus(filters.status ?? ''); setFrom(filters.from ?? ''); setTo(filters.to ?? ''); setPerPage(Number(filters.per_page ?? 30)); setPage(1); }} />}
        <PharmacyTable resource={lookup ? { ...resource, data: lookup, meta: lookup.meta } : resource} rowKey={section === 'inventory' ? (row) => String(row.balance_id) : recordId} columns={displayColumns} page={page} perPage={perPage} onPageChange={(next, size) => { setPage(size === perPage ? next : 1); setPerPage(size); }} /></Card>
        {section === 'products' && can('import.preview') && <PharmacyCatalogTools />}
        {section === 'work' && <PharmacyWorkTasks />}
        {section === 'work' && (permissions.includes('pharmacy.*') || permissions.includes('pharmacy.dispensing.manage')) && <PharmacyRecoveryCases />}
        {section === 'purchasing' && <PharmacyOperationalHistory kind="replenishment" />}
        {section === 'shifts' && <PharmacyOperationalHistory kind="cash-movements" />}
        {section === 'purchasing' && <Card title="Nhà cung cấp trong phạm vi" className="ph-subsection"><PharmacyTable resource={suppliers} columns={[{ title: 'Tên', dataIndex: 'name' }, { title: 'Mã', dataIndex: 'code' }, { title: 'Giấy phép', dataIndex: 'license_number' }, { title: 'Hiệu lực', render: (_, row) => dateLabel(row.valid_until) }, { title: 'Thao tác', render: (_, row) => can('supplier.save') && <Button disabled={!row.link_version || !row.supplier_revision} onClick={() => openCommand('supplier.save', row, { supplier_id: recordId(row), expected_version: row.link_version, supplier_revision: row.supplier_revision }, { title: 'Cập nhật nhà cung cấp và điều kiện', description: 'Kiểm tra lại điều kiện và thời hạn. Cập nhật đồng thời phiên bản dữ liệu nhà cung cấp dùng chung và hồ sơ đủ điều kiện của chuỗi.' })}>Cập nhật điều kiện</Button> }]} /></Card>}
        <Drawer title={detail ? recordLabel(detail) : 'Chi tiết'} open={Boolean(detail)} width={650} onClose={() => setDetail(null)} destroyOnHidden rootClassName="ph-drawer"><Descriptions bordered column={1} items={[
            ...(descriptions[spec.columns] ? [{ key: 'name', label: descriptions[spec.columns][1], children: detail?.[descriptions[spec.columns][0]] }] : []),
            ...Object.entries({ code: 'Mã', reference: 'Tham chiếu', status: 'Trạng thái', version: 'Phiên bản', batch_code: 'Mã lô', expires_at: 'Hạn dùng', reason: 'Lý do', prescriber_name: 'Người kê đơn', facility_name: 'Cơ sở kê đơn', original_reference: 'Tham chiếu đơn gốc' }).filter(([key]) => detail?.[key] != null).map(([key, label]) => ({ key, label, children: key === 'status' ? <StatusTag value={detail[key]} /> : String(detail[key]) })),
        ]} />{detail?.lines?.length > 0 && <div className="ph-detail-lines">{detail.lines.map((line, index) => <div key={recordId(line) || index}><strong>{line.name ?? line.product_name ?? line.product_id}</strong><span>{quantity(line.quantity)} {unitLabel(line.unit)}</span>{line.instructions && <p>{line.instructions}</p>}</div>)}</div>}
        {section === 'customers' && <Card loading={patient.loading} className="ph-subsection" title="Thông tin được phép xem"><PharmacyError error={patient.error} onRetry={patient.reload} /><Descriptions column={1} items={['date_of_birth', 'phone', 'address', 'notes', 'processing_basis'].filter((key) => patient.data?.[key] != null).map((key) => ({ key, label: ({ date_of_birth: 'Ngày sinh', phone: 'Điện thoại', address: 'Địa chỉ', notes: 'Ghi nhận cần thiết', processing_basis: 'Căn cứ xử lý' })[key], children: String(patient.data[key]) }))} />{patient.data?.allergies?.length > 0 && <p>Thông tin dị ứng đã ghi nhận: {patient.data.allergies.join(', ')}</p>}{patient.data && !patient.error && can('patient.save') && <Button onClick={() => { setDetail(null); openCommand('patient.save', patient.data, { patient_id: recordId(patient.data) }, { title: 'Cập nhật hồ sơ', description: 'Đối chiếu lại căn cứ xử lý dữ liệu trước khi lưu. Thay đổi hồ sơ có thể yêu cầu kiểm tra lại các giao dịch đang xử lý.' }); }}>Cập nhật hồ sơ</Button>}</Card>}
        {section === 'prescriptions' && detail?.transfers?.length > 0 && <Card title="Quyền cấp tiếp theo đơn gốc" className="ph-subsection">{detail.transfers.map((grant) => <div className="ph-toolbar" key={recordId(grant)}><Text>{(bootstrap.pharmacies ?? []).find((item) => recordId(item) === grant.target_pharmacy_id)?.name ?? grant.target_pharmacy_id}</Text><StatusTag value={grant.status} /><Text>Đến {dateLabel(grant.expires_at)}</Text>{grant.status === 'active' && detail.origin_pharmacy_id === pharmacyId && can('prescription.transfer-revoke') && <Button danger onClick={() => { setDetail(null); openCommand('prescription.transfer-revoke', grant, { transfer_id: recordId(grant) }, { title: 'Thu hồi quyền cấp tiếp', fields: [{ name: 'reason', label: 'Căn cứ thu hồi', type: 'textarea', required: true }] }); }}>Thu hồi quyền</Button>}</div>)}</Card>}
        {section === 'inventory' && detail && <div className="ph-subsection"><PharmacyTable resource={trace} rowKey={(row) => String(row.id)} columns={[{ title: 'Bút toán', dataIndex: 'id' }, { title: 'Loại', dataIndex: 'type' }, { title: 'Biến động', dataIndex: 'quantity_delta' }, { title: 'Nguồn', render: (_, row) => [row.source_type, row.source_id].filter(Boolean).join(' · ') }, { title: 'Ghi lúc', render: (_, row) => dateLabel(row.created_at) }]} /></div>}
        </Drawer>
    </>;
}
