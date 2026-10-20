import { useEffect, useState } from 'react';
import AuditOutlined from '@ant-design/icons/AuditOutlined';
import CheckCircleOutlined from '@ant-design/icons/CheckCircleOutlined';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import SafetyCertificateOutlined from '@ant-design/icons/SafetyCertificateOutlined';
import SwapOutlined from '@ant-design/icons/SwapOutlined';
import Avatar from 'antd/es/avatar';
import Empty from 'antd/es/empty';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Descriptions from 'antd/es/descriptions';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Radio from 'antd/es/radio';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { customerId, customerMergeFields, customerRows, serializeCustomerMergeChoices } from '../utils/customerData';
import '../styles/customers-profiles.css';

const fieldValue = (profile, field) => {
    const value = profile?.[field];
    if (field === 'contacts') return customerRows(value).map((item) => [item.label, item.value].filter(Boolean).join(': ')).join('\n') || '—';
    if (field === 'addresses') return customerRows(value).map((item) => [item.label, item.line1, item.line2, item.region, item.city, item.country_code].filter(Boolean).join(', ')).join('\n') || '—';
    if (field === 'tags') return customerRows(value).map((item) => typeof item === 'string' ? item : item.name ?? item.label).join(', ') || '—';
    return value || '—';
};

export default function CustomerDuplicates({ api, bookId, canMerge, onChanged, onView }) {
    const [items, setItems] = useState([]);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(false);
    const [pair, setPair] = useState(null);
    const [target, setTarget] = useState(null);
    const [fieldChoices, setFieldChoices] = useState({});
    const [busy, setBusy] = useState(false);
    const [form] = Form.useForm();
    const reload = async () => { setLoading(true); setError(null); try { setItems(customerRows((await api.read(`books/${bookId}/duplicates`)).data)); } catch (failure) { setError(failure.message); } finally { setLoading(false); } };
    useEffect(() => { reload(); }, [api, bookId]);
    const open = async (record) => {
        setError(null); setBusy(true); form.resetFields();
        try {
            const [left, right] = await Promise.all([api.read(`books/${bookId}/profiles/${customerId(record.left)}`), api.read(`books/${bookId}/profiles/${customerId(record.right)}`)]);
            setPair({ ...record, left: left.data, right: right.data }); setTarget(customerId(left.data)); setFieldChoices({});
        } catch (failure) { setError(failure.message); }
        finally { setBusy(false); }
    };
    const merge = async (values) => {
        const destination = customerId(pair.left) === target ? pair.left : pair.right;
        const source = customerId(pair.left) === target ? pair.right : pair.left;
        setBusy(true); setError(null);
        try {
            await api.command(`books/${bookId}/profiles/${customerId(source)}/merge`, { target_id: customerId(destination), expected_version: source.version, target_version: destination.version, reason: values.reason, field_choices: serializeCustomerMergeChoices(source, destination, fieldChoices) });
            setPair(null); await reload(); await onChanged?.();
        } catch (failure) { setError(failure.message); }
        finally { setBusy(false); }
    };
    const renderProfile = (profile) => <div className="customers-duplicates-identity"><Avatar shape={profile?.type === 'company' ? 'square' : 'circle'}>{profile?.name?.slice(0, 1) || '?'}</Avatar><div><Button type="link" onClick={() => onView(customerId(profile))}>{profile?.name ?? customerId(profile)}</Button><span>{profile?.code || (profile?.type === 'company' ? 'Doanh nghiệp' : 'Cá nhân')}</span></div></div>;
    return <div className="customers-duplicates-workspace">
        <div className="customers-duplicates-guide"><div className="customers-duplicates-guide-icon"><SafetyCertificateOutlined /></div><div><Typography.Title level={5}>Giữ danh bạ nhất quán, bảo toàn lịch sử</Typography.Title><Typography.Paragraph>Đối chiếu danh tính, chọn hồ sơ chính và kiểm tra từng thông tin trước khi hợp nhất. Chung liên hệ chưa đủ để kết luận hai hồ sơ là một.</Typography.Paragraph></div><Tag className="customers-duplicates-manual-tag">Kiểm tra thủ công</Tag></div>
        <Card className="customers-duplicates-card" title={<div className="customers-duplicates-card-title"><span className="customers-duplicates-title-icon"><AuditOutlined /></span><div><strong>Hồ sơ cần đối chiếu</strong><span>{error ? 'Lần kiểm tra chưa hoàn tất' : loading ? 'Đang kiểm tra danh bạ hiện tại' : `${items.length} cặp có thông tin liên hệ giống nhau`}</span></div></div>} extra={<Button icon={<ReloadOutlined />} loading={loading} onClick={reload}>Kiểm tra lại</Button>}>
            {error && <Alert type="error" showIcon message={error} style={{ marginBottom: 16 }} />}
            <Table rowKey={(pair) => `${customerId(pair.left)}:${customerId(pair.right)}`} dataSource={items} loading={loading} scroll={{ x: 780 }} columns={[
                { title: 'Hồ sơ thứ nhất', render: (_, record) => renderProfile(record.left) },
                { title: 'Hồ sơ thứ hai', render: (_, record) => renderProfile(record.right) },
                { title: 'Điểm trùng khớp', dataIndex: 'matches', render: (matches) => customerRows(matches).map((match) => { const kind = typeof match === 'string' ? match : match.kind ?? match.field; return <Tag key={kind}>{({ phone: 'Điện thoại', email: 'Email' })[kind] ?? kind}</Tag>; }) },
                { title: 'Thao tác', width: 200, render: (_, record) => canMerge && <Button icon={<SwapOutlined />} disabled={busy} onClick={() => open(record)}>So sánh hồ sơ</Button> },
            ]} pagination={{ pageSize: 20, hideOnSinglePage: true, showTotal: (total) => `${total} cặp cần đối chiếu` }} locale={{ emptyText: <Empty className="customers-duplicates-empty" image={<div className="customers-duplicates-empty-icon">{error ? <AuditOutlined /> : <CheckCircleOutlined />}</div>} description={<><Typography.Title level={4}>{error ? 'Chưa thể kiểm tra danh bạ' : 'Chưa phát hiện hồ sơ trùng'}</Typography.Title><Typography.Paragraph>{error ? 'Dùng Kiểm tra lại để tải kết quả đối chiếu mới nhất.' : <>Không có cặp hồ sơ trùng liên hệ trong lần kiểm tra này.<br />Bạn có thể kiểm tra lại sau khi thêm hoặc nhập dữ liệu mới.</>}</Typography.Paragraph></>} /> }} />
            <div className="customers-duplicates-footnote"><SafetyCertificateOutlined /><span>Hệ thống không tự hợp nhất. Thông tin trên chứng từ lịch sử luôn được giữ nguyên.</span></div>
        </Card>
        <Modal rootClassName="customers-profile-modal customers-duplicates-modal" open={Boolean(pair)} width={1080} title={<Space><SwapOutlined />So sánh và chọn hồ sơ chính</Space>} onCancel={() => !busy && setPair(null)} confirmLoading={busy} onOk={() => form.submit()} okText="Xác nhận hợp nhất" cancelText="Hủy" maskClosable={false} okButtonProps={{ disabled: !target || pair?.left?.type !== pair?.right?.type }}>
            {error && <Alert type="error" showIcon message={error} style={{ marginBottom: 16 }} />}<Alert type="warning" showIcon message="Chọn hồ sơ chính và dữ liệu cần giữ" description="Hồ sơ còn lại trở thành hồ sơ đã hợp nhất và giữ liên kết tra cứu. Danh tính trên chứng từ lịch sử được giữ nguyên. Mặc định giữ dữ liệu của hồ sơ chính." style={{ marginBottom: 16 }} />
            <Radio.Group disabled={busy} className="customers-duplicates-targets" value={target} onChange={(event) => { setTarget(event.target.value); setFieldChoices({}); }} style={{ width: '100%' }}><div className="customers-merge-grid">{pair && [pair.left, pair.right].map((profile) => <Card className={customerId(profile) === target ? 'customers-duplicates-choice is-selected' : 'customers-duplicates-choice'} key={customerId(profile)} size="small" title={<Radio value={customerId(profile)}>Giữ làm hồ sơ chính</Radio>}><Typography.Title level={5}>{profile.name}</Typography.Title><Descriptions column={1} size="small" items={[{ key: 'code', label: 'Mã', children: profile.code }, { key: 'phone', label: 'Điện thoại', children: profile.phone ?? '—' }, { key: 'email', label: 'Email', children: profile.email ?? '—' }, { key: 'type', label: 'Loại', children: profile.type === 'company' ? 'Doanh nghiệp' : 'Cá nhân' }]} /></Card>)}</div></Radio.Group>
            {pair && <><div className="customers-duplicates-comparison-title"><Typography.Title level={5}>Thông tin sau hợp nhất</Typography.Title><Typography.Text type="secondary">Chọn dữ liệu cần giữ cho từng trường bên dưới.</Typography.Text></div><Table rowKey="key" size="small" pagination={false} scroll={{ x: 850 }} dataSource={customerMergeFields(pair.left, pair.right)} columns={[
                { title: 'Thông tin', dataIndex: 'label', width: 145 },
                { title: 'Hồ sơ chính', width: 240, render: (_, field) => <span style={{ whiteSpace: 'pre-wrap' }}>{fieldValue(customerId(pair.left) === target ? pair.left : pair.right, field.key)}</span> },
                { title: 'Hồ sơ còn lại', width: 240, render: (_, field) => <span style={{ whiteSpace: 'pre-wrap' }}>{fieldValue(customerId(pair.left) === target ? pair.right : pair.left, field.key)}</span> },
                { title: 'Giữ lại', width: 200, render: (_, field) => <Select aria-label={`Giữ lại ${field.label}`} value={fieldChoices[field.key] ?? 'target'} disabled={busy} style={{ width: '100%' }} onChange={(value) => setFieldChoices((current) => ({ ...current, [field.key]: value }))} options={[{ value: 'target', label: 'Từ hồ sơ chính' }, { value: 'source', label: 'Từ hồ sơ còn lại' }, ...(field.multiple ? [{ value: 'combine', label: 'Kết hợp hai hồ sơ' }] : [])]} /> },
            ]} />{!(pair.left.sensitive_visible && pair.right.sensitive_visible) && <Typography.Paragraph type="secondary" style={{ marginTop: 12 }}>Liên hệ, địa chỉ và thông tin riêng tư chỉ được chọn khi có quyền xem đầy đủ cả hai hồ sơ.</Typography.Paragraph>}{pair.left.type !== pair.right.type && <Alert type="error" showIcon message="Chỉ hợp nhất hai hồ sơ cùng loại." style={{ marginTop: 12 }} />}</>}
            <Alert type="info" showIcon message="Lựa chọn liên hệ cần được xác nhận riêng" description="Đồng ý nhận liên hệ không được tự hợp nhất. Lựa chọn đã rút lại được giữ hoặc chuyển về chưa ghi nhận; cần xác nhận lại trước khi sử dụng." style={{ marginTop: 16 }} />
            <Form form={form} layout="vertical" onFinish={merge} style={{ marginTop: 18 }}><Form.Item name="reason" label="Lý do hợp nhất" rules={[{ required: true, min: 5, message: 'Nhập lý do tối thiểu 5 ký tự.' }]}><Input.TextArea rows={3} maxLength={500} placeholder="Ghi rõ căn cứ xác nhận hai hồ sơ thuộc cùng một khách hàng…" /></Form.Item></Form>
        </Modal>
    </div>;
}
