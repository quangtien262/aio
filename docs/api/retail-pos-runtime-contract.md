# Retail POS — hợp đồng runtime và cài đặt

Đối chiếu source ngày **20/10/2026**: Retail `0.1.0-dev.1`, Merchandise `0.1.0`, Inventory `0.3.0`, Purchasing `0.1.0`. Tài liệu mô tả hành vi hiện có để phát triển client và UAT; không thay [kiến trúc mục tiêu](../architecture/retail-pos-architecture-and-integration.md) hay công bố production hoàn chỉnh. Xem [trạng thái G0–G5 và bằng chứng kiểm thử](../architecture/retail-pos-implementation-status.md) để biết phần chưa đạt gate.

## 1. Transport, quyền và số chính xác

Các route dưới đây nằm trong **`/admin/api`**, dùng session Admin, middleware `auth:admin`, `admin.active`, `admin.website` và cơ chế CSRF hiện hành của AIO. Client phải giữ context website đã xác thực. Module tương ứng phải installed/enabled và capability đúng phiên bản; chỉ có bảng hoặc manifest mới chưa đủ readiness. Nguồn: [admin routes](../../routes/admin.php), [Retail routes](../../routes/retail.php), [capability checker](../../app/Core/Modules/ModuleCapabilityChecker.php).

| Quy ước | Hành vi hiện tại |
| --- | --- |
| Response | Thường `{ "data": ... }`; mutation Retail có thêm `meta.idempotency_key`. Không có một envelope lỗi tùy biến chung cho mọi module. |
| Lỗi | 401/403 cho phiên/quyền; 404 cho resource hoặc context không hợp lệ; 409 cho version, trạng thái hoặc key xung đột; 422 cho validation/quy tắc; 503 khi dependency chưa ready. Xử lý theo HTTP và body Laravel thực trả, không hard-code mọi lỗi thành một business code. |
| Retail ID | Các tham số `branch_id`, `chain_id`, `legal_entity_id`, `terminal_id`, `shift_id`, `sku_id`, sale/return/customer trong API Retail dùng **public UUID**. Response có thể kèm `database_id`; không thay UUID bằng số này trong endpoint Retail. |
| ID nội bộ | Purchasing dùng integer cho `catalog_id`, `warehouse_id`, `supplier_id`, `lines[].sku_id`, `order_line_id`, `receipt_line_id`; URL PO dùng UUID. Packaging, transfer line, stocktake balance và Accounting organization/item cũng dùng integer. Lấy đúng field từ API; không tự suy ID. |
| Tiền Retail | VND, `*_minor` là số nguyên đồng; gửi **chuỗi số** như `"12500"`, tối đa 14 chữ số tại `RetailNumbers`. Các field tiền nghiệp vụ chính được trả dạng chuỗi; metadata/ID/version/count không mang quy ước này. |
| Quantity/cost | Gửi decimal string, tối đa 6 chữ số thập phân; quantity còn phải đúng `quantity_scale` của SKU. Không gửi float hoặc số mũ. Inventory quantity/unit cost dùng `decimal(24,6)`. |
| Làm tròn | Retail quantity × đơn giá làm tròn HalfUp tới đồng; discount được phân bổ có giới hạn theo dòng; return dùng phân bổ cộng dồn theo snapshot và chốt phần cuối. Client không tự tính lại tổng rồi coi là số có thẩm quyền. |
| Cost | Response Retail ẩn đệ quy cost/unit_cost/total_cost/stock_allocations nếu thiếu `retail.report.cost.view`. Quyền bán không mặc nhiên cho quyền giá vốn. |

Core permission quyết định được làm gì; membership Retail quyết định được làm ở chain/branch nào. Không có core scope `branch` mới. Có role Retail không tự có membership; preset membership không cấp thêm role/quyền. Setup và quản lý membership cần global permission; thay membership tăng `auth_version`. Kho/pháp nhân được suy từ branch/kho đã cấu hình, không tin `owner_id` từ client. Nguồn: [RetailService](../../modules/RetailPos/Services/RetailService.php), [RetailController](../../modules/RetailPos/Http/RetailController.php), [RetailNumbers](../../modules/RetailPos/Domain/RetailNumbers.php).

