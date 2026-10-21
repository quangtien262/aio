# Trạng thái triển khai Quản lý xưởng may

Ngày đối chiếu **21/10/2026**, package `manufacturing` **`0.1.0-dev.1`**. Đã có app online với API, dữ liệu thật qua service, 12 workspace Admin và hành trình FOB từ đơn hàng tới giao thành phẩm. Chưa cài hoặc migrate vào database nghiệp vụ `aio`; thử nghiệm dùng SQLite và MySQL scratch cô lập.

Đọc [runtime contract](../api/manufacturing-runtime-contract.md) để bảo trì API/cài đặt và [kế hoạch triển khai](manufacturing-implementation-plan.md) để đối chiếu gate. Các gate pilot và capacity cần bằng chứng vận hành, không được suy từ số màn hình hoặc số test.

## 1. Phần đã có

| Miền | Chức năng chạy được |
| --- | --- |
| Nền tảng | Module lifecycle, native company/factory grants, bootstrap không lệ thuộc website, pause/drain, optimistic version, idempotency, audit, outbox/inbox, private documents |
| Kỹ thuật | Style, size/màu, Merchandise variants, BOM theo variant/allowance, routing/SMV, measurement, revision, mẫu PP và duyệt; impact report đọc lệnh/hold/WIP/PO ứng viên |
| Kế hoạch | Đơn thật/forecast/replenishment, forecast netting, chia nhu cầu theo lệnh/nhà máy, release snapshot, MRP, requisition provenance, calendar ngày/chuyền, cảnh báo tải, commit/freeze/reschedule và lịch sử |
| Vật tư FOB | Roll/lot/shade/khổ và đo phụ, quarantine/QC/reject, mua/duyệt/nhận từng phần, trả NCC, giữ/cấp/trả dư, remnant, split/transfer vật lý và kiểm kê qua Inventory contract |
| Điều hành | Cắt theo variant, actual consumption/variance approval, recut reference, dùng vật tư đã cấp, bó/tách/gộp, routing từng phần, operation scrap, bàn giao/nhận giữa nhà máy, WIP |
| Chất lượng/gia công | Inline/end-line/final full-count, defects/measurements, manual hold/release, rework không nhân output; gửi/nhận gia công và phí chỉ tính phần nghiệm thu đạt |
| Thành phẩm | Nhập FG có actual cost, carton/variant/roll contents, hủy packed shipment để repack, giao từng phần, đồng bộ fulfillment với stock outbound |
| Giá thành | Approved labor/overhead/subcontract, provisional/final cost, output/WIP reconciliation, late conversion adjustment sau xuất một phần; giữ nguyên posted history và tính đúng giá issue tiếp theo |
| Giao diện | 12 page tiếng Việt, form Drawer có cấu trúc, search/phân trang, context reset, pending command recovery, private attachments, QR nội bộ/in phiếu, monitor command/outbox |

## 2. Ranh giới kiến trúc

Manufacturing sở hữu nghiệp vụ may, không tạo stock ledger hoặc supplier ledger thứ hai. Merchandise sở hữu SKU/variant/UOM; Inventory sở hữu lượng vật lý, FIFO layers và valuation adjustments; Purchasing sở hữu PO/receipt/return/nghĩa vụ mua. Enterprise context và native memberships không thêm Core scope mới. Hàng xưởng này không đọc/ghi bằng website grant của xưởng khác.

WIP quantity là projection giao dịch từ bundle quantity trừ finished/scrap, có forward backfill và index theo factory. Read chính preload child/reference để tránh query theo từng dòng; báo cáo lượng aggregate tại DB theo UOM. Đây là hướng mở rộng trong source, chưa chứng minh throughput của 10 nhà máy.

Các adapter Customers/HRM/Payroll/Accounting/Catalog/Retail chưa được công bố sẵn sàng. Generic outbox có audit consumer thật; không giả trạng thái connected hoặc tự ghi ra các app optional.

## 3. Bằng chứng kiểm thử

SQLite khóa logic/API; MySQL process độc lập khóa tranh chấp và lifecycle; browser dùng fixture riêng với API thật. Không cộng các nhóm test chồng lặp thành số full-suite độc lập.

