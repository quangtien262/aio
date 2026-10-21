export const sections = {
    overview: { title: 'Tổng quan sản xuất', description: 'Theo dõi đơn hàng, dòng vật tư và tiến độ tại nhà máy.', kind: 'reports' },
    demand: { title: 'Nhu cầu & đơn hàng', description: 'Quản lý đơn khách hàng và dự báo theo màu, kích cỡ, kỳ giao.', kind: 'demands', create: 'demand.save' },
    engineering: { title: 'Kỹ thuật sản phẩm', description: 'Quản lý mã hàng, phiên bản định mức và quy trình trước khi phát hành.', kind: 'styles', create: 'style.save' },
    planning: { title: 'Kế hoạch sản xuất', description: 'Phân bổ lệnh cho phân xưởng, kiểm tra nhu cầu vật tư và phát hành.', kind: 'orders', create: 'order.save' },
    materials: { title: 'Nguyên phụ liệu', description: 'Mua hàng, nhận từng cuộn vải và kiểm soát lô màu trước khi sử dụng.', kind: 'materials', create: 'procurement.create' },
    cutting: { title: 'Trải & cắt', description: 'Ghi nhận cắt, tiêu hao vải và tạo bó có nguồn gốc rõ ràng.', kind: 'bundles', create: 'cut.create' },
    execution: { title: 'Điều hành phân xưởng', description: 'Bàn giao bó, theo dõi số lượng đang làm và tách bó.', kind: 'bundles' },
    quality: { title: 'Chất lượng', description: 'Kiểm phẩm, giữ hàng lỗi và tái kiểm sau sửa.', kind: 'quality', create: 'quality.inspect' },
    subcontract: { title: 'Gia công ngoài', description: 'Theo dõi hàng gửi, nhận từng phần và chất lượng tại đối tác.', kind: 'subcontracts', create: 'subcontract.dispatch' },
    fulfillment: { title: 'Đóng gói & giao hàng', description: 'Đóng thùng theo lệnh sản xuất và ghi nhận từng đợt giao.', kind: 'shipments', create: 'shipment.pack' },
    costing: { title: 'Giá thành', description: 'Ghi nhận chi phí và đối chiếu giá thành theo lệnh sản xuất.', kind: 'costing', create: 'costing.record' },
    settings: { title: 'Thiết lập sản xuất', description: 'Tổ chức công ty, nhà máy, phân xưởng, chuyền và quyền thành viên.', kind: 'settings' },
};

const field = (name, label, type = 'text', extra = {}) => ({ name, label, type, ...extra });
const required = { required: true };
const choice = (value, label) => ({ value, label });
const matrix = field('lines', 'Số lượng theo màu / kích cỡ', 'list', { required: true, fields: [field('color', 'Màu', 'text', required), field('size', 'Kích cỡ', 'text', required), field('quantity', 'Số lượng', 'number', { required: true, min: 1, integer: true })] });
const quantity = (name = 'quantity', label = 'Số lượng', min = 1) => field(name, label, 'number', { required: true, min, integer: true });
const lookup = (name, label, source, extra = {}) => field(name, label, 'lookup', { source, required: true, ...extra });
const notes = field('notes', 'Ghi chú', 'textarea');
const operationQuantity = [quantity(), quantity('good_quantity', 'Đạt', 0), quantity('rework_quantity', 'Cần sửa', 0), quantity('scrap_quantity', 'Loại bỏ', 0)];
const scheduleFields = [field('planned_start', 'Bắt đầu kế hoạch', 'date', required), field('planned_end', 'Kết thúc kế hoạch', 'date', required), lookup('workshop_id', 'Phân xưởng', 'workshops', { required: false }), lookup('line_id', 'Chuyền', 'lines', { required: false }), field('freeze_until', 'Đóng băng kế hoạch đến ngày', 'date'), field('acknowledge_warnings', 'Đã xem và chấp nhận cảnh báo năng lực', 'boolean'), field('override_frozen', 'Cho phép đổi trong thời gian đóng băng (cần quyền thiết lập)', 'boolean'), field('reason', 'Lý do cam kết / đổi lịch', 'textarea', required)];
const decimalMicros = (value) => {
    const text = String(value).trim();
    if (!/^\d+(?:\.\d{1,6})?$/.test(text)) throw new Error('Số thập phân cần tối đa 6 chữ số sau dấu chấm.');
    const [whole, fraction = ''] = text.split('.');
    return BigInt(whole) * 1000000n + BigInt(fraction.padEnd(6, '0'));
};

