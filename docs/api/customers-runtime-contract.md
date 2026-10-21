# Customers — runtime contract

Đối chiếu source ngày **20/10/2026**, module `customers` `0.1.0`. Đây là contract của implementation hiện tại; không phải OpenAPI đầy đủ hoặc cam kết rollout ở mọi môi trường. Đọc cùng [kiến trúc và phạm vi](../architecture/customers-module.md). Route thật nằm tại [Customers routes](../../modules/Customers/routes.php); kiểm quyền và logic nằm trong [service](../../modules/Customers/Services/CustomerService.php), không chỉ middleware.

## 1. Quy ước

- Admin prefix: **`/admin/api/customers`**, session Admin + CSRF + admin active/website, `module.enabled:customers`, throttle hiện tại 120 request/phút. `X-Website-Key` phải thuộc quyền actor và được Core xác thực.
- Book/profile/binding/export public IDs là UUID. `account_id`, `admin_id`, Accounting organization/party và field-definition ID là integer nội bộ; không tráo hai loại.
- Với Admin Customers API, gửi `Accept: application/json`, `Content-Type: application/json`, cookie session cùng origin và `X-CSRF-TOKEN` trên request ghi; frontend hiện lấy CSRF từ meta tag trong shell. Response JSON `{data: ...}` kèm `Cache-Control: private, no-store`; download trả file. Danh sách hồ sơ nằm trong `data.items`, có `page/per_page/total/stats/permissions`. Không áp envelope này máy móc cho FNB native API vốn có `meta` riêng. Lỗi theo Laravel; xem bảng xử lý lỗi bên dưới.
- Mutation đi qua `CustomerCommandService` dùng `Idempotency-Key`, 8–100 ký tự `A-Za-z0-9:_.-`, định danh theo **website + actor + key**. Giữ cùng operation/body/key khi retry; cùng key khác fingerprint trả 409. Preview import/migration là POST read-only, không đòi key ở service; DELETE bộ lọc, Accounting source-link và portal có contract riêng bên dưới. Expected version áp dụng cho đối tượng đang sửa; không đổi payload của command chưa rõ kết quả.
- Journal Customers lưu result references, không lưu toàn bộ PII/token/options. Replay authorize lại trước khi trả kết quả; response hồ sơ sau replay được đọc/chiếu theo quyền hiện hành, không phải ảnh chụp response lần đầu. Token invitation chỉ có ở lần phát đầu. `GET /commands/{key}` chỉ xem command của chính actor trong website hiện hành và authorize lại ý định gốc.
- Quyền Core kết hợp book role: reader cho đọc, editor cho tác nghiệp, manager cho cấu hình/hợp nhất/link account. Các core permissions vẫn bắt buộc, book grant không tạo quyền Core.
- Không có `customers.sensitive` thì contact bị che và dữ liệu riêng bị giới hạn. Không coi UUID khó đoán là authorization.

Nguồn: [access](../../modules/Customers/Services/CustomerAccessService.php), [commands](../../modules/Customers/Services/CustomerCommandService.php), [controller](../../modules/Customers/Http/CustomerController.php).

## 2. Book, hồ sơ, tìm kiếm và archive

