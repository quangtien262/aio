import { useEffect, useRef, useState } from 'react';
import InfoCircleOutlined from '@ant-design/icons/InfoCircleOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Modal from 'antd/es/modal';
import Pagination from 'antd/es/pagination';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Switch from 'antd/es/switch';
import Typography from 'antd/es/typography';
import TimePicker from 'antd/es/time-picker';
import dayjs from 'dayjs';
import { commands, commandPayload } from '../utils/manufacturingForms.js';
import ManufacturingTimezoneSelect from './ManufacturingTimezoneSelect.jsx';

export const rowId = (row) => row?.id ?? row?.uuid;
export const rowLabel = (row) => [row?.code ?? row?.roll_code ?? row?.number, row?.name ?? row?.title ?? row?.style_name].filter(Boolean).join(' · ') || String(rowId(row) ?? '');
export function resourceRows(payload, kind) {
    const data = payload?.data ?? payload;
    const rows = Array.isArray(data) ? data : data?.items ?? data?.[kind] ?? (kind === 'materials' ? data?.rolls : undefined) ?? [];
    return Array.isArray(rows) ? rows.map((row) => {
        const value = { ...(row.metadata ?? {}), ...row };
        if (kind === 'orders') return { ...value, planned_start: value.planned_start ?? value.planned_start_date, planned_end: value.planned_end ?? value.due_date };
        if (kind === 'demands') return { ...value, type: value.type ?? (value.source_type === 'customer_order' ? 'order' : value.source_type), forecast_id: value.forecast_id ?? value.source_id, customer_name: value.customer_name ?? value.buyer_snapshot?.name };
        if (kind === 'quality') return { ...value, code: value.code ?? value.bundle_code, quantity: value.inspected_quantity, good_quantity: value.accepted_quantity, rework_quantity: value.defects?.rework, scrap_quantity: value.defects?.scrap };
        return value;
    }) : [];
}

function RemoteLookup({ value, onChange, onRecord, source, api, bootstrap, contextKey, options: suppliedOptions, id }) {
    const [options, setOptions] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [query, setQuery] = useState('');
    const generation = useRef(0);
    const references = useRef(new Map());
    const local = suppliedOptions ?? bootstrap?.[source];
    useEffect(() => {
        const ticket = ++generation.current;
        setOptions([]); setError('');
        if (Array.isArray(local)) {
            setOptions(local.map((row) => row.value !== undefined ? row : { value: rowId(row), label: rowLabel(row), record: row }));
            return undefined;
        }
        const timer = setTimeout(async () => {
            setLoading(true);
            try {
                const allocation = ['issues', 'reservations', 'suppliers'].includes(source);
                const endpoint = allocation ? 'materials' : source;
                const params = allocation ? { allocation_type: source } : {};
                const selectedId = source === 'suppliers' ? references.current.get(String(value)) : value;
                const [result, selectedResult] = await Promise.all([api.read(endpoint, { ...params, search: query, page: 1, per_page: 30 }), selectedId ? api.read(endpoint, { ...params, selected_id: selectedId, page: 1, per_page: 1 }) : Promise.resolve(null)]);
                if (generation.current === ticket) {
                    const primary = resourceRows(result, source);
                    const rows = [...new Map([...primary, ...resourceRows(selectedResult, source)].map((row) => [rowId(row), row])).values()];
                    rows.forEach((row) => references.current.set(String(rowId(row)), row.public_id ?? rowId(row)));
                    setOptions(rows.map((row) => ({ value: rowId(row), label: rowLabel(row), record: row })));
                    const selected = rows.find((row) => rowId(row) === value);
                    if (selected) onRecord?.(selected);
                }
            } catch (err) { if (generation.current === ticket) setError(err.message || 'Không tải được danh sách.'); }
            finally { if (generation.current === ticket) setLoading(false); }
        }, 250);
        return () => { clearTimeout(timer); generation.current += 1; };
    }, [api, source, query, local, contextKey, value]);
    return <div><Select id={id} value={value} onChange={(next, option) => { onChange?.(next); onRecord?.(option?.record); }} showSearch allowClear filterOption={Array.isArray(local) ? (input, option) => String(option?.label ?? '').toLowerCase().includes(input.toLowerCase()) : false} onSearch={setQuery} options={options} loading={loading} notFoundContent={loading ? 'Đang tải…' : 'Chưa có dữ liệu'} optionFilterProp="label" style={{ width: '100%' }} />{error && <Typography.Text type="danger">{error}</Typography.Text>}</div>;
}

