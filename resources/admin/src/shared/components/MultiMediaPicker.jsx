import { useEffect, useMemo, useRef, useState } from 'react';
import Button from 'antd/es/button';
import Input from 'antd/es/input';
import message from 'antd/es/message';
import Modal from 'antd/es/modal';
import Pagination from 'antd/es/pagination';
import Radio from 'antd/es/radio';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';

const { Text, Paragraph } = Typography;
const { TextArea } = Input;
const EMPTY_MEDIA_OPTIONS = [];

function normalizeMediaList(value) {
    if (Array.isArray(value)) {
        return value.map((item) => String(item ?? '').trim()).filter(Boolean);
    }

    return String(value ?? '')
        .split(/\r?\n/)
        .map((item) => item.trim())
        .filter(Boolean);
}

export default function MultiMediaPicker({
    open,
    value,
    onChange,
    canManage,
    callAdminApi,
    mediaOptions = EMPTY_MEDIA_OPTIONS,
    recordTitle = '',
    previewTitle = 'Ảnh đã chọn',
    uploadButtonLabel = 'Upload nhiều ảnh',
    uploadHint = 'Mỗi lần có thể chọn nhiều ảnh và tự thêm vào gallery.',
    libraryButtonLabel = 'Mở thư viện media',
    libraryHint = 'Chọn nhiều ảnh có sẵn từ CMS media rồi thêm vào gallery.',
    urlPlaceholder = 'https://cdn.example.com/product-1.jpg\nhttps://cdn.example.com/product-2.jpg',
    urlButtonLabel = 'Lưu URL và thêm vào gallery',
    urlHint = 'Mỗi dòng là một URL ảnh, hệ thống sẽ lưu vào CMS media để tái sử dụng.',
    libraryModalTitle = 'Chọn ảnh từ thư viện',
    searchPlaceholder = 'Tìm theo tên media hoặc URL',
    uploadSuccessMessage = 'Đã thêm ảnh vào gallery.',
    urlSuccessMessage = 'Đã lưu URL vào thư viện media và thêm ảnh.',
    uploadErrorMessage = 'Upload gallery không thành công.',
    urlErrorMessage = 'Không thể lưu ảnh gallery từ URL.',
    emptyValueMessage = 'Nhập ít nhất một URL ảnh trước khi lưu.',
}) {
    const normalizedValue = useMemo(() => normalizeMediaList(value), [value]);
    const [messageApi, messageContextHolder] = message.useMessage();
    const [uploadingAsset, setUploadingAsset] = useState(null);
    const [mediaMode, setMediaMode] = useState('upload');
    const [mediaLibraryOpen, setMediaLibraryOpen] = useState(false);
    const [mediaLibraryPage, setMediaLibraryPage] = useState(1);
    const [mediaKeyword, setMediaKeyword] = useState('');
    const [mediaUrl, setMediaUrl] = useState('');
    const [availableMediaOptions, setAvailableMediaOptions] = useState(mediaOptions);
    const [mediaLibrarySelection, setMediaLibrarySelection] = useState([]);
    const uploadInputRef = useRef(null);

    useEffect(() => {
        setMediaMode(normalizedValue.length ? 'library' : 'upload');
        setMediaLibraryPage(1);
        setMediaKeyword('');
        setMediaUrl('');
        setMediaLibraryOpen(false);
        setMediaLibrarySelection(normalizedValue);
    }, [normalizedValue, open]);

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

    const setNormalizedValue = (nextValue) => {
        onChange?.(Array.from(new Set(normalizeMediaList(nextValue))));
    };

    const appendValue = (nextValue) => {
        setNormalizedValue([...normalizedValue, ...normalizeMediaList(nextValue)]);
    };

    const removeValue = (mediaUrlToRemove) => {
        setNormalizedValue(normalizedValue.filter((item) => item !== mediaUrlToRemove));
    };

    const createMediaRecord = async ({ file, fileUrl, title }) => {
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

        if (title) {
            formData.append('title', title);
        } else if (recordTitle) {
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

    const handleUpload = async (event) => {
        const files = Array.from(event.target.files ?? []);

        if (!files.length) {
            return;
        }

        setUploadingAsset('upload');

        try {
            const createdMedia = [];

            for (const file of files) {
                const media = await createMediaRecord({
                    file,
                    title: file.name.replace(/\.[^.]+$/, ''),
                });

                createdMedia.push(media);
            }

            setAvailableMediaOptions((currentOptions) => {
                const nextMap = new Map(currentOptions.map((item) => [item.id, item]));

                createdMedia.forEach((item) => {
                    nextMap.set(item.id, item);
                });

                return Array.from(nextMap.values());
            });
            appendValue(createdMedia.map((item) => item.file_url));
            messageApi.success(uploadSuccessMessage);
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : uploadErrorMessage);
        } finally {
            setUploadingAsset(null);
            event.target.value = '';
        }
    };

    const handleCreateFromUrl = async () => {
        const trimmedUrls = String(mediaUrl ?? '')
            .split(/\r?\n/)
            .map((item) => item.trim())
            .filter(Boolean);

        if (!trimmedUrls.length) {
            messageApi.warning(emptyValueMessage);
            return;
        }

        setUploadingAsset('url');

        try {
            const createdMedia = [];

            for (const [index, fileUrl] of trimmedUrls.entries()) {
                const media = await createMediaRecord({
                    fileUrl,
                    title: `${recordTitle || 'Gallery image'} ${index + 1}`,
                });

                createdMedia.push(media);
            }

            setAvailableMediaOptions((currentOptions) => {
                const nextMap = new Map(currentOptions.map((item) => [item.id, item]));

                createdMedia.forEach((item) => {
                    nextMap.set(item.id, item);
                });

                return Array.from(nextMap.values());
            });
            appendValue(createdMedia.map((item) => item.file_url));
            setMediaUrl('');
            messageApi.success(urlSuccessMessage);
        } catch (error) {
            messageApi.error(error instanceof Error ? error.message : urlErrorMessage);
        } finally {
            setUploadingAsset(null);
        }
    };

    const renderPreview = () => {
        if (!normalizedValue.length) {
            return null;
        }

        return (
            <div style={{ display: 'grid', gap: 12 }}>
                {normalizedValue.map((mediaUrlValue, index) => {
                    const selectedMedia = availableMediaOptions.find((item) => item.file_url === mediaUrlValue) ?? null;

                    return (
                        <div
                            key={`${mediaUrlValue}-${index}`}
                            style={{
                                display: 'grid',
                                gridTemplateColumns: '96px minmax(0, 1fr) auto',
                                gap: 12,
                                alignItems: 'center',
                                padding: 12,
                                border: '1px solid #dbe7e4',
                                borderRadius: 16,
                                background: '#fff',
                            }}
                        >
                            <img
                                src={mediaUrlValue}
                                alt={`${previewTitle} ${index + 1}`}
                                style={{ width: 96, height: 96, objectFit: 'cover', borderRadius: 12 }}
                            />
                            <div style={{ minWidth: 0, display: 'grid', gap: 4 }}>
                                <strong>{selectedMedia?.title || `${previewTitle} ${index + 1}`}</strong>
                                <Paragraph ellipsis={{ rows: 2 }} style={{ marginBottom: 0, color: '#6b7280', wordBreak: 'break-all' }}>
                                    {mediaUrlValue}
                                </Paragraph>
                            </div>
                            <Button size="small" onClick={() => removeValue(mediaUrlValue)}>Bỏ chọn</Button>
                        </div>
                    );
                })}
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
                        { label: 'Upload nhiều ảnh', value: 'upload' },
                        { label: 'Chọn từ thư viện', value: 'library' },
                        { label: 'Nhập từ URL', value: 'url' },
                    ]}
                />

                {mediaMode === 'upload' ? (
                    <div className="cms-featured-media-action-card">
                        <input ref={uploadInputRef} type="file" accept="image/*" multiple style={{ display: 'none' }} onChange={handleUpload} />
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
                                <Button type="primary" onClick={() => {
                                    setMediaLibrarySelection(normalizedValue);
                                    setMediaLibraryOpen(true);
                                }}>
                                    {libraryButtonLabel}
                                </Button>
                                <Text type="secondary">{libraryHint}</Text>
                            </Space>
                            {renderPreview()}
                        </Space>
                    </div>
                ) : null}

                {mediaMode === 'url' ? (
                    <div className="cms-featured-media-action-card">
                        <Space direction="vertical" size={10} style={{ width: '100%' }}>
                            <TextArea value={mediaUrl} onChange={(event) => setMediaUrl(event.target.value)} rows={4} placeholder={urlPlaceholder} />
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
                onOk={() => {
                    appendValue(mediaLibrarySelection);
                    setMediaLibraryOpen(false);
                }}
                okText={`Thêm ${mediaLibrarySelection.length} ảnh`}
                cancelText="Hủy"
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
                        {paginatedMediaOptions.map((item) => {
                            const isSelected = mediaLibrarySelection.includes(item.file_url);

                            return (
                                <button
                                    key={item.id}
                                    type="button"
                                    className={`cms-featured-media-library-item${isSelected ? ' is-selected' : ''}`}
                                    onClick={() => {
                                        setMediaLibrarySelection((currentSelection) => (
                                            currentSelection.includes(item.file_url)
                                                ? currentSelection.filter((currentItem) => currentItem !== item.file_url)
                                                : [...currentSelection, item.file_url]
                                        ));
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
                            );
                        })}
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