| Method/path | Quyền chính | Request/response |
| --- | --- | --- |
| GET `/books` | `customers.view` | Các book active trong website và grant hiện hành. |
| POST `/books` | `customers.settings` | `{name,code}`; name tối đa 255, code tối đa 40 và unique trong website; tạo book và manager grant cho actor. |
| GET `/books/{book}/profiles` | `customers.view` | `q` hoặc `query` (tối đa 128 ký tự), `status=active\|archived\|merged\|all` mặc định active, `type=person\|company`, `tag`, `page` ≥1, `per_page` 1–100 mặc định 25 ở API. |
| POST `/books/{book}/profiles` | `customers.create` | Body hồ sơ; editor và sensitive permission cho field nhạy cảm. |
| GET `/books/{book}/profiles/{profile}` | `customers.view` | Projection theo quyền; alias được resolve về survivor. |
| PUT `/books/{book}/profiles/{profile}` | `customers.update` | `expected_version` và field cần sửa. |
| POST `/books/{book}/profiles/{profile}/archive` | `customers.archive` | `{expected_version,archived:true\|false}`; không xóa history/source. |
| GET `/books/{book}/profiles/{profile}/timeline` | `customers.view` | `before` là integer ID cursor của master event, `limit` 1–100 mặc định 50; trả `items,scope,as_of,unavailable_sources,next_cursor`. Chỉ trang đầu không có before được ghép hoạt động consumer đã authorize. |
| GET `/books/{book}/saved-filters` | `customers.view` + reader | Bộ lọc riêng của actor trong book: `public_id,name,filters`. |
| POST `/books/{book}/saved-filters` | Như trên | `{name,filters:{q?,status?,type?,tag?,per_page?}}`; name tối đa 80, cùng name cập nhật; tối đa 50 bộ lọc/actor/book; có Idempotency-Key. |
| DELETE `/books/{book}/saved-filters/{filter}` | Như trên | UUID bộ lọc actor sở hữu; `{deleted:true}`, không cần key; không tồn tại/đã xóa trả 404. |
| GET `/commands/{key}` | `customers.view` + authorize context command | committed reference hoặc unknown; không trả lại PII/token của command gốc. |

Body hồ sơ gồm `name`, `type`, `code?`, `legal_name?`, `tax_code?`, `birthday?`, `notes?`, `phone?`, `email?`, `country_code?`, `contacts?`, `addresses?`, `tags?`, `custom_fields?`. Phone/email không unique giữa hai người; mã hồ sơ unique trong book.

```json
{
  "type": "person",
  "name": "Nguyễn An",
  "contacts": [
    {"kind": "phone", "value": "0901234567", "country_code": "VN", "is_primary": true},
    {"kind": "email", "value": "an@example.test", "is_primary": true}
  ],
  "addresses": [{"kind": "shipping", "line1": "Địa chỉ giao hàng", "country_code": "VN", "is_default": true}],
  "tags": ["khach-hang-ban-le"]
}
```

Ví dụ trên dùng dữ liệu giả, phù hợp book không có required custom fields; không chạy thành seed trên dữ liệu nghiệp vụ. Khi muốn ghi `custom_fields: {"customers.preferred_channel":"phone"}`, phải đăng ký definition cùng namespace/key ở book trước.

Các quy tắc dễ tích hợp sai:

- `name` bắt buộc khi tạo; PUT chỉ cần `expected_version` và các trường cần đổi. `id` trong resource profile là UUID public, không phải primary key DB. GET alias có thể trả UUID survivor khác URL vừa gọi; PUT vào source đã merged bị 409, hãy đọc/sửa survivor.
- Khi gửi `contacts`, **thay toàn bộ danh sách contact**; khi gửi `addresses` hoặc `tags`, cũng thay toàn bộ danh sách tương ứng. Bỏ hẳn field khỏi body để giữ dữ liệu cũ. `contacts:[]`/`addresses:[]`/`tags:[]` có nghĩa xóa các mục hiện tại, không phải “không thay đổi”.
- Nếu không gửi `contacts` mà gửi `phone`/`email`, service thay contact cùng loại bằng giá trị mới; null/rỗng loại bỏ loại đó, các loại khác được giữ. Khi có `contacts`, không giả định phone/email sẽ tự cộng thêm ngoài danh sách. Đọc lại response sau save.
- Contact được deduplicate trong **một profile** theo kind+normalized; chỉ giữ một primary mỗi kind. `verified_at`, `source`, UUID và timestamps là metadata server, không phải bằng chứng người gọi được quyền xác minh contact.
- Tìm kiếm là name/code prefix; contact normalized exact, phone suffix có ít nhất bốn chữ số hoặc prefix VN chuyển `0` sang `+84`; custom field searchable không nhạy cảm cũng dùng prefix. `tag` là nhãn khớp chính xác. Chưa có fuzzy search, full-text hay bộ lọc tùy ý trên mọi custom field.
- `stats` đếm trạng thái của **toàn book**, còn total/items theo bộ lọc. Frontend không được suy tổng toàn danh bạ bằng độ dài trang đang hiển thị.
- Timeline không phải một cursor thống nhất cho mọi app: `before/next_cursor` phân trang master events; consumer activities chỉ ghép ở lần đọc đầu, có thể báo nguồn unavailable. Alias history được đưa vào theo quyền hiện hành.

