# Kế hoạch triển khai app Quản lý xưởng may

> Phiên bản kế hoạch: `0.1.0` · Ngày: `2026-10-21`.
> Phạm vi ưu tiên: FOB và nhãn hàng riêng, doanh nghiệp tự mua nguyên phụ liệu, sản xuất và giao hàng.
> Trạng thái: kế hoạch triển khai; package `manufacturing` và các capability mục tiêu dưới đây chưa được triển khai.

App Sản xuất quản lý đơn hàng và kế hoạch nhãn hàng, kỹ thuật sản phẩm, điều phối nhiều phân xưởng, dòng nguyên liệu, sản phẩm dở dang, chất lượng và giao hàng. Merchandise giữ master hàng hóa, Inventory giữ tồn chính thức; Purchasing thực hiện mua nguyên liệu. App mới phải chạy được một đơn FOB từ đầu đến cuối và đối soát được số lượng, giá trị trước khi mở rộng vận hành.

## 1. Phạm vi và giả định

### 1.1. Những yêu cầu đã xác định

- Ưu tiên FOB và nhãn hàng riêng: tự mua nguyên liệu, sản xuất, nhập thành phẩm và giao hàng.
- Nhu cầu sản xuất đến từ đơn khách hàng, kế hoạch bộ sưu tập hoặc bổ sung tồn cho kênh bán.
- Có nhiều nhà máy hoặc phân xưởng, cần chia lệnh, bàn giao bán thành phẩm và điều hành tập trung.
- Kết nối linh hoạt với Merchandise, Inventory, Purchasing, Customers, HRM, Payroll, AccountingTax và kênh bán.
- Giữ kiến trúc AIO single-tenant theo codebase; mở rộng trong modular monolith hiện tại.

### 1.2. Giả định cần xác nhận trong G0

| Nội dung | Baseline dùng để lập kế hoạch | Quyết định cần chốt |
| --- | --- | --- |
| Quy mô | Câu trả lời “10” tạm hiểu là 10 phân xưởng hoặc địa điểm sản xuất | Là nhà máy, phân xưởng, chuyền hay người dùng; số chuyền và thiết bị từng nơi |
| Pilot | Hai phân xưởng có bàn giao thực tế, cùng một pháp nhân vận hành | Địa điểm, người phụ trách, mã hàng và đơn pilot |
| Kết nối | Luồng online tập trung, có màn tra cứu command sau mất response | Chất lượng WAN/LAN và thời gian gián đoạn chấp nhận được |
| Thiết bị | Máy tính hoặc tablet; mã QR nội bộ ở cấp bó, cuộn và carton | Máy quét, máy in tem, số trạm và phương án vận hành khi thiết bị hỏng |
| Nhân lực sản xuất | Bản đầu ghi theo tổ/chuyền; tài khoản admin cho người thao tác | Có yêu cầu ghi cá nhân hoặc tính lương công đoạn ngay trong pilot hay không |
| Nguyên liệu | Pilot dùng nguyên liệu thuộc doanh nghiệp | Loại vải, đơn vị chính, số đo phụ, quy trình kiểm và chính sách hao hụt |
| Gia công ngoài | Có model và đối soát thủ công có kiểm soát nếu đơn pilot dùng in, thêu hoặc wash | Công đoạn, đối tác, thời gian, phí và quy trình nghiệm thu |

Thiết kế dữ liệu hỗ trợ nhiều địa điểm ngay từ đầu. Số 10 không phải bằng chứng về số người dùng đồng thời hoặc năng lực xử lý. Các quyết định chưa chốt được ghi owner, hạn giải quyết và gate chịu ảnh hưởng; chỉ chặn công việc phụ thuộc trực tiếp.

### 1.3. Phạm vi bản đầu vận hành

Luồng bắt buộc: nhu cầu → mã hàng và size/màu → kỹ thuật đã duyệt → kế hoạch → mua/nhận/QC nguyên liệu → cấp từng đợt → cắt/bó → bàn giao và may → QC/sửa → nhập thành phẩm → đóng gói/giao từng phần → đối soát nguyên liệu và giá thành tác nghiệp.

Các phần triển khai sau pilot: lập lịch tối ưu tự động, offline edge, RFID/hệ thống treo, kết nối CAD tự động, portal khách/đối tác, AI, CMT nguyên liệu khách cấp, lương sản phẩm đầy đủ và ghi sổ kế toán tự động. Sơ đồ cắt/CAD được đính kèm thủ công trong bản đầu. Nếu đơn pilot dùng gia công ngoài, thao tác gửi/nhận và đối soát tương ứng phải hoàn thành trước pilot.

Mô hình sản xuất full-package/FOB và điều kiện giao hàng là hai thông tin riêng. Nhãn hàng riêng được phép lập lệnh từ kế hoạch tồn, không bắt buộc có một khách đặt hàng cho mọi lệnh.

## 2. Baseline source và khoảng trống

Các phiên bản dưới đây là manifest source tại thời điểm lập kế hoạch. Mỗi deployment phải đối chiếu installed version, schema và trạng thái module bằng kiểm tra chỉ đọc trước nâng cấp.

| Thành phần | Đã có | Công việc cần thêm cho xưởng may |
| --- | --- | --- |
| Core | Auth, RBAC, audit, lifecycle lease/recovery, runtime barrier và capability checker | Native context sản xuất, membership địa điểm, bootstrap workspace và chính sách thiết bị |
| Merchandise `0.1.0` | SKU, barcode, base unit, precision và quy đổi cố định có revision | Variant size/màu, quan hệ style với SKU, contract đơn vị phù hợp vật tư may |
| Inventory `0.3.0` | Decimal, lô, disposition, reserve/commit, transfer nhận từng phần, FIFO và stocktake | Cuộn/số đo phụ, giữ dài hạn theo lệnh, cấp/trả từng phần, transformation và lineage giá vốn |
| Purchasing `0.1.0` | NCC, PO, maker-checker, nhận từng phần, trả NCC và công nợ tạm | Requisition từ MRP, nhận nhiều cuộn vào quarantine, QC release/reject, trả hàng quarantine và UOM snapshot |
| Customers `0.1.0` | Hồ sơ chung, book, binding/cutover và account link | Provider và native authorization cho Manufacturing; resolver hiện còn xử lý riêng FNB/Retail |
| HRM và Payroll `1.0.0` | Hồ sơ nhân sự, attendance theo ngày, kỳ/phiếu lương | Phân công/ca qua đêm/kỹ năng và earning ledger, import công đã duyệt, decimal và transaction phù hợp |
| AccountingTax `0.2.0` | Chứng từ, approval/posting, mapping và artifact private | Adapter quyết toán sản xuất, mapping và đối soát; chưa có production costing subledger dùng sẵn |
| React admin | Shell, module menu, lazy route, drawer, API wrapper | Manufacturing workspace và native selector; route component vẫn cần đấu nối tường minh |

