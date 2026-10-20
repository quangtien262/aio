# Retail POS — trạng thái triển khai

Ngày đối chiếu: **20/10/2026**. Đây là ảnh chụp trạng thái mã nguồn và bằng chứng kiểm thử trong đợt phát triển hiện tại, không phải biên bản nghiệm thu production. Manifest Retail đang ở **`0.1.0-dev.1`**; Merchandise và Purchasing ở `0.1.0`, Inventory ở `0.3.0`.

Tài liệu này bổ sung cho [đặc tả sản phẩm](retail-pos-product-spec.md), [kiến trúc mục tiêu](retail-pos-architecture-and-integration.md) và [kế hoạch G0–G5](retail-pos-implementation-plan.md). Những checklist trong kế hoạch gốc vẫn là tiêu chí nghiệm thu; không suy ra đã hoàn tất từ số lượng màn hình hay số test. Hợp đồng đang chạy và hướng dẫn cài đặt nằm trong [runtime contract](../api/retail-pos-runtime-contract.md).

## 1. Cách đọc trạng thái

| Nhãn | Ý nghĩa |
| --- | --- |
| Implemented | Có đường thực thi trong source và kiểm thử tương ứng trong phạm vi mô tả. Không đồng nghĩa toàn bộ giai đoạn đã nghiệm thu. |
| Partial | Có phần chạy được, nhưng còn thiếu hành vi hoặc bằng chứng bắt buộc của kế hoạch. |
| Pending | Chưa có luồng hoàn chỉnh, hoặc chưa có bằng chứng vận hành để công bố. |

Hiện có lát cắt **online** từ hàng hóa → nhập tồn → mở ca → tính giá → thu tiền → trừ tồn → trả hàng → hoàn tiền → đối soát ca; có thêm điều chuyển, kiểm kê, mua hàng và chiếu sale sang chứng từ kế toán nháp. Chưa đạt điều kiện công bố G4 production pilot hay vận hành chuỗi liên tục khi mất mạng.

## 2. Đối chiếu G0–G5

| Giai đoạn | Trạng thái | Phần đã hiện thực | Phần còn thiếu trước khi đóng gate |
| --- | --- | --- | --- |
| **G0 — thiết kế và fixture** | Partial | Ba tài liệu thiết kế; ranh giới Merchandise/Inventory/Purchasing/Retail; chain/branch/pháp nhân/kho; decimal và quyền; fixture/test về nhiều phạm vi, replay và ownership. | Chốt cùng Operations/Finance quy mô, thiết bị, chính sách offline/provider/thuế; dữ liệu import thực; ma trận nghiệm thu và người ký. Chưa có bộ fixture vận hành đầy đủ thay thế mọi AT trong kế hoạch. |
| **G1 — nền tảng dùng chung** | Partial | Merchandise thật; Inventory v2 giữ/commit hàng, decimal 6, FIFO, transfer/stocktake; fresh install không cần CMS/Catalog; forward upgrade legacy; capability theo version; chặn writer cũ tại kho `v2_managed`; gate MySQL migration/concurrency. | Mapping và báo cáo xung đột Catalog legacy đầy đủ; barcode cân có cấu trúc; toàn bộ approval/reversal và drain/recovery mục tiêu; rehearsal trên bản sao dữ liệu triển khai thực; baseline regression AIO xanh hoặc từng lỗi được phân loại có bằng chứng. |
| **G2 — online nội bộ** | Partial | Onboarding, quầy/ca, assortment và revision giá; quote hết hạn sau 300 giây; POS online; tiền mặt/chuyển khoản ghi nhận thủ công; sale–tender–stock–outbox nguyên tử; command recovery; trả/hoàn cộng dồn; ca có chênh lệch và đối soát. | Payment intent/provider, callback và kết quả uncertain thực; hold/resume giỏ đầy đủ; kiểm thiết bị/in lại receipt, E2E nhiều quầy/browser crash/worker crash; các AT G2 còn lại. |
| **G3 — nhiều chi nhánh và mua hàng UAT** | Partial | Membership chain/branch, dữ liệu theo website; phân quyền chi phí; nhận điều chuyển từng phần, maker/checker; kiểm kê khóa kho; PO duyệt khác người lập, nhận/trả/chi vận hành; Accounting internal draft tùy chọn. | Scope vùng/delegation; publication giá có lịch và ACK; approval điều chuyển; blind/recount workflow; recall/waste đầy đủ; replenishment phối hợp PO/in-transit; invoice/3-way matching; adapter Accounting return/refund và supplier; thuế/hóa đơn điện tử; UAT ba chi nhánh/hai pháp nhân cùng đối soát báo cáo. |
| **G4 — offline và production pilot** | Pending | Có primitive Inventory escrow/epoch/sequence và browser journal nhận diện command online. | Gateway có DB/journal bền vững, ký grant, authority/fencing và recovery toàn trình; thử WAN/LAN/power/disk/clock; thiết bị thực; restore drill; provider sandbox; canary 2–3 chi nhánh và thời gian pilot theo kế hoạch. **`offline.enabled=false`.** |
| **G5 — mở rộng chuỗi** | Pending | Chưa có bằng chứng đạt gate mở rộng. | Capacity/load/soak đúng profile, rollout theo nhóm, loyalty/khuyến mại nâng cao, thanh toán tự động, kênh CMS/Catalog, HRM/Payroll, AVG/landed cost, return khác chi nhánh, kiểm kê liên tục và export lớn. |