| Giới hạn body hồ sơ | Giá trị trong validator hiện tại |
| --- | --- |
| name / code / notes | 255 / 60 / 5.000 ký tự; code chỉ `A-Za-z0-9._-` |
| contacts / addresses / tags / custom_fields | Tối đa 20 / 20 / 30 / 100 mục |
| birthday | `YYYY-MM-DD`, không ở tương lai |
| address line1 / line2 | 255 ký tự mỗi dòng; line1 bắt buộc khi gửi địa chỉ |
| custom field value | Validate theo definition; text tối đa 2.000 ký tự |

Nguồn chi tiết: [CustomerProfiles](../../modules/Customers/Services/CustomerProfiles.php).

## 3. Consent và quan hệ

| Method/path | Quyền | Body |
| --- | --- | --- |
| GET `/books/{book}/profiles/{profile}/consents` | `customers.view` | Tối đa 100 bản ghi consent gần nhất theo ID giảm dần; không có cursor riêng. Không phải boolean marketing toàn hệ thống hoặc toàn bộ ledger không giới hạn. |
| POST `.../consents` | `customers.consent.manage` | `expected_version`, `purpose`, `channel=email\|sms\|phone\|all`, `brand`, `state=unknown\|granted\|denied\|withdrawn`, `policy_version` bắt buộc khi granted, `evidence?`. |
| POST `.../relationships` | `customers.update` | `expected_version`, `related_profile_id`, `role=contact\|employee\|representative\|household\|other`, `starts_at?`, `ends_at?`; cùng book. |

Consent append-only, có source/actor/time; không ghi đè lần withdraw cũ và không suy consent từ import/merge/account-link. Evidence được mã hóa; projection thông thường không cung cấp evidence raw. Chưa có engine kiểm từng campaign/provider hoặc diễn giải đầy đủ nghĩa vụ pháp lý theo jurisdiction.

## 4. Trường mở rộng, duplicate và merge

| Method/path | Quyền | Contract |
| --- | --- | --- |
| GET `/books/{book}/fields` | `customers.view` | Definition theo quyền sensitive. |
| POST `/books/{book}/fields` | `customers.settings` + manager | `namespace?` mặc định customers, `key`, `label`, `type=text\|number\|date\|boolean\|select`, `options?`, `required?`, `sensitive?`, `searchable?`, `status?`, `expected_version` khi sửa. |
| GET `/books/{book}/duplicates` | `customers.merge` + manager | Tối đa 100 hàng ứng viên từ contact normalized giống nhau giữa profile active; một cặp có thể xuất hiện theo nhiều loại contact. Không phải audit toàn danh bạ hoặc tự hợp nhất. |
| POST `/books/{book}/profiles/{source}/merge` | `customers.merge` + manager | `{target_id,expected_version,target_version,reason,field_choices?}`. Hai profile active cùng loại/book; source thành alias. |

Không đổi type field đã có values nếu chưa có migration. Value lưu theo `namespace.key`, được validate và mã hóa; required được kiểm mỗi lần đi qua `CustomerProfiles::save` (create/update/import/consumer/migration), kể cả khi request không gửi custom_fields; không suy rằng archive/consent cũng validate lại required fields. Với key được gửi, service validate value theo required/type; với key không gửi, required hiện chỉ kiểm có row giá trị. Đổi optional → required không phải backfill hoặc tái kiểm toàn bộ giá trị cũ (kể cả row đã có value null). Field active, searchable, không sensitive lưu search_value tối đa 255 ký tự có index và tham gia tìm prefix qua q/query. Không cho sensitive+searchable; đổi cờ cấu hình cập nhật search values hiện có. Đây là index đọc trong phạm vi book, không phải full-text hoặc filter field tùy ý.

