# Customers — runtime contract

Đối chiếu source ngày **20/10/2026**, module `customers` `0.1.0`. Đọc cùng [kiến trúc và phạm vi](../architecture/customers-module.md). Route thật nằm tại [Customers routes](../../modules/Customers/routes.php); kiểm quyền và logic nằm trong [service](../../modules/Customers/Services/CustomerService.php), không chỉ middleware.

## 1. Quy ước

- Admin prefix: **`/admin/api/customers`**, session Admin + CSRF + admin active/website, `module.enabled:customers`, throttle hiện tại 120 request/phút. `X-Website-Key` phải thuộc quyền actor và được Core xác thực.
- Book/profile/binding/export public IDs là UUID. `account_id`, `admin_id`, Accounting organization/party và field-definition ID là integer nội bộ; không tráo hai loại.
- Response `{data: ...}`; dữ liệu danh sách hồ sơ nằm trong `data.items` và có page/per_page/total. Lỗi theo Laravel: 401/403/404/409/410/422/503 tùy phiên/quyền/context/version/expiry/validation/readiness.
- POST/PUT mutation Admin dùng `Idempotency-Key`, 8–100 ký tự `A-Za-z0-9:_.-`. Giữ cùng key/body khi retry. DELETE bộ lọc riêng có contract riêng bên dưới. Expected version áp dụng cho đối tượng đang sửa; không đổi payload của một command chưa rõ kết quả để thu lại/gửi lại.
- Journal Customers lưu result references, không lưu toàn bộ PII/token/options. Replay authorize lại; response hồ sơ sau replay được chiếu theo quyền hiện hành. Token invitation chỉ có ở lần phát đầu.
- Quyền Core kết hợp book role: reader cho đọc, editor cho tác nghiệp, manager cho cấu hình/hợp nhất/link account. Các core permissions vẫn bắt buộc, book grant không tạo quyền Core.
- Không có `customers.sensitive` thì contact bị che và dữ liệu riêng bị giới hạn. Không coi UUID khó đoán là authorization.

Nguồn: [access](../../modules/Customers/Services/CustomerAccessService.php), [commands](../../modules/Customers/Services/CustomerCommandService.php), [controller](../../modules/Customers/Http/CustomerController.php).

## 2. Book, hồ sơ, tìm kiếm và archive

| Method/path | Quyền chính | Request/response |
| --- | --- | --- |
| GET `/books` | `customers.view` | Các book active trong website và grant hiện hành. |
| POST `/books` | `customers.settings` | `{name,code}`; tạo book và manager grant cho actor. |
| GET `/books/{book}/profiles` | `customers.view` | `q` hoặc `query`, `status=active|archived|merged|all`, `type=person|company`, `tag`, `page`, `per_page` 1–100. |
| POST `/books/{book}/profiles` | `customers.create` | Body hồ sơ; editor và sensitive permission cho field nhạy cảm. |
| GET `/books/{book}/profiles/{profile}` | `customers.view` | Projection theo quyền; alias được resolve về survivor. |
| PUT `/books/{book}/profiles/{profile}` | `customers.update` | `expected_version` và field cần sửa. |
| POST `/books/{book}/profiles/{profile}/archive` | `customers.archive` | `{expected_version,archived:true|false}`; không xóa history/source. |
| GET `/books/{book}/profiles/{profile}/timeline` | `customers.view` | `before` event cursor, `limit` 1–100; sự kiện master và phần hoạt động consumer được authorize theo implementation. Không suy ra lịch sử toàn hệ thống. |
| GET `/books/{book}/saved-filters` | `customers.view` + reader | Bộ lọc riêng của actor trong book: `public_id,name,filters`. |
| POST `/books/{book}/saved-filters` | Như trên | `{name,filters:{q?,status?,type?,tag?,per_page?}}`; name tối đa 80, cùng name cập nhật; tối đa 50 bộ lọc/actor/book; có Idempotency-Key. |
| DELETE `/books/{book}/saved-filters/{filter}` | Như trên | UUID bộ lọc actor sở hữu; `{deleted:true}`, không cần key; không tồn tại/đã xóa trả 404. |
| GET `/commands/{key}` | `customers.view` + authorize context command | committed reference hoặc unknown; không trả lại PII/token của command gốc. |

