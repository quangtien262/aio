import { useEffect, useRef, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Empty from 'antd/es/empty';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { useNavigate, useSearchParams } from 'react-router-dom';
import PharmacyRemoteSelect from '../components/PharmacyRemoteSelect';
import { PharmacyError } from '../components/PharmacyCommon';
import usePharmacyResource from '../hooks/usePharmacyResource';
import { usePharmacyWorkspace } from '../state/PharmacyWorkspaceContext';
import { quantityFormatter, unitLabel, decimalInput, money, pharmacyFlag, recordId, resourceRows, dateLabel } from '../utils/pharmacyData';

export default function PharmacyPosPage() {
    const { api, pharmacyId, revision, can, openCommand, runCommand, permissions, setDraftDirty } = usePharmacyWorkspace();
    const navigate = useNavigate();
    const [params] = useSearchParams();
    const [mode, setMode] = useState(params.get('prescription') ? 'rx' : 'otc');
    const [patientId, setPatientId] = useState(null);
    const [prescriptionId, setPrescriptionId] = useState(params.get('prescription'));
    const [lines, setLines] = useState([]);
    const [scan, setScan] = useState('');
    const [query, setQuery] = useState('');
    const [error, setError] = useState(null);
    const [busy, setBusy] = useState(false);
    const [scanResult, setScanResult] = useState(null);
    const canReadPatient = permissions.includes('pharmacy.*') || permissions.includes('pharmacy.patient.view');
    useEffect(() => { setDraftDirty(Boolean(lines.length || patientId || prescriptionId)); return () => setDraftDirty(false); }, [lines, patientId, prescriptionId, setDraftDirty]);
    const searchRef = useRef(null);
    const products = usePharmacyResource(api, 'products', { q: query, query, page: 1, per_page: 40 }, Boolean(pharmacyId), revision);
    const prescription = usePharmacyResource(api, prescriptionId ? `prescriptions/${encodeURIComponent(prescriptionId)}` : null, {}, Boolean(prescriptionId && pharmacyId), revision);
    useEffect(() => {
        if (!prescription.data) return;
        const record = prescription.data;
        setPatientId(record.patient_id ?? record.patient?.id ?? null);
        setLines((record.lines ?? []).filter((line) => Number(line.remaining_quantity ?? line.quantity) > 0).map((line) => ({ key: globalThis.crypto.randomUUID(), product_id: line.product_id, prescription_line_id: recordId(line), name: line.product_name ?? line.name ?? line.product_id, quantity: String(line.remaining_quantity ?? line.quantity), unit: line.unit ?? line.base_unit ?? '' })));
    }, [prescription.data]);
    useEffect(() => {
        const handler = (event) => {
            if (event.key === 'F2') { event.preventDefault(); searchRef.current?.focus(); }
        };
        globalThis.addEventListener('keydown', handler);
        return () => globalThis.removeEventListener('keydown', handler);
    }, []);
    const add = (product) => {
        if (!can('dispensing.create') || busy || mode === 'rx') return false;
        if (product.packaging_id && (!product.quantity_multiplier || !product.packaging_name)) { setError(new Error('Mã thuộc quy cách đóng gói nhưng chưa đủ thông tin quy đổi để đối chiếu. Chưa thêm hàng; kiểm tra danh mục quy cách trước khi tiếp tục.')); return false; }
        setLines((items) => [...items, { key: globalThis.crypto.randomUUID(), product_id: recordId(product), name: product.name, unit: product.packaging_id ? product.packaging_unit ?? product.packaging_name : product.base_unit ?? product.unit ?? '', quantity: '1', ...(product.packaging_id ? { packaging_id: Number(product.packaging_id), packaging_name: product.packaging_name, quantity_multiplier: product.quantity_multiplier, base_unit: product.base_unit } : {}) }]);
        setScan(''); searchRef.current?.focus();
        return true;
    };
    const search = async (value) => {
        const text = value.trim(); setQuery(text); setError(null); setScanResult(null);
        if (!text) return;
        try {
            if (/^(?:\]C1|01\d{14}|\(01\))/.test(text) || /^\d{8,14}$/.test(text)) {
                const response = await api.read('scan', { code: text });
                const added = resourceRows(response.data).length === 1 && add(resourceRows(response.data)[0]);
                setScanResult({ ...response.data, auto_added: added });
                return;
            }
            const response = await api.read('products', { q: text, query: text, per_page: 40 });
            const exact = resourceRows(response.data).filter((product) => [product.code, product.sku, product.barcode, ...(product.barcodes ?? []).map((barcode) => typeof barcode === 'string' ? barcode : barcode.code ?? barcode.barcode)].includes(text));
            if (exact.length === 1) add(exact[0]);
        } catch (failure) { setError(failure); }
    };
    const save = async () => {
        setBusy(true); setError(null);
        try {
            if (!lines.length) throw new Error('Thêm hàng hóa để bắt đầu hồ sơ.');
            if (mode === 'rx' && (!prescriptionId || !patientId)) throw new Error('Chọn người bệnh và đơn thuốc trước khi tạo hồ sơ theo đơn.');
            const response = await runCommand('dispensing.create', { mode, patient_id: patientId, prescription_id: mode === 'rx' ? prescriptionId : null, lines: lines.map(({ product_id, prescription_line_id, packaging_id, quantity, unit }) => ({ product_id, quantity: decimalInput(quantity), unit, ...(prescription_line_id ? { prescription_line_id } : {}), ...(packaging_id ? { packaging_id } : {}) })) });
            const id = recordId(response?.data?.dispensing ?? response?.data);
            if (!id) throw new Error('Máy chủ chưa trả mã hồ sơ. Kiểm tra kết quả trước khi tạo lại.');
            navigate(`/modules/pharmacy/dispensings/${encodeURIComponent(id)}`);
        } catch (failure) { setError(failure); }
        finally { setBusy(false); }
    };
    const parsedScan = scanResult?.parsed ?? scanResult;
    return <div className="ph-pos-grid"><section className="ph-pos-catalog" aria-label="Tìm và chọn hàng hóa"><Card><div className="ph-section-heading"><strong>Tìm thuốc / hàng hóa</strong><Tag>F2 · Tìm hàng</Tag></div><Input.Search ref={searchRef} autoFocus size="large" allowClear value={scan} onChange={(event) => setScan(event.target.value)} onSearch={search} placeholder="Quét mã vạch, nhập tên hoặc mã hàng…" aria-label="Quét hoặc tìm hàng hóa" enterButton="Tìm" /><p className="ph-muted">{mode === 'rx' ? 'Dòng cấp theo đơn được tải từ đơn gốc. Đối chiếu lượng còn được cấp trước khi lưu.' : 'Mã khớp chính xác được thêm vào hồ sơ. Kết quả gần giống cần chọn và đối chiếu.'}</p></Card>
        {scanResult && <Card title="Đối chiếu mã vừa quét"><Alert type="info" showIcon message={scanResult.auto_added ? 'Đã thêm hàng khớp mã — chưa xác nhận lô thực lấy' : 'Chưa xác nhận lô thực lấy'} description={[parsedScan?.gtin, parsedScan?.batch_code ?? parsedScan?.lot, parsedScan?.expires_at ? `HSD ${dateLabel(parsedScan.expires_at)}` : null].filter(Boolean).join(' · ') || 'Không có hàng khớp mã. Kiểm tra nhãn và danh mục.'} /><p className="ph-muted">Kết quả mã hỗ trợ nhận diện. Chọn lô, hạn và lượng thực lấy riêng ở bước chuẩn bị.</p>{resourceRows(scanResult).map((row) => <Button key={`${recordId(row)}:${row.packaging_id ?? 'base'}`} disabled={mode === 'rx' || busy} onClick={() => add(row)}>{scanResult.auto_added ? 'Thêm lần nữa · ' : ''}{row.name}{row.packaging_name && ` · ${row.packaging_name}`}</Button>)}</Card>}
        <PharmacyError error={products.error} onRetry={products.reload} /><div className="ph-product-grid">{resourceRows(products.data).map((product) => <button type="button" className="ph-product-card" key={recordId(product)} onClick={() => add(product)} disabled={busy || mode === 'rx' || !can('dispensing.create')}><span className="ph-product-code">{product.code ?? product.sku}</span><strong>{product.name}</strong><span>{[product.strength, product.dosage_form, unitLabel(product.base_unit)].filter(Boolean).join(' · ')}</span><div className="ph-product-bottom"><strong>{money(product.price_minor)}</strong>{pharmacyFlag(product.requires_prescription) && <Tag>Rx</Tag>}</div></button>)}</div>{!products.loading && !resourceRows(products.data).length && <Card><Empty description={query ? 'Không có hàng phù hợp. Thử tên, hoạt chất hoặc mã khác.' : 'Danh mục chưa có hàng hóa được phép bán tại nhà thuốc.'} /></Card>}</section>
        <Card className="ph-cart-card" title="Hồ sơ đang lập" extra={<Tag>{lines.length} dòng</Tag>}><div className="ph-field"><label>Loại giao dịch</label><Select value={mode} disabled={busy} onChange={(value) => { setMode(value); setPrescriptionId(null); setLines([]); }} options={[{ value: 'otc', label: 'OTC / bán hàng thông thường' }, { value: 'rx', label: 'Cấp thuốc theo đơn' }]} /></div>
            <div className="ph-field"><label>Người bệnh / khách</label><Space.Compact style={{ width: '100%' }}><PharmacyRemoteSelect api={api} kind="patients" value={patientId} onChange={(value) => { setPatientId(value); if (mode === 'rx') { setPrescriptionId(null); setLines([]); } }} label="Tìm hồ sơ đã có" disabled={busy || !canReadPatient} aria-label="Chọn người bệnh" />{can('patient.save') && <Button disabled={busy} onClick={() => openCommand('patient.save', null, {}, { onCompleted: (response) => setPatientId(recordId(response.data)) })}>Thêm</Button>}</Space.Compact>{!canReadPatient && <p className="ph-muted">Cần quyền xem hồ sơ để chọn người bệnh. Có thể lập OTC không gắn hồ sơ.</p>}</div>
            {mode === 'rx' && <div className="ph-field"><label>Đơn thuốc</label><PharmacyRemoteSelect api={api} kind="prescriptions" params={{ patient_id: patientId }} value={prescriptionId} onChange={(value) => { setPrescriptionId(value); setLines([]); }} disabled={busy} label="Tìm đơn thuốc đã tiếp nhận" aria-label="Chọn đơn thuốc" /><PharmacyError error={prescription.error} onRetry={prescription.reload} /></div>}
            <Table rowKey="key" dataSource={lines} pagination={false} size="small" scroll={{ x: 440 }} locale={{ emptyText: 'Quét hoặc chọn hàng hóa để bắt đầu.' }} columns={[
                { title: 'Hàng hóa', render: (_, line) => <div><strong>{line.name}</strong><div className="ph-muted">{unitLabel(line.unit)}{line.packaging_name && ` · ${line.packaging_name} (${line.quantity_multiplier} ${unitLabel(line.base_unit)} / quy cách)`}</div></div> },
                { title: 'Số lượng', width: 120, render: (_, line, index) => <InputNumber stringMode min="0.000001" precision={6} formatter={quantityFormatter} controls={false} disabled={busy} aria-label={`Số lượng dòng ${index + 1}`} value={line.quantity} onChange={(value) => setLines((items) => items.map((item) => item.key === line.key ? { ...item, quantity: String(value ?? '') } : item))} style={{ width: '100%' }} /> },
                { title: '', width: 40, render: (_, line, index) => <Button type="text" danger disabled={busy} aria-label={`Xóa dòng ${index + 1}`} onClick={() => setLines((items) => items.filter((item) => item.key !== line.key))}>×</Button> },
            ]} />
            <PharmacyError error={error} /><Alert type="info" showIcon message="Giá và điều kiện bán do máy chủ xác nhận" description="Sau khi bấm Lưu, hồ sơ được giữ phía máy chủ. Tạo hồ sơ chưa thu tiền, xuất kho hoặc xác nhận đã giao hàng." />
            <Button type="primary" size="large" block className="ph-main-action" disabled={!lines.length || !can('dispensing.create')} loading={busy} onClick={save}>Lưu hồ sơ & chuẩn bị hàng</Button>
            <Typography.Paragraph className="ph-muted">Thông tin trên màn hình này không được lưu vào bộ nhớ trình duyệt.</Typography.Paragraph>
        </Card>
    </div>;
}
