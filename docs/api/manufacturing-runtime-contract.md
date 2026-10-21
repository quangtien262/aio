# Contract runtime Quản lý xưởng may

Ngày đối chiếu: **21/10/2026**. Package `manufacturing` ở `0.1.0-dev.1`, workspace online `/admin/modules/manufacturing`. Phạm vi hiện thực là FOB/nhãn hàng riêng, hàng tự sở hữu, nhiều nhà máy cùng doanh nghiệp. Trạng thái nghiệm thu và giới hạn nằm trong [implementation status](../architecture/manufacturing-implementation-status.md); kế hoạch mục tiêu nằm trong [implementation plan](../architecture/manufacturing-implementation-plan.md).

## 1. Context và quyền

API `/admin/api/manufacturing/*` dùng guard admin, active account, auth version, forced password change và module runtime gate. Đây là nhánh enterprise riêng, không lấy website đang chọn làm quyền nhà máy. Các API CMS/website khác vẫn giữ `admin.website`.

Shell `GET /admin/modules/manufacturing/{any?}` có nhánh riêng với cùng account/module gate, kiểm Core global `manufacturing.view` và native membership; màn hình đổi mật khẩu bắt buộc chỉ cần identity. Shell dùng tiêu đề enterprise và hoạt động khi toàn bộ website inactive. Core vẫn giữ nghĩa hiện hữu của role global: có thể vào context website đang active, nhưng không tự có quyền đọc dữ liệu CMS. Khi không còn website active, `/admin/api/me` trả `403` trong khi bootstrap Manufacturing vẫn hoạt động.

Guest và phiên HTML hết hiệu lực tại shell này chuyển đến `GET /admin/manufacturing/login`. `POST` cùng URL dùng lại unified Auth controller của Core cho mật khẩu, TOTP, mã khôi phục dùng một lần, auth version và audit, rồi kiểm guard admin cùng native eligibility trước khi vào workspace. Form có CSRF, rate limit và không lấy branding/SiteContext; tài khoản customer hoặc admin ngoài phạm vi bị từ chối, redirect thành công chỉ về shell Manufacturing. Luồng đăng nhập storefront hiện hữu giữ nguyên.

Bootstrap trả `permissions` theo factory đang chọn và `navigation_permissions` là hợp các quyền Core ∩ native tại các factory active được cấp. Menu shell dùng hợp này để khôi phục factory khác đã lưu; API và màn hình nghiệp vụ vẫn kiểm quyền factory hiện hành. Forced-password identity không có navigation. Alias own-account `/admin/api/manufacturing/me/password`, `/me/two-factor/setup`, `/me/two-factor/confirm`, `/me/two-factor` và `/logout` giữ kiểm mật khẩu/TOTP/auth version của Core; đổi mật khẩu bắt buộc vẫn chặn 2FA cho tới khi hoàn thành.

Quyền Core global phải đồng thời thỏa native membership ở company/factory. Company-wide membership áp dụng xuống các nhà máy; membership một nhà máy không cấp quyền thay đổi master kỹ thuật dùng chung toàn công ty. `System Owner`/Super Admin vẫn chịu module gate và maintenance pause khi ghi nghiệp vụ.

| Permission | Miền thao tác |
| --- | --- |
| `manufacturing.view` | Bootstrap, danh sách, báo cáo trong phạm vi |
| `manufacturing.settings` | Tổ chức, thành viên, nguyên liệu master, tồn đầu kỳ |
| `manufacturing.engineering` | Style, revision, mẫu PP, duyệt kỹ thuật, impact |
| `manufacturing.plan` | Demand, forecast, lệnh, lịch, đề nghị mua |
| `manufacturing.execute` | Cắt, cấp/giữ/trả vật tư, bó, công đoạn, bàn giao, nhận gia công |
| `manufacturing.quality` | Kiểm nguyên liệu, inline/end-line/final, giữ/giải phóng, tái kiểm |
| `manufacturing.fulfill` | Nhập FG, đóng gói, hủy đóng gói, xuất giao |
| `manufacturing.cost.view` | Đọc chi phí theo quyền native hiện hành |
| `manufacturing.cost.manage` | Ghi khoản chi phí duyệt, adjustment, tính/chốt giá thành |
| `manufacturing.documents` | Tài liệu private theo parent và nhà máy |
| `manufacturing.integrations` | Quyền dự phòng cho adapter; chưa có adapter external writer |