export const commands = {
    'style.save': { title: 'Lưu mã hàng / phiên bản kỹ thuật', permission: 'manufacturing.engineering.manage', fields: [field('code', 'Mã hàng', 'text', required), field('name', 'Tên mã hàng', 'text', required), field('sizes', 'Kích cỡ', 'tags', required), field('colors', 'Màu sắc', 'tags', required), field('bom', 'Định mức nguyên phụ liệu', 'list', { required: true, fields: [field('material_code', 'Mã vật tư', 'text', required), field('name', 'Tên vật tư'), field('uom', 'Đơn vị', 'text', required), field('quantity', 'Định mức', 'number', { required: true, min: 0.000001 }), field('allowance_percent', 'Hao hụt (%)', 'number', { min: 0 }), field('color', 'Màu áp dụng'), field('size', 'Size áp dụng')] }), field('routing', 'Quy trình công đoạn', 'list', { required: true, fields: [field('code', 'Mã công đoạn', 'text', required), field('name', 'Tên công đoạn', 'text', required), field('smv', 'Phút tiêu chuẩn', 'number', { min: 0 })] }), field('measurements', 'Thông số đo', 'list', { fields: [field('point', 'Vị trí đo', 'text', required), field('size', 'Size', 'text', required), field('value', 'Kích thước', 'number', { required: true, min: 0 }), field('tolerance', 'Dung sai', 'number', { min: 0 })] }), notes] },
    'style.approve': { title: 'Duyệt kỹ thuật', permission: 'manufacturing.engineering.approve', fields: [notes], confirm: 'Xác nhận phiên bản kỹ thuật đã sẵn sàng để phát hành lệnh.' },
    'sample.save': { title: 'Tạo hồ sơ mẫu', permission: 'manufacturing.engineering', fields: [lookup('style_id', 'Mã hàng / phiên bản hiện tại', 'styles'), field('type', 'Loại mẫu', 'select', { required: true, options: [choice('proto', 'Mẫu ban đầu'), choice('fit', 'Mẫu thử dáng'), choice('size_set', 'Bộ size'), choice('pp', 'Mẫu trước sản xuất (PP)')] }), notes] },
    'sample.approve': { title: 'Duyệt mẫu', permission: 'manufacturing.engineering', fields: [notes], confirm: 'Xác nhận mẫu đạt theo thông số và đúng phiên bản kỹ thuật.' },
    'demand.save': { title: 'Lưu nhu cầu', permission: 'manufacturing.demand.manage', fields: [field('code', 'Mã nhu cầu', 'text', required), lookup('style_id', 'Mã hàng', 'styles'), field('type', 'Loại nhu cầu', 'select', { required: true, options: [choice('order', 'Đơn khách hàng'), choice('forecast', 'Dự báo tồn kho'), choice('replenishment', 'Bổ sung tồn kho')] }), lookup('forecast_id', 'Tiêu thụ dự báo (nếu có)', 'demands', { required: false }), field('customer_name', 'Khách hàng / nhãn hàng'), field('due_date', 'Ngày cần hàng', 'date', required), matrix, notes] },
    'order.save': { title: 'Lập lệnh sản xuất', permission: 'manufacturing.planning.manage', fields: [field('code', 'Mã lệnh', 'text', required), lookup('demand_id', 'Nhu cầu nguồn', 'demands'), lookup('workshop_id', 'Phân xưởng', 'workshops'), lookup('line_id', 'Chuyền', 'lines', { required: false }), field('planned_start', 'Bắt đầu kế hoạch', 'date', required), field('planned_end', 'Kết thúc kế hoạch', 'date', required), matrix, notes] },
    'order.release': { title: 'Phát hành lệnh', permission: 'manufacturing.planning.release', fields: [notes], confirm: 'Phát hành sử dụng phiên bản kỹ thuật đã duyệt. Hệ thống sẽ kiểm tra lại điều kiện tại thời điểm lưu.' },
    'order.cancel': { title: 'Hủy lệnh sản xuất', permission: 'manufacturing.plan', fields: [field('reason', 'Lý do hủy', 'textarea', required)], confirm: 'Lệnh chỉ được hủy khi các số dư và công việc liên quan cho phép.' },
    'plan.commit': { title: 'Cam kết kế hoạch', permission: 'manufacturing.plan', fields: scheduleFields, confirm: 'Xem báo cáo năng lực trước khi cam kết. Lịch phân bổ được ghi nhận cùng lý do và cảnh báo đã chấp nhận.' },
    'plan.reschedule': { title: 'Điều chỉnh lịch đã cam kết', permission: 'manufacturing.plan', fields: scheduleFields, confirm: 'Đổi lịch giữ lại lịch sử trước và sau. Cửa sổ đóng băng cần quyền thiết lập và xác nhận rõ ràng.' },
    'plan.calendar.save': { title: 'Lưu lịch làm việc', permission: 'manufacturing.plan', fields: [field('scope_type', 'Phạm vi lịch', 'select', { required: true, options: [choice('factory', 'Toàn nhà máy'), choice('workshop', 'Phân xưởng'), choice('line', 'Chuyền')] }), lookup('workshop_id', 'Phân xưởng', 'workshops', { required: false }), lookup('line_id', 'Chuyền', 'lines', { required: false }), field('work_date', 'Ngày áp dụng', 'date', required), field('is_working', 'Ngày có làm việc', 'boolean'), field('available_minutes', 'Phút năng lực khả dụng', 'number', { required: true, min: 0 }), notes] },
    'procurement.create': { title: 'Lập đơn mua nguyên phụ liệu', permission: 'manufacturing.materials.manage', fields: [lookup('supplier_id', 'Nhà cung cấp đã có', 'suppliers', { required: false }), field('supplier_name', 'Hoặc tên nhà cung cấp mới (cần quyền tạo)'), lookup('order_id', 'Lệnh nguồn (nếu mua cho lệnh cụ thể)', 'orders', { required: false, versionField: 'source_revision' }), field('source_revision', 'Phiên bản lệnh nguồn', 'hidden'), field('lines', 'Vật tư đặt mua', 'list', { required: true, fields: [field('material_code', 'Mã vật tư', 'text', required), field('name', 'Tên vật tư', 'text', required), field('uom', 'Đơn vị', 'text', required), field('quantity', 'Số lượng mua', 'number', { required: true, min: 0.000001 }), field('unit_cost', 'Đơn giá', 'number', { required: true, min: 0 })] }), notes] },
    'procurement.approve': { title: 'Duyệt đơn mua', permission: 'manufacturing.materials.manage', fields: [notes], confirm: 'Xác nhận đơn mua trước khi nhận nguyên phụ liệu.' },
    'procurement.receive': { title: 'Nhận hàng theo đơn mua', permission: 'manufacturing.materials.manage', fields: [field('lines', 'Chi tiết cuộn / kiện nhận', 'list', { required: true, fields: [field('order_line_id', 'Dòng đơn mua', 'select', required), field('quantity', 'Số lượng nhận', 'number', { required: true, min: 0.000001 }), field('roll_code', 'Mã cuộn / kiện', 'text', required), field('lot_code', 'Lô sản xuất / nhuộm', 'text', required), field('shade', 'Nhóm ánh màu', 'text', required), field('width', 'Khổ vải', 'number', { min: 0 }), field('secondary_quantity', 'Số đo phụ', 'number', { min: 0 }), field('secondary_unit', 'Đơn vị phụ')] }), notes] },
    'procurement.return': { title: 'Trả nguyên liệu cho nhà cung cấp', permission: 'manufacturing.plan', fields: [field('receipt_line_id', 'Cuộn / dòng nhận cần trả', 'select', required), field('quantity', 'Số lượng trả', 'number', { required: true, min: 0.000001 }), field('reason', 'Lý do trả nhà cung cấp', 'textarea', required)], confirm: 'Chỉ trả phần vật tư còn đủ điều kiện rút khỏi kho. Phiếu trả đối chiếu đúng đơn mua và lô nhận.' },
    'material.receive': { title: 'Nhập tồn đầu kỳ', permission: 'manufacturing.materials.manage', fields: [field('code', 'Mã vật tư', 'text', required), field('name', 'Tên vật tư', 'text', required), field('roll_code', 'Mã cuộn / kiện', 'text', required), field('lot_code', 'Lô nhuộm / sản xuất', 'text', required), field('shade', 'Nhóm ánh màu', 'text', required), field('width', 'Khổ vải', 'number', { min: 0 }), field('quantity', 'Số lượng nhận', 'number', { required: true, min: 0.000001 }), field('uom', 'Đơn vị đo', 'text', required), field('unit_cost', 'Đơn giá nhập', 'number', { required: true, min: 0 }), field('reason', 'Lý do / tham chiếu số dư đầu kỳ', 'textarea', required)] },
    'material.inspect': { title: 'Kiểm nhận vật tư', permission: 'manufacturing.quality.inspect', fields: [field('status', 'Kết quả', 'select', { required: true, options: [choice('accepted', 'Đạt / cho phép sử dụng'), choice('rejected', 'Không đạt / giữ hàng')] }), notes] },
    'material.reserve': { title: 'Giữ vật tư cho lệnh', permission: 'manufacturing.materials.manage', fields: [lookup('order_id', 'Lệnh sản xuất', 'orders'), field('quantity', 'Số lượng giữ', 'number', { required: true, min: 0.000001 })] },
    'material.issue': { title: 'Cấp vật tư', permission: 'manufacturing.materials.manage', fields: [field('reservation_id', 'Mã giữ vật tư', 'text', required), field('quantity', 'Số lượng cấp', 'number', { required: true, min: 0.000001 })] },
    'material.release': { title: 'Giải phóng vật tư đã giữ', permission: 'manufacturing.execute', fields: [field('reservation_id', 'Mã giữ vật tư', 'text', required), field('quantity', 'Số lượng giải phóng', 'number', { required: true, min: 0.000001 }), notes] },
    'material.return': { title: 'Trả vật tư dư', permission: 'manufacturing.materials.manage', fields: [field('issue_id', 'Mã phiếu cấp', 'text', required), field('quantity', 'Số lượng trả', 'number', { required: true, min: 0.000001 }), notes] },
    'material.stocktake': { title: 'Bắt đầu kiểm kê nguyên phụ liệu', permission: 'manufacturing.settings', fields: [], confirm: 'Bắt đầu kiểm kê sẽ khóa biến động tại kho nhà máy. Chuẩn bị người đếm và người duyệt khác nhau trước khi bắt đầu.' },
    'material.stocktake.post': { title: 'Nhập và duyệt số đếm kiểm kê', apiOperation: 'material.stocktake', permission: 'manufacturing.settings', fields: [field('stocktake_id', 'Mã đợt kiểm kê', 'hidden', required), field('counts', 'Số đếm từng vị trí tồn', 'list', { required: true, fixed: true, fields: [field('balance_id', 'Tham chiếu tồn', 'hidden', required), field('label', 'Cuộn / vật tư / đơn vị', 'text', { readOnly: true, omit: true }), field('expected_quantity', 'Số lượng sổ', 'text', { readOnly: true, omit: true }), field('quantity', 'Số lượng thực đếm', 'number', { required: true, min: 0 }), field('secondary_quantity', 'Số đo phụ thực đếm (nếu có)', 'number', { min: 0 })] })], confirm: 'Người bắt đầu kiểm kê không tự duyệt. Nhập đầy đủ số thực đếm; vật tư hai đơn vị cần số đo phụ thực tế.' },
    'cut.create': { title: 'Ghi nhận cắt & tạo bó', permission: 'manufacturing.cutting.manage', fields: [lookup('order_id', 'Lệnh sản xuất', 'orders'), lookup('workshop_id', 'Phân xưởng', 'workshops'), field('code', 'Mã bàn cắt / bó', 'text', required), field('color', 'Màu', 'text', required), field('size', 'Kích cỡ', 'text', required), quantity(), field('materials', 'Nguyên liệu sử dụng theo BOM', 'list', { required: true, fields: [lookup('material_id', 'Cuộn mới (chọn một nguồn mỗi dòng)', 'materials', { required: false }), lookup('issue_id', 'Hoặc phiếu cấp đã có', 'issues', { required: false }), lookup('reservation_id', 'Hoặc vật tư đã giữ', 'reservations', { required: false }), field('consumed_quantity', 'Tiêu hao (đơn vị của cuộn)', 'number', { required: true, min: 0.000001 })] }), field('variance_reason', 'Lý do chênh lệch định mức', 'textarea'), lookup('recut_of', 'Bó cần cắt bù (nếu có)', 'bundles', { required: false }), field('recut_reason', 'Lý do cắt bù', 'textarea'), notes] },
    'bundle.handover': { title: 'Bàn giao bó', permission: 'manufacturing.execution.manage', fields: [quantity(), lookup('to_workshop_id', 'Nhà máy / phân xưởng nhận', 'handover_workshops'), field('to_operation', 'Công đoạn nhận', 'text', required), notes] },
    'bundle.split': { title: 'Tách bó', permission: 'manufacturing.execution.manage', fields: [quantity(), notes] },
    'bundle.merge': { title: 'Gộp bó tương thích', permission: 'manufacturing.execute', fields: [lookup('source_bundle_id', 'Bó nguồn cần gộp vào bó hiện tại', 'bundles', { versionField: 'source_expected_version' }), notes], confirm: 'Chỉ gộp các bó có cùng phiên bản, biến thể và trạng thái công đoạn phù hợp.' },
    'handover.receive': { title: 'Xác nhận nhận bàn giao', permission: 'manufacturing.execution.manage', fields: [quantity(), notes] },
    'operation.record': { title: 'Ghi nhận công đoạn', permission: 'manufacturing.execution.manage', fields: [field('operation_code', 'Mã công đoạn', 'text', required), quantity(), quantity('good_quantity', 'Đạt', 0), quantity('scrap_quantity', 'Loại bỏ', 0), field('work_minutes', 'Phút lao động thực tế', 'number', { min: 0 }), notes] },
    'bundle.finish': { title: 'Nhập thành phẩm từ bó đạt', permission: 'manufacturing.fulfillment.manage', fields: [quantity(), notes] },
    'quality.inspect': { title: 'Kiểm phẩm', permission: 'manufacturing.quality.inspect', fields: [lookup('bundle_id', 'Bó hàng', 'bundles'), field('checkpoint', 'Điểm kiểm', 'select', { required: true, options: [choice('inline', 'Trong chuyền'), choice('end_line', 'Cuối chuyền'), choice('final', 'Kiểm cuối để nhập thành phẩm')] }), ...operationQuantity, field('defect_code', 'Mã lỗi'), notes], confirm: 'Kiểm trong / cuối chuyền ghi nhận quan sát, giữ hàng và sửa lỗi. Chỉ kiểm cuối xác nhận số đạt để nhập thành phẩm; loại bỏ tại công đoạn hoặc kiểm cuối.' },
    'quality.rework': { title: 'Tái kiểm hàng sửa', permission: 'manufacturing.quality.inspect', fields: [lookup('bundle_id', 'Bó hàng', 'bundles'), field('rework_id', 'Tham chiếu phiếu sửa (nếu chọn cụ thể)'), quantity(), quantity('good_quantity', 'Sửa đạt', 0), quantity('scrap_quantity', 'Loại bỏ', 0), notes] },
    'quality.hold': { title: 'Giữ bó hàng để kiểm tra', permission: 'manufacturing.quality', fields: [field('reason', 'Lý do giữ hàng', 'textarea', required)] },
    'quality.release': { title: 'Giải phóng giữ hàng', permission: 'manufacturing.quality', fields: [field('reason', 'Lý do giải phóng', 'textarea', required)], confirm: 'Hệ thống sẽ kiểm tra lại các phiếu sửa và điều kiện giữ hàng trước khi cho phép tiếp tục.' },
    'subcontract.dispatch': { title: 'Gửi gia công ngoài', permission: 'manufacturing.subcontract.manage', fields: [lookup('bundle_id', 'Bó hàng', 'bundles'), field('vendor_name', 'Đối tác gia công', 'text', required), field('process', 'Công đoạn', 'text', required), quantity(), field('due_date', 'Hạn trả hàng', 'date', required), field('unit_cost', 'Đơn giá gia công', 'number', { required: true, min: 0 }), notes] },
    'subcontract.receive': { title: 'Nhận hàng gia công', permission: 'manufacturing.subcontract.manage', fields: [...operationQuantity, notes] },
    'shipment.pack': { title: 'Đóng thùng', permission: 'manufacturing.fulfillment.manage', fields: [lookup('order_id', 'Lệnh sản xuất', 'orders'), field('code', 'Mã thùng', 'text', required), field('items', 'Hàng trong thùng', 'list', { required: true, fields: [lookup('bundle_id', 'Bó hàng đạt', 'bundles'), quantity()] }), notes] },
    'shipment.dispatch': { title: 'Giao hàng', permission: 'manufacturing.fulfillment.manage', fields: [field('reference', 'Tham chiếu vận chuyển', 'text', required), notes], confirm: 'Xác nhận hàng đã sẵn sàng giao. Hệ thống ghi thời điểm giao khi giao dịch được lưu thành công.' },
    'shipment.cancel': { title: 'Hủy đóng gói / đợt giao', permission: 'manufacturing.fulfill', fields: [field('reason', 'Lý do hủy', 'textarea', required)], confirm: 'Chỉ hủy lô chưa giao; số lượng đóng gói sẽ được hoàn lại theo giao dịch.' },
    'costing.record': { title: 'Ghi nhận chi phí', permission: 'manufacturing.costing.manage', fields: [lookup('order_id', 'Lệnh sản xuất', 'orders'), field('category', 'Khoản mục', 'select', { required: true, options: [choice('labor', 'Nhân công'), choice('overhead', 'Chi phí chung'), choice('subcontract', 'Gia công')] }), field('amount', 'Số tiền', 'number', { required: true, min: 0.01 }), { ...notes, required: true }, field('reopen_reason', 'Lý do bổ sung sau khi chốt giá thành (nếu đã chốt)', 'textarea')] },
    'costing.close': { title: 'Tính và chốt giá thành', permission: 'manufacturing.cost.manage', fields: [lookup('order_id', 'Lệnh sản xuất', 'orders'), notes], confirm: 'Giá thành dùng tiêu hao thực tế và chi phí đã duyệt. Lệnh chỉ khóa khi các khoản phân bổ được đối chiếu đầy đủ.' },
    'costing.adjust': { title: 'Phân bổ chi phí bổ sung', permission: 'manufacturing.cost.manage', fields: [lookup('order_id', 'Lệnh sản xuất', 'orders'), field('reason', 'Lý do điều chỉnh giá thành', 'textarea', required)], confirm: 'Phân bổ phần chênh lệch từ các chi phí đã ghi nhận trước khi tính lại giá thành.' },
    'company.create': { title: 'Tạo công ty sản xuất', permission: 'manufacturing.settings.manage', fields: [field('code', 'Mã công ty', 'text', required), field('name', 'Tên công ty', 'text', required)] },
    'factory.create': { title: 'Tạo nhà máy', permission: 'manufacturing.settings.manage', fields: [lookup('company_id', 'Công ty', 'companies'), field('code', 'Mã nhà máy', 'text', { placeholder: 'Để trống để tự tạo mã bắt đầu bằng NM', maxLength: 40 }), field('name', 'Tên nhà máy', 'text', required), field('timezone', 'Múi giờ', 'text', required)] },
    'workshop.create': { title: 'Tạo phân xưởng', permission: 'manufacturing.settings.manage', fields: [field('code', 'Mã phân xưởng', 'text', required), field('name', 'Tên phân xưởng', 'text', required)] },
    'line.create': { title: 'Tạo chuyền', permission: 'manufacturing.settings.manage', fields: [lookup('workshop_id', 'Phân xưởng', 'workshops'), field('code', 'Mã chuyền', 'text', required), field('name', 'Tên chuyền', 'text', required), field('capacity_minutes', 'Năng lực phút / ca', 'number', { required: true, min: 0 })] },
    'shift.create': { title: 'Tạo ca làm việc', permission: 'manufacturing.settings', fields: [field('code', 'Mã ca', 'text', required), field('name', 'Tên ca', 'text', required), field('starts_at', 'Giờ bắt đầu (HH:mm)', 'text', required), field('ends_at', 'Giờ kết thúc (HH:mm)', 'text', required)] },
    'sku.save': { title: 'Tạo mã nguyên phụ liệu', permission: 'manufacturing.settings', fields: [field('sku', 'Mã vật tư', 'text', required), field('name', 'Tên vật tư', 'text', required), field('base_unit', 'Đơn vị gốc', 'text', required), field('quantity_scale', 'Số chữ số thập phân', 'number', { required: true, min: 0, integer: true }), field('tracking_mode', 'Theo dõi lô', 'select', { required: true, options: [choice('batch', 'Theo lô / cuộn'), choice('none', 'Không theo lô')] })] },
    'runtime.pause': { title: 'Tạm dừng ghi dữ liệu sản xuất', permission: 'manufacturing.settings', fields: [field('reason', 'Lý do tạm dừng', 'textarea', required)], confirm: 'Tạm dừng áp dụng cho hệ thống sản xuất. Chỉ thực hiện khi đã phối hợp với các nhà máy.' },
    'runtime.resume': { title: 'Tiếp tục ghi dữ liệu sản xuất', permission: 'manufacturing.settings', fields: [field('reason', 'Lý do tiếp tục', 'textarea', required)] },
    'membership.save': { title: 'Phân quyền thành viên', permission: 'manufacturing.settings.manage', fields: [lookup('company_id', 'Công ty', 'companies'), lookup('factory_id', 'Nhà máy (bỏ trống: toàn công ty)', 'factories', { required: false }), field('admin_id', 'ID tài khoản quản trị', 'number', { required: true, min: 1, integer: true }), field('role', 'Vai trò tại nhà máy', 'select', { required: true, options: [choice('reader', 'Chỉ xem'), choice('operator', 'Vận hành'), choice('manager', 'Quản lý')] }), field('status', 'Trạng thái thành viên', 'select', { options: [choice('active', 'Hoạt động'), choice('inactive', 'Vô hiệu hóa')] }), field('expires_at', 'Ngày hết hạn', 'date'), notes] },
};

