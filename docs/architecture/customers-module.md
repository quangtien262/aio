# Customers — kiến trúc và phạm vi đã triển khai

Đối chiếu source ngày **20/10/2026**. Module `customers` đang ở `0.1.0`; đây là bản triển khai cần nghiệm thu theo dữ liệu và môi trường thực, không phải cam kết đã hoàn thành toàn bộ CRM hay yêu cầu tuân thủ pháp lý. Hợp đồng HTTP và thao tác chuyển đổi nằm tại [runtime contract](../api/customers-runtime-contract.md).

## 1. Trách nhiệm và định danh

Customers sở hữu hồ sơ khách dùng chung theo **nhóm dữ liệu khách hàng** (`book`), liên hệ, địa chỉ, nhãn, quan hệ, consent, định nghĩa/giá trị trường mở rộng, source mappings và liên kết tới tài khoản website. Có app quản trị riêng tại `/admin/modules/customers` và thành phần chọn/tạo nhanh có thể nhúng vào consumer.

Điều hướng quản trị sử dụng menu chính của app, không lặp lại bằng tab trong nội dung. Các trang có URL riêng, hỗ trợ tải lại và lịch sử trình duyệt:

| Menu | URL | Quyền menu |
| --- | --- | --- |
| Danh bạ | `/admin/modules/customers` | `customers.view` |
| Hồ sơ trùng | `/admin/modules/customers/duplicates` | `customers.view` và `customers.merge` |
| Nhập & xuất | `/admin/modules/customers/data` | `customers.view` và ít nhất một trong `customers.import`, `customers.export` |
| Thiết lập | `/admin/modules/customers/settings` | `customers.view` và `customers.settings` |

Manifest dùng `permission` kết hợp AND với nhóm OR `permissions_any`; navigation và liên kết nhanh Dashboard áp dụng cùng quy tắc. Quyền menu không thay thế kiểm tra book grant và quyền API. Trong danh bạ không có quyền tương ứng, trang hiển thị thông báo giới hạn quyền; riêng Nhập & xuất chỉ hiển thị thao tác được cấp.

| Đối tượng | Chủ sở hữu và ý nghĩa |
| --- | --- |
| `App\Models\Customer`, bảng `customers` | Core giữ tài khoản đăng nhập website, password, session và email unique hiện hữu. Không đổi thành bảng master B2B/B2C; khách tại quầy không bắt buộc tạo account. |
| `cust_profiles` | Customers giữ master `person|company`, UUID public ổn định, mã trong book, trạng thái và version. Phone/email không unique giữa các hồ sơ. |
| `cust_books` và grants | Partition nội bộ module gắn website; không phải core tenant hoặc pháp nhân kế toán. Hai consumer chỉ dùng chung khi binding tường minh tới cùng book. |
| Local customer FNB/Retail | Giữ ID nguồn và lịch sử đã có; adapter/mapping điều phối chuyển sang master. Không gộp bằng phone/email. |
| `AcctParty`, `AcctPartySource` | Accounting giữ đối tác và quan hệ `customer|supplier|both` theo organization, thông tin kế toán và chứng từ. Công ty khách hàng không đồng nghĩa `AcctOrganization` vận hành AIO. |
| Giao dịch, tài chính, chương trình khách hàng | Sale/return/payment tại consumer; AP tại Purchasing/Accounting. Loyalty, ví, voucher, campaign, lead/opportunity và ticket/SLA tiếp tục thuộc app nghiệp vụ riêng. |

Nguồn: [manifest Customers](../../modules/Customers/module.json), [schema](../../modules/Customers/database/migrations/2026_10_20_300000_create_customer_master_tables.php), [tài khoản Core](../../app/Models/Customer.php), [AcctParty](../../app/Models/AcctParty.php), [source Accounting](../../app/Models/AcctPartySource.php).

## 2. Phân quyền nhiều lớp

Admin API dùng Core identity/session, `admin.active`, website context và permission module. Customers kết hợp **core permission + book grant** (`reader|editor|manager`). Global `customers.settings` bỏ qua yêu cầu book grant trong website hiện hành nhưng vẫn cần permission của thao tác; role thường phải có grant book. Không thêm scope branch vào Core, không mở rộng whitelist organization scope Accounting cho Customers.