Body hồ sơ gồm `name`, `type`, `code?`, `legal_name?`, `tax_code?`, `birthday?`, `notes?`, `phone?`, `email?`, `country_code?`, `contacts?`, `addresses?`, `tags?`, `custom_fields?`. Phone/email không unique giữa hai người; mã hồ sơ unique trong book.

```json
{
  "type": "person",
  "name": "Nguyễn An",
  "phone": "0901234567",
  "country_code": "VN",
  "contacts": [
    {"kind": "phone", "value": "0901234567", "country_code": "VN", "is_primary": true},
    {"kind": "email", "value": "an@example.test", "is_primary": true}
  ],
  "addresses": [{"kind": "shipping", "line1": "Địa chỉ giao hàng", "country_code": "VN", "is_default": true}],
  "tags": ["khach-hang-ban-le"],
  "custom_fields": {"customers.preferred_channel": "phone"}
}
```

Chỉ gửi custom field đã đăng ký đúng book/type. Khi có `contacts`, dùng đó làm bộ contact gửi lên; không đồng thời giả định `phone/email` sẽ tự cộng thêm ngoài danh sách. Sửa một trường contact qua phone/email sẽ thay contact cùng loại theo logic service; client phải đọc lại response.

## 3. Consent và quan hệ

| Method/path | Quyền | Body |
| --- | --- | --- |
| GET `/books/{book}/profiles/{profile}/consents` | `customers.view` | Lịch sử consent, không phải một boolean marketing toàn hệ thống. |
| POST `.../consents` | `customers.consent.manage` | `expected_version`, `purpose`, `channel=email|sms|phone|all`, `brand`, `state=unknown|granted|denied|withdrawn`, `policy_version` bắt buộc khi granted, `evidence?`. |
| POST `.../relationships` | `customers.update` | `expected_version`, `related_profile_id`, `role=contact|employee|representative|household|other`, `starts_at?`, `ends_at?`; cùng book. |

Consent append-only, có source/actor/time; không ghi đè lần withdraw cũ và không suy consent từ import/merge/account-link. Evidence được mã hóa; projection thông thường không cung cấp evidence raw. Chưa có engine kiểm từng campaign/provider hoặc diễn giải đầy đủ nghĩa vụ pháp lý theo jurisdiction.

## 4. Trường mở rộng, duplicate và merge

| Method/path | Quyền | Contract |
| --- | --- | --- |
| GET `/books/{book}/fields` | `customers.view` | Definition theo quyền sensitive. |
| POST `/books/{book}/fields` | `customers.settings` + manager | `namespace?` mặc định customers, `key`, `label`, `type=text|number|date|boolean|select`, `options?`, `required?`, `sensitive?`, `searchable?`, `status?`, `expected_version` khi sửa. |
| GET `/books/{book}/duplicates` | `customers.merge` + manager | Cặp ứng viên theo contact, không tự hợp nhất. |
| POST `/books/{book}/profiles/{source}/merge` | `customers.merge` + manager | `{target_id,expected_version,target_version,reason,field_choices?}`. Hai profile active cùng loại/book; source thành alias. |

Không đổi type field đã có values nếu chưa có migration. Value lưu theo `namespace.key`, được validate và mã hóa; required được kiểm trên mọi lần ghi hồ sơ kể cả khi request không gửi custom_fields. Field active, searchable, không sensitive lưu search_value tối đa 255 ký tự có index và tham gia tìm prefix qua q/query. Không cho sensitive+searchable; đổi cờ cấu hình cập nhật search values hiện có. Đây là index đọc trong phạm vi book, không phải full-text hoặc filter field tùy ý.

`field_choices` nhận các khóa name/legal_name/tax_code/birthday/notes/contacts/addresses/tags với giá trị source/target/combine; combine chỉ dùng cho ba field danh sách. Field không chọn giữ target. Sensitive write vẫn cần quyền riêng. Merge tạo consent mới unknown, hoặc withdrawn nếu nguồn/đích có denied/withdrawn trong dimension tương ứng; không mở rộng consent. Merge không viết lại snapshot bill, không gộp credentials, không tự merge Accounting parties. Liên kết tài khoản tại profile nguồn chặn merge để xử lý riêng. Không có API unmerge hoàn chỉnh trong bản này.

## 5. Grant và binding consumer