function ListField({ item, name, rules, api, bootstrap, contextKey, form, options }) {
    const [page, setPage] = useState(1);
    const pageSize = 25;
    return <div className="mfg-form-list"><Typography.Title level={5}>{item.label}</Typography.Title><Form.List name={name} rules={rules}>{(rows, { add, remove }, { errors }) => {
        const visible = item.fixed ? rows.slice((page - 1) * pageSize, page * pageSize) : rows;
        if (item.layout === 'table') return <>
            <div className="mfg-form-table-scroll" role="region" aria-label={item.label} tabIndex={0}><table className="mfg-form-table" aria-label={item.label}>
                <thead><tr><th scope="col" className="mfg-form-table-index">STT</th>{item.fields.map((column) => <th scope="col" key={column.name}>{column.required && <span className="mfg-form-table-required" aria-hidden="true">* </span>}{column.label}</th>)}{!item.fixed && <th scope="col" className="mfg-form-table-action">Thao tác</th>}</tr></thead>
                <tbody>{visible.length ? visible.map((row) => <tr key={row.key}><td className="mfg-form-table-index">{row.name + 1}</td>{item.fields.map((column) => <td key={column.name}><Fields fields={[column]} prefix={[row.name]} api={api} bootstrap={bootstrap} contextKey={contextKey} form={form} options={options} compact /></td>)}{!item.fixed && <td className="mfg-form-table-action"><Button size="small" danger type="text" aria-label={`Xóa dòng ${row.name + 1}`} onClick={() => remove(row.name)}>Xóa</Button></td>}</tr>) : <tr><td className="mfg-form-table-empty" colSpan={item.fields.length + (item.fixed ? 1 : 2)}>Chưa có dòng dữ liệu. Chọn “Thêm dòng” để bắt đầu.</td></tr>}</tbody>
            </table></div>
            <Form.ErrorList errors={errors} />
            {!item.fixed && <Button block type="dashed" className="mfg-form-table-add" onClick={() => add({})}>+ Thêm dòng</Button>}
        </>;
        return <><div className="mfg-list-rows">{visible.map((row) => <div key={row.key} className="mfg-list-row"><div className="mfg-list-row-heading"><strong>Dòng {row.name + 1}</strong>{!item.fixed && <Button size="small" danger type="text" onClick={() => remove(row.name)}>Xóa</Button>}</div><div className="mfg-fields"><Fields fields={item.fields} prefix={[row.name]} api={api} bootstrap={bootstrap} contextKey={contextKey} form={form} options={options} /></div></div>)}</div><Form.ErrorList errors={errors} />{item.fixed && rows.length > pageSize && <Pagination current={page} pageSize={pageSize} total={rows.length} showSizeChanger={false} showTotal={(total) => `${total} vị trí tồn cần nhập đủ trước khi duyệt`} onChange={setPage} />}{!item.fixed && <Button block type="dashed" onClick={() => add({})}>+ Thêm dòng</Button>}</>;
    }}</Form.List></div>;
}

function TimeField({ value, onChange, ...props }) {
    return <TimePicker {...props} value={value ? dayjs(`2000-01-01T${value}`) : null}
        onChange={(time) => onChange?.(time ? time.format('HH:mm') : null)} format="HH:mm"
        placeholder="Chọn giờ" inputReadOnly needConfirm={false} showNow={false} style={{ width: '100%' }} />;
}

function FieldHelpIcon(props) {
    // Form.Item removes tabIndex from its icon; keep the rendered control keyboard accessible.
    return <InfoCircleOutlined {...props} tabIndex={0} role="button" onKeyDown={(event) => {
        if (['Enter', ' '].includes(event.key)) { event.preventDefault(); event.currentTarget.click(); }
    }} />;
}

function Fields({ fields, prefix = [], api, bootstrap, contextKey, form, options = {}, compact = false }) {
    return fields.map((item) => {
        const name = [...prefix, item.name];
        const rules = item.required ? item.type === 'list' ? [{ validator: async (_, value) => { if (!Array.isArray(value) || !value.length) throw new Error(`Vui lòng thêm ${item.label.toLowerCase()}.`); } }] : [{ required: true, message: `Vui lòng nhập ${item.label.toLowerCase()}.`, ...(item.type === 'tags' ? { type: 'array', min: 1 } : {}) }] : [];
        if (item.type === 'list') return <ListField key={item.name} item={item} name={name} rules={rules} api={api} bootstrap={bootstrap} contextKey={contextKey} form={form} options={options} />;
        let input;
        if (item.type === 'number') input = <InputNumber min={item.min ?? 0} stringMode={!item.integer} precision={item.integer ? 0 : 6} style={{ width: '100%' }} />;
        else if (item.type === 'boolean') input = <Switch />;
        else if (item.type === 'textarea') input = <Input.TextArea rows={3} maxLength={4000} showCount />;
        else if (item.type === 'date') input = <Input type="date" />;
        else if (item.type === 'timezone') input = <ManufacturingTimezoneSelect timezones={bootstrap?.timezones} />;
        else if (item.type === 'time') input = <TimeField />;
        else if (item.type === 'tags') input = <Select mode="tags" tokenSeparators={[',']} placeholder="Nhập và nhấn Enter" />;
        else if (item.type === 'select') input = <Select options={options[item.name] ?? item.options} allowClear />;
        else if (item.type === 'lookup') input = <RemoteLookup source={item.source} api={api} bootstrap={bootstrap} contextKey={contextKey} options={item.options} onRecord={item.versionField || item.copyTimezone || ['bundle_id', 'order_id'].includes(item.name) ? (row) => {
            if (item.copyTimezone && row?.timezone) form.setFieldValue('timezone', row.timezone);
            if (item.versionField || ['bundle_id', 'order_id'].includes(item.name)) form.setFieldValue(item.versionField ?? 'expected_version', row?.version);
        } : undefined} />;
        else input = <Input maxLength={item.maxLength ?? 255} placeholder={item.placeholder} autoComplete="off" readOnly={item.readOnly} />;
        return <Form.Item key={item.name} name={name} label={item.label} extra={item.extra} tooltip={item.tooltip ? { title: item.tooltip, icon: <FieldHelpIcon aria-label={`Giải thích ${item.label.toLowerCase()}`} />, trigger: ['hover', 'focus', 'click'] } : undefined} hidden={item.type === 'hidden'} rules={rules} valuePropName={item.type === 'boolean' ? 'checked' : 'value'} className={[item.wide || item.type === 'textarea' ? 'mfg-field-wide' : '', compact ? 'mfg-table-field' : ''].filter(Boolean).join(' ')}>{input}</Form.Item>;
    });
}