Những ràng buộc ảnh hưởng critical path:

1. Reservation Inventory hiện mặc định 15 phút và commit/release toàn bộ. Production hold phải có contract mới; không đổi semantics POS hoặc dùng `protected_payment`/escrow để giả giữ vật tư sản xuất.
2. Purchasing receive hiện không truyền disposition nên stock mặc định saleable. Pilot phải nhận vào quarantine và chỉ giải phóng sau QC.
3. Trả NCC hiện reserve từ phần saleable. Hàng rejected/quarantine cần return theo receipt, roll, owner, location và cost lineage gốc.
4. Stocktake hiện chặn mọi active reservation. Muốn giữ vật tư dài hạn phải xây cách kiểm kê tương thích hoặc freeze phạm vi có quy trình đối soát; không tự hủy hold để vượt gate.
5. `legal_entity_ref` của kho và alias `owner_ref` hiện chưa biểu diễn chủ sở hữu hàng độc lập. Backfill self-owned phải xác định được và giữ số dư; CMT vẫn bị khóa tới khi contract ownership hoàn chỉnh.
6. Inventory writer hiện có khóa cấp warehouse. Đường ghi tải lớn cần đo contention và điều chỉnh theo stock bucket với thứ tự khóa an toàn.
7. API admin hiện chịu `admin.website`; app toàn doanh nghiệp cần nhánh context hẹp có authorize riêng. Không dùng website đang chọn làm bằng chứng quyền xưởng.
8. Bridge Accounting Inventory hiện gọi writer legacy theo Catalog. Manufacturing phải dùng contract production mới, không đi vòng qua bridge legacy.

File neo: [Merchandise service](../../modules/Merchandise/Services/MerchandiseService.php), [Inventory v2](../../modules/Inventory/Services/InventoryV2Service.php), [Purchasing service](../../modules/Purchasing/Services/PurchasingService.php), [Purchasing routes](../../modules/Purchasing/routes.php), [website gate](../../app/Http/Middleware/EnsureAdminWebsiteAccess.php), [capability checker](../../app/Core/Modules/ModuleCapabilityChecker.php), [Payroll](../../app/Http/Controllers/Admin/Api/Payroll/PayrollPayslipController.php), [Accounting inventory bridge](../../app/Support/AccountingTax/AccountingInventoryBridge.php).

## 3. Ownership và tổ chức nhiều địa điểm

### 3.1. Các trục dữ liệu

- Doanh nghiệp vận hành → nhà máy → phân xưởng → work center/chuyền → trạm/máy.
- Pháp nhân vận hành và mapping Accounting là trục riêng có hiệu lực theo thời gian; kho/giao dịch giữ identity và snapshot đúng thời điểm.
- Ca là lịch theo địa điểm/timezone; tổ và nhân sự được phân công có ngày hiệu lực, không là con cố định của chuyền.
- Kho và vị trí thuộc Inventory; Manufacturing liên kết phạm vi được dùng và quyền sử dụng.
- Website chỉ là kênh nguồn hoặc kênh công bố sản phẩm. Các bảng sản xuất không dùng `website_key` làm partition chính.

Manufacturing giữ đơn vị vận hành tối thiểu độc lập Accounting. Mapping sang `acct_organizations` là tùy chọn; thao tác kế toán yêu cầu mapping và quyền organization hợp lệ. Không thêm scope factory/branch vào Core hoặc mở organization scope kế toán cho Manufacturing.

### 3.2. Ranh giới sở hữu

| Miền hoặc module | Sở hữu | Boundary cần giữ |
| --- | --- | --- |
| Manufacturing Engineering | Style, tech pack, mẫu duyệt, measurement, BOM/routing và revision | Tham chiếu SKU Merchandise; release đóng băng revision |
| Manufacturing Planning | Demand, forecast consumption, MRP proposal, lịch và phân bổ sản xuất | Đề xuất mua/giữ vật tư đi qua contract, không tự ghi PO/stock |
| Manufacturing Execution | Lệnh, bó, công đoạn, bàn giao, WIP attribution, rework và subcontract order | Không tạo ledger tồn vật lý thứ hai |
| Manufacturing Quality | Checklist, defect, kết quả kiểm, disposition decision, hold/release | Thay đổi availability qua stock contract được authorize |
| Manufacturing Costing | Chi phí theo lệnh, allocation, WIP tác nghiệp, cost run và quyết toán | Nhận actual material cost từ Inventory; giữ reconciliation tới stock layers |
| Merchandise | Identity SKU, generic variant axes, barcode và đơn vị cố định | Thuộc tính kỹ thuật may nằm ở Manufacturing, SKU thương mại không bị nhân đôi |
| Inventory | Stock, roll/lot, custody, reservation, movement và cost layers | Mọi reader/writer dùng cùng grain; stock bán thành phẩm chỉ ghi tại đây |
| Purchasing | NCC, PO, receipt/return và nghĩa vụ mua hàng | Manufacturing phát requisition; không tự tạo sổ NCC/AP |
| Các adapter | Mapping, readiness, source links, outbox/inbox và reconcile | Module optional không có FK bắt buộc hoặc truy vấn tắt qua bảng còn tồn tại |

Cấp vật tư tới WIP không đồng nghĩa tiêu hao. G0 phải chốt điểm chuyển: nguyên liệu tại vị trí WIP vẫn do Inventory giữ lượng; khi thực tiêu hao, cost được chuyển có lineage sang WIP theo lệnh; khi tạo output, actual cost và conversion cost được phân bổ vào thành phẩm/WIP còn lại/scrap hoặc variance theo policy. Cost layers chính thức và báo cáo WIP phải đối soát được. Không cộng kg, mét và chiếc vào cùng một phương trình số lượng.

### 3.3. Dependency và capability mục tiêu

- Manifest app `manufacturing`: hard dependency Merchandise và Inventory ở installed version cung cấp contract đã nghiệm thu.
- Purchasing là optional dependency về package, nhưng là **dependency vận hành bắt buộc của pilot FOB này**. Nguồn mua ngoài sau này cần adapter riêng được nghiệm thu.
- Customers, HRM, Payroll, AccountingTax, Catalog/CMS/Retail là optional. App không phụ thuộc FNB hoặc một theme.
- G0 chốt version phát hành; chỉ công bố capability có implementation, schema, authorization và test. Khai báo `introduced_version` theo bản cài thực tế.

