import { DndContext, DragOverlay, MeasuringStrategy, PointerSensor, pointerWithin, useDroppable, useSensor, useSensors } from '@dnd-kit/core';
import { SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import HolderOutlined from '@ant-design/icons/HolderOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Checkbox from 'antd/es/checkbox';
import Col from 'antd/es/col';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import List from 'antd/es/list';
import Popconfirm from 'antd/es/popconfirm';
import Progress from 'antd/es/progress';
import Row from 'antd/es/row';
import Select from 'antd/es/select';
import Segmented from 'antd/es/segmented';
import Space from 'antd/es/space';
import Statistic from 'antd/es/statistic';
import Table from 'antd/es/table';
import Tabs from 'antd/es/tabs';
import Tag from 'antd/es/tag';
import Timeline from 'antd/es/timeline';
import Typography from 'antd/es/typography';
import Upload from 'antd/es/upload';
import useAdminRouteResource from '../../../shared/hooks/useAdminRouteResource';
import ProjectFormDrawer from '../components/ProjectFormDrawer';
import ProjectTaskDetailDrawer from '../components/ProjectTaskDetailDrawer';
import ProjectReportDrawer from '../components/ProjectReportDrawer';
import ProjectTaskDrawer from '../components/ProjectTaskDrawer';
import dayjs from 'dayjs';
import { useEffect, useMemo, useState } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';

const { Title, Text, Paragraph } = Typography;

function resolveProjectIdFromPath(pathname) {
    const match = pathname.match(/\/project\/projects\/(\d+)/);

    return match ? Number(match[1]) : null;
}

function resolveTagColor(color) {
    return color || 'default';
}

function resolveSemanticColor(color, fallback = '#5b7fad') {
    const palette = {
        default: '#64748b',
        processing: '#1677ff',
        warning: '#faad14',
        success: '#52c41a',
        error: '#ff4d4f',
        blue: '#1677ff',
        cyan: '#13c2c2',
        purple: '#722ed1',
        green: '#52c41a',
        red: '#ff4d4f',
        orange: '#fa8c16',
        gold: '#faad14',
        magenta: '#eb2f96',
        volcano: '#fa541c',
        lime: '#a0d911',
        geekblue: '#2f54eb',
    };

    if (!color) {
        return fallback;
    }

    return palette[color] || color || fallback;
}

function resolveNameInitial(name) {
    if (!name) {
        return 'A';
    }

    return name.trim().charAt(0).toUpperCase();
}

function reorderProjectTasks(tasks, taskId, targetStatusId, overTaskId, taskStatuses) {
    const activeTask = tasks.find((task) => task.id === taskId);

    if (!activeTask) {
        return tasks;
    }

    const activeStatusMeta = taskStatuses.find((status) => status.id === targetStatusId) ?? null;
    const grouped = new Map(taskStatuses.map((status) => [status.id, []]));
    const otherTasks = tasks.filter((task) => task.id !== taskId);

    otherTasks.forEach((task) => {
        const bucket = grouped.get(task.task_status_id) ?? [];
        bucket.push(task);
        grouped.set(task.task_status_id, bucket);
    });

    const nextTask = {
        ...activeTask,
        task_status_id: targetStatusId,
        status: activeStatusMeta ? {
            ...(activeTask.status ?? {}),
            id: activeStatusMeta.id,
            name: activeStatusMeta.name,
            color: activeStatusMeta.color,
            is_done: activeStatusMeta.is_done,
        } : activeTask.status,
    };

    const targetBucket = [...(grouped.get(targetStatusId) ?? [])];
    const insertIndex = overTaskId ? targetBucket.findIndex((task) => task.id === overTaskId) : -1;

    if (insertIndex >= 0) {
        targetBucket.splice(insertIndex, 0, nextTask);
    } else {
        targetBucket.push(nextTask);
    }

    grouped.set(targetStatusId, targetBucket);

    return taskStatuses.flatMap((status) => (grouped.get(status.id) ?? []).map((task, index) => ({
        ...task,
        sort_order: index + 1,
    })));
}

function areTaskLayoutsEqual(leftTasks, rightTasks) {
    if (leftTasks.length !== rightTasks.length) {
        return false;
    }

    return leftTasks.every((task, index) => {
        const otherTask = rightTasks[index];

        return otherTask
            && task.id === otherTask.id
            && task.task_status_id === otherTask.task_status_id
            && task.sort_order === otherTask.sort_order;
    });
}

function ProjectKanbanTaskCardBody({ task, canManageTasks, onEdit, onOpenDetail, dragHandleProps = null }) {
    return (
        <div className="project-task-card-shell">
            <div className="project-task-card-topline">
                <Tag color={resolveTagColor(task.priority?.color)} className="project-task-priority-tag">{task.priority?.name}</Tag>
                <span className="project-task-progress">{task.progress ?? 0}%</span>
            </div>

            {canManageTasks && dragHandleProps ? (
                <button type="button" className="project-task-title-dragger" aria-label="Kéo thả công việc bằng tiêu đề" {...dragHandleProps}>
                    <span className="project-task-title-wrap">
                        <strong className="project-task-title">{task.title}</strong>
                        <Text type="secondary" className="project-task-description">{task.description || 'Chưa có mô tả.'}</Text>
                    </span>
                    <span className="project-task-drag-handle" aria-hidden="true">
                        <HolderOutlined />
                    </span>
                </button>
            ) : (
                <div className="project-task-title-static">
                    <strong className="project-task-title">{task.title}</strong>
                    <Text type="secondary" className="project-task-description">{task.description || 'Chưa có mô tả.'}</Text>
                </div>
            )}

            <div className="project-task-meta-line">
                <span>{task.due_date ? `Hạn ${dayjs(task.due_date).format('DD/MM/YYYY')}` : 'Chưa có deadline'}</span>
            </div>

            <div className="project-task-footer">
                <div className="project-task-assignee">
                    <span className="project-task-assignee-avatar">{resolveNameInitial(task.assignee?.name)}</span>
                    <span className="project-task-assignee-name">{task.assignee?.name || 'Chưa phân công'}</span>
                </div>
                <Space size={4}>
                    {onOpenDetail ? <Button size="small" type="text" onClick={onOpenDetail}>Chi tiết</Button> : null}
                    {canManageTasks && onEdit ? <Button size="small" type="text" onClick={onEdit}>Cập nhật</Button> : null}
                </Space>
            </div>
        </div>
    );
}

function ProjectKanbanColumn({ status, count, isDropTarget, children }) {
    const { setNodeRef } = useDroppable({
        id: `status-${status.id}`,
        data: { type: 'status', statusId: status.id },
    });

    return (
        <div ref={setNodeRef} className={`project-kanban-column${isDropTarget ? ' is-drop-target' : ''}`} style={{ '--project-kanban-accent': resolveSemanticColor(status.color, '#5b7fad') }}>
            <Card className="project-kanban-column-card" styles={{ body: { padding: 0 } }}>
                <div className="project-kanban-column-head">
                    <div className="project-kanban-column-headcopy">
                        <strong>{status.name}</strong>
                        <span>{count ? `${count} nhiệm vụ` : 'Không có nhiệm vụ'}</span>
                    </div>
                    <span className="project-kanban-column-count">{count > 99 ? '99+' : count}</span>
                </div>
                <Space direction="vertical" size={12} style={{ width: '100%' }} className="project-kanban-column-body">
                    {children}
                </Space>
            </Card>
        </div>
    );
}

function ProjectKanbanTaskCard({ task, canManageTasks, isMoving, isDragging, onEdit, onOpenDetail }) {
    const { attributes, listeners, setNodeRef, transform, transition } = useSortable({
        id: `task-${task.id}`,
        disabled: !canManageTasks || isMoving,
        data: {
            type: 'task',
            taskId: task.id,
            statusId: task.task_status_id,
        },
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        '--project-task-accent': resolveSemanticColor(task.priority?.color, '#ef4444'),
    };
    const dragHandleProps = canManageTasks && !isMoving ? { ...listeners, ...attributes } : null;

    return (
        <Card
            ref={setNodeRef}
            size="small"
            style={style}
            styles={{ body: { padding: 14 } }}
            className={`project-task-card${isDragging ? ' is-dragging' : ''}${isMoving ? ' is-moving' : ''}`}
        >
            <ProjectKanbanTaskCardBody task={task} canManageTasks={canManageTasks} onEdit={onEdit} onOpenDetail={onOpenDetail} dragHandleProps={dragHandleProps} />
        </Card>
    );
}

export default function ProjectManagerPage({ moduleMenu, callAdminApi, runAdminAction, currentPermissions }) {
    const location = useLocation();
    const navigate = useNavigate();
    const sectionKey = moduleMenu?.key ?? 'project-projects';
    const activeProjectId = resolveProjectIdFromPath(location.pathname);
    const [projectDrawerOpen, setProjectDrawerOpen] = useState(false);
    const [taskDrawerOpen, setTaskDrawerOpen] = useState(false);
    const [reportDrawerOpen, setReportDrawerOpen] = useState(false);
    const [editingProject, setEditingProject] = useState(null);
    const [editingTask, setEditingTask] = useState(null);
    const [editingReport, setEditingReport] = useState(null);
    const [taskDetailOpen, setTaskDetailOpen] = useState(false);
    const [taskDetailId, setTaskDetailId] = useState(null);
    const [taskViewMode, setTaskViewMode] = useState('kanban');
    const [draggingTaskId, setDraggingTaskId] = useState(null);
    const [dropStatusId, setDropStatusId] = useState(null);
    const [placeholderTaskId, setPlaceholderTaskId] = useState(null);
    const [placeholderStatusId, setPlaceholderStatusId] = useState(null);
    const [movingTaskId, setMovingTaskId] = useState(null);
    const [optimisticTasks, setOptimisticTasks] = useState(null);
    const [fileUpload, setFileUpload] = useState({ title: '', task_id: null, fileList: [] });
    const [newChecklist, setNewChecklist] = useState({ title: '', assigned_admin_id: null });
    const [newMember, setNewMember] = useState({ admin_id: null, role: 'member' });
    const [projectFilter, setProjectFilter] = useState({ search: '', project_status_id: null, project_type_id: null });
    const [taskFilter, setTaskFilter] = useState({ search: '', project_id: null });
    const [reportFilter, setReportFilter] = useState({ project_id: null });

    const permissions = useMemo(() => ({
        canCreateProject: currentPermissions.includes('project.create'),
        canUpdateProject: currentPermissions.includes('project.update'),
        canDeleteProject: currentPermissions.includes('project.delete'),
        canManageMembers: currentPermissions.includes('project.member.manage'),
        canViewTasks: currentPermissions.includes('project.task.view'),
        canManageTasks: currentPermissions.includes('project.task.create') || currentPermissions.includes('project.task.update') || currentPermissions.includes('project.task.delete'),
        canManageChecklist: currentPermissions.includes('project.checklist.manage'),
        canManageFiles: currentPermissions.includes('project.file.manage'),
        canViewReports: currentPermissions.includes('project.report.view'),
        canManageReports: currentPermissions.includes('project.report.create') || currentPermissions.includes('project.report.update') || currentPermissions.includes('project.report.delete'),
        canViewActivity: currentPermissions.includes('project.activity.view'),
    }), [currentPermissions]);
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }));

    const { data, loading, error, reload, mutateData } = useAdminRouteResource({
        enabled: sectionKey === 'project-projects'
            ? Boolean(activeProjectId ? currentPermissions.includes('project.view') : currentPermissions.includes('project.view'))
            : sectionKey === 'project-tasks'
                ? permissions.canViewTasks
                : permissions.canViewReports,
        loader: async () => {
            if (sectionKey === 'project-projects' && activeProjectId) {
                const payload = await callAdminApi(`/admin/api/project/projects/${activeProjectId}`);

                return payload.data ?? null;
            }

            if (sectionKey === 'project-projects') {
                const query = new URLSearchParams();
                if (projectFilter.search) query.set('search', projectFilter.search);
                if (projectFilter.project_status_id) query.set('project_status_id', String(projectFilter.project_status_id));
                if (projectFilter.project_type_id) query.set('project_type_id', String(projectFilter.project_type_id));
                const payload = await callAdminApi(`/admin/api/project/projects${query.toString() ? `?${query.toString()}` : ''}`);

                return payload.data ?? null;
            }

            if (sectionKey === 'project-tasks') {
                const query = new URLSearchParams();
                if (taskFilter.search) query.set('search', taskFilter.search);
                if (taskFilter.project_id) query.set('project_id', String(taskFilter.project_id));
                const payload = await callAdminApi(`/admin/api/project/tasks${query.toString() ? `?${query.toString()}` : ''}`);

                return payload.data ?? null;
            }

            const query = new URLSearchParams();
            if (reportFilter.project_id) query.set('project_id', String(reportFilter.project_id));
            const payload = await callAdminApi(`/admin/api/project/reports${query.toString() ? `?${query.toString()}` : ''}`);

            return payload.data ?? null;
        },
        deps: [sectionKey, activeProjectId, JSON.stringify(projectFilter), JSON.stringify(taskFilter), JSON.stringify(reportFilter)],
    });

    const references = data?.references ?? data?.project?.references ?? {};
    const project = data?.project ?? null;
    const projectItems = data?.items ?? [];
    const projectReferences = data?.references ?? {};
    const taskStatuses = references.task_statuses ?? [];
    const projectTasks = optimisticTasks ?? project?.tasks ?? [];
    const activeDraggedTask = useMemo(() => projectTasks.find((task) => task.id === draggingTaskId) ?? null, [draggingTaskId, projectTasks]);
    const selectedTask = useMemo(() => projectTasks.find((task) => task.id === taskDetailId) ?? project?.tasks?.find((task) => task.id === taskDetailId) ?? null, [project, projectTasks, taskDetailId]);
    const groupedProjectTasks = useMemo(() => projectTasks.reduce((result, task) => {
        const statusId = task.task_status_id;

        if (!result[statusId]) {
            result[statusId] = [];
        }

        result[statusId].push(task);

        return result;
    }, {}), [projectTasks]);
    const taskProjectOptions = useMemo(() => {
        const registry = new Map();

        (data?.items ?? []).forEach((item) => {
            if (item.project?.id && item.project?.name) {
                registry.set(item.project.id, { value: item.project.id, label: item.project.name });
            }
        });

        return Array.from(registry.values());
    }, [data]);

    useEffect(() => {
        setOptimisticTasks(null);
    }, [project?.id]);

    const openCreateProject = () => {
        setEditingProject(null);
        setProjectDrawerOpen(true);
    };

    const openTaskDetail = (task) => {
        setTaskDetailId(task.id);
        setTaskDetailOpen(true);
    };

    const closeTaskDetail = () => {
        setTaskDetailOpen(false);
    };

    const submitProject = async (values) => {
        const endpoint = editingProject ? `/admin/api/project/projects/${editingProject.id}` : '/admin/api/project/projects';
        const method = editingProject ? 'PUT' : 'POST';

        await runAdminAction(
            () => callAdminApi(endpoint, { method, body: JSON.stringify(values) }),
            editingProject ? 'Đã cập nhật dự án.' : 'Đã tạo dự án.',
            async () => {
                setProjectDrawerOpen(false);
                setEditingProject(null);
                await reload();
            },
        );
    };

    const submitTask = async (values) => {
        if (!project) {
            return;
        }

        const endpoint = editingTask ? `/admin/api/project/tasks/${editingTask.id}` : `/admin/api/project/projects/${project.id}/tasks`;
        const method = editingTask ? 'PUT' : 'POST';

        await runAdminAction(
            () => callAdminApi(endpoint, { method, body: JSON.stringify(values) }),
            editingTask ? 'Đã cập nhật công việc.' : 'Đã tạo công việc.',
            async () => {
                setTaskDrawerOpen(false);
                setEditingTask(null);
                await reload();
            },
        );
    };

    const submitReport = async (values) => {
        const currentProjectId = project?.id ?? values.project_id;
        if (!currentProjectId) {
            return;
        }

        const endpoint = editingReport ? `/admin/api/project/reports/${editingReport.id}` : `/admin/api/project/projects/${currentProjectId}/reports`;
        const method = editingReport ? 'PUT' : 'POST';

        await runAdminAction(
            () => callAdminApi(endpoint, { method, body: JSON.stringify(values) }),
            editingReport ? 'Đã cập nhật báo cáo.' : 'Đã tạo báo cáo.',
            async () => {
                setReportDrawerOpen(false);
                setEditingReport(null);
                await reload();
            },
        );
    };

    const handleDeleteProject = async (record) => {
        await runAdminAction(
            () => callAdminApi(`/admin/api/project/projects/${record.id}`, { method: 'DELETE' }),
            'Đã xóa dự án.',
            reload,
        );
    };

    const handleDeleteTask = async (record) => {
        await runAdminAction(
            () => callAdminApi(`/admin/api/project/tasks/${record.id}`, { method: 'DELETE' }),
            'Đã xóa công việc.',
            reload,
        );
    };

    const moveTaskToStatus = async (taskId, targetStatusId, overTaskId = null) => {
        const task = project?.tasks?.find((item) => item.id === taskId);

        if (!task || !permissions.canManageTasks || movingTaskId) {
            return;
        }

        if (task.task_status_id === targetStatusId) {
            return;
        }

        setMovingTaskId(task.id);

        const completedAt = task.completed_at ? dayjs(task.completed_at).format('YYYY-MM-DD') : null;
        const nextSortOrder = overTaskId ? ((groupedProjectTasks[targetStatusId] ?? []).findIndex((item) => item.id === overTaskId) + 1) : (groupedProjectTasks[targetStatusId]?.length ?? 0) + 1;
        let nextTask = null;

        try {
            await runAdminAction(
                async () => {
                    const payload = await callAdminApi(`/admin/api/project/tasks/${task.id}`, {
                        method: 'PUT',
                        body: JSON.stringify({
                            title: task.title,
                            description: task.description,
                            task_status_id: targetStatusId,
                            priority_id: task.priority_id,
                            assignee_admin_id: task.assignee_admin_id,
                            start_date: task.start_date,
                            due_date: task.due_date,
                            completed_at: completedAt,
                            sort_order: nextSortOrder,
                            progress: task.progress,
                        }),
                    });

                    nextTask = payload?.data ?? null;

                    return payload;
                },
                'Đã cập nhật trạng thái công việc.',
                () => {
                    if (!nextTask) {
                        setOptimisticTasks(null);
                        return;
                    }

                    mutateData((currentData) => {
                        if (!currentData?.project) {
                            return currentData;
                        }

                        return {
                            ...currentData,
                            project: {
                                ...currentData.project,
                                tasks: (currentData.project.tasks ?? []).map((item) => (item.id === nextTask.id ? nextTask : item)),
                            },
                        };
                    });

                    setOptimisticTasks(null);
                },
            );
        } finally {
            setMovingTaskId(null);
        }
    };

    const handleTaskDragCancel = () => {
        setDraggingTaskId(null);
        setDropStatusId(null);
        setPlaceholderTaskId(null);
        setPlaceholderStatusId(null);
        setOptimisticTasks(null);
    };

    const handleTaskDragStart = (event) => {
        const taskId = event.active.data.current?.taskId ?? null;

        setDraggingTaskId(taskId);
        setOptimisticTasks(project?.tasks ?? []);
    };

    const handleTaskDragOver = (event) => {
        if (!event.over) {
            setDropStatusId(null);
            return;
        }

        const overData = event.over.data.current;
        const nextStatusId = overData?.type === 'status' ? overData.statusId : overData?.statusId ?? null;
        const overTaskId = overData?.type === 'task' ? overData.taskId : null;

        setDropStatusId(nextStatusId);
        setPlaceholderStatusId(nextStatusId);
        setPlaceholderTaskId(overTaskId);

        const taskId = event.active.data.current?.taskId ?? null;
        if (!taskId || !nextStatusId) {
            return;
        }

        setOptimisticTasks((currentTasks) => {
            const sourceTasks = currentTasks ?? project?.tasks ?? [];
            const activeTask = sourceTasks.find((task) => task.id === taskId);

            if (!activeTask) {
                return sourceTasks;
            }

            const currentBucket = sourceTasks.filter((task) => task.task_status_id === nextStatusId);
            const activeIndex = currentBucket.findIndex((task) => task.id === taskId);
            const overIndex = overTaskId ? currentBucket.findIndex((task) => task.id === overTaskId) : currentBucket.length - 1;

            if (activeTask.task_status_id === nextStatusId && overTaskId && activeIndex === overIndex) {
                return sourceTasks;
            }

            const reorderedTasks = reorderProjectTasks(sourceTasks, taskId, nextStatusId, overTaskId, taskStatuses);

            if (areTaskLayoutsEqual(sourceTasks, reorderedTasks)) {
                return sourceTasks;
            }

            return reorderedTasks;
        });
    };

    const handleTaskDragEnd = async (event) => {
        const taskId = event.active.data.current?.taskId ?? null;
        const overData = event.over?.data.current;
        const nextStatusId = overData?.type === 'status' ? overData.statusId : overData?.statusId ?? null;
        const overTaskId = overData?.type === 'task' ? overData.taskId : null;

        setDraggingTaskId(null);
        setDropStatusId(null);
        setPlaceholderTaskId(null);
        setPlaceholderStatusId(null);

        if (!taskId || !nextStatusId) {
            setOptimisticTasks(null);
            return;
        }

        await moveTaskToStatus(taskId, nextStatusId, overTaskId);
    };

    const handleDeleteChecklist = async (record) => {
        await runAdminAction(
            () => callAdminApi(`/admin/api/project/checklists/${record.id}`, { method: 'DELETE' }),
            'Đã xóa checklist.',
            reload,
        );
    };

    const handleDeleteFile = async (record) => {
        await runAdminAction(
            () => callAdminApi(`/admin/api/project/files/${record.id}`, { method: 'DELETE' }),
            'Đã xóa file.',
            reload,
        );
    };

    const handleDeleteReport = async (record) => {
        await runAdminAction(
            () => callAdminApi(`/admin/api/project/reports/${record.id}`, { method: 'DELETE' }),
            'Đã xóa báo cáo.',
            reload,
        );
    };

    const handleChecklistToggle = async (record, checked) => {
        await runAdminAction(
            () => callAdminApi(`/admin/api/project/checklists/${record.id}`, {
                method: 'PUT',
                body: JSON.stringify({
                    title: record.title,
                    description: record.description,
                    assigned_admin_id: record.assigned_admin_id,
                    sort_order: record.sort_order,
                    is_completed: checked,
                }),
            }),
            'Đã cập nhật checklist.',
            reload,
        );
    };

    const handleCreateChecklist = async () => {
        if (!project || !newChecklist.title.trim()) {
            return;
        }

        await runAdminAction(
            () => callAdminApi(`/admin/api/project/projects/${project.id}/checklists`, {
                method: 'POST',
                body: JSON.stringify(newChecklist),
            }),
            'Đã thêm checklist.',
            async () => {
                setNewChecklist({ title: '', assigned_admin_id: null });
                await reload();
            },
        );
    };

    const handleAddMember = async () => {
        if (!project || !newMember.admin_id) {
            return;
        }

        await runAdminAction(
            () => callAdminApi(`/admin/api/project/projects/${project.id}/members`, {
                method: 'POST',
                body: JSON.stringify(newMember),
            }),
            'Đã cập nhật thành viên.',
            async () => {
                setNewMember({ admin_id: null, role: 'member' });
                await reload();
            },
        );
    };

    const handleDeleteMember = async (record) => {
        await runAdminAction(
            () => callAdminApi(`/admin/api/project/members/${record.id}`, { method: 'DELETE' }),
            'Đã xóa thành viên.',
            reload,
        );
    };

    const handleUploadFile = async () => {
        if (!project || !fileUpload.title.trim() || fileUpload.fileList.length === 0) {
            return;
        }

        const formData = new FormData();
        formData.append('title', fileUpload.title);
        if (fileUpload.task_id) {
            formData.append('task_id', String(fileUpload.task_id));
        }
        formData.append('file', fileUpload.fileList[0]);

        await runAdminAction(
            () => callAdminApi(`/admin/api/project/projects/${project.id}/files`, { method: 'POST', body: formData }),
            'Đã tải file lên.',
            async () => {
                setFileUpload({ title: '', task_id: null, fileList: [] });
                await reload();
            },
        );
    };

    const mutateProjectCollection = (key, updater) => {
        mutateData((currentData) => {
            if (!currentData?.project) {
                return currentData;
            }

            return {
                ...currentData,
                project: {
                    ...currentData.project,
                    [key]: updater(currentData.project[key] ?? []),
                },
            };
        });
    };

    const updateTaskRecord = async (task, overrides, successMessage = 'Đã cập nhật công việc.') => {
        if (!task) {
            return null;
        }

        let nextTask = null;
        const hasOverride = (key) => Object.prototype.hasOwnProperty.call(overrides, key);

        const payload = {
            title: hasOverride('title') ? overrides.title : task.title,
            description: hasOverride('description') ? overrides.description : task.description,
            task_status_id: hasOverride('task_status_id') ? overrides.task_status_id : task.task_status_id,
            priority_id: hasOverride('priority_id') ? overrides.priority_id : task.priority_id,
            assignee_admin_id: hasOverride('assignee_admin_id') ? overrides.assignee_admin_id : task.assignee_admin_id,
            start_date: hasOverride('start_date') ? overrides.start_date : task.start_date,
            due_date: hasOverride('due_date') ? overrides.due_date : task.due_date,
            completed_at: hasOverride('completed_at') ? overrides.completed_at : (task.completed_at ? dayjs(task.completed_at).format('YYYY-MM-DD') : null),
            sort_order: hasOverride('sort_order') ? overrides.sort_order : task.sort_order,
            progress: hasOverride('progress') ? overrides.progress : task.progress,
        };

        await runAdminAction(
            async () => {
                const response = await callAdminApi(`/admin/api/project/tasks/${task.id}`, {
                    method: 'PUT',
                    body: JSON.stringify(payload),
                });

                nextTask = response?.data ?? null;

                return response;
            },
            successMessage,
            () => {
                if (!nextTask) {
                    return;
                }

                mutateData((currentData) => {
                    if (!currentData?.project) {
                        return currentData;
                    }

                    return {
                        ...currentData,
                        project: {
                            ...currentData.project,
                            tasks: (currentData.project.tasks ?? []).map((item) => (item.id === nextTask.id ? nextTask : item)),
                        },
                    };
                });
            },
        );

        return nextTask;
    };

    const createDetailChecklist = async (payload) => {
        return payload;
    };

    const createTaskDetailChecklist = async (task, payload) => {
        if (!task) {
            return;
        }

        let nextChecklist = null;

        await runAdminAction(
            async () => {
                const response = await callAdminApi(`/admin/api/project/tasks/${task.id}/checklists`, {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });

                nextChecklist = response?.data ?? null;

                return response;
            },
            'Đã thêm checklist.',
            () => {
                if (!nextChecklist) {
                    return;
                }

                mutateProjectCollection('task_checklists', (items) => [...items, nextChecklist]);
            },
        );
    };

    const toggleTaskDetailChecklist = async (record, checked) => {
        let nextChecklist = null;

        await runAdminAction(
            async () => {
                const response = await callAdminApi(`/admin/api/project/task-checklists/${record.id}`, {
                    method: 'PUT',
                    body: JSON.stringify({
                        title: record.title,
                        description: record.description,
                        assigned_admin_id: record.assigned_admin_id,
                        sort_order: record.sort_order,
                        is_completed: checked,
                    }),
                });

                nextChecklist = response?.data ?? null;

                return response;
            },
            'Đã cập nhật checklist.',
            () => {
                if (!nextChecklist) {
                    return;
                }

                mutateProjectCollection('task_checklists', (items) => items.map((item) => (item.id === nextChecklist.id ? nextChecklist : item)));
            },
        );
    };

    const deleteTaskDetailChecklist = async (record) => {
        await runAdminAction(
            () => callAdminApi(`/admin/api/project/task-checklists/${record.id}`, { method: 'DELETE' }),
            'Đã xóa checklist.',
            () => {
                mutateProjectCollection('task_checklists', (items) => items.filter((item) => item.id !== record.id));
            },
        );
    };

    const uploadDetailFile = async (task, title, file) => {
        if (!project || !file) {
            return;
        }

        const formData = new FormData();
        formData.append('title', title);
        formData.append('task_id', String(task.id));
        formData.append('file', file);

        let nextFile = null;

        await runAdminAction(
            async () => {
                const response = await callAdminApi(`/admin/api/project/projects/${project.id}/files`, { method: 'POST', body: formData });

                nextFile = response?.data ?? null;

                return response;
            },
            'Đã tải file lên.',
            () => {
                if (!nextFile) {
                    return;
                }

                mutateProjectCollection('files', (items) => [...items, nextFile]);
            },
        );
    };

    const deleteDetailFile = async (record) => {
        await runAdminAction(
            () => callAdminApi(`/admin/api/project/files/${record.id}`, { method: 'DELETE' }),
            'Đã xóa file.',
            () => {
                mutateProjectCollection('files', (items) => items.filter((item) => item.id !== record.id));
            },
        );
    };

    const createTaskComment = async (task, payload) => {
        if (!task) {
            return;
        }

        let nextComment = null;

        await runAdminAction(
            async () => {
                const response = await callAdminApi(`/admin/api/project/tasks/${task.id}/comments`, {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });

                nextComment = response?.data ?? null;

                return response;
            },
            'Đã thêm bình luận.',
            () => {
                if (!nextComment) {
                    return;
                }

                mutateProjectCollection('task_comments', (items) => [nextComment, ...items]);
            },
        );
    };

    const updateTaskComment = async (comment, payload) => {
        let nextComment = null;

        await runAdminAction(
            async () => {
                const response = await callAdminApi(`/admin/api/project/task-comments/${comment.id}`, {
                    method: 'PUT',
                    body: JSON.stringify(payload),
                });

                nextComment = response?.data ?? null;

                return response;
            },
            'Đã cập nhật bình luận.',
            () => {
                if (!nextComment) {
                    return;
                }

                mutateProjectCollection('task_comments', (items) => items.map((item) => (item.id === nextComment.id ? nextComment : item)));
            },
        );
    };

    const deleteTaskComment = async (comment) => {
        await runAdminAction(
            () => callAdminApi(`/admin/api/project/task-comments/${comment.id}`, { method: 'DELETE' }),
            'Đã xóa bình luận.',
            () => {
                mutateProjectCollection('task_comments', (items) => items.filter((item) => item.id !== comment.id));
            },
        );
    };

    const createTaskTimeEntry = async (task, payload) => {
        if (!task) {
            return;
        }

        let nextEntry = null;

        await runAdminAction(
            async () => {
                const response = await callAdminApi(`/admin/api/project/tasks/${task.id}/time-entries`, {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });

                nextEntry = response?.data ?? null;

                return response;
            },
            'Đã thêm time tracking.',
            () => {
                if (!nextEntry) {
                    return;
                }

                mutateProjectCollection('task_time_entries', (items) => [nextEntry, ...items]);
            },
        );
    };

    const updateTaskTimeEntry = async (entry, payload) => {
        let nextEntry = null;

        await runAdminAction(
            async () => {
                const response = await callAdminApi(`/admin/api/project/task-time-entries/${entry.id}`, {
                    method: 'PUT',
                    body: JSON.stringify(payload),
                });

                nextEntry = response?.data ?? null;

                return response;
            },
            'Đã cập nhật time tracking.',
            () => {
                if (!nextEntry) {
                    return;
                }

                mutateProjectCollection('task_time_entries', (items) => items.map((item) => (item.id === nextEntry.id ? nextEntry : item)));
            },
        );
    };

    const deleteTaskTimeEntry = async (entry) => {
        await runAdminAction(
            () => callAdminApi(`/admin/api/project/task-time-entries/${entry.id}`, { method: 'DELETE' }),
            'Đã xóa time tracking.',
            () => {
                mutateProjectCollection('task_time_entries', (items) => items.filter((item) => item.id !== entry.id));
            },
        );
    };

    const renderProjectList = () => {
        const metrics = data?.metrics ?? {};
        const items = projectItems;

        const columns = [
            {
                title: 'Dự án',
                dataIndex: 'name',
                key: 'name',
                render: (_, record) => (
                    <div>
                        <Button type="link" style={{ padding: 0 }} onClick={() => navigate(`/project/projects/${record.id}`)}>
                            <strong>{record.name}</strong>
                        </Button>
                        <div><Text type="secondary">{record.code}</Text></div>
                        <div><Text type="secondary">{record.description || 'Chưa có mô tả.'}</Text></div>
                    </div>
                ),
            },
            { title: 'Loại', dataIndex: ['project_type', 'name'], key: 'project_type', render: (value) => value || '-' },
            { title: 'Trạng thái', dataIndex: ['status', 'name'], key: 'status', render: (_, record) => <Tag color={resolveTagColor(record.status?.color)}>{record.status?.name}</Tag> },
            { title: 'Ưu tiên', dataIndex: ['priority', 'name'], key: 'priority', render: (_, record) => <Tag color={resolveTagColor(record.priority?.color)}>{record.priority?.name}</Tag> },
            { title: 'Tiến độ', dataIndex: 'progress', key: 'progress', render: (value) => <Progress percent={value ?? 0} size="small" /> },
            { title: 'Người quản lý', dataIndex: ['manager', 'name'], key: 'manager', render: (value) => value || '-' },
            { title: 'Ngày bắt đầu', dataIndex: 'start_date', key: 'start_date', render: (value) => value ? dayjs(value).format('DD/MM/YYYY') : '-' },
            { title: 'Ngày hoàn thành', dataIndex: 'due_date', key: 'due_date', render: (value) => value ? dayjs(value).format('DD/MM/YYYY') : '-' },
            {
                title: 'Thao tác',
                key: 'actions',
                render: (_, record) => (
                    <Space>
                        <Button size="small" onClick={() => navigate(`/project/projects/${record.id}`)}>Xem</Button>
                        {permissions.canUpdateProject ? <Button size="small" onClick={() => { setEditingProject(record); setProjectDrawerOpen(true); }}>Sửa</Button> : null}
                        {permissions.canDeleteProject ? (
                            <Popconfirm title="Xóa dự án này?" onConfirm={() => handleDeleteProject(record)}>
                                <Button size="small" danger>Xóa</Button>
                            </Popconfirm>
                        ) : null}
                    </Space>
                ),
            },
        ];

        return (
            <Space direction="vertical" size={16} style={{ width: '100%' }}>
                <Row gutter={[16, 16]}>
                    <Col xs={24} md={8}><Card><Statistic title="Dự án đang có" value={metrics.active_projects ?? 0} /></Card></Col>
                    <Col xs={24} md={8}><Card><Statistic title="Tổng công việc" value={metrics.total_tasks ?? 0} /></Card></Col>
                    <Col xs={24} md={8}><Card><Statistic title="Công việc hoàn thành" value={metrics.completed_tasks ?? 0} /></Card></Col>
                </Row>

                <Card>
                    <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
                        <Space wrap>
                            <Input.Search placeholder="Tìm tên, mã dự án..." style={{ width: 280 }} onSearch={(value) => setProjectFilter((prev) => ({ ...prev, search: value }))} allowClear />
                            <Select
                                allowClear
                                placeholder="Trạng thái"
                                style={{ width: 180 }}
                                options={(projectReferences.project_statuses ?? []).map((item) => ({ value: item.id, label: item.name }))}
                                onChange={(value) => setProjectFilter((prev) => ({ ...prev, project_status_id: value }))}
                            />
                            <Select
                                allowClear
                                placeholder="Loại dự án"
                                style={{ width: 180 }}
                                options={(projectReferences.project_types ?? []).map((item) => ({ value: item.id, label: item.name }))}
                                onChange={(value) => setProjectFilter((prev) => ({ ...prev, project_type_id: value }))}
                            />
                        </Space>

                        {permissions.canCreateProject ? <Button type="primary" onClick={openCreateProject}>Tạo dự án mới</Button> : null}
                    </Space>
                </Card>

                <Card title={`Danh sách dự án (${data?.total ?? 0})`}>
                    <Table rowKey="id" dataSource={items} columns={columns} scroll={{ x: 1200 }} pagination={false} />
                </Card>
            </Space>
        );
    };

    const renderProjectDetail = () => {
        if (!project) {
            return <Empty description="Không tìm thấy dự án." />;
        }

        const taskColumns = [
            { title: 'Công việc', dataIndex: 'title', key: 'title', render: (value, record) => <div><strong>{value}</strong><div><Text type="secondary">{record.description || 'Chưa có mô tả.'}</Text></div></div> },
            { title: 'Trạng thái', dataIndex: ['status', 'name'], key: 'status', render: (_, record) => <Tag color={resolveTagColor(record.status?.color)}>{record.status?.name}</Tag> },
            { title: 'Ưu tiên', dataIndex: ['priority', 'name'], key: 'priority', render: (_, record) => <Tag color={resolveTagColor(record.priority?.color)}>{record.priority?.name}</Tag> },
            { title: 'Người thực hiện', dataIndex: ['assignee', 'name'], key: 'assignee', render: (value) => value || '-' },
            { title: 'Hạn', dataIndex: 'due_date', key: 'due_date', render: (value) => value ? dayjs(value).format('DD/MM/YYYY') : '-' },
            { title: 'Tiến độ', dataIndex: 'progress', key: 'progress', render: (value) => <Progress percent={value ?? 0} size="small" /> },
            { title: 'Thao tác', key: 'actions', render: (_, record) => <Space><Button size="small" onClick={() => openTaskDetail(record)}>Chi tiết</Button>{permissions.canManageTasks ? <Button size="small" onClick={() => { setEditingTask(record); setTaskDrawerOpen(true); }}>Sửa</Button> : null}{permissions.canManageTasks ? <Popconfirm title="Xóa công việc này?" onConfirm={() => handleDeleteTask(record)}><Button size="small" danger>Xóa</Button></Popconfirm> : null}</Space> },
        ];

        const memberColumns = [
            { title: 'Thành viên', dataIndex: ['admin', 'name'], key: 'admin', render: (_, record) => <div><strong>{record.admin?.name}</strong><div><Text type="secondary">{record.admin?.email}</Text></div></div> },
            { title: 'Vai trò', dataIndex: 'role', key: 'role', render: (value) => <Tag>{value}</Tag> },
            { title: 'Thao tác', key: 'actions', render: (_, record) => permissions.canManageMembers ? <Popconfirm title="Xóa thành viên này?" onConfirm={() => handleDeleteMember(record)}><Button size="small" danger>Xóa</Button></Popconfirm> : null },
        ];

        const fileColumns = [
            { title: 'Tệp', dataIndex: 'title', key: 'title', render: (_, record) => <div><strong>{record.title}</strong><div><Text type="secondary">{record.original_name}</Text></div></div> },
            { title: 'Kích thước', dataIndex: 'size', key: 'size', render: (value) => value ? `${(value / 1024).toFixed(1)} KB` : '-' },
            { title: 'Người tải lên', dataIndex: ['uploader', 'name'], key: 'uploader', render: (value) => value || '-' },
            { title: 'Thao tác', key: 'actions', render: (_, record) => <Space><Button size="small" href={record.download_url}>Tải xuống</Button>{permissions.canManageFiles ? <Popconfirm title="Xóa file này?" onConfirm={() => handleDeleteFile(record)}><Button size="small" danger>Xóa</Button></Popconfirm> : null}</Space> },
        ];

        const reportColumns = [
            { title: 'Báo cáo', dataIndex: 'title', key: 'title', render: (_, record) => <div><strong>{record.title}</strong><div><Text type="secondary">{record.summary || 'Chưa có tóm tắt.'}</Text></div></div> },
            { title: 'Ngày báo cáo', dataIndex: 'report_date', key: 'report_date', render: (value) => value ? dayjs(value).format('DD/MM/YYYY') : '-' },
            { title: 'Người tạo', dataIndex: ['author', 'name'], key: 'author', render: (value) => value || '-' },
            { title: 'Thao tác', key: 'actions', render: (_, record) => <Space>{permissions.canManageReports ? <Button size="small" onClick={() => { setEditingReport(record); setReportDrawerOpen(true); }}>Sửa</Button> : null}{permissions.canManageReports ? <Popconfirm title="Xóa báo cáo này?" onConfirm={() => handleDeleteReport(record)}><Button size="small" danger>Xóa</Button></Popconfirm> : null}</Space> },
        ];

        return (
            <Space direction="vertical" size={16} style={{ width: '100%' }}>
                <Card>
                    <Space direction="vertical" size={12} style={{ width: '100%' }}>
                        <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
                            <Button onClick={() => navigate('/project/projects')}>Quay lại</Button>
                            {permissions.canUpdateProject ? <Button onClick={() => { setEditingProject(project); setProjectDrawerOpen(true); }}>Sửa dự án</Button> : null}
                        </Space>

                        <div>
                            <Text className="card-label">Project Workspace</Text>
                            <Title level={2} style={{ margin: '6px 0' }}>{project.name}</Title>
                            <Paragraph style={{ marginBottom: 0 }}>{project.description || 'Chưa có mô tả cho dự án này.'}</Paragraph>
                        </div>

                        <Space wrap>
                            <Tag color={resolveTagColor(project.status?.color)}>{project.status?.name}</Tag>
                            <Tag color={resolveTagColor(project.priority?.color)}>{project.priority?.name}</Tag>
                            <Tag>{project.code}</Tag>
                            <Tag>{project.project_type?.name || 'Chưa gán loại'}</Tag>
                        </Space>

                        <Row gutter={[16, 16]}>
                            <Col xs={12} md={6}><Card><Statistic title="Tiến độ" value={project.progress ?? 0} suffix="%" /></Card></Col>
                            <Col xs={12} md={6}><Card><Statistic title="Công việc" value={project.tasks?.length ?? 0} /></Card></Col>
                            <Col xs={12} md={6}><Card><Statistic title="Files" value={project.files?.length ?? 0} /></Card></Col>
                            <Col xs={12} md={6}><Card><Statistic title="Báo cáo" value={project.reports?.length ?? 0} /></Card></Col>
                        </Row>
                    </Space>
                </Card>

                <Tabs
                    defaultActiveKey="tasks"
                    items={[
                        {
                            key: 'tasks',
                            label: `Nhiệm vụ (${project.tasks?.length ?? 0})`,
                            children: (
                                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                                    <Card>
                                        <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
                                            <Segmented options={[{ label: 'Bảng', value: 'table' }, { label: 'Kanban', value: 'kanban' }]} value={taskViewMode} onChange={setTaskViewMode} />
                                            {permissions.canManageTasks ? <Button type="primary" onClick={() => { setEditingTask(null); setTaskDrawerOpen(true); }}>Thêm nhiệm vụ</Button> : null}
                                        </Space>
                                    </Card>

                                    {taskViewMode === 'table' ? (
                                        <Card><Table rowKey="id" dataSource={project.tasks ?? []} columns={taskColumns} pagination={false} scroll={{ x: 1000 }} /></Card>
                                    ) : (
                                        <DndContext
                                            sensors={sensors}
                                            collisionDetection={pointerWithin}
                                            measuring={{ droppable: { strategy: MeasuringStrategy.WhileDragging } }}
                                            onDragStart={handleTaskDragStart}
                                            onDragOver={handleTaskDragOver}
                                            onDragEnd={handleTaskDragEnd}
                                            onDragCancel={handleTaskDragCancel}
                                        >
                                        <div className="project-kanban-board">
                                            {(references.task_statuses ?? []).map((status) => (
                                                <ProjectKanbanColumn
                                                    key={status.id}
                                                    status={status}
                                                    count={groupedProjectTasks[status.id]?.length ?? 0}
                                                    isDropTarget={dropStatusId === status.id}
                                                >
                                                    <SortableContext items={(groupedProjectTasks[status.id] ?? []).map((task) => `task-${task.id}`)} strategy={verticalListSortingStrategy}>
                                                    {(groupedProjectTasks[status.id] ?? []).length ? (groupedProjectTasks[status.id] ?? []).map((task) => (
                                                            <div key={`task-slot-${task.id}`}>
                                                            {draggingTaskId && placeholderStatusId === status.id && placeholderTaskId === task.id && draggingTaskId !== task.id ? <div className="project-task-placeholder" /> : null}
                                                            <ProjectKanbanTaskCard
                                                                key={task.id}
                                                                task={task}
                                                                canManageTasks={permissions.canManageTasks}
                                                                isMoving={movingTaskId === task.id}
                                                                isDragging={draggingTaskId === task.id}
                                                                onOpenDetail={() => openTaskDetail(task)}
                                                                onEdit={() => { setEditingTask(task); setTaskDrawerOpen(true); }}
                                                            />
                                                            </div>
                                                        )) : <Empty description="Không có công việc" image={Empty.PRESENTED_IMAGE_SIMPLE} />}
                                                    {draggingTaskId && placeholderStatusId === status.id && !placeholderTaskId ? <div key={`placeholder-tail-${status.id}`} className="project-task-placeholder is-column-tail" /> : null}
                                                    </SortableContext>
                                                </ProjectKanbanColumn>
                                            ))}
                                        </div>
                                        <DragOverlay>
                                            {activeDraggedTask ? (
                                                <div className="project-task-overlay">
                                                    <Card size="small" className="project-task-card is-overlay">
                                                        <ProjectKanbanTaskCardBody task={activeDraggedTask} canManageTasks={false} />
                                                    </Card>
                                                </div>
                                            ) : null}
                                        </DragOverlay>
                                        </DndContext>
                                    )}
                                </Space>
                            ),
                        },
                        {
                            key: 'members',
                            label: `Thành viên (${project.members?.length ?? 0})`,
                            children: (
                                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                                    {permissions.canManageMembers ? (
                                        <Card>
                                            <Space wrap>
                                                <Select
                                                    style={{ width: 280 }}
                                                    placeholder="Chọn admin"
                                                    value={newMember.admin_id}
                                                    onChange={(value) => setNewMember((prev) => ({ ...prev, admin_id: value }))}
                                                    options={(references.admins ?? []).map((item) => ({ value: item.id, label: `${item.name} (${item.email})` }))}
                                                />
                                                <Select
                                                    style={{ width: 180 }}
                                                    value={newMember.role}
                                                    onChange={(value) => setNewMember((prev) => ({ ...prev, role: value }))}
                                                    options={[{ value: 'manager', label: 'Manager' }, { value: 'member', label: 'Member' }, { value: 'viewer', label: 'Viewer' }]}
                                                />
                                                <Button type="primary" onClick={handleAddMember}>Thêm thành viên</Button>
                                            </Space>
                                        </Card>
                                    ) : null}
                                    <Card><Table rowKey="id" dataSource={project.members ?? []} columns={memberColumns} pagination={false} /></Card>
                                </Space>
                            ),
                        },
                        {
                            key: 'files',
                            label: `Files (${project.files?.length ?? 0})`,
                            children: (
                                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                                    {permissions.canManageFiles ? (
                                        <Card className="project-file-upload-card">
                                            <Space wrap>
                                                <Input placeholder="Tiêu đề file" style={{ width: 240 }} value={fileUpload.title} onChange={(event) => setFileUpload((prev) => ({ ...prev, title: event.target.value }))} />
                                                <Select
                                                    allowClear
                                                    placeholder="Gắn với công việc"
                                                    style={{ width: 240 }}
                                                    value={fileUpload.task_id}
                                                    onChange={(value) => setFileUpload((prev) => ({ ...prev, task_id: value }))}
                                                    options={(project.tasks ?? []).map((task) => ({ value: task.id, label: task.title }))}
                                                />
                                                <Upload beforeUpload={(file) => { setFileUpload((prev) => ({ ...prev, fileList: [file] })); return false; }} maxCount={1} fileList={fileUpload.fileList.map((file) => ({ uid: file.uid ?? file.name, name: file.name, status: 'done' }))} onRemove={() => { setFileUpload((prev) => ({ ...prev, fileList: [] })); return true; }}>
                                                    <Button>Chọn file</Button>
                                                </Upload>
                                                <Button type="primary" onClick={handleUploadFile}>Tải lên</Button>
                                            </Space>
                                        </Card>
                                    ) : null}
                                    <Card><Table rowKey="id" dataSource={project.files ?? []} columns={fileColumns} pagination={false} /></Card>
                                </Space>
                            ),
                        },
                        {
                            key: 'checklists',
                            label: `Checklist (${(project.checklists ?? []).filter((item) => item.is_completed).length}/${project.checklists?.length ?? 0})`,
                            children: (
                                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                                    {permissions.canManageChecklist ? (
                                        <Card>
                                            <Space wrap>
                                                <Input placeholder="Nội dung checklist" style={{ width: 280 }} value={newChecklist.title} onChange={(event) => setNewChecklist((prev) => ({ ...prev, title: event.target.value }))} />
                                                <Select
                                                    allowClear
                                                    placeholder="Người phụ trách"
                                                    style={{ width: 240 }}
                                                    value={newChecklist.assigned_admin_id}
                                                    onChange={(value) => setNewChecklist((prev) => ({ ...prev, assigned_admin_id: value }))}
                                                    options={(references.admins ?? []).map((item) => ({ value: item.id, label: `${item.name} (${item.email})` }))}
                                                />
                                                <Button type="primary" onClick={handleCreateChecklist}>Thêm checklist</Button>
                                            </Space>
                                        </Card>
                                    ) : null}

                                    <Card>
                                        <List
                                            dataSource={project.checklists ?? []}
                                            locale={{ emptyText: 'Chưa có checklist nào.' }}
                                            renderItem={(item) => (
                                                <List.Item
                                                    actions={permissions.canManageChecklist ? [
                                                        <Popconfirm key="delete" title="Xóa checklist này?" onConfirm={() => handleDeleteChecklist(item)}>
                                                            <Button size="small" danger>Xóa</Button>
                                                        </Popconfirm>,
                                                    ] : []}
                                                >
                                                    <List.Item.Meta
                                                        title={<Space><Checkbox checked={item.is_completed} onChange={(event) => handleChecklistToggle(item, event.target.checked)} disabled={!permissions.canManageChecklist} /> <span>{item.title}</span></Space>}
                                                        description={<Space wrap><Text type="secondary">{item.description || 'Không có mô tả'}</Text><Text type="secondary">{item.assignee?.name || 'Chưa phân công'}</Text></Space>}
                                                    />
                                                </List.Item>
                                            )}
                                        />
                                    </Card>
                                </Space>
                            ),
                        },
                        {
                            key: 'reports',
                            label: `Báo cáo (${project.reports?.length ?? 0})`,
                            children: (
                                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                                    {permissions.canManageReports ? <Card><Button type="primary" onClick={() => { setEditingReport(null); setReportDrawerOpen(true); }}>Thêm báo cáo</Button></Card> : null}
                                    <Card><Table rowKey="id" dataSource={project.reports ?? []} columns={reportColumns} pagination={false} /></Card>
                                </Space>
                            ),
                        },
                        {
                            key: 'history',
                            label: `Lịch sử (${project.activities?.length ?? 0})`,
                            children: permissions.canViewActivity ? (
                                <Card>
                                    {(project.activities ?? []).length ? (
                                        <Timeline
                                            items={(project.activities ?? []).map((activity) => ({
                                                children: (
                                                    <Space direction="vertical" size={2}>
                                                        <Text strong>{activity.description}</Text>
                                                        <Text type="secondary">{activity.author?.name || 'Hệ thống'} • {activity.created_at ? dayjs(activity.created_at).format('DD/MM/YYYY HH:mm') : '-'}</Text>
                                                    </Space>
                                                ),
                                            }))}
                                        />
                                    ) : <Empty description="Chưa có lịch sử hoạt động." image={Empty.PRESENTED_IMAGE_SIMPLE} />}
                                </Card>
                            ) : <Empty description="Bạn không có quyền xem lịch sử." image={Empty.PRESENTED_IMAGE_SIMPLE} />,
                        },
                        {
                            key: 'info',
                            label: 'Thông tin',
                            children: (
                                <Card>
                                    <div className="detail-grid detail-grid-2">
                                        <div className="detail-tile"><Text className="detail-label">Mã dự án</Text><Text strong>{project.code}</Text></div>
                                        <div className="detail-tile"><Text className="detail-label">Người quản lý</Text><Text strong>{project.manager?.name || '-'}</Text></div>
                                        <div className="detail-tile"><Text className="detail-label">Ngày bắt đầu</Text><Text strong>{project.start_date ? dayjs(project.start_date).format('DD/MM/YYYY') : '-'}</Text></div>
                                        <div className="detail-tile"><Text className="detail-label">Ngày hoàn thành</Text><Text strong>{project.due_date ? dayjs(project.due_date).format('DD/MM/YYYY') : '-'}</Text></div>
                                    </div>
                                </Card>
                            ),
                        },
                    ]}
                />
            </Space>
        );
    };

    const renderGlobalTasks = () => {
        const items = data?.items ?? [];

        const columns = [
            { title: 'Công việc', dataIndex: 'title', key: 'title', render: (_, record) => <div><strong>{record.title}</strong><div><Text type="secondary">{record.description || 'Chưa có mô tả.'}</Text></div></div> },
            { title: 'Dự án', dataIndex: ['project', 'name'], key: 'project', render: (_, record) => <Button type="link" onClick={() => navigate(`/project/projects/${record.project?.id}`)}>{record.project?.name}</Button> },
            { title: 'Trạng thái', dataIndex: ['status', 'name'], key: 'status', render: (_, record) => <Tag color={resolveTagColor(record.status?.color)}>{record.status?.name}</Tag> },
            { title: 'Ưu tiên', dataIndex: ['priority', 'name'], key: 'priority', render: (_, record) => <Tag color={resolveTagColor(record.priority?.color)}>{record.priority?.name}</Tag> },
            { title: 'Người thực hiện', dataIndex: ['assignee', 'name'], key: 'assignee', render: (value) => value || '-' },
            { title: 'Hạn', dataIndex: 'due_date', key: 'due_date', render: (value) => value ? dayjs(value).format('DD/MM/YYYY') : '-' },
        ];

        return (
            <Space direction="vertical" size={16} style={{ width: '100%' }}>
                <Card>
                    <Space wrap>
                        <Input.Search placeholder="Tìm công việc..." style={{ width: 280 }} allowClear onSearch={(value) => setTaskFilter((prev) => ({ ...prev, search: value }))} />
                        <Select
                            allowClear
                            placeholder="Lọc theo dự án"
                            style={{ width: 260 }}
                            options={taskProjectOptions}
                            value={taskFilter.project_id}
                            onChange={(value) => setTaskFilter((prev) => ({ ...prev, project_id: value }))}
                        />
                    </Space>
                </Card>
                <Card title={`Danh sách công việc (${data?.total ?? 0})`}>
                    <Table rowKey="id" dataSource={items} columns={columns} pagination={false} scroll={{ x: 980 }} />
                </Card>
            </Space>
        );
    };

    const renderGlobalReports = () => {
        const items = data?.items ?? [];
        const columns = [
            { title: 'Báo cáo', dataIndex: 'title', key: 'title', render: (_, record) => <div><strong>{record.title}</strong><div><Text type="secondary">{record.summary || 'Chưa có tóm tắt.'}</Text></div></div> },
            { title: 'Dự án', dataIndex: ['project', 'name'], key: 'project', render: (_, record) => <Button type="link" onClick={() => navigate(`/project/projects/${record.project?.id}`)}>{record.project?.name}</Button> },
            { title: 'Ngày báo cáo', dataIndex: 'report_date', key: 'report_date', render: (value) => value ? dayjs(value).format('DD/MM/YYYY') : '-' },
            { title: 'Người tạo', dataIndex: ['author', 'name'], key: 'author', render: (value) => value || '-' },
            { title: 'Nội dung', dataIndex: 'content', key: 'content', render: (value) => <Text type="secondary">{value || 'Chưa có nội dung chi tiết.'}</Text> },
        ];

        return (
            <Card title={`Báo cáo dự án (${data?.total ?? 0})`}>
                <Table rowKey="id" dataSource={items} columns={columns} pagination={false} scroll={{ x: 1000 }} />
            </Card>
        );
    };

    if (loading) {
        return <Card loading title={moduleMenu?.label ?? 'Project'} />;
    }

    if (error) {
        return <Alert type="error" showIcon message={error} />;
    }

    return (
        <>
            {sectionKey === 'project-projects' && activeProjectId ? renderProjectDetail() : null}
            {sectionKey === 'project-projects' && !activeProjectId ? renderProjectList() : null}
            {sectionKey === 'project-tasks' ? renderGlobalTasks() : null}
            {sectionKey === 'project-reports' ? renderGlobalReports() : null}

            <ProjectFormDrawer
                open={projectDrawerOpen}
                onClose={() => { setProjectDrawerOpen(false); setEditingProject(null); }}
                onSubmit={submitProject}
                project={editingProject}
                references={projectReferences.project_statuses ? projectReferences : references}
                canSubmit={permissions.canCreateProject || permissions.canUpdateProject}
            />

            <ProjectTaskDrawer
                open={taskDrawerOpen}
                onClose={() => { setTaskDrawerOpen(false); setEditingTask(null); }}
                onSubmit={submitTask}
                task={editingTask}
                references={references}
                canSubmit={permissions.canManageTasks}
            />

            <ProjectTaskDetailDrawer
                open={taskDetailOpen}
                onClose={closeTaskDetail}
                task={selectedTask}
                project={project}
                references={references}
                taskChecklists={project?.task_checklists ?? []}
                taskComments={project?.task_comments ?? []}
                taskTimeEntries={project?.task_time_entries ?? []}
                files={project?.files ?? []}
                activities={project?.activities ?? []}
                canManageTasks={permissions.canManageTasks}
                canManageChecklist={permissions.canManageChecklist}
                canManageFiles={permissions.canManageFiles}
                canViewActivity={permissions.canViewActivity}
                onUpdateTask={(task, overrides) => updateTaskRecord(task, overrides)}
                onCreateTaskChecklist={createTaskDetailChecklist}
                onToggleTaskChecklist={toggleTaskDetailChecklist}
                onDeleteTaskChecklist={deleteTaskDetailChecklist}
                onCreateTaskComment={createTaskComment}
                onUpdateTaskComment={updateTaskComment}
                onDeleteTaskComment={deleteTaskComment}
                onCreateTaskTimeEntry={createTaskTimeEntry}
                onUpdateTaskTimeEntry={updateTaskTimeEntry}
                onDeleteTaskTimeEntry={deleteTaskTimeEntry}
                onUploadFile={uploadDetailFile}
                onDeleteFile={deleteDetailFile}
                onMarkDone={(task, doneStatusId) => updateTaskRecord(task, { task_status_id: doneStatusId, progress: 100, completed_at: dayjs().format('YYYY-MM-DD') }, 'Đã hoàn thành công việc.')}
            />

            <ProjectReportDrawer
                open={reportDrawerOpen}
                onClose={() => { setReportDrawerOpen(false); setEditingReport(null); }}
                onSubmit={submitReport}
                report={editingReport}
                canSubmit={permissions.canManageReports}
            />
        </>
    );
}
