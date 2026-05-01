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
        private const DEMO_PROJECT_CODE_PREFIX = 'AIO-PRO-DEMO-';

        private bool $removeExisting = true;

        public function configure(array $options = []): static
        {
            if (array_key_exists('remove_existing', $options)) {
                $this->removeExisting = (bool) $options['remove_existing'];
            }

            return $this;
        }

        public function run(): void
        {
            $admin = Admin::query()->where('is_active', true)->orderBy('id')->first();

        if (! $admin) {
            throw new RuntimeException('Không có admin active để tạo dữ liệu mẫu cho module Project.');
        }

        DB::transaction(function () use ($admin): void {
            $definitions = $this->projectDefinitions();

            if ($this->removeExisting) {
                $this->purgeDemoProjects();
            } else {
                $definitions = $this->makeBatchDefinitions($definitions, $this->nextBatchNumber());
            }

            foreach ($definitions as $definition) {
                $this->seedProject($definition, $admin);
            }
        });
        }

    private function purgeDemoProjects(): void
    {
        Project::withTrashed()
            ->where('code', 'like', self::DEMO_PROJECT_CODE_PREFIX.'%')
            ->get()
            ->each(function (Project $project): void {
                $this->resetProjectChildren($project);
                $project->taskStatuses()->delete();
                $project->forceDelete();
            });
    }

    private function nextBatchNumber(): int
    {
        $maxBatch = 1;

        foreach (Project::withTrashed()->where('code', 'like', self::DEMO_PROJECT_CODE_PREFIX.'%')->pluck('code') as $code) {
            if (preg_match('/-B(\d+)$/', $code, $matches) === 1) {
                $maxBatch = max($maxBatch, (int) $matches[1]);
                continue;
            }

            $maxBatch = max($maxBatch, 1);
        }

        return $maxBatch + 1;
    }

    private function makeBatchDefinitions(array $definitions, int $batchNumber): array
    {
        return array_map(function (array $definition) use ($batchNumber): array {
            unset($definition['fixed_id']);
            $definition['code'] = sprintf('%s-B%02d', $definition['code'], $batchNumber);
            $definition['name'] = sprintf('%s (Demo %02d)', $definition['name'], $batchNumber);
            $definition['meta'] = [
                ...($definition['meta'] ?? []),
                'demo_batch' => $batchNumber,
            ];

            return $definition;
        }, $definitions);
    }

    private function seedProject(array $definition, Admin $admin): void
    {
        $project = Project::withTrashed()->firstOrNew(['code' => $definition['code']]);

        if (! $project->exists && isset($definition['fixed_id']) && ! Project::withTrashed()->whereKey($definition['fixed_id'])->exists()) {
            $project->forceFill(['id' => $definition['fixed_id']]);
        }

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
                'fixed_id' => 11,
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
                    ['key' => 'content', 'title' => 'Biên tập nội dung section giới thiệu', 'description' => 'Chốt nội dung cho about, năng lực và CTA liên hệ.', 'status' => 'Hoàn thành', 'priority' => 'Medium', 'start_date' => '2026-04-07', 'due_date' => '2026-04-14', 'completed_at' => '2026-04-14 16:00:00', 'progress' => 100],
                    ['key' => 'build', 'title' => 'Tích hợp module CMS cho landing page', 'description' => 'Binding block nội dung, SEO fields và banner từ CMS workspace.', 'status' => 'Đang làm', 'priority' => 'Urgent', 'start_date' => '2026-04-12', 'due_date' => '2026-05-02', 'completed_at' => null, 'progress' => 58],
                    ['key' => 'seo', 'title' => 'Thiết lập SEO tổng thể cho landing', 'description' => 'Khai báo title, description, OG image và schema cơ bản.', 'status' => 'Đang review', 'priority' => 'Medium', 'start_date' => '2026-04-15', 'due_date' => '2026-04-24', 'completed_at' => null, 'progress' => 72],
                    ['key' => 'media', 'title' => 'Chuẩn hóa ảnh banner và gallery', 'description' => 'Tối ưu crop, dung lượng và phiên bản mobile cho các banner chính.', 'status' => 'Đang làm', 'priority' => 'Medium', 'start_date' => '2026-04-16', 'due_date' => '2026-04-28', 'completed_at' => null, 'progress' => 44],
                    ['key' => 'form', 'title' => 'Tích hợp form liên hệ và email thông báo', 'description' => 'Nối form liên hệ với mail template và validate dữ liệu nhập.', 'status' => 'Đang làm', 'priority' => 'High', 'start_date' => '2026-04-18', 'due_date' => '2026-04-30', 'completed_at' => null, 'progress' => 61],
                    ['key' => 'staging', 'title' => 'Deploy staging cho khách duyệt', 'description' => 'Publish bản staging và chốt danh sách feedback cuối.', 'status' => 'Chưa bắt đầu', 'priority' => 'High', 'start_date' => '2026-05-02', 'due_date' => '2026-05-07', 'completed_at' => null, 'progress' => 0],
                    ['key' => 'qa', 'title' => 'QA responsive và tối ưu tốc độ', 'description' => 'Kiểm tra mobile/tablet và tối ưu LCP trước khi nghiệm thu.', 'status' => 'Chưa bắt đầu', 'priority' => 'Medium', 'start_date' => '2026-05-03', 'due_date' => '2026-05-10', 'completed_at' => null, 'progress' => 0],
                    ['key' => 'launch', 'title' => 'Go-live và bàn giao vận hành', 'description' => 'Đưa site production, chốt checklist bàn giao và tài khoản liên quan.', 'status' => 'Chưa bắt đầu', 'priority' => 'High', 'start_date' => '2026-05-11', 'due_date' => '2026-05-15', 'completed_at' => null, 'progress' => 0],
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
                'fixed_id' => 12,
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
                    ['key' => 'docs', 'title' => 'Viết tài liệu chuẩn release module', 'description' => 'Định nghĩa checklist release và rollback cho app store.', 'status' => 'Đang review', 'priority' => 'Medium', 'start_date' => '2026-04-30', 'due_date' => '2026-05-08', 'completed_at' => null, 'progress' => 78],
                    ['key' => 'policy', 'title' => 'Đề xuất policy versioning', 'description' => 'Quy ước semver và chiến lược compatibility cho module.', 'status' => 'Chưa bắt đầu', 'priority' => 'Low', 'start_date' => '2026-05-01', 'due_date' => '2026-05-10', 'completed_at' => null, 'progress' => 0],
                    ['key' => 'ux', 'title' => 'Rà soát lại UI action panel App Store', 'description' => 'Tinh gọn action chính, trạng thái module và thông điệp blocker.', 'status' => 'Đang làm', 'priority' => 'Medium', 'start_date' => '2026-04-28', 'due_date' => '2026-05-05', 'completed_at' => null, 'progress' => 52],
                    ['key' => 'permission', 'title' => 'Chuẩn hóa permission cho module lifecycle', 'description' => 'Rà soát quyền view/install/enable/upgrade/uninstall cho từng role.', 'status' => 'Đang làm', 'priority' => 'High', 'start_date' => '2026-04-29', 'due_date' => '2026-05-06', 'completed_at' => null, 'progress' => 47],
                    ['key' => 'seed-demo', 'title' => 'Thiết kế luồng tạo data test cho module', 'description' => 'Bổ sung trigger tạo sample data ngay trong App Store cho module cần demo.', 'status' => 'Đang review', 'priority' => 'High', 'start_date' => '2026-04-30', 'due_date' => '2026-05-04', 'completed_at' => null, 'progress' => 83],
                    ['key' => 'qa-store', 'title' => 'Kiểm thử reinstall và upgrade flow', 'description' => 'Chạy lại install/enable/upgrade nhiều vòng để bắt lỗi side effect.', 'status' => 'Chưa bắt đầu', 'priority' => 'Medium', 'start_date' => '2026-05-05', 'due_date' => '2026-05-12', 'completed_at' => null, 'progress' => 0],
                    ['key' => 'telemetry', 'title' => 'Bổ sung log lifecycle quan trọng', 'description' => 'Ghi lại install, upgrade, seed demo data và lỗi runtime chính.', 'status' => 'Chưa bắt đầu', 'priority' => 'Low', 'start_date' => '2026-05-06', 'due_date' => '2026-05-14', 'completed_at' => null, 'progress' => 0],
                    ['key' => 'training', 'title' => 'Soạn tài liệu onboarding cho admin', 'description' => 'Tổng hợp ảnh chụp màn hình và hướng dẫn thao tác module store.', 'status' => 'Chưa bắt đầu', 'priority' => 'Medium', 'start_date' => '2026-05-07', 'due_date' => '2026-05-15', 'completed_at' => null, 'progress' => 0],
                    ['key' => 'rollout', 'title' => 'Lập kế hoạch rollout theo nhóm website', 'description' => 'Xác định module nào bật mặc định cho từng loại website.', 'status' => 'Chưa bắt đầu', 'priority' => 'Medium', 'start_date' => '2026-05-08', 'due_date' => '2026-05-18', 'completed_at' => null, 'progress' => 0],
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
        ];
    }
    }
}