| Capability dự kiến chưa tồn tại | Provider | Nội dung tối thiểu |
| --- | --- | --- |
| `merchandise.variants.read.v1`, `merchandise.variants.manage.v1` | Merchandise | Axis/combination, SKU ổn định, revision và import |
| `inventory.trace_units.manage.v1` | Inventory | Roll/lot, số đo phụ, split và genealogy |
| `inventory.quality.dispositions.v1` | Inventory | Quarantine/release/reject/return có lineage |
| `inventory.production.reservations.v1` | Inventory | Giữ theo lệnh, cấp/release/trả từng phần, kiểm lại quyền |
| `inventory.production.post.v1` | Inventory | Input/output/scrap, cost provenance, reversal và transaction-aware posting |
| `purchasing.production.requisitions.v1` | Purchasing | Demand/requisition/PO link và partial receipt với QC |
| `manufacturing.execution.v1` | Manufacturing | Release lệnh, công đoạn, bó, handover và accepted output |
| `manufacturing.costing.read.v1` | Manufacturing | Provisional/final cost, revision và reconciliation |

Semantics stock ownership khách cấp và adapter lương/kế toán được đặt tên capability sau khi phạm vi đó được triển khai; không quảng bá CMT chỉ vì schema có trường owner.

## 4. Các invariant triển khai

1. Core permission thao tác kết hợp native membership và row policy; quyền xưởng không tự cấp quyền CMS, HRM, Purchasing HQ hoặc Accounting.
2. List, detail, export, command status và replay đều authorize phạm vi hiện hành; entity ID/header/body phải được server kiểm lại.
3. Lệnh phát hành giữ snapshot BOM, routing, variant, UOM và policy. Thay đổi sau release đi engineering change có đánh giá ảnh hưởng WIP và người duyệt.
4. Forecast và đơn thật có consumption policy để không tạo hai nhu cầu cho cùng cam kết. Tổng phân bổ lệnh con không vượt nhu cầu/tolerance đã duyệt.
5. Available stock loại quarantine, rejected, reservation, hàng đi đường và lượng không thuộc phạm vi/owner được phép.
6. Cuộn dùng một đơn vị chính có thẩm quyền; số đo phụ và tỷ lệ đo thực tế giữ snapshot. Đổi số đo là adjustment có quyền, không viết lại lịch sử quy đổi.
7. Issue, return, output, scrap và reversal có source identity và cost lineage; trả không vượt phần còn có thể trả; giữ hàng và kiểm kê không làm âm tồn/giữ.
8. Split/merge/bàn giao bó bảo toàn lượng và genealogy; một lượng ở trạng thái đang đi không đồng thời khả dụng ở hai xưởng.
9. QC hold và rework tách khỏi output tốt. Sửa lại hoặc scan trùng không tự tạo thêm thành phẩm hoặc công được tính trùng.
10. Chốt giá thành dùng decimal/rounding policy; output, WIP, scrap/variance đối soát với material actual cost và conversion cost được duyệt. Posted history sửa bằng adjustment/reversal.
11. Cùng command key và payload trả kết quả gốc sau kiểm quyền; cùng key khác payload trả conflict. Timeout sau commit được tra trạng thái/replay bằng identity cũ.
12. Ghi nghiệp vụ nội bộ và outbox trong cùng transaction. Consumer deduplicate theo event ID; tích hợp ngoài có retry/reconcile, không gọi mạng khi giữ DB lock.
13. Forward migration giữ ID, số dư, reservation, layer và dữ liệu consumer cũ. Không sửa migration đã chạy hoặc tái seed tồn để nâng cấp.
14. Tài liệu kỹ thuật, ảnh QC, export và dữ liệu công dùng disk private riêng, download có quyền, checksum/retention và audit; không mặc định dùng disk `local` hiện trỏ `public/files`.

## 5. Cấu trúc code và bề mặt sản phẩm

### 5.1. Package và các điểm đấu nối

```text
modules/Manufacturing/
  module.json
  routes.php
  Contracts/
  Domain/{Engineering,Planning,Execution,Quality,Subcontracting,Costing}/
  Services/{Access,Commands,Integrations,Runtime}/
  Http/
  Jobs/
  Hooks/
  database/migrations/
resources/admin/src/modules/manufacturing/
  pages/
  components/
  hooks/
  services/
  utils/
  styles/
tests/Feature/Manufacturing*/
tests/UnitJavaScript/manufacturing-*.test.js
tests/Support/manufacturing-mysql-gates.php
tests/browser/manufacturing.spec.js
```

Đây là cấu trúc mục tiêu. Migration, service và test cụ thể được thêm theo từng lát cắt. Ưu tiên logic domain/service trong package; core chỉ nhận thay đổi dùng chung cần thiết, không nhét sản xuất vào `CmsSiteController` hoặc `LandingPageBuilder`.

Các điểm đấu nối có kiểm soát: routes admin và middleware/context; bootstrap `/me` hoặc endpoint enterprise riêng; `AdminLayout`, `ModuleRoutePage`, API config; ModuleManager/security/runtime provider; upgrade Merchandise/Inventory/Purchasing. Trước khi sửa phải đọc diff hiện có vì admin shell và navigation FNB đang có thay đổi chưa commit.

Các nhóm bảng mục tiêu `mfg_*`: đơn vị/địa điểm/membership; style/tech revisions/BOM/routing; demand/forecast consumption/plan allocation; orders/releases/material requirements; production lots/bundles/operation runs/handover; inspections/rework; subcontract orders; cartons/shipments; cost runs/allocations; command/outbox/inbox/source links. G0 quyết định schema và FK sau khi chốt grain, không dùng danh sách này như migration đã tồn tại.

### 5.2. Giao diện và API

Workspace dự kiến `/admin/modules/manufacturing/*`; API `/admin/api/manufacturing/*` với auth, active/password/session, module gate và native context/permission. Native bootstrap phải phục vụ operator sản xuất không có website grant mà vẫn giữ nguyên bảo vệ các API khác.

Menu: Tổng quan; Nhu cầu và đơn hàng; Kỹ thuật sản phẩm; Kế hoạch; Nguyên liệu; Cắt; Sản xuất và bàn giao; Chất lượng; Gia công ngoài; Thành phẩm và giao hàng; Giá thành; Thiết lập.

Danh sách dùng filter/phân trang phía server; form tạo/sửa dùng drawer; ma trận size/màu có validation tổng. Màn hiện trường tối giản theo công việc nhận bó, giao bó, ghi sản lượng hoặc QC; quyền quyết định nội dung được thao tác. Chọn xưởng phải reset state và bỏ response cũ; journal browser chỉ lưu key/hash/scope, không lưu tài liệu, PII hoặc token.

API command trả request ID, version, replay status và integration status; phân biệt stale version, scope denied, stock conflict và dependency unavailable. Status lookup chỉ trả command của identity/phạm vi được phép. Device API, nếu bổ sung, có credential và authority riêng; không tái dùng admin cookie làm token máy.

## 6. Các giai đoạn và critical path

```mermaid
flowchart LR
    G0["G0 Contract và fixture"] --> G1["G1 Nền app và vật tư"]
    G1 --> G2["G2 Kỹ thuật và kế hoạch"]
    G2 --> G3["G3 Đơn FOB xuyên suốt"]
    G3 --> G4["G4 Pilot hai phân xưởng"]
    G4 --> G5["G5 Mở rộng theo cụm"]
    G5 --> G6["G6 Adapter và tối ưu"]
```