| Method/path | Quyền | Contract |
| --- | --- | --- |
| GET `/books/{book}/settings-options` | `customers.settings` | Options cấu hình theo website/quyền. |
| GET `/books/{book}/grants` | `customers.settings` + manager | Admin/role của book. |
| POST `/books/{book}/grants` | Như trên | `admin_id`, `role=reader|editor|manager`, `revoke?`; không gán role Core, không tự thu hồi manager của mình. |
| GET `/books/{book}/bindings` | Như trên | Binding hiện tại và mode. |
| POST `/books/{book}/bindings` | Như trên + native configure | `module_key`, `scope_type`, `scope_key`, `abilities`, `expected_version` nếu có. |
| POST `/bindings/{binding}/migration` | `customers.settings` + manager + native configure | `{commit:false}` preview; `{commit:true,cutover:false}` import; `{commit:true,cutover:true}` chuyển nguồn sau commit. |

Abilities hiện tại: **lookup/create/view/update/attach/history**. Native configure là quyền riêng của provider, không phải ability cấp cho thu ngân trong binding.

```json
{
  "module_key": "retail-pos",
  "scope_type": "chain",
  "scope_key": "<chain public UUID>",
  "abilities": ["lookup", "create", "view", "update", "attach", "history"]
}
```

Retail runtime gọi gateway bằng **branch UUID**, nhưng binding lưu **chain UUID**. Cấu hình/di trú Retail yêu cầu `retail.setup.manage` global và live HQ membership. FNB binding theo **website_key**; context runtime dùng outlet native của provider. Không sử dụng Accounting organization làm book scope.

Không tự đổi book của một binding đã tồn tại; API trả conflict để yêu cầu quy trình di chuyển dữ liệu riêng. Tạo binding vẫn ở legacy, không tự cutover. Preview phải được xem lại: row counts, mã trùng, trạng thái archived, contact trùng và source mapping. Source ID Retail là public UUID local customer; source FNB là chuỗi ID integer local profile. Migration commit xử lý tối đa 500 hồ sơ mới mỗi lượt; `has_more=true` thì dùng key mới chạy lượt tiếp theo, chỉ cutover khi hết nguồn chưa mapping. Mapping đã có được đối chiếu source hash khi còn legacy; nguồn thay đổi cần đối soát trước. Contact trùng được trả `warnings` với action create_separate_profile, không tự merge; mã/identity conflict vẫn là lỗi. Không đoán ID từ điện thoại.

**Shared mode không fallback.** Khi Customers bị tắt, thiếu capability, book archived hoặc quyền bị thu hồi, consumer trả lỗi/trạng thái không ready. Giữ lịch sử giao dịch và source mapping; không xóa local profiles để vượt qua conflict. Không dùng sửa `cust_bindings.mode` trực tiếp để rollback.

Nguồn: [integration service](../../modules/Customers/Services/CustomerIntegrationService.php), [gateway](../../modules/Customers/Services/ConsumerCustomerService.php), [Retail provider](../../modules/RetailPos/Services/RetailCustomersProvider.php), [FNB provider](../../modules/FnbPos/Services/FnbCustomersProvider.php).

## 6. Consumer gateway và Retail POS

`Modules\Customers\Services\ConsumerCustomerService` là contract service nội bộ: `status(actor,module,scope,lock=false)`, `lookup`, `listing`, `create`, `update`, `profile`, `snapshot`, `assertAccess`, `resolveLegacy`, `legacyIds`, `profileIds`. Gateway kiểm binding abilities, readiness và native provider; không dùng một global service account để đọc mọi book.

| Retail API | Hành vi |
| --- | --- |
| GET `/admin/api/retail/customer-context?branch_id=UUID` | mode/available/ready/book_id/binding_id. Legacy có thể ready dù Customers chưa cài vì dữ liệu local còn sử dụng được. |
| GET `/admin/api/retail/customers?branch_id=UUID&query=...` | Legacy local chain hoặc lookup shared book; native `retail.customer.view`. |
| POST `/admin/api/retail/customers`, PUT `.../{UUID}` | Native `retail.customer.manage`, Idempotency-Key; shared gateway khi cutover, local khi legacy. |
| POST `/admin/api/retail/sales` | Có thể thêm `customer_id` cho local hoặc `shared_customer_id` cho shared. Khách đã gắn cần readiness; bỏ trống vẫn bán khách lẻ khi Customers tắt, không fallback local. |
| GET `/admin/api/retail/customers/{UUID}/history?branch_id=UUID` | Native customer.view + sale.view, history ability; chỉ branch được phép. Bao gồm legacy mapping và merged aliases; tối đa 100 sale completed gần nhất. |

