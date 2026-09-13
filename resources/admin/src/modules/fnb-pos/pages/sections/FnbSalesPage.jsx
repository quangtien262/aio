import ArrowRightOutlined from '@ant-design/icons/ArrowRightOutlined';
import CheckOutlined from '@ant-design/icons/CheckOutlined';
import CoffeeOutlined from '@ant-design/icons/CoffeeOutlined';
import CreditCardOutlined from '@ant-design/icons/CreditCardOutlined';
import DeleteOutlined from '@ant-design/icons/DeleteOutlined';
import EditOutlined from '@ant-design/icons/EditOutlined';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import PrinterOutlined from '@ant-design/icons/PrinterOutlined';
import SendOutlined from '@ant-design/icons/SendOutlined';
import ShopOutlined from '@ant-design/icons/ShopOutlined';
import SwapOutlined from '@ant-design/icons/SwapOutlined';
import Alert from 'antd/es/alert';
import App from 'antd/es/app';
import Badge from 'antd/es/badge';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Checkbox from 'antd/es/checkbox';
import Drawer from 'antd/es/drawer';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import List from 'antd/es/list';
import Modal from 'antd/es/modal';
import Divider from 'antd/es/divider';
import Popconfirm from 'antd/es/popconfirm';
import Segmented from 'antd/es/segmented';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Spin from 'antd/es/spin';
import Tabs from 'antd/es/tabs';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createIdempotencyKey } from '../../api/fnbApi';
import FnbPageHeader from '../../components/FnbPageHeader';
import { FnbResourceError } from '../../components/FnbResourceState';
import FnbStatusTag from '../../components/FnbStatusTag';
import useFnbResource from '../../hooks/useFnbResource';
import { useFnbWorkspace } from '../../state/FnbWorkspaceContext';
import { asArray, compactIdentifier, formatDateTime, formatMinorMoney, formatQuantity } from '../../utils/fnbFormat';

const { Text, Title } = Typography;

const serviceTypes = [
    { value: 'dine_in', label: 'Tại bàn' },
    { value: 'counter', label: 'Tại quầy' },
    { value: 'takeaway', label: 'Mang đi' },
];

const orderStatus = (order) => order.lifecycle_status ?? order.status ?? 'draft';
const lineStatus = (line) => line.status ?? line.fulfillment_status ?? 'draft';
const checkStatus = (check) => check.lifecycle_status ?? check.status ?? 'draft';
const serverAmountDue = (check) => {
    const explicit = check?.balance_due_minor ?? check?.amount_due_minor ?? check?.payable_total_minor;
    if (explicit !== null && explicit !== undefined) return explicit;
    if (asArray(check?.payments).length) return null;
    return check?.settlement_total_minor ?? check?.grand_total_minor ?? check?.total_minor ?? null;
};

const decimalMicros = (value) => {
    const match = String(value ?? '0').match(/^(\d+)(?:\.(\d{1,6}))?$/);
    if (!match) return 0n;
    return (BigInt(match[1]) * 1000000n) + BigInt((match[2] ?? '').padEnd(6, '0'));
};

const microsDecimal = (value) => {
    const whole = value / 1000000n;
    const fraction = String(value % 1000000n).padStart(6, '0');
    return `${whole}.${fraction}`;
};

const compensableQuantity = (checkLine, orderLine) => {
    if (!checkLine || !orderLine) return null;
    const allocated = decimalMicros(checkLine.allocated_quantity ?? checkLine.quantity);
    const unserved = decimalMicros(orderLine.ordered_quantity ?? orderLine.quantity)
        - decimalMicros(orderLine.fulfilled_quantity)
        - decimalMicros(orderLine.voided_quantity)
        - decimalMicros(orderLine.compensated_quantity);
    const remaining = allocated < unserved ? allocated : unserved;

    return remaining > 0n ? microsDecimal(remaining) : null;
};

function sessionTableId(session) {
    return session.current_table_id ?? session.table_id ?? session.table?.id ?? asArray(session.tables).find((table) => !table.left_at)?.id ?? null;
}

function sessionDisplay(session, tables) {
    const tableId = sessionTableId(session);
    const table = session.table ?? tables.find((candidate) => String(candidate.id) === String(tableId));
    if (table) return table.name ?? table.code;
    if (session.service_type === 'takeaway') return `Mang đi #${session.id}`;
    if (session.service_type === 'counter') return `Tại quầy #${session.id}`;
    return session.session_no ?? `Phiên #${session.id}`;
}

function OpenSessionDrawer({ open, form, areas, tables, occupiedTableIds, customers, customerSearching, canLookupCustomer, canCreateCustomer, canUpdateCustomer, saving, onCustomerSearch, onClose, onSubmit }) {
    const serviceType = Form.useWatch('service_type', form);
    const availableTables = tables.filter((table) => !occupiedTableIds.has(String(table.id)) || String(open?.tableId) === String(table.id));

    return (
        <Drawer
            open={Boolean(open)}
            title="Mở phiên phục vụ"
            width="min(620px, 96vw)"
            onClose={onClose}
            destroyOnHidden
            extra={<Button type="primary" loading={saving} onClick={() => form.submit()}>Mở phiên</Button>}
        >
            <Form form={form} layout="vertical" onFinish={onSubmit}>
                <Form.Item name="service_type" label="Hình thức phục vụ" rules={[{ required: true }]}>
                    <Segmented block options={serviceTypes} />
                </Form.Item>
                {serviceType === 'dine_in' ? (
                    <Form.Item name="table_id" label="Bàn" rules={[{ required: true, message: 'Chọn bàn.' }]}>
                        <Select
                            showSearch
                            optionFilterProp="label"
                            options={availableTables.map((table) => {
                                const area = areas.find((candidate) => candidate.id === table.service_area_id);
                                return { value: table.id, label: `${area?.name ? `${area.name} · ` : ''}${table.name}` };
                            })}
                        />
                    </Form.Item>
                ) : null}
                <Form.Item name="guest_count" label="Số khách" rules={[{ required: true }]}>
                    <InputNumber min={1} max={100} precision={0} style={{ width: '100%' }} />
                </Form.Item>
                {canLookupCustomer ? <>
                    <Form.Item name="customer_profile_id" label="Khách hàng hiện có">
                        <Select
                            allowClear
                            showSearch
                            filterOption={false}
                            onSearch={onCustomerSearch}
                            loading={customerSearching}
                            options={customers.map((customer) => ({ value: customer.id, label: `${customer.name}${customer.phone_normalized ? ` · ${customer.phone_normalized}` : ''}` }))}
                            placeholder="Nhập tên, mã hoặc số điện thoại"
                        />
                    </Form.Item>
                    {canCreateCustomer ? <>
                        <Divider plain>hoặc tạo khách mới (tùy chọn)</Divider>
                        <Form.Item name="new_customer_name" label="Tên khách mới"><Input /></Form.Item>
                        <Form.Item name="new_customer_phone" label="Số điện thoại"><Input inputMode="tel" /></Form.Item>
                        {canUpdateCustomer ? <Form.Item name="privacy_consent" valuePropName="checked"><Checkbox>Khách đồng ý lưu thông tin để phục vụ giao dịch</Checkbox></Form.Item> : null}
                    </> : null}
                </> : null}
                <Alert type="info" showIcon message="Phiên chỉ giữ chỗ sau khi máy chủ xác nhận" description="Nếu nhân viên khác vừa nhận bàn này, hệ thống sẽ báo xung đột và tải lại sơ đồ bàn." />
            </Form>
        </Drawer>
    );
}