Critical path: ownership/UOM/context → variant và roll/QC receiving → production holds/transformation → thực thi/QC/thành phẩm → đối soát/pilot → mở rộng. UX, fixture và các master kỹ thuật có thể làm song song sau khi contract tương ứng ổn định. Không đưa ledger tồn tạm vào pilot rồi mới chuyển Inventory.

## 7. G0 Chốt contract và dữ liệu nghiệm thu

**Đầu vào:** source baseline, mẫu đơn FOB/nhãn hàng, BOM, thông số, phiếu kho/QC, packing list và quy trình hai xưởng pilot.

- [ ] `G0-01` Chốt quy mô “10”, hierarchy, operating entity, native grants, kho và lịch ca; xác định một địa điểm dùng nhiều pháp nhân nếu có.
- [ ] `G0-02` Chốt style/size/color/SKU, revision, loại vật tư/thành phẩm, base UOM và measured secondary UOM.
- [ ] `G0-03` Chốt stock grain và backfill self-owned; mối quan hệ owner/custodian, roll/shade, location và disposition; giữ CMT ngoài release đầu.
- [ ] `G0-04` Chốt PO → receipt → quarantine → QC → release/reject/return; chính sách số thực nhận, số chấp nhận và nghĩa vụ phải trả là các khái niệm riêng.
- [ ] `G0-05` Chốt forecast consumption, MRP theo ngày, allocation, tolerance và trạng thái plan/order/release; source demand không bị nhân đôi.
- [ ] `G0-06` Chốt cấp vật tư/tiêu hao/WIP/FG/scrap, cost handoff, provisional/final costing, nhập/giao từng phần và adjustment chi phí đến muộn.
- [ ] `G0-07` Chốt command/API/event envelope, lifecycle, retry/replay, permission matrix và native enterprise bootstrap.
- [ ] `G0-08` Chốt routing cấu hình, checkpoint QC, split/merge/recut, handover hai bước, subcontract và carton genealogy.
- [ ] `G0-09` Tạo golden fixture, expected quantities/costs, test matrix; phân loại baseline failures trước khi sửa code.
- [ ] `G0-10` Khảo sát số chuyền, peak scans, data retention, mạng/thiết bị, SLO và phạm vi pilot; phân công owner và estimate lại backlog.

**Đầu ra:** ADR/data dictionary, state machines, OpenAPI/contract draft, ma trận quyền, bộ fixture có đáp án và decision log. G0 tạo đặc tả chi tiết đúng phần cần thi công; kế hoạch này chưa thay các contract đó.

**Gate:** các quyết định ownership, quantity/cost, scope và transaction đủ rõ để viết migration và test. Các giả định còn mở có owner và không làm sai boundary của work package bắt đầu.

## 8. G1 Nền app và hàng hóa nguyên liệu

**Phụ thuộc:** G0 chốt contract liên quan. Core/context, Merchandise/Inventory và UI shell được làm theo các nhánh riêng có review giao diện contract.

- [ ] `G1-01` Tạo package Manufacturing, migrations, hooks, permission definitions, runtime provider, lifecycle và readiness; `uninstall=false` ở release đầu.
- [ ] `G1-02` Native hierarchy/membership và bootstrap; kiểm toàn bộ đường list/detail/export/command; giữ nguyên gate CMS/FNB/Retail/Accounting.
- [ ] `G1-03` Command journal, optimistic version, outbox/inbox, audit, private storage và màn health/reconcile cơ bản.
- [ ] `G1-04` Nâng Merchandise variants/import; SKU/barcode/base unit/precision cũ giữ nguyên; extension kỹ thuật không tạo master SKU thứ hai.
- [ ] `G1-05` Nâng stock grain và roll/measurement; đồng bộ balance, layers, reservation, availability, transfer, return và stocktake theo grain mới.
- [ ] `G1-06` Nâng Purchasing nhận từng phần/nhiều roll vào quarantine; QC disposition, trả NCC từ quarantine/rejected và giữ lineage nghĩa vụ mua.
- [ ] `G1-07` Production hold dài hạn, partial allocation/issue/release/return; stocktake freeze scope có đối soát hold và chênh lệch.
- [ ] `G1-08` Production posting input/output/scrap và cost lineage; contract tham gia transaction caller, reversal kiểm downstream usage.
- [ ] `G1-09` Native warehouse authorization cho adapter; Purchasing HQ tiếp tục cần quyền global hiện hữu tới khi có delegated contract được nghiệm thu.
- [ ] `G1-10` Admin workspace shell/context switching, migration rehearsal fresh/legacy/partial DDL và regression consumer cũ.

**Gate:** receipt/quarantine/QC/supplier return; reserve/issue/return/output; cost/quantity lineage và stocktake chạy bằng service/API trên MySQL. Chưa cần hoàn chỉnh UI may để kiểm các stock contract. Fresh install không cần CMS/Catalog/Accounting/HRM. Retail/Purchasing cũ giữ số dư và hành vi đã khóa bằng regression.

## 9. G2 Kỹ thuật và kế hoạch FOB

**Phụ thuộc:** Engineering có thể làm sau G0 identity/revision; phần stock/MRP thực chỉ nghiệm thu sau capability G1 tương ứng.

- [ ] `G2-01` Style/colorway/size matrix, measurement, tech pack private, sample requests và các vòng duyệt theo khách/nhãn hàng.
- [ ] `G2-02` BOM theo variant, consumption/allowance, vật tư thay thế có duyệt; routing/operation/standard minutes có revision.
- [ ] `G2-03` Demand từ đơn thật hoặc kế hoạch nhãn hàng; revision, consumption với forecast, due dates và giao từng phần.
- [ ] `G2-04` Lệnh và release snapshot; engineering change có impact report tới hold/PO/WIP, không sửa lịch sử release.
- [ ] `G2-05` MRP sơ cấp theo ngày cần, available/allocated/inbound đã duyệt; requisition → PO link; lập lại cùng version không tạo đề nghị mua trùng.
- [ ] `G2-06` Calendar/ca và năng lực kế hoạch theo work center; phân bổ thủ công giữa xưởng; cảnh báo quá tải, setup và vật tư/mẫu chưa sẵn sàng.
- [ ] `G2-07` Plan nháp/committed, freeze window, lý do đổi và version conflict; UI Gantt cơ bản hoặc bảng lịch có cùng contract.
- [ ] `G2-08` Permission theo planner/engineering/approver và kiểm matrix size/màu ở cả server/UI.