const permissionMap = {
    'manufacturing.engineering.manage': 'manufacturing.engineering',
    'manufacturing.engineering.approve': 'manufacturing.engineering',
    'manufacturing.demand.manage': 'manufacturing.plan',
    'manufacturing.planning.manage': 'manufacturing.plan',
    'manufacturing.planning.release': 'manufacturing.plan',
    'manufacturing.materials.manage': 'manufacturing.execute',
    'manufacturing.cutting.manage': 'manufacturing.execute',
    'manufacturing.execution.manage': 'manufacturing.execute',
    'manufacturing.quality.inspect': 'manufacturing.quality',
    'manufacturing.subcontract.manage': 'manufacturing.execute',
    'manufacturing.fulfillment.manage': 'manufacturing.fulfill',
    'manufacturing.costing.manage': 'manufacturing.cost.manage',
    'manufacturing.settings.manage': 'manufacturing.settings',
};
Object.values(commands).forEach((command) => { command.permission = permissionMap[command.permission] ?? command.permission; });
commands['material.receive'].permission = 'manufacturing.settings';
['procurement.create', 'procurement.approve', 'procurement.receive'].forEach((operation) => { commands[operation].permission = 'manufacturing.plan'; });

export function sectionForMenu(menu) {
    const aliases = { demands: 'demand', subcontracts: 'subcontract' };
    const key = String(menu?.key ?? '').replace(/^manufacturing[-.]/, '');
    const candidate = aliases[key] ?? key;
    if (sections[candidate]) return candidate;
    const routeSection = String(menu?.route ?? '').split('?')[0].split('/').filter(Boolean).at(-1);
    return sections[aliases[routeSection] ?? routeSection] ? (aliases[routeSection] ?? routeSection) : 'overview';
}

