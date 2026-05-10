import { useEffect, useMemo, useRef, useState } from 'react';
import Button from 'antd/es/button';
import Input from 'antd/es/input';
import message from 'antd/es/message';
import Modal from 'antd/es/modal';
import Pagination from 'antd/es/pagination';
import Radio from 'antd/es/radio';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';

const { Text } = Typography;
const EMPTY_MEDIA_OPTIONS = [];

export default function SingleMediaPicker({
    open,
    value,
    onChange,
    canManage,
    callAdminApi,
    mediaOptions = EMPTY_MEDIA_OPTIONS,
    recordTitle = '',
    previewTitle = 'Ảnh đã chọn',
    uploadButtonLabel = 'Upload ảnh',
    uploadHint = 'Ảnh upload xong sẽ tự được gán vào trường hiện tại.',
    libraryButtonLabel = 'Mở thư viện media',
    libraryHint = 'Chọn lại từ media CMS đã có sẵn.',
    urlPlaceholder = 'https://example.com/image.jpg',
    urlButtonLabel = 'Lưu URL vào media',
    urlHint = 'URL sẽ được lưu vào CMS media để tái sử dụng về sau.',
    libraryModalTitle = 'Chọn ảnh từ thư viện',
    searchPlaceholder = 'Tìm theo tên media hoặc URL',
    uploadSuccessMessage = 'Đã upload và gán ảnh.',
    urlSuccessMessage = 'Đã lưu URL vào thư viện media và gán ảnh.',
    uploadErrorMessage = 'Upload ảnh không thành công.',
    urlErrorMessage = 'Không thể lưu ảnh từ URL.',
    emptyValueMessage = 'Nhập URL ảnh trước khi lưu.',
}) {
    const [messageApi, messageContextHolder] = message.useMessage();
    const [uploadingAsset, setUploadingAsset] = useState(null);
    const [mediaMode, setMediaMode] = useState('upload');
    const [mediaLibraryOpen, setMediaLibraryOpen] = useState(false);
    const [mediaLibraryPage, setMediaLibraryPage] = useState(1);
    const [mediaKeyword, setMediaKeyword] = useState('');
    const [mediaUrl, setMediaUrl] = useState('');
    const [availableMediaOptions, setAvailableMediaOptions] = useState(mediaOptions);
    const uploadInputRef = useRef(null);

    useEffect(() => {
        setMediaMode(value ? 'library' : 'upload');
        setMediaLibraryPage(1);
        setMediaKeyword('');
        setMediaUrl(value ?? '');
        setMediaLibraryOpen(false);
    }, [value, open]);

    useEffect(() => {
        setAvailableMediaOptions((currentOptions) => {
            const nextMap = new Map(currentOptions.map((item) => [item.id, item]));

            mediaOptions.forEach((item) => {
                nextMap.set(item.id, item);
            });

            return Array.from(nextMap.values());
        });
    }, [mediaOptions]);

    useEffect(() => {
        if (!open || !callAdminApi) {
            return undefined;
        }

        let isActive = true;

        callAdminApi('/admin/api/cms/media')
            .then((payload) => {
                if (!isActive) {
                    return;
                }

                const nextItems = payload?.data?.items ?? [];

                setAvailableMediaOptions((currentOptions) => {
                    const nextMap = new Map(currentOptions.map((item) => [item.id, item]));

                    nextItems.forEach((item) => {
                        nextMap.set(item.id, item);
                    });

                    return Array.from(nextMap.values());
                });
            })
            .catch(() => {
                if (isActive) {
                    setAvailableMediaOptions((currentOptions) => currentOptions);
                }
            });

        return () => {
            isActive = false;
        };
    }, [open, callAdminApi]);

    const selectedMedia = useMemo(() => availableMediaOptions.find((item) => item.file_url === value) ?? null, [availableMediaOptions, value]);

    const filteredMediaOptions = useMemo(() => {
        const normalizedKeyword = mediaKeyword.trim().toLowerCase();

        if (!normalizedKeyword) {
            return availableMediaOptions;
        }

        return availableMediaOptions.filter((item) => [item.title, item.file_url]
            .some((candidate) => String(candidate ?? '').toLowerCase().includes(normalizedKeyword)));
    }, [mediaKeyword, availableMediaOptions]);

    const mediaPageSize = 8;
    const paginatedMediaOptions = useMemo(() => {
        const startIndex = (mediaLibraryPage - 1) * mediaPageSize;

        return filteredMediaOptions.slice(startIndex, startIndex + mediaPageSize);
    }, [filteredMediaOptions, mediaLibraryPage]);

    const createMediaRecord = async ({ file, fileUrl }) => {
        if (!callAdminApi) {
            throw new Error('Thiếu cấu hình media library.');
        }

        const formData = new FormData();

        if (file) {
            formData.append('file', file);
        }

        if (fileUrl) {
            formData.append('file_url', fileUrl);
        }

        if (recordTitle) {
            formData.append('title', recordTitle);
        } else if (file?.name) {
            formData.append('title', file.name.replace(/\.[^.]+$/, ''));
        }

        const payload = await callAdminApi('/admin/api/cms/media', {
            method: 'POST',
            body: formData,
        });

        if (!payload?.data?.file_url) {
            throw new Error('Không thể lưu media.');
        }

        return payload.data;
    };

    const selectMedia = (media) => {
        setAvailableMediaOptions((currentOptions) => [media, ...currentOptions.filter((item) => item.id !== media.id)]);
        onChange?.(media.file_url);
    };

    const handleUpload = async (event) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        setUploadingAsset('upload');

        try {
            const media = await createMediaRecord({ file });

            selectMedia(media);
            messageApi.success(uploadSuccessMessage);
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : uploadErrorMessage);
        } finally {
            setUploadingAsset(null);
            event.target.value = '';
        }
    };

    const handleCreateFromUrl = async () => {
        const trimmedUrl = String(mediaUrl ?? '').trim();

        if (!trimmedUrl) {
            messageApi.warning(emptyValueMessage);
            return;
        }

        setUploadingAsset('url');

        try {
            const media = await createMediaRecord({ fileUrl: trimmedUrl });

            selectMedia(media);
            messageApi.success(urlSuccessMessage);
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : urlErrorMessage);
        } finally {
            setUploadingAsset(null);
        }
    };

    const renderPreview = () => {
        if (!value) {
            return null;
        }

        return (
            <div className="cms-featured-media-preview">
                <img src={value} alt={selectedMedia?.title || previewTitle} />
                <div className="cms-featured-media-preview-copy">
                    <strong>{selectedMedia?.title || recordTitle || previewTitle}</strong>
                    <span>{value}</span>
                </div>
                <Button size="small" onClick={() => onChange?.('')}>Bỏ chọn</Button>
            </div>
        );
    };

    return (
        <>
            {messageContextHolder}
            <div className="cms-featured-media-shell">
                <Radio.Group
                    value={mediaMode}
                    onChange={(event) => setMediaMode(event.target.value)}
                    optionType="button"
                    buttonStyle="solid"
                    className="cms-featured-media-mode"
                    options={[
                        { label: 'Upload ảnh trực tiếp', value: 'upload' },
                        { label: 'Chọn từ thư viện', value: 'library' },
                        { label: 'Nhập từ URL', value: 'url' },
                    ]}
                />

                {mediaMode === 'upload' ? (
                    <div className="cms-featured-media-action-card">
                        <input ref={uploadInputRef} type="file" accept="image/*" style={{ display: 'none' }} onChange={handleUpload} />
                        <Space direction="vertical" size={10} style={{ width: '100%' }}>
                            <Space wrap>
                                <Button
                                    type="primary"
                                    disabled={!canManage || !callAdminApi}
                                    loading={uploadingAsset === 'upload'}
                                    onClick={() => uploadInputRef.current?.click()}
                                >
                                    {uploadButtonLabel}
                                </Button>
                                <Text type="secondary">{uploadHint}</Text>
                            </Space>
                            {renderPreview()}
                        </Space>
                    </div>
                ) : null}

                {mediaMode === 'library' ? (
                    <div className="cms-featured-media-action-card">
                        <Space direction="vertical" size={10} style={{ width: '100%' }}>
                            <Space wrap>
                                <Button type="primary" onClick={() => setMediaLibraryOpen(true)}>{libraryButtonLabel}</Button>
                                <Text type="secondary">{libraryHint}</Text>
                            </Space>
                            {renderPreview()}
                        </Space>
                    </div>
                ) : null}

                {mediaMode === 'url' ? (
                    <div className="cms-featured-media-action-card">
                        <Space direction="vertical" size={10} style={{ width: '100%' }}>
                            <Input value={mediaUrl} onChange={(event) => setMediaUrl(event.target.value)} placeholder={urlPlaceholder} />
                            <Space wrap>
                                <Button
                                    type="primary"
                                    disabled={!canManage || !callAdminApi}
                                    loading={uploadingAsset === 'url'}
                                    onClick={handleCreateFromUrl}
                                >
                                    {urlButtonLabel}
                                </Button>
                                <Text type="secondary">{urlHint}</Text>
                            </Space>
                            {renderPreview()}
                        </Space>
                    </div>
                ) : null}
            </div>

            <Modal
                title={libraryModalTitle}
                open={mediaLibraryOpen}
                onCancel={() => setMediaLibraryOpen(false)}
                footer={null}
                width={920}
                destroyOnHidden
            >
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Input.Search
                        allowClear
                        value={mediaKeyword}
                        onChange={(event) => {
                            setMediaKeyword(event.target.value);
                            setMediaLibraryPage(1);
                        }}
                        placeholder={searchPlaceholder}
                    />

                    <div className="cms-featured-media-library-grid">
                        {paginatedMediaOptions.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                className={`cms-featured-media-library-item${item.file_url === value ? ' is-selected' : ''}`}
                                onClick={() => {
                                    onChange?.(item.file_url);
                                    setMediaLibraryOpen(false);
                                }}
                            >
                                <div className="cms-featured-media-library-thumb">
                                    {item.file_url ? <img src={item.file_url} alt={item.title} /> : null}
                                </div>
                                <div className="cms-featured-media-library-copy">
                                    <strong>{item.title || `Media #${item.id}`}</strong>
                                    <span>{item.file_url || 'Không có URL'}</span>
                                </div>
                            </button>
                        ))}
                    </div>

                    <Pagination
                        current={mediaLibraryPage}
                        pageSize={mediaPageSize}
                        total={filteredMediaOptions.length}
                        showSizeChanger={false}
                        onChange={setMediaLibraryPage}
                    />
                </Space>
            </Modal>
        </>
    );
}
