import HolderOutlined from '@ant-design/icons/HolderOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import { DndContext, KeyboardSensor, PointerSensor, closestCenter, useSensor, useSensors } from '@dnd-kit/core';
import { SortableContext, arrayMove, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import Button from 'antd/es/button';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Typography from 'antd/es/typography';
import { createContext, useContext, useRef, useState } from 'react';
import FnbStatusTag from './FnbStatusTag';

const DragContext = createContext({});
const { Text } = Typography;

function SortableRow({ style, ...props }) {
    const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({ id: props['data-row-key'] });
    return <DragContext.Provider value={{ attributes, listeners, setActivatorNodeRef }}>
        <tr {...props} ref={setNodeRef} style={{ ...style, transform: CSS.Transform.toString(transform), transition, ...(isDragging ? { position: 'relative', zIndex: 1, background: '#f0faf7' } : {}) }} />
    </DragContext.Provider>;
}

function DragHandle({ disabled, name }) {
    const { attributes, listeners, setActivatorNodeRef } = useContext(DragContext);
    return <Button {...attributes} {...listeners} ref={setActivatorNodeRef} disabled={disabled} type="text" icon={<HolderOutlined />} aria-label={`Sắp xếp ${name}`} style={{ cursor: disabled ? 'default' : 'grab', touchAction: 'none' }} />;
}

export default function FnbCategoryList({ menus, categories, editable, loading, onAdd, onEdit, onReorder }) {
    const [selectedMenuId, setSelectedMenuId] = useState(null);
    const [pendingRows, setPendingRows] = useState(null);
    const busy = useRef(false);
    const menuId = menus.some((menu) => menu.id === selectedMenuId) ? selectedMenuId : menus[0]?.id;
    const rows = pendingRows ?? categories.filter((category) => category.menu_id === menuId);
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }), useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }));
    const reorder = async ({ active, over }) => {
        if (!over || active.id === over.id || busy.current || !editable) return;
        const from = rows.findIndex((row) => row.id === active.id);
        const to = rows.findIndex((row) => row.id === over.id);
        if (from < 0 || to < 0) return;
        const next = arrayMove(rows, from, to);
        busy.current = true;
        setPendingRows(next);
        try { await onReorder(menuId, next); }
        finally { busy.current = false; setPendingRows(null); }
    };
    const disabled = loading || Boolean(pendingRows);
    return <>
        <div className="fnb-table-toolbar">
            <Space wrap>
                <Select aria-label="Thực đơn nhóm món" value={menuId} disabled={disabled} onChange={setSelectedMenuId} style={{ minWidth: 180 }} options={menus.map((menu) => ({ value: menu.id, label: menu.name }))} />
                <Text type="secondary">{pendingRows ? 'Đang lưu thứ tự…' : 'Kéo biểu tượng ⋮⋮ để sắp xếp nhóm trong thực đơn.'}</Text>
            </Space>
            {editable ? <Button type="primary" icon={<PlusOutlined />} disabled={disabled || !menuId} onClick={() => onAdd(menuId)}>Thêm nhóm</Button> : null}
        </div>
        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={reorder}>
            <SortableContext items={rows.map((row) => row.id)} strategy={verticalListSortingStrategy} disabled={!editable || disabled}>
                <Table rowKey="id" pagination={false} dataSource={rows} components={{ body: { row: SortableRow } }} columns={[
                    ...(editable ? [{ key: 'drag', width: 52, render: (_, record) => <DragHandle disabled={disabled} name={record.name} /> }] : []),
                    { title: 'Nhóm', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.name}</Text><Text type="secondary">{record.code}</Text></Space> },
                    { title: 'Trạng thái', dataIndex: 'status', render: (value) => <FnbStatusTag status={value} /> },
                    ...(editable ? [{ key: 'edit', width: 80, render: (_, record) => <Button size="small" disabled={disabled} onClick={() => onEdit(record)}>Sửa</Button> }] : []),
                ]} />
            </SortableContext>
        </DndContext>
    </>;
}