export default function ManufacturingCommandDrawer({ action, onClose, api, bootstrap, contextKey, onSaved, onPendingChange }) {
    const [form] = Form.useForm();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const generation = useRef(0);
    const definition = commands[action?.operation];
    const isCompanyEdit = action?.operation === 'company.update';
    useEffect(() => {
        generation.current += 1;
        setBusy(false); setError(''); form.resetFields();
        if (action) {
            const record = action.record ?? {};
            const metadata = record.metadata ?? {};
            form.setFieldsValue({ timezone: 'Asia/Ho_Chi_Minh', type: action.operation === 'sample.save' ? 'pp' : 'order', checkpoint: 'final', scope_type: 'factory', is_working: true, acknowledge_warnings: false, override_frozen: false, role: 'reader', quantity_scale: 6, tracking_mode: 'batch', ...metadata, ...record, ...action.initial });
        }
        return () => { generation.current += 1; };
    }, [action, contextKey, form]);
    const save = async (values) => {
        if (!definition || busy) return;
        const ticket = generation.current;
        setError(''); setBusy(true);
        try {
            const data = commandPayload(action.operation, values, action.record);
            const response = await api.command(definition.apiOperation ?? action.operation, action.resource ?? rowId(action.record), data);
            if (generation.current === ticket) { onSaved(response, action.operation); onClose(); }
        } catch (err) {
            if (generation.current === ticket) {
                setError(err.message || 'Không thể ghi nhận thao tác.');
                const errors = err.errors ?? err.payload?.errors ?? err.details?.errors;
                if (errors) form.setFields(Object.entries(errors).map(([name, messages]) => ({ name: name.replace(/^data\./, '').split('.').map((part) => /^\d+$/.test(part) ? Number(part) : part), errors: Array.isArray(messages) ? messages : [String(messages)] })));
            }
        } finally {
            if (generation.current === ticket) setBusy(false);
            onPendingChange();
        }
    };
    const actions = <Space><Button onClick={onClose} disabled={busy}>Đóng</Button><Button type="primary" loading={busy} onClick={() => form.submit()}>Ghi nhận</Button></Space>;
    const content = <>
        {definition?.confirm && <Alert type="info" showIcon message={definition.confirm} style={{ marginBottom: 20 }} />}
        {error && <Alert type="error" showIcon message={error} style={{ marginBottom: 20 }} />}
        {action?.record && !isCompanyEdit && <div className="mfg-record-context"><strong>{rowLabel(action.record)}</strong><Typography.Text type="secondary">{action.record.version !== undefined ? `Phiên bản ${action.record.version}` : ''}</Typography.Text></div>}
        <Form form={form} layout="vertical" onFinish={save} requiredMark disabled={busy}><Form.Item name="expected_version" hidden><InputNumber /></Form.Item><Form.Item name="source_expected_version" hidden><InputNumber /></Form.Item><div className="mfg-fields"><Fields fields={definition?.fields ?? []} api={api} bootstrap={bootstrap} contextKey={contextKey} form={form} options={action?.options} /></div></Form>
    </>;
    const modal = {
        'workshop.create': { width: 520, className: 'mfg-workshop-modal' },
        'line.create': { width: 600, className: 'mfg-line-modal' },
        'company.update': { width: 560, className: 'mfg-company-modal' },
        'factory.create': { width: 600, className: 'mfg-factory-modal' },
        'shift.create': { width: 560, className: 'mfg-shift-modal' },
    }[action?.operation];
    if (modal) {
        return <Modal title={definition.title} open centered width={modal.width} onCancel={() => !busy && onClose()} closable={!busy} maskClosable={!busy} keyboard={!busy} destroyOnClose rootClassName={`mfg-command-modal ${modal.className}`} footer={actions}>{content}</Modal>;
    }
    return <Drawer title={definition?.title ?? ''} open={Boolean(action)} width={760} onClose={() => !busy && onClose()} maskClosable={!busy} keyboard={!busy} destroyOnClose rootClassName="mfg-drawer" extra={actions}>{content}</Drawer>;
}