## 3. Tái sử dụng module AIO

| Thành phần | Trạng thái và cách tái sử dụng | Nguồn chính |
| --- | --- | --- |
| Core Admin, RBAC, website, audit, module lifecycle | Implemented: dùng identity/RBAC hiện có, không tạo tài khoản thu ngân riêng. Membership Retail là phạm vi nghiệp vụ bổ sung, không cấp quyền core. Mutation Retail kiểm lại actor/auth version, module và quyền của ý định gốc khi replay/status, gồm HQ, native Inventory và grant discount/chuyển khoản thủ công. Bốn module dùng durable runtime barrier để phối hợp mutation với lifecycle. | [RetailService](../../modules/RetailPos/Services/RetailService.php), [ModuleManager](../../app/Core/Modules/ModuleManager.php), [route admin](../../routes/admin.php) |
| Merchandise `0.1.0` | Implemented: master catalog/SKU, đơn vị cơ sở, scale, packaging có revision, barcode và checksum chuẩn hỗ trợ; import preview/commit tối đa 1.000 dòng. Giá bán theo branch thuộc Retail, tồn thuộc Inventory. Partial: chưa có parser tem cân và công cụ hòa giải master legacy đầy đủ. | [manifest](../../modules/Merchandise/module.json), [service](../../modules/Merchandise/Services/MerchandiseService.php), [migration](../../modules/Merchandise/database/migrations/2026_10_20_010001_create_merchandise_tables.php) |
| Inventory `0.3.0` | Implemented: nguồn ghi tồn duy nhất của Retail; warehouse gắn catalog/pháp nhân; reservation, availability, FIFO, lô/hạn, disposition, transfer, stocktake và journal idempotency. AVG/serial không được mở trong luồng v2 này. Escrow hiện chỉ là primitive, chưa phải offline POS. | [manifest](../../modules/Inventory/module.json), [InventoryV2Service](../../modules/Inventory/Services/InventoryV2Service.php), [chặn writer legacy](../../app/Models/InvStockDocument.php) |
| Purchasing `0.1.0` | Implemented ở mức vận hành: NCC, PO, maker/checker, nhận từng phần cùng transaction Inventory, trả NCC, ghi chi tiền, payable/credit từ hàng thực nhận. HTTP mutation khóa actor, kiểm lại auth version/quyền native global trước tác động và trước replay. **Partial:** `basis=received_provisional`, không thay sổ kế toán/phải trả chính thức hay hóa đơn NCC. | [manifest](../../modules/Purchasing/module.json), [service](../../modules/Purchasing/Services/PurchasingService.php), [routes](../../modules/Purchasing/routes.php) |
| Retail POS `0.1.0-dev.1` | Implemented lát cắt online và workspace nhiều phân hệ; giữ snapshot bán/giá/lô, command journal phía server, đối soát ca và outbox. Báo cáo vận hành cơ bản; chưa công bố báo cáo lãi gộp đầy đủ, `cost_status=unavailable`. | [manifest](../../modules/RetailPos/module.json), [service](../../modules/RetailPos/Services/RetailService.php), [controller](../../modules/RetailPos/Http/RetailController.php), [workspace](../../resources/admin/src/modules/retail-pos/pages/RetailPosManagerPage.jsx) |
| AccountingTax | Partial, tùy chọn: gọi API domain hiện có để tạo **`internal_invoice`, outbound, draft** từ `sale.completed`. Opt-in mapping pháp nhân/organization và SKU/item; kiểm quyền native; replay không tạo chứng từ thứ hai. Không tự approve/post/issue, không tạo movement kho hay payment kế toán. | [RetailAccountingBridge](../../modules/RetailPos/Services/RetailAccountingBridge.php), [integration controller](../../modules/RetailPos/Http/RetailIntegrationController.php), [AccountingDocumentService](../../app/Support/AccountingTax/AccountingDocumentService.php) |
| CMS/Catalog | Không còn là hard dependency của Retail/Inventory v2. Liên kết và publication sản phẩm/kênh online đầy đủ vẫn Pending; không dùng Catalog làm nguồn tồn dự phòng. | [Inventory package paths](../../modules/Inventory/module.json), [migration discovery](../../app/Core/Modules/Support/ModuleMigrationPaths.php) |
| Minvoice/provider thanh toán | Pending ở consumer Retail: chưa gọi mạng phát hành hóa đơn, QR/thẻ/capture/refund tự động. Có module optional không có nghĩa Retail đã tích hợp nghiệp vụ đó. | [Retail manifest](../../modules/RetailPos/module.json), [settings runtime](../../modules/RetailPos/Http/RetailController.php) |
| HRM/Payroll | Pending: chỉ khai báo optional dependency; chưa có đồng bộ nhân sự, chấm công, tính lương của Retail. | [Retail manifest](../../modules/RetailPos/module.json) |

