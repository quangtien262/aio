<?php

namespace Modules\Project\Services;

use App\Core\Modules\TestData\ModuleTestDataContext;
use App\Core\Modules\TestData\ModuleTestDataProvider;

final class ProjectTestDataProvider implements ModuleTestDataProvider
{
    public function preview(): array
    {
        return ['Một dự án TEST riêng, hai công việc chưa hoàn tất và một checklist.', 'Tạo trạng thái/ưu tiên riêng, chỉ thêm người tạo vào dự án mẫu; không chạy seeder cũ.'];
    }

    public function references(): array
    {
        return [['table' => 'pro__activities', 'column' => 'entity_id', 'foreign_table' => 'pro__tasks', 'foreign_column' => 'id', 'where' => ['entity_type' => 'task']]];
    }

    public function create(ModuleTestDataContext $context): void
    {
        $status = $context->insert('pro__project_statuses', ['name' => '[TEST] Đang chuẩn bị', 'color' => 'blue', 'sort_order' => 999, 'is_active' => true]);
        $type = $context->insert('pro__project_types', ['name' => '[TEST] Dự án mẫu', 'color' => 'blue', 'is_active' => true]);
        $priority = $context->insert('pro__priorities', ['name' => '[TEST] Bình thường', 'color' => 'blue']);
        $project = $context->insert('pro__projects', ['code' => $context->code('PROJECT'), 'name' => '[TEST] Dự án làm quen ứng dụng', 'description' => 'Dự án giả lập để thử quản lý công việc.', 'project_type_id' => $type, 'project_status_id' => $status, 'priority_id' => $priority, 'manager_admin_id' => $context->actor->id, 'start_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(), 'progress' => 0]);
        $context->insert('pro__project_members', ['project_id' => $project, 'admin_id' => $context->actor->id, 'role' => 'manager']);
        $taskStatus = $context->insert('pro__task_statuses', ['project_id' => $project, 'name' => '[TEST] Chưa bắt đầu', 'color' => 'blue', 'is_done' => false, 'is_active' => true]);
        foreach (['Xem thông tin dự án', 'Cập nhật công việc mẫu'] as $index => $title) {
            $context->insert('pro__tasks', ['project_id' => $project, 'title' => '[TEST] '.$title, 'description' => 'Công việc thử nghiệm; có thể chỉnh sửa hoặc kéo thả.', 'task_status_id' => $taskStatus, 'priority_id' => $priority, 'assignee_admin_id' => $context->actor->id, 'created_by_admin_id' => $context->actor->id, 'sort_order' => $index, 'progress' => 0]);
        }
        $context->insert('pro__project_checklists', ['project_id' => $project, 'title' => '[TEST] Kiểm tra giao diện quản lý', 'is_completed' => false, 'assigned_admin_id' => $context->actor->id]);
    }
}
