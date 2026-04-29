import CheckOutlined from '@ant-design/icons/CheckOutlined';
import CloseOutlined from '@ant-design/icons/CloseOutlined';
import EditOutlined from '@ant-design/icons/EditOutlined';
import FileTextOutlined from '@ant-design/icons/FileTextOutlined';
import PaperClipOutlined from '@ant-design/icons/PaperClipOutlined';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Checkbox from 'antd/es/checkbox';
import DatePicker from 'antd/es/date-picker';
import Drawer from 'antd/es/drawer';
import Empty from 'antd/es/empty';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import List from 'antd/es/list';
import Popconfirm from 'antd/es/popconfirm';
import Progress from 'antd/es/progress';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Tabs from 'antd/es/tabs';
import Tag from 'antd/es/tag';
import Timeline from 'antd/es/timeline';
import Typography from 'antd/es/typography';
import Upload from 'antd/es/upload';
import dayjs from 'dayjs';
import { useEffect, useMemo, useState } from 'react';

const { Text, Paragraph } = Typography;
const { TextArea } = Input;

function TaskInfoRow({ label, canEdit, isEditing, display, editor, onEdit, onSave, onCancel, saving }) {
    return (
        <div className="project-task-detail-row">
            <div className="project-task-detail-label">{label}</div>
            <div className="project-task-detail-value">
                {isEditing ? (
                    <div className="project-task-detail-editor">
                        <div className="project-task-detail-editor-control">{editor}</div>
                        <Space size={4}>
                            <Button type="text" icon={<CheckOutlined />} onClick={onSave} loading={saving} />
                            <Button type="text" icon={<CloseOutlined />} onClick={onCancel} disabled={saving} />
                        </Space>
                    </div>
                ) : (
                    <div className="project-task-detail-static">
                        <div className="project-task-detail-display">{display}</div>
                        {canEdit ? <Button type="text" icon={<EditOutlined />} onClick={onEdit} /> : null}
                    </div>
                )}
            </div>
        </div>
    );
}