## 4. Giới hạn nghiệp vụ cần giữ khi UAT

- **Điều chuyển:** tạo phiếu hiện gọi `dispatchTransfer` ngay, trừ kho nguồn và tạo in-transit. Chưa có trạng thái đề nghị/duyệt trước xuất. Endpoint `/dispatch` trả 422 để tránh xuất hai lần; nhận từng phần cần người khác người giao. Chỉ chuyển cùng chain và pháp nhân.
- **Purchasing:** HTTP API yêu cầu quyền **global**, phục vụ đầu mối mua hàng HQ. Không dùng membership Retail để hạn chế người mua trong một branch; không cấp các quyền này cho thu ngân/nhân viên kho chỉ có phạm vi chi nhánh. Ngoài Purchasing còn cần quyền Inventory native khi tạo/nhận/trả đơn.
- **Giá:** giá branch có version, quote lưu version và hết hạn 300 giây; complete kiểm lại TTL, SKU/assortment và giá trước side effect. Đây chưa phải publication có lịch/ACK tới quầy.
- **Thanh toán:** `cash` và `manual_transfer`; chuyển khoản là xác nhận thủ công có quyền và reference. Không suy ra ngân hàng/provider đã xác nhận; browser journal chỉ hỗ trợ khôi phục kết quả online.
- **Trả hàng:** theo sale gốc, giới hạn quantity/amount cộng dồn, trả vào branch gốc và giữ lineage lô/cost; phân loại saleable/quarantine/damaged. Cross-branch/cross-legal return chưa mở.
- **Accounting:** dòng đích là phép chiếu `quantity=1`, đơn giá bằng net total dòng Retail để giữ đúng tiền khi domain Accounting có precision khác. Snapshot lưu quantity/giá/tên/SKU/item mapping gốc; `projection_basis=retail_line_net_total`, `requires_tax_classification=true`, thuế `not_declared`. Kế toán phải phân loại thuế trước nghiệp vụ tiếp theo. Return/refund outbox còn deferred; chưa có reversal adapter.
- **Lifecycle:** Retail chặn disable/upgrade nếu còn ca open/closed chưa đối soát, sale priced hoặc refund pending; Inventory chặn disable khi còn giữ hàng, in-transit hoặc kiểm kê. Merchandise/Purchasing/Retail/Inventory v2 dùng shared mutation lock và durable state provider để lifecycle chờ lệnh đang chạy, đóng nhận lệnh mới và recovery đúng operation owner. Traffic ghi Inventory legacy chưa được bao phủ toàn bộ bởi runtime barrier này; vẫn phải quiesce client/job legacy khi maintenance. Đây chưa thay thế diễn tập maintenance/recovery toàn hệ thống. Merchandise/Purchasing/Retail không hỗ trợ uninstall dữ liệu lịch sử.

## 5. Bằng chứng kiểm thử đã ghi nhận

Các kết quả dưới đây là snapshot trong đợt phát triển, không phải SLA hay chứng nhận capacity. Khi source/schema đổi phải chạy lại trên release candidate cố định.