| Nhóm | Kết quả snapshot | Phạm vi |
| --- | --- | --- |
| Focused PHP tích hợp và regression | **204 tests / 2.297 assertions, PASS** | AuthSplit, AccessControl, AdminFoundation; Manufacturing gồm native login/shell/2FA/password; Inventory/Merchandise/Purchasing; Retail và bridge liên quan. |
| MySQL Manufacturing | **5 gates / 17 assertions, PASS** | Hai process cùng key/tranh stock, revoke chờ giao dịch, pause, drain/disable/upgrade khi còn WIP. Scratch DB đã xóa và xác minh. |
| MySQL shared stock/cost | **7 gates / 20 assertions, PASS** | Fresh production, last stock/replay, cost input/output, late valuation/variance, split/transfer/return, DECIMAL6 và residual. Scratch DB đã xóa và xác minh. |
| JavaScript | **73 tests, PASS** | Toàn bộ UnitJavaScript, gồm journal/context và form contract Manufacturing. |
| Admin build | **PASS** | Còn cảnh báo chunk lớn của admin UI dùng chung; chưa phải phép đo capacity. |
| Browser cô lập | **3 hành trình PASS** | Kỹ thuật/PP/private document upload-download; lịch–sản xuất–QC–FG–đóng gói–giao hàng–chốt giá thành; guest đăng nhập bằng credentials thật rồi đổi mật khẩu bắt buộc và vào xưởng được cấp khi mọi site inactive. Dùng API thật trên SQLite riêng. |
| Toàn AIO | **1.136 tests / 29.148 assertions; 30 errors, 49 failures** | Không có failure ở Manufacturing/Auth/Inventory/Purchasing/Retail trong lượt chạy này. Cả **79/79** failing cases đã tái hiện trên HEAD độc lập, cùng loại lỗi, vị trí assertion và điều kiện thất bại. Không công bố toàn suite xanh. |

Full suite dùng JUnit riêng để phân loại, không đọc cache defects lịch sử như kết quả hiện tại. Baseline HEAD `09427213` được archive với autoload audit: 14 process, không có class App/Modules/Tests/Themes/Database lấy từ checkout đã thay đổi. Test source của 79 case giống HEAD. Fresh refresh hiện dừng ở migration FNB `000014` forward-only; lỗi này cũng tái hiện ở HEAD. Các test không thay UAT thiết bị, restore drill hoặc load/soak hai đến mười xưởng.

[Bảng đối chiếu từng lỗi với HEAD](../validation/manufacturing-baseline-2026-10-21.json) giữ test identity, lỗi/expected condition và source đã chuẩn hóa đường dẫn; không lưu toàn bộ HTML response.

Nguồn kiểm chứng: [workflow](../../tests/Feature/ManufacturingWorkflowTest.php), [foundation/security/documents/planning](../../tests/Feature/ManufacturingFoundationTest.php), [fresh install](../../tests/Feature/ManufacturingFreshInstallTest.php), [MySQL Manufacturing](../../tests/Support/manufacturing-mysql-gates.php), [Inventory production](../../tests/Feature/InventoryProductionTest.php), [Purchasing production](../../tests/Feature/PurchasingProductionTest.php), [MySQL shared stock](../../tests/Support/inventory-production-mysql.php), [browser](../../tests/browser/manufacturing.spec.js), [JavaScript](../../tests/UnitJavaScript/manufacturing-workflows.test.js).

Bổ sung UI cùng ngày: logo header về `/admin` để khởi tạo lại Core shell; cảnh báo readiness và runtime paused mở modal hướng dẫn theo quyền, có kiểm tra lại và biểu mẫu tiếp tục vận hành cho người đủ quyền. App Store mở tab riêng, không tự cài hay bật app khi mở cảnh báo. Kiểm thử riêng cho thay đổi này: Foundation **30 tests / 260 assertions**, JavaScript Manufacturing + workspace navigation **16 tests**, build đạt; [browser remediation](../../tests/browser/manufacturing-guidance.spec.js) đạt ở desktop và viewport 390 px trên SQLite cô lập. Các trạng thái thiếu capability/quyền/lỗi kiểm tra được mô phỏng response bootstrap trong browser; quyền App Store thực được kiểm tại Foundation API. Đây không phải lượt chạy lại toàn suite hoặc xác nhận cài đặt DB nghiệp vụ.