- Quyền đọc danh bạ khác quyền thông tin nhạy cảm. Không có `customers.sensitive` thì phone/email được che; ghi chú, ngày sinh, địa chỉ và field nhạy cảm được giới hạn theo projection.
- Quyền cấu hình/import/export/merge/account-link/consent là các quyền riêng. Grant book không gán role Core hoặc quyền native cho consumer.
- `CustomerAccessService` kiểm actor active, trạng thái yêu cầu đổi mật khẩu, auth version và quyền website. Mutation/replay kiểm lại quyền hiện hành ở service, không chỉ tại middleware.
- Consumer được ủy quyền qua binding đã cấu hình, vẫn phải kiểm permission/membership native; không tự có quyền toàn book vì biết một UUID.
- Retail cấu hình binding toàn chain cần `retail.setup.manage` global và membership HQ còn hiệu lực; xem khách tại một branch không đủ quyền chuyển nguồn toàn chain.
- Runtime barrier bền vững phối hợp mutation và lifecycle. Shared mode phải fail closed khi Customers/book không sẵn sàng, không đọc/ghi lại local customer để tiếp tục ngầm.

Nguồn: [CustomerAccessService](../../modules/Customers/Services/CustomerAccessService.php), [command journal](../../modules/Customers/Services/CustomerCommandService.php), [consumer gateway](../../modules/Customers/Services/ConsumerCustomerService.php), [Retail provider](../../modules/RetailPos/Services/RetailCustomersProvider.php), [config consumer](../../config/customers.php).

## 3. Mô hình dữ liệu và khả năng hiện có

| Nhóm | Hành vi trong source |
| --- | --- |
| Hồ sơ | Danh sách phân trang/lọc/tìm; bộ lọc riêng theo Admin/book lưu trên server; person/company; mã unique trong book; create/update có expected version; archive/restore; alias sau merge. |
| Liên hệ | Nhiều phone/email, giữ giá trị gốc và normalized; chuẩn hóa VN về dạng `+84…`, email chữ thường; số nước ngoài cần dấu `+`. Không xác minh quyền sở hữu phone/email bằng normalization. |
| Địa chỉ/nhãn/quan hệ | Địa chỉ shipping/billing/both, nhãn, quan hệ giữa hồ sơ cùng book có vai trò và ngày hiệu lực. Không phải workflow pháp lý xác nhận quyền đại diện công ty. |
| Consent | Append-only theo purpose/channel/brand/state/policy_version/source/time; trạng thái unknown/granted/denied/withdrawn; grant yêu cầu policy_version. Evidence lưu mã hóa, không đưa vào payload sự kiện. |
| Trường mở rộng | Namespace/key, text/number/date/boolean/select, required/sensitive/searchable/status/version. Giá trị mã hóa; field searchable không nhạy cảm có search_value công khai trong phạm vi book và index tìm prefix. Required áp dụng cả khi request không gửi custom_fields; không cho đổi type khi đã có dữ liệu. |
| Nhập dữ liệu | JSON rows, preview và commit tạo mới; lỗi theo dòng; duplicate contact được xử lý theo contract hiện hành, không tự merge. Không phải công cụ cập nhật hàng loạt theo phone. |
| Xuất | CSV private, queue, lease worker, artifact riêng từng lượt, checksum, expiry và reauthorization trước publish/download. Các cột hiện tại: code/type/name/phone/email/status. |
| Hợp nhất | Hai hồ sơ active cùng book/cùng loại, kiểm version cả hai; nguồn thành alias, mappings chuyển về survivor. Có field_choices tường minh; consent về unknown hoặc withdrawn để không nới quyền. Giữ provenance nguồn; không tự gộp credentials hay tài khoản. |
| Tài khoản website | Admin có quyền tạo invitation ràng buộc account đích; khách đăng nhập đúng account, nhập token và mật khẩu hiện tại để chấp nhận. Tự xem/sửa projection cho phép và consent trong website hiện hành. |

`CustomerProfiles` là persistence/projection nội bộ; entry point công khai phải đi qua `CustomerService`, consumer gateway hoặc account-link service đã authorize. Không dùng thẳng `CustomerProfiles` trong route mới để bỏ qua book grants. Nguồn: [profiles](../../modules/Customers/Services/CustomerProfiles.php), [service](../../modules/Customers/Services/CustomerService.php), [bộ lọc riêng](../../modules/Customers/Services/CustomerSavedFilterService.php), [import](../../modules/Customers/Services/CustomerImportService.php), [export](../../modules/Customers/Services/CustomerExportService.php), [account links](../../modules/Customers/Services/CustomerAccountLinkService.php).

## 4. Tích hợp consumer và chuyển đổi