## 2. Idempotency và khôi phục response

Mọi mutation **Retail** và **Purchasing HTTP** cần header:

```http
Idempotency-Key: retail:550e8400-e29b-41d4-a716-446655440000
```

Key dài 8–120 ký tự, chỉ gồm `A-Z a-z 0-9 _ . : -`. Tạo một key cho một ý định; giữ nguyên key và body khi retry sau timeout. Retail journal gắn actor, website tin cậy, operation và input canonical; dùng lại key với ý định/context khác trả 409. Khi replay/status, Retail vẫn kiểm lại membership/HQ, quyền native Inventory, quyền discount/chuyển khoản thủ công và chủ ca của ý định gốc; không kiểm lại mutable version/status/quote TTL/giá của nghiệp vụ đã commit; thu hồi quyền có thể khiến command đã commit không còn được đọc. Command và side effect cùng transaction; không dùng journal client làm bằng chứng thu tiền.

```http
GET /admin/api/retail/commands/{key}
GET /admin/api/purchasing/commands/{key}
```

Kết quả là `{ "data": { "status": "committed", "resource": { ... } } }` hoặc `{ "data": { "status": "unknown" } }`. Retail giới hạn actor + website và reauthorize intent; Purchasing giới hạn actor và yêu cầu quyền `purchasing.view` **global**. `unknown` không xác nhận thất bại; retry đúng key/body hoặc đối soát trước khi tạo ý định tài chính mới. Purchasing là journal riêng, không có phạm vi website/branch như Retail; service kiểm lại quyền Purchasing/native Inventory global của action trước trả cached result. HTTP mutation Merchandise/Purchasing khóa actor và kiểm active/auth version/quyền hiện hành sau authorize ban đầu.

Browser [retailCommandJournal](../../resources/admin/src/modules/retail-pos/state/retailCommandJournal.js) chỉ giữ identity/hash/key/path, không giữ payload khách/tender. Nó chặn sửa một command tài chính chưa rõ kết quả nhưng **không cung cấp bán offline**. Merchandise master API riêng chưa có command journal này; preview import là read-only, commit chỉ thêm SKU mới và kiểm duplicate.

## 3. Retail: đọc dữ liệu và quyền

Prefix bảng: **`/admin/api/retail`**. Các danh sách theo branch cần `?branch_id={UUID}` trừ bootstrap/branches/settings/commands. Nguồn chuẩn là [routes](../../routes/retail.php), [controller](../../modules/RetailPos/Http/RetailController.php) và [service](../../modules/RetailPos/Services/RetailService.php).

| GET | Quyền Retail | Nội dung |
| --- | --- | --- |
| `/bootstrap`, `/branches` | `retail.view` | Chain/branch/quầy/pháp nhân/catalog trong phạm vi. Bootstrap có permissions/capabilities; `offline=false`, `electronic_payment_provider=false`. |
| `/settings` | `retail.settings.manage` | Version runtime, VND, Inventory authority, offline/provider chưa bật. Chưa có endpoint lưu mọi cấu hình. |
| `/products` | `retail.product.view` | SKU bán tại branch; `query` tìm tên/SKU/barcode. `include_catalog=1` cần quyền quản lý sản phẩm để lấy thêm SKU chưa niêm yết. Có price_version, available, packaging_id/multiplier khi barcode khớp. |
| `/inventory` | `retail.inventory.view` | Availability qua Inventory cho branch. |
| `/sales` | `retail.sale.view` | Sale kèm snapshot lines/tenders; không có endpoint `GET /sales/{id}` riêng trong bản này. |
| `/shifts` | `retail.shift.manage` | Ca và số tiền hệ thống/kiểm đếm/chênh lệch. |
| `/returns` | `retail.return.manage` | Phiếu trả và trạng thái hoàn tiền. |
| `/customers` | `retail.customer.view` | Hồ sơ khách của chain gắn với branch được phép. |
| `/terminals` | `retail.view` | Quầy của branch. |
| `/transfers`, `/stocktakes` | `retail.transfer.manage`, `retail.stocktake.manage` | Tối đa 100 phiếu gần nhất, có lines. |
| `/dashboard`, `/reports` | `retail.report.view` | Tổng hợp branch theo `from`, `to`; doanh thu bán, discount, trả hàng, `net_sales_minor`, refund pending, ca mở, as_of. `cost_status=unavailable`; không suy lãi gộp từ cost 0. |

