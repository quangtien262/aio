<?php

namespace Modules\Project\Database\Seeders;

use App\Models\Admin;
use App\Models\Project;
use App\Models\ProjectChecklist;
use App\Models\ProjectFile;
use App\Models\ProjectMember;
use App\Models\ProjectPriority;
use App\Models\ProjectReport;
use App\Models\ProjectStatus;
use App\Models\ProjectTask;
use App\Models\ProjectTaskChecklist;
use App\Models\ProjectTaskComment;
use App\Models\ProjectTaskStatus;
use App\Models\ProjectTaskTimeEntry;
use App\Models\ProjectType;
use App\Support\ProjectActivityLogger;
use App\Support\ProjectTaskStatusManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

if (! class_exists(__NAMESPACE__.'\\ProjectSampleDataSeeder', false)) {
    class ProjectSampleDataSeeder extends Seeder
    {
        public function run(): void
        {
            $admin = Admin::query()->where('is_active', true)->orderBy('id')->first();

        if (! $admin) {
            throw new RuntimeException('Không có admin active để tạo dữ liệu mẫu cho module Project.');
        }

        DB::transaction(function () use ($admin): void {
            foreach ($this->projectDefinitions() as $definition) {
                $this->seedProject($definition, $admin);
            }
        });
        }

    private function seedProject(array $definition, Admin $admin): void
    {
        $project = Project::withTrashed()->firstOrNew(['code' => $definition['code']]);

        if ($project->exists && $project->trashed()) {
            $project->restore();
        }

        $project->fill([
            'name' => $definition['name'],
            'description' => $definition['description'],
            'project_type_id' => $this->resolveProjectTypeId($definition['project_type']),
            'project_status_id' => $this->resolveProjectStatusId($definition['project_status']),
            'priority_id' => $this->resolvePriorityId($definition['priority']),
            'manager_admin_id' => $admin->id,
            'start_date' => $definition['start_date'],
            'due_date' => $definition['due_date'],
            'completed_at' => $definition['completed_at'],
            'progress' => $definition['progress'],
            'color' => $definition['color'],
            'meta' => $definition['meta'],
        ]);
        $project->save();

        ProjectTaskStatusManager::ensureProjectStatuses($project);

        $this->resetProjectChildren($project);

        ProjectMember::query()->create([
            'project_id' => $project->id,
            'admin_id' => $admin->id,
            'role' => 'manager',
        ]);

        $tasks = [];

        foreach ($definition['tasks'] as $index => $taskDefinition) {
            $task = ProjectTask::query()->create([
                'project_id' => $project->id,
                'title' => $taskDefinition['title'],
                'description' => $taskDefinition['description'],
                'task_status_id' => $this->resolveTaskStatusId($project, $taskDefinition['status']),
                'priority_id' => $this->resolvePriorityId($taskDefinition['priority']),
                'assignee_admin_id' => $admin->id,
                'created_by_admin_id' => $admin->id,
                'start_date' => $taskDefinition['start_date'],
                'due_date' => $taskDefinition['due_date'],
                'completed_at' => $taskDefinition['completed_at'],
                'sort_order' => $index + 1,
                'progress' => $taskDefinition['progress'],
            ]);

            $tasks[$taskDefinition['key']] = $task;
        }

        foreach ($definition['checklists'] as $index => $checklistDefinition) {
            ProjectChecklist::query()->create([
                'project_id' => $project->id,
                'title' => $checklistDefinition['title'],
                'description' => $checklistDefinition['description'],
                'is_completed' => $checklistDefinition['is_completed'],
                'assigned_admin_id' => $admin->id,
                'sort_order' => $index + 1,
            ]);
        }

        foreach (($definition['task_checklists'] ?? []) as $index => $checklistDefinition) {
            $task = $tasks[$checklistDefinition['task_key']] ?? null;

            if (! $task) {
                continue;
            }

            ProjectTaskChecklist::query()->create([
                'project_id' => $project->id,
                'task_id' => $task->id,
                'title' => $checklistDefinition['title'],
                'description' => $checklistDefinition['description'],
                'is_completed' => $checklistDefinition['is_completed'],
                'assigned_admin_id' => $admin->id,
                'sort_order' => $index + 1,
            ]);
        }

        foreach (($definition['task_comments'] ?? []) as $commentDefinition) {
            $task = $tasks[$commentDefinition['task_key']] ?? null;

            if (! $task) {
                continue;
            }

            ProjectTaskComment::query()->create([
                'project_id' => $project->id,
                'task_id' => $task->id,
                'content' => $commentDefinition['content'],
                'created_by_admin_id' => $admin->id,
                'updated_by_admin_id' => $admin->id,
                'created_at' => $commentDefinition['created_at'] ?? now(),
                'updated_at' => $commentDefinition['updated_at'] ?? ($commentDefinition['created_at'] ?? now()),
            ]);
        }

        foreach (($definition['task_time_entries'] ?? []) as $timeEntryDefinition) {
            $task = $tasks[$timeEntryDefinition['task_key']] ?? null;

            if (! $task) {
                continue;
            }

            ProjectTaskTimeEntry::query()->create([
                'project_id' => $project->id,
                'task_id' => $task->id,
                'tracked_by_admin_id' => $admin->id,
                'tracked_at' => $timeEntryDefinition['tracked_at'],
                'duration_minutes' => $timeEntryDefinition['duration_minutes'],
                'note' => $timeEntryDefinition['note'],
            ]);
        }

        foreach ($definition['files'] as $fileDefinition) {
            $task = $fileDefinition['task_key'] ? ($tasks[$fileDefinition['task_key']] ?? null) : null;
            $path = sprintf('projects/demo/%s/%s', strtolower($definition['code']), $fileDefinition['filename']);
            $content = $fileDefinition['content'];

            Storage::disk('local')->put($path, $content);

            ProjectFile::query()->create([
                'project_id' => $project->id,
                'task_id' => $task?->id,
                'title' => $fileDefinition['title'],
                'disk' => 'local',
                'path' => $path,
                'original_name' => $fileDefinition['filename'],
                'size' => strlen($content),
                'mime_type' => 'text/plain',
                'uploaded_by_admin_id' => $admin->id,
            ]);
        }

        foreach ($definition['reports'] as $reportDefinition) {
            ProjectReport::query()->create([
                'project_id' => $project->id,
                'title' => $reportDefinition['title'],
                'report_date' => $reportDefinition['report_date'],
                'summary' => $reportDefinition['summary'],
                'content' => $reportDefinition['content'],
                'created_by_admin_id' => $admin->id,
            ]);
        }

        foreach ($definition['activities'] as $activityDefinition) {
            ProjectActivityLogger::log(
                $project,
                $activityDefinition['entity_type'],
                $activityDefinition['entity_key'] ? ($tasks[$activityDefinition['entity_key']]?->id ?? null) : null,
                $activityDefinition['action'],
                $activityDefinition['description'],
                $admin,
                $activityDefinition['properties'] ?? [],
            );
        }
    }

    private function resetProjectChildren(Project $project): void
    {
        $project->activities()->delete();
        $project->taskTimeEntries()->delete();
        $project->taskComments()->delete();
        $project->taskChecklists()->delete();
        $project->reports()->delete();
        $project->checklists()->delete();
        $project->members()->delete();

        foreach ($project->files as $file) {
            Storage::disk($file->disk)->delete($file->path);
        }

        $project->files()->delete();
        $project->tasks()->delete();
    }

    private function resolveProjectStatusId(string $name): int
    {
        return ProjectStatus::query()->where('name', $name)->value('id')
            ?? throw new RuntimeException("Không tìm thấy project status [{$name}].");
    }

    private function resolveProjectTypeId(string $name): int
    {
        return ProjectType::query()->where('name', $name)->value('id')
            ?? throw new RuntimeException("Không tìm thấy project type [{$name}].");
    }

    private function resolvePriorityId(string $name): int
    {
        return ProjectPriority::query()->where('name', $name)->value('id')
            ?? throw new RuntimeException("Không tìm thấy priority [{$name}].");
    }

    private function resolveTaskStatusId(Project $project, string $name): int
    {
        return ProjectTaskStatus::query()->where('project_id', $project->id)->where('name', $name)->value('id')
            ?? throw new RuntimeException("Không tìm thấy task status [{$name}].");
    }

    private function projectDefinitions(): array
    {
        return [
            [
                'code' => 'AIO-PRO-DEMO-01',
                'name' => 'Triển khai website corporate 2026',
                'description' => 'Bộ dữ liệu mẫu để test full UI module Project với luồng website, checklist, report và file đính kèm.',
                'project_type' => 'Website',
                'project_status' => 'Đang thực hiện',
                'priority' => 'High',
                'start_date' => '2026-04-01',
                'due_date' => '2026-05-15',
                'completed_at' => null,
                'progress' => 62,
                'color' => '#0f766e',
                'meta' => ['client' => 'Aitilen', 'channel' => 'Corporate site'],
                'tasks' => [
                    ['key' => 'brief', 'title' => 'Chốt sitemap và thông điệp trang chủ', 'description' => 'Hoàn tất cấu trúc nội dung và key message cho hero section.', 'status' => 'Hoàn thành', 'priority' => 'High', 'start_date' => '2026-04-01', 'due_date' => '2026-04-05', 'completed_at' => '2026-04-05 10:00:00', 'progress' => 100],
                    ['key' => 'design', 'title' => 'Thiết kế UI kit landing page', 'description' => 'Xây dựng palette, typography và component nền cho trang corporate.', 'status' => 'Đang review', 'priority' => 'High', 'start_date' => '2026-04-06', 'due_date' => '2026-04-18', 'completed_at' => null, 'progress' => 85],
                    ['key' => 'build', 'title' => 'Tích hợp module CMS cho landing page', 'description' => 'Binding block nội dung, SEO fields và banner từ CMS workspace.', 'status' => 'Đang làm', 'priority' => 'Urgent', 'start_date' => '2026-04-12', 'due_date' => '2026-05-02', 'completed_at' => null, 'progress' => 58],
                    ['key' => 'qa', 'title' => 'QA responsive và tối ưu tốc độ', 'description' => 'Kiểm tra mobile/tablet và tối ưu LCP trước khi nghiệm thu.', 'status' => 'Chưa bắt đầu', 'priority' => 'Medium', 'start_date' => '2026-05-03', 'due_date' => '2026-05-10', 'completed_at' => null, 'progress' => 0],
                ],
                'checklists' => [
                    ['title' => 'Khóa sitemap với team content', 'description' => 'Xác nhận trang chủ, giới thiệu, dịch vụ, liên hệ.', 'is_completed' => true],
                    ['title' => 'Review prototype với stakeholder', 'description' => 'Buổi review 30 phút để chốt visual direction.', 'is_completed' => true],
                    ['title' => 'Kiểm thử Lighthouse bản staging', 'description' => 'Mục tiêu mobile performance tối thiểu 85.', 'is_completed' => false],
                ],
                'task_checklists' => [
                    ['task_key' => 'design', 'title' => 'Khóa palette chính', 'description' => 'Chốt màu chủ đạo và button system.', 'is_completed' => true],
                    ['task_key' => 'design', 'title' => 'Review spacing mobile', 'description' => 'Đảm bảo section hero và CTA không bị ngợp.', 'is_completed' => false],
                    ['task_key' => 'build', 'title' => 'Bind hero block vào CMS', 'description' => 'Kết nối đầy đủ nội dung hero và CTA.', 'is_completed' => false],
                ],
                'task_comments' => [
                    ['task_key' => 'design', 'content' => 'Bản UI hiện tại ổn, cần đậm contrast hơn ở khu vực hero.', 'created_at' => '2026-04-17 09:30:00'],
                    ['task_key' => 'build', 'content' => 'Đã nối xong block banner, còn pending phần SEO fields.', 'created_at' => '2026-04-24 14:15:00'],
                ],
                'task_time_entries' => [
                    ['task_key' => 'design', 'tracked_at' => '2026-04-15 10:00:00', 'duration_minutes' => 150, 'note' => 'Tinh typography và component spacing.'],
                    ['task_key' => 'build', 'tracked_at' => '2026-04-23 13:30:00', 'duration_minutes' => 210, 'note' => 'Tích hợp dữ liệu landing page với CMS workspace.'],
                ],
                'files' => [
                    ['title' => 'Biên bản kickoff', 'filename' => 'kickoff-notes.txt', 'task_key' => null, 'content' => "Kickoff dự án corporate 2026\n- Phạm vi: website giới thiệu\n- Deadline: 15/05/2026\n"],
                    ['title' => 'Checklist UI review', 'filename' => 'ui-review.txt', 'task_key' => 'design', 'content' => "UI Review\n- Hero visual\n- Section spacing\n- Responsive grid\n"],
                ],
                'reports' => [
                    ['title' => 'Báo cáo tuần 14', 'report_date' => '2026-04-08', 'summary' => 'Đã chốt sitemap và bắt đầu dựng UI kit.', 'content' => 'Tiến độ tốt. Không có blocker lớn. Cần chốt thêm ảnh cover từ team content.'],
                    ['title' => 'Báo cáo tuần 16', 'report_date' => '2026-04-22', 'summary' => 'UI kit gần xong, bắt đầu tích hợp CMS.', 'content' => 'Task design đạt 85%. Task build đang chạy song song để kịp mốc staging đầu tháng 5.'],
                ],
                'activities' => [
                    ['entity_type' => 'project', 'entity_key' => null, 'action' => 'created', 'description' => 'Khởi tạo workspace dự án website corporate 2026.'],
                    ['entity_type' => 'task', 'entity_key' => 'brief', 'action' => 'completed', 'description' => 'Đã hoàn thành task chốt sitemap và thông điệp.'],
                    ['entity_type' => 'task', 'entity_key' => 'build', 'action' => 'updated', 'description' => 'Task tích hợp CMS được đẩy lên mức ưu tiên Urgent.', 'properties' => ['progress' => 58]],
                ],
            ],
            [
                'code' => 'AIO-PRO-DEMO-02',
                'name' => 'Chuẩn hóa vận hành App Store',
                'description' => 'Dữ liệu demo cho luồng project nội bộ, tập trung vào quy trình module lifecycle và tài liệu vận hành.',
                'project_type' => 'Nội bộ',
                'project_status' => 'Mới tạo',
                'priority' => 'Medium',
                'start_date' => '2026-04-20',
                'due_date' => '2026-06-01',
                'completed_at' => null,
                'progress' => 18,
                'color' => '#1d4ed8',
                'meta' => ['owner_team' => 'Platform'],
                'tasks' => [
                    ['key' => 'audit', 'title' => 'Audit module lifecycle hiện tại', 'description' => 'Rà soát install, enable, upgrade, uninstall theo từng module.', 'status' => 'Đang làm', 'priority' => 'Medium', 'start_date' => '2026-04-20', 'due_date' => '2026-04-29', 'completed_at' => null, 'progress' => 40],
                    ['key' => 'docs', 'title' => 'Viết tài liệu chuẩn release module', 'description' => 'Định nghĩa checklist release và rollback cho app store.', 'status' => 'Chưa bắt đầu', 'priority' => 'Medium', 'start_date' => '2026-04-30', 'due_date' => '2026-05-08', 'completed_at' => null, 'progress' => 0],
                    ['key' => 'policy', 'title' => 'Đề xuất policy versioning', 'description' => 'Quy ước semver và chiến lược compatibility cho module.', 'status' => 'Chưa bắt đầu', 'priority' => 'Low', 'start_date' => '2026-05-01', 'due_date' => '2026-05-10', 'completed_at' => null, 'progress' => 0],
                ],
                'checklists' => [
                    ['title' => 'Liệt kê module core hiện có', 'description' => 'Catalog, CMS, Theme, Project.', 'is_completed' => true],
                    ['title' => 'Chuẩn hóa quy trình release note', 'description' => 'Đề xuất mẫu release note dùng chung.', 'is_completed' => false],
                ],
                'task_checklists' => [
                    ['task_key' => 'audit', 'title' => 'Rà install/enable/disable', 'description' => 'Ghi nhận luồng chính xác cho từng module.', 'is_completed' => false],
                ],
                'task_comments' => [
                    ['task_key' => 'audit', 'content' => 'Lifecycle của Project đang đủ tốt để làm baseline cho tài liệu.', 'created_at' => '2026-04-26 11:00:00'],
                ],
                'task_time_entries' => [
                    ['task_key' => 'audit', 'tracked_at' => '2026-04-26 09:00:00', 'duration_minutes' => 120, 'note' => 'Audit nhanh install/upgrade/uninstall.'],
                ],
                'files' => [
                    ['title' => 'Danh sách module hiện tại', 'filename' => 'module-inventory.txt', 'task_key' => 'audit', 'content' => "Module inventory\n- Catalog\n- Cms\n- Project\n- Themes\n"],
                ],
                'reports' => [
                    ['title' => 'Báo cáo khởi động', 'report_date' => '2026-04-25', 'summary' => 'Đã xác định phạm vi nội bộ và danh sách đầu việc chính.', 'content' => 'Dự án đang ở pha khảo sát. Chưa phát sinh rủi ro đáng kể.'],
                ],
                'activities' => [
                    ['entity_type' => 'project', 'entity_key' => null, 'action' => 'created', 'description' => 'Khởi tạo dự án chuẩn hóa App Store nội bộ.'],
                    ['entity_type' => 'task', 'entity_key' => 'audit', 'action' => 'started', 'description' => 'Bắt đầu audit lifecycle của các module hiện hữu.'],
                ],
            ],
            [
                'code' => 'AIO-PRO-DEMO-03',
                'name' => 'Chiến dịch ra mắt module Project',
                'description' => 'Bộ dữ liệu demo dạng marketing/completed để kiểm tra các trạng thái hoàn tất và báo cáo tổng kết.',
                'project_type' => 'Marketing',
                'project_status' => 'Hoàn thành',
                'priority' => 'High',
                'start_date' => '2026-03-01',
                'due_date' => '2026-04-10',
                'completed_at' => '2026-04-10',
                'progress' => 100,
                'color' => '#7c3aed',
                'meta' => ['campaign' => 'Project launch'],
                'tasks' => [
                    ['key' => 'plan', 'title' => 'Lập media plan cho đợt launch', 'description' => 'Chốt kênh truyền thông, ngân sách và timeline triển khai.', 'status' => 'Hoàn thành', 'priority' => 'High', 'start_date' => '2026-03-01', 'due_date' => '2026-03-05', 'completed_at' => '2026-03-05 09:00:00', 'progress' => 100],
                    ['key' => 'content', 'title' => 'Soạn landing nội dung giới thiệu module', 'description' => 'Viết copy về tính năng, lợi ích và luồng quản trị dự án.', 'status' => 'Hoàn thành', 'priority' => 'High', 'start_date' => '2026-03-06', 'due_date' => '2026-03-18', 'completed_at' => '2026-03-18 15:00:00', 'progress' => 100],
                    ['key' => 'announce', 'title' => 'Phát hành thông báo nội bộ', 'description' => 'Gửi thông báo tới admin users và team vận hành.', 'status' => 'Hoàn thành', 'priority' => 'Medium', 'start_date' => '2026-03-20', 'due_date' => '2026-03-25', 'completed_at' => '2026-03-25 11:30:00', 'progress' => 100],
                ],
                'checklists' => [
                    ['title' => 'Chốt landing page launch', 'description' => 'Đã publish nội dung và visual chính thức.', 'is_completed' => true],
                    ['title' => 'Gửi recap sau chiến dịch', 'description' => 'Báo cáo số liệu và insight sau launch.', 'is_completed' => true],
                ],
                'task_checklists' => [
                    ['task_key' => 'content', 'title' => 'Publish landing launch', 'description' => 'Đảm bảo copy và CTA đã lên production.', 'is_completed' => true],
                ],
                'task_comments' => [
                    ['task_key' => 'announce', 'content' => 'Thông báo nội bộ đã gửi, phản hồi ban đầu khá tích cực.', 'created_at' => '2026-03-25 13:00:00'],
                ],
                'task_time_entries' => [
                    ['task_key' => 'content', 'tracked_at' => '2026-03-14 14:00:00', 'duration_minutes' => 180, 'note' => 'Hoàn thiện copy giới thiệu module Project.'],
                ],
                'files' => [
                    ['title' => 'Tổng hợp campaign', 'filename' => 'campaign-summary.txt', 'task_key' => null, 'content' => "Campaign summary\n- Reach nội bộ tốt\n- Adoption tích cực\n- Đề xuất follow-up training\n"],
                ],
                'reports' => [
                    ['title' => 'Báo cáo tổng kết chiến dịch', 'report_date' => '2026-04-10', 'summary' => 'Chiến dịch launch hoàn tất, phản hồi nội bộ tích cực.', 'content' => 'Tỷ lệ quan tâm tốt. Đề xuất tiếp tục bằng workshop onboarding cho admin mới.'],
                ],
                'activities' => [
                    ['entity_type' => 'project', 'entity_key' => null, 'action' => 'completed', 'description' => 'Đã hoàn tất chiến dịch ra mắt module Project.'],
                    ['entity_type' => 'task', 'entity_key' => 'announce', 'action' => 'completed', 'description' => 'Thông báo nội bộ đã phát hành tới toàn bộ admin users.'],
                ],
            ],
        ];
    }
    }
}