Retail sale lưu buyer_snapshot tại quote, giữ customer_id cũ, thêm shared_customer_id. Không cập nhật snapshot lịch sử khi đổi master/merge; thiếu customer.view thì phone/email bị loại khỏi response sale. Shared source bị tắt giữa quote và complete chặn trước Inventory/tender side effects. Chi tiết POS/ca/tồn nằm trong [Retail contract](retail-pos-runtime-contract.md).

FNB `0.2.0-dev.3` có [directory adapter](../../modules/FnbPos/Services/FnbCustomerDirectory.php) và [provider](../../modules/FnbPos/Services/FnbCustomersProvider.php), thêm nullable shared_customer_id trên session/check. GET `/admin/api/fnb/outlets/{outlet}/customers-integration` báo mode/readiness; customer native routes nhận integer ID legacy hoặc UUID shared. Cấu hình binding cần native HQ `fnb.chain.manage` và outlet access. Notes/ngày sinh/consent nghiệp vụ riêng của FNB không tự chuyển thành consent toàn book; snapshot/lịch sử cũ giữ riêng và history theo alias UUID.

## 7. Accounting explicit mapping

`POST /books/{book}/profiles/{profile}/accounting-sync` với `{branch_id,organization_id}` và Idempotency-Key. Cần quyền đọc Customers/book, native Retail customer.view/membership, mapping pháp nhân Retail→organization, **accounting.party.manage + accounting.integration.sync** đúng organization/global và Accounting active.

Kết quả `{customer_id,organization_id,party_id,created,mode:"explicit_source_link"}`. Adapter tạo/reuse AcctPartySource theo organization + module customers + type profile + profile UUID, giữ dữ liệu Accounting đã chỉnh sửa. Không tự tạo hóa đơn/ghi sổ/thanh toán, không tự đồng bộ ngược hay hợp nhất party sau merge Customers. Nguồn: [adapter](../../modules/RetailPos/Services/RetailCustomerAccountingAdapter.php).

## 8. Import/export và tài khoản website

| Method/path | Contract |
| --- | --- |
| POST `/books/{book}/imports` | `customers.import` + sensitive/editor; `{rows:[...],commit:false|true}` tối đa 1.000. Preview read-only, commit atomic tạo mới; không upsert theo phone. |
| POST `/books/{book}/exports` | `customers.export` + sensitive/editor; key để retry; queue job và trả public export UUID/status, created_at, expires_at, error. |
| GET `/exports/{UUID}` | Requester + quyền hiện hành + auth version + expiry; metadata như trên, client chấp nhận created_at tùy response/version; không trả đường dẫn storage private. |
| GET `/exports/{UUID}/download` | Như trên, file private/checksum; hết hạn 410. CSV sáu cột code/type/name/phone/email/status, chống formula injection. |
| POST `/books/{book}/profiles/{UUID}/account-link-invitations` | `customers.account.link` + manager; `{account_id}` integer Core Customer; token 64 ký tự, hết hạn 30 phút, hiển thị một lần. |

Invitation dành cho person active và account đích đã chọn. Retry cùng key không phát lại token; mất token cần phát invitation mới theo key mới, service làm hết hạn invitation cũ. Không tự gửi email/SMS.

Trong portal có locale/account segment hiện hành: `/{locale}/{accountSegment}/api/shared-customers`. Dùng session **customer**, CSRF, module enabled và throttle 30/phút:

| Method/path tương đối | Contract |
| --- | --- |
| GET `/` | Những profile account đã link trong website hiện hành. |
| POST `/accept` | `{token,password}`; đúng recipient, mật khẩu hiện tại, invitation chưa dùng/chưa hết hạn, issuer vẫn có quyền. |
| GET `/{UUID}` | Chỉ profile có account link hợp lệ; projection self-service. |
| PUT `/{UUID}` | `expected_version`, `name?`, `phone?`, `addresses?`; không sửa role, book, tax/notes tùy ý. |
| POST `/{UUID}/consents` | Consent của chính profile linked, expected_version và purpose/channel/brand/state/policy_version. |