Gửi gia công có đơn giá yêu cầu thêm native `cost.manage`. Mua hàng vẫn yêu cầu quyền global Purchasing hiện hữu: tạo đơn/NCC, duyệt, receipt/return tương ứng; native planner không tự có quyền Purchasing HQ. PO giữ maker/checker của Purchasing. SKU master cần Merchandise manage; tạo kho cần Inventory warehouse manage. Kiểm kê cần Inventory stocktake manage.

Response list và command đều che cost, amount, unit price và phí khi người dùng không có `cost.view` tại phạm vi đang thao tác. Command status chỉ trả reference, không phát lại payload hoặc dữ liệu tài chính đã bị thu hồi quyền.

## 2. Endpoint và identity

| Endpoint | Kết quả |
| --- | --- |
| `GET bootstrap?factory_id=UUID` | Identity, companies/factories/workshops/lines/shifts, permissions, runtime và readiness |
| `GET {kind}?factory_id=UUID&page=1&per_page=25&search=...&status=...` | Danh sách có giới hạn phía server |
| `POST commands/{operation}` | Một command giao dịch, bắt buộc `Idempotency-Key` |
| `GET commands/{key}` | `unknown` hoặc `committed`, kiểm quyền hiện hành |
| `POST documents` | Multipart private upload với key và checksum |
| `GET documents/{document UUID}` | Attachment download có authorize, checksum và audit |

`kind`: `styles`, `revisions`, `samples`, `demands`, `orders`, `bundles`, `handovers`, `quality`, `reworks`, `subcontracts`, `shipments`, `cartons`, `costing`, `materials`, `reports`, `settings`, `documents`, `order-mrp`, `plan-capacity`, `plan-calendar`, `plan-history`, `engineering-impact`.

Điều hướng Admin gom các link đã lọc quyền thành 5 mục chính: Tổng quan; Chuẩn bị (Nhu cầu và đơn hàng, Kỹ thuật sản phẩm, Kế hoạch); Sản xuất (Nguyên liệu, Cắt và bó, Sản xuất và bàn giao, Chất lượng, Gia công ngoài); Thành phẩm (Thành phẩm và giao hàng, Giá thành); Thiết lập. Nhóm rỗng tự ẩn; leaf key, route và quyền giữ nguyên. Manifest/API navigation vẫn phẳng để Core tiếp tục resolve route và authorize. Mobile mở sẵn nhóm chứa trang hiện tại; breadcrumb thêm tên nhóm.

Trang Thiết lập dùng `ManufacturingSettings`: tổng quan nhà máy đang chọn và bốn nhóm nội bộ Cơ cấu tổ chức, Nhân sự & quyền, Ca & danh mục, Nhật ký. Danh sách doanh nghiệp/nhà máy dùng dữ liệu được cấp quyền; số phân xưởng/chuyền/ca và sơ đồ cơ cấu lọc theo nhà máy hiện tại. Tạo nguyên liệu, tạo ca và điều khiển pause/resume nằm trong Ca & danh mục. Tất cả thao tác tiếp tục đi qua command drawer và `can()` hiện hữu; đổi nhà máy reset nhóm nội bộ, không giữ dữ liệu/biểu mẫu của phạm vi trước. Nhật ký dùng response settings đã giới hạn phía server, phân trang 10 dòng ở UI; không phải lịch sử toàn hệ thống.

`readiness.blockers` chứa cảnh báo capability của Inventory, Merchandise và Purchasing. Admin cho click cảnh báo để mở modal hướng dẫn cài/bật/nâng cấp và gọi lại bootstrap bằng “Kiểm tra lại”; lỗi tải không được coi là đã khắc phục. `readiness.can_open_app_store` phản ánh quyền global `store.module.view`, chỉ dùng hiển thị liên kết App Store. API lifecycle tiếp tục kiểm quyền riêng cho từng thao tác và context Core hiện hữu. Mở modal không tự cài hay thay đổi dữ liệu. Cảnh báo runtime paused có hướng dẫn và nút mở biểu mẫu `runtime.resume` theo quyền; sau khi xác nhận thành công, UI tải lại bootstrap. Logo header liên kết về `/admin`, tải lại Core shell để không dùng navigation mặc định của app hiện tại.

Resource ID chính ở native API dùng public UUID. Các adapter giữ internal numeric ID của Inventory/Purchasing bên trong service; supplier ID và stocktake balance ID là opaque numeric reference được chọn từ danh sách đã authorize, không nhập tay hoặc dùng làm bằng chứng phạm vi. `procurement.receive` nhận public UUID dòng PO và vẫn chấp nhận numeric ID tương thích khi dòng thực sự thuộc PO đã authorize.

