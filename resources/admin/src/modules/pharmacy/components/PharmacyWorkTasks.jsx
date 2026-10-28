import Button from 'antd/es/button';
import Card from 'antd/es/card';
import { useNavigate } from 'react-router-dom';
import { PharmacyTable, StatusTag } from './PharmacyCommon';
import usePharmacyResource from '../hooks/usePharmacyResource';
import { usePharmacyWorkspace } from '../state/PharmacyWorkspaceContext';
import { dateLabel, recordId } from '../utils/pharmacyData';

export default function PharmacyWorkTasks() {
    const { api, pharmacyId, revision } = usePharmacyWorkspace();
    const tasks = usePharmacyResource(api, 'tasks', {}, Boolean(pharmacyId), revision);
    const navigate = useNavigate();
    return <Card title="Công việc được phân công" className="ph-subsection"><PharmacyTable resource={tasks} columns={[
        { title: 'Bước', render: (_, row) => ({ prepare: 'Chuẩn bị hàng', review: 'Kiểm tra chuyên môn', payment: 'Thu tiền', handover: 'Bàn giao' }[row.stage] ?? row.stage) },
        { title: 'Người nhận', render: (_, row) => row.assigned_name ?? `Tài khoản ${row.assigned_to}` },
        { title: 'Ưu tiên', render: (_, row) => row.priority ? `Mức ${row.priority}` : 'Thường' },
        { title: 'Hạn xử lý', render: (_, row) => dateLabel(row.due_at) },
        { title: 'Hồ sơ hiện tại', render: (_, row) => <StatusTag value={row.dispensing_status} /> },
        { title: 'Thao tác', render: (_, row) => <Button disabled={!row.dispensing_public_id} onClick={() => navigate(`/modules/pharmacy/dispensings/${encodeURIComponent(row.dispensing_public_id)}`)}>Mở hồ sơ</Button> },
    ]} rowKey={recordId} /><p className="ph-muted">Bước được phân công có thể khác trạng thái hồ sơ hiện tại. Luôn tải bản mới nhất trước khi xử lý.</p></Card>;
}