`CustomerConsumerProvider` đăng ký tại `config/customers.php`; provider phải authorize ngoài HTTP bằng actor/context hiện hành. `bindingScope` xác định partition của consumer, `legacyProfiles` cung cấp dữ liệu nguồn. Gateway hỗ trợ status, lookup, create/update, profile/snapshot, resolveLegacy, legacyIds và profileIds cho alias history.

| Consumer | Binding bền vững | Context thao tác | Lịch sử |
| --- | --- | --- | --- |
| Retail | `scope_type=chain`, `scope_key=chain public UUID` | Branch public UUID; permission + membership chain/branch + website | Sale giữ legacy customer_id, thêm shared_customer_id và buyer_snapshot. History union source mappings và alias IDs, chỉ branch được phép. |
| FNB | `scope_type=website`, `scope_key=website_key` | Outlet native context; quyền consumer và staff/outlet membership | FNB 0.2.0-dev.3 giữ local profile và buyer snapshot cũ; thêm shared ID trên session/check, nối history qua numeric source IDs và alias UUID. Notes/ngày sinh/consent riêng FNB không được tự nâng thành consent toàn book. |

Trạng thái mặc định là **legacy**. Tạo binding chưa chuyển nguồn. Luồng vận hành: cấu hình book/grants → bind consumer → preview migration → xử lý dữ liệu/cảnh báo → commit theo lô tối đa 500 hồ sơ mới → cutover tường minh khi `has_more=false`. Nguồn đã mapping nhưng đổi hash trong thời gian còn legacy chặn cutover để đối soát. Không đổi mode bằng sửa DB tay. Shared mode đã bật phải dùng Customers; disable/thiếu capability/book archived trả trạng thái không ready, không fallback local. Retail/FNB vẫn cho giao dịch khách lẻ không gắn hồ sơ; quote đã gắn shared customer phải kiểm readiness trước thanh toán.

Retail customer master cũ thuộc chain; nhiều branch cùng chain dùng cùng binding. FNB profile cũ thuộc website, có unique phone/core_customer_id theo website. Vì vậy không áp cấu trúc nhiều book theo từng outlet lên schema FNB cũ mà không qua adapter/migration. Không suy identity từ tên, phone hoặc email; source ID mới là lineage di trú.

Nguồn: [integration service](../../modules/Customers/Services/CustomerIntegrationService.php), [interface](../../modules/Customers/Contracts/CustomerConsumerProvider.php), [Retail sale service](../../modules/RetailPos/Services/RetailService.php), [FNB provider](../../modules/FnbPos/Services/FnbCustomersProvider.php).

## 5. Snapshot và Accounting

Retail chụp buyer snapshot khi tạo quote và không lấy tên/liên hệ master mới để viết lại sale khi complete. Sale shared kiểm lại readiness/identity trước tác động tồn/thu tiền. Thiếu quyền Retail customer.view thì response sale che phone/email trong snapshot. History sau merge theo cả UUID canonical và aliases, không sửa shared_customer_id lịch sử.

Adapter Accounting là thao tác **explicit source link**: đúng branch/book, mapping pháp nhân Retail → Accounting organization, quyền native `accounting.party.manage` và `accounting.integration.sync`, module/org active. Nó tạo một `AcctParty` cùng `AcctPartySource` cho profile nguồn; key retry không tạo lần hai và không ghi đè master Accounting đã chỉnh sửa. Audit chỉ có ID/thông tin kết quả, không copy PII vào audit payload.

Đây không phải đồng bộ hai chiều, không tự hợp nhất các đối tác kế toán sau merge Customers và không tạo payment/chứng từ thuế. Bridge sale nội bộ Retail giữ source buyer snapshot trong metadata; quy trình kế toán và phân loại thuế tiếp tục thuộc Accounting. Nguồn: [adapter](../../modules/RetailPos/Services/RetailCustomerAccountingAdapter.php), [Retail forward migration](../../modules/RetailPos/database/migrations/2026_10_20_200003_add_shared_customer_sale_snapshots.php), [Accounting bridge](../../modules/RetailPos/Services/RetailAccountingBridge.php).

## 6. Sự kiện và phần mở rộng

Mutation tạo `cust_events`; các destination module có shared binding nhận `cust_outbox_deliveries`. Dispatcher phát event nội bộ `CustomerChanged` với eventId, destination, website, profile UUID, type, aggregate version và changedFields; **không broadcast contact data**.