Các list Retail hiện bị giới hạn số bản ghi, chưa có contract pagination/export lớn thống nhất. `revenue_minor` là tổng sale completed trong kỳ; `net_sales_minor` trừ giá trị return phát sinh trong kỳ. `refunds_minor` hiện cộng giá trị return, kể cả đang pending refund, nên không được diễn giải thành toàn bộ tiền đã chi hoàn thực tế. Báo cáo theo ngày và dòng tiền cần đọc đúng field/source trước khi đối soát Finance.

## 4. Retail: onboarding, hàng hóa và khách hàng

Mọi dòng trong bảng là mutation có Idempotency-Key. `{id}` là UUID public.

| Method/path | Body chính | Quyền và hành vi bổ sung |
| --- | --- | --- |
| POST `/setup` | `name`, `branch_name?`, `legal_name?` | `retail.setup.manage` global. Tạo catalog, chain theo website, pháp nhân, branch, kho v2 và POS-01; cấp membership HQ cho actor. |
| POST `/branches` | `chain_id`, `legal_entity_id`, `code`, `name`, `timezone?` | `retail.branch.manage` + HQ chain; pháp nhân phải được phép. Tạo branch/kho/quầy mới. |
| POST `/terminals` | `branch_id`, `code`, `name` | `retail.branch.manage` + HQ. |
| POST `/memberships` | `branch_id`, `admin_id` integer, `preset`, `is_active` | `retail.membership.manage` global. Preset `hq|manager|cashier|stock|finance`; chỉ map Admin hiện có, không gán role core. |
| POST `/products` | `branch_id`, `sku`, `name`, `base_unit`, `quantity_scale`, `price_minor`, `tracking_mode?`, `requires_expiry?`, `barcode?`, `packagings?`, `barcodes?` | `retail.product.manage` + HQ. Tạo SKU master và assortment branch. Có thể kèm `opening_quantity`, `unit_cost`; nhập đầu kỳ còn cần native `inventory.receipt.manage`. |
| POST `/assortments` hoặc PUT `/products/{sku UUID}` | `branch_id`, `sku_id` nếu POST, `price_minor`, `expected_version` khi đã tồn tại | `retail.product.manage` + `retail.price.manage` + HQ. Liệt kê SKU đã có cho branch hoặc đổi giá, tăng version. Chưa có schedule/ACK publication. |
| POST `/inventory/receipts` | `branch_id`, `sku_id`, `quantity`, `unit_cost`, `batch_code?`, `expires_at?` | `retail.inventory.view` + native `inventory.receipt.manage`; owner/catalog từ branch, không nhận tự do từ client. |
| POST `/customers` hoặc PUT `/customers/{id}` | `branch_id`, `name`, `phone?`, `email?`, `status?`, `expected_version` khi sửa | `retail.customer.manage`; customer dùng chung chain. Chưa có loyalty/consent workflow đầy đủ. |

Giá bán `price_minor` tính theo **đơn vị cơ sở**. Nếu barcode là lốc 6, gửi `quantity="1"` cùng `packaging_id` để server quy đổi thành 6 đơn vị; không tự nhân rồi vẫn gửi packaging_id. Packaging multiplier phải có thể biểu diễn đúng scale SKU. Không dùng barcode numeric để tránh mất số 0 đầu.

## 5. Retail: ca, bán, trả và hoàn

