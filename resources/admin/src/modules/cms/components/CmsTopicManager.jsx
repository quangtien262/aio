import { useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Popconfirm from 'antd/es/popconfirm';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Switch from 'antd/es/switch';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import FolderOutlined from '@ant-design/icons/FolderOutlined';
import { adminApi } from '../../../shared/config/routes';

const slugify = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[đĐ]/g, 'd').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

export default function CmsTopicManager({ callAdminApi, canManage, onChanged, localeOptions = [], sourceLocale = 'vi', renderTrigger }) {
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState([]);
    const [editing, setEditing] = useState(null);
    const [locale, setLocale] = useState(sourceLocale);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [form] = Form.useForm();
    const translated = locale !== sourceLocale;
    const load = async () => {
        setBusy(true); setError('');
        try { const result = await callAdminApi(adminApi('cms/topics')); setItems(result.data?.items ?? []); }
        catch (err) { setError(err.message); }
        finally { setBusy(false); }
    };
    const edit = async (item, nextLocale = sourceLocale) => {
        setEditing(item); setLocale(nextLocale); setError(''); form.resetFields();
        if (nextLocale === sourceLocale) { form.setFieldsValue({ is_active: true, ...item }); return; }
        setBusy(true);
        try {
            const result = await callAdminApi(adminApi(`localization/content/cms_topic/${item.id}`));
            form.setFieldsValue(result.data?.translations?.[nextLocale]?.payload ?? {});
        } catch (err) { setError(err.message); }
        finally { setBusy(false); }
    };
    const save = async (publish = false) => {
        try {
            const values = await form.validateFields(); setBusy(true); setError('');
            const url = translated ? `localization/content/cms_topic/${editing.id}/${locale}` : `cms/topics${editing.id ? `/${editing.id}` : ''}`;
            await callAdminApi(adminApi(url), { method: translated || editing.id ? 'PUT' : 'POST', body: JSON.stringify(translated ? { payload: values, publish } : values) });
            setEditing(null); await load(); await onChanged?.();
        } catch (err) {
            if (!err.errorFields) {
                const errors = err?.payload?.errors ?? err?.payload?.details?.errors ?? {};
                form.setFields(Object.entries(errors).map(([name, messages]) => ({ name: name.replace(/^payload\./, ''), errors: Array.isArray(messages) ? messages : [messages] })));
                setError(Object.values(errors).flat()[0] || err.message || 'Không lưu được chuyên đề.');
            }
        } finally { setBusy(false); }
    };
    const remove = async item => {
        setBusy(true); setError('');
        try {
            await callAdminApi(adminApi(`cms/topics/${item.id}`), { method: 'DELETE' });
            await load(); await onChanged?.();
        } catch (err) { setError(err.message); }
        finally { setBusy(false); }
    };
    return <>
        {renderTrigger
            ? renderTrigger(() => { setOpen(true); load(); })
            : <Button icon={<FolderOutlined />} onClick={() => { setOpen(true); load(); }}>QL chuyên đề</Button>}
        <Modal title="Quản lý chuyên đề tin tức" open={open} width={800} footer={null} onCancel={() => { if (!busy) setOpen(false); }}>
            {!editing && error && <Alert type="error" showIcon message={error} style={{ marginBottom: 16 }} />}
            <Button type="primary" disabled={!canManage || busy} onClick={() => edit({})} style={{ marginBottom: 16 }}>Thêm chuyên đề</Button>
            <Table loading={busy} rowKey="id" dataSource={items} pagination={{ pageSize: 10 }} columns={[
                { title: 'Chuyên đề', dataIndex: 'name', render: (name, item) => <><strong>{name}</strong><div style={{ color: '#888' }}>{item.slug}</div></> },
                { title: 'Bài viết', dataIndex: 'posts_count', width: 85 },
                { title: 'Trạng thái', dataIndex: 'is_active', render: active => <Tag color={active ? 'green' : 'default'}>{active ? 'Hiển thị' : 'Ẩn'}</Tag> },
                { title: 'Thao tác', render: (_, item) => <Space><Button disabled={!canManage || busy} onClick={() => edit(item)}>Sửa</Button><Popconfirm title="Xóa chuyên đề?" description="Bài viết được giữ lại, chỉ gỡ liên kết với chuyên đề này." onConfirm={() => remove(item)} okText="Xóa" cancelText="Hủy"><Button danger disabled={!canManage || busy}>Xóa</Button></Popconfirm></Space> },
            ]} />
        </Modal>
        <Modal title={editing?.id ? 'Cập nhật chuyên đề' : 'Thêm chuyên đề'} open={editing !== null} onCancel={() => { if (!busy) setEditing(null); }} footer={<Space><Button disabled={busy} onClick={() => setEditing(null)}>Hủy</Button><Button loading={busy} disabled={!canManage} type="primary" onClick={() => save(false)}>{translated ? 'Lưu nháp' : 'Lưu chuyên đề'}</Button>{translated && <Button disabled={!canManage} loading={busy} onClick={() => save(true)}>Xuất bản bản dịch</Button>}</Space>}>
            {error && <Alert type="error" showIcon message={error} style={{ marginBottom: 16 }} />}
            {editing?.id && <Select aria-label="Ngôn ngữ chuyên đề" value={locale} disabled={busy} style={{ width: '100%', marginBottom: 16 }} onChange={value => edit(editing, value)} options={localeOptions.map(option => ({ value: option.code ?? option.value, label: option.native_name ?? option.label ?? option.name ?? option.code }))} />}
            <Form name="cms-topic" form={form} layout="vertical" disabled={busy || !canManage} onValuesChange={changes => { if (changes.name !== undefined && !editing?.id) form.setFieldValue('slug', slugify(changes.name)); }}>
                <Form.Item name="name" label="Tên chuyên đề" rules={[{ required: true, whitespace: true, message: 'Nhập tên chuyên đề' }, { max: 255 }]}><Input /></Form.Item>
                <Form.Item name="slug" label="Slug" rules={[{ required: true, message: 'Nhập slug' }, { pattern: /^[a-z0-9]+(?:-[a-z0-9]+)*$/, message: 'Dùng chữ thường không dấu, số và dấu gạch ngang.' }]}><Input /></Form.Item>
                <Form.Item name="description" label="Mô tả"><Input.TextArea rows={3} /></Form.Item>
                {!translated && <Form.Item name="image_url" label="Đường dẫn ảnh đại diện" rules={[{ type: 'url', message: 'Nhập URL ảnh đầy đủ https://...' }]}><Input placeholder="https://..." /></Form.Item>}
                <Form.Item name="meta_title" label="SEO Title" rules={[{ max: 255 }]}><Input /></Form.Item>
                <Form.Item name="meta_description" label="SEO Description" rules={[{ max: 1000 }]}><Input.TextArea rows={2} /></Form.Item>
                {!translated && <Form.Item name="is_active" label="Hiển thị chuyên đề" valuePropName="checked"><Switch /></Form.Item>}
            </Form>
        </Modal>
    </>;
}
