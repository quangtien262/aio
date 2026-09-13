import { useEffect, useMemo, useRef, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Descriptions from 'antd/es/descriptions';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Space from 'antd/es/space';
import Spin from 'antd/es/spin';
import Steps from 'antd/es/steps';
import Typography from 'antd/es/typography';

const { Paragraph, Text } = Typography;

const proofToken = (resource) => resource?.reauth_proof ?? resource?.token ?? null;
const approvalToken = (resource) => resource?.approval_token ?? resource?.token ?? null;

function ApprovalPoller({ onPoll }) {
    useEffect(() => {
        const timer = window.setInterval(() => {
            if (document.visibilityState !== 'hidden') onPoll();
        }, 3000);

        return () => window.clearInterval(timer);
    }, [onPoll]);

    return null;
}

export default function FnbCriticalActionDrawer({ request, api, onClose, onCompleted, onConflict }) {
    const [form] = Form.useForm();
    const [submitting, setSubmitting] = useState(false);
    const [stage, setStage] = useState('reauth');
    const [error, setError] = useState(null);
    const [proof, setProof] = useState(null);
    const [approval, setApproval] = useState(null);
    const approvalPollInFlight = useRef(false);
    const details = request?.error?.details ?? request?.details ?? {};
    const descriptor = useMemo(() => ({
        action: details.action ?? request?.action,
        subject: details.subject ?? request?.subject ?? null,
        payload_hash: details.payload_hash ?? request?.payloadHash ?? null,
        policy_key: details.policy_key ?? request?.policyKey ?? null,
    }), [details, request]);

    useEffect(() => {
        if (!request) return;
        setStage('reauth');
        setError(null);
        setProof(null);
        setApproval(null);
        approvalPollInFlight.current = false;
        form.resetFields();
    }, [form, request]);

    if (!request) {
        return null;
    }

    const retryOriginal = async ({ reauthProof, approvalToken: resolvedApprovalToken = null }) => {
        const result = await request.execute({
            reauthProof,
            approvalToken: resolvedApprovalToken,
        });

        await onCompleted(result);
    };

    const createApproval = async (reauthProof, approvalError) => {
        const approvalDetails = approvalError?.details ?? details;
        const result = await api.command('approvals', {
            action: approvalDetails.action ?? descriptor.action,
            subject: approvalDetails.subject ?? descriptor.subject,
            payload_hash: approvalDetails.payload_hash ?? descriptor.payload_hash,
            policy_key: approvalDetails.policy_key ?? descriptor.policy_key,
            reason: request.reason ?? null,
        }, {
            scope: 'approval-request',
            reauthProof,
            idempotencyKey: request.approvalIdempotencyKey,
        });
        const resource = result.data ?? {};
        const token = approvalToken(resource);

        if (token) {
            await retryOriginal({ reauthProof, approvalToken: token });
            return;
        }

        setApproval(resource);
        setStage('approval');
    };

    const handleReauth = async () => {
        const values = await form.validateFields();
        setSubmitting(true);
        setError(null);

        try {
            const result = await api.command('reauth/proofs', {
                ...descriptor,
                password: values.password,
                two_factor_code: values.two_factor_code || null,
            }, {
                scope: 'reauth-proof',
                idempotencyKey: request.reauthIdempotencyKey,
            });
            const token = proofToken(result.data);

            if (!token) {
                throw new Error('Máy chủ không trả bằng chứng xác thực hợp lệ.');
            }

            setProof(token);
            form.resetFields();

            try {
                await retryOriginal({ reauthProof: token });
            } catch (commandError) {
                if (commandError.status === 403 && commandError.code === 'FNB_APPROVAL_REQUIRED') {
                    await createApproval(token, commandError);
                    return;
                }

                throw commandError;
            }
        } catch (exception) {
            if (exception.status === 409) {
                onConflict?.(exception, request.onConflict);
                return;
            }
            setError(exception);
        } finally {
            setSubmitting(false);
        }
    };

    const refreshApproval = async () => {
        if ((!approval?.id && !approval?.public_id) || approvalPollInFlight.current) {
            return;
        }

        approvalPollInFlight.current = true;
        setSubmitting(true);
        setError(null);

        try {
            const identity = approval.id ?? approval.public_id;
            const result = await api.read(`approvals/${encodeURIComponent(identity)}`);
            const resource = result.data ?? {};

            setApproval(resource);

            if (resource.status === 'approved') {
                const claimed = await api.command(`approvals/${encodeURIComponent(identity)}/claim-token`, {
                    expected_version: resource.version,
                }, {
                    scope: 'approval-claim',
                    reauthProof: proof,
                    idempotencyKey: request.approvalClaimIdempotencyKey,
                });
                const token = approvalToken(claimed.data);

                if (!token) {
                    throw new Error('Máy chủ chưa cấp bằng chứng phê duyệt cho phiên yêu cầu này.');
                }

                await retryOriginal({ reauthProof: proof, approvalToken: token });
            }
        } catch (exception) {
            if (exception.status === 409) {
                onConflict?.(exception, request.onConflict);
                return;
            }
            setError(exception);
        } finally {
            approvalPollInFlight.current = false;
            setSubmitting(false);
        }
    };

    const requiredPermissions = details.required_permissions ?? [];

    return (
        <Drawer
            title="Xác nhận thao tác nhạy cảm"
            open
            width="min(560px, 96vw)"
            onClose={submitting ? undefined : onClose}
            destroyOnHidden
            extra={stage === 'reauth'
                ? <Button type="primary" loading={submitting} onClick={handleReauth}>Xác nhận và tiếp tục</Button>
                : <Button type="primary" loading={submitting} onClick={refreshApproval}>Kiểm tra phê duyệt</Button>}
        >
            <Space direction="vertical" size={20} style={{ width: '100%' }}>
                <Steps
                    size="small"
                    current={stage === 'approval' ? 1 : 0}
                    items={[{ title: 'Xác thực lại' }, { title: 'Phê duyệt' }, { title: 'Hoàn tất' }]}
                />

                <Alert
                    type="warning"
                    showIcon
                    message={request.title ?? 'Thao tác cần xác minh danh tính'}
                    description="Hệ thống sẽ dùng lại đúng payload và mã chống trùng ban đầu sau khi xác thực."
                />

                {error ? (
                    <Alert
                        type="error"
                        showIcon
                        message={error.message || 'Không thể tiếp tục thao tác.'}
                        description={error.requestId ? `Mã đối soát: ${error.requestId}` : null}
                    />
                ) : null}

                <Descriptions size="small" column={1} bordered items={[
                    { key: 'action', label: 'Thao tác', children: descriptor.action || 'Theo chính sách điểm bán' },
                    { key: 'policy', label: 'Chính sách', children: descriptor.policy_key || 'Máy chủ quyết định' },
                    ...(requiredPermissions.length ? [{ key: 'permissions', label: 'Quyền duyệt', children: requiredPermissions.join(', ') }] : []),
                ]} />

                {stage === 'reauth' ? (
                    <Form form={form} layout="vertical" autoComplete="off">
                        <Form.Item
                            name="password"
                            label="Mật khẩu hiện tại"
                            rules={[{ required: true, message: 'Nhập mật khẩu hiện tại.' }]}
                        >
                            <Input.Password autoComplete="current-password" />
                        </Form.Item>
                        <Form.Item
                            name="two_factor_code"
                            label="Mã xác thực hai lớp"
                            extra="Nhập khi tài khoản đã bật xác thực hai lớp."
                        >
                            <Input inputMode="numeric" autoComplete="one-time-code" maxLength={12} />
                        </Form.Item>
                    </Form>
                ) : (
                    <Space direction="vertical" size={12} style={{ width: '100%' }}>
                        {approval?.status === 'pending' ? <ApprovalPoller onPoll={refreshApproval} /> : null}
                        <Alert
                            type={approval?.status === 'rejected' ? 'error' : 'info'}
                            showIcon
                            message={approval?.status === 'rejected' ? 'Yêu cầu đã bị từ chối' : 'Đang chờ người có quyền phê duyệt'}
                            description="Người duyệt thực hiện trên phiên đăng nhập của họ. Không chia sẻ mật khẩu hoặc mã xác thực."
                        />
                        <Text>Mã yêu cầu: <Text copyable strong>{approval?.public_id ?? approval?.id ?? 'Đang tạo'}</Text></Text>
                        {submitting ? <Spin size="small" /> : null}
                        <Paragraph type="secondary" style={{ marginBottom: 0 }}>
                            Sau khi được duyệt, chọn “Kiểm tra phê duyệt”. Bằng chứng chỉ dùng một lần và gắn với đúng thao tác này.
                        </Paragraph>
                    </Space>
                )}
            </Space>
        </Drawer>
    );
}
