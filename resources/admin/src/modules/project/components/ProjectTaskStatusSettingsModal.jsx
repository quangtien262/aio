import DeleteOutlined from '@ant-design/icons/DeleteOutlined';
import EditOutlined from '@ant-design/icons/EditOutlined';
import HolderOutlined from '@ant-design/icons/HolderOutlined';
import InfoCircleOutlined from '@ant-design/icons/InfoCircleOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import { DndContext, PointerSensor, closestCenter, useSensor, useSensors } from '@dnd-kit/core';
import { SortableContext, arrayMove, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import Button from 'antd/es/button';
import Checkbox from 'antd/es/checkbox';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Popconfirm from 'antd/es/popconfirm';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Tag from 'antd/es/tag';
import Tooltip from 'antd/es/tooltip';
import Typography from 'antd/es/typography';
import { useEffect, useMemo, useState } from 'react';

const { Text } = Typography;

const colorOptions = [
    { value: 'default', label: 'Default' },
    { value: 'processing', label: 'Processing' },
    { value: 'warning', label: 'Warning' },
    { value: 'success', label: 'Success' },
    { value: 'error', label: 'Error' },
    { value: 'blue', label: 'Blue' },
    { value: 'cyan', label: 'Cyan' },
    { value: 'purple', label: 'Purple' },
    { value: 'green', label: 'Green' },
    { value: 'red', label: 'Red' },
    { value: 'orange', label: 'Orange' },
    { value: 'gold', label: 'Gold' },
    { value: 'magenta', label: 'Magenta' },
    { value: 'volcano', label: 'Volcano' },
    { value: 'lime', label: 'Lime' },
    { value: 'geekblue', label: 'Geekblue' },
];

const doneStatusTooltip = 'Khi bật, trạng thái này sẽ được xem là cột hoàn thành mặc định của dự án. Task nằm trong cột này được tính là đã xong.';

function SortableStatusRow({
    status,
    index,
    total,
    canManage,
    busyKey,
    handleEdit,
    handleDelete,
}) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: `task-status-${status.id}`,
        disabled: !canManage,
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
    };

    return (
        <div ref={setNodeRef} style={style} className={`project-status-settings-row${isDragging ? ' is-dragging' : ''}`}>
            <div className="project-status-settings-order">{index + 1}</div>
            <button
                type="button"
                className="project-status-settings-dragger"
                aria-label={`Kéo thả để sắp xếp trạng thái ${status.name}`}
                disabled={!canManage}
                {...listeners}
                {...attributes}
            >
                <HolderOutlined />
            </button>
            <div className="project-status-settings-summary">
                <div className="project-status-settings-name-row">
                    <strong>{status.name}</strong>
                    {status.is_done ? <Tag color="success">Cột hoàn thành</Tag> : null}
                </div>
                <div className="project-status-settings-meta-row">
                    <Tag color={status.color || 'default'}>{status.color || 'default'}</Tag>
                    <Text type="secondary">Thứ tự hiển thị: #{index + 1}</Text>
                </div>
            </div>
            <Space size={6} className="project-status-settings-actions">
                <Text type="secondary" className="project-status-settings-position">#{index + 1}/{total}</Text>
                <Button icon={<EditOutlined />} onClick={() => handleEdit(status)} disabled={!canManage || busyKey === `edit-${status.id}`}>
                    Sửa
                </Button>
                <Popconfirm title="Xóa trạng thái này?" onConfirm={() => handleDelete(status)} disabled={!canManage}>
                    <Button danger icon={<DeleteOutlined />} disabled={!canManage} loading={busyKey === `delete-${status.id}`} />
                </Popconfirm>
            </Space>
        </div>
    );
}

