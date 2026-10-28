import { useEffect, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Checkbox from 'antd/es/checkbox';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Modal from 'antd/es/modal';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import CheckCircleOutlined from '@ant-design/icons/es/icons/CheckCircleOutlined';
import MedicineBoxOutlined from '@ant-design/icons/es/icons/MedicineBoxOutlined';
import { PharmacyError } from './PharmacyCommon';
import PharmacyRemoteSelect from './PharmacyRemoteSelect';
import PharmacyProductFields from './PharmacyProductFields';

export function CommandField({ field, api }) {
    const control = field.kind ? <PharmacyRemoteSelect api={api} kind={field.kind} params={field.params} mode={field.multiple ? 'multiple' : undefined} label={field.placeholder ?? `Tìm ${field.label.toLowerCase()}`} />
        : ['select', 'tags'].includes(field.type) ? <Select optionFilterProp="label" disabled={field.disabled} mode={field.type === 'tags' ? 'tags' : field.multiple ? 'multiple' : undefined} options={field.options} placeholder={field.placeholder ?? 'Chọn giá trị'} allowClear />
        : field.type === 'checkbox' ? <Checkbox>{field.checkboxLabel ?? field.label}</Checkbox>
        : field.type === 'textarea' ? <Input.TextArea rows={3} maxLength={field.maxLength ?? 4000} showCount />
        : ['money', 'quantity', 'integer', 'number'].includes(field.type) ? <InputNumber stringMode min={field.min ?? '0'} max={field.max} precision={['money', 'integer'].includes(field.type) ? 0 : 6} controls={false} style={{ width: '100%' }} suffix={field.type === 'money' ? '₫' : undefined} />
        : <Input type={['date', 'datetime-local'].includes(field.type) ? field.type : 'text'} maxLength={field.maxLength ?? 255} placeholder={field.placeholder} autoFocus={field.autoFocus} />;
    return <Form.Item name={field.name} label={field.type === 'checkbox' ? undefined : field.label} valuePropName={field.type === 'checkbox' ? 'checked' : 'value'} rules={field.required ? [{ required: true, message: `Nhập ${field.label.toLowerCase()}.` }] : []} extra={field.help}>{control}</Form.Item>;
}

function SetupFields({ fields = [], api }) {
    const field = (name) => {
        const definition = fields.find((item) => item.name === name);
        return definition ? <CommandField field={definition} api={api} /> : null;
    };
    return <>
        <section className="ph-setup-section" aria-labelledby="ph-setup-chain-title">
            <h3 id="ph-setup-chain-title"><span aria-hidden="true">01</span>Thông tin chuỗi</h3>
            {field('chain_name')}
        </section>
        <section className="ph-setup-section" aria-labelledby="ph-setup-site-title">
            <h3 id="ph-setup-site-title"><span aria-hidden="true">02</span>Nhà thuốc đầu tiên</h3>
            <div className="ph-setup-fields">
                <div className="ph-setup-full-width">{field('pharmacy_name')}</div>
                {field('code')}
                {field('license_number')}
            </div>
        </section>
        <div className="ph-setup-note"><CheckCircleOutlined aria-hidden="true" /><span>Kho và quầy mặc định sẽ được tạo cùng nhà thuốc.</span></div>
    </>;
}

export default function PharmacyCommandDrawer({ request, api, onClose, onSubmit }) {
    const [form] = Form.useForm();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);
    useEffect(() => { if (!request) return; form.resetFields(); form.setFieldsValue(request.initial ?? {}); setError(null); }, [request, form]);
    const submit = async (values) => {
        setBusy(true); setError(null);
        try { await onSubmit(request.action, { ...request.payload, ...values }, request); onClose(); }
        catch (failure) {
            setError(failure);
            const fields = failure.payload?.errors ?? failure.details?.errors;
            if (fields) form.setFields(Object.entries(fields).map(([name, errors]) => ({ name: name.split('.').map((part) => /^\d+$/.test(part) ? Number(part) : part), errors: Array.isArray(errors) ? errors : [String(errors)] })));
        } finally { setBusy(false); }
    };
    const isSetup = request?.action === 'setup';
    const isProduct = request?.action === 'product.save';
    const productTitle = request?.payload?.product_id ? 'Cập nhật hàng hóa' : 'Thêm hàng hóa';
    const content = <>
        {request?.description && <Alert type="info" showIcon message={request.description} style={{ marginBottom: 20 }} />}
        <PharmacyError error={error} onRetry={Number(error?.status) === 409 ? request?.onReload : null} />
        <Form form={form} layout="vertical" disabled={busy} onFinish={submit} preserve={false} className={isSetup ? 'ph-setup-form' : isProduct ? 'ph-product-form' : undefined}>
            {isSetup ? <SetupFields fields={request.fields} api={api} /> : isProduct ? <PharmacyProductFields fields={request.fields} initial={request.initial} renderField={(field) => <CommandField key={String(field.name)} field={field} api={api} />} /> : request?.fields?.map((field) => <CommandField key={String(field.name)} field={field} api={api} />)}
            {!isProduct && request?.renderFields?.({ form, api })}
        </Form>
    </>;
    if (isSetup) return <Modal open centered title={<span className="ph-setup-title"><span className="ph-setup-icon" aria-hidden="true"><MedicineBoxOutlined /></span><span>{request.title}</span></span>}
        width={720} rootClassName="ph-setup-modal" destroyOnHidden maskClosable={false} closable={!busy} keyboard={!busy} onCancel={() => !busy && onClose()}
        footer={<div className="ph-setup-footer"><Button disabled={busy} onClick={onClose}>Để sau</Button><Button type="primary" loading={busy} onClick={() => form.submit()}>{request.submitLabel ?? 'Tạo nhà thuốc'}</Button></div>}>
        <p className="ph-setup-intro">Tạo chuỗi và cơ sở đầu tiên để bắt đầu quản lý nhà thuốc.</p>
        {content}
    </Modal>;
    return <Drawer open={Boolean(request)} title={isProduct ? <div className="ph-product-drawer-title"><span className="ph-product-drawer-icon" aria-hidden="true"><MedicineBoxOutlined /></span><div><strong>{productTitle}</strong><p>Danh mục dùng chung trong chuỗi · Giá theo nhà thuốc</p></div></div> : request?.title} width={request?.width ?? 600} rootClassName={isProduct ? 'ph-drawer ph-product-drawer' : 'ph-drawer'} destroyOnHidden maskClosable={false} closable={!busy} keyboard={!busy} onClose={() => !busy && onClose()}
        footer={isProduct ? <div className="ph-product-form-footer"><span className="ph-muted">Thông tin có dấu * là bắt buộc.</span><Space><Button disabled={busy} onClick={onClose}>Đóng</Button><Button type="primary" loading={busy} onClick={() => form.submit()}>{request?.payload?.product_id ? 'Lưu thay đổi' : 'Lưu hàng hóa'}</Button></Space></div> : <Space className="ph-drawer-footer"><span className="ph-muted">Thay đổi được máy chủ kiểm tra và ghi nhận.</span><Space><Button disabled={busy} onClick={onClose}>Đóng</Button><Button type="primary" loading={busy} onClick={() => form.submit()}>{request?.submitLabel ?? 'Lưu và xác nhận'}</Button></Space></Space>}>
        {content}
    </Drawer>;
}
