# AIO Base Source

Base source cho nền tảng website AIO của HT Việt Nam, xây trên Laravel 13, React, Vite và Ant Design.

## Mục tiêu

- Dùng một base source chuẩn để nhân bản cho nhiều khách hàng.
- Tách nghiệp vụ thành module có thể cài đặt, bật tắt, nâng cấp và gỡ bỏ.
- Tách giao diện website thành theme package có thể đổi linh hoạt mà không làm mất dữ liệu.
- Quản trị toàn bộ hệ thống bằng admin shell React chạy bên trong Laravel.

## Tech Stack

- PHP 8.3+
- Laravel 13
- React 19
- Vite 7
- Ant Design 5

## Cấu trúc chính

- `app/`: lõi ứng dụng Laravel và core platform.
- `modules/`: các business module cài đặt qua store.
- `themes/`: các theme package cho public website.
- `resources/admin/src/`: admin shell React + Ant Design.
- `stubs/`: mẫu generate module và theme.
- `docs/architecture/`: sơ đồ kiến trúc và sơ đồ cấu trúc source code.

## Khởi động nhanh

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run dev
php artisan serve
```

## Tài liệu sơ đồ

- `docs/architecture/aio-overall-architecture.svg`
- `docs/architecture/aio-module-store-flow.svg`
- `docs/architecture/aio-theme-engine-flow.svg`
- `docs/architecture/aio-source-code-structure.svg`

## Hướng phát triển tiếp theo

- Tạo generator cho module và theme từ `stubs/`.
- Bổ sung auth guard riêng cho admin và customer.
- Xây module manager, theme manager, setup wizard và RBAC.