Danh sách `orders` của xưởng nhận bàn giao gồm lệnh có bó thực sự đang ở xưởng, với `custody_only=true` và nested rows được giới hạn theo custody. Quyền đọc lệnh tại xưởng nhận không tự cấp quyền sửa kế hoạch lệnh nguồn. Handover xuất hiện cho cả xưởng gửi và xưởng nhận đúng company.

Read mặc định trả `{data: {items,total,page,per_page}}`; báo cáo/settings/MRP có cấu trúc chuyên biệt. Quantity/cost dùng decimal string 6 chữ số ở boundary native; sản lượng chiếc phải nguyên. Không cộng mét, kg và chiếc trong một KPI lượng.

## 3. Command và retry

`factory.create` cho phép bỏ `data.code`, gửi `null` hoặc chuỗi trống/khoảng trắng. Server tự cấp mã tiền tố `NM` và số ít nhất 6 chữ số (ví dụ `NM000001`), kiểm trùng trong công ty và khóa công ty trong transaction khi tạo. Mã nhập tay vẫn được kiểm định dạng và trùng như trước; replay command giữ nguyên mã đã cấp. Kho của nhà máy có mã tự cấp dùng ID công ty trong mã kho để không vượt giới hạn độ dài khi mã công ty dài.

```json
{
  "factory_id": "UUID",
  "resource": "UUID hoặc null",
  "data": { "expected_version": 3 }
}
```

Header `Idempotency-Key` dài 8–120 ký tự, gồm chữ/số/`:_-.`. Key thuộc actor, fingerprint gồm operation, payload canonical và phạm vi đã authorize. Cùng key khác nội dung/phạm vi trả `409`. Replay luôn kiểm lại account, module, runtime và native grant trước khi trả reference của lần commit gốc. New key không được dùng để bỏ qua version conflict.

Mutation trả `{data: {...,id,version,replayed,command_id},meta:{replayed,command_id}}`. Replay đã commit chỉ bảo đảm reference `id/public_id/status/version`; client phải đọc lại resource để lấy trạng thái mới nhất. Journal trình duyệt chỉ giữ key/hash/scope/operation, không giữ nội dung đơn, file hay token. Khi đổi actor/xưởng, response cũ bị bỏ qua.

`403`: quyền/account không hợp lệ. `404`: module tắt, lifecycle đang chặn hoặc row ngoài phạm vi. `409`: stale version, trạng thái, tồn hoặc nội dung key xung đột. `422`: dữ liệu nghiệp vụ không hợp lệ. `423`: module enabled nhưng native maintenance pause đang chặn ghi. `503`: capability optional cần cho hành động chưa sẵn sàng.

## 4. Các operation

| Nhóm | Operation |
| --- | --- |
| Thiết lập | `company.create`, `factory.create`, `workshop.create`, `line.create`, `shift.create`, `membership.save`, `sku.save`, `runtime.pause`, `runtime.resume` |
| Kỹ thuật | `style.save`, `style.approve`, `sample.save`, `sample.approve` |
| Kế hoạch | `demand.save`, `order.save`, `order.release`, `order.cancel`, `plan.calendar.save`, `plan.commit`, `plan.reschedule` |
| Vật tư | `material.receive`, `material.inspect`, `material.reserve`, `material.issue`, `material.release`, `material.return`, `material.stocktake` |
| Mua hàng | `procurement.create`, `procurement.approve`, `procurement.receive`, `procurement.return` |
| Sản xuất | `cut.create`, `bundle.split`, `bundle.merge`, `operation.record`, `bundle.handover`, `handover.receive` |
| Chất lượng/gia công | `quality.inspect`, `quality.rework`, `quality.hold`, `quality.release`, `subcontract.dispatch`, `subcontract.receive` |
| Thành phẩm | `bundle.finish`, `shipment.pack`, `shipment.cancel`, `shipment.dispatch` |
| Giá thành | `costing.record`, `costing.adjust`, `costing.close` |

Drawer trong Admin là client contract tham khảo, server luôn kiểm dữ liệu và quyền độc lập.

### Kỹ thuật và release

`style.save`: code/name, arrays sizes/colors, BOM `{material_code,uom,quantity,size?,color?,allowance_percent?}`, routing `{code,name,smv}`. Mỗi edit tạo revision mới; SKU Merchandise của biến thể cũ giữ identity. BOM thuộc catalog công ty, UOM phải khớp master. `sample.save` gắn đúng revision hiện tại; cần PP sample approved trước `style.approve`.