| Method/path | Body chính | Contract hiện tại |
| --- | --- | --- |
| POST `/shifts` | `branch_id`, `terminal_id`, `opening_minor` | `retail.shift.manage`; một ca open/quầy. |
| POST `/sales` | `branch_id`, `terminal_id`, `shift_id`, `customer_id?`, `lines:[{sku_id,quantity,packaging_id?}]` | `retail.sale.create`; ca thuộc quầy/branch và actor. Tạo trạng thái `priced`, snapshot tên/SKU/giá/revision; chưa thu/trừ tồn. |
| POST `/sales/{id}/complete` | `expected_version`, `discount_minor?`, `reason?`, `tenders:[{method,amount_minor,tendered_minor?,reference?}]` | `retail.payment.collect`; quote còn hạn **300 giây**, revision giá/assortment chưa đổi, SKU còn active. Discount cần `retail.price.manage` và lý do. Tổng tender phải đúng total sau discount. |
| POST `/sales/{id}/cancel` | `expected_version` | `retail.sale.create`; hủy sale chưa kết thúc. |
| POST `/sales/{id}/returns` | `expected_version`, `reason`, `lines:[{sale_line_id,quantity,disposition}]` | `retail.return.manage`. Sale completed ở branch gốc; disposition `saleable|quarantine|damaged`. Giới hạn cộng dồn, giữ lô/cost gốc. |
| POST `/returns/{id}/refund` | `shift_id`, `method`, `reference?` | `retail.return.manage` + `retail.payment.collect`; hoàn toàn bộ phiếu pending, ca open cùng branch/actor. |
| POST `/shifts/{id}/close` | `expected_version`, `counted_minor` | `retail.shift.manage`, chủ ca hoặc superadmin; chặn khi sale/hoàn tiền của ca chưa kết thúc. Ghi variance, không sửa số hệ thống. |
| POST `/shifts/{id}/reconcile` | `expected_version` | `retail.shift.manage` + HQ chain; closed → reconciled. |

Tender chỉ hỗ trợ **`cash`** và **`manual_transfer`**; không có trùng method trong một complete. `manual_transfer` cần `retail.settings.manage` và reference, chỉ là ghi nhận thủ công. Cash có `tendered_minor` để tính tiền thừa; `amount_minor` là phần phân bổ trả đơn, không phải toàn bộ tiền khách đưa. Hoàn chuyển khoản cũng cần quyền/reference tương ứng. Sale/tender/cash movement/Inventory commit/outbox cùng DB transaction; lỗi tồn rollback toàn bộ.

Ví dụ luồng sau khi đã tạo SKU và mở ca; thay UUID bằng kết quả API, dùng **key khác nhau** cho create và complete:

```json
{
  "branch_id": "<branch UUID>",
  "terminal_id": "<terminal UUID>",
  "shift_id": "<shift UUID>",
  "lines": [{"sku_id": "<sku UUID>", "quantity": "0.125000"}]
}
```

```json
{
  "expected_version": 1,
  "tenders": [{"method": "cash", "amount_minor": "12500", "tendered_minor": "20000"}]
}
```

Lấy `amount_minor` từ sale response thực tế. Quote hết hạn/giá đổi trả 409 trước side effect; hủy quote cũ rồi tạo lại để xác nhận giá mới. Không đổi version/body của một complete chưa rõ kết quả rồi gửi key mới.

## 6. Retail: điều chuyển và kiểm kê

| Method/path | Body chính | Hành vi |
| --- | --- | --- |
| POST `/transfers` | `source_branch_id`, `destination_branch_id`, `lines:[{sku_id,quantity}]` | Quyền `retail.transfer.manage` tại cả hai branch + native `inventory.transfer.manage`; cùng chain/pháp nhân. **Tạo là xuất giao ngay**, không có bước approval. |
| POST `/transfers/{id}/dispatch` | — | Hiện trả 422: phiếu đã xuất lúc tạo. Client không gọi để xuất lại. |
| POST `/transfers/{id}/receive` | `expected_version`, `lines:[{line_id,quantity,disposition?}]` | Quyền branch đích + native transfer; actor khác người giao, nhận từng phần, không nhận vượt. `line_id` là integer Inventory transfer line từ response. |
| POST `/stocktakes` | `branch_id` | `retail.stocktake.manage` + native `inventory.stocktake.manage`; tạo snapshot và khóa warehouse khi counting, từ chối nếu còn reservation cần drain. |
| POST `/stocktakes/{id}/post` | `counts:[{balance_id,quantity}]` | Cần đủ mọi dòng snapshot; actor checker khác maker. Delta tạo movement/cost lineage. `balance_id` integer lấy từ lines của phiếu. |