Điều hướng cùng ngày được gom từ 12 link thành 5 mục chính theo luồng Chuẩn bị → Sản xuất → Thành phẩm, cùng Tổng quan và Thiết lập. Quyền/route giữ nguyên, nhóm trống ẩn, mobile tự mở nhóm đang chọn. **22 tests JavaScript** (Manufacturing, grouping, shared navigation và FNB navigation), build và [browser navigation](../../tests/browser/manufacturing-navigation.spec.js) tại 1047 px + 390 px đạt trên SQLite riêng; không chạy lại toàn PHP suite cho thay đổi menu này.

Trang Thiết lập được thiết kế lại với tổng quan nhà máy, bốn thẻ số liệu theo phạm vi, bốn nhóm thiết lập nội bộ và sơ đồ phân xưởng–chuyền. Component [ManufacturingSettings](../../resources/admin/src/modules/manufacturing/components/ManufacturingSettings.jsx) và CSS riêng giữ command drawer/quyền hiện hữu. Lượt kiểm tra UI riêng: build, **17 tests JavaScript**, [hành trình Thiết lập](../../tests/browser/manufacturing-settings.spec.js) desktop 1047 px/mobile 390 px và hành trình kỹ thuật/PP/tài liệu của browser Manufacturing đạt trên SQLite cô lập. Có tạo nhà máy mã NM tự động, phân xưởng, chuyền và ca thật trong fixture; mở phân quyền/pause mà không xác nhận pause. Chưa chạy lại toàn PHP suite cho thay đổi giao diện này.

## 4. Gate còn mở

| Phạm vi | Điều kiện còn lại |
| --- | --- |
| Quy trình thực tế | Chốt mẫu phiếu, chính sách duyệt và tolerance, sơ đồ chuyền/ca, dữ liệu đầu kỳ và người nghiệm thu; tài chính hiện theo VND, chưa có FX/export-customs workflow |
| Kỹ thuật/planning nâng cao | Approved substitute materials, engineering change áp dụng có kiểm soát vào WIP cũ, routing có nhánh/hội tụ, lay/ply/marker CAD và tối ưu finite capacity |
| Ngoại lệ kho/sản xuất | Bộ reversal tổng quát kiểm downstream usage, chênh lệch mất/hỏng khi bàn giao, short-close/scrap settlement, trace/recall và returns sau giao hoàn chỉnh |
| Cutover | Import preview đối soát opening WIP/PO/subcontract/FG trên dữ liệu thật; rehearsal legacy/partial DDL toàn installation thực tế |
| Vận hành | Worker/scheduler, quyền private disk, backup DB+files và restore drill, retention, thiết bị in/quét thực, WAN/outage và hỗ trợ ca |
| Capacity | Chốt profile, load/soak hai xưởng rồi từng cụm 2→5→10, đo contention kho/SKU/order, p95/p99, queue recovery và RPO/RTO |
| G6 | Customers/HRM/Payroll/Accounting/Retail adapters, CMT/customer-owned stock, offline/site authority, IoT/RFID, buyer/vendor portal và AI |

G0–G3 có phần hiện thực tương ứng trong bảng chức năng, còn các ngoại lệ/gate nêu trên. **G4/G5 chưa nghiệm thu**: chưa có pilot xưởng thật hoặc capacity report. Phạm vi nâng cao và adapter không được bật bằng cách bỏ qua readiness.

## 5. Source map

- Module: `modules/Manufacturing/module.json`, `routes.php`, hooks và migrations 400000–700000.
- Entry API: `Http/ManufacturingController.php`, `Services/ManufacturingService.php`.
- Domain: `Services/Engineering`, `Planning`, `Execution`, `Quality`, `Fulfillment`, `Costing`.
- Quyền/runtime/replay: `Services/Access`, `Runtime`, `Commands`.
- Read/private/worker: `Services/Read`, `Documents`, `Commands/ManufacturingOutboxService.php` và `routes/console.php`.
- Shared contracts: MerchandiseVariantService, InventoryProductionService/InventoryValuationService, PurchasingProductionService.
- Admin: `resources/admin/src/modules/manufacturing/`; nhánh native trong AdminLayout và ModuleRoutePage giữ các thay đổi FNB đang có.

Sau khi nghiệm thu deployment, cập nhật trạng thái theo release/schema cụ thể; không sửa checklist kế hoạch thành pass dựa trên tài liệu này.