export default function ProjectTaskStatusSettingsModal({
    open,
    onClose,
    statuses,
    canManage,
    onCreate,
    onUpdate,
    onDelete,
    onReorder,
}) {
    const [busyKey, setBusyKey] = useState(null);
    const [editorOpen, setEditorOpen] = useState(false);
    const [editingStatus, setEditingStatus] = useState(null);
    const [editorValues, setEditorValues] = useState({ name: '', color: 'default', is_done: false });
    const [localStatuses, setLocalStatuses] = useState([]);
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }));

    const orderedStatuses = useMemo(() => [...(statuses ?? [])].sort((left, right) => {
        const orderDiff = (left.sort_order ?? 0) - (right.sort_order ?? 0);

        return orderDiff || left.id - right.id;
    }), [statuses]);

    const displayStatuses = busyKey?.startsWith('reorder-') ? localStatuses : orderedStatuses;

    useEffect(() => {
        if (!open) {
            return;
        }

        setBusyKey(null);
    }, [open, statuses]);

    useEffect(() => {
        if (!open || busyKey?.startsWith('reorder-')) {
            return;
        }

        setLocalStatuses(orderedStatuses);
    }, [busyKey, open, orderedStatuses]);

    const updateEditorValues = (changes) => {
        setEditorValues((currentValues) => ({
            ...currentValues,
            ...changes,
        }));
    };

    const openCreateEditor = () => {
        setEditingStatus(null);
        setEditorValues({ name: '', color: 'default', is_done: false });
        setEditorOpen(true);
    };

    const openEditEditor = (status) => {
        setEditingStatus(status);
        setEditorValues({
            name: status.name,
            color: status.color || 'default',
            is_done: Boolean(status.is_done),
        });
        setEditorOpen(true);
    };

    const closeEditor = () => {
        setEditorOpen(false);
        setEditingStatus(null);
        setEditorValues({ name: '', color: 'default', is_done: false });
    };

    const handleSubmitEditor = async () => {
        if (!editorValues.name.trim()) {
            return;
        }

        const actionKey = editingStatus ? `save-${editingStatus.id}` : 'create';
        setBusyKey(actionKey);
        try {
            if (editingStatus) {
                await onUpdate(editingStatus, {
                    name: editorValues.name.trim(),
                    color: editorValues.color || 'default',
                    is_done: Boolean(editorValues.is_done),
                });
            } else {
                await onCreate({
                    name: editorValues.name.trim(),
                    color: editorValues.color || 'default',
                    is_done: Boolean(editorValues.is_done),
                });
            }

            closeEditor();
        } finally {
            setBusyKey(null);
        }
    };

    const handleDelete = async (status) => {
        setBusyKey(`delete-${status.id}`);
        try {
            await onDelete(status);
        } finally {
            setBusyKey(null);
        }
    };

    const handleDragEnd = async (event) => {
        const { active, over } = event;

        if (!over || active.id === over.id || !canManage) {
            return;
        }

        const activeStatusId = Number(String(active.id).replace('task-status-', ''));
        const overStatusId = Number(String(over.id).replace('task-status-', ''));
        const activeIndex = displayStatuses.findIndex((status) => status.id === activeStatusId);
        const overIndex = displayStatuses.findIndex((status) => status.id === overStatusId);

        if (activeIndex < 0 || overIndex < 0 || activeIndex === overIndex) {
            return;
        }

        const previousStatuses = displayStatuses;
        const nextStatuses = arrayMove(displayStatuses, activeIndex, overIndex).map((status, index) => ({
            ...status,
            sort_order: index + 1,
        }));

        setLocalStatuses(nextStatuses);

        setBusyKey(`reorder-${activeStatusId}`);
        try {
            await onReorder(nextStatuses.map((status) => status.id));
        } catch (error) {
            setLocalStatuses(previousStatuses);
            throw error;
        } finally {
            setBusyKey(null);
        }
    };

    return (
        <Modal
            title="Cài đặt trạng thái công việc"
            open={open}
            onCancel={onClose}
            width={860}
            footer={[
                <Button key="close" onClick={onClose}>Đóng</Button>,
            ]}
            destroyOnHidden
        >
            <Space direction="vertical" size={16} style={{ width: '100%' }}>
                <div className="project-status-settings-header">
                    <Text type="secondary">Thứ tự dưới đây cũng là thứ tự hiển thị cột Kanban của dự án này.</Text>
                    <Button type="primary" icon={<PlusOutlined />} onClick={openCreateEditor} disabled={!canManage}>
                        Thêm trạng thái
                    </Button>
                </div>

                <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
                    <SortableContext items={displayStatuses.map((status) => `task-status-${status.id}`)} strategy={verticalListSortingStrategy}>
                        <div className="project-status-settings-list">
                            {displayStatuses.map((status, index) => {
                                return (
                                    <SortableStatusRow
                                        key={status.id}
                                        status={status}
                                        index={index}
                                        total={displayStatuses.length}
                                        canManage={canManage && !busyKey?.startsWith('reorder-')}
                                        busyKey={busyKey}
                                        handleEdit={openEditEditor}
                                        handleDelete={handleDelete}
                                    />
                                );
                            })}
                        </div>
                    </SortableContext>
                </DndContext>
            </Space>

            <Modal
                title={editingStatus ? 'Sửa trạng thái' : 'Thêm trạng thái'}
                open={editorOpen}
                onCancel={closeEditor}
                onOk={handleSubmitEditor}
                okText={editingStatus ? 'Lưu trạng thái' : 'Tạo trạng thái'}
                cancelText="Hủy"
                confirmLoading={busyKey === 'create' || (editingStatus && busyKey === `save-${editingStatus.id}`)}
                destroyOnHidden
            >
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Input
                        placeholder="Tên trạng thái"
                        value={editorValues.name}
                        onChange={(event) => updateEditorValues({ name: event.target.value })}
                        disabled={busyKey === 'create' || Boolean(editingStatus && busyKey === `save-${editingStatus.id}`)}
                    />
                    <Select
                        value={editorValues.color}
                        options={colorOptions}
                        onChange={(value) => updateEditorValues({ color: value })}
                        disabled={busyKey === 'create' || Boolean(editingStatus && busyKey === `save-${editingStatus.id}`)}
                    />
                    <Checkbox
                        checked={editorValues.is_done}
                        onChange={(event) => updateEditorValues({ is_done: event.target.checked })}
                        disabled={busyKey === 'create' || Boolean(editingStatus && busyKey === `save-${editingStatus.id}`)}
                    >
                        <Space size={6}>
                            <span>Cột hoàn thành</span>
                            <Tooltip title={doneStatusTooltip}>
                                <InfoCircleOutlined className="project-status-settings-help" />
                            </Tooltip>
                        </Space>
                    </Checkbox>
                </Space>
            </Modal>
        </Modal>
    );
}
