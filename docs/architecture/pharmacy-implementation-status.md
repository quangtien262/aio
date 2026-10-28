# Trạng thái triển khai Nhà thuốc

Đối chiếu ngày **22/10/2026**, package **`pharmacy 0.1.0-dev.1`**. Đã có bản nội bộ chạy đầu đến cuối cho nhà thuốc bán lẻ/chuỗi, API Laravel, 18 khu vực giao diện React và các adapter nội bộ. **Chưa cài/migrate vào database nghiệp vụ, chưa nghiệm thu pilot hoặc production.** Kiểm thử sử dụng dữ liệu tổng hợp trên SQLite và MySQL scratch; không gửi dữ liệu đến dịch vụ thật.

Đọc [runtime contract](../api/pharmacy-runtime-contract.md) để cài đặt/bảo trì, [kế hoạch](pharmacy-implementation-plan.md) để theo dõi phạm vi toàn app và [bằng chứng kiểm thử](../evidence/pharmacy/README.md) để đối chiếu snapshot. Kế hoạch có cả hạng mục chưa triển khai; không đánh dấu tất cả task hoặc G0–G10 đã đạt.

## 1. Đã triển khai trong source

| Miền | Hành vi hiện có |
| --- | --- |
| Nền tảng | Module install/enable/upgrade, dependency/capability, native đăng nhập/2FA/đổi mật khẩu, chain/pharmacy/counter, membership giao với quyền Core, qualification có chứng cứ xác minh thủ công, optimistic version, idempotency, audit/outbox/inbox |
| Danh mục/giá | Liên kết Merchandise SKU và Inventory item, hoạt chất có cấu trúc, dạng/hàm lượng/đường dùng, đơn vị/quy cách và giá theo quy cách; parser GS1 giới hạn; import preview/commit tối đa 100 sản phẩm mới, phát hành giá nhiều cơ sở với version từng điểm |
| Người bệnh/đơn | Hồ sơ và chứng cứ xử lý mã hóa, tra chính xác số điện thoại qua blind index, xác minh đơn thủ công, giới hạn lượng đã cấp/đang giữ, chuyển quyền đọc giữa cơ sở theo consent có thể thu hồi; không tự gộp theo điện thoại |
| Cấp thuốc | OTC/Rx thông thường: draft → chọn đúng lô → prepared → duyệt chuyên môn → finalized → handed_over; recheck quyền, qualification, hồ sơ/đơn, giá, quy cách, tồn/lô/hold và review trước thu tiền; ảnh chụp thông tin duyệt được giữ riêng |
| Tiền/ca | Ca của đúng người/quầy/cơ sở, tiền mặt/chuyển khoản được ghi nhận thủ công, tiền thừa, thu/chi có lý do, chốt ca/chênh lệch, nhận trả cách ly và hoàn tiền riêng có giới hạn; giao thất bại giữ nguyên nghĩa vụ, không tự hoàn tồn/tiền/lượng đơn |
| Kho/chất lượng | Inventory ledger duy nhất; nhập cách ly, kiểm nhận bởi người đủ điều kiện, multi-reason holds, chuyển tình trạng, hủy, trả NCC, thu hồi toàn chuỗi kể cả cơ sở mở sau; trace/reconcile/đóng hồ sơ thu hồi với chứng cứ, giữ chặn lô sau đóng |
| Mua/chuỗi | Purchasing supplier/PO/duyệt/nhận từng phần/trả có nguồn, gợi ý đặt hàng từ dữ liệu đã giao; điều chuyển cùng pháp nhân draft → người khác duyệt → xuất → người khác nhận từng phần vào cách ly; kiểm kê toàn kho có maker/checker |
| Công việc | Phân công có kiểm tra quyền/chuyên môn, người mua/người bệnh/người nhận riêng, tư vấn có snapshot mã hóa, hàng đợi, ca, công việc thu hồi và recovery reservation/quyền bị thu hồi |
| Tài liệu | Upload gốc private, nhãn cấp thuốc/phiếu giao/biên nhận tiền, xác minh source khi in lại; CSV có chống công thức, TTL và kiểm lại quyền tải; không trả hướng dẫn dùng thuốc cho người chỉ được xem tài chính |
| Báo cáo | Doanh thu, hoàn, ca, chờ giao, holds/outbox và bảng HQ chỉ tổng hợp các cơ sở người dùng có quyền báo cáo; lọc ngày theo múi giờ cơ sở, phân trang tối đa 100 |
| Tích hợp | Mapping tường minh Customers theo website/book, chia sẻ thương mại có consent riêng; Accounting theo pháp nhân/item và quyền kế toán, chỉ tạo nháp; durable provider queue/reconcile với trạng thái uncertain, chưa đăng ký transport thực |
| UX | 18 khu vực nghiệp vụ tiếng Việt; quầy chọn đúng lô, phím tắt, drawer biểu mẫu, giá theo đơn vị bán, cảnh báo quyền/readiness, reset dữ liệu khi chuyển cơ sở; mobile 390 px, xác nhận giỏ đang sửa, journal chỉ lưu tham chiếu không lưu PHI |

