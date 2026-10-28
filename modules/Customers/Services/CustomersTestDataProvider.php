<?php

namespace Modules\Customers\Services;

use App\Core\Modules\TestData\ModuleTestDataContext;
use App\Core\Modules\TestData\ModuleTestDataProvider;
use Illuminate\Support\Str;

final class CustomersTestDataProvider implements ModuleTestDataProvider
{
    public function preview(): array
    {
        return ['Một danh bạ TEST riêng với hai cá nhân, một công ty và liên hệ dùng miền example.invalid.', 'Cấp người tạo quyền quản lý riêng danh bạ mẫu; không liên kết tài khoản, đổi binding, ghi consent hoặc gửi thông báo.'];
    }

    public function references(): array
    {
        $references = [
            ['table' => 'ph_customer_mappings', 'column' => 'book_id', 'foreign_table' => 'cust_books', 'foreign_column' => 'id'],
            ['table' => 'ph_patient_customer_links', 'column' => 'customer_id', 'foreign_table' => 'cust_profiles', 'foreign_column' => 'id'],
        ];
        foreach (['retail_sales', 'fnb_checks', 'fnb_service_sessions', 'fnb_shared_customer_extensions'] as $table) {
            $references[] = ['table' => $table, 'column' => 'shared_customer_id', 'foreign_table' => 'cust_profiles', 'foreign_column' => 'public_id'];
        }
        $references[] = ['table' => 'cust_profiles', 'column' => 'merged_into_id', 'foreign_table' => 'cust_profiles', 'foreign_column' => 'id'];
        $references[] = ['table' => 'cust_events', 'column' => 'profile_id', 'foreign_table' => 'cust_profiles', 'foreign_column' => 'id'];
        $references[] = ['table' => 'acct_party_sources', 'column' => 'source_id', 'foreign_table' => 'cust_profiles', 'foreign_column' => 'public_id', 'where' => ['source_module' => 'customers']];

        return $references;
    }

    public function create(ModuleTestDataContext $context): void
    {
        $book = $context->insert('cust_books', ['public_id' => (string) Str::uuid(), 'code' => $context->code('BOOK'), 'name' => '[TEST] Danh bạ khách hàng mẫu', 'status' => 'active', 'version' => 1]);
        $context->insert('cust_book_grants', ['book_id' => $book, 'admin_id' => $context->actor->id, 'role' => 'manager']);
        foreach ([['person', 'Khách hàng An'], ['person', 'Khách hàng Bình'], ['company', 'Công ty mẫu']] as [$type, $name]) {
            $profile = $context->insert('cust_profiles', ['public_id' => (string) Str::uuid(), 'book_id' => $book, 'code' => $context->code('CUSTOMER'), 'type' => $type, 'name' => '[TEST] '.$name, 'legal_name' => $type === 'company' ? '[TEST] Công ty mẫu' : null, 'notes' => 'Hồ sơ tổng hợp phục vụ thử nghiệm; không phải khách hàng thật.', 'status' => 'active', 'version' => 1, 'created_by' => $context->actor->id]);
            $email = strtolower($context->code('CONTACT')).'@example.invalid';
            $context->insert('cust_contacts', ['public_id' => (string) Str::uuid(), 'book_id' => $book, 'profile_id' => $profile, 'kind' => 'email', 'value' => $email, 'normalized' => $email, 'label' => 'Email thử nghiệm', 'country_code' => 'VN', 'is_primary' => true, 'source' => 'customers']);
            $context->insert('cust_addresses', ['public_id' => (string) Str::uuid(), 'profile_id' => $profile, 'label' => '[TEST] Địa chỉ mẫu', 'kind' => 'both', 'line1' => 'Địa chỉ giả lập để thử biểu mẫu', 'country_code' => 'VN', 'is_default' => true]);
            $context->insert('cust_profile_tags', ['book_id' => $book, 'profile_id' => $profile, 'tag' => 'TEST']);
        }
    }
}
