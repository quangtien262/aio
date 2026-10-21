import { useMemo, useState } from 'react';
import ApartmentOutlined from '@ant-design/icons/ApartmentOutlined';
import BankOutlined from '@ant-design/icons/BankOutlined';
import BuildOutlined from '@ant-design/icons/BuildOutlined';
import DatabaseOutlined from '@ant-design/icons/DatabaseOutlined';
import EditOutlined from '@ant-design/icons/EditOutlined';
import SearchOutlined from '@ant-design/icons/SearchOutlined';
import Button from 'antd/es/button';
import Empty from 'antd/es/empty';
import Input from 'antd/es/input';
import Table from 'antd/es/table';
import { manufacturingOrganizationRows } from '../utils/manufacturingOrganization.js';
import '../styles/manufacturing-organization.css';

const id = (row) => row?.public_id ?? row?.id;
const same = (left, right) => String(left ?? '') === String(right ?? '');
const format = (value) => new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(Number(value) || 0);
const kinds = { company: { label: 'Doanh nghiệp', icon: BankOutlined }, factory: { label: 'Nhà máy', icon: BuildOutlined }, workshop: { label: 'Phân xưởng', icon: ApartmentOutlined }, line: { label: 'Chuyền', icon: DatabaseOutlined } };

export default function ManufacturingOrganization({ companies, groups, factoryId, can, paused, create, onOpen, onSelectFactory }) {
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [pageSize, setPageSize] = useState(25);
    const rows = useMemo(() => manufacturingOrganizationRows(companies, groups, search), [companies, groups, search]);
    const currentFactory = groups.find((group) => same(id(group.factory), factoryId))?.factory;
    const currentCompany = companies.find((company) => same(id(company), currentFactory?.company_id)) ?? (companies.length === 1 ? companies[0] : null);
    const workshopCount = groups.reduce((sum, group) => sum + group.workshops.length, 0);
    const lineCount = groups.reduce((sum, group) => sum + group.otherLines.length + group.workshops.reduce((count, item) => count + item.lines.length, 0), 0);
    const selectedIndex = rows.findIndex((row) => row.kind === 'factory' && same(row.factoryId, factoryId));
    const selectedPage = selectedIndex >= 0 ? Math.floor(selectedIndex / pageSize) + 1 : null;
    const actions = (row) => {
        if (row.kind === 'company') return can('company.update', row.record) && <Button type="text" size="small" icon={<EditOutlined />} aria-label={`Sửa thông tin doanh nghiệp ${row.record.name}`} disabled={paused} onClick={() => onOpen('company.update', row.record)}>Sửa</Button>;
        if (row.kind === 'factory') return same(row.factoryId, factoryId)
            ? <div className="mfg-org-row-actions">{create('workshop.create', 'Tạo phân xưởng')}{create('line.create', 'Tạo chuyền')}</div>
            : <Button size="small" type="link" aria-label={`Chọn nhà máy ${row.record.name}`} onClick={() => onSelectFactory(row.factoryId)}>Chọn nhà máy</Button>;
        if (row.kind === 'workshop' && same(row.factoryId, factoryId) && can('line.create')) return <Button size="small" type="text" disabled={paused} aria-label={`Thêm chuyền tại ${row.record.name}`} onClick={() => onOpen('line.create', null, { initial: { workshop_id: id(row.record) } })}>Thêm chuyền</Button>;
        return null;
    };
    const columns = [
        { title: 'Đơn vị / Mã', key: 'unit', width: 290, render: (_, row) => {
            const Icon = kinds[row.kind].icon;
            const selected = row.kind === 'factory' && same(row.factoryId, factoryId);
            return <div className={`mfg-org-unit mfg-org-unit-${row.kind}`} style={{ paddingLeft: row.depth * 12 }}><Icon /><div><strong title={row.record.name}>{row.record.name}</strong><small><span className="mfg-org-code" title={row.record.code}>{row.record.code}</span>{selected && <span className="mfg-org-selected-label">Đang chọn</span>}</small></div></div>;
        } },
        { title: 'Loại đơn vị', key: 'kind', width: 100, render: (_, row) => <span className="mfg-org-kind">{kinds[row.kind].label}</span> },
        { title: 'Trực thuộc', dataIndex: 'parent', width: 200, ellipsis: true },
        { title: 'Quy mô / Năng lực', key: 'detail', width: 145, render: (_, row) => <span className="mfg-org-detail">{row.kind === 'line' ? `${format(row.detail)} phút / ca` : row.detail}</span> },
        { title: 'Thao tác', key: 'actions', width: 225, render: (_, row) => actions(row) },
    ];
    return <section className="mfg-settings-organization">
        <div className="mfg-org-toolbar"><div><h4>Cơ cấu sản xuất</h4><p>{companies.length} doanh nghiệp · {groups.length} nhà máy · {workshopCount} phân xưởng · {lineCount} chuyền</p></div><div className="mfg-settings-card-actions">{create('company.create', 'Tạo công ty')}{create('factory.create', 'Tạo nhà máy', true, currentCompany ? { company_id: id(currentCompany), timezone: currentCompany.timezone } : undefined)}</div></div>
        <div className="mfg-org-table-wrap" role="region" aria-label="Bảng cơ cấu sản xuất">
            <div className="mfg-org-table-toolbar"><Input prefix={<SearchOutlined />} aria-label="Tìm trong cơ cấu" placeholder="Tìm đơn vị, mã, phân xưởng hoặc chuyền…" value={search} onChange={(event) => { setSearch(event.target.value); setPage(1); }} allowClear /><div className="mfg-org-table-navigation"><span className="mfg-org-table-scope">Toàn bộ đơn vị được cấp quyền</span>{selectedPage && selectedPage !== page && <Button type="link" size="small" onClick={() => setPage(selectedPage)}>Tới nhà máy đang chọn</Button>}</div><span className="mfg-org-mobile-hint">Vuốt ngang để xem các cột còn lại</span></div>
            <Table size="small" tableLayout="fixed" rowKey="key" columns={columns} dataSource={rows} scroll={{ x: 960 }} rowClassName={(row) => `mfg-org-row mfg-org-row-${row.kind}${row.kind === 'factory' && same(row.factoryId, factoryId) ? ' is-selected' : ''}`} onRow={(row) => ({ 'data-unit-kind': row.kind, 'data-unit-id': row.key, 'data-factory-id': row.factoryId ?? undefined })} pagination={{ current: page, pageSize, pageSizeOptions: [25, 50, 100], showSizeChanger: true, hideOnSinglePage: true, showTotal: (total) => `${total} đơn vị`, onChange: (next, size) => { setPage(next); setPageSize(size); } }} locale={{ emptyText: <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description={search.trim() ? 'Không tìm thấy cơ cấu phù hợp' : 'Chưa có cơ cấu sản xuất'}>{search.trim() && <Button onClick={() => { setSearch(''); setPage(1); }}>Xóa tìm kiếm</Button>}</Empty> }} />
        </div>
        <p className="mfg-org-scope">Chọn nhà máy để tạo phân xưởng hoặc chuyền tại đúng nhà máy đó. Danh sách vẫn hiển thị các nhà máy còn lại.</p>
    </section>;
}
