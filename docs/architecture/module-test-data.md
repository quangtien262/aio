# Dữ liệu test trong App Store

App đang bật có nút **Data test** trên thẻ và trong phần quản lý app. Quyền tạo, xem các đợt và dọn dữ liệu dùng quyền Core global `store.module.upgrade` hiện có. Bộ chọn website xác định phạm vi của đợt test.

Mỗi lần tạo thêm một đợt riêng, có UUID, nội dung mẫu và số bản ghi. `request_id` giữ nguyên khi thử lại yêu cầu chưa chắc kết quả, nên gửi lại cùng yêu cầu không tạo trùng đợt. Không chạy installation seeder hay xóa dữ liệu theo tiền tố. Chức năng cũ của Project được thay bằng provider insert-only; dữ liệu demo cũ chưa có ownership ledger không tự động được nhận là dữ liệu test để xóa.

## Sổ theo dõi

- `module_test_data_batches`: website, app, người tạo, mã yêu cầu, nội dung mẫu, số bản ghi, trạng thái và biên nhận xóa.
- `module_test_data_records`: bảng, khóa chính xác của từng bản ghi, nhận diện ổn định và batch sở hữu. Bao gồm bản ghi phụ thuộc thuộc app khác do đợt test tạo mới.
- `module_test_data_scopes`: khóa phạm vi cho các thao tác tạo/dọn cùng website.

Tạo và dọn chạy trong transaction. Dọn theo một đợt, một app hoặc toàn bộ các đợt của website. Chỉ bản ghi có ownership mới được xóa; biên nhận batch và audit log được giữ lại. Chỉnh sửa nội dung thông thường không bỏ nhãn test nếu nhận diện gốc vẫn còn. Nếu khóa nhận diện bị dùng lại hoặc có dữ liệu ngoài tập dọn đang tham chiếu tới bản ghi test, toàn bộ yêu cầu dọn bị chặn và rollback.

## Hợp đồng provider

Manifest khai báo `test_data_provider`, triển khai `ModuleTestDataProvider::preview()` và `create(ModuleTestDataContext)`. Mọi bản ghi mới phải qua `context->insert()`, hoặc chỉ `track()` đúng bản ghi mới do domain service vừa tạo. Không cập nhật/tag bản ghi thật, không cấp quyền Core, không đổi cấu hình đang dùng, không gửi email/API ngoài hay tạo giao dịch đã ghi sổ. Các provider hiện tạo master riêng, hồ sơ giả lập hoặc chứng từ nháp; dữ liệu công khai/CMS và menu Cafe giữ trạng thái nháp.

Provider khai báo `references()` cho liên kết mềm không có FK. Hỗ trợ cột thường, cặp cột composite, điều kiện phân loại `where`, JSON `->` và đường dẫn mảng `*`. Các FK được đọc từ schema tự động. Khi bổ sung consumer hoặc cấu trúc JSON mới phải bổ sung map tương ứng và kiểm thử việc chặn dọn. Các writer không dùng runtime barrier phải xác thực lại và shared-lock parent trong transaction trước khi ghi liên kết mềm.

Dọn dữ liệu giữ khóa lifecycle của package, khóa exclusive installation/runtime/site tương ứng, khóa bản ghi sở hữu và dùng current locking reads cho reference scan. Không tắt FK. Tạo mới kiểm tra app/dependency đang bật và không trong quá trình thay đổi vòng đời.

## Kiểm chứng

Các test `ModuleTestDataTest`, `PosModuleTestDataProvidersTest`, `OperationalModuleTestDataProvidersTest` và `RemainingModuleTestDataProvidersTest` chạy trên SQLite memory, kiểm chứng replay, rollback, phạm vi website, quyền HTTP, bảo toàn dữ liệu thật, FK cascade/soft/JSON references và create/remove roundtrip của các provider. Không chạy các bộ tạo fixture trên DB nghiệp vụ để kiểm thử tự động.

Gate MySQL: `php tests/Support/module-test-data-mysql-gate.php`. Script tự tạo database scratch tên ngẫu nhiên, kiểm tra migration, replay, FK cascade và JSON reference mới commit sau khi snapshot cũ được tạo, rồi xóa chính database scratch trong `finally`. Không ghi dữ liệu test vào database nghiệp vụ; tài khoản DB cần quyền tạo/xóa database scratch. Gate này bổ sung kiểm chứng locking/current-read mà SQLite không mô phỏng được.