function AddItemDrawer({ item, editing = false, form, currency, saving, onClose, onSubmit }) {
    const variants = asArray(item?.variants).filter((variant) => variant.status !== 'inactive');
    const groups = asArray(item?.modifier_groups);

    return (
        <Drawer
            open={Boolean(item)}
            title={item ? `${editing ? 'Sửa' : 'Thêm'} ${item.name}` : 'Thêm món'}
            width="min(640px, 96vw)"
            onClose={onClose}
            destroyOnHidden
            extra={<Button type="primary" loading={saving} onClick={() => form.submit()}>{editing ? 'Lưu thay đổi' : 'Thêm vào order'}</Button>}
        >
            <Form form={form} layout="vertical" onFinish={onSubmit}>
                <Form.Item name="variant_id" label="Size / lựa chọn" rules={[{ required: true, message: 'Chọn size.' }]}>
                    <Select options={variants.map((variant) => ({
                        value: variant.id,
                        label: `${variant.name} · ${formatMinorMoney(variant.price_minor ?? variant.base_price_minor, currency)}`,
                    }))} />
                </Form.Item>
                <Form.Item name="quantity" label="Số lượng" rules={[{ required: true }]}>
                    <InputNumber stringMode min="0.000001" max="999" precision={6} style={{ width: '100%' }} />
                </Form.Item>
                {groups.map((group) => (
                    <Form.Item
                        key={group.id}
                        name={['modifier_groups', String(group.id)]}
                        label={`${group.name} · chọn ${group.min_select ?? 0}–${group.max_select ?? 1}`}
                        rules={[{
                            validator: (_, value = []) => {
                                const min = Number(group.min_select ?? 0);
                                const max = Number(group.max_select ?? 1);
                                return value.length >= min && value.length <= max
                                    ? Promise.resolve()
                                    : Promise.reject(new Error(`Chọn từ ${min} đến ${max} lựa chọn.`));
                            },
                        }]}
                    >
                        <Checkbox.Group className="fnb-modifier-grid">
                            {asArray(group.options).filter((option) => option.status !== 'inactive').map((option) => (
                                <Checkbox value={option.id} key={option.id}>
                                    {option.name} {Number(option.price_delta_minor ?? option.base_price_delta_minor) > 0 ? `(+${formatMinorMoney(option.price_delta_minor ?? option.base_price_delta_minor, currency)})` : ''}
                                </Checkbox>
                            ))}
                        </Checkbox.Group>
                    </Form.Item>
                ))}
                <Form.Item name="note" label="Ghi chú pha chế"><Input.TextArea rows={3} maxLength={500} placeholder="Ví dụ: ít đá, mang ra sau món bánh" /></Form.Item>
                <Alert type="info" showIcon message="Giá, thuế và tuyến Bar do máy chủ chốt" description="POS chỉ gửi size, topping, số lượng và ghi chú." />
            </Form>
        </Drawer>
    );
}

function PaymentDrawer({ open, form, methods, currency, saving, onClose, onSubmit }) {
    const methodId = Form.useWatch('payment_method_id', form);
    const selectedMethod = methods.find((method) => String(method.id) === String(methodId));
    const amount = serverAmountDue(open?.check);

    return (
        <Drawer
            open={Boolean(open)}
            title="Thu tiền"
            width="min(620px, 96vw)"
            onClose={onClose}
            destroyOnHidden
            extra={<Button type="primary" loading={saving} onClick={() => form.submit()}>Xác nhận thu tiền</Button>}
        >
            <Card className="fnb-payment-total" bordered={false}>
                <Text type="secondary">Số tiền máy chủ đang yêu cầu</Text>
                <Title level={2}>{formatMinorMoney(amount, currency)}</Title>
                <Text type="secondary">Bill {open?.check?.check_no ?? `#${open?.check?.id ?? ''}`}</Text>
            </Card>
            <Form form={form} layout="vertical" onFinish={onSubmit}>
                <Form.Item name="settlement_mode" label="Cách thanh toán" rules={[{ required: true }]}>
                    <Select options={[
                        { value: 'single_cash', label: 'Một lần · tiền mặt' },
                        { value: 'single_non_cash', label: 'Một lần · không tiền mặt' },
                        { value: 'mixed', label: 'Nhiều phương thức' },
                    ]} />
                </Form.Item>
                <Form.Item name="payment_method_id" label="Phương thức" rules={[{ required: true, message: 'Chọn phương thức.' }]}>
                    <Select onChange={(value) => {
                        const method = methods.find((candidate) => String(candidate.id) === String(value));
                        if (form.getFieldValue('settlement_mode') !== 'mixed') form.setFieldValue('settlement_mode', method?.kind === 'cash' ? 'single_cash' : 'single_non_cash');
                    }} options={methods.map((method) => ({ value: method.id, label: method.name }))} />
                </Form.Item>
                <Form.Item name="amount_minor" label="Số tiền cần ghi nhận" rules={[{ required: true, message: 'Nhập số tiền.' }]}>
                    <InputNumber min={1} precision={0} controls={false} addonAfter="₫" style={{ width: '100%' }} />
                </Form.Item>
                {selectedMethod?.kind === 'cash' ? (
                    <Form.Item name="tendered_minor" label="Khách đưa"><InputNumber min={0} precision={0} controls={false} addonAfter="₫" style={{ width: '100%' }} /></Form.Item>
                ) : null}
                {selectedMethod?.requires_reference || selectedMethod?.kind !== 'cash' ? (
                    <Form.Item name="reference" label="Mã tham chiếu" rules={selectedMethod?.requires_reference ? [{ required: true, message: 'Nhập mã tham chiếu.' }] : []}><Input maxLength={160} /></Form.Item>
                ) : null}
                <Alert type="warning" showIcon message="Không đóng hoặc tải lại trang khi giao dịch đang xử lý" description="Nếu trạng thái chưa chắc chắn, hãy đối soát thay vì thu lại lần nữa." />
            </Form>
        </Drawer>
    );
}

function CheckAllocationDrawer({ open, form, rows, saving, onClose, onSubmit }) {
    return (
        <Drawer
            open={open}
            title="Tạo / tách bill"
            width="min(700px, 96vw)"
            onClose={onClose}
            destroyOnHidden
            extra={<Button type="primary" loading={saving} onClick={() => form.submit()}>Chốt các dòng đã chọn</Button>}
        >
            <Alert type="info" showIcon message="Có thể tách theo món hoặc một phần số lượng" description="Giới hạn phân bổ được máy chủ kiểm tra lại; giá trị tiền trên bill luôn do máy chủ phân bổ." style={{ marginBottom: 16 }} />
            <Form form={form} layout="vertical" onFinish={onSubmit}>
                <Form.List name="allocations">
                    {(fields) => <Space direction="vertical" size={10} style={{ width: '100%' }}>
                        {fields.map((field, index) => {
                            const row = rows[index];
                            return <Card size="small" key={field.key}>
                                <div className="fnb-check-allocation">
                                    <Form.Item {...field} name={[field.name, 'selected']} valuePropName="checked" style={{ marginBottom: 0 }}><Checkbox /></Form.Item>
                                    <div><Text strong>{row?.line.item_name_snapshot ?? row?.line.item_name}</Text><div><Text type="secondary">{row?.order.order_no} · còn có thể phân bổ {formatQuantity(row?.remaining)}</Text></div></div>
                                    <Form.Item {...field} name={[field.name, 'quantity']} rules={[{ required: true, message: 'Nhập số lượng.' }, { validator: (_, value) => {
                                        const quantity = decimalMicros(value);
                                        return quantity > 0n && quantity <= (row?.remainingMicros ?? 0n) ? Promise.resolve() : Promise.reject(new Error('Số lượng vượt phần còn lại.'));
                                    } }]} style={{ marginBottom: 0 }}><Input inputMode="decimal" style={{ width: 120 }} /></Form.Item>
                                    <Form.Item {...field} name={[field.name, 'order_line_id']} hidden><Input /></Form.Item>
                                </div>
                            </Card>;
                        })}
                    </Space>}
                </Form.List>
            </Form>
        </Drawer>
    );
}

