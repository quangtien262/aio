<?php

namespace Modules\Customers\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CustomerImportService
{
    public function __construct(private readonly CustomerAccessService $access, private readonly CustomerProfiles $profiles, private readonly CustomerCommandService $commands) {}

    public function run(Admin $actor, string $book, array $data, string $key): array
    {
        $v = validator($data, ['rows' => 'required|array|max:1000', 'rows.*' => 'required|array', 'commit' => 'sometimes|boolean'])->validate();
        $live = $this->access->actor($actor);
        $b = $this->access->book($live, $book, 'customers.import', 'editor');
        abort_unless($this->access->allowed($live, $b, 'customers.sensitive', 'editor'), 403);
        if (! ($v['commit'] ?? false)) {
            return $this->preview($b, $v['rows']);
        }

        return $this->commands->run($actor, 'profiles.import', ['book' => $book, 'rows' => $v['rows']], $key, function ($a) use ($book) {
            $b = $this->access->book($a, $book, 'customers.import', 'editor', true);
            abort_unless($this->access->allowed($a, $b, 'customers.sensitive', 'editor'), 403);

            return $b;
        }, function ($a, $b) use ($v) {
            $preview = $this->preview($b, $v['rows']);
            abort_unless($preview['valid'], 422, 'Dữ liệu nhập có lỗi; kiểm tra trước khi ghi.');
            foreach ($v['rows'] as $row) {
                $this->profiles->save($b->id, $row, null, $a->id, 'import');
            }

            return array_replace(array_diff_key($preview, ['duplicates' => true]), ['created_count' => count($v['rows']), 'committed' => true]);
        });
    }

    private function preview(object $book, array $rows): array
    {
        $errors = [];
        $duplicates = [];
        $seen = [];
        $accepted = 0;
        foreach ($rows as $index => $row) {
            try {
                $v = $this->profiles->validate($row);
                if (! empty($v['code'])) {
                    abort_if(isset($seen['code:'.$v['code']]) || DB::table('cust_profiles')->where('book_id', $book->id)->where('code', $v['code'])->exists(), 422, 'Mã khách trùng.');
                    $seen['code:'.$v['code']] = true;
                }
                foreach (['phone', 'email'] as $kind) {
                    if (! empty($v[$kind])) {
                        $normal = $this->profiles->normalize($kind, $v[$kind]);
                        if (isset($seen[$kind.':'.$normal]) || DB::table('cust_contacts')->where('book_id', $book->id)->where('kind', $kind)->where('normalized', $normal)->exists()) {
                            $duplicates[] = ['row' => $index + 1, 'field' => $kind, 'action' => 'create_separate_profile'];
                        }$seen[$kind.':'.$normal] = true;
                    }
                }
                $accepted++;
            } catch (ValidationException $e) {
                $errors[] = ['row' => $index + 1, 'errors' => $e->errors()];
            } catch (HttpException $e) {
                if ($e->getStatusCode() !== 422) {
                    throw $e;
                }$errors[] = ['row' => $index + 1, 'errors' => ['row' => [$e->getMessage()]]];
            }
        }

        return ['valid' => $errors === [], 'row_count' => count($rows), 'accepted_count' => $accepted, 'errors' => $errors, 'duplicates' => $duplicates, 'created_count' => 0, 'committed' => false];
    }
}
