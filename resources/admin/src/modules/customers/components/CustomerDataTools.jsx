import { useEffect, useRef, useState } from 'react';
import CloudUploadOutlined from '@ant-design/icons/CloudUploadOutlined';
import DownloadOutlined from '@ant-design/icons/DownloadOutlined';
import FileTextOutlined from '@ant-design/icons/FileTextOutlined';
import ImportOutlined from '@ant-design/icons/ImportOutlined';
import ExportOutlined from '@ant-design/icons/ExportOutlined';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import SafetyCertificateOutlined from '@ant-design/icons/SafetyCertificateOutlined';
import CheckCircleOutlined from '@ant-design/icons/CheckCircleOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Popconfirm from 'antd/es/popconfirm';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Steps from 'antd/es/steps';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { customerDate, customerId, customerRows, mapCustomerImport, parseCustomerCsv } from '../utils/customerData';
import '../styles/customers-data.css';

const { Title, Text, Paragraph } = Typography;
const importFields = [{ value: '', label: 'Bỏ qua cột' }, { value: 'name', label: 'Tên khách hàng' }, { value: 'code', label: 'Mã khách hàng' }, { value: 'type', label: 'Loại person / company' }, { value: 'phone', label: 'Điện thoại' }, { value: 'email', label: 'Email' }, { value: 'legal_name', label: 'Tên pháp lý' }, { value: 'tax_code', label: 'Mã số thuế' }, { value: 'birthday', label: 'Ngày sinh YYYY-MM-DD' }, { value: 'notes', label: 'Ghi chú' }];
const exportStatuses = { ready: 'Sẵn sàng', completed: 'Sẵn sàng', pending: 'Đang chuẩn bị', queued: 'Đang chờ', failed: 'Không thành công' };