**Gate:** một đơn thật và một kế hoạch nhãn hàng tạo demand đúng, không bị cộng trùng forecast; BOM theo size/màu sinh đúng yêu cầu vật tư; release giữ nguyên sau master đổi; phân bổ cho hai xưởng và chuyển requisition sang Purchasing có provenance.

## 10. G3 Luồng sản xuất và giao hàng xuyên suốt

**Phụ thuộc:** G1 stock contracts và G2 released order; frontend làm theo từng hành trình cùng API, không gom UI tới cuối.

- [ ] `G3-01` Nhận nguyên liệu theo PO, QC/roll/shade, cấp tới WIP và trả dư từng phần theo issue gốc.
- [ ] `G3-02` Cut order/lay/ply/size ratio, marker attachment, actual consumption, remnant/scrap và recut; in QR và genealogy của bundle.
- [ ] `G3-03` Operation run, record output theo ca/tổ, split/merge bó; routing quyết định công đoạn hợp lệ và điểm hội tụ nếu có nhánh.
- [ ] `G3-04` Bàn giao dispatch/receive từng phần, in-transit, thiếu/hỏng và phê duyệt chênh lệch; cross-legal giữ ngoài luồng transfer nội bộ pilot.
- [ ] `G3-05` Incoming/inline/end-line/final QC, defect taxonomy và measurement tolerance; rework/reinspection; stock hold/release qua Inventory contract.
- [ ] `G3-06` Subcontract order nếu pilot cần: material/WIP custody, giao/nhận nhiều lần, rejected/rework, phí và đối soát; chưa tự tạo invoice/công nợ pháp lý.
- [ ] `G3-07` Nhập FG/quarantine theo variant, carton contents/packing ratio, shipment hold/release, giao từng phần; source demand và stock outbound cùng nhất quán.
- [ ] `G3-08` Cost run theo đơn: material actual, chi phí công/gia công đã duyệt và overhead policy; provisional/final, WIP/output/scrap allocation và reconciliation. Ghi chi phí tổ/chuyền chưa đồng nghĩa tính lương cá nhân.
- [ ] `G3-09` Dashboard theo xưởng/đơn, WIP aging, tiến độ, QC và thiếu vật tư; command/queue/reconcile monitor có phân quyền.
- [ ] `G3-10` Browser E2E cùng failure injection: timeout sau commit, duplicate scan, hai người bàn giao cùng bó, stale context và quyền bị thu hồi.

**Gate:** đơn FOB chạy từ mua nguyên liệu tới giao thành phẩm trên môi trường cô lập, có ít nhất hai xưởng, partial receipt/issue/return/handover/shipment, một case rework và chi phí đối soát được. Nếu G0 xác nhận pilot có gia công ngoài, G3-06 và AT18 cũng phải đạt. Không còn lệch chưa giải thích ở vật tư, WIP, carton, shipment hoặc valuation.

## 11. G4 Pilot hai phân xưởng

**Phụ thuộc:** G3 đạt gate; Purchasing được cài/nâng đúng contract; thiết bị, mạng, người vận hành và dữ liệu pilot đã chọn.

- [ ] `G4-01` Freeze release candidate/schema; chạy targeted, MySQL gates, full regression và build; kiểm tải/soak theo profile hai xưởng pilot, có tranh stock và query báo cáo, đạt SLO/queue recovery G0 trước khi mở writer thật. Capacity report pilot là đầu vào G5. Từng failure được phân loại có owner, không tự miễn toàn suite vì có baseline.
- [ ] `G4-02` Rehearsal fresh/upgrade với dữ liệu cô lập; import preview rồi đối soát roll, tồn, WIP, hàng ở đối tác, PO còn nhận và đơn đang giao.
- [ ] `G4-03` Chọn điểm cutover, khóa writer cũ trong phạm vi chuyển đổi, chụp backup và kiểm checksum/đếm lượng. Mỗi opening record có nguồn và chống import lặp.
- [ ] `G4-04` Restore drill trên môi trường khác; ghi RPO/RTO đạt thực tế và backlog phục hồi outbox/job/print.
- [ ] `G4-05` Cấu hình worker/scheduler, queue timeout/retry/lease, alert, disk private/retention và quyền file; có runbook lỗi thiết bị/WAN.
- [ ] `G4-06` Đào tạo planner, kho, cắt, tổ trưởng, QC, giao hàng và quản lý; thử máy in/quét thực trên các điểm bàn giao.
- [ ] `G4-07` Vận hành pilot theo chu kỳ đơn và các ca đại diện; đối soát hàng ngày giữa vật tư, WIP, QC, FG, giao hàng và cost.
- [ ] `G4-08` Diễn tập native pause, lifecycle drain/recovery khi còn WIP, worker crash, mất response và khôi phục thiết bị.
- [ ] `G4-09` Chốt biên bản nghiệm thu, sai lệch còn mở, owner hỗ trợ, phạm vi tải được phép và điều kiện dừng mở rộng.

**Gate:** profile tải hai xưởng, recovery và thiết bị đạt trước khi ghi dữ liệu thật; sau đó ít nhất một đơn đi hết vòng sản xuất–giao hàng và một lần đối soát/chốt cost theo policy. Các ca/thiết bị đại diện đã được thử, không có lỗi chặn vận hành hoặc rò quyền. Pilot kết thúc theo bằng chứng, không chỉ theo số ngày đã chạy.

## 12. G5 Mở rộng lên các phân xưởng còn lại

**Phụ thuộc:** G4 có biên bản và capacity report hai xưởng pilot. Trước khi mở mỗi cụm mới phải kiểm tải lại theo profile của cụm đó; report pilot không chứng minh capacity 5–10 xưởng. Nếu G0 xác nhận tổng quy mô khác 10, điều chỉnh số đợt rollout và test profile tương ứng.

- [ ] `G5-01` Benchmark và soak theo số chuyền/thiết bị/event đã khảo sát; đo hot warehouse, hot SKU và hot order; tối ưu locks/index/read models theo bằng chứng.
- [ ] `G5-02` Bổ sung dữ liệu và grants từng xưởng bằng import có preview, warehouse/line mapping và checklist readiness riêng.
- [ ] `G5-03` Mở theo cụm dự kiến 2 → 5 → 10 phân xưởng; mỗi cụm đủ ca/chu kỳ đại diện và đối soát đạt trước cụm tiếp theo.
- [ ] `G5-04` Kiểm điều chuyển/WIP qua nhiều xưởng, phân bổ lại kế hoạch, kiểm kê và QC hold đồng thời; drill restore ở quy mô mới.
- [ ] `G5-05` Chốt hạ tầng, retention, support rota và dashboard hệ thống; ghi rõ giới hạn tải đã đo của release.

**Gate:** từng cụm không có lệch chưa giải thích, queue backlog phục hồi trong ngân sách, quyền/thiết bị đúng phạm vi; đạt SLO và recovery objectives đã chốt tại G0.

## 13. G6 Các adapter và tối ưu sau pilot