`field_choices` nhận các khóa name/legal_name/tax_code/birthday/notes/contacts/addresses/tags với giá trị source/target/combine; combine chỉ dùng cho ba field danh sách. Field không chọn giữ target. Sensitive write vẫn cần quyền riêng. Merge tạo consent mới unknown, hoặc withdrawn nếu nguồn/đích có denied/withdrawn trong dimension tương ứng; không mở rộng consent. Merge không viết lại snapshot bill, không gộp credentials, không tự merge Accounting parties. Liên kết tài khoản tại profile nguồn chặn merge để xử lý riêng. Không có API unmerge hoàn chỉnh trong bản này.

## 5. Grant và binding consumer

| Method/path | Quyền | Contract |
| --- | --- | --- |
| GET `/books/{book}/settings-options` | `customers.settings` | Options cấu hình theo website/quyền. |
| GET `/books/{book}/grants` | `customers.settings` + manager | Admin/role của book. |
| POST `/books/{book}/grants` | Như trên | `admin_id`, `role=reader\|editor\|manager`, `revoke?`; không gán role Core, không tự thu hồi manager của mình. |
| GET `/books/{book}/bindings` | Như trên | Binding hiện tại và mode. |
| POST `/books/{book}/bindings` | Như trên + native configure | `module_key`, `scope_type`, `scope_key`, `abilities`, `expected_version` nếu có. |
| POST `/bindings/{binding}/migration` | `customers.settings` + manager + native configure | `{commit:false}` preview; `{commit:true,cutover:false}` import; `{commit:true,cutover:true}` chuyển nguồn sau commit. |

Abilities hiện tại: **lookup/create/view/update/attach/history**. Native configure là quyền riêng của provider, không phải ability cấp cho thu ngân trong binding.

Contract cấu hình hiện chỉ hỗ trợ hai cặp `fnb-pos/website` và `retail-pos/chain`: `consumerScope` xử lý riêng, validator `scope_type` chỉ nhận website/chain, settings-options chỉ liệt kê hai app. Thêm provider vào config chưa đủ mở consumer mới; cần mở rộng đường resolve scope/native configure, validation/options và kiểm thử tương ứng. Không tự dựng scope mới từ interface.

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

`row_count` là số dòng nguồn đã duyệt trong lượt hiện tại; khi gặp lô tiếp theo service dừng, nên không phải tổng tuyệt đối mọi nguồn. `accepted_count` là hồ sơ mới có thể nhập ở lượt đó, `existing_count` là mapping đã gặp, `created_count` là số mới đã tạo. Với dữ liệu vượt lô, đọc `has_more` thay vì suy hoàn tất từ count.

Migration replay dùng reference journal, không phát lại toàn bộ `warnings/errors/has_more/existing_count`. Sau mất response, tra command rồi chạy preview mới để biết còn lô hay đủ điều kiện cutover; không coi has_more bị thiếu là false. Key của lượt mới phải khác key lượt đã commit; retry của cùng lượt giữ nguyên key/body.

**Shared mode không fallback.** Khi Customers bị tắt, thiếu capability, book archived hoặc quyền bị thu hồi, consumer trả lỗi/trạng thái không ready. Giữ lịch sử giao dịch và source mapping; không xóa local profiles để vượt qua conflict. Không dùng sửa `cust_bindings.mode` trực tiếp để rollback.

Nguồn: [integration service](../../modules/Customers/Services/CustomerIntegrationService.php), [gateway](../../modules/Customers/Services/ConsumerCustomerService.php), [Retail provider](../../modules/RetailPos/Services/RetailCustomersProvider.php), [FNB provider](../../modules/FnbPos/Services/FnbCustomersProvider.php).

## 6. Consumer gateway và Retail POS

`Modules\Customers\Services\ConsumerCustomerService` là contract service nội bộ: `status(actor,module,scope,lock=false)`, `lookup`, `listing`, `create`, `update`, `profile`, `snapshot`, `assertAccess`, `resolveLegacy`, `legacyIds`, `profileIds`. Gateway kiểm binding abilities, readiness và native provider; không dùng một global service account để đọc mọi book. Đây là quyền **ủy quyền consumer**, không yêu cầu người dùng POS có thêm role Customers trung tâm; client chỉ gửi native scope, gateway chọn book từ binding đã cấu hình.

