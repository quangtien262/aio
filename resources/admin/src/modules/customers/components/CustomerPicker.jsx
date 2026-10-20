import { useEffect, useRef, useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Spin from 'antd/es/spin';

export default function CustomerPicker({ value, selectedOption, onChange, loadOptions, createCustomer, disabled = false, loading = false, placeholder = 'Tìm tên, mã hoặc điện thoại khách hàng', size, allowClear = true }) {
    const [options, setOptions] = useState([]);
    const [selected, setSelected] = useState(selectedOption ?? null);
    const [query, setQuery] = useState('');
    const [searching, setSearching] = useState(false);
    const [creating, setCreating] = useState(false);
    const [open, setOpen] = useState(false);
    const [error, setError] = useState(null);
    const [createError, setCreateError] = useState(null);
    const [form] = Form.useForm();
    const generation = useRef(0);
    const loadRef = useRef(loadOptions);
    loadRef.current = loadOptions;
    useEffect(() => { if (selectedOption) setSelected(selectedOption); }, [selectedOption]);
    useEffect(() => {
        const request = ++generation.current;
        if (disabled) return undefined;
        const timer = window.setTimeout(async () => {
            setSearching(true); setError(null);
            try {
                const result = await loadRef.current(query.trim());
                if (request === generation.current) setOptions(Array.isArray(result) ? result : []);
            } catch (failure) { if (request === generation.current) setError(failure.message); }
            finally { if (request === generation.current) setSearching(false); }
        }, 250);
        return () => { window.clearTimeout(timer); generation.current += 1; };
    }, [query, disabled]);
    const current = selectedOption ?? selected;
    const choices = current && String(current.value) === String(value) && !options.some((item) => String(item.value) === String(value)) ? [current, ...options] : options;
    const save = async (values) => {
        setCreating(true); setCreateError(null);
        try {
            const created = await createCustomer({ name: values.name.trim(), phone: values.phone?.trim() || undefined, email: values.email?.trim() || undefined });
            if (!created?.value) throw new Error('Chưa nhận được định danh khách hàng. Kiểm tra kết quả trước khi tạo lại.');
            setSelected(created); setOptions((items) => [created, ...items.filter((item) => item.value !== created.value)]);
            onChange?.(created.value, created); setOpen(false); form.resetFields();
        } catch (failure) { setCreateError(failure.message); }
        finally { setCreating(false); }
    };
    return <div style={{ width: '100%' }}>
        <Space.Compact style={{ width: '100%' }}><Select showSearch allowClear={allowClear} size={size} disabled={disabled || creating} value={value ?? undefined} options={choices} optionFilterProp="label" filterOption={false} onSearch={setQuery} onChange={(next, option) => { setSelected(option ?? null); onChange?.(next ?? null, option ?? null); }} loading={loading || searching} notFoundContent={searching ? <Spin size="small" /> : 'Chưa có khách phù hợp'} placeholder={placeholder} style={{ width: '100%' }} />{createCustomer && <Button size={size} disabled={disabled || creating} onClick={() => { form.resetFields(); setCreateError(null); setOpen(true); }}>Thêm mới</Button>}</Space.Compact>
        {error && <Alert type="error" showIcon message={error} style={{ marginTop: 8 }} />}
        <Modal open={open} title="Thêm nhanh khách hàng" width={480} destroyOnHidden maskClosable={false} onCancel={() => !creating && setOpen(false)} confirmLoading={creating} onOk={() => form.submit()} okText="Lưu và chọn khách" cancelText="Hủy" cancelButtonProps={{ disabled: creating }} closable={!creating}>
            {createError && <Alert type="error" showIcon message={createError} style={{ marginBottom: 16 }} />}
            <Form form={form} layout="vertical" disabled={creating} onFinish={save}>
                <Form.Item name="name" label="Tên khách hàng" rules={[{ required: true, whitespace: true, message: 'Nhập tên khách hàng.' }]}><Input autoFocus maxLength={255} /></Form.Item>
                <Form.Item name="phone" label="Điện thoại"><Input type="tel" maxLength={40} /></Form.Item>
                <Form.Item name="email" label="Email" rules={[{ type: 'email', message: 'Email chưa hợp lệ.' }]}><Input type="email" maxLength={255} /></Form.Item>
                <div style={{ color: 'var(--ant-color-text-secondary)', fontSize: 12 }}>Chỉ nhập thông tin cần thiết. Lựa chọn nhận liên hệ được quản lý riêng trong hồ sơ.</div>
            </Form>
        </Modal>
    </div>;
}
