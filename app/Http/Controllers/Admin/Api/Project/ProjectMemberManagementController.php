<?php

namespace App\Http\Controllers\Admin\Api\Project;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Support\ProjectActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectMemberManagementController
{
    public function store(Request $request, int $project): JsonResponse
    {
        $parent = Project::query()->findOrFail($project);
        $validated = $request->validate([
            'admin_id' => ['required', 'integer', Rule::exists('admins', 'id')],
            'role' => ['required', 'string', Rule::in(['manager', 'member', 'viewer'])],
        ]);

        $member = $parent->members()->updateOrCreate(
            ['admin_id' => $validated['admin_id']],
            ['role' => $validated['role']],
        );

        ProjectActivityLogger::log($parent, 'member', $member->id, 'updated', 'Đã cập nhật thành viên dự án.', $request->user('admin'));

        return response()->json([
            'message' => 'Đã cập nhật thành viên.',
            'data' => [
                'id' => $member->id,
                'role' => $member->role,
                'admin' => [
                    'id' => $member->admin?->id,
                    'name' => $member->admin?->name,
                    'email' => $member->admin?->email,
                ],
            ],
        ]);
    }

    public function destroy(Request $request, int $member): JsonResponse
    {
        $record = ProjectMember::query()->with(['project', 'admin'])->findOrFail($member);

        ProjectActivityLogger::log($record->project, 'member', $record->id, 'deleted', 'Đã xóa thành viên khỏi dự án.', $request->user('admin'), ['admin' => $record->admin?->email]);
        $record->delete();

        return response()->json([
            'message' => 'Đã xóa thành viên.',
        ]);
    }
}