- `lookup` luôn trả projection tối thiểu có contact che, bỏ tags; limit được clamp 1–50. `listing/profile` dùng delegated view và có thể trả contact đầy đủ theo quyền native+binding. Không lấy cờ `customers.sensitive` của Admin UI làm thay thế cho policy native.
- `snapshot` cần ability attach, chỉ chấp nhận active profile và trả contact đầy đủ để transaction owner lưu snapshot. Đây là dữ liệu nội bộ; consumer phải che projection ở response/log theo quyền hiện hành, không broadcast snapshot vì caller có attach.
- Quick create/update chỉ allowlist type/name/code/phone/email/country_code (+ expected_version khi update; status active/archived có nhánh riêng). Không chuyển arbitrary contacts/notes/custom_fields hoặc consent vào gateway để vượt quyền. Vì quick create không nhận custom_fields, book có required custom field chưa có dữ liệu có thể làm POS create trả 422; cần thiết kế luồng bổ sung dữ liệu qua Admin hoặc thay contract có kiểm chứng, không bỏ validation cho POS.

| Retail API | Hành vi |
| --- | --- |
| GET `/admin/api/retail/customer-context?branch_id=UUID` | mode/available/ready/book_id/binding_id. Legacy có thể ready dù Customers chưa cài vì dữ liệu local còn sử dụng được. |
| GET `/admin/api/retail/customers?branch_id=UUID&query=...` | Legacy local chain (tối đa 100) hoặc lookup shared book (clamp tối đa 50, contact che); native `retail.customer.view`. |
| POST `/admin/api/retail/customers`, PUT `.../{UUID}` | Native `retail.customer.manage`, Idempotency-Key; shared gateway khi cutover, local khi legacy. |
| POST `/admin/api/retail/sales` | Có thể thêm `customer_id` cho local hoặc `shared_customer_id` cho shared. Khách đã gắn cần readiness; bỏ trống vẫn bán khách lẻ khi Customers tắt, không fallback local. |
| GET `/admin/api/retail/customers/{UUID}/history?branch_id=UUID` | Native customer.view + sale.view, history ability; chỉ branch được phép. Bao gồm legacy mapping và merged aliases; tối đa 100 sale completed gần nhất. |

Retail sale lưu buyer_snapshot tại quote, giữ customer_id cũ, thêm shared_customer_id. Không cập nhật snapshot lịch sử khi đổi master/merge; thiếu customer.view thì phone/email bị loại khỏi response sale. Shared source bị tắt giữa quote và complete chặn trước Inventory/tender side effects. Chi tiết POS/ca/tồn nằm trong [Retail contract](retail-pos-runtime-contract.md).

FNB `0.2.0-dev.3` có [directory adapter](../../modules/FnbPos/Services/FnbCustomerDirectory.php) và [provider](../../modules/FnbPos/Services/FnbCustomersProvider.php), thêm nullable shared_customer_id trên session/check. GET `/admin/api/fnb/outlets/{outlet}/customers-integration` báo mode/readiness; customer native routes nhận integer ID legacy hoặc UUID shared. Cấu hình binding cần native HQ `fnb.chain.manage` và outlet access. Notes/ngày sinh/consent nghiệp vụ riêng của FNB không tự chuyển thành consent toàn book; snapshot/lịch sử cũ giữ riêng và history theo alias UUID.

## 7. Accounting explicit mapping

`POST /books/{book}/profiles/{profile}/accounting-sync` với `{branch_id,organization_id}` và Idempotency-Key. Cần quyền đọc Customers/book, native Retail customer.view/membership, mapping pháp nhân Retail→organization, **accounting.party.manage + accounting.integration.sync** đúng organization/global và Accounting active.

Source-link dùng journal `retail_customer_accounting_syncs` của Retail, key 8–120 ký tự; không được khôi phục qua `GET /admin/api/customers/commands/{key}`. Retry cùng request/key gọi lại chính accounting-sync endpoint để được reauthorize và lấy kết quả. Kết quả `{customer_id,organization_id,party_id,created,mode:"explicit_source_link"}`. Adapter tạo/reuse AcctPartySource theo organization + module customers + type profile + profile UUID, giữ dữ liệu Accounting đã chỉnh sửa. Không tự tạo hóa đơn/ghi sổ/thanh toán, không tự đồng bộ ngược hay hợp nhất party sau merge Customers. Nguồn: [adapter](../../modules/RetailPos/Services/RetailCustomerAccountingAdapter.php).