Đây là kiểm kê khóa **toàn kho branch**, chưa có blind count/recount/duyệt theo ngưỡng hay kiểm kê liên tục có bù movement. Kho khác vẫn là phạm vi riêng. Xem [InventoryV2Service](../../modules/Inventory/Services/InventoryV2Service.php).

## 7. Merchandise API độc lập

Prefix **`/admin/api/merchandise`**; mọi quyền dưới đây là **global**, không giới hạn bằng Retail membership. Route catalog/SKU dùng UUID. [Routes](../../modules/Merchandise/routes.php), [controller](../../modules/Merchandise/Http/MerchandiseController.php), [service](../../modules/Merchandise/Services/MerchandiseService.php).

| Method/path | Quyền | Body/query |
| --- | --- | --- |
| GET `/catalogs` | `merchandise.view` | Tối đa 500 catalog. |
| POST `/catalogs` | `merchandise.manage` | `code`, `name`. Tạo catalog active. |
| GET `/catalogs/{catalog}/skus` | `merchandise.view` | `query?`; barcode exact trước, tối đa 100 kết quả. |
| POST `/catalogs/{catalog}/skus` | `merchandise.manage` | `sku`, `name`, `base_unit`, `quantity_scale`, `tracking_mode?`, `requires_expiry?`, packaging/barcode. |
| PUT `/catalogs/{catalog}/skus/{sku}` | `merchandise.manage` | `expected_version` + dữ liệu; base_unit/scale/tracking không đổi sau tạo. |
| POST `/catalogs/{catalog}/import` | `merchandise.import` | `rows` tối đa 1.000, `commit=false` để preview; `true` commit atomic, chỉ tạo mới. |

Packaging: `{code,name,quantity_multiplier,barcode?,format?}`; barcode rời: `{barcode,format?,packaging_id?}` với packaging_id integer thuộc SKU. Format được hỗ trợ: internal/ean13/ean8/upca; checksum áp dụng chuẩn tương ứng. Packaging đổi tỷ lệ tạo revision mới, không viết lại conversion cũ. API chưa cung cấp parser barcode cân có prefix/giá/lượng nhúng.

## 8. Purchasing API độc lập

Prefix **`/admin/api/purchasing`**; module tùy chọn đối với POS. **Tất cả quyền Purchasing HTTP hiện bắt buộc global** và có thể đọc danh sách NCC/PO toàn hệ thống. Chỉ cấp cho nhóm mua hàng HQ; không coi đây là API phân quyền branch nhờ Retail membership. Key recovery thuộc actor. [Routes](../../modules/Purchasing/routes.php), [controller](../../modules/Purchasing/Http/PurchasingController.php), [service](../../modules/Purchasing/Services/PurchasingService.php).

| Method/path | Quyền `purchasing.*` | Body/query chính |
| --- | --- | --- |
| GET `/suppliers`, `/orders`, `/orders/{UUID}`, `/commands/{key}` | `view` | Orders có `page`, `per_page` tối đa 100 và meta pagination. |
| POST `/suppliers` | `supplier.manage` | `code`, `name`, `phone?`, `email?`, `address?`, `payment_terms_days?`. Trả 201. |
| POST `/orders` | `order.create` | `supplier_id`, `warehouse_id`, `catalog_id` integer; `lines:[{sku_id,quantity,unit_cost}]`; `expected_at?`, `note?`. Thêm native `inventory.receipt.manage`. Trả 201. |
| POST `/orders/{UUID}/approve` | `order.approve` | `expected_version`; draft → approved; người duyệt khác người tạo. |
| POST `/orders/{UUID}/receive` | `receipt.manage` | `expected_version`, `reference?`, `lines:[{order_line_id,quantity,batch_code?,expires_at?}]`; thêm native Inventory receipt. |
| POST `/orders/{UUID}/returns` | `return.manage` | `expected_version`, `receipt_line_id`, `quantity`, `reason`; thêm native `inventory.issue.manage`. |
| POST `/orders/{UUID}/payments` | `payment.manage` | `expected_version`, `amount_minor` chuỗi nguyên dương, `method=cash|bank`, `reference?`. Ghi nhận thủ công. |
| POST `/orders/{UUID}/close` | `order.approve` | `expected_version`; đóng phần còn lại, không đảo receipt đã nhận. |

