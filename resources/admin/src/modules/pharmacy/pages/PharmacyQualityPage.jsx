import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Space from 'antd/es/space';
import { PharmacyError, PharmacyTable, StatusTag } from '../components/PharmacyCommon';
import usePharmacyResource from '../hooks/usePharmacyResource';
import { usePharmacyWorkspace } from '../state/PharmacyWorkspaceContext';
import { dateLabel, quantity, recordId } from '../utils/pharmacyData';

export default function PharmacyQualityPage() {
    const { api, pharmacyId, revision, can, openCommand } = usePharmacyWorkspace();
    const resource = usePharmacyResource(api, 'quality', {}, Boolean(pharmacyId), revision);
    const data = resource.data ?? {};
    return <><PharmacyError error={resource.error} onRetry={resource.reload} /><Card title="Chất lượng theo lô" extra={<Space wrap>{can('quality.hold') && <Button onClick={() => openCommand('quality.hold')}>Giữ / cách ly lô</Button>}{can('recall.create') && <Button danger onClick={() => openCommand('recall.create')}>Mở thu hồi</Button>}{can('temperature.record') && <Button onClick={() => openCommand('temperature.record')}>Ghi nhiệt độ</Button>}</Space>}>
        <PharmacyTable resource={{ data: data.holds ?? [], loading: resource.loading }} columns={[
            { title: 'Lô', dataIndex: 'batch_id' }, { title: 'Lý do', dataIndex: 'reason' }, { title: 'Loại giữ', dataIndex: 'reason_code' }, { title: 'Trạng thái', render: (_, row) => <StatusTag value={row.status} /> },
            { title: 'Thao tác', render: (_, row) => row.status === 'active' && row.reason_code !== 'recall' && can('quality.release') ? <Button onClick={() => openCommand('quality.release', row, { hold_id: recordId(row) })}>Xử lý lý do giữ</Button> : null },
        ]} empty="Không có lý do giữ hàng được ghi nhận." />
    </Card><Card title="Công việc thu hồi tại nhà thuốc" className="ph-subsection"><PharmacyTable resource={{ data: data.recalls ?? [] }} columns={[
        { title: 'Căn cứ', dataIndex: 'reference' }, { title: 'Lô', dataIndex: 'batch_id' }, { title: 'Lý do', dataIndex: 'reason' }, { title: 'Đã cách ly', render: (_, row) => quantity(row.isolated_quantity) }, { title: 'Trạng thái', render: (_, row) => <StatusTag value={row.status} /> },
        { title: 'Thao tác', render: (_, row) => row.status === 'pending' && can('recall.confirm') ? <Button onClick={() => openCommand('recall.confirm', row, { task_id: recordId(row) })}>Xác nhận số cách ly</Button> : null },
    ]} empty="Chưa có công việc thu hồi tại nhà thuốc." /></Card><Card title="Ghi nhận bảo quản thủ công" className="ph-subsection"><PharmacyTable resource={{ data: data.temperatures ?? [] }} columns={[
        { title: 'Khu vực', dataIndex: 'zone' }, { title: 'Nhiệt độ', render: (_, row) => `${row.temperature} °C` }, { title: 'Độ ẩm', render: (_, row) => row.humidity == null ? '—' : `${row.humidity}%` }, { title: 'Đo lúc', render: (_, row) => dateLabel(row.measured_at) },
    ]} empty="Chưa có ghi nhận. Dữ liệu thủ công không thay thế nghiệm thu chuỗi lạnh." /></Card></>;
}