function ReceiptDrawer({ job, currency, loading, onClose, onRefresh, onRequestPrint, onOpenPrint, onConfirm }) {
    if (!job) return null;
    const snapshot = job.snapshot ?? {};
    const check = snapshot.check ?? {};
    const canConfirm = job.status === 'print_requested';
    const canRequest = ['generated', 'user_confirmed'].includes(job.status);

    return (
        <Drawer
            open
            title={`Phiếu bán hàng · ${check.check_no ?? `#${job.id}`}`}
            width="min(520px, 98vw)"
            onClose={loading ? undefined : onClose}
            extra={<Button icon={<ReloadOutlined />} loading={loading} onClick={onRefresh}>Đồng bộ</Button>}
        >
            <Alert
                type="info"
                showIcon
                message="Đây là phiếu bán hàng, không phải hóa đơn thuế"
                description="Nội dung là snapshot bất biến tại thời điểm tạo phiếu. Xác nhận in là thao tác riêng sau hộp thoại của trình duyệt."
                style={{ marginBottom: 14 }}
            />
            <div className="fnb-receipt-sheet">
                <div className="fnb-receipt-center">
                    <Title level={4}>{snapshot.outlet?.name ?? 'Quán Cafe'}</Title>
                    {snapshot.outlet?.address ? <div>{snapshot.outlet.address}</div> : null}
                    {snapshot.outlet?.phone ? <div>Điện thoại: {snapshot.outlet.phone}</div> : null}
                    <div className="fnb-receipt-heading">PHIẾU BÁN HÀNG</div>
                    <div>{check.check_no}</div>
                    <div>{formatDateTime(check.finalized_at)}</div>
                </div>
                <div className="fnb-receipt-rule" />
                {asArray(snapshot.lines).map((line) => <div className="fnb-receipt-line" key={line.id}>
                    <div className="fnb-receipt-line-main"><span>{formatQuantity(line.quantity)}× {line.item?.name}{line.variant?.name && line.variant.name !== 'Mặc định' ? ` · ${line.variant.name}` : ''}</span><strong>{formatMinorMoney(line.total_minor, check.currency ?? currency)}</strong></div>
                    {asArray(line.modifiers).map((modifier, index) => <div className="fnb-receipt-modifier" key={`${line.id}-${index}`}>+ {modifier.name} × {formatQuantity(modifier.quantity)}</div>)}
                </div>)}
                <div className="fnb-receipt-rule" />
                <div className="fnb-receipt-money"><span>Tạm tính</span><span>{formatMinorMoney(check.subtotal_minor, check.currency ?? currency)}</span></div>
                {Number(check.discount_total_minor) ? <div className="fnb-receipt-money"><span>Giảm giá</span><span>-{formatMinorMoney(check.discount_total_minor, check.currency ?? currency)}</span></div> : null}
                {Number(check.service_charge_total_minor) ? <div className="fnb-receipt-money"><span>Phí phục vụ</span><span>{formatMinorMoney(check.service_charge_total_minor, check.currency ?? currency)}</span></div> : null}
                {Number(check.tax_total_minor) ? <div className="fnb-receipt-money"><span>Thuế</span><span>{formatMinorMoney(check.tax_total_minor, check.currency ?? currency)}</span></div> : null}
                {check.cash_rounding_minor !== null && check.cash_rounding_minor !== undefined && Number(check.cash_rounding_minor) !== 0 ? <div className="fnb-receipt-money"><span>Làm tròn tiền mặt</span><span>{formatMinorMoney(check.cash_rounding_minor, check.currency ?? currency)}</span></div> : null}
                <div className="fnb-receipt-money is-total"><span>Tổng cộng</span><strong>{formatMinorMoney(check.settlement_total_minor ?? check.grand_total_minor, check.currency ?? currency)}</strong></div>
                {asArray(snapshot.payments).map((payment, index) => <div className="fnb-receipt-money" key={index}><span>{payment.method_name_snapshot}</span><span>{formatMinorMoney(payment.amount_minor, check.currency ?? currency)}</span></div>)}
                <div className="fnb-receipt-rule" />
                <div className="fnb-receipt-center">Cảm ơn Quý khách</div>
                <div className="fnb-receipt-center fnb-receipt-disclaimer">PHIẾU NÀY KHÔNG PHẢI HÓA ĐƠN THUẾ</div>
            </div>
            <Space direction="vertical" size={10} style={{ width: '100%', marginTop: 16 }}>
                <Space wrap>
                    {canRequest ? <Button type="primary" icon={<PrinterOutlined />} loading={loading} onClick={onRequestPrint}>{job.status === 'user_confirmed' ? 'In lại' : 'Yêu cầu in'}</Button> : null}
                    {canConfirm ? <Button icon={<PrinterOutlined />} onClick={onOpenPrint}>Mở hộp thoại in</Button> : null}
                    {canConfirm ? <Button type="primary" icon={<CheckOutlined />} loading={loading} onClick={onConfirm}>Xác nhận đã in</Button> : null}
                </Space>
                <Text type="secondary">Trạng thái: <FnbStatusTag status={job.status} /> · số lần yêu cầu {job.request_count ?? 0}{job.reprint_count ? ` · in lại ${job.reprint_count}` : ''}</Text>
            </Space>
        </Drawer>
    );
}

function ReasonDrawer({ action, form, currency, selectedRefundLines, onRefundLinesChange, saving, onClose, onSubmit }) {
    const isRefund = action?.type === 'refund';
    const isCompensation = action?.type === 'compensation';
    const isDiscount = action?.type === 'discount';
    const lines = asArray(action?.check?.lines);
    const title = isDiscount
        ? 'Giảm giá order'
        : isRefund
        ? 'Hoàn tiền giao dịch'
        : isCompensation
            ? 'Bồi hoàn món chưa phục vụ'
            : action?.type === 'void'
                ? 'Hủy món'
                : action?.type === 'cancelRefund'
                    ? 'Hủy yêu cầu hoàn'
                    : 'Hủy giao dịch thanh toán';
    const submitLabel = isDiscount
        ? 'Xác nhận giảm giá'
        : isRefund
        ? 'Yêu cầu hoàn tiền'
        : isCompensation
            ? 'Xác nhận bồi hoàn'
            : action?.type === 'void'
                ? 'Xác nhận hủy món'
                : 'Xác nhận hủy';

    return (
        <Drawer
            open={Boolean(action)}
            title={title}
            width="min(650px, 96vw)"
            onClose={onClose}
            destroyOnHidden
            extra={<Button danger type="primary" loading={saving} onClick={() => form.submit()}>{submitLabel}</Button>}
        >
            <Alert
                type="warning"
                showIcon
                message={isDiscount ? 'Giảm giá là thao tác kiểm soát' : isRefund || isCompensation ? 'Hoàn tiền cần xác thực và có thể cần quản lý khác phê duyệt' : action?.type === 'void' ? 'Món đã gửi Bar được hủy theo trạng thái thực tế' : 'Chỉ hủy khi giao dịch chưa ở trạng thái hoàn tất'}
                description={isDiscount ? 'Máy chủ phân bổ số tiền giảm và tính lại thuế. Sau khi áp dụng, hãy gửi order; không thay đổi cấu trúc dòng món.' : isCompensation ? 'Dùng khi bill đã thu tiền nhưng Bar không thể hoàn tất món. Hệ thống giữ nguyên bill, tạo hoàn tiền và dấu vết bồi hoàn riêng.' : isRefund ? 'Giới hạn hoàn và phân bổ theo dòng bill được máy chủ kiểm tra.' : action?.type === 'void' ? 'Máy chủ kiểm tra trạng thái Bar, cập nhật hao hụt nếu cần và không sửa lịch sử đã chốt.' : 'Nếu trạng thái chưa chắc chắn, hãy đối soát với nhà cung cấp trước khi thao tác.'}
                style={{ marginBottom: 16 }}
            />
            <Form form={form} layout="vertical" onFinish={onSubmit}>
                {isDiscount ? <Form.Item name="amount_minor" label="Số tiền giảm" rules={[{ required: true, message: 'Nhập số tiền giảm.' }]}>
                    <InputNumber min={1} max={action?.maxAmountMinor} precision={0} controls={false} addonAfter="₫" style={{ width: '100%' }} />
                </Form.Item> : null}
                {isCompensation ? <Form.Item name="quantity" label="Số lượng không thể phục vụ" rules={[{ required: true, message: 'Nhập số lượng cần bồi hoàn.' }]}>
                    <InputNumber stringMode min="0.000001" max={action?.maxQuantity} precision={6} controls={false} style={{ width: '100%' }} />
                </Form.Item> : null}
                {isRefund ? <>
                    <Form.Item name="amount_minor" label="Số tiền hoàn" rules={[{ required: true, message: 'Nhập số tiền hoàn.' }]}>
                        <InputNumber min={1} max={action?.payment?.refundable_minor ?? action?.payment?.amount_minor} precision={0} controls={false} addonAfter="₫" style={{ width: '100%' }} />
                    </Form.Item>
                    <Form.Item label="Dòng bill liên quan" required>
                        <Checkbox.Group value={selectedRefundLines} onChange={onRefundLinesChange} style={{ width: '100%' }}>
                            <Space direction="vertical" style={{ width: '100%' }}>
                                {lines.map((line) => (
                                    <Checkbox key={line.id} value={line.id}>
                                        {formatQuantity(line.refundable_quantity ?? line.allocated_quantity ?? line.quantity)}× {line.item_name_snapshot ?? line.name ?? `Dòng #${line.id}`} · {formatMinorMoney(line.total_minor ?? line.allocated_total_minor, currency)}
                                    </Checkbox>
                                ))}
                            </Space>
                        </Checkbox.Group>
                    </Form.Item>
                </> : null}
                <Form.Item name="reason" label="Lý do" rules={[{ required: true, min: 3, message: 'Nhập lý do từ 3 ký tự.' }]}>
                    <Input.TextArea rows={4} maxLength={500} showCount />
                </Form.Item>
            </Form>
        </Drawer>
    );
}

export default function FnbSalesPage() {
    const { message } = App.useApp();
    const { api, outletId, terminalId, selectedOutlet, selectedTerminal, can, runCommand, navigateSection } = useFnbWorkspace();
    const [selectedSessionId, setSelectedSessionId] = useState(null);
    const [categoryId, setCategoryId] = useState('all');
    const [search, setSearch] = useState('');
    const [openSession, setOpenSession] = useState(null);
    const [addItem, setAddItem] = useState(null);
    const [editingLine, setEditingLine] = useState(null);
    const [paymentAction, setPaymentAction] = useState(null);
    const [checkDrawerOpen, setCheckDrawerOpen] = useState(false);
    const [reasonAction, setReasonAction] = useState(null);
    const [transferOpen, setTransferOpen] = useState(false);
    const [selectedRefundLines, setSelectedRefundLines] = useState([]);
    const [customers, setCustomers] = useState([]);
    const [customerSearching, setCustomerSearching] = useState(false);
    const customerSearchSequence = useRef(0);
    const [saving, setSaving] = useState(false);
    const [receiptJob, setReceiptJob] = useState(null);
    const [receiptLoading, setReceiptLoading] = useState(false);
    const [sessionForm] = Form.useForm();
    const [itemForm] = Form.useForm();
    const [paymentForm] = Form.useForm();
    const [checkForm] = Form.useForm();
    const [reasonForm] = Form.useForm();
    const [transferForm] = Form.useForm();
    const loadPos = useCallback(() => api.pos(), [api]);
    const resource = useFnbResource({ enabled: Boolean(outletId && terminalId), loader: loadPos, deps: [outletId, terminalId], pollMs: 15000 });
    const pos = resource.data ?? {};
    const sessions = asArray(pos.active_sessions);
    const menu = pos.published_menu ?? {};
    const items = asArray(menu.items).filter((item) => item.status !== 'inactive' && item.available !== false);
    const categories = asArray(menu.categories);
    const areas = asArray(pos.areas)
        .filter((area) => area.status !== 'inactive')
        .map((area) => ({ ...area, tables: asArray(area.tables).filter((table) => table.status !== 'inactive') }));
    const tables = (asArray(pos.tables).length ? asArray(pos.tables) : areas.flatMap((area) => asArray(area.tables)))
        .filter((table) => table.status !== 'inactive');
    const methods = asArray(pos.payment_methods).filter((method) => method.status !== 'inactive');
    const currency = pos.outlet?.currency ?? menu.outlet?.currency ?? selectedOutlet?.currency ?? 'VND';
    const selectedSession = sessions.find((session) => String(compactIdentifier(session)) === String(selectedSessionId)) ?? null;
    const orders = asArray(selectedSession?.orders);
    const checks = asArray(selectedSession?.checks);
    const orderLinesById = useMemo(() => {
        const entries = orders.flatMap((order) => asArray(order.lines).map((line) => [String(line.id), { order, line }]));

        return new Map(entries);
    }, [orders]);
    const occupiedTableIds = useMemo(() => new Set(sessions.map(sessionTableId).filter(Boolean).map(String)), [sessions]);
    const currentCheck = [...checks].reverse().find((check) => ['open', 'finalized'].includes(checkStatus(check))) ?? null;
    const allocationRows = useMemo(() => {
        const allocated = new Map();
        checks.filter((check) => !['void', 'voided', 'cancelled'].includes(checkStatus(check))).forEach((check) => {
            asArray(check.lines).forEach((line) => {
                const key = String(line.order_line_id);
                allocated.set(key, (allocated.get(key) ?? 0n) + decimalMicros(line.allocated_quantity ?? line.quantity));
            });
        });
        return orders.filter((order) => orderStatus(order) !== 'draft').flatMap((order) => asArray(order.lines).map((line) => {
            const available = decimalMicros(line.ordered_quantity ?? line.quantity)
                - decimalMicros(line.voided_quantity)
                - (allocated.get(String(line.id)) ?? 0n);
            return { order, line, remainingMicros: available, remaining: available > 0n ? microsDecimal(available) : '0.000000' };
        })).filter((row) => row.remainingMicros > 0n && !['voided', 'cancelled'].includes(lineStatus(row.line)));
    }, [checks, orders]);
    const menuItems = items.filter((item) => {
        const categoryMatches = categoryId === 'all' || String(item.category_id) === String(categoryId) || asArray(item.category_ids).some((id) => String(id) === String(categoryId));
        const needle = search.trim().toLocaleLowerCase('vi');
        const searchMatches = !needle || `${item.name} ${item.code ?? ''} ${item.sku ?? ''}`.toLocaleLowerCase('vi').includes(needle);
        return categoryMatches && searchMatches;
    });

    useEffect(() => {
        if (selectedSessionId && !selectedSession && sessions.length) {
            setSelectedSessionId(compactIdentifier(sessions[0]));
        }
    }, [selectedSession, selectedSessionId, sessions]);

    const reload = useCallback(() => resource.reload({ silent: true }), [resource.reload]);

    const launchSession = (serviceType = 'counter', tableId = null) => {
        sessionForm.resetFields();
        sessionForm.setFieldsValue({ service_type: serviceType, table_id: tableId, guest_count: 1 });
        setOpenSession({ serviceType, tableId });
    };

    const searchCustomers = async (query) => {
        if (String(query).trim().length < 2) {
            setCustomers([]);
            return;
        }
        const sequence = ++customerSearchSequence.current;
        setCustomerSearching(true);
        try {
            const result = await api.read(api.outletPath('customers'), { query: String(query).trim() });
            if (sequence === customerSearchSequence.current) setCustomers(asArray(result.data?.items));
        } catch (error) {
            if (sequence === customerSearchSequence.current) message.error(error.message || 'Không tìm được khách hàng.');
        } finally {
            if (sequence === customerSearchSequence.current) setCustomerSearching(false);
        }
    };

    const createSession = async (values) => {
        const key = createIdempotencyKey('session-open');
        const customerKey = createIdempotencyKey('customer-create');
        setSaving(true);
        try {
            await runCommand({
                execute: async (auth) => {
                    let customerId = values.customer_profile_id ?? null;
                    if (!customerId && values.new_customer_name) {
                        const customerPayload = {
                            name: values.new_customer_name.trim(),
                            phone: values.new_customer_phone || null,
                            ...(can('fnb.customer.update') ? {
                                privacy_consent: Boolean(values.privacy_consent),
                                marketing_consent: false,
                            } : {}),
                        };
                        const created = await api.command('customers', customerPayload, { idempotencyKey: customerKey, ...auth });
                        customerId = created.data?.id ?? null;
                    }
                    return api.command('sessions', {
                        business_day_id: pos.business_day?.id,
                        service_type: values.service_type,
                        table_id: values.service_type === 'dine_in' ? values.table_id : null,
                        guest_count: values.guest_count,
                        customer_profile_id: customerId,
                    }, { idempotencyKey: key, ...auth });
                },
                successMessage: 'Đã mở phiên phục vụ.',
                onSuccess: async (result) => {
                    setOpenSession(null);
                    setSelectedSessionId(compactIdentifier(result?.data));
                    await reload();
                },
                onConflict: reload,
            });
        } finally {
            setSaving(false);
        }
    };

    const launchAddItem = (item) => {
        if (orders.some((order) => orderStatus(order) === 'draft' && Number(order.discount_total_minor) > 0)) {
            message.warning('Order nháp đã có giảm giá. Hãy gửi order này trước khi gọi thêm món.');
            return;
        }
        const variant = asArray(item.variants).find((candidate) => candidate.is_default) ?? asArray(item.variants)[0];
        itemForm.resetFields();
        itemForm.setFieldsValue({ variant_id: variant?.id, quantity: 1, modifier_groups: {} });
        setEditingLine(null);
        setAddItem(item);
    };

    const launchEditLine = (order, line) => {
        const item = items.find((candidate) => String(candidate.id) === String(line.item_id))
            ?? items.find((candidate) => asArray(candidate.variants).some((variant) => String(variant.id) === String(line.variant_id)));
        if (!item) {
            message.warning('Món này không còn trong menu đang phát hành; không thể sửa an toàn tại POS.');
            return;
        }
        const selections = {};
        asArray(line.modifiers).forEach((modifier) => {
            const groupId = String(modifier.modifier_group_id ?? '');
            if (!groupId || !modifier.modifier_option_id) return;
            selections[groupId] = [...(selections[groupId] ?? []), modifier.modifier_option_id];
        });
        if (!asArray(line.modifiers).length) {
            asArray(line.modifier_option_ids).forEach((optionId) => {
                const group = asArray(item.modifier_groups).find((candidate) => asArray(candidate.options).some((option) => String(option.id) === String(optionId)));
                if (group) selections[String(group.id)] = [...(selections[String(group.id)] ?? []), optionId];
            });
        }

        itemForm.resetFields();
        itemForm.setFieldsValue({
            variant_id: line.variant_id,
            quantity: String(line.ordered_quantity ?? line.quantity ?? '1'),
            modifier_groups: selections,
            note: line.note ?? null,
        });
        setAddItem(item);
        setEditingLine({ order, line, item });
    };

    const saveLine = async (values) => {
        if (!selectedSession || !pos.shift) return;
        const orderKey = createIdempotencyKey('order-create');
        const lineKey = createIdempotencyKey(editingLine ? 'order-line-update' : 'order-line');
        const modifierIds = Object.values(values.modifier_groups ?? {}).flat().filter(Boolean);
        setSaving(true);
        try {
            await runCommand({
                execute: async (auth) => {
                    if (editingLine) {
                        return api.command(`order-lines/${editingLine.line.id}`, {
                            variant_id: values.variant_id,
                            quantity: String(values.quantity),
                            modifier_option_ids: modifierIds,
                            note: values.note || null,
                            expected_version: editingLine.order.version,
                            expected_versions: { [`order:${editingLine.order.public_id ?? editingLine.order.id}`]: editingLine.order.version },
                        }, { method: 'PUT', idempotencyKey: lineKey, ...auth });
                    }
                    let order = orders.find((candidate) => orderStatus(candidate) === 'draft');
                    if (!order) {
                        const created = await api.command(`sessions/${selectedSession.id}/orders`, {
                            shift_id: pos.shift.id,
                            note: null,
                            expected_version: selectedSession.version,
                            expected_versions: { [`service_session:${selectedSession.public_id ?? selectedSession.id}`]: selectedSession.version },
                        }, { idempotencyKey: orderKey, ...auth });
                        order = created.data;
                    }
                    if (!order?.id || !order?.version) throw new Error('Máy chủ chưa trả về phiên bản order mới. Vui lòng tải lại.');
                    return api.command(`orders/${order.id}/lines`, {
                        variant_id: values.variant_id,
                        quantity: String(values.quantity),
                        modifier_option_ids: modifierIds,
                        note: values.note || null,
                        expected_version: order.version,
                        expected_versions: { [`order:${order.public_id ?? order.id}`]: order.version },
                    }, { idempotencyKey: lineKey, ...auth });
                },
                successMessage: editingLine ? `Đã cập nhật ${addItem.name}.` : `Đã thêm ${addItem.name}.`,
                onSuccess: async () => { setAddItem(null); setEditingLine(null); await reload(); },
                onConflict: reload,
            });
        } finally {
            setSaving(false);
        }
    };

    const removeDraftLine = async (order, line) => {
        const key = createIdempotencyKey('order-line-remove');
        await runCommand({
            execute: (auth) => api.command(`order-lines/${line.id}`, {
                expected_version: order.version,
                expected_versions: { [`order:${order.public_id ?? order.id}`]: order.version },
            }, { method: 'DELETE', idempotencyKey: key, ...auth }),
            successMessage: 'Đã xóa món khỏi order nháp.',
            onSuccess: reload,
            onConflict: reload,
        });
    };

    const submitOrder = async (order) => {
        const key = createIdempotencyKey('order-submit');
        await runCommand({
            execute: (auth) => api.command(`orders/${order.id}/submit`, {
                expected_version: order.version,
                expected_versions: { [`order:${order.public_id ?? order.id}`]: order.version },
            }, { idempotencyKey: key, ...auth }),
            successMessage: 'Đã gửi order đến Bar / Bếp.',
            onSuccess: reload,
            onConflict: reload,
        });
    };

    const launchVoid = (order, line) => {
        reasonForm.resetFields();
        setReasonAction({ type: 'void', order, line });
    };

    const launchCompensation = (check, checkLine, order, line, maxQuantity) => {
        reasonForm.resetFields();
        reasonForm.setFieldsValue({ quantity: maxQuantity });
        setReasonAction({ type: 'compensation', check, checkLine, order, line, maxQuantity });
    };

    const launchDiscount = (order) => {
        const maxAmountMinor = Math.max(0, Number(order.subtotal_minor ?? 0) - Number(order.discount_total_minor ?? 0));
        if (!maxAmountMinor) {
            message.warning('Order không còn giá trị có thể giảm.');
            return;
        }
        reasonForm.resetFields();
        setReasonAction({ type: 'discount', order, maxAmountMinor });
    };

    const submitReason = async (values) => {
        const action = reasonAction;
        const key = createIdempotencyKey(action?.type ?? 'reason');
        setSaving(true);
        try {
            if (action?.type === 'discount') {
                await runCommand({
                    execute: (auth) => api.command(`orders/${action.order.id}/discount`, {
                        amount_minor: values.amount_minor,
                        reason: values.reason,
                        expected_version: action.order.version,
                        expected_versions: { [`order:${action.order.public_id ?? action.order.id}`]: action.order.version },
                    }, { idempotencyKey: key, ...auth }),
                    successMessage: 'Đã áp dụng giảm giá theo kết quả máy chủ.',
                    title: 'Giảm giá order',
                    reason: values.reason,
                    onSuccess: async () => { setReasonAction(null); await reload(); },
                    onConflict: reload,
                });
            } else if (action?.type === 'void') {
                await runCommand({
                    execute: (auth) => api.command(`order-lines/${action.line.id}/void`, {
                        reason: values.reason,
                        expected_version: action.order.version,
                        expected_versions: { [`order:${action.order.public_id ?? action.order.id}`]: action.order.version },
                    }, { idempotencyKey: key, ...auth }),
                    successMessage: 'Đã ghi nhận yêu cầu hủy món.',
                    reason: values.reason,
                    onSuccess: async () => { setReasonAction(null); await reload(); },
                    onConflict: reload,
                });
            } else if (action?.type === 'compensation') {
                if (!pos.shift?.id) {
                    message.warning('Cần mở ca trên thiết bị này trước khi bồi hoàn.');
                    return;
                }
                await runCommand({
                    execute: (auth) => api.command(`check-lines/${action.checkLine.id}/compensations`, {
                        shift_id: pos.shift.id,
                        quantity: String(values.quantity),
                        expected_version: action.order.version,
                        expected_versions: { [`order:${action.order.public_id ?? action.order.id}`]: action.order.version },
                        reason: values.reason,
                    }, { idempotencyKey: key, ...auth }),
                    successMessage: 'Đã bồi hoàn món không thể phục vụ.',
                    title: 'Bồi hoàn món đã thu tiền',
                    reason: values.reason,
                    onSuccess: async () => { setReasonAction(null); await reload(); },
                    onConflict: reload,
                });
            } else if (action?.type === 'refund') {
                if (!selectedRefundLines.length) {
                    message.warning('Chọn ít nhất một dòng bill liên quan.');
                    return;
                }
                const allocations = asArray(action.check?.lines).filter((line) => selectedRefundLines.includes(line.id)).map((line) => ({
                    check_line_id: line.id,
                                    quantity: String(line.refundable_quantity ?? line.allocated_quantity ?? line.quantity),
                }));
                await runCommand({
                    execute: (auth) => api.command(`payments/${action.payment.id}/refunds`, {
                        shift_id: pos.shift?.id,
                        amount_minor: values.amount_minor,
                        reason: values.reason,
                        allocations,
                    }, { idempotencyKey: key, ...auth }),
                    successMessage: 'Đã ghi nhận yêu cầu hoàn tiền.',
                    reason: values.reason,
                    onSuccess: async () => { setReasonAction(null); setSelectedRefundLines([]); await reload(); },
                    onConflict: reload,
                });
            } else if (action?.type === 'cancelPayment') {
                await runCommand({
                    execute: (auth) => api.command(`payments/${action.payment.id}/cancel`, { reason: values.reason }, { idempotencyKey: key, ...auth }),
                    successMessage: 'Đã hủy giao dịch thanh toán.',
                    onSuccess: async () => { setReasonAction(null); await reload(); },
                    onConflict: reload,
                });
            } else if (action?.type === 'cancelRefund') {
                await runCommand({
                    execute: (auth) => api.command(`refunds/${action.refund.id}/cancel`, { reason: values.reason }, { idempotencyKey: key, ...auth }),
                    successMessage: 'Đã hủy yêu cầu hoàn tiền.',
                    reason: values.reason,
                    onSuccess: async () => { setReasonAction(null); await reload(); },
                    onConflict: reload,
                });
            }
        } finally {
            setSaving(false);
        }
    };

    const preparePayment = async (requestedAllocations = null) => {
        if (!selectedSession) return;
        let check = currentCheck;
        const checkKey = createIdempotencyKey('check-create');
        const finalizeKey = createIdempotencyKey('check-finalize');
        setSaving(true);
        try {
            const prepared = await runCommand({
                execute: async (auth) => {
                    if (!check) {
                        const allocations = requestedAllocations ?? allocationRows.map((row) => ({ order_line_id: row.line.id, quantity: row.remaining }));
                        if (!allocations.length) throw new Error('Chưa có món hợp lệ để tạo bill.');
                        const created = await api.command(`sessions/${selectedSession.id}/checks`, {
                            allocations,
                            expected_version: selectedSession.version,
                            expected_versions: { [`service_session:${selectedSession.public_id ?? selectedSession.id}`]: selectedSession.version },
                        }, { idempotencyKey: checkKey, ...auth });
                        check = created.data;
                    }
                    if (checkStatus(check) === 'draft') {
                        const finalized = await api.command(`checks/${check.id}/finalize`, {
                            expected_version: check.version,
                            expected_versions: { [`check:${check.public_id ?? check.id}`]: check.version },
                        }, { idempotencyKey: finalizeKey, ...auth });
                        check = finalized.data;
                    }
                    return { data: check, meta: {} };
                },
                onConflict: reload,
            });
            check = prepared?.data ?? check;
            if (check?.id) {
                const defaultMethod = methods[0];
                const settlementMode = defaultMethod?.kind === 'cash' ? 'single_cash' : 'single_non_cash';
                paymentForm.resetFields();
                paymentForm.setFieldsValue({
                    settlement_mode: settlementMode,
                    payment_method_id: defaultMethod?.id,
                    amount_minor: serverAmountDue(check),
                    tendered_minor: serverAmountDue(check),
                });
                setPaymentAction({ check });
                await reload();
            }
        } finally {
            setSaving(false);
        }
    };

    const beginPayment = () => {
        if (currentCheck) {
            preparePayment();
            return;
        }
        if (!allocationRows.length) {
            message.warning('Không còn dòng món nào để tạo bill mới.');
            return;
        }
        checkForm.resetFields();
        checkForm.setFieldsValue({ allocations: allocationRows.map((row) => ({ selected: true, order_line_id: row.line.id, quantity: row.remaining })) });
        setCheckDrawerOpen(true);
    };

    const createAllocatedCheck = async (values) => {
        const allocations = asArray(values.allocations).filter((row) => row.selected).map((row) => ({
            order_line_id: row.order_line_id,
            quantity: String(row.quantity),
        }));
        if (!allocations.length) {
            message.warning('Chọn ít nhất một dòng món cho bill.');
            return;
        }
        setCheckDrawerOpen(false);
        await preparePayment(allocations);
    };

    const collectPayment = async (values) => {
        let check = paymentAction?.check;
        const planKey = createIdempotencyKey('check-plan');
        const paymentKey = createIdempotencyKey('payment');
        setSaving(true);
        try {
            await runCommand({
                execute: async (auth) => {
                    if (check.settlement_mode !== values.settlement_mode) {
                        const planned = await api.command(`checks/${check.id}/settlement-plan`, {
                            mode: values.settlement_mode,
                            expected_version: check.version,
                            expected_versions: { [`check:${check.public_id ?? check.id}`]: check.version },
                        }, { idempotencyKey: planKey, ...auth });
                        check = planned.data;
                    }
                    return api.command(`checks/${check.id}/payments`, {
                        shift_id: pos.shift.id,
                        payment_method_id: values.payment_method_id,
                        amount_minor: values.amount_minor,
                        tendered_minor: values.tendered_minor ?? values.amount_minor,
                        reference: values.reference || null,
                        expected_version: check.version,
                        expected_versions: { [`check:${check.public_id ?? check.id}`]: check.version },
                    }, { idempotencyKey: paymentKey, ...auth });
                },
                successMessage: 'Đã ghi nhận thanh toán.',
                onSuccess: async () => { setPaymentAction(null); await reload(); },
                onConflict: reload,
            });
        } finally {
            setSaving(false);
        }
    };

    const transferSession = async (values) => {
        const key = createIdempotencyKey('session-transfer');
        setSaving(true);
        try {
            await runCommand({
                execute: (auth) => api.command(`sessions/${selectedSession.id}/transfer-table`, {
                    table_id: values.table_id,
                    expected_version: selectedSession.version,
                    expected_versions: { [`service_session:${selectedSession.public_id ?? selectedSession.id}`]: selectedSession.version },
                }, { idempotencyKey: key, ...auth }),
                successMessage: 'Đã chuyển bàn.',
                onSuccess: async () => { setTransferOpen(false); await reload(); },
                onConflict: reload,
            });
        } finally {
            setSaving(false);
        }
    };

    const updateSessionState = async (command) => {
        const key = createIdempotencyKey(`session-${command}`);
        await runCommand({
            execute: (auth) => api.command(`sessions/${selectedSession.id}/${command}`, {
                expected_version: selectedSession.version,
                expected_versions: { [`service_session:${selectedSession.public_id ?? selectedSession.id}`]: selectedSession.version },
            }, { idempotencyKey: key, ...auth }),
            successMessage: command === 'settle' ? 'Phiên đã chuyển sang thanh toán.' : 'Đã đóng phiên phục vụ.',
            onSuccess: async () => { if (command === 'close') setSelectedSessionId(null); await reload(); },
            onConflict: reload,
        });
    };

    const launchRefund = (payment, check) => {
        reasonForm.resetFields();
        reasonForm.setFieldsValue({ amount_minor: payment.refundable_minor ?? payment.amount_minor });
        const lines = asArray(check.lines);
        setSelectedRefundLines(lines.map((line) => line.id));
        setReasonAction({ type: 'refund', payment, check });
    };

    const launchCancellation = (type, resourceRecord) => {
        reasonForm.resetFields();
        setReasonAction({ type, ...(type === 'cancelPayment' ? { payment: resourceRecord } : { refund: resourceRecord }) });
    };

    const openBrowserPrint = useCallback(() => {
        if (typeof window === 'undefined' || typeof document === 'undefined') return;

        document.body.classList.add('fnb-receipt-print-mode');
        try {
            window.print();
        } finally {
            document.body.classList.remove('fnb-receipt-print-mode');
        }
    }, []);

    const generateReceipt = async (check) => {
        const key = createIdempotencyKey('receipt-generate');
        setReceiptLoading(true);
        try {
            await runCommand({
                execute: (auth) => api.command(`checks/${check.id}/receipts`, {
                    expected_version: check.version,
                }, { idempotencyKey: key, ...auth }),
                successMessage: 'Đã tạo snapshot phiếu bán hàng.',
                onSuccess: (result) => setReceiptJob(result?.data ?? null),
                onConflict: reload,
            });
        } finally {
            setReceiptLoading(false);
        }
    };

    const refreshReceipt = async () => {
        if (!receiptJob?.id) return;
        setReceiptLoading(true);
        try {
            const result = await api.read(`print-jobs/${receiptJob.id}`);
            setReceiptJob(result.data);
        } catch (error) {
            message.error(error.message || 'Không đồng bộ được phiếu in.');
        } finally {
            setReceiptLoading(false);
        }
    };

    const requestReceiptPrint = async () => {
        if (!receiptJob?.id) return;
        const key = createIdempotencyKey('receipt-print-request');
        setReceiptLoading(true);
        try {
            await runCommand({
                execute: (auth) => api.command(`print-jobs/${receiptJob.id}/request`, {
                    expected_version: receiptJob.version,
                }, { idempotencyKey: key, ...auth }),
                successMessage: receiptJob.status === 'user_confirmed' ? 'Đã ghi nhận yêu cầu in lại.' : 'Đã ghi nhận yêu cầu in.',
                onSuccess: (result) => {
                    setReceiptJob(result?.data ?? receiptJob);
                    window.requestAnimationFrame(openBrowserPrint);
                },
                onConflict: refreshReceipt,
            });
        } finally {
            setReceiptLoading(false);
        }
    };

    const confirmReceiptPrint = async () => {
        if (!receiptJob?.id) return;
        const key = createIdempotencyKey('receipt-print-confirm');
        setReceiptLoading(true);
        try {
            await runCommand({
                execute: (auth) => api.command(`print-jobs/${receiptJob.id}/confirm`, {
                    expected_version: receiptJob.version,
                }, { idempotencyKey: key, ...auth }),
                successMessage: 'Đã xác nhận phiếu được in.',
                onSuccess: (result) => setReceiptJob(result?.data ?? receiptJob),
                onConflict: refreshReceipt,
            });
        } finally {
            setReceiptLoading(false);
        }
    };

    if (!terminalId) {
        return <Alert type="warning" showIcon message="Chưa có quầy POS khả dụng" description="Chọn hoặc tạo quầy POS trong Thiết lập trước khi bán hàng." action={can('fnb.settings.view') ? <Button onClick={() => navigateSection('settings')}>Mở thiết lập</Button> : null} />;
    }
    if (selectedTerminal?.type && selectedTerminal.type !== 'pos') {
        return <Alert type="warning" showIcon message="Thiết bị đang chọn không phải quầy POS" description="Chọn một thiết bị loại POS ở thanh phía trên để bán hàng." action={can('fnb.settings.view') ? <Button onClick={() => navigateSection('settings')}>Kiểm tra thiết bị</Button> : null} />;
    }
    if (resource.error && !resource.data) {
        return <FnbResourceError error={resource.error} onRetry={resource.reload} title="Không tải được màn hình bán hàng" />;
    }

    const operational = pos.business_day && pos.shift;

    return (
        <div className="fnb-page-stack fnb-pos-page">
            <FnbPageHeader
                eyebrow={selectedTerminal?.name ?? 'Quầy POS'}
                title="Bán hàng"
                description="Order tại quầy hoặc tại bàn; mọi giá và tổng thanh toán lấy từ máy chủ."
                actions={<Button icon={<ReloadOutlined />} loading={resource.refreshing} onClick={reload}>Đồng bộ</Button>}
            />
            {resource.error ? <Alert type="warning" showIcon message="Mất kết nối tạm thời" description={`${resource.error.message} Không gửi lại thanh toán nếu chưa đối soát trạng thái.`} /> : null}
            {!operational ? (
                <Alert
                    type="warning"
                    showIcon
                    message={!pos.business_day ? 'Chưa mở ngày kinh doanh' : 'Chưa mở ca cho quầy đang chọn'}
                    description="POS chỉ nhận order khi ngày kinh doanh và ca thu ngân đều đang mở."
                    action={can('fnb.shift.open') ? <Button type="primary" onClick={() => navigateSection('shifts')}>Mở ca <ArrowRightOutlined /></Button> : null}
                />
            ) : null}

            <div className="fnb-pos-layout">
                <aside className="fnb-pos-sessions">
                    <Card className="fnb-panel" title="Bàn & phiên" extra={can('fnb.order.create') ? <Button size="small" type="primary" onClick={() => launchSession('counter')} disabled={!operational}>+ Quầy</Button> : null}>
                        <Tabs
                            size="small"
                            items={[
                                ...areas.map((area) => ({
                                    key: `area-${area.id}`,
                                    label: area.name,
                                    children: <div className="fnb-table-grid">{asArray(area.tables).map((table) => {
                                        const session = sessions.find((candidate) => String(sessionTableId(candidate)) === String(table.id));
                                        return (
                                            <button
                                                type="button"
                                                key={table.id}
                                                className={`fnb-table-tile ${session ? 'is-occupied' : ''} ${session && String(compactIdentifier(session)) === String(selectedSessionId) ? 'is-selected' : ''}`}
                                                onClick={() => session ? setSelectedSessionId(compactIdentifier(session)) : launchSession('dine_in', table.id)}
                                                disabled={!session && (!operational || !can('fnb.order.create'))}
                                            >
                                                <ShopOutlined />
                                                <strong>{table.name}</strong>
                                                <span>{session ? `${session.guest_count ?? 1} khách` : 'Trống'}</span>
                                            </button>
                                        );
                                    })}</div>,
                                })),
                                {
                                    key: 'other',
                                    label: 'Quầy / mang đi',
                                    children: <List
                                        dataSource={sessions.filter((session) => !sessionTableId(session))}
                                        locale={{ emptyText: 'Chưa có phiên' }}
                                        renderItem={(session) => <List.Item onClick={() => setSelectedSessionId(compactIdentifier(session))} className={String(compactIdentifier(session)) === String(selectedSessionId) ? 'fnb-session-selected' : 'fnb-session-row'}><List.Item.Meta title={sessionDisplay(session, tables)} description={`${session.guest_count ?? 1} khách · ${formatDateTime(session.opened_at)}`} /></List.Item>}
                                    />,
                                },
                            ]}
                        />
                    </Card>
                </aside>

                <main className="fnb-pos-menu">
                    <Card className="fnb-panel" title="Thực đơn" extra={<Input.Search allowClear value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Tìm món" style={{ width: 220 }} />}>
                        <div className="fnb-category-strip">
                            <Button type={categoryId === 'all' ? 'primary' : 'default'} onClick={() => setCategoryId('all')}>Tất cả</Button>
                            {categories.map((category) => <Button key={category.id} type={String(categoryId) === String(category.id) ? 'primary' : 'default'} onClick={() => setCategoryId(category.id)}>{category.name}</Button>)}
                        </div>
                        {resource.loading ? <div className="fnb-centered"><Spin size="large" /></div> : menuItems.length ? (
                            <div className="fnb-product-grid">
                                {menuItems.map((item) => {
                                    const variant = asArray(item.variants).find((candidate) => candidate.is_default) ?? asArray(item.variants)[0];
                                    return (
                                        <button type="button" className="fnb-product-tile" key={item.public_id ?? item.id} disabled={!selectedSession || !operational || !can('fnb.order.update')} onClick={() => launchAddItem(item)}>
                                            <span className="fnb-product-icon">{item.image_url ? <img src={item.image_url} alt="" /> : <CoffeeOutlined />}</span>
                                            <strong>{item.name}</strong>
                                            <span>{formatMinorMoney(variant?.price_minor ?? variant?.base_price_minor, currency)}</span>
                                        </button>
                                    );
                                })}
                            </div>
                        ) : <Empty description="Không có món phù hợp" />}
                    </Card>
                </main>

                <aside className="fnb-pos-order">
                    <Card
                        className="fnb-panel fnb-order-card"
                        title={selectedSession ? sessionDisplay(selectedSession, tables) : 'Order hiện tại'}
                        extra={selectedSession ? <FnbStatusTag status={selectedSession.status} /> : null}
                    >
                        {!selectedSession ? <Empty description="Chọn bàn hoặc mở phiên tại quầy để bắt đầu" /> : <>
                            <div className="fnb-order-meta">
                                <Text type="secondary">{selectedSession.guest_count ?? 1} khách · mở {formatDateTime(selectedSession.opened_at)}</Text>
                                <Space wrap>
                                    {sessionTableId(selectedSession) && can('fnb.order.transfer') ? <Button size="small" icon={<SwapOutlined />} onClick={() => { transferForm.resetFields(); setTransferOpen(true); }}>Chuyển bàn</Button> : null}
                                </Space>
                            </div>
                            <div className="fnb-order-lines">
                                {orders.length ? orders.map((order) => (
                                    <section className="fnb-order-group" key={order.id}>
                                        <div className="fnb-order-group-head"><Text strong>{order.order_no ?? `Order #${order.id}`}</Text><FnbStatusTag status={orderStatus(order)} /></div>
                                        {asArray(order.lines).map((line) => (
                                            <div className="fnb-order-line" key={line.id}>
                                                <div><Text strong>{formatQuantity(line.ordered_quantity ?? line.quantity)}× {line.item_name_snapshot ?? line.item_name}</Text><div><Text type="secondary">{line.variant_name_snapshot}{line.note ? ` · ${line.note}` : ''}</Text></div><FnbStatusTag status={lineStatus(line)} /></div>
                                                <div className="fnb-order-line-price">
                                                    <Text>{formatMinorMoney(line.total_minor, currency)}</Text>
                                                    {orderStatus(order) === 'draft' ? (
                                                        lineStatus(line) === 'draft' && !Number(order.discount_total_minor) && can('fnb.order.update') ? <>
                                                            <Button type="text" size="small" icon={<EditOutlined />} onClick={() => launchEditLine(order, line)} aria-label="Sửa món" />
                                                            <Popconfirm title="Xóa món khỏi order nháp?" description="Thao tác này không dùng cho món đã gửi Bar." okText="Xóa" cancelText="Giữ lại" okButtonProps={{ danger: true }} onConfirm={() => removeDraftLine(order, line)}>
                                                                <Button danger type="text" size="small" icon={<DeleteOutlined />} aria-label="Xóa món nháp" />
                                                            </Popconfirm>
                                                        </> : null
                                                    ) : can('fnb.order.void') && !['voided', 'cancelled', 'cancelled_compensated'].includes(lineStatus(line)) ? <Button danger type="text" size="small" icon={<DeleteOutlined />} onClick={() => launchVoid(order, line)} aria-label="Hủy món" /> : null}
                                                </div>
                                            </div>
                                        ))}
                                        {Number(order.discount_total_minor) > 0 ? <div className="fnb-order-total"><Text type="secondary">Giảm giá đã phân bổ</Text><Text type="danger">-{formatMinorMoney(order.discount_total_minor, currency)}</Text></div> : null}
                                        <div className="fnb-order-total"><Text type="secondary">Tổng order từ máy chủ</Text><Text strong>{formatMinorMoney(order.grand_total_minor, currency)}</Text></div>
                                        {orderStatus(order) === 'draft' ? <div className="fnb-order-command-stack">
                                            {can('fnb.discount.apply') ? <Button onClick={() => launchDiscount(order)} disabled={!asArray(order.lines).length || Number(order.discount_total_minor) >= Number(order.subtotal_minor)}>Giảm giá order</Button> : null}
                                            {can('fnb.order.submit') ? <Button type="primary" icon={<SendOutlined />} block onClick={() => submitOrder(order)} disabled={!asArray(order.lines).length}>Gửi Bar / Bếp</Button> : null}
                                        </div> : null}
                                    </section>
                                )) : <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Chưa gọi món" />}
                            </div>

                            {checks.length ? <div className="fnb-check-list">
                                <Text strong>Bill & thanh toán</Text>
                                {checks.map((check) => <Card size="small" key={check.id} title={check.check_no ?? `Bill #${check.id}`} extra={<FnbStatusTag status={checkStatus(check)} />}>
                                    {asArray(check.lines).map((checkLine) => {
                                        const source = orderLinesById.get(String(checkLine.order_line_id));
                                        const maxQuantity = compensableQuantity(checkLine, source?.line);
                                        const canCompensate = checkStatus(check) === 'closed'
                                            && ['waiting', 'preparing', 'ready'].includes(lineStatus(source?.line ?? {}))
                                            && Boolean(maxQuantity && pos.shift?.id)
                                            && can('fnb.order.void')
                                            && can('fnb.payment.refund');

                                        return <div className="fnb-payment-row fnb-check-line-row" key={checkLine.id}>
                                            <span>{formatQuantity(checkLine.allocated_quantity)}× {source?.line?.item_name_snapshot ?? `Dòng bill #${checkLine.id}`} · {formatMinorMoney(checkLine.allocated_total_minor, currency)}</span>
                                            {canCompensate ? <Button danger size="small" onClick={() => launchCompensation(check, checkLine, source.order, source.line, maxQuantity)}>Không thể phục vụ</Button> : null}
                                        </div>;
                                    })}
                                    <div className="fnb-order-total"><Text>Số phải thu</Text><Text strong>{formatMinorMoney(serverAmountDue(check), currency)}</Text></div>
                                    {asArray(check.payments).map((payment) => <div key={payment.id}>
                                        <div className="fnb-payment-row">
                                            <span><Badge status={payment.status === 'succeeded' ? 'success' : payment.status === 'uncertain' ? 'warning' : 'default'} /> {payment.method_name_snapshot ?? payment.method_code_snapshot ?? 'Thanh toán'} · {formatMinorMoney(payment.amount_minor, currency)}</span>
                                            <Space>
                                                {payment.status === 'succeeded' && can('fnb.payment.refund') ? <Button danger size="small" onClick={() => launchRefund(payment, check)}>Hoàn</Button> : null}
                                                {['reserved', 'processing'].includes(payment.status) && can('fnb.payment.collect') ? <Button danger size="small" onClick={() => launchCancellation('cancelPayment', payment)}>Hủy</Button> : null}
                                            </Space>
                                        </div>
                                        {asArray(payment.refunds).map((refund) => <div className="fnb-refund-row" key={refund.id}>
                                            <span>Hoàn {formatMinorMoney(refund.amount_minor, currency)} · <FnbStatusTag status={refund.status} /></span>
                                            {['reserved', 'requested', 'pending'].includes(refund.status) && can('fnb.payment.refund') ? <Button danger type="link" size="small" onClick={() => launchCancellation('cancelRefund', refund)}>Hủy yêu cầu</Button> : null}
                                        </div>)}
                                    </div>)}
                                    {check.finalized_at && can('fnb.payment.collect') ? <Button block icon={<PrinterOutlined />} onClick={() => generateReceipt(check)}>In phiếu bán hàng</Button> : null}
                                </Card>)}
                            </div> : null}

                            <div className="fnb-pos-actions">
                                {can('fnb.payment.collect') ? <Button type="primary" size="large" icon={<CreditCardOutlined />} onClick={beginPayment} loading={saving} disabled={!orders.length || !methods.length}>Thanh toán / tách bill</Button> : null}
                                {selectedSession.status === 'open' && can('fnb.order.update') ? <Button size="large" onClick={() => updateSessionState('settle')}>Yêu cầu tính tiền</Button> : null}
                                {selectedSession.status === 'settling' && can('fnb.order.update') ? <Button size="large" icon={<CheckOutlined />} onClick={() => updateSessionState('close')}>Đóng phiên</Button> : null}
                            </div>
                        </>}
                    </Card>
                </aside>
            </div>

            <OpenSessionDrawer open={openSession} form={sessionForm} areas={areas} tables={tables} occupiedTableIds={occupiedTableIds} customers={customers} customerSearching={customerSearching} canLookupCustomer={can('fnb.customer.lookup')} canCreateCustomer={can('fnb.customer.create')} canUpdateCustomer={can('fnb.customer.update')} saving={saving} onCustomerSearch={searchCustomers} onClose={() => setOpenSession(null)} onSubmit={createSession} />
            <AddItemDrawer item={addItem} editing={Boolean(editingLine)} form={itemForm} currency={currency} saving={saving} onClose={() => { setAddItem(null); setEditingLine(null); }} onSubmit={saveLine} />
            <PaymentDrawer open={paymentAction} form={paymentForm} methods={methods} currency={currency} saving={saving} onClose={() => setPaymentAction(null)} onSubmit={collectPayment} />
            <CheckAllocationDrawer open={checkDrawerOpen} form={checkForm} rows={allocationRows} saving={saving} onClose={() => setCheckDrawerOpen(false)} onSubmit={createAllocatedCheck} />
            <ReasonDrawer action={reasonAction} form={reasonForm} currency={currency} selectedRefundLines={selectedRefundLines} onRefundLinesChange={setSelectedRefundLines} saving={saving} onClose={() => setReasonAction(null)} onSubmit={submitReason} />
            <ReceiptDrawer
                job={receiptJob}
                currency={currency}
                loading={receiptLoading}
                onClose={() => setReceiptJob(null)}
                onRefresh={refreshReceipt}
                onRequestPrint={requestReceiptPrint}
                onOpenPrint={openBrowserPrint}
                onConfirm={confirmReceiptPrint}
            />

            <Modal title="Chuyển bàn" open={transferOpen} onCancel={() => setTransferOpen(false)} onOk={() => transferForm.submit()} confirmLoading={saving} okText="Chuyển bàn" cancelText="Hủy">
                <Form form={transferForm} layout="vertical" onFinish={transferSession}>
                    <Form.Item name="table_id" label="Bàn mới" rules={[{ required: true, message: 'Chọn bàn mới.' }]}>
                        <Select showSearch optionFilterProp="label" options={tables.filter((table) => !occupiedTableIds.has(String(table.id))).map((table) => ({ value: table.id, label: table.name }))} />
                    </Form.Item>
                </Form>
            </Modal>
        </div>
    );
}
