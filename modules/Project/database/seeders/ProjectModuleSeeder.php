<?php

namespace Modules\Project\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

if (! class_exists(__NAMESPACE__.'\\ProjectModuleSeeder', false)) {
    class ProjectModuleSeeder extends Seeder
    {
        public function run(): void
        {
            $this->seedProjectStatuses();
            $this->seedProjectTypes();
            $this->seedPriorities();
            $this->seedTaskStatuses();
        }

        private function seedProjectStatuses(): void
        {
            $statuses = [
                ['name' => 'Mới tạo', 'color' => 'default', 'sort_order' => 1],
                ['name' => 'Đang thực hiện', 'color' => 'processing', 'sort_order' => 2],
                ['name' => 'Tạm dừng', 'color' => 'warning', 'sort_order' => 3],
                ['name' => 'Hoàn thành', 'color' => 'success', 'sort_order' => 4],
                ['name' => 'Đã hủy', 'color' => 'error', 'sort_order' => 5],
            ];

            foreach ($statuses as $status) {
                DB::table('pro__project_statuses')->updateOrInsert(
                    ['name' => $status['name']],
                    [...$status, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        }

        private function seedProjectTypes(): void
        {
            $types = [
                ['name' => 'Website', 'description' => 'Dự án xây dựng và vận hành website.', 'color' => 'blue', 'sort_order' => 1],
                ['name' => 'Nội bộ', 'description' => 'Dự án vận hành hoặc cải tiến nội bộ.', 'color' => 'cyan', 'sort_order' => 2],
                ['name' => 'Marketing', 'description' => 'Dự án marketing hoặc chiến dịch truyền thông.', 'color' => 'purple', 'sort_order' => 3],
            ];

            foreach ($types as $type) {
                DB::table('pro__project_types')->updateOrInsert(
                    ['name' => $type['name']],
                    [...$type, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        }

        private function seedPriorities(): void
        {
            $priorities = [
                ['name' => 'Low', 'color' => 'default', 'sort_order' => 1],
                ['name' => 'Medium', 'color' => 'processing', 'sort_order' => 2],
                ['name' => 'High', 'color' => 'warning', 'sort_order' => 3],
                ['name' => 'Urgent', 'color' => 'error', 'sort_order' => 4],
            ];

            foreach ($priorities as $priority) {
                DB::table('pro__priorities')->updateOrInsert(
                    ['name' => $priority['name']],
                    [...$priority, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        }

        private function seedTaskStatuses(): void
        {
            $statuses = \App\Support\ProjectTaskStatusManager::defaultDefinitions();

            foreach ($statuses as $status) {
                DB::table('pro__task_statuses')->updateOrInsert(
                    ['project_id' => null, 'name' => $status['name']],
                    [...$status, 'project_id' => null, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        }
    }
}
