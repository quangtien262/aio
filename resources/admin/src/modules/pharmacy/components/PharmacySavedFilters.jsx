import DatePicker from 'antd/es/date-picker';
import viVN from 'antd/es/date-picker/locale/vi_VN';
import dayjs from 'dayjs';
import 'dayjs/locale/vi';
import Select from 'antd/es/select';
import Button from 'antd/es/button';
import usePharmacyResource from '../hooks/usePharmacyResource';
import { usePharmacyWorkspace } from '../state/PharmacyWorkspaceContext';
import { recordId, resourceRows } from '../utils/pharmacyData';
import { PharmacyError } from './PharmacyCommon';

export default function PharmacySavedFilters({ resource, filters, onApply }) {
    const { api, pharmacyId, revision, can, openCommand } = usePharmacyWorkspace();
    const saved = usePharmacyResource(api, 'filters', {}, Boolean(pharmacyId), revision);
    const rows = resourceRows(saved.data).filter((row) => row.resource === resource);
    const fromDate = filters.from && dayjs(filters.from).isValid() ? dayjs(filters.from) : null;
    const toDate = filters.to && dayjs(filters.to).isValid() ? dayjs(filters.to) : null;
    return <><div className="ph-toolbar ph-saved-filters">
        <label>Từ ngày<DatePicker locale={viVN} format="DD/MM/YYYY" placeholder="Chọn ngày" allowClear value={fromDate} onChange={(date) => onApply({ ...filters, from: date?.format('YYYY-MM-DD') ?? '' })} disabledDate={(date) => Boolean(toDate && date.isAfter(toDate, 'day'))} aria-label="Lọc từ ngày" /></label>
        <label>Đến ngày<DatePicker locale={viVN} format="DD/MM/YYYY" placeholder="Chọn ngày" allowClear value={toDate} onChange={(date) => onApply({ ...filters, to: date?.format('YYYY-MM-DD') ?? '' })} disabledDate={(date) => Boolean(fromDate && date.isBefore(fromDate, 'day'))} aria-label="Lọc đến ngày" /></label>
        <Select placeholder="Bộ lọc đã lưu của tôi" aria-label="Chọn bộ lọc đã lưu" value={undefined} options={rows.map((row) => ({ value: recordId(row), label: row.name }))} onChange={(id) => { const row = rows.find((item) => recordId(item) === id); if (row) onApply(row.filters ?? {}); }} style={{ minWidth: 190 }} />{can('filter.save') && <Button onClick={() => openCommand('filter.save', null, { resource, filters }, { title: 'Lưu bộ lọc cá nhân', description: 'Lưu trạng thái, khoảng ngày và số dòng theo nhà thuốc hiện tại. Tên hoặc thông tin người bệnh không được đưa vào bộ lọc.', fields: [{ name: 'name', label: 'Tên bộ lọc mới', required: true, maxLength: 100 }] })}>Lưu bộ lọc mới</Button>}</div><PharmacyError error={saved.error} onRetry={saved.reload} /></>;
}