export default function ProjectTaskDetailDrawer({
    open,
    onClose,
    task,
    project,
    references,
    taskChecklists,
    taskComments,
    taskTimeEntries,
    files,
    activities,
    canManageTasks,
    canManageChecklist,
    canManageFiles,
    canViewActivity,
    onUpdateTask,
    onCreateTaskChecklist,
    onToggleTaskChecklist,
    onDeleteTaskChecklist,
    onCreateTaskComment,
    onUpdateTaskComment,
    onDeleteTaskComment,
    onCreateTaskTimeEntry,
    onUpdateTaskTimeEntry,
    onDeleteTaskTimeEntry,
    onUploadFile,
    onDeleteFile,
    onMarkDone,
}) {
    const [editingField, setEditingField] = useState(null);
    const [draftValue, setDraftValue] = useState(null);
    const [savingField, setSavingField] = useState(false);
    const [newChecklistTitle, setNewChecklistTitle] = useState('');
    const [newChecklistAssigneeId, setNewChecklistAssigneeId] = useState(null);
    const [newCommentContent, setNewCommentContent] = useState('');
    const [editingCommentId, setEditingCommentId] = useState(null);
    const [editingCommentContent, setEditingCommentContent] = useState('');
    const [newTimeEntryDate, setNewTimeEntryDate] = useState(dayjs());
    const [newTimeEntryMinutes, setNewTimeEntryMinutes] = useState(60);
    const [newTimeEntryNote, setNewTimeEntryNote] = useState('');
    const [editingTimeEntryId, setEditingTimeEntryId] = useState(null);
    const [editingTimeEntryDate, setEditingTimeEntryDate] = useState(null);
    const [editingTimeEntryMinutes, setEditingTimeEntryMinutes] = useState(60);
    const [editingTimeEntryNote, setEditingTimeEntryNote] = useState('');
    const [uploadTitle, setUploadTitle] = useState('');
    const [uploadList, setUploadList] = useState([]);
    const [uploadingFile, setUploadingFile] = useState(false);

    useEffect(() => {
        setEditingField(null);
        setDraftValue(null);
        setNewChecklistTitle('');
        setNewChecklistAssigneeId(null);
        setNewCommentContent('');
        setEditingCommentId(null);
        setEditingCommentContent('');
        setNewTimeEntryDate(dayjs());
        setNewTimeEntryMinutes(60);
        setNewTimeEntryNote('');
        setEditingTimeEntryId(null);
        setEditingTimeEntryDate(null);
        setEditingTimeEntryMinutes(60);
        setEditingTimeEntryNote('');
        setUploadTitle('');
        setUploadList([]);
        setSavingField(false);
        setUploadingFile(false);
    }, [task?.id, open]);

    const taskStatuses = references?.task_statuses ?? [];
    const priorities = references?.priorities ?? [];
    const admins = references?.admins ?? [];
    const taskFiles = useMemo(() => (files ?? []).filter((item) => item.task_id === task?.id), [files, task?.id]);
    const scopedTaskChecklists = useMemo(() => (taskChecklists ?? []).filter((item) => item.task_id === task?.id), [taskChecklists, task?.id]);
    const scopedTaskComments = useMemo(() => (taskComments ?? []).filter((item) => item.task_id === task?.id), [taskComments, task?.id]);
    const scopedTaskTimeEntries = useMemo(() => (taskTimeEntries ?? []).filter((item) => item.task_id === task?.id), [taskTimeEntries, task?.id]);
    const totalTrackedMinutes = useMemo(() => scopedTaskTimeEntries.reduce((total, item) => total + (item.duration_minutes ?? 0), 0), [scopedTaskTimeEntries]);
    const taskActivities = useMemo(() => (activities ?? []).filter((item) => (item.entity_type === 'task' && item.entity_id === task?.id) || item.properties?.task_id === task?.id), [activities, task?.id]);
    const doneStatus = useMemo(() => taskStatuses.find((item) => item.is_done), [taskStatuses]);

    if (!task) {
        return null;
    }

    const beginEdit = (field) => {
        const currentValueMap = {
            title: task.title ?? '',
            description: task.description ?? '',
            task_status_id: task.task_status_id ?? null,
            priority_id: task.priority_id ?? null,
            assignee_admin_id: task.assignee_admin_id ?? null,
            progress: task.progress ?? 0,
            start_date: task.start_date ? dayjs(task.start_date) : null,
            due_date: task.due_date ? dayjs(task.due_date) : null,
        };

        setEditingField(field);
        setDraftValue(currentValueMap[field] ?? null);
    };

    const cancelEdit = () => {
        setEditingField(null);
        setDraftValue(null);
    };

    const saveEdit = async () => {
        if (!editingField) {
            return;
        }

        const payloadMap = {
            title: { title: String(draftValue ?? '').trim() || task.title },
            description: { description: String(draftValue ?? '').trim() || null },
            task_status_id: { task_status_id: draftValue },
            priority_id: { priority_id: draftValue },
            assignee_admin_id: { assignee_admin_id: draftValue ?? null },
            progress: { progress: Number(draftValue ?? 0) },
            start_date: { start_date: draftValue?.format?.('YYYY-MM-DD') ?? null },
            due_date: { due_date: draftValue?.format?.('YYYY-MM-DD') ?? null },
        };

        setSavingField(true);
        try {
            await onUpdateTask(task, payloadMap[editingField] ?? {});
            cancelEdit();
        } finally {
            setSavingField(false);
        }
    };

    const submitChecklist = async () => {
        if (!newChecklistTitle.trim()) {
            return;
        }

        await onCreateTaskChecklist(task, {
            title: newChecklistTitle.trim(),
            assigned_admin_id: newChecklistAssigneeId || null,
        });

        setNewChecklistTitle('');
        setNewChecklistAssigneeId(null);
    };

    const submitComment = async () => {
        if (!newCommentContent.trim()) {
            return;
        }

        await onCreateTaskComment(task, {
            content: newCommentContent.trim(),
        });

        setNewCommentContent('');
    };

    const startCommentEdit = (comment) => {
        setEditingCommentId(comment.id);
        setEditingCommentContent(comment.content);
    };

    const cancelCommentEdit = () => {
        setEditingCommentId(null);
        setEditingCommentContent('');
    };

    const saveCommentEdit = async (comment) => {
        await onUpdateTaskComment(comment, { content: editingCommentContent.trim() });
        cancelCommentEdit();
    };

    const submitTimeEntry = async () => {
        if (!newTimeEntryDate || !newTimeEntryMinutes) {
            return;
        }

        await onCreateTaskTimeEntry(task, {
            tracked_at: newTimeEntryDate.format('YYYY-MM-DD HH:mm:ss'),
            duration_minutes: Number(newTimeEntryMinutes),
            note: newTimeEntryNote.trim() || null,
        });

        setNewTimeEntryDate(dayjs());
        setNewTimeEntryMinutes(60);
        setNewTimeEntryNote('');
    };

    const startTimeEntryEdit = (entry) => {
        setEditingTimeEntryId(entry.id);
        setEditingTimeEntryDate(entry.tracked_at ? dayjs(entry.tracked_at) : dayjs());
        setEditingTimeEntryMinutes(entry.duration_minutes ?? 60);
        setEditingTimeEntryNote(entry.note ?? '');
    };

    const cancelTimeEntryEdit = () => {
        setEditingTimeEntryId(null);
        setEditingTimeEntryDate(null);
        setEditingTimeEntryMinutes(60);
        setEditingTimeEntryNote('');
    };

    const saveTimeEntryEdit = async (entry) => {
        await onUpdateTaskTimeEntry(entry, {
            tracked_at: (editingTimeEntryDate ?? dayjs()).format('YYYY-MM-DD HH:mm:ss'),
            duration_minutes: Number(editingTimeEntryMinutes),
            note: editingTimeEntryNote.trim() || null,
        });
        cancelTimeEntryEdit();
    };

    const submitFile = async () => {
        if (!uploadTitle.trim() || uploadList.length === 0) {
            return;
        }

        setUploadingFile(true);

        try {
            await onUploadFile(task, uploadTitle.trim(), uploadList[0]);
            setUploadTitle('');
            setUploadList([]);
        } finally {
            setUploadingFile(false);
        }
    };

    const infoItems = [
        {
            key: 'title',
            label: 'Tiêu đề',
            display: <strong>{task.title}</strong>,
            editor: <Input value={draftValue ?? ''} onChange={(event) => setDraftValue(event.target.value)} onPressEnter={saveEdit} />,
        },
        {
            key: 'description',
            label: 'Mô tả',
            display: <Paragraph style={{ marginBottom: 0 }}>{task.description || '-'}</Paragraph>,
            editor: <TextArea rows={4} value={draftValue ?? ''} onChange={(event) => setDraftValue(event.target.value)} />,
        },
        {
            key: 'task_status_id',
            label: 'Trạng thái',
            display: task.status ? <Tag color={task.status.color}>{task.status.name}</Tag> : '-',
            editor: <Select value={draftValue} style={{ width: '100%' }} options={taskStatuses.map((item) => ({ value: item.id, label: item.name }))} onChange={setDraftValue} />,
        },
        {
            key: 'priority_id',
            label: 'Ưu tiên',
            display: task.priority ? <Tag color={task.priority.color}>{task.priority.name}</Tag> : '-',
            editor: <Select value={draftValue} style={{ width: '100%' }} options={priorities.map((item) => ({ value: item.id, label: item.name }))} onChange={setDraftValue} />,
        },
        {
            key: 'assignee_admin_id',
            label: 'Người thực hiện',
            display: task.assignee ? `${task.assignee.name} (${task.assignee.email})` : '-',
            editor: <Select allowClear value={draftValue} style={{ width: '100%' }} options={admins.map((item) => ({ value: item.id, label: `${item.name} (${item.email})` }))} onChange={setDraftValue} />,
        },
        {
            key: 'progress',
            label: 'Tiến độ',
            display: <div className="project-task-detail-progress"><Progress percent={task.progress ?? 0} size="small" /></div>,
            editor: <InputNumber min={0} max={100} style={{ width: '100%' }} value={draftValue ?? 0} onChange={setDraftValue} />,
        },
        {
            key: 'start_date',
            label: 'Ngày bắt đầu',
            display: task.start_date ? dayjs(task.start_date).format('DD/MM/YYYY') : '-',
            editor: <DatePicker allowClear style={{ width: '100%' }} format="DD/MM/YYYY" value={draftValue} onChange={setDraftValue} />,
        },
        {
            key: 'due_date',
            label: 'Deadline',
            display: task.due_date ? dayjs(task.due_date).format('DD/MM/YYYY') : '-',
            editor: <DatePicker allowClear style={{ width: '100%' }} format="DD/MM/YYYY" value={draftValue} onChange={setDraftValue} />,
        },
    ];

    return (
        <Drawer
            title={task.title}
            open={open}
            onClose={onClose}
            width={980}
            destroyOnClose={false}
            extra={
                <Space>
                    {canManageTasks && doneStatus && !task.status?.is_done ? <Button type="primary" onClick={() => onMarkDone(task, doneStatus.id)}>Hoàn thành</Button> : null}
                    <Button onClick={onClose}>Đóng</Button>
                </Space>
            }
        >
            <div className="project-task-detail-header">
                <div>
                    <Text className="card-label">Task Detail</Text>
                    <div className="project-task-detail-summary-title">{project?.name || 'Project'}</div>
                </div>
                <Space wrap>
                    {task.status ? <Tag color={task.status.color}>{task.status.name}</Tag> : null}
                    {task.priority ? <Tag color={task.priority.color}>{task.priority.name}</Tag> : null}
                    <Tag>{task.progress ?? 0}%</Tag>
                </Space>
            </div>

            <Tabs
                items={[
                    {
                        key: 'info',
                        label: 'Thông tin',
                        children: (
                            <Card className="project-task-detail-card">
                                <div className="project-task-detail-grid">
                                    {infoItems.map((item) => (
                                        <TaskInfoRow
                                            key={item.key}
                                            label={item.label}
                                            canEdit={canManageTasks}
                                            isEditing={editingField === item.key}
                                            display={item.display}
                                            editor={item.editor}
                                            onEdit={() => beginEdit(item.key)}
                                            onSave={saveEdit}
                                            onCancel={cancelEdit}
                                            saving={savingField}
                                        />
                                    ))}
                                </div>
                            </Card>
                        ),
                    },
                    {
                        key: 'files',
                        label: `Files (${taskFiles.length})`,
                        children: (
                            <Space direction="vertical" size={16} style={{ width: '100%' }}>
                                {canManageFiles ? (
                                    <Card className="project-task-detail-card">
                                        <Space wrap style={{ width: '100%' }}>
                                            <Input placeholder="Tên file hiển thị" style={{ width: 260 }} value={uploadTitle} onChange={(event) => setUploadTitle(event.target.value)} />
                                            <Upload beforeUpload={() => false} maxCount={1} fileList={uploadList.map((file) => ({ ...file, uid: file.uid ?? file.name }))} onChange={({ fileList }) => setUploadList(fileList.map((item) => item.originFileObj ?? item))}>
                                                <Button icon={<PaperClipOutlined />}>Chọn file</Button>
                                            </Upload>
                                            <Button type="primary" onClick={submitFile} loading={uploadingFile}>Tải lên</Button>
                                        </Space>
                                    </Card>
                                ) : null}

                                <Card className="project-task-detail-card">
                                    {taskFiles.length ? (
                                        <List
                                            dataSource={taskFiles}
                                            renderItem={(item) => (
                                                <List.Item
                                                    actions={[
                                                        <Button key="download" size="small" href={item.download_url}>Tải xuống</Button>,
                                                        canManageFiles ? <Popconfirm key="delete" title="Xóa file này?" onConfirm={() => onDeleteFile(item)}><Button size="small" danger>Xóa</Button></Popconfirm> : null,
                                                    ].filter(Boolean)}
                                                >
                                                    <List.Item.Meta
                                                        avatar={<FileTextOutlined />}
                                                        title={item.title}
                                                        description={`${item.original_name} • ${item.uploader?.name || 'N/A'} • ${item.size ? `${(item.size / 1024).toFixed(1)} KB` : '-'}`}
                                                    />
                                                </List.Item>
                                            )}
                                        />
                                    ) : <Empty description="Task này chưa có file" />}
                                </Card>
                            </Space>
                        ),
                    },
                    {
                        key: 'checklist',
                        label: `Checklist (${scopedTaskChecklists.filter((item) => item.is_completed).length}/${scopedTaskChecklists.length})`,
                        children: (
                            <Space direction="vertical" size={16} style={{ width: '100%' }}>
                                {canManageChecklist ? (
                                    <Card className="project-task-detail-card">
                                        <Space wrap style={{ width: '100%' }}>
                                            <Input placeholder="Tên checklist" style={{ width: 260 }} value={newChecklistTitle} onChange={(event) => setNewChecklistTitle(event.target.value)} />
                                            <Select allowClear placeholder="Người phụ trách" style={{ width: 240 }} value={newChecklistAssigneeId} options={admins.map((item) => ({ value: item.id, label: item.name }))} onChange={setNewChecklistAssigneeId} />
                                            <Button type="primary" onClick={submitChecklist}>Thêm checklist</Button>
                                        </Space>
                                    </Card>
                                ) : null}

                                <Card className="project-task-detail-card">
                                    {scopedTaskChecklists.length ? (
                                        <List
                                            dataSource={scopedTaskChecklists}
                                            renderItem={(item) => (
                                                <List.Item
                                                    actions={[
                                                        canManageChecklist ? <Popconfirm key="delete" title="Xóa checklist này?" onConfirm={() => onDeleteTaskChecklist(item)}><Button size="small" danger>Xóa</Button></Popconfirm> : null,
                                                    ].filter(Boolean)}
                                                >
                                                    <Checkbox checked={item.is_completed} onChange={(event) => onToggleTaskChecklist(item, event.target.checked)} disabled={!canManageChecklist}>
                                                        {item.title}
                                                    </Checkbox>
                                                    <Text type="secondary">{item.assignee?.name || 'Chưa phân công'}</Text>
                                                </List.Item>
                                            )}
                                        />
                                    ) : <Empty description="Chưa có checklist" />}
                                </Card>
                            </Space>
                        ),
                    },
                    {
                        key: 'comments',
                        label: `Bình luận (${scopedTaskComments.length})`,
                        children: (
                            <Space direction="vertical" size={16} style={{ width: '100%' }}>
                                <Card className="project-task-detail-card">
                                    <Space.Compact style={{ width: '100%' }}>
                                        <TextArea rows={3} placeholder="Nhập bình luận cho task..." value={newCommentContent} onChange={(event) => setNewCommentContent(event.target.value)} />
                                        <Button type="primary" onClick={submitComment}>Gửi</Button>
                                    </Space.Compact>
                                </Card>

                                <Card className="project-task-detail-card">
                                    {scopedTaskComments.length ? (
                                        <List
                                            dataSource={scopedTaskComments}
                                            renderItem={(item) => (
                                                <List.Item
                                                    actions={editingCommentId === item.id ? [
                                                        <Button key="save" size="small" type="primary" onClick={() => saveCommentEdit(item)}>Lưu</Button>,
                                                        <Button key="cancel" size="small" onClick={cancelCommentEdit}>Hủy</Button>,
                                                    ] : [
                                                        <Button key="edit" size="small" onClick={() => startCommentEdit(item)}>Sửa</Button>,
                                                        <Popconfirm key="delete" title="Xóa bình luận này?" onConfirm={() => onDeleteTaskComment(item)}><Button size="small" danger>Xóa</Button></Popconfirm>,
                                                    ]}
                                                >
                                                    <List.Item.Meta
                                                        avatar={<EditOutlined />}
                                                        title={`${item.author?.name || 'Hệ thống'} • ${item.created_at ? dayjs(item.created_at).format('DD/MM/YYYY HH:mm') : '-'}`}
                                                        description={editingCommentId === item.id ? <TextArea rows={3} value={editingCommentContent} onChange={(event) => setEditingCommentContent(event.target.value)} /> : item.content}
                                                    />
                                                </List.Item>
                                            )}
                                        />
                                    ) : <Empty description="Chưa có bình luận" />}
                                </Card>
                            </Space>
                        ),
                    },
                    {
                        key: 'time-tracking',
                        label: `Time Tracking (${Math.floor(totalTrackedMinutes / 60)}h ${totalTrackedMinutes % 60}m)`,
                        children: (
                            <Space direction="vertical" size={16} style={{ width: '100%' }}>
                                <Card className="project-task-detail-card">
                                    <Space wrap style={{ width: '100%' }}>
                                        <DatePicker showTime style={{ width: 220 }} format="DD/MM/YYYY HH:mm" value={newTimeEntryDate} onChange={setNewTimeEntryDate} />
                                        <Space.Compact>
                                            <InputNumber min={1} max={1440} style={{ width: 120 }} value={newTimeEntryMinutes} onChange={setNewTimeEntryMinutes} />
                                            <Button disabled>phút</Button>
                                        </Space.Compact>
                                        <Input placeholder="Ghi chú công việc" style={{ width: 320 }} value={newTimeEntryNote} onChange={(event) => setNewTimeEntryNote(event.target.value)} />
                                        <Button type="primary" onClick={submitTimeEntry}>Ghi nhận</Button>
                                    </Space>
                                </Card>

                                <Card className="project-task-detail-card">
                                    {scopedTaskTimeEntries.length ? (
                                        <List
                                            dataSource={scopedTaskTimeEntries}
                                            renderItem={(item) => (
                                                <List.Item
                                                    actions={editingTimeEntryId === item.id ? [
                                                        <Button key="save" size="small" type="primary" onClick={() => saveTimeEntryEdit(item)}>Lưu</Button>,
                                                        <Button key="cancel" size="small" onClick={cancelTimeEntryEdit}>Hủy</Button>,
                                                    ] : [
                                                        <Button key="edit" size="small" onClick={() => startTimeEntryEdit(item)}>Sửa</Button>,
                                                        <Popconfirm key="delete" title="Xóa time tracking này?" onConfirm={() => onDeleteTaskTimeEntry(item)}><Button size="small" danger>Xóa</Button></Popconfirm>,
                                                    ]}
                                                >
                                                    {editingTimeEntryId === item.id ? (
                                                        <Space wrap style={{ width: '100%' }}>
                                                            <DatePicker showTime style={{ width: 220 }} format="DD/MM/YYYY HH:mm" value={editingTimeEntryDate} onChange={setEditingTimeEntryDate} />
                                                            <Space.Compact>
                                                                <InputNumber min={1} max={1440} style={{ width: 120 }} value={editingTimeEntryMinutes} onChange={setEditingTimeEntryMinutes} />
                                                                <Button disabled>phút</Button>
                                                            </Space.Compact>
                                                            <Input placeholder="Ghi chú" style={{ width: 280 }} value={editingTimeEntryNote} onChange={(event) => setEditingTimeEntryNote(event.target.value)} />
                                                        </Space>
                                                    ) : (
                                                        <List.Item.Meta
                                                            avatar={<FileTextOutlined />}
                                                            title={`${item.author?.name || 'Hệ thống'} • ${item.tracked_at ? dayjs(item.tracked_at).format('DD/MM/YYYY HH:mm') : '-'}`}
                                                            description={`${item.duration_minutes} phút${item.note ? ` • ${item.note}` : ''}`}
                                                        />
                                                    )}
                                                </List.Item>
                                            )}
                                        />
                                    ) : <Empty description="Chưa có time tracking" />}
                                </Card>
                            </Space>
                        ),
                    },
                    {
                        key: 'history',
                        label: `Lịch sử (${taskActivities.length})`,
                        children: (
                            <Card className="project-task-detail-card">
                                {canViewActivity && taskActivities.length ? (
                                    <Timeline
                                        items={taskActivities.map((item) => ({
                                            children: (
                                                <div>
                                                    <strong>{item.description}</strong>
                                                    <div><Text type="secondary">{item.author?.name || 'Hệ thống'} • {item.created_at ? dayjs(item.created_at).format('DD/MM/YYYY HH:mm') : '-'}</Text></div>
                                                </div>
                                            ),
                                        }))}
                                    />
                                ) : <Empty description="Chưa có lịch sử cho task này" />}
                            </Card>
                        ),
                    },
                ]}
            />
        </Drawer>
    );
}
