import { useEffect, useMemo } from 'react';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Modal from 'antd/es/modal';
import Select from 'antd/es/select';
import Tabs from 'antd/es/tabs';
import Typography from 'antd/es/typography';

const { Text } = Typography;

function localeLabel(locale, locales) {
    return locales.find((item) => item.value === locale || item.code === locale)?.label || locale.toUpperCase();
}

export default function LandingPageFormModal({
    open,
    canManage,
    editingPage,
    locales = [],
    defaultLocale = 'vi',
    onCancel,
    onSubmit,
}) {
    const [form] = Form.useForm();
    const activeLocales = useMemo(
        () => (locales.length ? locales.map((item) => item.value || item.code).filter(Boolean) : [defaultLocale]),
        [defaultLocale, locales],
    );

    useEffect(() => {
        if (!open) {
            return;
        }

        const dataByLocale = activeLocales.reduce((carry, locale) => ({
            ...carry,
            [locale]: {
                title: editingPage?.data_by_locale?.[locale]?.title ?? (locale === defaultLocale ? editingPage?.title : ''),
                excerpt: editingPage?.data_by_locale?.[locale]?.excerpt ?? '',
                meta_title: editingPage?.data_by_locale?.[locale]?.meta_title ?? '',
                meta_description: editingPage?.data_by_locale?.[locale]?.meta_description ?? '',
            },
        }), {});

        form.setFieldsValue({
            slug: editingPage?.is_home ? 'home' : (editingPage?.slug ?? ''),
            status: editingPage?.status ?? 'draft',
            sort_order: editingPage?.sort_order ?? 0,
            data_by_locale: dataByLocale,
        });
    }, [activeLocales, defaultLocale, editingPage, form, open]);

    const handleSubmit = async () => {
        const values = await form.validateFields();
        await onSubmit({
            ...values,
            slug: editingPage?.is_home ? undefined : values.slug,
        });
    };

    return (
        <Modal
            title={editingPage?.id ? (editingPage?.is_home ? 'Cài đặt trang chủ' : 'Cập nhật landingpage') : 'Tạo landingpage'}
            open={open}
            onCancel={onCancel}
            onOk={handleSubmit}
            okText="Lưu"
            cancelText="Hủy"
            okButtonProps={{ disabled: !canManage }}
            width={760}
            destroyOnHidden
        >
            <Form form={form} layout="vertical" disabled={!canManage}>
                {editingPage?.is_home ? (
                    <Text type="secondary">Trang chủ là landingpage đặc biệt, đường dẫn luôn cố định là / và không thể đổi slug.</Text>
                ) : null}
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 160px 140px', gap: 12, marginTop: 12 }}>
                    <Form.Item name="slug" label="Slug public" tooltip="Landingpage thường sẽ có đường dẫn /land/{slug}. Trang chủ luôn là /." rules={editingPage?.is_home ? [] : [{ required: true, message: 'Nhập slug landingpage' }]}>
                        <Input disabled={editingPage?.is_home} placeholder="bao-gia-xay-dung" />
                    </Form.Item>
                    <Form.Item name="status" label="Trạng thái">
                        <Select
                            disabled={editingPage?.is_home}
                            options={[
                                { label: 'Bản nháp', value: 'draft' },
                                { label: 'Đã xuất bản', value: 'published' },
                            ]}
                        />
                    </Form.Item>
                    <Form.Item name="sort_order" label="Thứ tự">
                        <InputNumber min={0} style={{ width: '100%' }} />
                    </Form.Item>
                </div>
                <Tabs
                    items={activeLocales.map((locale) => ({
                        key: locale,
                        label: localeLabel(locale, locales),
                        children: (
                            <>
                                <Form.Item name={['data_by_locale', locale, 'title']} label="Tiêu đề" rules={[{ required: locale === defaultLocale, message: 'Nhập tiêu đề' }]}>
                                    <Input placeholder="Landingpage báo giá xây dựng" />
                                </Form.Item>
                                <Form.Item name={['data_by_locale', locale, 'excerpt']} label="Mô tả ngắn">
                                    <Input.TextArea rows={3} placeholder="Mô tả ngắn dùng trong danh sách quản lý hoặc SEO." />
                                </Form.Item>
                                <Form.Item name={['data_by_locale', locale, 'meta_title']} label="Meta title">
                                    <Input />
                                </Form.Item>
                                <Form.Item name={['data_by_locale', locale, 'meta_description']} label="Meta description">
                                    <Input.TextArea rows={3} />
                                </Form.Item>
                            </>
                        ),
                    }))}
                />
            </Form>
        </Modal>
    );
}
