# AIO Platform

AIO là base source website và quản trị doanh nghiệp của HT Việt Nam, xây trên Laravel 13 và React 19. Mỗi khách hàng được triển khai bằng một codebase riêng; `website_key` dùng để cô lập website/domain nội bộ trong cùng installation, không phải mô hình SaaS multi-tenant dùng chung runtime.

## Kiến trúc chính

- Laravel monolith chứa backend, storefront và admin shell React.
- Business capability được quản lý bằng module manifest + lifecycle `install/enable/disable/upgrade/uninstall`.
- Module migration chỉ chạy khi module được cài; production core migration không tự aggregate migration của module.
- Theme là package filesystem trong `themes/`; việc kích hoạt và branding được tách theo website.
- Storefront luôn resolve theo thứ tự website context → locale → localized content → active theme.
- Admin RBAC gắn role và scope `global|website|organization` trên cùng một assignment. Các API quản trị account/RBAC/audit/site-mapping vẫn là global-only; scope pháp nhân chỉ áp dụng cho AccountingTax/Minvoice.

## Tech stack

- PHP 8.3+
- Laravel 13
- React 19
- Vite 7
- Ant Design 5
- CKEditor 5

## Cấu trúc repository

- `app/`: core application, domain services, controllers và models.
- `modules/`: manifest, migrations, seeders, hooks và assets của business modules.
- `themes/`: storefront theme packages.
- `resources/admin/src/`: admin shell React.
- `database/migrations/`: core migrations; không chứa schema sở hữu riêng của module.
- `docs/architecture/`: tài liệu contract theo từng miền.
- `tests/`: unit, feature, integration và fresh-install smoke tests.

## Khởi động nhanh

```bash
composer setup
php artisan db:seed
composer dev
```

`composer setup` tạo `.env` nếu thiếu, generate app key, chạy core migrations và build frontend. `db:seed` dành cho môi trường local/demo; nó tạo System Owner, website mặc định và cài/bật CMS qua module lifecycle.

Nếu chạy thủ công:

```bash
composer install
php artisan key:generate
php artisan migrate --force
npm install
npm run build
```

Sau core migrate, cài các module cần thiết từ Module Manager. Không chạy thủ công toàn bộ `modules/*/database/migrations` như core migrations.

`accounting-tax` có thể cài độc lập. `minvoice-connector` phụ thuộc `accounting-tax`; `inventory` phụ thuộc `cms` và `catalog`, còn AccountingTax chỉ sử dụng Inventory khi module này thực sự enabled và cung cấp capability phù hợp.

Khi dùng AccountingTax, phải chạy cả queue worker và scheduler cho export, email, đồng bộ nhà cung cấp, dọn artifact tạm và kiểm chứng audit. Kết nối Minvoice/mSMI mặc định không được phép gọi mạng hoặc production. Chỉ cấu hình `ACCOUNTING_EINVOICE_CONTRACT_VERSION` sau khi đúng phiên bản hợp đồng API đã qua sandbox, UAT và phê duyệt pháp lý.

## Kiểm thử

```bash
composer test
php artisan test --do-not-cache-result tests/Feature/FreshProductionInstallTest.php
npm run build
```

Localization release gate:

```bash
php artisan localization:audit --website=website-main --strict
php artisan localization:audit --website=website-main --strict --require-ready
```

`--strict` kiểm tra cấu trúc; chỉ `--require-ready` mới chứng minh target locale đủ điều kiện phát hành.

### Tạo lại database phát triển

`php artisan migrate:refresh` rollback các migration đã chạy, bao gồm migration
của module, rồi chạy lại **core**. Lệnh này xóa dữ liệu trong các bảng bị rollback;
không dùng để nâng cấp deployment có dữ liệu cần giữ. Nâng cấp dùng `php artisan migrate`
và Module Manager.

`php artisan migrate:refresh --seed` cài/bật lại CMS và tạo tài khoản System Owner
qua seeder. Các module khác cần cài lại qua Module Manager. Môi trường production
vẫn bắt buộc cấu hình `AIO_SYSTEM_OWNER_PASSWORD`; nếu vừa sửa `.env`, chạy
`php artisan config:clear` trước khi seed. `--step` chỉ refresh số migration đã chọn,
kể cả migration module; `--path` giữ nguyên phạm vi được chỉ định.

Kiểm chứng vòng migrate/refresh/seed trên database cô lập:

```bash
php -d memory_limit=256M vendor/bin/phpunit tests/Feature/FreshProductionInstallTest.php
php tests/Support/migration-refresh-mysql.php
```

Smoke MySQL cần quyền tạo database, tự tạo database tên ngẫu nhiên
`aio_refresh_test_*` và chỉ xóa database đó khi kết thúc; không sao chép dữ liệu local.

## Tài liệu bắt đầu

- `docs/ai-session-bootstrap-prompt.md`
- `docs/architecture/admin-access-control.md`
- `docs/architecture/localization-foundation.md`
- `docs/architecture/localization-rollout-runbook.md`
- `docs/architecture/accounting-tax-module.md`
- `docs/theme-authoring-guide.md`
- `docs/landing-page-builder.md`

Khi tài liệu và runtime mâu thuẫn, ưu tiên source code, migration/schema hiện hành và regression tests.