PO kiểm catalog/SKU và `legal_entity_ref` cấu hình tại kho, không nhận chủ sở hữu tùy ý. Nhận hàng nhiều đợt, inventory movement và receipt cùng transaction; retry key không tạo lần hai. Giá trị receipt làm tròn tới đồng theo dòng; trả NCC dùng phân bổ cộng dồn. `financials` gồm `received_minor`, `returned_minor`, `paid_minor`, `payable_minor`, `supplier_credit_minor`, **`basis="received_provisional"`**. Đây là công nợ tác nghiệp tạm theo hàng nhận, chưa có hóa đơn NCC/3-way matching/thuế/landed cost hay sổ AP chính thức; phương thức `bank` không gọi ngân hàng.

## 9. Accounting bridge tùy chọn

Các path dưới đây có prefix **`/admin/api/retail`**, cần `retail.integration.manage` và membership branch. Bật mapping/delivery còn cần **`accounting.document.create` và `accounting.integration.sync`** native ở organization đích hoặc global; HQ Retail không thay quyền Accounting.

| Method/path | Request | Kết quả |
| --- | --- | --- |
| GET `/integrations` | `?branch_id={UUID}` | Availability, mapping, organization/items được phép, deliveries/pending/deferred; `mode=internal_draft_only`, `automatic_dispatch=false`. |
| PUT `/integrations/mapping` | `branch_id`, `organization_id` integer, `enabled`, `expected_version` (0 khi tạo), `sku_mappings:[{sku_id:UUID,accounting_item_id:integer}]`; Idempotency-Key | Mapping tường minh; ACK chỉ gồm `branch_id` và `accounting.{enabled,organization_id,mapping_version}`. Reload GET để lấy options có quyền hiện hành; không journal danh sách organization/item. |
| POST `/integrations/reconcile` | `branch_id`, `limit?` 1–100; Idempotency-Key | Xử lý sale pending bằng thao tác chủ động; trả status/count/errors. Không có worker tự dispatch Retail trong bản này. |

Bridge chỉ xử lý **`sale.completed`**, gọi [AccountingDocumentService](../../app/Support/AccountingTax/AccountingDocumentService.php) tạo `internal_invoice`, direction `outbound`, workflow `draft`. Không tự approve/post/issue, không gửi Minvoice, không ghi tồn/payment Accounting. Dòng chiếu `quantity=1`, unit_price=net total Retail; snapshot giữ tên, SKU, quantity 6 số, giá/discount/cost và item mapping gốc, với `projection_basis=retail_line_net_total`, `requires_tax_classification=true`, tax `not_declared`. Không coi đây là hóa đơn thuế đã hợp lệ.

Actor ủy quyền, auth version, quyền native, mapping pháp nhân/organization và module readiness được kiểm lại khi delivery. Link duy nhất theo source + destination + operation; mất ACK/replay giữ một draft, không tạo thêm do đổi contract version. Accounting disabled/mapping thiếu giữ pending và không làm mất sale. `return.posted`/`refund.confirmed` còn deferred; adapter đảo chứng từ chưa có. Nguồn: [bridge](../../modules/RetailPos/Services/RetailAccountingBridge.php), [schema links](../../modules/RetailPos/database/migrations/2026_10_20_200001_create_retail_integration_links.php).

## 10. Cài mới và nâng cấp an toàn

### Chuẩn bị

1. Ghi revision source, package versions và trạng thái module hiện tại. Sao lưu DB nhất quán, file đính kèm/storage cần thiết và cấu hình/khóa ứng dụng theo quy trình bí mật của môi trường; xác nhận có thể restore trên môi trường cô lập. Không in `.env`, mật khẩu hoặc khóa vào log.
2. Dùng staging/DB clone riêng để rehearsal; tắt mọi job/client ghi kho trong cửa sổ nâng cấp. Đối chiếu ca, priced sale, refund pending, reservation, escrow, điều chuyển, kiểm kê và PO còn mở. Không xóa các dòng này để vượt lifecycle guard.
3. Triển khai core migration theo quy trình AIO; cài/nâng module bằng **App Store/ModuleManager lifecycle**, không chạy toàn bộ module migrations bằng glob trong production. Migration paths hiện hành/lịch sử lấy từ manifest qua [ModuleMigrationPaths](../../app/Core/Modules/Support/ModuleMigrationPaths.php).