## 8. Import/export và tài khoản website

| Method/path | Contract |
| --- | --- |
| POST `/books/{book}/imports` | `customers.import` + sensitive/editor; `{rows:[...],commit:false\|true}` tối đa 1.000. Preview read-only trả valid/counts/errors/duplicates; commit cần key, atomic tạo mới và kiểm lại dữ liệu, không upsert theo phone. |
| POST `/books/{book}/exports` | `customers.export` + sensitive/editor; key để retry; queue job và trả public export UUID/status, created_at, expires_at, error. |
| GET `/exports/{UUID}` | Requester + quyền hiện hành + auth version + expiry; metadata luôn có public_id/status/created_at/expires_at/error trong source hiện tại, không trả đường dẫn storage. |
| GET `/exports/{UUID}/download` | Như trên, checksum; file chưa ready trả 409, hết hạn 410. CSV sáu cột code/type/name/phone/email/status, UTF-8 BOM và chống formula injection. Chỉ API này được kiểm quyền; xem lưu ý storage bên dưới. |
| POST `/books/{book}/profiles/{UUID}/account-link-invitations` | `customers.account.link` + manager; `{account_id}` integer Core Customer; token 64 ký tự, hết hạn 30 phút, hiển thị một lần. |

Import nhận JSON rows sau khi client đọc CSV; endpoint không nhận multipart/file CSV trực tiếp. UI giới hạn file 5 MB và parser 1.000 dòng; backend hiện hardcode tối đa 1.000 rows trong validator dù config có `customers.import_limit`. Preview kiểm cấu trúc/code và cảnh báo contact trùng ở phone/email cấp dòng; nó không khóa nguồn/definition và không thay thế commit validation. Commit vẫn có thể 422 vì required/custom field hoặc dữ liệu đã đổi; transaction rollback cả lô. Preview không tạo hồ sơ, không tạo consent, không bảo đảm mọi dạng contacts[] trùng đều được báo trước.

Export là toàn book được authorize, **bao gồm active/archived/merged**, không nhận bộ lọc trang Danh bạ. Job đọc theo chunk, không phải snapshot nhất quán tại một thời điểm cho toàn master. Trạng thái thực trong source: pending → processing → ready, hoặc failed; prune chuyển expired. UI có thể chấp nhận nhãn cũ khác để tương thích nhưng không giả định API phát completed/queued. Retention mặc định 24 giờ tại config; danh sách “lần xuất trong phiên này” trên UI chỉ giữ trong state trang, chưa có API list export history.

**Lưu ý storage bắt buộc trước production:** worker/download/prune dùng `Storage::disk('local')`, trong khi [config/filesystems.php](../../config/filesystems.php) hiện đặt local.root ở `public_path('files')`. API download có requester/permission/checksum/expiry, nhưng file vật lý có thể nằm trong document root; chưa được coi là private chỉ vì UUID/path khó đoán. Cần cấu hình/sửa luồng sang vùng private và xác minh web server không phục vụ file trực tiếp trước khi xuất dữ liệu thật ở deployment mới. Chưa thay đổi code/config ở lượt cập nhật tài liệu này; test Storage::fake/tmp không chứng minh physical storage production an toàn.

Invitation dành cho person active và account đích đã chọn. Retry cùng key không phát lại token; mất token cần phát invitation mới theo key mới, service làm hết hạn invitation cũ. Không tự gửi email/SMS.

Portal không dùng Customers command journal hoặc Idempotency-Key. PUT/consent dùng expected_version; accept là token single-use, retry sau lần thành công có thể trả 422 vì token đã consumed. Một account chỉ link một profile trong mỗi book, nhưng có thể link nhiều book được chia sẻ. Self projection không trả notes/tags/custom_fields/source mappings nội bộ.

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