Demand nhận ma trận `{color,size,quantity}`, due date và buyer snapshot. `type` là `order|forecast|replenishment`. Firm order có `forecast_id` tiêu thụ cùng tháng/biến thể/xưởng; forecast allocated cộng firm consumption không vượt forecast gốc. Muốn chuyển forecast đã phân bổ sang đơn thực phải giảm/hủy lệnh nháp forecast trước.

Lệnh nháp phân bổ từ demand không vượt số lượng từng biến thể. Release đóng băng BOM, routing, measurement, SKU, buyer và order lines. Đổi master không đổi released snapshot. `engineering-impact` chỉ đọc lệnh/hold/WIP và PO ứng viên theo material; PO ứng viên chưa có source link không được trình bày như phân bổ được xác nhận.

Lịch là phân bổ thủ công theo SMV và ngày làm việc. Commit lưu cảnh báo được xác nhận, freeze window và lịch sử trước/sau. Override lịch đang freeze cần settings native; `order.save` không được bỏ qua schedule đã committed. Capacity report không phải bộ tối ưu finite scheduling.

### Vật tư và cắt

Kho factory được tạo là kho Inventory v2 rỗng, catalog/pháp nhân của công ty, rồi mới bật production-managed. Không tự chuyển kho Retail đang có tồn. Roll có batch riêng, primary quantity theo SKU, secondary đo thực tế, shade/width/lot và disposition. Nhập/PO receipt mặc định quarantine; QC mới cho saleable. Writer cũ bị chặn ở kho production-managed.

`material.receive` là tồn đầu kỳ có reason và quyền settings, không thay PO mua thật. `procurement.create` nhận supplier và lines `{material_code,quantity,unit_cost}`; có thể gắn `order_id` cùng `source_revision=order.version` để giữ requisition provenance. Lặp cùng order/revision khác key không tạo PO thứ hai. Nhận nhiều roll/từng phần và trả NCC đi qua Purchasing contract.

`cut.create`: order/workshop, mã bó, màu/size, quantity và `materials`. Mỗi dòng chọn đúng một nguồn: `material_id` cuộn chưa cấp, `reservation_id` hold đang giữ hoặc `issue_id` đã cấp; kèm `consumed_quantity`. Dùng issue đã cấp không trừ kho lần hai. Một issue chỉ được gắn tối đa phần chưa trả/chưa allocated/chưa bound vào bó khác. Thiếu một material BOM rollback cả reserve, issue và bundle.

Tiêu hao thực ngoài khoảng BOM base đến base+allowance cần `variance_reason` và native plan approval. `recut_of` cùng `recut_reason` tham chiếu bó có scrap cùng order variant; tổng good cut không vượt nhu cầu. Phiếu cấp dư chỉ trả phần chưa bound vào bó, về remnant quarantine theo layer nguồn.

### Bó, QC và gia công

Routing hiện tuyến tính. Operation kiểm đúng bước, ghi từng phần, khóa target trước khi trừ scrap; không bỏ bước hoặc nhân output do retry. Split/merge giữ genealogy và material allocations. Không tách công đoạn đang ghi dở hoặc bó đã QC/FG. Scrap cost được hấp thụ vào good remainder theo policy hiện hành; parent chỉ còn scrap có WIP bằng 0.

Handover dispatch/receive từng phần giữ in-transit, không đồng thời khả dụng ở hai xưởng. Người gửi cần quyền tại đích; người nhận thao tác theo native grant đích. Hàng đã QC/nhập FG cần luồng điều chuyển kho riêng, không chuyển custody bằng handover bó.

QC `checkpoint=inline|end_line|final`. Inline/end-line tạo quan sát/hold/rework, không cộng accepted FG; loại được ghi tại operation/final. Final chỉ sau routing, kiểm toàn lượng theo policy full-count; kết quả good+rework+scrap cân bằng. Reinspection chỉ tăng phần đạt mới. Manual hold chặn finish/pack/dispatch; release có reason và không bỏ qua rework còn mở.

Gia công giữ snapshot đơn giá, custody và receipt từng phần. Chỉ phần good được nghiệm thu mới tạo phí; rework được tính sau khi đạt, scrap không tự tính như good. Phí này là cost nghiệp vụ, chưa phải hóa đơn hoặc bút toán công nợ pháp lý.

### FG, shipment và cost