Các gói độc lập dưới đây có thể lên ưu tiên sớm nếu nghiệp vụ pilot yêu cầu, nhưng phải bổ sung dependency và gate tương ứng.

| Gói | Công việc | Điều kiện nghiệm thu |
| --- | --- | --- |
| Customers | Provider/binding Manufacturing, book grants và snapshot buyer | Quyền native + Customers hợp lệ; chọn shared mà thiếu readiness thì fail closed; không auto-merge |
| HRM | Employee reference, skill/assignment history, ca nhiều đoạn và qua đêm | Có explicit workforce mode; không tái tạo HR master hoặc cấp account cho mọi công nhân |
| Payroll | Earning ledger, rate snapshot, cut-off, approval, batch import và adjustment; nâng transaction/decimal Payroll | Batch trùng không nhân lương; correction sau khóa là adjustment; mẫu cũ không đổi kỳ đã chốt |
| Accounting | Mapping pháp nhân/kho/party, approved cost settlement và source-link journal | Đúng quyền organization, reconciliation và classification; không mặc định settlement là invoice hoặc tự post |
| Catalog và Retail | Publish/link SKU thành phẩm, nhận demand bổ sung tồn, transfer/phân phối | Không seed tồn lần hai; Catalog giữ marketing, Retail giữ giá/assortment/bán hàng |
| Advanced planning | Finite capacity, what-if, setup/learning curve, bottleneck và critical path | So kế hoạch với actual và baseline; planner duyệt committed plan |
| Thiết bị và offline | CAD/RFID/machine connector hoặc site edge authority | Credential, bounded allocation, sequence, fencing, dedup và outage/reconcile đã test |
| CMT | Customer-owned stock/custody, vật tư khách cấp và quyết toán | Owner partition end-to-end ở mọi stock writer/reader, quantity/valuation và legal mapping đã kiểm |
| Portal và AI | Buyer/vendor view, cảnh báo/đề xuất lịch, tìm tài liệu theo quyền | Phạm vi dữ liệu, version và người duyệt; không tự sửa BOM/stock/committed plan |

## 14. Bộ dữ liệu và ma trận nghiệm thu

### 14.1. Fixture tối thiểu

- Một installation có fixture đủ 10 địa điểm theo giả định; hai xưởng tham gia hành trình E2E, các xưởng khác dùng để thử phân quyền/tải.
- Hai operating entities để kiểm từ chối thao tác xuyên pháp nhân; pilot sản xuất cùng một entity. Có website và FNB/Retail để khóa regression context.
- Một style hai màu, năm size, BOM khác nhau theo size; revision nguồn và engineering change sau release.
- Hai lot, ít nhất ba cuộn có shade và tỷ lệ đo phụ khác nhau; vật tư quarantine/rejected, vật tư dư và remnant.
- Một đơn FOB và một forecast/nhu cầu tồn nhãn hàng; partial receipt, issue, return, output và shipment. Thêm subcontract fixture nếu G0 xác nhận pilot có gia công ngoài.
- Tài khoản HQ, planner, kho, tổ trưởng từng xưởng, QC, cost viewer và người bị thu hồi quyền; nhiều thiết bị thao tác cùng stock/bundle.
- Số lượng/đơn giá fixture là dữ liệu kiểm thử, không phải định mức ngành may hoặc chính sách lương.

### 14.2. Các scenario bắt buộc

| ID | Scenario | Kết quả bắt buộc | Gate đầu tiên |
| --- | --- | --- | --- |
| AT01 | Fresh core rồi cài Manufacturing qua lifecycle | Không aggregate module ngoài test; thiếu capability bắt buộc chặn enable; optional module vắng vẫn hợp lệ | G1 |
| AT02 | Operator xưởng A dùng ID/header/filter xưởng B | List/detail/export/command/status đều từ chối; native grants không cấp quyền CMS/Accounting | G1 |
| AT03 | Native bootstrap cho người không có website grant | Workspace sản xuất đúng quyền; các website API vẫn giữ gate cũ; must-change-password/session gate giữ nguyên | G1 |
| AT04 | Import variant/SKU trùng và sửa master cũ | Báo lỗi trước commit; SKU/barcode/base unit/precision lịch sử giữ nguyên | G1 |
| AT05 | PO nhận từng phần, có reject và retry | Nhận vào quarantine; chỉ accepted available; trả rejected đúng receipt/roll/cost, không ghi đôi | G1 |
| AT06 | Hai cuộn cùng SKU có kg/m đo khác nhau | Primary quantity và measured secondary snapshot đúng từng cuộn; không áp hệ số chung ngầm định | G1 |
| AT07 | Fixture nhận 100 m, cấp 80 m, trả 5 m, tiêu hao 75 m | Cuối cùng 25 m nguyên liệu chưa tiêu hao; 75 m có production source; trả đúng layer, không cộng giá mới tùy ý | G1 |
| AT08 | Hai process tranh lượng nguyên liệu cuối | Chỉ command đủ lượng commit; không âm on-hand/reserved/roll; response mất được replay | G1 |
| AT09 | Kiểm kê khi có production hold | Freeze/snapshot và variance policy hoạt động; thiếu vật lý dưới hold tạo conflict xử lý có quyền | G1 |
| AT10 | Upgrade từ dữ liệu Retail/Purchasing cũ và DDL dở | Tiếp tục an toàn, IDs/số dư/layers/holds đúng; không seed lại tồn | G1 |
| AT11 | Forecast 100 chiếc, đơn thật 40 trong cửa sổ consumption | Theo fixture policy, forecast còn 60 và tổng nhu cầu 100, không thành 140 | G2 |
| AT12 | Sửa BOM sau release; planner ghi cùng version | Lệnh cũ giữ snapshot; thay đổi qua impact/approval; update stale bị conflict | G2 |
| AT13 | Phân bổ đơn cha cho hai xưởng | Tổng theo từng màu/size không vượt demand/tolerance; MRP giữ source và due date | G2 |
| AT14 | Bó 50 tách 20 và 30 rồi bàn giao/merge | Tổng lượng và genealogy đúng; một lượng không vừa in-transit vừa available hai nơi | G3 |
| AT15 | Bó 50 có 48 đạt và 2 sửa, scan/reinspection lặp | Sau 2 chiếc đạt, tổng output tốt tối đa 50; rework không nhân output hoặc công theo cùng source | G3 |
| AT16 | QC hold trước dùng/giao và lỗi phát hiện sau giao | Hold chặn thao tác tương ứng; sau giao tạo exception/trace tác động, không xóa lịch sử | G3 |
| AT17 | Fixture material cost 3.750.000 và conversion 250.000 đồng | Tổng cost output, WIP cuối, scrap/variance theo policy bằng 4.000.000; residual làm tròn giải thích được | G3 |
| AT18 | Gửi gia công 50, nhận 48 và 2 scrap được duyệt, khi thuộc phạm vi pilot | Custody/in-transit và quyết toán không còn 2 chiếc vô chủ; retry không nhân receipt/phí | G3 nếu áp dụng |
| AT19 | Nhập FG, đóng carton và giao nhiều lần | Nội dung carton/variant khớp accepted quantity; giao cộng dồn không vượt phần được phép | G3 |
| AT20 | Chi phí đến muộn sau nhập/giao một phần | Provisional/final và adjustment versioned có lineage; không sửa đè posted issue/layer | G3 |
| AT21 | Cùng key khác payload; revoke trước retry | Conflict hoặc từ chối quyền đúng; cùng ý định đã commit không ghi lần hai | G3 |
| AT22 | Crash giữa stock/production/outbox và consumer nhận lặp | Commit đầy đủ hoặc rollback; retry/reconcile không tạo nửa giao dịch hoặc hiệu ứng trùng | G3 |
| AT23 | Tắt optional adapter hoặc mapping sai | Trạng thái chưa kết nối/actionable; shared/adapter đã chọn nhưng mất readiness phải fail closed | G3 |
| AT24 | Native pause, disabled và active lifecycle | Pause enabled chặn ghi và cho GET an toàn; disabled/lifecycle giữ gate hiện có; không bypass bằng owner | G4 |
| AT25 | Drain/upgrade/recovery khi còn WIP | Đợi transaction/checkpoint, giữ WIP/release cũ và tiếp tục được; không yêu cầu WIP bằng 0 | G4 |
| AT26 | Đổi xưởng khi request cũ trả chậm, reload sau commit | UI bỏ response context cũ; command gốc được tra/replay; journal không giữ payload nhạy cảm | G3 |
| AT27 | Cutover opening cuộn, WIP, hàng ở đối tác và PO mở | Có source/version, chống import lặp; đối soát lượng và giá trị trước mở writer | G4 |
| AT28 | Restore DB/files/outbox/command/print | Khôi phục tới checkpoint nhất quán; không mất posted operation hoặc tạo lại nghiệp vụ đã hoàn tất | G4 |
| AT29 | Load/soak hai xưởng pilot, rồi từng cụm mở rộng và hot warehouse | Đạt profile/SLO tương ứng trước mở writer thật hoặc thêm cụm; không cross-scope, âm tồn hoặc backlog không phục hồi | G4 rồi G5 |
| AT30 | Payroll/Accounting import và correction | Source link idempotent, đúng quyền, khóa kỳ và adjustment; không tự publish/post | G6 |

