import { useState } from 'react';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Drawer from 'antd/es/drawer';
import { PharmacyTable, StatusTag } from './PharmacyCommon';
import usePharmacyResource from '../hooks/usePharmacyResource';
import { usePharmacyWorkspace } from '../state/PharmacyWorkspaceContext';
import { dateLabel, money, quantity, unitLabel } from '../utils/pharmacyData';

export default function PharmacyOperationalHistory({ kind }) {
    const { api, pharmacyId, revision, can, openCommand } = usePharmacyWorkspace();
    const data = usePharmacyResource(api, kind, {}, Boolean(pharmacyId), revision);
    const [detail, setDetail] = useState(null);
    const replenish = kind === 'replenishment';
    return <Card className="ph-subsection" title={replenish ? 'Đề xuất bổ sung hàng' : 'Sổ thu / chi quầy'} extra={replenish && can('replenishment.propose') && <Button onClick={() => openCommand('replenishment.propose', null, {}, { title: 'Tính đề xuất bổ sung', description: 'Dựa trên lượng đã bàn giao 30 ngày, tồn khả dụng, hàng đang chuyển và đơn mua đang mở. Đây là đề xuất để đối chiếu; chưa tạo đơn mua hay giữ hàng.', fields: [{ name: 'coverage_days', label: 'Số ngày cần đáp ứng', type: 'integer', min: 1, max: 90, required: true }, { name: 'product_ids', label: 'Hàng cần tính đề xuất', kind: 'products', multiple: true, required: true }], initial: { coverage_days: '14' } })}>Tính đề xuất</Button>}><PharmacyTable resource={data} columns={replenish ? [
        { title: 'Số ngày đáp ứng', dataIndex: 'coverage_days' }, { title: 'Dữ liệu đến', render: (_, row) => dateLabel(row.as_of) }, { title: 'Trạng thái', render: (_, row) => <StatusTag value={row.status} /> }, { title: 'Thao tác', render: (_, row) => <Button onClick={() => setDetail(row)}>Đối chiếu đề xuất</Button> },
    ] : [
        { title: 'Chiều tiền', render: (_, row) => row.direction === 'in' ? 'Nộp vào quầy' : 'Chi khỏi quầy' }, { title: 'Số tiền', render: (_, row) => money(row.amount_minor) }, { title: 'Người ghi', dataIndex: 'actor_id' }, { title: 'Ghi nhận lúc', render: (_, row) => dateLabel(row.created_at) },
    ]} /><Drawer open={Boolean(detail)} title="Đối chiếu đề xuất bổ sung" width={900} onClose={() => setDetail(null)} destroyOnHidden rootClassName="ph-drawer"><p>Số liệu tại {dateLabel(detail?.as_of)}. Kiểm tra lại tồn và nhu cầu trước khi lập yêu cầu mua.</p><PharmacyTable resource={{ data: detail?.lines ?? [] }} rowKey="product_id" columns={[
        { title: 'Hàng hóa', dataIndex: 'name' }, { title: 'Đã bàn giao 30 ngày', render: (_, row) => quantity(row.sold_30_days) }, { title: 'Khả dụng', render: (_, row) => quantity(row.available) }, { title: 'Đang chuyển', render: (_, row) => quantity(row.incoming) }, { title: 'Đang đặt mua', render: (_, row) => quantity(row.on_order) }, { title: 'Đề xuất', render: (_, row) => <strong>{quantity(row.proposed_quantity)} {unitLabel(row.unit)}</strong> },
    ]} /></Drawer></Card>;
}
