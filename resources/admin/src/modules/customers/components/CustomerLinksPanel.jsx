import { useEffect, useState } from 'react';
import BankOutlined from '@ant-design/icons/BankOutlined';
import CopyOutlined from '@ant-design/icons/CopyOutlined';
import LinkOutlined from '@ant-design/icons/LinkOutlined';
import UserOutlined from '@ant-design/icons/UserOutlined';
import Alert from 'antd/es/alert';
import App from 'antd/es/app';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Descriptions from 'antd/es/descriptions';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Modal from 'antd/es/modal';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';
import { customerDate, customerId, customerRows } from '../utils/customerData';
import '../styles/customers-profiles.css';

export default function CustomerLinksPanel({ api, bookId, profile, can, nativePermissions = [], organizationOptions = [] }) {
    const { message } = App.useApp();
    const [accounts, setAccounts] = useState([]);
    const [branches, setBranches] = useState([]);
    const [error, setError] = useState(null);
    const [busy, setBusy] = useState(false);
    const [invitation, setInvitation] = useState(null);
    const [sync, setSync] = useState(null);
    const [linkForm] = Form.useForm();
    const [accountingForm] = Form.useForm();
    const accountingAllowed = nativePermissions.includes('accounting.integration.sync') && nativePermissions.includes('accounting.party.manage');
    const path = `books/${bookId}/profiles/${customerId(profile)}`;
    useEffect(() => {
        let cancelled = false;
        api.read(`books/${bookId}/settings-options`).then((response) => { if (!cancelled) setAccounts(customerRows(response.data?.accounts)); }).catch(() => { /* Account identifiers remain usable when options are not granted. */ });
        if (accountingAllowed) api.readNative('retail/bootstrap').then((response) => { if (!cancelled) setBranches(customerRows(response.data?.branches)); }).catch((failure) => { if (!cancelled) setError(failure.message); });
        return () => { cancelled = true; };
    }, [api, bookId, accountingAllowed]);
    const invite = async (values) => {
        setBusy(true); setError(null);
        try {
            const result = await api.command(`${path}/account-link-invitations`, { account_id: values.account_id });
            setInvitation(result.data);
        } catch (failure) { setError(failure.message); }
        finally { setBusy(false); }
    };
    const synchronize = async (values) => {
        setBusy(true); setError(null);
        try { setSync((await api.command(`${path}/accounting-sync`, values)).data); }
        catch (failure) { setError(failure.message); }
        finally { setBusy(false); }
    };
    return <Space className="customers-profile-links" direction="vertical" size={18} style={{ width: '100%' }}>
        {error && <Alert type="error" showIcon message={error} />}
        {can('customers.account.link') && <Card className="customers-profile-link-card" title={<Space><UserOutlined />Tài khoản website</Space>} size="small"><Typography.Paragraph type="secondary">Tạo lời mời cho một tài khoản cụ thể. Khách cần đăng nhập đúng tài khoản và xác nhận mật khẩu hiện tại để chấp nhận liên kết hồ sơ.</Typography.Paragraph><Form form={linkForm} layout="vertical" onFinish={invite} disabled={busy}><Form.Item name="account_id" label="Tài khoản website" rules={[{ required: true }]}>{accounts.length ? <Select showSearch optionFilterProp="label" options={accounts.map((account) => ({ value: account.id, label: [account.name, account.email].filter(Boolean).join(' · ') }))} /> : <InputNumber min={1} precision={0} style={{ width: '100%' }} placeholder="Mã tài khoản website cần nhận lời mời" />}</Form.Item><Button type="primary" icon={<LinkOutlined />} htmlType="submit" loading={busy}>Tạo lời mời liên kết</Button></Form><Typography.Paragraph type="secondary" style={{ marginTop: 12, marginBottom: 0 }}>Lời mời mới thay thế lời mời cũ. Việc trùng điện thoại hoặc email không tự liên kết tài khoản.</Typography.Paragraph></Card>}
        {accountingAllowed && <Card className="customers-profile-link-card" title={<Space><BankOutlined />Đối tác kế toán</Space>} size="small"><Typography.Paragraph type="secondary">Liên kết rõ ràng hồ sơ này với đối tác kế toán theo chi nhánh và pháp nhân đã được ánh xạ. Chứng từ đã phát hành giữ nguyên thông tin lịch sử.</Typography.Paragraph><Form form={accountingForm} layout="vertical" onFinish={synchronize} disabled={busy}><Form.Item name="branch_id" label="Chi nhánh Retail" rules={[{ required: true }]}><Select showSearch optionFilterProp="label" options={branches.map((branch) => ({ value: customerId(branch), label: branch.name }))} /></Form.Item><Form.Item name="organization_id" label="Pháp nhân kế toán" rules={[{ required: true }]}>{organizationOptions.length ? <Select options={organizationOptions.map((organization) => ({ value: organization.id ?? organization.value, label: organization.name ?? organization.label }))} /> : <InputNumber min={1} precision={0} style={{ width: '100%' }} placeholder="Mã pháp nhân kế toán được cấp quyền" />}</Form.Item><Button icon={<LinkOutlined />} htmlType="submit" loading={busy}>Kiểm tra và liên kết đối tác</Button></Form>{sync && <Alert style={{ marginTop: 16 }} showIcon type="success" message={sync.created ? 'Đã tạo liên kết đối tác kế toán.' : 'Đã xác nhận liên kết đối tác hiện có.'} description={`Đối tác #${sync.party_id} · pháp nhân #${sync.organization_id}`} />}</Card>}
        {!can('customers.account.link') && !accountingAllowed && <Alert type="info" message="Chưa có tác vụ liên kết trong phạm vi được cấp quyền." showIcon />}
        <Modal rootClassName="customers-profile-modal" open={Boolean(invitation)} title={<Space><LinkOutlined />Lời mời liên kết tài khoản</Space>} onCancel={() => setInvitation(null)} footer={<Button type="primary" onClick={() => setInvitation(null)}>Đã lưu mã, đóng</Button>} maskClosable={false} destroyOnHidden>
            {invitation?.token ? <><Alert type="warning" showIcon message="Mã lời mời chỉ hiển thị một lần" description="Sao chép và cung cấp mã cho đúng chủ tài khoản. Đóng cửa sổ sẽ xóa mã khỏi phiên hiển thị này." style={{ marginBottom: 16 }} /><Input.TextArea readOnly value={invitation.token} autoSize={{ minRows: 2, maxRows: 5 }} /><Button icon={<CopyOutlined />} style={{ marginTop: 12 }} onClick={async () => { try { await navigator.clipboard.writeText(invitation.token); message.success('Đã sao chép mã lời mời.'); } catch { message.warning('Hãy chọn và sao chép mã trong ô bên trên.'); } }}>Sao chép mã</Button></> : <Alert type="info" showIcon message="Lời mời đã được ghi nhận nhưng mã không hiển thị lại" description="Nếu chưa lưu mã trước đó, đóng cửa sổ và tạo lời mời mới. Lời mời mới sẽ thay thế lời mời trước." />}
            <Descriptions style={{ marginTop: 16 }} column={1} items={[{ key: 'expires', label: 'Hết hạn', children: customerDate(invitation?.expires_at) }]} />
        </Modal>
    </Space>;
}