Nguồn: [portal routes](../../routes/web.php), [account-link service](../../modules/Customers/Services/CustomerAccountLinkService.php), [portal controller](../../modules/Customers/Http/CustomerPortalController.php). Luồng login/password Core được giữ riêng; không tự xác minh bằng email/phone khớp.

## 9. Sự kiện, queue và vận hành

`cust_events` lưu event ID/type/aggregate version/changed_fields và metadata không chứa toàn bộ contact. `CustomerChanged` gửi nội bộ tới module có shared binding; listener phải deduplicate eventId+destination, không suy exactly-once. Dispatch hiện retry tối đa tám attempts, lùi năm phút sau lỗi; disabled destination tiếp tục pending/failed. Không có public webhook/network integration mặc định.

```sh
php artisan customers:dispatch-events --limit=100
php artisan customers:prune-exports
```

Scheduler dispatch mỗi phút, prune hàng giờ; chạy queue worker/scheduler theo cấu hình môi trường. Event `delivered` chỉ xác nhận dispatch nội bộ không lỗi, không bảo đảm consumer bên ngoài đã xử lý. Export có lease năm phút và artifact private riêng từng lượt; worker kiểm quyền lại trước publish, download kiểm quyền/checksum/expiry; prune xóa file hết hạn. Nguồn: [outbox](../../modules/Customers/Services/CustomerOutboxService.php), [worker export](../../modules/Customers/Jobs/BuildCustomerExport.php), [scheduler](../../routes/console.php).

## 10. Cài đặt, kiểm thử và giới hạn

Sao lưu DB/storage/config và rehearsal trên clone trước cài/nâng. Dùng App Store/lifecycle `POST /admin/api/modules/customers/install|enable|upgrade`, quyền store.module global tương ứng; không ghi trực tiếp module_installations. Customers không có hard dependency FNB/Retail/Accounting. Nâng consumer qua lifecycle để nhận schema mới trước binding/cutover. Retail hiện `0.1.0-dev.2` với migration shared customer snapshot; dữ liệu nghiệp vụ đang chạy không được `migrate:fresh` hoặc seed lại.

```sh
# Tập Customers và consumer trực tiếp; bộ release 22 file còn gồm hồi quy Core/Retail/FNB.
APP_ENV=testing vendor/bin/phpunit --do-not-cache-result tests/Feature/CustomersModuleTest.php tests/Feature/CustomersAccessAndPortalTest.php tests/Feature/CustomersAdvancedTest.php tests/Feature/CustomersMigrationTest.php tests/Feature/FnbCustomersIntegrationTest.php tests/Feature/RetailSharedCustomersTest.php tests/Feature/CustomerPortalTest.php
php tests/Support/customers-mysql-gates.php --allow-isolated-mysql
node --test tests/UnitJavaScript/*.test.js
npm run build
```

Release tập trung **22 file / 206 tests / 2.623 assertions pass**, không phải toàn AIO suite. MySQL standalone **6 gates / 42 assertions pass**, scratch database và export đã dọn/xác minh; JS **54/54 pass**, build pass. Script MySQL chỉ tạo database local tên ngẫu nhiên và kiểm owner marker trước thao tác, không chạy vào DB nghiệp vụ; cần đọc script/config trước chạy. SQLite UI riêng đã thử create/contact/search/saved filter/details/activity/export ready/merge preview, không console error. Không suy capacity/production pilot từ các bằng chứng này.

Local DB `aio` đã bật Customers `0.1.0`, nâng FNB `0.2.0-dev.3`/Retail `0.1.0-dev.2`; ba profile FNB đã migrate sau dry-run valid. Legacy profile/check snapshot hashes và số Core login accounts giữ nguyên. Retail hiện 0 chain nên chưa cần cutover. Deployment khác phải thực hiện lại backup, lifecycle và preview/cutover theo dữ liệu riêng.

Uninstall Customers bị từ chối để giữ history/source mappings. Disable giữ dữ liệu nhưng consumer shared phải ngừng chức năng cần Customers; không chuyển về legacy tự động. Archive chưa phải erasure/anonymization; CSV không phải gói data-subject export đầy đủ; không có unmerge, campaign/loyalty engine, invoice tax issuance, public webhook delivery hay capacity/production pilot đã nghiệm thu. Xem [giới hạn kiến trúc](../architecture/customers-module.md#8-giới-hạn-phải-biết).