Giao diện mở từ **`/admin/pharmacy/login`** hoặc **`/admin/modules/pharmacy`** khi module đã được cài/bật. Người chỉ có quyền báo cáo đi vào báo cáo; shell không gọi API lâm sàng để dựng trang đó. Có thể kiêm nhiệm tại cửa hàng nhỏ, nhưng qualification và những bước maker/checker được khai báo ở server vẫn áp dụng.

## 2. Ownership và đường mở rộng

Pharmacy giữ hồ sơ người bệnh/đơn/cấp thuốc, sale/tender/shift/return/refund; Merchandise giữ SKU/quy cách/barcode, Inventory giữ lượng/lô/FIFO/giá vốn duy nhất, Purchasing giữ PO/receipt/return mua. `App\Support\Pos\PosNumbers` chia sẻ phép tính số lượng/tiền và phân bổ deterministic với Retail; không chia sẻ cart, hồ sơ hoặc ledger Retail.

Inventory source lên **`0.5.0`**, capability `inventory.managed_stock.v1`; Purchasing lên **`0.2.2`**, capability `purchasing.managed_returns.v1`. Managed warehouse policy chặn writer generic, observer chỉnh kho/lô và replay không được ủy quyền. Adapter Purchasing cần đồng thời native Pharmacy và quyền shared module. Lifecycle không gỡ dữ liệu Pharmacy; disable bị chặn khi còn cấp thuốc hoặc tác vụ tích hợp cần xử lý. Snapshot permission/role theo phiên bản không tự biến System Owner thành người có chuyên môn.

Native chain/pharmacy không dùng website đang mở. Chỉ adapter Customers mới đổi context tạm sang website đã mapping và vẫn kiểm tra quyền website/book, sau đó khôi phục context. Accounting dùng quyền organization riêng. HRM/Payroll/Minvoice/CMS/Catalog là optional dependencies, **không đồng nghĩa đã có adapter nghiệp vụ hoàn chỉnh**.

Mỗi read có scope, giới hạn trang và query aggregate; bootstrap tính projection quyền theo request thay vì query từng permission ở từng cơ sở. Các lệnh vẫn kiểm tra quyền hiện hành trong transaction. Queue tách I/O ngoài transaction; idempotency, row locks và unique constraints xử lý retry/tranh chấp. Đây là kiến trúc có đường mở rộng, chưa phải bằng chứng SLA production.

## 3. Bằng chứng và giới hạn

Các nhóm dưới có phần chồng lặp; không cộng thành tổng test độc lập.

| Nhóm | Snapshot đã xác nhận |
| --- | --- |
| PHP tích hợp/regression | **215 tests / 5.830 assertions PASS** trong lượt focused, gồm Pharmacy, shared stock/Purchasing/Merchandise, Retail, Customers/Accounting và fresh install/routes. Lượt khóa cuối **75 tests / 568 assertions PASS**; sau sửa timezone và GTIN alias, Administration **9 tests / 98 assertions PASS**, gồm bốn mốc sát ranh ngày cho reader ủy quyền và generic. Các lượt có phần trùng, không cộng tổng. |
| MySQL Pharmacy | **3 gates / 13 assertions PASS**: tranh lô cuối, replay tiền cùng key, cùng đơn ở hai chi nhánh; process độc lập, xóa scratch xác nhận. |
| MySQL managed Inventory | **5 gates / 8 assertions PASS**: ownership/lifecycle và generic writer bị chặn; xóa scratch xác nhận. |
| Browser API thật | Hành trình trên Laravel/SQLite riêng: đăng nhập khi mọi website inactive, setup, bán/cấp/thu/giao/in/chốt ca, thu hồi qualification, người chỉ có quyền báo cáo, desktop/mobile và không lưu PHI/website context sai. |
| JavaScript/build/browser UI | **34 tests JavaScript, 6 browser UI tests và build PASS**; journal/context/quy cách/form mappings. Browser UI dùng response fixture; lượt Laravel API thật ghi riêng ở trên. Một cảnh báo chunk-size của bundle Admin chung còn tồn tại. |
| Toàn AIO | Snapshot **1.252 tests / 34.190 assertions, 1.170 pass, 52 failures và 30 errors**. **80/82** lỗi tái hiện trên HEAD `f4a9333b` độc lập. Hai lỗi mới (Inventory version expectation và route inline) đã sửa và kiểm tra riêng. Chưa công bố toàn suite xanh. |

Các lỗi baseline gồm 30 errors dữ liệu/theme preset/slug, 48 assertions CMS/theme/preview, một assertion precision Retail cũ và một rollback migration FNB forward-only. Phân loại so sánh từng case dùng checkout archive, autoload tách khỏi source đang thay đổi; không dựa vào cache PHPUnit lịch sử. Lượt focused không thay cho full-suite rerun hoặc UAT nghiệp vụ thật.

Benchmark service MySQL cục bộ, cùng **4 workers**, mỗi điểm có actor/quầy/ca/kho riêng, 2 giao dịch OTC mỗi điểm:

