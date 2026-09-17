import { useEffect, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Select from 'antd/es/select';
import { adminApi } from '../../../shared/config/routes';

export default function CmsTagTranslationModal({ open, onClose, locale, options, callAdminApi, canPublish }) {
    const [form] = Form.useForm();
    const [tagId, setTagId] = useState(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);
    const [notice, setNotice] = useState(null);
    useEffect(() => { if (open) setTagId(options[0]?.id ?? null); }, [open]);
    useEffect(() => {
        if (!open || !tagId) return;
        let active = true;
        setBusy(true); setError(null); setNotice(null); form.resetFields();
        callAdminApi(adminApi(`localization/content/cms_tag/${tagId}`)).then(result => {
            if (active) form.setFieldsValue(result.data?.translations?.[locale]?.payload ?? {});
        }).catch(err => { if (active) setError(err.message); }).finally(() => { if (active) setBusy(false); });
        return () => { active = false; };
    }, [open, tagId, locale, callAdminApi, form]);
    const save = async (publish) => {
        try {
            const payload = await form.validateFields();
            setBusy(true); setError(null); setNotice(null);
            await callAdminApi(adminApi(`localization/content/cms_tag/${tagId}/${locale}`), { method: 'PUT', body: JSON.stringify({ payload, publish }) });
            setNotice(publish ? 'Đã xuất bản bản dịch tag.' : 'Đã lưu bản nháp tag.');
        } catch (err) { if (!err.errorFields) setError(err.message); }
        finally { setBusy(false); }
    };
    return <Modal title={`Dịch tags — ${locale.toUpperCase()}`} open={open} onCancel={onClose} footer={[
        <Button key="draft" disabled={!tagId} loading={busy} onClick={() => save(false)}>Lưu nháp</Button>,
        canPublish ? <Button key="publish" type="primary" disabled={!tagId} loading={busy} onClick={() => save(true)}>Xuất bản</Button> : null,
    ]}>
        <p>Bản dịch áp dụng cho tag này ở tất cả bài viết trong website.</p>
        <Select aria-label="Tag cần dịch" style={{ width: '100%', marginBottom: 16 }} value={tagId} disabled={busy} onChange={setTagId} options={options.map(tag => ({ label: tag.label, value: tag.id }))} />
        {error ? <Alert type="error" message={error} /> : null}
        {notice ? <Alert type="success" message={notice} /> : null}
        <Form name="cms-tag-translation" form={form} layout="vertical" disabled={busy}>
            <Form.Item name="name" label="Tên tag" rules={[{ required: true, whitespace: true, max: 80 }]}><Input maxLength={80} /></Form.Item>
            <Form.Item name="slug" label="Slug" extra="Để trống để tự tạo từ tên tag."><Input maxLength={255} /></Form.Item>
        </Form>
    </Modal>;
}