export function commandPayload(operation, values, record) {
    const definition = commands[operation];
    if (!definition) throw new Error('Thao tác không được hỗ trợ.');
    const clean = (fields, data) => Object.fromEntries(fields.filter((item) => !item.omit).map((item) => {
        const value = data[item.name];
        if (item.required && (value === undefined || value === null || value === '' || (Array.isArray(value) && !value.length))) throw new Error(`Vui lòng nhập ${item.label.toLowerCase()}.`);
        if (item.type === 'list') return [item.name, (value ?? []).map((row) => clean(item.fields, row))];
        if (item.type === 'number' && value !== undefined && value !== null && value !== '') {
            if (item.integer) {
                const parsed = Number(value);
                if (!Number.isSafeInteger(parsed) || (item.min !== undefined && parsed < item.min)) throw new Error(`${item.label} không hợp lệ.`);
                return [item.name, parsed];
            }
            if (decimalMicros(value) < decimalMicros(item.min ?? 0)) throw new Error(`${item.label} không hợp lệ.`);
            return [item.name, String(value).trim()];
        }
        if (item.type === 'tags') return [item.name, [...new Set((value ?? []).map((entry) => String(entry).trim()).filter(Boolean))]];
        return [item.name, typeof value === 'string' ? value.trim() : value];
    }).filter(([, value]) => value !== undefined && value !== ''));
    const result = clean(definition.fields, values);
    if (operation === 'procurement.create' && !result.supplier_id && !result.supplier_name) throw new Error('Chọn nhà cung cấp đã có hoặc nhập tên nhà cung cấp mới.');
    if (result.lines && ['demand.save', 'order.save'].includes(operation)) {
        const seen = new Set();
        for (const row of result.lines) {
            const key = JSON.stringify([row.color, row.size]);
            if (seen.has(key)) throw new Error('Mỗi cặp màu / kích cỡ chỉ được khai báo một lần.');
            seen.add(key);
        }
    }
    if (['quality.inspect', 'subcontract.receive', 'operation.record'].includes(operation)
        && Number(result.good_quantity) + Number(result.rework_quantity ?? 0) + Number(result.scrap_quantity) !== Number(result.quantity)) {
        throw new Error('Tổng số đạt, cần sửa và loại bỏ phải bằng số lượng kiểm / nhận.');
    }
    if (operation === 'quality.rework' && Number(result.good_quantity) + Number(result.scrap_quantity) !== Number(result.quantity)) {
        throw new Error('Số sửa đạt và loại bỏ phải bằng số lượng tái kiểm.');
    }
    if (operation === 'quality.inspect' && result.checkpoint !== 'final' && Number(result.scrap_quantity) > 0) throw new Error('Chỉ ghi loại bỏ tại công đoạn hoặc kiểm cuối.');
    if (operation === 'plan.calendar.save' && result.scope_type !== 'factory' && !result[`${result.scope_type}_id`]) throw new Error('Vui lòng chọn phân xưởng hoặc chuyền tương ứng với phạm vi lịch.');
    if (operation === 'cut.create') {
        for (const row of result.materials ?? []) {
            if (['material_id', 'issue_id', 'reservation_id'].filter((key) => Boolean(row[key])).length !== 1) throw new Error('Mỗi dòng nguyên liệu cần chọn đúng một nguồn: cuộn mới, phiếu cấp hoặc phần đã giữ.');
        }
    }
    if (result.planned_start && result.planned_end && result.planned_start > result.planned_end) throw new Error('Ngày kết thúc không được trước ngày bắt đầu.');
    if (record?.version !== undefined) result.expected_version = record.version;
    else if (values.expected_version !== undefined) result.expected_version = values.expected_version;
    if (values.source_expected_version !== undefined) result.source_expected_version = values.source_expected_version;
    return result;
}
