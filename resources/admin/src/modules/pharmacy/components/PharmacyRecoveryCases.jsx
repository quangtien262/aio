import Button from 'antd/es/button';
import Card from 'antd/es/card';
import { useNavigate } from 'react-router-dom';
import { PharmacyTable, StatusTag } from './PharmacyCommon';
import usePharmacyResource from '../hooks/usePharmacyResource';
import { usePharmacyWorkspace } from '../state/PharmacyWorkspaceContext';

export default function PharmacyRecoveryCases() {
    const { api, pharmacyId, revision, can, openCommand } = usePharmacyWorkspace();
    const data = usePharmacyResource(api, 'recovery', { status: 'manual_review' }, Boolean(pharmacyId), revision);
    const navigate = useNavigate();
    return <Card title="Giao dịch cần đối soát thủ công" className="ph-subsection"><p className="ph-muted">Đối chiếu chứng từ và kết quả nguồn trước khi xử lý. Xử lý hồ sơ này không tự thu tiền, trả tồn hoặc xác nhận bàn giao.</p><PharmacyTable resource={data} columns={[
        { title: 'Trạng thái', render: (_, row) => <StatusTag value={row.status} /> }, { title: 'Mã cần xử lý', dataIndex: 'error_code' }, { title: 'Thao tác', render: (_, row) => <><Button onClick={() => navigate(`/modules/pharmacy/dispensings/${encodeURIComponent(row.dispensing_public_id)}`)}>Xem hồ sơ</Button>{can('recovery.resolve') && <Button onClick={() => openCommand('recovery.resolve', null, { case_id: row.id, expected_version: row.dispensing_version }, { title: 'Ghi nhận kết quả đối soát thủ công', description: 'Ghi rõ chứng từ, kết quả đã đối chiếu và trách nhiệm xử lý. Máy chủ kiểm tra điều kiện trước khi khép hồ sơ ngoại lệ.', fields: [{ name: 'reason', label: 'Căn cứ và kết quả đối soát', type: 'textarea', required: true }] })}>Ghi kết quả đối soát</Button>}</> },
    ]} /></Card>;
}
