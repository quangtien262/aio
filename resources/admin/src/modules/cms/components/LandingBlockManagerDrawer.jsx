import { useEffect, useMemo, useState } from 'react';
import DeleteOutlined from '@ant-design/icons/DeleteOutlined';
import EyeOutlined from '@ant-design/icons/EyeOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Drawer from 'antd/es/drawer';
import Empty from 'antd/es/empty';
import Popconfirm from 'antd/es/popconfirm';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Switch from 'antd/es/switch';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';

const { Paragraph, Text, Title } = Typography;

function blockTitle(block) {
    return block?.data?.title || block?.data?.subtitle || block?.block_type || 'Khối landing';
}

function buildVisualUrl(page, block) {
    if (!page?.admin_url) {
        return null;
    }

    return `${page.admin_url}${block?.anchor_id ? `#${block.anchor_id}` : ''}`;
}

export default function LandingBlockManagerDrawer({
    open,
    page,
    locale = 'vi',
    canCreate,
    canUpdate,
    canDelete,
    callAdminApi,
    runAdminAction,
    onClose,
    onChanged,
}) {
    const [blocks, setBlocks] = useState([]);
    const [availableBlocks, setAvailableBlocks] = useState([]);
    const [selectedBlockType, setSelectedBlockType] = useState(null);
    const [loading, setLoading] = useState(false);
    const [draggingId, setDraggingId] = useState(null);

    const selectedBlock = useMemo(
        () => availableBlocks.find((item) => item.block_type === selectedBlockType),
        [availableBlocks, selectedBlockType],
    );

    const loadBlocks = async () => {
        if (!page?.id) {
            return;
        }

        setLoading(true);

        try {
            const payload = await callAdminApi(`/admin/api/landing/pages/${page.id}/blocks?locale=${encodeURIComponent(locale)}`);
            setBlocks(payload.data ?? []);
            setAvailableBlocks(payload.available_blocks ?? []);
            setSelectedBlockType((payload.available_blocks ?? [])[0]?.block_type ?? null);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (open) {
            loadBlocks();
        }
    }, [open, page?.id, locale]);

    const handleAddBlock = async () => {
        if (!selectedBlockType || !page?.id) {
            return;
        }

        await runAdminAction(
            () => callAdminApi(`/admin/api/landing/pages/${page.id}/blocks`, {
                method: 'POST',
                body: JSON.stringify({ block_type: selectedBlockType, locale }),
            }),
            'Đã thêm khối landingpage.',
            async () => {
                await loadBlocks();
                await onChanged?.();
            },
        );
    };

    const reorderBlocks = async (nextBlocks) => {
        const previousBlocks = blocks;
        const normalizedBlocks = nextBlocks.map((block, index) => ({ ...block, sort_order: (index + 1) * 10 }));
        setBlocks(normalizedBlocks);

        try {
            await callAdminApi(`/admin/api/landing/pages/${page.id}/blocks/reorder`, {
                method: 'PUT',
                body: JSON.stringify({
                    blocks: normalizedBlocks.map((block) => ({ id: block.id, sort_order: block.sort_order })),
                }),
            });
            'Đã sắp xếp lại khối.',
            await onChanged?.();
        } catch {
            setBlocks(previousBlocks);
        }
    };

    const handleDrop = async (targetId) => {
        if (!draggingId || draggingId === targetId) {
            setDraggingId(null);
            return;
        }

        const currentIndex = blocks.findIndex((block) => block.id === draggingId);
        const targetIndex = blocks.findIndex((block) => block.id === targetId);

        if (currentIndex < 0 || targetIndex < 0) {
            setDraggingId(null);
            return;
        }

        const nextBlocks = [...blocks];
        const [movingBlock] = nextBlocks.splice(currentIndex, 1);
        nextBlocks.splice(targetIndex, 0, movingBlock);
        setDraggingId(null);
        await reorderBlocks(nextBlocks);
    };

    const handleToggleVisible = async (block, checked) => {
        await runAdminAction(
            () => callAdminApi(`/admin/api/landing/blocks/${block.id}`, {
                method: 'PUT',
                body: JSON.stringify({ is_visible: checked, locale }),
            }),
            checked ? 'Đã bật hiển thị khối.' : 'Đã ẩn khối.',
            async () => {
                await loadBlocks();
                await onChanged?.();
            },
        );
    };

    const handleDeleteBlock = async (block) => {
        await runAdminAction(
            () => callAdminApi(`/admin/api/landing/blocks/${block.id}`, { method: 'DELETE' }),
            'Đã xóa khối landingpage.',
            async () => {
                await loadBlocks();
                await onChanged?.();
            },
        );
    };

    const openVisualEditor = (block) => {
        const url = buildVisualUrl(page, block);

        if (url) {
            window.open(url, '_blank', 'noopener,noreferrer');
        }
    };

    return (
        <Drawer
            title={page ? `Quản lý khối: ${page.title || page.path}` : 'Quản lý khối landingpage'}
            open={open}
            onClose={onClose}
            width={760}
            destroyOnHidden
        >
            <Space direction="vertical" size={16} style={{ width: '100%' }}>
                {page ? (
                    <Card size="small">
                        <Space direction="vertical" size={4}>
                            <Space wrap>
                                <Tag color={page.is_home ? 'green' : 'blue'}>{page.is_home ? 'Trang chủ' : page.path}</Tag>
                                <Tag>{page.theme_key}</Tag>
                                <Tag color={page.status === 'published' ? 'green' : 'default'}>{page.status === 'published' ? 'Đã xuất bản' : 'Bản nháp'}</Tag>
                            </Space>
                            <Text type="secondary">Có thể sắp xếp bằng cách kéo từng khối lên/xuống. Nút sửa trực quan sẽ mở website với chế độ admin.</Text>
                        </Space>
                    </Card>
                ) : null}

                <Card
                    size="small"
                    title="Thêm khối"
                    extra={<Button type="primary" icon={<PlusOutlined />} disabled={!canCreate || !selectedBlockType} onClick={handleAddBlock}>Thêm khối</Button>}
                >
                    <Space direction="vertical" size={8} style={{ width: '100%' }}>
                        <Select
                            value={selectedBlockType}
                            onChange={setSelectedBlockType}
                            options={availableBlocks.map((block) => ({
                                value: block.block_type,
                                label: block.label || block.block_type,
                            }))}
                            style={{ width: '100%' }}
                            placeholder="Chọn mẫu khối có sẵn trong theme"
                        />
                        {selectedBlock ? (
                            <Paragraph type="secondary" style={{ marginBottom: 0 }}>{selectedBlock.description}</Paragraph>
                        ) : null}
                    </Space>
                </Card>

                {loading ? (
                    <Card loading />
                ) : blocks.length ? (
                    <Space direction="vertical" size={10} style={{ width: '100%' }}>
                        {blocks.map((block, index) => (
                            <Card
                                key={block.id}
                                size="small"
                                draggable={canUpdate}
                                onDragStart={() => setDraggingId(block.id)}
                                onDragOver={(event) => event.preventDefault()}
                                onDrop={() => handleDrop(block.id)}
                                style={{
                                    borderColor: draggingId === block.id ? '#bed600' : undefined,
                                    cursor: canUpdate ? 'grab' : 'default',
                                }}
                            >
                                <div style={{ display: 'grid', gridTemplateColumns: '56px 1fr auto', gap: 12, alignItems: 'center' }}>
                                    <div style={{ width: 44, height: 44, borderRadius: 14, display: 'grid', placeItems: 'center', background: '#eef6d1', color: '#6a8a00', fontWeight: 800 }}>
                                        {index + 1}
                                    </div>
                                    <Space direction="vertical" size={2}>
                                        <Space wrap>
                                            <Title level={5} style={{ margin: 0 }}>{blockTitle(block)}</Title>
                                            <Tag>{block.block_type}</Tag>
                                            {block.anchor_id ? <Tag color="blue">#{block.anchor_id}</Tag> : null}
                                        </Space>
                                        <Text type="secondary">{block.data?.description || block.data?.subtitle || 'Khối nội dung landingpage'}</Text>
                                    </Space>
                                    <Space>
                                        <Switch
                                            checked={Boolean(block.is_visible)}
                                            disabled={!canUpdate}
                                            onChange={(checked) => handleToggleVisible(block, checked)}
                                        />
                                        <Button icon={<EyeOutlined />} onClick={() => openVisualEditor(block)}>Sửa trực quan</Button>
                                        <Popconfirm title="Xóa khối này?" disabled={!canDelete} onConfirm={() => handleDeleteBlock(block)}>
                                            <Button danger icon={<DeleteOutlined />} disabled={!canDelete}>Xóa</Button>
                                        </Popconfirm>
                                    </Space>
                                </div>
                            </Card>
                        ))}
                    </Space>
                ) : (
                    <Empty description="Landingpage này chưa có khối nào." />
                )}

                {!canUpdate ? (
                    <Alert type="info" showIcon message="Tài khoản hiện tại chỉ có quyền xem, chưa thể sắp xếp hoặc sửa khối." />
                ) : null}
            </Space>
        </Drawer>
    );
}