| Dữ liệu | Giao dịch / assertions | p50 / p95 / max toàn chu kỳ (ms) | Giao dịch/giây trong cửa sổ hoạt động |
| --- | --- | --- | --- |
| 10 điểm | 20 / 68 | 417,621 / 505,761 / 516,648 | 8,132 |
| 50 điểm | 100 / 268 | 433,709 / 595,973 / 663,523 | 8,847 |
| 100 điểm | 200 / 518 | 448,274 / 581,143 / 698,959 | 8,602 |

Máy Apple M1 Pro 10 cores/32 GiB, PHP 8.5.5, MySQL 8.0.44, InnoDB buffer 128 MiB. Kiểm khớp tiền, exact lot, handover, ca, tồn cuối và reserved 0; scratch đã xóa. **100 điểm dữ liệu với 4 luồng service không phải 100 điểm hoạt động đồng thời**, HTTP/WAN, hot warehouse/SKU, mix Rx/return/report, soak hay capacity production. Xem JSON cho thời gian setup/boot và điều kiện chạy.

## 4. Những phần chưa hoàn thành toàn kế hoạch

| Phạm vi | Công việc còn lại trước khi công bố |
| --- | --- |
| Nghiệp vụ/pilot | Dược sĩ và chủ cơ sở duyệt chính sách, qualification, loại hình/giấy phép, mẫu phiếu, đơn có hiệu lực/cấp từng phần, xử lý ngoại lệ và thời hạn lưu. Config validity hiện là chính sách kỹ thuật mặc định, không phải kết luận pháp lý. |
| Phạm vi sản phẩm | Schema hiện dùng batch/expiry cho sản phẩm Pharmacy; cần hoàn thiện phân loại hàng không phải thuốc, nhóm chứng từ chuyên biệt, chính sách giảm giá/khuyến mại, thay thế thuốc được duyệt và bộ ngoại lệ/chênh lệch tổng quát. |
| Provider ngoài | CSDL dược và đơn thuốc điện tử là hai adapter riêng; cần contract, tài khoản sandbox, mapping/version, test reconcile và nghiệm thu từng nhà cung cấp. Thanh toán/hóa đơn điện tử chưa có transport thực hoặc tự xác nhận đã nhận tiền/đã phát hành. |
| Gói nâng cao | Thuốc kiểm soát đặc biệt, chuỗi lạnh/IoT, bán online và tri thức/tương tác thuốc **chưa triển khai đầy đủ, bị chặn server**. Đổi cờ config không mở được luồng; không có tự chẩn đoán/đổi thuốc. |
| Cutover | Import danh mục mới đã có; chưa có migration dữ liệu thật toàn bộ tồn đầu/PO/đơn đang cấp, mapping legacy và rehearsal/rollback toàn installation. |
| Vận hành/bảo mật | UAT với người dùng và thiết bị in/quét thật, chiến lược key rotation/reindex phone, privacy/retention được phê duyệt, backup DB+private files+keys+checkpoints và restore drill RPO/RTO, worker/scheduler/monitoring. |
| Tải/rollout | Chốt profile/SLO, HTTP/WAN, kho/SKU nóng, tải đồng thời và soak, outage/recovery; pilot 2–3 điểm rồi tăng wave 10→50→100 theo gate. |

Không đánh dấu R2/R3/R4 hoặc G5–G10 đạt chỉ từ bộ source này. Phần R1 có triển khai và kiểm thử nội bộ như bảng trên; nghiệm thu G0–G4 vẫn cần đối chiếu đầy đủ ma trận trong kế hoạch và chủ nghiệp vụ.

## 5. Source map

- Module, migrations và permission snapshot: `modules/Pharmacy/module.json`, `database/migrations/`, `Security/`, `Hooks/`.
- API và dispatch: `modules/Pharmacy/routes.php`, `Http/`, `Services/PharmacyService.php`.
- Domain: `Services/Setup`, `Prescriptions`, `Dispensing`, `Sales`, `Operations`, `Quality`, `Purchasing`, `Workflow`, `Administration`.
- Isolation/recovery: `Services/Access`, `Commands`, `Runtime`, `Integrations`, `Documents`, `Jobs`.
- Shared: `app/Support/Pos`, `modules/Inventory/Contracts`, `Services/InventoryManagedStockService.php`, `Observers`, `modules/Purchasing/Services/PurchasingManagedStockService.php`.
- UI: `resources/admin/src/modules/pharmacy/`, nhánh native tại AdminLayout/ModuleRoutePage và routes chung.
- Test: `tests/Feature/Pharmacy*`, `InventoryManagedStockTest`, `tests/Support/pharmacy-*`, `inventory-managed-mysql.php`, `tests/browser/pharmacy-*`, `tests/UnitJavaScript/pharmacy-*`.

Sau mỗi đợt nghiệm thu, cập nhật release/schema và evidence thực tế; không suy trạng thái DB hiện tại từ ngày bàn giao source.