### Fresh installation

Các endpoint lifecycle có prefix `/admin/api/modules/{key}`: `POST /install`, `/enable`, `/upgrade`, `/disable`, yêu cầu quyền `store.module.*` global tương ứng. Gọi bằng Admin đã xác thực; không chèn trực tiếp trạng thái enabled/version vào `module_installations`.

1. Cài rồi bật **Merchandise `0.1.0`**.
2. Cài rồi bật **Inventory `0.3.0`**; fresh path là `modules/Inventory/database/migrations/v2`, không yêu cầu bảng CMS/Catalog giả.
3. Cài rồi bật **Retail POS `0.1.0-dev.1`**; kiểm các capability Inventory v2 trước onboarding. Dùng `/retail/setup`, sau đó cấp core roles và membership theo trách nhiệm thực.
4. Nếu dùng mua hàng, cài/bật **Purchasing `0.1.0`** sau Merchandise/Inventory; dùng hai actor tạo/duyệt PO. Nếu dùng Accounting, cài/bật module đích qua lifecycle riêng, cấp native rights rồi opt-in mapping.
5. Tạo assortment/giá, nhập opening stock có chứng từ một lần, mở ca test và đối soát đầy đủ trước mở UAT. Kho `MAIN` legacy do hook Inventory tạo không tự có catalog/pháp nhân v2; Retail setup tạo warehouse riêng đã binding. Không dùng MAIN chưa cấu hình làm kho Retail.

### Inventory 0.2.0 → 0.3.0 và dữ liệu cũ

Đây là thay đổi có DDL/precision và ownership; **không phải chỉ sửa version manifest**. Cài/bật Merchandise trước khi upgrade Inventory. [Baseline v2](../../modules/Inventory/database/migrations/v2/2026_07_09_000004_create_inventory_v2_baseline.php) nhận biết bảng đã có; [forward migration](../../modules/Inventory/database/migrations/v2/2026_10_20_000001_add_inventory_v2_operations.php) mở rộng quantity/cost lên 6 số, bỏ FK Catalog bắt buộc, thêm identity/ownership và bảng v2. Path migration cũ được giữ trong `historical_migrations`; không đánh dấu migration chưa chạy là đã chạy.

1. Chạy upgrade trên clone có legacy ledger/FK/cost layers; kiểm ID, quantity, cost và migration lineage trước/sau, kể cả tiếp tục sau partial DDL. MySQL DDL có thể commit từng bước; không giả định transaction rollback khôi phục toàn bộ schema.
2. Chốt mapping từng item legacy ↔ Merchandise SKU, catalog, kho và pháp nhân trước khi đưa kho vào Retail v2. Migration không tự suy/gộp SKU theo tên và không biến mọi kho cũ thành `v2_managed`. Báo cáo xung đột/cutover dữ liệu thực vẫn là việc phải làm riêng.
3. Không seed/opening stock lại cho hàng đã có ledger. Không cho legacy writer và v2 writer cùng quản một kho; [InvStockDocument creating guard](../../app/Models/InvStockDocument.php) từ chối ghi legacy trên `v2_managed`.
4. Nâng Retail theo lifecycle để nhận đủ schema links và [price_version migration](../../modules/RetailPos/database/migrations/2026_10_20_200002_add_retail_quote_price_versions.php). Nếu môi trường đã cài đúng cùng version dev nhưng thiếu migration được thêm trong quá trình phát triển, không ép đổi trạng thái/version DB: cần package version nâng cấp mới và rehearsal trước khi áp dụng.
5. Kiểm capability theo **installed version + trạng thái** sau upgrade; chỉ bật client khi đúng schema/permission. Retail guard chặn disable/upgrade nếu còn ca open/closed chưa reconcile, sale priced hoặc refund pending; Inventory disable guard chặn reservation/in-transit/counting. Kiểm tra phụ thuộc trước khi dừng module nền. Bốn module đăng ký [ModuleRuntimeBarrier](../../app/Core/Modules/Support/ModuleRuntimeBarrier.php) qua provider [Retail](../../modules/RetailPos/Services/RetailRuntimeState.php), [Inventory](../../modules/Inventory/Services/InventoryRuntimeState.php), [Merchandise](../../modules/Merchandise/Services/MerchandiseRuntimeState.php), [Purchasing](../../modules/Purchasing/Services/PurchasingRuntimeState.php): mutation giữ shared lock trong transaction; lifecycle drain chờ lệnh đang chạy và chặn lệnh mới, recovery/compensation theo operation owner. Phạm vi Inventory barrier là **v2**, chưa bao phủ mọi traffic native legacy; vẫn phải dừng client/job legacy trong runbook.