| Nhóm | Kết quả snapshot | Phạm vi và giới hạn |
| --- | --- | --- |
| Focused PHP feature | **78 tests / 843 assertions — pass** | Merchandise, Purchasing, Inventory v2, Retail POS, quote, authorization/replay, lifecycle barrier và Accounting bridge. SQLite kiểm logic/API; không thay bằng chứng lock MySQL. |
| Regression module liên quan | **21 tests / 132 assertions — pass** | Capability, mapping kho Accounting, Inventory availability, adapter F&B, Catalog lifecycle và CMS media migration. Đây là tập regression chọn lọc, không phải toàn AIO suite. |
| MySQL gates | **9 gates — pass** | Fresh không CMS/Catalog; hai process tranh hàng cuối; cùng key replay; decimal/stock commit; sale–return–refund–ca; hai process sale/tender atomicity; upgrade legacy/partial DDL/lineage; hai gate MySQL interleaving mutation với lifecycle drain ở Inventory v2 và Retail. |
| JavaScript unit | **39 tests — pass** | Toàn bộ `tests/UnitJavaScript/*.test.js`, gồm Retail và F&B; có test số chính xác/journal và binding JSX Retail sau transform thực. Không phải 39 E2E Retail. |
| Frontend build | **`npm run build` — pass** | Vẫn có cảnh báo chunk lớn của bundle admin; chưa có phép đo tải/quầy/scanner hoặc ngân sách hiệu năng đạt nghiệm thu. |
| Browser smoke cô lập | **Pass một hành trình online** | Onboarding Hà Nội, SKU tồn 24/giá 15.000, ca 0, bán 1 bằng tiền khách đưa 20.000/thu 15.000/thừa 5.000, tồn 23, đóng ca/variance 0/reconcile; thêm HCM cùng pháp nhân và niêm yết SKU master cũ giá 16.000, tồn riêng 0. Không thay UAT/thiết bị/pilot nhiều chi nhánh. |
| Toàn bộ PHP suite | **Chưa xanh** | Có lỗi baseline đã quan sát, ví dụ kỳ vọng điều hướng CMS trong `AdminFoundationApiTest`. Snapshot toàn suite cũ còn có lỗi schema khi source/migration đang đổi đồng thời. Chưa đủ bằng chứng để quy mọi lỗi thành baseline; cần chạy lại sau khi cố định source/schema, phân loại và xử lý từng lỗi. |

Nguồn kiểm chứng: [MerchandiseModuleTest](../../tests/Feature/MerchandiseModuleTest.php), [PurchasingModuleTest](../../tests/Feature/PurchasingModuleTest.php), [InventoryV2Test](../../tests/Feature/InventoryV2Test.php), [RetailPosTest](../../tests/Feature/RetailPosTest.php), [RetailQuoteTest](../../tests/Feature/RetailQuoteTest.php), [RetailAuthorizationTest](../../tests/Feature/RetailAuthorizationTest.php), [RetailReplayAuthorizationTest](../../tests/Feature/RetailReplayAuthorizationTest.php), [RetailInventoryBarrierTest](../../tests/Feature/RetailInventoryBarrierTest.php), [MerchandisePurchasingBarrierTest](../../tests/Feature/MerchandisePurchasingBarrierTest.php), [RetailAccountingBridgeTest](../../tests/Feature/RetailAccountingBridgeTest.php), [MySQL gate runner](../../tests/Support/retail-mysql-gates.php), [fresh/legacy smoke](../../tests/Support/inventory-v2-fresh-smoke.php), [Retail JS journal tests](../../tests/UnitJavaScript/retail-command-unit.test.js), [JSX binding tests](../../tests/UnitJavaScript/retail-component-bindings.test.js).

Runtime barrier dùng [ModuleRuntimeBarrier](../../app/Core/Modules/Support/ModuleRuntimeBarrier.php) và các provider [Retail](../../modules/RetailPos/Services/RetailRuntimeState.php), [Inventory](../../modules/Inventory/Services/InventoryRuntimeState.php), [Merchandise](../../modules/Merchandise/Services/MerchandiseRuntimeState.php), [Purchasing](../../modules/Purchasing/Services/PurchasingRuntimeState.php). Test bao gồm draining/recovery/compensation, stale operation owner, lệnh đã nạp service trước drain, thu hồi quyền native và khóa tài khoản sau bước authorize ban đầu.

## 6. Điều kiện tiếp tục triển khai

1. Cố định revision/schema, chạy lại focused/MySQL/full regression; phân loại từng lỗi AIO thay vì miễn toàn suite.
2. Diễn tập fresh và upgrade trên dữ liệu cô lập gần triển khai thực; chốt mapping hàng/kho/pháp nhân, không seed tồn lần hai.
3. Nghiệm thu online UAT nhiều chi nhánh, tiền–kho–PO–ca và native permissions; hoàn thiện những khoảng trống G1–G3 trong bảng trên.
4. Chọn thiết bị/provider/thuế và xây gateway nếu cần đạt G4; chạy restore, outage, load/soak và pilot thực theo kế hoạch.
5. Chỉ mở rộng số chi nhánh sau khi có capacity report và biên bản pilot. Số branch/SKU/quầy mà production chịu được **chưa được kiểm chứng** trong đợt này.
