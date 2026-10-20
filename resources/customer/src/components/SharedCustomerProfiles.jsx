import { useEffect, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Typography from 'antd/es/typography';

export default function SharedCustomerProfiles({ apiBase, enabled }) {
    const [profiles, setProfiles] = useState([]);
    const [selected, setSelected] = useState(null);
    const [details, setDetails] = useState(null);
    const [error, setError] = useState(null);
    const [busy, setBusy] = useState(false);
    const [inviteOpen, setInviteOpen] = useState(false);
    const [consentOpen, setConsentOpen] = useState(false);
    const [form] = Form.useForm();
    const [consentForm] = Form.useForm();
    const api = async (path = '', options = {}) => {
        const response = await fetch(`${apiBase}/shared-customers${path}`, { credentials: 'same-origin', ...options, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' } });
        const payload = await response.json(); if (!response.ok) throw new Error(payload.message ?? 'Không thể thực hiện thao tác.'); return payload.data;
    };
    const load = async () => { const result = await api(); setProfiles(result); return result; };
    useEffect(() => { if (enabled) load().catch((failure) => setError(failure.message)); }, [enabled, apiBase]);
    useEffect(() => {
        let active = true; setDetails(null); if (!selected) return undefined;
        api(`/${selected}`).then((p) => { if (active) { setDetails(p); form.setFieldsValue({ name: p.name, phone: p.phone, addresses: (p.addresses ?? []).map(({ label, kind, line1, line2, city, region, postal_code, country_code, is_default }) => ({ label, kind, line1, line2, city, region, postal_code, country_code, is_default: Boolean(is_default) })) }); } }).catch((failure) => active && setError(failure.message));
        return () => { active = false; };
    }, [selected, apiBase]);
    const run = async (action) => { setBusy(true); setError(null); try { await action(); } catch (failure) { setError(failure.message); } finally { setBusy(false); } };
    if (!enabled) return <Card><Empty description="Hồ sơ khách hàng dùng chung chưa được bật trên website này." /></Card>;
    return <Space direction="vertical" size={20} style={{ width: '100%' }}>
        {error && <Alert type="error" showIcon message={error} />}
        <Card title="Hồ sơ khách hàng liên kết" extra={<Button onClick={() => setInviteOpen(true)}>Nhập mã liên kết</Button>}>
            <Typography.Paragraph type="secondary">Chỉ hiển thị hồ sơ đã được liên kết với tài khoản của bạn tại website hiện tại.</Typography.Paragraph>
            <Select style={{ width: '100%' }} placeholder="Chọn hồ sơ" value={selected} onChange={setSelected} options={profiles.map((p) => ({ value: p.public_id, label: p.book_name }))} />
            {!profiles.length && <Empty description="Chưa có hồ sơ liên kết. Nhập mã mời do đơn vị cung cấp." />}
        </Card>
        {details && <Card title={`${details.name} · ${details.code}`}>
            <Form form={form} layout="vertical" disabled={busy} onFinish={(values) => run(async () => { const result = await api(`/${selected}`, { method: 'PUT', body: JSON.stringify({ ...values, expected_version: details.version }) }); setDetails(result); })}>
                <Form.Item name="name" label="Họ tên" rules={[{ required: true }]}><Input maxLength={255} /></Form.Item><Form.Item name="phone" label="Điện thoại liên hệ"><Input maxLength={40} /></Form.Item>
                <Form.List name="addresses">{(fields, { add, remove }) => <><Typography.Title level={5}>Địa chỉ liên hệ</Typography.Title>{fields.map(({ key, name }) => <Card key={key} size="small" style={{ marginBottom: 12 }} extra={<Button type="text" onClick={() => remove(name)}>Bỏ địa chỉ</Button>}><Form.Item name={[name, 'line1']} label="Địa chỉ" rules={[{ required: true }]}><Input /></Form.Item><Form.Item name={[name, 'city']} label="Tỉnh / thành phố"><Input /></Form.Item><Form.Item name={[name, 'country_code']} label="Mã quốc gia" initialValue="VN"><Input maxLength={2} /></Form.Item></Card>)}<Button onClick={() => add({ country_code: 'VN' })}>Thêm địa chỉ</Button></>}</Form.List>
                <Button type="primary" htmlType="submit" loading={busy} style={{ marginTop: 16 }}>Lưu thông tin liên hệ</Button>
            </Form>
            <Typography.Title level={5}>Lựa chọn liên hệ</Typography.Title><Button onClick={() => { consentForm.resetFields(); setConsentOpen(true); }}>Cập nhật lựa chọn</Button>
            <Table style={{ marginTop: 16 }} rowKey="public_id" pagination={false} dataSource={details.consents ?? []} columns={[{ title: 'Mục đích', dataIndex: 'purpose' }, { title: 'Kênh', dataIndex: 'channel' }, { title: 'Đơn vị', dataIndex: 'brand' }, { title: 'Lựa chọn', dataIndex: 'state', render: (s) => ({ granted: 'Đã đồng ý', denied: 'Từ chối', withdrawn: 'Đã rút lại', unknown: 'Chưa ghi nhận' }[s] ?? s) }]} />
        </Card>}
        <Modal open={inviteOpen} title="Liên kết hồ sơ khách hàng" footer={null} onCancel={() => !busy && setInviteOpen(false)} destroyOnHidden>
            <Typography.Paragraph>Nhập mã mời được cấp cho tài khoản của bạn và mật khẩu đăng nhập hiện tại để xác nhận.</Typography.Paragraph>
            <Form layout="vertical" disabled={busy} onFinish={(values) => run(async () => { await api('/accept', { method: 'POST', body: JSON.stringify(values) }); await load(); setInviteOpen(false); })}>
                <Form.Item name="token" label="Mã mời" rules={[{ required: true, len: 64 }]}><Input autoComplete="off" /></Form.Item><Form.Item name="password" label="Mật khẩu hiện tại" rules={[{ required: true }]}><Input.Password autoComplete="current-password" /></Form.Item><Button type="primary" htmlType="submit" loading={busy}>Xác nhận liên kết</Button>
            </Form>
        </Modal>
        <Modal open={consentOpen} title="Lựa chọn liên hệ" footer={null} onCancel={() => !busy && setConsentOpen(false)} destroyOnHidden>
            <Form form={consentForm} layout="vertical" disabled={busy} onFinish={(values) => run(async () => { setDetails(await api(`/${selected}/consents`, { method: 'POST', body: JSON.stringify({ ...values, expected_version: details.version }) })); setConsentOpen(false); })}>
                <Form.Item name="purpose" label="Mục đích" rules={[{ required: true }]}><Select options={[{ value: 'marketing', label: 'Thông tin / ưu đãi' }, { value: 'service', label: 'Liên hệ giao dịch' }]} /></Form.Item><Form.Item name="channel" label="Kênh" rules={[{ required: true }]}><Select options={['email', 'sms', 'phone'].map((value) => ({ value, label: value }))} /></Form.Item><Form.Item name="brand" label="Đơn vị / thương hiệu" rules={[{ required: true }]}><Input maxLength={100} /></Form.Item><Form.Item name="state" label="Lựa chọn" rules={[{ required: true }]}><Select options={[{ value: 'granted', label: 'Đồng ý' }, { value: 'denied', label: 'Từ chối' }, { value: 'withdrawn', label: 'Rút lại đồng ý' }]} /></Form.Item><Form.Item shouldUpdate noStyle>{({ getFieldValue }) => <Form.Item name="policy_version" label="Phiên bản chính sách đã đọc" rules={getFieldValue('state') === 'granted' ? [{ required: true }] : []}><Input maxLength={80} /></Form.Item>}</Form.Item><Button type="primary" htmlType="submit" loading={busy}>Lưu lựa chọn</Button>
            </Form>
        </Modal>
    </Space>;
}