Finish chỉ nhập lượng đạt final QC còn lại, nhận actual cost từ issue layers và conversion approved. Final QC là nguồn giải phóng FG quarantine qua Inventory contract. Carton giữ bundle/variant/roll/quantity, pack không trừ kho. Dispatch vừa issue stock vừa tăng shipped/demand fulfilled trong cùng transaction. Cancel chỉ cho packed shipment chưa xuất, trả allocation đóng gói để repack.

Cost record giữ khoản labor/overhead/subcontract approved. Cost close tính material actual (`issueLayer.total_cost-returned_cost`) và conversion approved, đối soát posting/output/WIP; chỉ final khi toàn bộ output và delta khớp. Khóa cost trước khi giao vẫn cho giao tiếp, order chỉ closed khi cả shipping và cost hoàn tất.

`costing.adjust` có order resource, expected version, reason. Chi phí conversion đến muộn được phân bổ theo output quantity; phần hàng còn kho nằm trong adjustment layer, phần đã xuất là realized variance. Posted issue/layer gốc không bị ghi đè. Future issue nhận giá đã điều chỉnh. Cost record sau final cần `reopen_reason`; final run cũ được giữ. Số realized tổng bao gồm phần đã nằm trong future issue, nên báo cáo COGS phải dùng `realized_variance_value`, không cộng mù realized vào issue cost.

## 5. Outbox, tài liệu và vận hành

Mỗi command ghi business state, audit và outbox trong cùng transaction. `mfg:dispatch-events` tiêu thụ destination `audit` với inbox dedup; scheduler chạy mỗi phút. Destination chưa có consumer bị blocked rõ ràng. Chưa gửi HTTP webhook, trả lương hay post kế toán tự động.

Tài liệu dùng disk `manufacturing_private` tại `storage/app/manufacturing-private`, tách khỏi public `/files`. Upload kiểm loại, dung lượng tối đa 20 MiB, checksum và parent scope; rollback dọn file. Download attachment có no-sniff, checksum và audit. Cần backup DB cùng private files, commands, outbox/inbox và valuation provenance.

Pause/resume toàn module chỉ cho System Owner/Super Admin. Pause đợi writer hiện hành, cho GET đã authorize và chặn ghi mới. Disable/upgrade qua lifecycle barrier, giữ WIP và snapshot; không yêu cầu WIP về 0. Không uninstall lịch sử.

## 6. Cài đặt và kiểm thử

Thực hiện trên môi trường clone/staging trước. Nâng/cài Merchandise, Inventory, Purchasing rồi Manufacturing qua ModuleManager/Module Store để module migrations chạy đúng lifecycle. Manufacturing có hard dependency Merchandise/Inventory; Purchasing optional ở package nhưng bắt buộc cho mua FOB. Enable fail closed khi thiếu capability. Không chạy aggregate test migrations hoặc seed lại tồn trên DB nghiệp vụ.

```bash
vendor/bin/phpunit tests/Feature/ManufacturingFoundationTest.php tests/Feature/ManufacturingFreshInstallTest.php tests/Feature/ManufacturingWorkflowTest.php
vendor/bin/phpunit tests/Feature/InventoryProductionTest.php tests/Feature/PurchasingProductionTest.php
node --test tests/UnitJavaScript/*.test.js
npm run build
php tests/Support/manufacturing-mysql-gates.php --allow-isolated-mysql
php tests/Support/inventory-production-mysql.php --allow-isolated-mysql
```

PHPUnit dùng SQLite memory theo `phpunit.xml`; không dùng `php artisan test` với môi trường nghiệp vụ đang boot trước test config. MySQL runners tự tạo database random có prefix guard và DROP chính database đó; không migrate/seed database `aio`. Browser fixture/router chỉ hoạt động với test environment và SQLite riêng; không dùng router test để phục vụ deployment.

Shared versions tối thiểu cho đường mới: Merchandise `0.2.0`, Inventory `0.4.0`, Purchasing `0.2.1` với receipt identity capability. Migrations valuation/identity là forward-only; giữ numeric IDs và posted history. Legacy receipt writer có thể tiếp tục tạo dòng chưa có UUID, native production receipt mới bảo đảm UUID. Backfill issue cost cũ dùng quantity×unit cost khi chưa có exact-cost provenance; không tái tạo được dữ liệu lịch sử đã thiếu.

Late valuation yêu cầu nhận xong roll đang chuyển kho. Warehouse valuation không bao gồm giá trị đang đi đường; realized variance là dữ liệu để adapter kế toán đối soát về sau, chưa là GL posting. Secondary UOM là số đo đối soát, chưa có pricing engine theo đơn vị phụ.