Merchandise/Purchasing/Retail giữ lịch sử và không hỗ trợ uninstall. Inventory cũng từ chối uninstall khi có movement. Không dùng `migrate:fresh`, demo-data, rollback hủy schema hoặc xóa journal trên DB vận hành. Forward migration ưu tiên khi đã có giao dịch; application backout phải tương thích schema mới, nếu không phải dùng bản backup đã kiểm restore theo kế hoạch cutover. Đây chưa phải bằng chứng một restore drill production đã hoàn tất.

## 11. Chạy kiểm chứng trên môi trường cô lập

Chạy PHPUnit trực tiếp để tránh `php artisan test` bootstrap `.env` thật trước khi PHPUnit áp cấu hình testing. Xác nhận `phpunit.xml`/environment dùng DB test riêng, không dùng DB đang vận hành.

```sh
APP_ENV=testing vendor/bin/phpunit --do-not-cache-result tests/Feature/MerchandiseModuleTest.php tests/Feature/PurchasingModuleTest.php tests/Feature/InventoryV2Test.php tests/Feature/RetailPosTest.php tests/Feature/RetailQuoteTest.php tests/Feature/RetailAuthorizationTest.php tests/Feature/RetailAccountingBridgeTest.php tests/Feature/RetailReplayAuthorizationTest.php tests/Feature/RetailInventoryBarrierTest.php tests/Feature/MerchandisePurchasingBarrierTest.php
node --test tests/UnitJavaScript/*.test.js
npm run build
```

[MySQL gate runner](../../tests/Support/retail-mysql-gates.php) cần MySQL test connection có quyền tạo/xóa scratch database. Ngoài bảy gate correctness/migration, runner có hai gate interleaving nhiều process để kiểm lifecycle chờ mutation đang chạy ở Retail và Inventory v2. Script tạo DB ngẫu nhiên dạng `aio_retail_gate_<16 hex>`, dùng dữ liệu synthetic và dọn đúng DB do nó tạo; chạy trên máy test có cấu hình phù hợp, không trỏ công cụ thử nghiệm vào DB business:

```sh
php tests/Support/retail-mysql-gates.php
```

Snapshot ghi nhận: **78 focused PHP tests / 843 assertions**, **21 regression tests / 132 assertions**, **9 MySQL gates**, **39 JavaScript tests** và build pass. Browser smoke trên dữ liệu cô lập đã đi qua onboard → nhập tồn → mở ca → bán tiền mặt/tiền thừa → đóng/đối soát ca, và niêm yết cùng SKU tại branch thứ hai với giá/tồn riêng. Toàn PHP suite **chưa xanh**: có lỗi baseline được quan sát, nhưng run toàn suite cũ còn bị source/schema thay đổi đồng thời; phải chạy lại ở revision cố định và phân loại từng lỗi, không kết luận tất cả lỗi đều baseline.

Chưa có chứng cứ capacity/load/soak, kiểm thiết bị đầy đủ, provider/tax sandbox, restore drill hoặc pilot production 2–3 chi nhánh. Các API online và escrow primitive hiện tại không đáp ứng gate offline G4; cờ offline/provider tiếp tục false cho đến khi luồng tương ứng được xây và nghiệm thu.