- Delivery có retry; extension phải deduplicate bằng eventId + destination và xử lý version cũ. Không có bảo đảm exactly-once cho side effect ngoài DB.
- Module đích disabled hoặc listener lỗi giữ failed/retry theo giới hạn trong source; đây không phải webhook công khai hay cơ chế gọi mạng đã xác minh.
- Listener cần đọc qua service có grant/context phù hợp. Event không cấp quyền đọc hồ sơ, không dùng Admin #1 làm quyền mặc định.
- `delivered` xác nhận dispatch/listener nội bộ không ném lỗi; không chứng minh một hệ thống bên ngoài đã nhận hoặc tồn tại listener nghiệp vụ ở mọi app.

Nguồn: [CustomerChanged](../../modules/Customers/Events/CustomerChanged.php), [outbox](../../modules/Customers/Services/CustomerOutboxService.php), [scheduler](../../routes/console.php).

## 7. Cài đặt, rollout và kiểm chứng

1. Sao lưu DB/storage/config theo quy trình AIO, lưu revision và module versions; rehearsal trên DB clone trước rollout sang môi trường khác.
2. Cài/bật Customers qua App Store/lifecycle. Module không hard-depend FNB/Retail/Accounting; Core login hiện hữu giữ riêng. Chạy upgrade consumer qua lifecycle để nhận schema/version mới, gồm Retail `0.1.0-dev.2`.
3. Cấp Core roles và book grants tối thiểu; thử tài khoản reader/editor/manager riêng. Cấu hình native consumer và HQ authority trước bind/migration.
4. Preview/commit/cutover từng consumer, đối chiếu row count/source mapping, hồ sơ archived, duplicate contact, profile/phone code và giao dịch trước/sau. Giữ legacy IDs/ledger; không import lặp để seed lại lịch sử.
5. Chạy queue worker và scheduler trong môi trường triển khai. Export/artifact private cần storage và retention job hoạt động; nếu muốn kiểm thủ công dùng các lệnh runtime contract.
6. Nghiệm thu đa website/book/branch/outlet, revoke khi replay/worker, shared-disabled, alias history, consent, import/export, account-link recipient và backup/restore trên release cố định.

Kết quả release tập trung: **22 file / 206 tests / 2.623 assertions pass**, gồm Customers, migration, FNB, Retail và hồi quy Core auth/RBAC/portal; đây không phải toàn bộ AIO suite. [MySQL standalone](../../tests/Support/customers-mysql-gates.php) **6 gates / 42 assertions pass**, đã xác minh dọn scratch database/export; JavaScript **54/54 pass**, build pass. UI trên SQLite riêng đã thử tạo/contact/tìm kiếm/lưu bộ lọc/details/activity/export ready/preview merge, không có console error. Các kết quả này không chứng minh capacity hoặc production pilot.

Trạng thái local ngày 20/10/2026: DB `aio` đã cài/bật Customers `0.1.0`, nâng FNB `0.2.0-dev.3` và Retail `0.1.0-dev.2`. Sau dry-run hợp lệ, ba profile FNB đã mapping/cutover; kiểm hash xác nhận legacy profiles/check snapshots và số tài khoản đăng nhập không đổi. Retail chưa có chain nên không có cutover Retail cần thực hiện. Đây là trạng thái local, không tự áp dụng cho deployment khác.

## 8. Giới hạn phải biết

- Không có self-service xóa toàn bộ dữ liệu/anonymization/retention hold đầy đủ. Archive không phải erasure; snapshot và journal consumer có chính sách lưu riêng.
- CSV là danh sách sáu cột, không phải bản xuất toàn bộ dữ liệu cá nhân gồm consent/địa chỉ/custom fields. Worker đọc dữ liệu lúc chạy; chưa phải snapshot nhất quán toàn book giữa nhiều transaction đồng thời.
- Namespace trường hiện là cấu hình của manager; không phải marketplace extension có publisher signing/namespace ownership enforcement. Searchable chỉ lập index prefix cho giá trị không nhạy cảm, không phải full-text/filter engine cho mọi field.
- Quick-create tại POS nhận tập field tối thiểu. Nếu book có custom field bắt buộc mà consumer không cung cấp, tạo nhanh/di trú trả lỗi để bổ sung qua app Customers; cần rà cấu hình book trước cutover.
- Merge chỉ thay các field được chọn; combine áp dụng contacts/addresses/tags, không có unmerge hoàn chỉnh. Liên kết tài khoản và mapping kế toán cần xử lý riêng.
- Không tự gửi invitation qua email/SMS; token hiển thị một lần, admin chuyển qua kênh được phép. Không dùng email/phone trùng để xác minh account.
- Chưa kiểm capacity lớn, thiết bị/production pilot, khôi phục thảm họa hay đầy đủ yêu cầu pháp lý theo từng môi trường. Cần kiểm thử/đánh giá riêng trước rollout.
