import { useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Form from 'antd/es/form';
import Space from 'antd/es/space';
import { PharmacyTable, StatusTag } from './PharmacyCommon';
import { usePharmacyWorkspace } from '../state/PharmacyWorkspaceContext';
import usePharmacyResource from '../hooks/usePharmacyResource';
import { dateLabel, recordId } from '../utils/pharmacyData';
import { parseProductImport } from '../utils/pharmacyImport';

const template = 'Mã hàng,Tên hàng,Đơn vị,Giá bán,Bán theo đơn,Kiểm soát đặc biệt,Chuỗi lạnh\nMA-MAU,Hàng hóa mẫu,vien,1000,Không,Không,Không\n';
export default function PharmacyCatalogTools() {
    const { api, pharmacyId, revision, can, openCommand } = usePharmacyWorkspace();
    const [preview, setPreview] = useState(null);
    const [previewRows, setPreviewRows] = useState([]);
    const imports = usePharmacyResource(api, 'imports', {}, Boolean(pharmacyId && can('import.preview')), revision);
    const commit = (row) => openCommand('import.commit', row, { import_id: recordId(row) }, { title: 'Áp dụng danh mục đã xem trước', description: `Tạo mới ${row.row_count} hàng. Nếu mã hàng vừa thay đổi, toàn bộ lần nhập sẽ bị từ chối để đối chiếu lại.`, fields: [], onCompleted: () => setPreview(null) });
    return <Card title="Nhập danh mục có kiểm tra" className="ph-subsection"><Space wrap>
        <Button href={`data:text/csv;charset=utf-8,${encodeURIComponent('\uFEFF' + template)}`} download="mau-danh-muc-nha-thuoc.csv">Tải bảng mẫu</Button>
        {can('import.preview') && <Button onClick={() => openCommand('import.preview', null, {}, { title: 'Xem trước danh mục nhập', width: 760, description: 'Chọn CSV hoặc dán bảng từ Excel có dòng tiêu đề. Tối đa 100 hàng; không tự ghi đè mã trùng. Chỉ dữ liệu danh mục, không nhập hồ sơ người bệnh.', fields: [{ name: 'source_reference', label: 'Nguồn danh mục / phiên bản', required: true }, { name: 'table', label: 'Bảng danh mục', type: 'textarea', maxLength: 200000, required: true, help: 'Đơn vị dùng mã trong chuỗi như vien, hop, chai. Giá là số nguyên đồng, không dấu phân cách.' }], renderFields: ({ form }) => <Form.Item label="Chọn tệp CSV / TSV trên thiết bị"><input type="file" accept=".csv,.tsv,text/csv,text/tab-separated-values" aria-label="Chọn tệp danh mục" onChange={async (event) => { const file = event.target.files?.[0]; if (!file) return; if (file.size > 200000) { form.setFields([{ name: 'table', errors: ['Tệp tối đa 200 KB, tối đa 100 hàng.'] }]); return; } try { form.setFieldValue('table', await file.text()); form.setFieldValue('source_reference', file.name); } catch { form.setFields([{ name: 'table', errors: ['Không đọc được tệp trên thiết bị.'] }]); } }} /></Form.Item>, normalize: ({ table, source_reference }) => { const rows = parseProductImport(table); setPreviewRows(rows); return { source_reference, rows }; }, onCompleted: (response) => setPreview(response.data) })}>Chọn CSV / dán bảng & kiểm tra</Button>}
        {can('unit.save') && <Button onClick={() => openCommand('unit.save', null, {}, { title: 'Thêm đơn vị dùng trong chuỗi', fields: [{ name: 'code', label: 'Mã đơn vị', required: true, help: 'Mã không dấu, được dùng nhất quán trong sổ kho.' }, { name: 'name', label: 'Tên hiển thị', required: true }] })}>Thêm đơn vị</Button>}
    </Space>{preview && <div className="ph-subsection"><Alert type={preview.can_commit ? 'success' : 'warning'} showIcon message={preview.can_commit ? `Đã kiểm tra ${preview.row_count} hàng — chưa áp dụng` : 'Cần sửa bảng trước khi áp dụng'} description={(preview.errors ?? []).map((error) => <div key={error.row}>Dòng {error.row}: {Object.entries(error.fields ?? {}).map(([field, messages]) => `${field}: ${messages.join(', ')}`).join(' · ')}</div>)} /><PharmacyTable resource={{ data: previewRows }} rowKey="code" columns={[{ title: 'Mã hàng', dataIndex: 'code' }, { title: 'Tên hàng', dataIndex: 'name' }, { title: 'Đơn vị', dataIndex: 'base_unit' }, { title: 'Giá bán', dataIndex: 'price_minor' }]} />{preview.can_commit && can('import.commit') && <Button type="primary" onClick={() => commit(preview)} style={{ marginTop: 12 }}>Xác nhận tạo {preview.row_count} hàng</Button>}</div>}
    <PharmacyTable resource={imports} columns={[
        { title: 'Số hàng', dataIndex: 'row_count' }, { title: 'Tình trạng', render: (_, row) => <StatusTag value={row.status} /> }, { title: 'Tạo lúc', render: (_, row) => dateLabel(row.created_at) }, { title: 'Thao tác', render: (_, row) => row.status === 'preview' && !(row.errors ?? []).length && can('import.commit') && <Button onClick={() => commit(row)}>Áp dụng bản xem trước</Button> },
    ]} /></Card>;
}