Scheduler dispatch mỗi phút, prune hàng giờ; chạy queue worker/scheduler theo cấu hình môi trường. Event `delivered` chỉ xác nhận dispatch nội bộ không lỗi, không bảo đảm consumer bên ngoài đã xử lý. Export có lease năm phút và artifact riêng từng lượt trên disk local; worker kiểm quyền lại trước publish, download kiểm quyền/checksum/expiry; prune xử lý tối đa 500 export hết hạn mỗi lần. Bảo vệ API không thay thế cấu hình storage private nêu ở mục 8. Nguồn: [outbox](../../modules/Customers/Services/CustomerOutboxService.php), [worker export](../../modules/Customers/Jobs/BuildCustomerExport.php), [scheduler](../../routes/console.php).

## 10. Cài đặt, kiểm thử và giới hạn

Sao lưu DB/storage/config và rehearsal trên clone trước cài/nâng. Dùng App Store/lifecycle `POST /admin/api/modules/customers/install|enable|upgrade`, quyền store.module global tương ứng; không ghi trực tiếp module_installations. Customers không có hard dependency FNB/Retail/Accounting. Nâng consumer qua lifecycle để nhận schema mới trước binding/cutover. Retail hiện `0.1.0-dev.2` với migration shared customer snapshot; dữ liệu nghiệp vụ đang chạy không được `migrate:fresh` hoặc seed lại.

```sh
# Tập Customers và consumer trực tiếp; bộ release 22 file còn gồm hồi quy Core/Retail/FNB.
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=':memory:' DB_URL='' \
  CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync \
  vendor/bin/phpunit --do-not-cache-result \
  tests/Feature/CustomersModuleTest.php \
  tests/Feature/CustomersAccessAndPortalTest.php \
  tests/Feature/CustomersAdvancedTest.php \
  tests/Feature/CustomersMigrationTest.php \
  tests/Feature/FnbCustomersIntegrationTest.php \
  tests/Feature/RetailSharedCustomersTest.php \
  tests/Feature/CustomerPortalTest.php \
  tests/Feature/ModuleNavigationPermissionsTest.php
# MySQL gate riêng: chỉ chạy sau khi đã đọc script/config và xác nhận local scratch quyền CREATE/DROP.
php tests/Support/customers-mysql-gates.php --allow-isolated-mysql
# Hai file Customers; để chạy mọi unit JavaScript dùng tests/UnitJavaScript/*.test.js.
node --test tests/UnitJavaScript/customers-component-bindings.test.js tests/UnitJavaScript/customers-domain-unit.test.js
npm run build
```

Các lệnh PHPUnit ở trên phải đi vào SQLite in-memory; [phpunit.xml](../../phpunit.xml) đặt các env test nhưng không force đè shell env đã tồn tại, vì vậy ghi rõ DB_CONNECTION/DB_DATABASE/DB_URL. Kiểm tra config cache/test bootstrap nếu môi trường có override. Không tái sử dụng cấu hình DB nghiệp vụ để chạy RefreshDatabase.

Các checkpoint **đã ghi nhận ngày 20/10/2026**, không chạy lại DB/tests trong lượt cập nhật tài liệu:

- Release tập trung: **22 file / 206 tests / 2.623 assertions pass**, không phải toàn AIO suite. MySQL standalone: **6 gates / 42 assertions pass**, scratch database và export đã dọn/xác minh. JavaScript lúc release: **54/54 pass**, build pass.
- Sau thay menu chính: [ModuleNavigationPermissionsTest](../../tests/Feature/ModuleNavigationPermissionsTest.php) **3 tests / 35 assertions pass**, gồm điều kiện permission AND permissions_any, import-only/export-only và module disabled; không cộng số này thành một lần chạy full suite.
- Sau UI redesign: hai file JavaScript Customers **15 tests pass**, build pass; đã kiểm trực tiếp bốn trang, tìm kiếm/đặt lại, nhóm thiết lập, form bắt buộc/thay tab/Hủy. Đây là thay UI, không có bằng chứng chạy lại toàn bộ PHP suite sau redesign.
- UI SQLite riêng của đợt release đã thử create/contact/search/saved filter/details/activity/export ready/merge preview, không console error. Các test dùng storage fake/tmp; không chứng minh cấu hình physical private của deployment hiện hành.

