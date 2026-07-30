import { useMemo, useState } from 'react';
import Alert from 'antd/es/alert';
import message from 'antd/es/message';
import Modal from 'antd/es/modal';
import Space from 'antd/es/space';
import Tabs from 'antd/es/tabs';
import Tag from 'antd/es/tag';

const translationStatusLabels = {
    missing: 'Chưa có',
    needs_translation: 'Cần dịch',
    draft: 'Bản nháp',
    machine_draft: 'Bản dịch máy',
    in_review: 'Đang duyệt',
    ready: 'Sẵn sàng',
    published: 'Đã xuất bản',
    outdated: 'Cần cập nhật',
};

const translationStatusColors = {
    needs_translation: 'orange',
    draft: 'default',
    machine_draft: 'cyan',
    in_review: 'gold',
    ready: 'blue',
    published: 'green',
    outdated: 'orange',
};

export default function LocalizedContentTabs({
    localeOptions = [],
    contentLocale = 'vi',
    sourceLocale = 'vi',
    editingRecord,
    entityLabel = 'nội dung',
    translationDescription,
    sourceDescription,
    isDirty,
    getCurrentValues,
    onLocaleChange,
}) {
    const [messageApi, messageContextHolder] = message.useMessage();
    const [modalApi, modalContextHolder] = Modal.useModal();
    const [switchingLocale, setSwitchingLocale] = useState(false);
    const editableLocales = useMemo(() => (
        localeOptions.map((locale) => ({
            code: locale.code ?? locale.value,
            name: locale.native_name || locale.name || locale.label || locale.code || locale.value,
            isSource: locale.is_source ?? (locale.code ?? locale.value) === sourceLocale,
        })).filter((locale) => Boolean(locale.code))
    ), [localeOptions, sourceLocale]);
    const translationStatuses = editingRecord?._translation_statuses ?? {};
    const activeTranslationStatus = editingRecord?._translation_status
        ?? translationStatuses[contentLocale]
        ?? (contentLocale === sourceLocale
            ? (editingRecord?.id ? 'published' : 'draft')
            : 'missing');
    const translationMode = contentLocale !== sourceLocale;
    const isCreating = !editingRecord?.id;
    const activeLocaleName = editableLocales.find((locale) => locale.code === contentLocale)?.name
        || contentLocale.toUpperCase();

    const switchLocale = async (nextLocale) => {
        if (
            nextLocale === contentLocale
            || switchingLocale
            || !onLocaleChange
        ) {
            return;
        }

        setSwitchingLocale(true);

        try {
            const didSwitch = await onLocaleChange(nextLocale, getCurrentValues?.());

            if (didSwitch === false) {
                messageApi.error('Không thể tải nội dung của ngôn ngữ đã chọn.');
            }
        } finally {
            setSwitchingLocale(false);
        }
    };

    const handleLocaleChange = (nextLocale) => {
        if (isCreating) {
            void switchLocale(nextLocale);
            return;
        }

        if (!isDirty?.()) {
            void switchLocale(nextLocale);
            return;
        }

        modalApi.confirm({
            title: 'Chuyển ngôn ngữ nhập liệu?',
            content: 'Các thay đổi chưa lưu trong ngôn ngữ hiện tại sẽ bị bỏ.',
            okText: 'Chuyển ngôn ngữ',
            cancelText: 'Ở lại',
            onOk: () => switchLocale(nextLocale),
        });
    };

    if (!editableLocales.length) {
        return null;
    }

    return (
        <>
            {messageContextHolder}
            {modalContextHolder}
            <Tabs
                activeKey={contentLocale}
                onChange={handleLocaleChange}
                items={editableLocales.map((locale) => {
                    const status = translationStatuses[locale.code]
                        ?? (locale.code === contentLocale ? activeTranslationStatus : 'missing');

                    return {
                        key: locale.code,
                        disabled: switchingLocale,
                        label: (
                            <Space size={6}>
                                <span>{locale.name}</span>
                                {locale.isSource ? <Tag>Gốc</Tag> : null}
                                <Tag color={translationStatusColors[status]}>
                                    {translationStatusLabels[status] ?? status}
                                </Tag>
                            </Space>
                        ),
                    };
                })}
            />
            <Alert
                type={translationMode ? 'info' : 'success'}
                showIcon
                message={`Đang nhập ${activeLocaleName}`}
                description={translationMode
                    ? `${translationDescription
                        || `Các trường nội dung và trạng thái xuất bản của ${entityLabel} được lưu riêng cho ngôn ngữ này. Dữ liệu vận hành dùng chung tiếp tục lấy từ bản gốc.`}${isCreating
                        ? ' Nội dung đang được giữ tạm; quay lại ngôn ngữ gốc và bấm Lưu để tạo bản gốc cùng các bản dịch nháp.'
                        : ''}`
                    : (sourceDescription
                        || `Đây là ngôn ngữ gốc của ${entityLabel}. Dữ liệu dùng chung được quản lý tại đây.`)}
                style={{ marginBottom: 16 }}
            />
        </>
    );
}