export default function CustomerDataTools({ api, bookId, can, onChanged }) {
    const [parsed, setParsed] = useState(null);
    const [filename, setFilename] = useState('');
    const [mapping, setMapping] = useState({});
    const [report, setReport] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);
    const [exports, setExports] = useState([]);
    const [exporting, setExporting] = useState(false);
    const generation = useRef(0);
    const mounted = useRef(true);
    const prefix = `books/${bookId}`;
    const canImport = can('customers.import') && can('customers.sensitive');
    const canExport = can('customers.export') && can('customers.sensitive');
    useEffect(() => { mounted.current = true; return () => { mounted.current = false; generation.current += 1; }; }, []);
    const selectFile = async (event) => {
        const file = event.target.files?.[0];
        if (!file) return;
        setError(null); setReport(null);
        try {
            if (file.size > 5 * 1024 * 1024) throw new Error('File tối đa 5 MB.');
            const next = parseCustomerCsv(await file.text());
            setParsed(next); setFilename(file.name);
            const known = new Set(importFields.map((item) => item.value));
            setMapping(Object.fromEntries(next.headers.map((header) => [header, known.has(header.trim().toLowerCase()) ? header.trim().toLowerCase() : ''])));
        } catch (failure) { setError(failure.message); setParsed(null); }
        event.target.value = '';
    };
    const runImport = async (commit) => {
        setBusy(true); setError(null);
        try {
            const rows = mapCustomerImport(parsed, mapping);
            const result = await api[commit ? 'command' : 'preview'](`${prefix}/imports`, { rows, commit });
            setReport(result.data);
            if (result.data?.committed) await onChanged?.();
        } catch (failure) { setError(failure.message); }
        finally { setBusy(false); }
    };
    const requestExport = async () => {
        setExporting(true); setError(null);
        const request = ++generation.current;
        try {
            let item = (await api.command(`${prefix}/exports`, {})).data;
            if (!mounted.current || request !== generation.current) return;
            setExports((items) => [item, ...items]);
            for (let attempt = 0; attempt < 15 && !['ready', 'completed', 'failed'].includes(item.status); attempt += 1) {
                await new Promise((resolve) => window.setTimeout(resolve, 1500));
                if (!mounted.current || request !== generation.current) return;
                item = (await api.read(`exports/${customerId(item)}`)).data;
                if (mounted.current) setExports((items) => items.map((saved) => customerId(saved) === customerId(item) ? item : saved));
            }
            if (!['ready', 'completed', 'failed'].includes(item.status)) setError('File vẫn đang được chuẩn bị. Dùng Kiểm tra để xem kết quả sau.');
        } catch (failure) { if (mounted.current) setError(failure.message); }
        finally { if (mounted.current) setExporting(false); }
    };
    const refreshExport = async (item) => {
        try {
            const result = await api.read(`exports/${customerId(item)}`);
            setExports((items) => items.map((saved) => customerId(saved) === customerId(item) ? result.data : saved));
        } catch (failure) { setError(failure.message); }
    };
    const downloadExport = async (item) => {
        try { await api.download(`exports/${customerId(item)}/download`, `khach-hang-${customerId(item).slice(-8)}.csv`); }
        catch (failure) { setError(failure.message); }
    };
    const resetImport = () => { setParsed(null); setFilename(''); setReport(null); setMapping({}); };

    return <div className="customers-data-workspace">
        {error && <Alert type="error" showIcon message={error} />}
        {!can('customers.sensitive') && <Alert type="info" showIcon message="Cần quyền thông tin riêng tư để nhập hoặc xuất dữ liệu liên hệ." />}
        <div className={`customers-data-layout${!canImport || !canExport ? ' customers-data-layout-single' : ''}`}>
            {canImport && <Card className="customers-data-panel customers-data-import" title={<div className="customers-data-panel-heading"><span className="customers-data-heading-icon"><ImportOutlined /></span><div><span className="customers-data-kicker">ĐƯA DỮ LIỆU VÀO DANH BẠ</span><Title level={4}>Nhập khách hàng từ CSV</Title></div></div>} extra={<Button type="text" icon={<ReloadOutlined />} disabled={busy} onClick={resetImport}>Làm lại</Button>}>
                <div className="customers-data-steps"><Steps size="small" responsive current={report?.committed ? 3 : report ? 2 : parsed ? 1 : 0} items={[{ title: 'Chọn file' }, { title: 'Ánh xạ cột' }, { title: 'Kiểm tra' }, { title: 'Nhập dữ liệu' }]} /></div>
                <div className="customers-data-import-body">
                    <label className={`customers-data-upload${parsed ? ' customers-data-upload-selected' : ''}${busy ? ' customers-data-upload-disabled' : ''}`}>
                        <input type="file" accept=".csv,text/csv" aria-label="Chọn file CSV" onChange={selectFile} disabled={busy} />
                        <span className="customers-data-upload-icon">{parsed ? <FileTextOutlined /> : <CloudUploadOutlined />}</span>
                        <strong>{parsed ? filename : 'Bắt đầu với file khách hàng của bạn'}</strong>
                        <span>{parsed ? `${parsed.records.length} dòng dữ liệu · Chọn để thay file khác` : 'Chọn file CSV để xem trước và kiểm tra trước khi nhập'}</span>
                        <span className="customers-data-upload-button">{parsed ? 'Thay file CSV' : 'Chọn file CSV'}</span>
                        <span className="customers-data-file-specs"><span>CSV UTF-8</span><span>Tối đa 1.000 dòng</span><span>Dung lượng ≤ 5 MB</span></span>
                    </label>
                    {parsed ? <div className="customers-data-mapping">
                        <div className="customers-data-section-heading"><div><Title level={5}>Ánh xạ cột dữ liệu</Title><Text type="secondary">Chọn trường tương ứng cho từng cột trong file.</Text></div><Tag>{parsed.headers.length} cột</Tag></div>
                        <Row gutter={[16, 16]}>{parsed.headers.map((header) => <Col xs={24} sm={12} xl={8} key={header}><div className="customers-data-field-label">{header}</div><Select aria-label={`Ánh xạ cột ${header}`} style={{ width: '100%' }} value={mapping[header]} options={importFields} disabled={busy} onChange={(value) => { setMapping((current) => ({ ...current, [header]: value })); setReport(null); }} /></Col>)}</Row>
                        <div className="customers-data-section-heading customers-data-preview-heading"><div><Title level={5}>Xem trước dữ liệu</Title><Text type="secondary">5 dòng đầu tiên trong file đã chọn.</Text></div></div>
                        <Table className="customers-data-preview-table" rowKey="_row" size="small" scroll={{ x: 'max-content' }} dataSource={parsed.records.slice(0, 5).map((record, index) => ({ _row: index, ...Object.fromEntries(parsed.headers.map((header, column) => [header, record[column]])) }))} columns={parsed.headers.map((header) => ({ title: header, dataIndex: header }))} pagination={false} />
                        <div className="customers-data-import-actions"><span><SafetyCertificateOutlined /> Dữ liệu chỉ được ghi sau khi xác nhận.</span><Space wrap><Button loading={busy} onClick={() => runImport(false)}>Kiểm tra dữ liệu</Button><Popconfirm title="Nhập các hồ sơ đã kiểm tra?" onConfirm={() => runImport(true)}><Button type="primary" disabled={!report?.valid || Boolean(report?.committed) || busy}>Xác nhận nhập</Button></Popconfirm></Space></div>
                    </div> : <div className="customers-data-import-notes"><span className="customers-data-note-icon"><CheckCircleOutlined /></span><div><strong>Xem trước rồi mới nhập</strong><p>Ánh xạ cột, kiểm tra lỗi và rà soát thông tin có thể trùng trước khi xác nhận tạo hồ sơ.</p></div></div>}
                    {report && <div className="customers-data-report">
                        <Alert type={report.committed ? 'success' : report.valid ? 'info' : 'warning'} showIcon message={report.committed ? 'Đã nhập dữ liệu' : report.valid ? 'Dữ liệu hợp lệ để nhập' : 'Cần xử lý dữ liệu trước khi nhập'} />
                        <div className="customers-data-report-stats"><div><span>Tổng dòng</span><strong>{report.row_count}</strong></div><div><span>Dòng hợp lệ</span><strong>{report.accepted_count}</strong></div><div><span>Đã tạo</span><strong>{report.created_count ?? 0}</strong></div></div>
                        <Table size="small" scroll={{ x: 420 }} rowKey={(item) => item.row} dataSource={customerRows(report.errors)} pagination={{ pageSize: 10 }} columns={[{ title: 'Dòng', dataIndex: 'row', width: 70 }, { title: 'Lỗi cần sửa', render: (_, item) => Object.values(item.errors ?? {}).flat().join(' · ') || item.message }]} locale={{ emptyText: <span className="customers-data-no-errors"><CheckCircleOutlined /> Không có lỗi dòng được báo.</span> }} />
                        {customerRows(report.duplicates).length > 0 && <Alert type="warning" showIcon message={`${report.duplicates.length} trường hợp có thể trùng. Kiểm tra tại mục Hồ sơ trùng trước khi tiếp tục.`} />}
                    </div>}
                </div>
            </Card>}
            <div className="customers-data-rail">
                {canExport && <Card className="customers-data-panel customers-data-export" title={<div className="customers-data-panel-heading"><span className="customers-data-heading-icon"><ExportOutlined /></span><div><span className="customers-data-kicker">DỮ LIỆU THEO QUYỀN</span><Title level={4}>Xuất danh bạ</Title></div></div>}>
                    <div className="customers-data-export-content"><span className="customers-data-private-badge"><SafetyCertificateOutlined /> File riêng của bạn</span><Paragraph type="secondary">Chuẩn bị bản CSV từ dữ liệu bạn được phép truy cập trong danh bạ hiện tại.</Paragraph><div className="customers-data-export-format"><FileTextOutlined /><div><strong>Định dạng CSV</strong><span>Mã, loại, tên, điện thoại, email, trạng thái</span></div></div><Button block type="primary" icon={<DownloadOutlined />} loading={exporting} onClick={requestExport}>Chuẩn bị file CSV</Button><div className="customers-data-export-privacy"><SafetyCertificateOutlined /> Quyền truy cập được kiểm tra lại khi tải file.</div></div>
                    <div className="customers-data-export-history"><div className="customers-data-section-heading"><Title level={5}>Lần xuất trong phiên này</Title>{exports.length > 0 && <Tag>{exports.length}</Tag>}</div>{exports.length === 0 ? <div className="customers-data-export-empty"><FileTextOutlined /><strong>Chưa có file xuất</strong><span>File đang chuẩn bị và liên kết tải sẽ xuất hiện tại đây.</span></div> : <div className="customers-data-export-list">{exports.map((item) => <div className="customers-data-export-item" key={customerId(item)}><div className="customers-data-export-item-top"><span className="customers-data-export-file-icon"><FileTextOutlined /></span><div><strong>Danh bạ khách hàng</strong><span>{customerDate(item.created_at)}</span></div></div><Tag color={['ready', 'completed'].includes(item.status) ? 'green' : item.status === 'failed' ? 'red' : 'blue'}>{exportStatuses[item.status] ?? item.status}</Tag><Space wrap><Button size="small" icon={<ReloadOutlined />} onClick={() => refreshExport(item)}>Kiểm tra</Button>{['ready', 'completed'].includes(item.status) && <Button size="small" type="primary" icon={<DownloadOutlined />} onClick={() => downloadExport(item)}>Tải CSV</Button>}</Space></div>)}</div>}</div>
                </Card>}
                {canImport && <Card className="customers-data-panel customers-data-guide"><div className="customers-data-guide-heading"><SafetyCertificateOutlined /><Title level={5}>Chuẩn bị dữ liệu đúng cách</Title></div><ol><li><span>01</span><div><strong>Tên cột ở dòng đầu</strong><p>Mỗi dòng tiếp theo là một khách hàng. Chọn đúng trường tại bước ánh xạ.</p></div></li><li><span>02</span><div><strong>Giữ nguyên thông tin liên hệ</strong><p>Định dạng điện thoại và mã khách là văn bản để giữ số 0 ở đầu.</p></div></li><li><span>03</span><div><strong>Tôn trọng đồng ý liên hệ</strong><p>Nhập hồ sơ không tự ghi nhận đồng ý nhận quảng cáo.</p></div></li></ol></Card>}
            </div>
        </div>
    </div>;
}
