import { useState } from 'react';
import Alert from 'antd/es/alert';
import App from 'antd/es/app';
import Button from 'antd/es/button';
import Col from 'antd/es/col';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Modal from 'antd/es/modal';
import Popconfirm from 'antd/es/popconfirm';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import CheckCircleOutlined from '@ant-design/icons/CheckCircleOutlined';
import LogoutOutlined from '@ant-design/icons/LogoutOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import ShopOutlined from '@ant-design/icons/ShopOutlined';
import { formatMoney, uuid } from '../utils/retailFormat';

const money = () => <InputNumber stringMode min="0" precision={0} controls={false} suffix="₫" style={{ width: '100%' }} formatter={(value) => value == null || value === '' ? '' : String(value).replace(/\B(?=(\d{3})+(?!\d))/g, '.')} parser={(value) => (value ?? '').replace(/\./g, '')} />;
const labels = { open: 'Đang mở', closed: 'Chờ đối soát', reconciled: 'Đã đối soát' };

export default function RetailShiftManager({ open, onClose, branch, shifts, terminals, api, onChanged, onCommand, canCreateTerminal, locked, loading }) {
    const { message } = App.useApp();
    const [editor, setEditor] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);
    const [filter, setFilter] = useState('all');
    const [form] = Form.useForm();
    const terminalName = (shift) => terminals.find((terminal) => [uuid(terminal), String(terminal.database_id)].includes(String(shift.terminal_id)))?.name ?? shift.terminal_name ?? '—';
    const availableTerminals = terminals.filter((terminal) => !shifts.some((shift) => shift.status === 'open' && [uuid(terminal), String(terminal.database_id)].includes(String(shift.terminal_id))));
    const edit = (kind, record = null) => {
        setError(null); setEditor({ kind, record }); form.resetFields();
        form.setFieldsValue(kind === 'open' ? { terminal_id: availableTerminals[0] ? uuid(availableTerminals[0]) : undefined, opening_minor: '0' } : {});
    };
    const perform = async (path, payload, success) => {
        setBusy(true); setError(null);
        try {
            await api.command(path, payload);
            setEditor(null); message.success(success); await onChanged();
        } catch (failure) { setError(failure.message); }
        finally { setBusy(false); onCommand?.(); }
    };
    const submit = (values) => {
        if (locked) return;
        if (editor.kind === 'terminal') return perform('terminals', { ...values, branch_id: uuid(branch) }, 'Đã thêm quầy bán.');
        if (editor.kind === 'open') return perform('shifts', { ...values, branch_id: uuid(branch) }, 'Đã mở ca bán hàng.');
        return perform(`shifts/${uuid(editor.record)}/close`, { ...values, expected_version: editor.record.version }, 'Đã đóng ca và lưu số tiền kiểm đếm.');
    };
    const blocked = busy || locked || loading;
    return <>
        <Modal title="Quản lý ca & tiền quầy" open={open} width={1080} centered className="retail-shift-modal" onCancel={() => { setError(null); onClose(); }} closable={!busy && !editor} keyboard={!busy && !editor} maskClosable={false} footer={<Button disabled={busy || Boolean(editor)} onClick={onClose}>Đóng</Button>}>
            <div className="retail-shift-summary"><span><ShopOutlined /> <strong>{branch?.name}</strong></span><Space wrap><Tag color="blue">{shifts.filter((shift) => shift.status === 'open').length} ca đang mở</Tag><Tag color="orange">{shifts.filter((shift) => shift.status === 'closed').length} ca chờ đối soát</Tag></Space></div>
            {locked && <Alert type="info" showIcon message="Hoàn tất hoặc hủy bản tính giá đang xử lý trước khi thay đổi ca." style={{ marginBottom: 12 }} />}
            {error && !editor && <Alert type="error" showIcon message={error} style={{ marginBottom: 12 }} />}
            <div className="retail-shift-toolbar"><Select aria-label="Lọc trạng thái ca" value={filter} onChange={setFilter} style={{ minWidth: 170 }} options={[{ value: 'all', label: 'Tất cả trạng thái' }, ...Object.entries(labels).map(([value, label]) => ({ value, label }))]} /><Space wrap>{canCreateTerminal && <Button icon={<ShopOutlined />} disabled={blocked} onClick={() => edit('terminal')}>Thêm quầy</Button>}<Button type="primary" icon={<PlusOutlined />} disabled={blocked || !availableTerminals.length} onClick={() => edit('open')}>Mở ca</Button></Space></div>
            <Table rowKey={uuid} size="small" loading={loading} dataSource={shifts.filter((shift) => filter === 'all' || shift.status === filter)} scroll={{ x: 950 }} pagination={{ pageSize: 7, showSizeChanger: false }} locale={{ emptyText: 'Chưa có ca phù hợp tại chi nhánh.' }} columns={[
                { title: 'Ca / quầy', width: 160, render: (_, shift) => <><strong>{terminalName(shift)}</strong><div className="retail-muted">{shift.business_date} · {shift.code ?? uuid(shift).slice(-8)}</div></> },
                { title: 'Đầu ca', width: 115, align: 'right', dataIndex: 'opening_minor', render: formatMoney },
                { title: 'Hệ thống', width: 125, align: 'right', render: (_, shift) => formatMoney(shift.expected_minor ?? shift.expected_cash_minor) },
                { title: 'Thực đếm', width: 115, align: 'right', dataIndex: 'counted_minor', render: (value) => value == null ? '—' : formatMoney(value) },
                { title: 'Chênh lệch', width: 115, align: 'right', dataIndex: 'variance_minor', render: (value) => value == null ? '—' : <span style={{ color: String(value) === '0' ? '#16806c' : '#c53b36' }}>{formatMoney(value)}</span> },
                { title: 'Trạng thái', width: 135, dataIndex: 'status', render: (value) => <Tag color={value === 'open' ? 'blue' : value === 'closed' ? 'orange' : 'green'}>{labels[value] ?? value}</Tag> },
                { title: 'Thao tác', width: 145, render: (_, shift) => shift.status === 'open' ? <Button size="small" icon={<LogoutOutlined />} disabled={blocked} onClick={() => edit('close', shift)}>Đóng ca</Button> : shift.status === 'closed' ? <Popconfirm title="Xác nhận đã đối soát ca?" description="Kiểm tra tiền thực đếm và chênh lệch trước khi xác nhận." okText="Đối soát" cancelText="Hủy" onConfirm={() => !locked && perform(`shifts/${uuid(shift)}/reconcile`, { expected_version: shift.version }, 'Đã đối soát ca.')}><Button size="small" icon={<CheckCircleOutlined />} disabled={blocked}>Đối soát</Button></Popconfirm> : null },
            ]} />
        </Modal>
        <Modal title={editor?.kind === 'open' ? 'Mở ca bán hàng' : editor?.kind === 'terminal' ? 'Thêm quầy bán' : 'Đóng ca & kiểm đếm'} open={Boolean(editor)} centered width={500} zIndex={1100} forceRender maskClosable={false} closable={!busy} keyboard={!busy} onCancel={() => { if (!busy) { setEditor(null); setError(null); } }} footer={<Space><Button disabled={busy} onClick={() => { setEditor(null); setError(null); }}>Hủy</Button><Button type="primary" loading={busy} disabled={locked} onClick={() => form.submit()}>{editor?.kind === 'open' ? 'Mở ca' : editor?.kind === 'terminal' ? 'Thêm quầy' : 'Xác nhận đóng ca'}</Button></Space>}>
            {error && editor && <Alert type="error" showIcon message={error} style={{ margin: '12px 0' }} />}
            <Form form={form} layout="vertical" disabled={busy || locked} onFinish={submit} style={{ marginTop: 16 }}>
                {editor?.kind === 'terminal' ? <Row gutter={16}><Col span={9}><Form.Item name="code" label="Mã quầy" rules={[{ required: true, whitespace: true, message: 'Nhập mã quầy.' }]}><Input maxLength={40} /></Form.Item></Col><Col span={15}><Form.Item name="name" label="Tên quầy" rules={[{ required: true, whitespace: true, message: 'Nhập tên quầy.' }]}><Input maxLength={100} /></Form.Item></Col></Row> : editor?.kind === 'open' ? <>
                    <Form.Item name="terminal_id" label="Quầy bán" rules={[{ required: true, message: 'Chọn quầy chưa có ca mở.' }]}><Select placeholder="Chọn quầy bán" options={availableTerminals.map((terminal) => ({ value: uuid(terminal), label: terminal.name ?? terminal.code }))} /></Form.Item>
                    <Form.Item name="opening_minor" label="Tiền mặt đầu ca" rules={[{ required: true, message: 'Nhập tiền mặt đầu ca.' }]}>{money()}</Form.Item>
                </> : editor?.kind === 'close' ? <>
                    <div className="retail-shift-count-summary"><strong>{terminalName(editor.record)}</strong><span>Tiền hệ thống: <strong>{formatMoney(editor.record.expected_minor ?? editor.record.expected_cash_minor)}</strong></span></div>
                    <Form.Item name="counted_minor" label="Tiền mặt thực đếm" rules={[{ required: true, message: 'Nhập số tiền đã kiểm đếm thực tế.' }]}>{money()}</Form.Item>
                    <Alert type="info" showIcon message="Chênh lệch được lưu để đối soát" description="Nhập đúng số tiền kiểm đếm thực tế tại quầy." />
                </> : null}
            </Form>
        </Modal>
    </>;
}