Các scenario xuyên công đoạn được chạy lại ở API/UI/pilot tương ứng, không chỉ service test. Nếu pilot chưa có gia công ngoài, G0 ghi quyết định deferred cho G3-06/AT18 và điều kiện bật lại; không ghi pass cho scenario chưa thực hiện. Test có cùng fixture chồng lặp không được cộng thành số full-suite test độc lập.

### 14.3. Chiến lược kiểm thử

- Unit: BOM expansion, forecast consumption, quantity/UOM, routing, cost allocation, split/merge và state transitions.
- Feature/API: native grants, workflow, private artifacts, idempotency, readiness và optional adapters.
- MySQL bằng process độc lập: tranh stock/bundle, partial issue/return, stocktake, drain/lease và crash/recovery. SQLite không thay bằng chứng lock.
- Browser: deep link, context reset, matrix/drawer validation, scanner keyboard, printer/reprint semantics và toàn hành trình pilot.
- Upgrade/fresh: subprocess production-like, module migration hiện hành/lịch sử, partial DDL và application rollback tương thích schema mở rộng.
- Regression: auth/RBAC/foundation, Merchandise, Inventory v2, Purchasing, Retail/Customers/FNB liên quan; build Admin. Full suite chạy trên release candidate cố định; từng failure được phân loại.

## 15. Vận hành và chỉ tiêu đo

### 15.1. Định nghĩa nghiệp vụ

| Chỉ tiêu | Định nghĩa cần khóa |
| --- | --- |
| Output tốt | Lượng được chấp nhận tại checkpoint xác định, phân biệt operation output và finished garment |
| WIP và WIP aging | Lượng tại trạng thái/custodian cụ thể; thời gian từ entry hoặc handover, tách hàng đang đi |
| First pass yield | Số đơn vị đạt lần kiểm đầu chia số đơn vị kiểm lần đầu; không dùng số defect làm số sản phẩm lỗi |
| Hiệu suất chuyền | Earned minutes từ output/công đoạn hợp lệ chia actual direct labor minutes theo policy; lưu revision định mức |
| Tiêu hao nguyên liệu | Actual consumption so với BOM snapshot cùng UOM; allowance, remnant, scrap và variance tách riêng |
| Đúng hạn | Theo đợt giao, thời điểm và lượng đã cam kết; thay đổi cam kết có lịch sử |
| Giá thành | Material actual cộng conversion được duyệt, phân bổ output/WIP/scrap/variance; hiện rõ provisional/final |

Standard minutes phục vụ kế hoạch/hiệu suất; rate và earning có policy riêng. KPI đã công bố phải có numerator/denominator, timezone, cut-off, revision và source có thể drill-down.

### 15.2. Capacity và vận hành

G0 ghi profile gồm địa điểm, chuyền, người ghi đồng thời, peak scans/giây, bundle/ngày, số event, file volume, retention và WAN outage. Sau đó chốt mục tiêu p95/p99, error rate, queue lag/recovery và RPO/RTO. Các con số là mục tiêu kiểm thử của deployment, chưa phải năng lực đã đạt.

Ưu tiên transaction ngắn, khóa theo stock/order/bundle có thứ tự, index theo scope và thời gian, keyset pagination, report projections và job chunked có checkpoint. Load test có cả query báo cáo cùng hot SKU/warehouse; không chỉ nhiều request đọc độc lập. Đề xuất thay đổi khóa cấp kho phải giữ invariant permission, stocktake và lifecycle bằng competing-process test.

Scheduler/worker cần theo dõi queue lag, failed jobs, stuck command, expired worker lease, outbox/inbox retry, receipt chờ QC, handover quá hạn, cost chưa reconcile và private disk. Timeout worker/job phải thấp hơn retry visibility theo chính sách deployment; lease và dedup không thay cấu hình đúng.

Native maintenance pause là trạng thái app khi module vẫn enabled và không có active lifecycle operation; cho GET an toàn theo quyền và chặn mutation. Module disabled/uninstalled giữ 404. Generic `module.enabled` hiện cũng chặn khi active lifecycle; kế hoạch không mặc định có thể đọc suốt DDL upgrade. Maintenance window và read-safe endpoint nếu cần được thiết kế riêng.

### 15.3. Cutover và phục hồi