Script MySQL chỉ tạo database local tên ngẫu nhiên và kiểm owner marker trước thao tác, không migrate vào DB nghiệp vụ; vẫn cần đọc script/config và quyền CREATE/DROP trước chạy. Không suy capacity, bảo vệ file tĩnh hoặc production pilot từ các checkpoint này.

**Snapshot local từng được ghi nhận ngày 20/10/2026; chưa truy vấn xác minh lại ở lượt docs:** DB `aio` đã bật Customers `0.1.0`, nâng FNB `0.2.0-dev.3`/Retail `0.1.0-dev.2`; ba profile FNB đã migrate sau dry-run valid. Legacy profile/check snapshot hashes và số Core login accounts giữ nguyên. Retail lúc đó 0 chain nên chưa cần cutover. Deployment khác phải thực hiện lại backup, lifecycle và preview/cutover theo dữ liệu riêng.

## 11. Xử lý lỗi và retry cho client/AI

| Trạng thái | Trường hợp điển hình | Cách xử lý |
| --- | --- | --- |
| 401 / 419 | Session không còn hợp lệ / CSRF thiếu hoặc hết hạn | Đăng nhập/lấy lại CSRF theo Core, giữ nguyên ý định command; không bỏ auth guard để sửa UI. |
| 403 | Core permission/book grant/native membership/auth version không còn hợp lệ | Dừng thao tác, tải lại quyền/context; không dùng Admin #1 hoặc global service account làm fallback. |
| 404 | Resource không thuộc website/book/requester, book không active, module disabled ở middleware | Xem lại website context/module/readiness/định danh; không suy UUID tồn tại ở phạm vi khác. |
| 409 | expected_version cũ, key dùng cho body khác, merge alias, file chưa ready/checksum đổi | Đọc lại resource và phân biệt conflict với replay; không tự ghi đè bằng version mới. Export chưa ready thì poll status. |
| 410 | Export quá expires_at | Yêu cầu export mới nếu vẫn có quyền; không phục hồi file qua public URL. |
| 422 | Validation/required custom field, mã trùng, điều kiện merge/migration/link không đạt | Hiển thị lỗi, kiểm lại dữ liệu; preview không thay thế validation ở commit. Token consumed/hết hạn cũng có thể 422. |
| 429 | Vượt throttle | Back off; không tự nhân key/retry vô hạn. |
| 503 | Shared binding/readiness/capability/runtime drain chưa sẵn sàng | Giữ shared mode, dừng thao tác cần hồ sơ; không fallback sang dữ liệu local. |
| Network timeout / 5xx | Chưa rõ command đã commit hay chưa | Giữ nguyên body/key, tra GET commands rồi retry đúng endpoint khi phù hợp; với Accounting dùng endpoint/journal Retail riêng, portal theo version/single-use token. |

Command lookup mẫu: `GET /admin/api/customers/commands/{key}` trả `{data:{status:"unknown",resource:null}}` hoặc `{data:{status:"committed",resource:{...references}}}`. `unknown` không phải giấy phép đổi payload của một request đang chạy ở nơi khác; xác minh kết quả và retry cùng ý định. Mất quyền có thể trả 403 thay cho committed. Result references không có contact/token hoặc toàn bộ báo cáo migration.

Không có API CRUD book đầy đủ (update/archive/delete book), GET list export history, xóa relationship/contact từng item, revoke account link hay unmerge công khai trong bản này. Đừng tự dựng endpoint theo tên bảng; các luồng thay danh sách và endpoint thực nằm trong route/service đã liệt kê.

Uninstall Customers bị từ chối để giữ history/source mappings. Disable giữ dữ liệu nhưng consumer shared phải ngừng chức năng cần Customers; không chuyển về legacy tự động. Archive chưa phải erasure/anonymization; CSV không phải gói data-subject export đầy đủ; không có unmerge, campaign/loyalty engine, invoice tax issuance, public webhook delivery hay capacity/production pilot đã nghiệm thu. Xem [giới hạn kiến trúc](../architecture/customers-module.md#8-giới-hạn-phải-biết).