1. Kiểm installed versions/schema/capability và exact phạm vi; chụp baseline dữ liệu và writer/job đang chạy.
2. Preview import/mapping rồi rehearsal trên database cô lập; giải quyết trùng identity, sai shade/UOM/owner và cost thiếu nguồn.
3. Backup DB và file private, chọn cut-off và dừng writer cũ trong phạm vi chuyển đổi. Hàng/WIP còn mở phải có opening event và nguồn, không giả đơn mới hoàn thành để tạo số dư.
4. Nâng core/module qua lifecycle, đối soát trước mở lại writer; capability mới chỉ ready sau migration và kiểm tra mapping.
5. Khi lỗi, pause ghi, giữ command IDs và outbox, xác định transaction đã commit trước retry. Rollback application chỉ dùng phiên bản tương thích schema hiện hành; không rollback/xóa ledger production.
6. Restore sau khi đã có ghi thật cần đối soát các sự kiện phát sinh sau checkpoint và external acknowledgement. Không restore DB riêng lẻ rồi tiếp tục phát lại integrations mà bỏ qua file/outbox/inbox/cost lineage.

## 16. Phân công và ước lượng

### 16.1. Trách nhiệm

| Vai trò | Trách nhiệm |
| --- | --- |
| Product Owner và quản lý sản xuất | Chốt quy trình, ưu tiên, quy mô và UAT |
| Tech Lead | ADR, contract, boundary, migration, security và release gate |
| Backend nền tảng | Merchandise/Inventory/Purchasing, context và tương thích consumer |
| Backend Manufacturing | Engineering/planning/execution/QC/cost và adapters |
| Frontend | Workspace, matrix, drawer, hiện trường, scanner và lỗi/retry |
| QA | Fixture có đáp án, regression, MySQL concurrency, E2E và pilot evidence |
| Kho, QC và cost control | Đối soát lượng/giá trị, nghiệm thu policy và ngoại lệ |
| Operations | Thiết bị/mạng, worker, backup/restore, monitoring và hỗ trợ |

### 16.2. Dải thời gian tham chiếu

Ước lượng ban đầu dùng nhóm hai backend, một frontend, một QA; Tech Lead/Product Owner và Operations tham gia thường xuyên theo phần việc. Đây là dải lập kế hoạch cho phạm vi G0–G5, cần ước lượng lại sau G0 bằng backlog và dữ liệu thực tế.

| Giai đoạn | Dải tuần lịch nếu xếp tuần tự | Phần có thể song song |
| --- | ---: | --- |
| G0 | 1–2 | UX, khảo sát và fixture |
| G1 | 4–6 | Core/context, shared stock và UI shell theo contract |
| G2 | 4–6 | Master kỹ thuật/demand khởi động sớm sau G0; stock/MRP chờ G1 |
| G3 | 4–6 | Frontend/API theo vertical slice, QA theo từng command |
| G4 | 2–3 tối thiểu cho chuẩn bị và pilot | Thời gian thực tế còn phụ thuộc chu kỳ đơn sản xuất |
| G5 | 2–3 tối thiểu cho rollout theo cụm | Mỗi cụm chờ đủ bằng chứng ca/chu kỳ và SLO |

Tổng cộng tuần tự khoảng **17–26 tuần**, chưa gồm chờ chu kỳ đơn dài hơn cửa sổ pilot, phần mềm/thiết bị bên ngoài, hoặc các gói G6. Có thể rút critical path bằng các phần song song sau khi contract ổn định; không suy tổng duration chỉ bằng cộng tuần-người hoặc tăng agent. Không gán ngày hoàn thành khi chưa có staffing và lịch pilot.

## 17. Thứ tự work package và việc bắt đầu

| Gói | Phạm vi bàn giao | Phụ thuộc |
| --- | --- | --- |
| WP01 | ADR/data dictionary, fixture và test matrix G0 | Bắt đầu trước thay đổi schema |
| WP02 | Manufacturing package, native bootstrap/access và workspace shell | WP01 context/security |
| WP03 | Command/runtime/outbox/private storage | WP02 và contract WP01 |
| WP04 | Merchandise variants/UOM và upgrade compatibility | WP01 identity |
| WP05 | Inventory grain/roll/QC và Purchasing receiving/return | WP04 và WP01 stock/QC |
| WP06 | Production reservation/issue/return/transformation/stocktake/cost provenance | WP05 |
| WP07 | Engineering/BOM/routing/sample/release | WP04; có thể phát triển cùng WP05–WP06 |
| WP08 | Demand/forecast/MRP/requisition/calendar/allocation | WP07; integration chờ WP05–WP06 |
| WP09 | Cutting/bundle/operation/handover/QC/subcontract | WP03, WP06–WP08 |
| WP10 | FG/carton/shipment/cost run/dashboard | Các slice WP09 và stock/cost contracts |
| WP11 | Full regression, cutover, restore, hai xưởng pilot | WP10 và các gate G4 |
| WP12 | Load/soak, mở theo cụm và capacity report | WP11 |

Work package có thể chia thành nhiều PR vừa review được; mỗi PR chạm writer có test transaction và quyền tương ứng. Frontend đi cùng API có contract, không xây tất cả màn bằng mock rồi hoãn kiểm tích hợp tới pilot.

**Việc bắt đầu tiếp theo:** thực hiện WP01 với mẫu đơn/BOM/phiếu thực tế và fixture cô lập, chốt các ADR làm đầu vào WP02–WP06. Sau đó tạo scaffold app có kiểm quyền và lifecycle, trước khi phát triển các hành trình vật tư và sản xuất. Tài liệu này không tự cài module hoặc thay đổi installation nghiệp vụ.

## 18. Tài liệu liên quan

- [Bootstrap AIO](../ai-session-bootstrap-prompt.md).
- [Kiến trúc access control](admin-access-control.md); đối chiếu source khi phần scope/context trong tài liệu cũ chưa cập nhật.
- [Retail implementation status](retail-pos-implementation-status.md) và [Retail runtime contract](../api/retail-pos-runtime-contract.md).
- [Customers](customers-module.md) và [Customers runtime contract](../api/customers-runtime-contract.md).
- [HRM và Payroll](hrm-and-payroll-modules.md).
- [AccountingTax](accounting-tax-module.md).
- [ERPNext Work Order](https://docs.frappe.io/erpnext/work-order) mô tả BOM, công đoạn, WIP và work order.
- [ILO Industrial Engineering](https://www.ilo.org/resource/article/ilo-promotes-industrial-engineering-catalyst-productivity-improvements) mô tả SMV, throughput và line balancing trong ngành may.
- [GS1 SSCC](https://www.gs1.org/standards/id-keys/sscc) định danh đơn vị logistics khi khách hàng yêu cầu áp dụng chuẩn.

Các nguồn nghiệp vụ cung cấp bối cảnh; task, capability và release gate trong kế hoạch là thiết kế cho AIO, không phải chức năng đã được các module hiện tại bảo đảm.
