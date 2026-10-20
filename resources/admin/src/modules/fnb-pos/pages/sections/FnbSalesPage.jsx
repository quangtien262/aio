import AppstoreOutlined from '@ant-design/icons/AppstoreOutlined';
import ThunderboltOutlined from '@ant-design/icons/ThunderboltOutlined';
import ArrowRightOutlined from '@ant-design/icons/ArrowRightOutlined';
import CheckOutlined from '@ant-design/icons/CheckOutlined';
import CoffeeOutlined from '@ant-design/icons/CoffeeOutlined';
import CreditCardOutlined from '@ant-design/icons/CreditCardOutlined';
import DeleteOutlined from '@ant-design/icons/DeleteOutlined';
import EditOutlined from '@ant-design/icons/EditOutlined';
import FullscreenOutlined from '@ant-design/icons/FullscreenOutlined';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
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
import ConfigProvider from 'antd/es/config-provider';
import Drawer from 'antd/es/drawer';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Modal from 'antd/es/modal';
import Popconfirm from 'antd/es/popconfirm';
import Segmented from 'antd/es/segmented';
import Select from 'antd/es/select';
import Radio from 'antd/es/radio';
import Space from 'antd/es/space';
import Spin from 'antd/es/spin';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { createIdempotencyKey } from '../../api/fnbApi';
import FnbPageHeader from '../../components/FnbPageHeader';
import FnbPaymentModal from '../../components/FnbPaymentModal';
import { FnbResourceError } from '../../components/FnbResourceState';
import FnbStatusTag from '../../components/FnbStatusTag';
import FnbSessionCompletionDrawer from '../../components/FnbSessionCompletionDrawer';
import useFnbResource from '../../hooks/useFnbResource';
import { useFnbWorkspace } from '../../state/FnbWorkspaceContext';
import { isZeroCharge, paymentDefaults, preparePaymentParts, refundAllocations, serverAmountDue } from '../../utils/fnbPayment';
import { asArray, compactIdentifier, formatDateTime, formatMinorMoney, formatQuantity } from '../../utils/fnbFormat';
import { sessionDisplayStatus } from '../../utils/fnbSessionStatus';

const { Text, Title } = Typography;

const moneyInputProps = {
    inputMode: 'numeric',
    formatter: (value) => value === undefined || value === null || value === '' ? '' : String(value).replace(/\B(?=(\d{3})+(?!\d))/g, '.'),
    parser: (value) => (value ?? '').replace(/\./g, ''),
};

const serviceTypes = [
    { value: 'dine_in', label: 'Tại bàn' },
    { value: 'counter', label: 'Tại quầy' },
    { value: 'takeaway', label: 'Mang đi' },
];

const orderStatus = (order) => order.lifecycle_status ?? order.status ?? 'draft';
const lineStatus = (line) => line.status ?? line.fulfillment_status ?? 'draft';
const checkStatus = (check) => check.lifecycle_status ?? check.status ?? 'draft';
const normalizeMenuSearch = (value) => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/gi, 'd').toLocaleLowerCase('vi').trim();

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

function TransferTablePicker({ id, value, onChange, tables, areas, disabled }) {
    const [search, setSearch] = useState('');
    const options = tables.map((table) => ({ ...table, areaName: areas.find((area) => String(area.id) === String(table.service_area_id))?.name ?? '' }));
    const needle = normalizeMenuSearch(search);
    const filtered = options.filter((table) => normalizeMenuSearch(`${table.name} ${table.code ?? ''} ${table.areaName}`).includes(needle));

    return <div className="fnb-transfer-picker">
        <Input.Search allowClear value={search} disabled={disabled} onChange={(event) => setSearch(event.target.value)} placeholder="Tìm tên bàn, mã bàn hoặc khu vực" aria-label="Tìm bàn để chuyển" />
        <div className="fnb-transfer-list-heading"><Text type="secondary">Chỉ hiển thị bàn trống có thể chuyển đến</Text><Tag>{filtered.length} bàn</Tag></div>
        {filtered.length ? <Radio.Group id={id} aria-label="Bàn mới" className="fnb-transfer-table-options" value={value} disabled={disabled} onChange={(event) => onChange?.(event.target.value)}>
            {filtered.map((table) => <Radio key={table.id} value={table.id} className={`fnb-transfer-table-option ${String(value) === String(table.id) ? 'is-selected' : ''}`}>
                <span className="fnb-transfer-table-icon"><ShopOutlined /></span>
                <span className="fnb-transfer-table-copy"><strong>{table.name}</strong><span>{[table.areaName, Number(table.capacity) > 0 ? `${table.capacity} chỗ` : null].filter(Boolean).join(' · ') || table.code}</span></span>
                <Tag color="green">{String(value) === String(table.id) ? 'Đã chọn' : 'Trống'}</Tag>
            </Radio>)}
        </Radio.Group> : <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description={tables.length ? 'Không tìm thấy bàn phù hợp' : 'Hiện không còn bàn trống để chuyển'} />}
    </div>;
}

function OpenSessionModal({ open, form, areas, tables, occupiedTableIds, customers, customerSearching, canLookupCustomer, canCreateCustomer, canUpdateCustomer, saving, onCustomerSearch, onCustomerChange, onCreateCustomer, onClose, onSubmit }) {
    const [quickCreateOpen, setQuickCreateOpen] = useState(false);
    const [customerSaving, setCustomerSaving] = useState(false);
    const [customerForm] = Form.useForm();
    const serviceType = Form.useWatch('service_type', form);
    useEffect(() => {
        if (!open) setQuickCreateOpen(false);
    }, [open]);
    const submitCustomer = async (values) => {
        setCustomerSaving(true);
        try {
            await onCreateCustomer(values, (customer) => {
                form.setFieldValue('customer_profile_id', customer.id);
                setQuickCreateOpen(false);
                customerForm.resetFields();
            });
        } finally { setCustomerSaving(false); }
    };
    const availableTables = tables.filter((table) => !occupiedTableIds.has(String(table.id)) || String(open?.tableId) === String(table.id));

    return (
        <>
        <Modal
            open={Boolean(open)}
            title={<span className="fnb-session-modal-title"><span className="fnb-session-modal-icon"><ShopOutlined /></span><span>Mở phiên phục vụ</span></span>}
            width={560}
            centered
            className="fnb-session-modal"
            onCancel={saving || customerSaving || quickCreateOpen ? undefined : onClose}
            closable={!saving && !customerSaving && !quickCreateOpen}
            maskClosable={!saving && !customerSaving && !quickCreateOpen}
            keyboard={!saving && !customerSaving && !quickCreateOpen}
            destroyOnHidden
            onOk={() => form.submit()}
            okText="Mở phiên"
            cancelText="Hủy"
            confirmLoading={saving}
            okButtonProps={{ icon: <PlusOutlined />, disabled: quickCreateOpen || customerSaving }}
            cancelButtonProps={{ disabled: saving || customerSaving || quickCreateOpen }}
            styles={{ body: { maxHeight: 'min(65dvh, 620px)', overflowY: 'auto', paddingRight: 4 } }}
        >
            <Form disabled={saving || customerSaving} form={form} layout="vertical" onFinish={onSubmit}>
                <Form.Item name="service_type" label="Hình thức phục vụ" rules={[{ required: true }]}>
                    <Segmented block options={serviceTypes.map((option) => ({ ...option, icon: option.value === 'dine_in' ? <ShopOutlined /> : option.value === 'counter' ? <CoffeeOutlined /> : <SendOutlined /> }))} />
                </Form.Item>
                <div className={`fnb-session-modal-fields${serviceType !== 'dine_in' ? ' is-quick' : ''}`}>
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
                </div>
                {canLookupCustomer ? <>
                    <Form.Item name="customer_profile_id" extra="Để trống nếu phục vụ khách lẻ." label={<span className="fnb-session-customer-label"><span>Khách hàng <span className="fnb-session-optional">(tùy chọn)</span></span>{canCreateCustomer ? <Button type="link" size="small" icon={<PlusOutlined />} aria-label="Thêm nhanh khách hàng" disabled={saving || customerSaving} onClick={() => { customerForm.resetFields(); setQuickCreateOpen(true); }}>Thêm mới</Button> : null}</span>}>
                        <Select
                            allowClear
                            showSearch
                            filterOption={false}
                            onSearch={onCustomerSearch}
                            onChange={onCustomerChange}
                            loading={customerSearching}
                            notFoundContent={customerSearching ? <Spin size="small" /> : 'Không có khách hàng phù hợp'}
                            options={customers.map((customer) => ({ value: customer.id, label: `${customer.name}${customer.code ? ` · ${customer.code}` : ''}${customer.phone_normalized ? ` · ${customer.phone_normalized}` : ''}` }))}
                            placeholder="Nhập tên, mã hoặc số điện thoại"
                        />
                    </Form.Item>

                </> : null}
            </Form>
        </Modal>
        <Modal title="Thêm nhanh khách hàng" open={Boolean(open) && quickCreateOpen} width={480} centered zIndex={1100} onCancel={customerSaving ? undefined : () => setQuickCreateOpen(false)} closable={!customerSaving} maskClosable={!customerSaving} confirmLoading={customerSaving} onOk={() => customerForm.submit()} okText="Lưu khách hàng" cancelText="Hủy" cancelButtonProps={{ disabled: customerSaving }} destroyOnHidden>
            <Text type="secondary" style={{ display: 'block', marginBottom: 16 }}>Mã khách hàng được tự động tạo với tiền tố KH khi lưu.</Text>
            <Form form={customerForm} disabled={customerSaving} layout="vertical" onFinish={submitCustomer}>
                <Form.Item name="name" label="Tên khách hàng" rules={[{ required: true, whitespace: true, message: 'Nhập tên khách hàng.' }, { max: 255 }]}><Input autoFocus maxLength={255} /></Form.Item>
                <Form.Item name="phone" label="Số điện thoại"><Input inputMode="tel" maxLength={40} /></Form.Item>
                {canUpdateCustomer ? <Form.Item name="privacy_consent" valuePropName="checked"><Checkbox>Khách đồng ý lưu thông tin để phục vụ giao dịch</Checkbox></Form.Item> : null}
            </Form>
        </Modal>
        </>
    );
}

function AddItemModal({ item, editing = false, form, currency, saving, onClose, onSubmit }) {
    const variants = asArray(item?.variants).filter((variant) => variant.status !== 'inactive');
    const groups = asArray(item?.modifier_groups);

    return (
        <Modal
            open={Boolean(item)}
            title={<span className="fnb-item-modal-title"><span className="fnb-item-modal-icon"><CoffeeOutlined /></span><span>{item ? `${editing ? 'Sửa' : 'Thêm'} ${item.name}` : 'Thêm món'}</span></span>}
            width={560}
            centered
            className="fnb-item-modal"
            onCancel={saving ? undefined : onClose}
            closable={!saving}
            maskClosable={!saving}
            keyboard={!saving}
            destroyOnHidden
            onOk={() => form.submit()}
            okText={editing ? 'Lưu thay đổi' : 'Thêm vào order'}
            cancelText="Hủy"
            confirmLoading={saving}
            okButtonProps={{ icon: editing ? <CheckOutlined /> : <PlusOutlined /> }}
            cancelButtonProps={{ disabled: saving }}
            styles={{ body: { maxHeight: 'min(65dvh, 620px)', overflowY: 'auto', paddingRight: 4 } }}
        >
            <Form form={form} layout="vertical" onFinish={onSubmit} disabled={saving}>
                <div className="fnb-item-modal-fields">
                    <Form.Item name="variant_id" label="Size / lựa chọn" rules={[{ required: true, message: 'Chọn size.' }]}>
                        <Select options={variants.map((variant) => ({
                            value: variant.id,
                            label: `${variant.name} · ${formatMinorMoney(variant.price_minor ?? variant.base_price_minor, currency)}`,
                        }))} />
                    </Form.Item>
                    <Form.Item name="quantity" label="Số lượng" rules={[{ required: true, message: 'Nhập số lượng.' }, { type: 'integer', min: 1, max: 999, message: 'Số lượng phải là số nguyên từ 1 đến 999.' }]}>
                        <InputNumber min={1} max={999} step={1} precision={0} inputMode="numeric" style={{ width: '100%' }} />
                    </Form.Item>
                </div>
                {groups.map((group) => (
                    <Form.Item
                        key={group.id}
                        className="fnb-item-modal-modifiers"
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
                <Form.Item name="note" label="Ghi chú pha chế" className="fnb-item-modal-note"><Input.TextArea autoSize={{ minRows: 2, maxRows: 4 }} maxLength={500} showCount placeholder="Ví dụ: ít đá, ít đường…" /></Form.Item>
            </Form>
        </Modal>
    );
}

function CheckAllocationQuantity({ value }) {
    return <Text strong className="fnb-check-allocation-quantity">{formatQuantity(value)} món</Text>;
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
            <Alert type="info" showIcon message="Chọn món để tách bill" description="Đánh dấu các dòng món muốn đưa vào bill theo số lượng hiển thị." style={{ marginBottom: 16 }} />
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
                                        if (!Number.isInteger(value)) return Promise.reject(new Error('Số lượng phải là số nguyên.'));
                                        const quantity = decimalMicros(value);
                                        return quantity > 0n && quantity <= (row?.remainingMicros ?? 0n) ? Promise.resolve() : Promise.reject(new Error('Số lượng vượt phần còn lại.'));
                                    } }]} style={{ marginBottom: 0 }}><CheckAllocationQuantity /></Form.Item>
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
                    <div>{formatDateTime(check.finalized_at, { timeZone: check.timezone_snapshot ?? snapshot.outlet?.timezone })}</div>
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
            ? 'Hoàn tiền món chưa phục vụ'
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
            ? 'Xác nhận hoàn tiền món'
            : action?.type === 'void'
                ? 'Xác nhận hủy món'
                : 'Xác nhận hủy';

    const content = <>
            <Alert
                type="warning"
                showIcon
                message={isCompensation ? 'Hoàn tiền cho món đã thu tiền nhưng chưa phục vụ' : isDiscount ? 'Giảm giá là thao tác kiểm soát' : isRefund || isCompensation ? 'Hoàn tiền cần xác thực và có thể cần quản lý khác phê duyệt' : action?.type === 'void' ? 'Món đã gửi Bar được hủy theo trạng thái thực tế' : 'Chỉ hủy khi giao dịch chưa ở trạng thái hoàn tất'}
                description={isDiscount ? 'Máy chủ phân bổ số tiền giảm và tính lại thuế. Sau khi áp dụng, hãy gửi order; không thay đổi cấu trúc dòng món.' : isCompensation ? 'Có thể cần xác thực và quản lý phê duyệt trước khi hoàn tiền.' : isRefund ? 'Giới hạn hoàn và phân bổ theo dòng bill được máy chủ kiểm tra.' : action?.type === 'void' ? 'Máy chủ kiểm tra trạng thái Bar, cập nhật hao hụt nếu cần và không sửa lịch sử đã chốt.' : 'Nếu trạng thái chưa chắc chắn, hãy đối soát với nhà cung cấp trước khi thao tác.'}
                style={{ marginBottom: 16 }}
            />
            {isCompensation ? <div className="fnb-refund-item-summary">
                <span className="fnb-refund-item-icon"><CoffeeOutlined /></span>
                <div><Text strong>{action?.line?.item_name_snapshot ?? action?.line?.item_name ?? 'Món cần hoàn tiền'}</Text>
                    <div><Text type="secondary">{[action?.line?.variant_name_snapshot, action?.check?.check_no].filter(Boolean).join(' · ')}</Text></div>
                </div>
            </div> : null}
            <Form form={form} layout="vertical" onFinish={onSubmit} disabled={isCompensation && saving}>
                {isDiscount ? <Form.Item name="amount_minor" label="Số tiền giảm" rules={[{ required: true, message: 'Nhập số tiền giảm.' }]}>
                    <InputNumber min={1} max={action?.maxAmountMinor} {...moneyInputProps} precision={0} controls={false} addonAfter="₫" style={{ width: '100%' }} />
                </Form.Item> : null}
                {isCompensation ? <Form.Item name="quantity" label="Số lượng hoàn" extra={`Có thể hoàn tối đa ${formatQuantity(action?.maxQuantity ?? 0)} món`} rules={[{ required: true, message: 'Nhập số lượng cần hoàn tiền.' }, { type: 'integer', min: 1, max: Math.floor(Number(action?.maxQuantity ?? 0)), message: 'Nhập số lượng nguyên dương, không vượt quá số món có thể hoàn tiền.' }]}>
                    <InputNumber min={1} max={Math.floor(Number(action?.maxQuantity ?? 0))} step={1} precision={0} inputMode="numeric" controls={false} style={{ width: '100%' }} />
                </Form.Item> : null}
                {isRefund ? <>
                    <Form.Item name="amount_minor" label="Số tiền hoàn" rules={[{ required: true, message: 'Nhập số tiền hoàn.' }]}>
                        <InputNumber min={1} max={action?.payment?.refundable_minor ?? action?.payment?.amount_minor} {...moneyInputProps} precision={0} controls={false} addonAfter="₫" style={{ width: '100%' }} />
                    </Form.Item>
                    <Form.Item label="Dòng bill liên quan" required>
                        <Checkbox.Group value={selectedRefundLines} onChange={onRefundLinesChange} style={{ width: '100%' }}>
                            <Space direction="vertical" style={{ width: '100%' }}>
                                {lines.map((line) => (
                                    <div key={line.id} className="fnb-refund-allocation">
                                        <Checkbox value={line.id} disabled={Number(line.refundable_quantity ?? line.allocated_quantity ?? line.quantity) <= 0}>
                                            {line.item_name_snapshot ?? line.name ?? `Dòng #${line.id}`} · còn {formatQuantity(line.refundable_quantity ?? line.allocated_quantity ?? line.quantity)} · {formatMinorMoney(line.total_minor ?? line.allocated_total_minor, currency)}
                                        </Checkbox>
                                        {selectedRefundLines.includes(line.id) ? <Form.Item name={['refund_quantities', String(line.id)]} label="Số lượng hoàn" rules={[{ required: true }, { validator: (_, value) => {
                                            try { refundAllocations([line], [line.id], { [String(line.id)]: value }); return Promise.resolve(); }
                                            catch (error) { return Promise.reject(error); }
                                        } }]}><InputNumber stringMode min="0.000001" max={line.refundable_quantity ?? line.allocated_quantity ?? line.quantity} precision={6} /></Form.Item> : null}
                                    </div>
                                ))}
                            </Space>
                        </Checkbox.Group>
                    </Form.Item>
                </> : null}
                <Form.Item name="reason" label="Lý do" rules={[{ required: true, min: 3, message: 'Nhập lý do từ 3 ký tự.' }]}>
                    <Input.TextArea rows={isCompensation ? 3 : 4} maxLength={500} showCount placeholder={isCompensation ? 'Ví dụ: Hết nguyên liệu, khách không nhận món…' : undefined} />
                </Form.Item>
            </Form>
    </>;

    if (isCompensation) {
        return <Modal open={Boolean(action)} title="Hoàn tiền món" centered width={520}
            className="fnb-refund-item-modal" destroyOnHidden
            onCancel={saving ? undefined : onClose} closable={!saving} maskClosable={!saving} keyboard={!saving}
            onOk={() => form.submit()} okText="Xác nhận hoàn tiền" cancelText="Hủy"
            confirmLoading={saving} okButtonProps={{ danger: true }} cancelButtonProps={{ disabled: saving }}>
            {content}
        </Modal>;
    }

    return <Drawer open={Boolean(action)} title={title} width="min(650px, 96vw)" onClose={onClose} destroyOnHidden
        extra={<Button danger type="primary" loading={saving} onClick={() => form.submit()}>{submitLabel}</Button>}>
        {content}
    </Drawer>;
}

export default function FnbSalesPage() {
    const pageRef = useRef(null);
    const [fullscreen, setFullscreen] = useState(false);
    const getContainer = useCallback(() => pageRef.current ?? document.body, []);
    const messageConfig = useMemo(() => ({ getContainer }), [getContainer]);

    useEffect(() => {
        const changed = () => setFullscreen(document.fullscreenElement === pageRef.current);
        document.addEventListener('fullscreenchange', changed);
        return () => document.removeEventListener('fullscreenchange', changed);
    }, []);

    const toggleFullscreen = async () => {
        if (document.fullscreenElement === pageRef.current) {
            await document.exitFullscreen();
        } else if (pageRef.current?.requestFullscreen) {
            await pageRef.current.requestFullscreen();
        } else {
            throw new Error('Fullscreen unavailable');
        }
    };

    return (
        <div ref={pageRef} className="fnb-pos-fullscreen-container">
            <ConfigProvider getPopupContainer={getContainer}>
                <App component={false} message={messageConfig} notification={messageConfig}>
                    <FnbSalesContent fullscreen={fullscreen} onToggleFullscreen={toggleFullscreen} />
                </App>
            </ConfigProvider>
        </div>
    );
}

function FnbSalesContent({ fullscreen, onToggleFullscreen }) {
    const { message } = App.useApp();
    const { api, outletId, terminalId, selectedOutlet, selectedTerminal, can, runCommand, navigateSection } = useFnbWorkspace();
    const [selectedSessionId, setSelectedSessionId] = useState(null);
    const [categoryId, setCategoryId] = useState('all');
    const [search, setSearch] = useState('');
    const [menuPickerOpen, setMenuPickerOpen] = useState(false);
    const menuSearchRef = useRef(null);
    const [visibleItemCount, setVisibleItemCount] = useState(48);
    const [openSession, setOpenSession] = useState(null);
    const [addItem, setAddItem] = useState(null);
    const [editingLine, setEditingLine] = useState(null);
    const [paymentAction, setPaymentAction] = useState(null);
    const [checkDrawerOpen, setCheckDrawerOpen] = useState(false);
    const [reasonAction, setReasonAction] = useState(null);
    const [transferOpen, setTransferOpen] = useState(false);
    const [completionSessionId, setCompletionSessionId] = useState(null);
    const [selectedRefundLines, setSelectedRefundLines] = useState([]);
    const [customers, setCustomers] = useState([]);
    const [sessionCustomer, setSessionCustomer] = useState(null);
    const customerChoices = sessionCustomer && !customers.some((customer) => customer.id === sessionCustomer.id) ? [...customers, sessionCustomer] : customers;
    const [customerSearching, setCustomerSearching] = useState(false);
    const customerSearchSequence = useRef(0);
    const customerSearchTimer = useRef(null);
    useEffect(() => {
        setCustomers([]);
        setSessionCustomer(null);
        setCustomerSearching(false);
        return () => {
            customerSearchSequence.current += 1;
            window.clearTimeout(customerSearchTimer.current);
        };
    }, [outletId, terminalId]);
    const [saving, setSaving] = useState(false);
    const [receiptJob, setReceiptJob] = useState(null);
    const [receiptLoading, setReceiptLoading] = useState(false);
    const [sessionForm] = Form.useForm();
    const [itemForm] = Form.useForm();
    const [paymentForm] = Form.useForm();
    const [checkForm] = Form.useForm();
    const [reasonForm] = Form.useForm();
    const [transferForm] = Form.useForm();
    const transferTableId = Form.useWatch('table_id', transferForm);
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
    const selectedSessionStatus = sessionDisplayStatus(selectedSession);
    const orders = asArray(selectedSession?.orders);
    const checks = asArray(selectedSession?.checks);
    const orderLinesById = useMemo(() => {
        const entries = orders.flatMap((order) => asArray(order.lines).map((line) => [String(line.id), { order, line }]));

        return new Map(entries);
    }, [orders]);
    const occupiedTableIds = useMemo(() => new Set(sessions.map(sessionTableId).filter(Boolean).map(String)), [sessions]);
    const transferTables = tables.filter((table) => !occupiedTableIds.has(String(table.id)) && (!table.status || table.status === 'available'));
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
    const hasPaymentWork = Boolean(currentCheck || allocationRows.length);
    const menuItems = items.filter((item) => {
        const categoryMatches = categoryId === 'all' || String(item.category_id) === String(categoryId) || asArray(item.category_ids).some((id) => String(id) === String(categoryId));
        const needle = normalizeMenuSearch(search);
        const searchMatches = !needle || normalizeMenuSearch(`${item.name} ${item.code ?? ''} ${item.sku ?? ''}`).includes(needle);
        return categoryMatches && searchMatches;
    });

    useEffect(() => setVisibleItemCount(48), [categoryId, search]);
    useEffect(() => setMenuPickerOpen(false), [selectedSessionId, outletId, terminalId]);

    useEffect(() => {
        if (selectedSessionId && !selectedSession && !resource.loading) {
            setSelectedSessionId(null);
        }
    }, [selectedSession, selectedSessionId, resource.loading]);
    useEffect(() => setSelectedSessionId(null), [outletId, terminalId]);

    const reload = useCallback(() => resource.reload({ silent: true }), [resource.reload]);

    const launchSession = (serviceType = 'counter', tableId = null) => {
        sessionForm.resetFields();
        setSessionCustomer(null);
        sessionForm.setFieldsValue({ service_type: serviceType, table_id: tableId, guest_count: 1 });
        setOpenSession({ serviceType, tableId });
        if (can('fnb.customer.lookup')) searchCustomers('', true);
    };

    const searchCustomers = (query, immediate = false) => {
        const sequence = ++customerSearchSequence.current;
        window.clearTimeout(customerSearchTimer.current);
        setCustomerSearching(true);
        const load = async () => {
            try {
                const result = await api.read(api.outletPath('customers'), { query: String(query ?? '').trim() });
                if (sequence === customerSearchSequence.current) setCustomers(asArray(result.data?.items));
            } catch (error) {
                if (sequence === customerSearchSequence.current) message.error(error.message || 'Không tải được danh sách khách hàng.');
            } finally {
                if (sequence === customerSearchSequence.current) setCustomerSearching(false);
            }
        };
        if (immediate) load();
        else customerSearchTimer.current = window.setTimeout(load, 250);
    };

    const quickCreateCustomer = async (values, onCreated) => {
        const key = createIdempotencyKey('customer-create');
        return runCommand({
            execute: (auth) => api.command('customers', {
                name: values.name.trim(),
                phone: values.phone?.trim() || null,
                ...(can('fnb.customer.update') ? { privacy_consent: Boolean(values.privacy_consent), marketing_consent: false } : {}),
            }, { idempotencyKey: key, ...auth }),
            successMessage: 'Đã thêm khách hàng.',
            onSuccess: (result) => {
                const customer = result.data;
                customerSearchSequence.current += 1;
                window.clearTimeout(customerSearchTimer.current);
                setCustomerSearching(false);
                setCustomers((current) => [...current.filter((item) => item.id !== customer.id), customer]);
                setSessionCustomer(customer);
                onCreated(customer);
            },
        });
    };

    const createSession = async (values) => {
        const key = createIdempotencyKey('session-open');
        setSaving(true);
        try {
            await runCommand({
                execute: (auth) => api.command('sessions', {
                    business_day_id: pos.business_day?.id,
                    service_type: values.service_type,
                    table_id: values.service_type === 'dine_in' ? values.table_id : null,
                    guest_count: values.guest_count,
                    customer_profile_id: values.customer_profile_id ?? null,
                }, { idempotencyKey: key, ...auth }),
                successMessage: 'Đã mở phiên phục vụ.',
                onSuccess: async (result) => {
                    setOpenSession(null);
                    await reload();
                    setSelectedSessionId(compactIdentifier(result?.data));
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
        setMenuPickerOpen(false);
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
            quantity: Number(line.ordered_quantity ?? line.quantity ?? 1),
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
                    let order = selectedSession.status === 'open' ? orders.find((candidate) => orderStatus(candidate) === 'draft') : null;
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
        reasonForm.setFieldsValue({ quantity: Math.floor(Number(maxQuantity)) || null });
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
                const allocations = refundAllocations(asArray(action.check?.lines), selectedRefundLines, values.refund_quantities);
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
                    onConflict: async () => { setReasonAction(null); setSelectedRefundLines([]); await reload(); },
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

    const preparePayment = async (requestedAllocations = null, requestedCheck = null, returnTable = false) => {
        if (!pos.business_day || !pos.shift) { message.warning('Cần mở ngày kinh doanh và ca thu ngân trước khi thu tiền.'); return; }
        if (!selectedSession) return;
        let check = requestedCheck ?? currentCheck;
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
                    if (checkStatus(check) === 'open') {
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
            if (!prepared) return;
            check = prepared.data;
            if (check?.id) {
                paymentForm.resetFields();
                paymentForm.setFieldsValue(paymentDefaults(check, methods));
                setPaymentAction({ check, returnTable, sessionId: selectedSession.id, selectedSessionId });
                await reload();
            }
        } finally {
            setSaving(false);
        }
    };

    const beginPayment = (returnTable = false) => {
        if (!pos.business_day || !pos.shift) { message.warning('Cần mở ngày kinh doanh và ca thu ngân trước khi thu tiền.'); return; }
        if (currentCheck) {
            preparePayment(null, null, returnTable);
            return;
        }
        if (!allocationRows.length) {
            message.warning('Không còn dòng món nào để tạo bill mới.');
            return;
        }
        if (returnTable) {
            preparePayment(null, null, true);
            return;
        }
        checkForm.resetFields();
        checkForm.setFieldsValue({ allocations: allocationRows.map((row) => ({ selected: true, order_line_id: row.line.id, quantity: Math.floor(Number(row.remaining)) })) });
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
        if (!pos.business_day || !pos.shift) { message.warning('Cần mở ngày kinh doanh và ca thu ngân trước khi thu tiền.'); return; }
        let check = paymentAction?.check;
        const paymentContext = paymentAction;
        let parts;
        try {
            parts = isZeroCharge(check) ? { settlement_mode: check.settlement_mode ?? 'single_non_cash', payments: [] } : preparePaymentParts(check, values.payments, methods);
        } catch (exception) { message.warning(exception.message); return; }
        const planKey = createIdempotencyKey('check-plan');
        const paymentKey = createIdempotencyKey('payment');
        setSaving(true);
        try {
            await runCommand({
                execute: async (auth) => {
                    if (check.settlement_mode !== parts.settlement_mode) {
                        const planned = await api.command(`checks/${check.id}/settlement-plan`, {
                            mode: parts.settlement_mode,
                            expected_version: check.version,
                            expected_versions: { [`check:${check.public_id ?? check.id}`]: check.version },
                        }, { idempotencyKey: planKey, ...auth });
                        check = planned.data;
                        // Preserve the acknowledged plan even if the following
                        // payment response is lost; retries must not plan again.
                        setPaymentAction({ ...paymentContext, check });
                        if (parts.settlement_mode !== 'mixed' && !isZeroCharge(check) && parts.payments[0].amount_minor !== serverAmountDue(check)) return { data: check, meta: { planOnly: true } };
                    }
                    if (isZeroCharge(check)) {
                        return api.command(`checks/${check.id}/close-zero-charge`, { shift_id: pos.shift.id, reason: values.zero_charge_reason, expected_version: check.version }, { idempotencyKey: paymentKey, ...auth });
                    }
                    if (serverAmountDue(check) === 0) throw new Error('Bill không còn số tiền có thể thu thêm. Hãy đồng bộ và kiểm tra giao dịch đang xử lý.');
                    return api.command(`checks/${check.id}/payments`, {
                        shift_id: pos.shift.id,
                        payments: parts.payments,
                        expected_version: check.version,
                        expected_versions: { [`check:${check.public_id ?? check.id}`]: check.version },
                    }, { idempotencyKey: paymentKey, ...auth });
                },
                onSuccess: async (result) => {
                    if (result?.meta?.planOnly) {
                        setPaymentAction({ ...paymentContext, check: result.data });
                        paymentForm.setFieldsValue(paymentDefaults(result.data, methods));
                        message.info('Số tiền đã cập nhật theo làm tròn tiền mặt. Kiểm tra và xác nhận lại.');
                        await reload();
                        return;
                    }
                    setPaymentAction(null);
                    message.success('Đã ghi nhận thanh toán.');
                    const refreshed = await reload();
                    if (!paymentContext?.returnTable) return;
                    const session = asArray(refreshed?.data?.active_sessions).find((candidate) => String(candidate.id) === String(paymentContext.sessionId));
                    if (!session) {
                        message.info('Hãy kiểm tra trạng thái bàn sau khi đồng bộ. Không cần thu lại khoản thanh toán vừa ghi nhận.');
                        return;
                    }
                    if (asArray(session.checks).some((bill) => !['closed', 'void'].includes(checkStatus(bill)))) {
                        message.warning('Đã ghi nhận thanh toán; chưa trả bàn vì còn bill chưa thu đủ tiền hoặc giao dịch đang xử lý.');
                        return;
                    }
                    if (asArray(session.orders).some((order) => ['draft', 'active'].includes(orderStatus(order)))) {
                        setCompletionSessionId(paymentContext.selectedSessionId);
                        message.warning('Đã ghi nhận thanh toán. Hãy hoàn tất các món còn lại trước khi trả bàn.');
                        return;
                    }
                    // Close separately from collecting money: a close conflict
                    // never retries or rolls back an acknowledged payment.
                    const closeKey = createIdempotencyKey('paid-session-close');
                    await runCommand({
                        execute: (auth) => api.command(`sessions/${session.id}/close`, {
                            expected_version: session.version,
                            expected_versions: { [`service_session:${session.public_id ?? session.id}`]: session.version },
                        }, { idempotencyKey: closeKey, ...auth }),
                        successMessage: 'Đã thanh toán và trả bàn.',
                        onSuccess: async () => {
                            setSelectedSessionId((current) => String(current) === String(paymentContext.selectedSessionId) ? null : current);
                            await reload();
                        },
                        onConflict: reload,
                    });
                },
                onConflict: async () => { setPaymentAction(null); await reload(); },
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
        setSaving(true);
        try {
            await runCommand({
                execute: (auth) => api.command(`sessions/${selectedSession.id}/${command}`, {
                    expected_version: selectedSession.version,
                    expected_versions: { [`service_session:${selectedSession.public_id ?? selectedSession.id}`]: selectedSession.version },
                }, { idempotencyKey: key, ...auth }),
                successMessage: command === 'settle' ? 'Phiên đã chuyển sang thanh toán.' : 'Đã kết thúc phiên và trả bàn.',
                onSuccess: async () => { if (command === 'close') setSelectedSessionId(null); await reload(); },
                onConflict: reload,
            });
        } finally {
            setSaving(false);
        }
    };

    const launchRefund = (payment, check) => {
        reasonForm.resetFields();
        const lines = asArray(check.lines).filter((line) => Number(line.refundable_quantity ?? line.allocated_quantity ?? line.quantity) > 0);
        reasonForm.setFieldsValue({ amount_minor: payment.refundable_minor ?? payment.amount_minor,
            refund_quantities: Object.fromEntries(lines.map((line) => [String(line.id), String(line.refundable_quantity ?? line.allocated_quantity ?? line.quantity)])),
        });
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

    const operational = Boolean(pos.business_day && pos.shift);

    return (
        <div className="fnb-page-stack fnb-pos-page">
            <FnbPageHeader
                eyebrow={selectedTerminal?.name ?? 'Quầy POS'}
                title="Bán hàng"
                actions={<Space wrap>
                    <Button icon={<FullscreenOutlined />} onClick={() => onToggleFullscreen().catch(() => message.warning('Trình duyệt chưa cho phép mở toàn màn hình.'))}>
                        {fullscreen ? 'Thoát toàn màn hình' : 'Toàn màn hình'}
                    </Button>
                    <Button icon={<ReloadOutlined />} loading={resource.refreshing} onClick={reload}>Đồng bộ</Button>
                </Space>}
            />
            {resource.error ? <Alert type="warning" showIcon message="Mất kết nối tạm thời" description={`${resource.error.message} Không gửi lại thanh toán nếu chưa đối soát trạng thái.`} /> : null}
            {!resource.loading && !operational ? (
                <Alert
                    type="warning"
                    showIcon
                    message={!pos.business_day ? 'Chưa mở ngày kinh doanh' : `Ngày đã mở; chưa mở ca bán hàng tại ${selectedTerminal?.name ?? 'quầy đang chọn'}`}
                    description="Ngày kinh doanh và ca thu ngân là hai bước riêng. Vào Ca bán hàng, chọn Mở ca bán hàng và xác nhận tiền đầu ca để bắt đầu nhận order."
                    action={can('fnb.shift.open') ? <Button type="primary" onClick={() => navigateSection('shifts')}>Mở ca <ArrowRightOutlined /></Button> : null}
                />
            ) : null}

            <div className="fnb-pos-layout fnb-pos-overview">
                <section className="fnb-pos-sessions" aria-label="Khu vực bàn">
                    <Card className="fnb-panel" title={<span className="fnb-pos-panel-title"><span className="fnb-pos-panel-icon"><AppstoreOutlined /></span><span>{areas.length === 1 ? areas[0].name : 'Khu vực bàn'}</span><span className="fnb-pos-panel-count">{tables.length}</span></span>}>
                        <div className="fnb-pos-table-toolbar">
                            <Text type="secondary">Chọn bàn để gọi món và thanh toán</Text>
                            <div className="fnb-pos-table-legend"><span><i />Trống</span><span className="is-occupied"><i />Có khách</span></div>
                        </div>
                        {areas.length ? areas.map((area) => (
                            <section key={area.id} className="fnb-dining-area" aria-label={area.name}>
                                {areas.length > 1 ? <div className="fnb-session-section-heading"><Text strong>{area.name}</Text></div> : null}
                                <div className="fnb-table-grid">{asArray(area.tables).map((table) => {
                                    const session = sessions.find((candidate) => String(sessionTableId(candidate)) === String(table.id));
                                    return (
                                        <button
                                            type="button"
                                            key={table.id}
                                            className={`fnb-table-tile ${session ? 'is-occupied' : ''} ${session && String(compactIdentifier(session)) === String(selectedSessionId) ? 'is-selected' : ''}`}
                                            onClick={() => session ? setSelectedSessionId(compactIdentifier(session)) : launchSession('dine_in', table.id)}
                                            disabled={!session && (!operational || !can('fnb.order.create'))}
                                        >
                                            <span className="fnb-pos-table-icon"><ShopOutlined /></span>
                                            <span className="fnb-pos-table-copy">
                                                <strong>{table.name}</strong>
                                                <span className="fnb-pos-table-caption">{session ? `${session.guest_count ?? 1} khách` : 'Trống'}</span>
                                            </span>
                                        </button>
                                    );
                                })}</div>
                                {!asArray(area.tables).length ? <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Khu vực chưa có bàn" /> : null}
                            </section>
                        )) : <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Chưa có khu vực bàn" />}
                    </Card>
                </section>

                <section className="fnb-pos-quick-sales" aria-label="Mua nhanh">
                    <Card className="fnb-panel" title={<span className="fnb-pos-panel-title"><span className="fnb-pos-panel-icon is-quick"><ThunderboltOutlined /></span><span>Mua nhanh</span><span className="fnb-pos-panel-count">{sessions.filter((session) => !sessionTableId(session)).length}</span></span>}>
                        {can('fnb.order.create') ? <Button block type="primary" icon={<PlusOutlined />} onClick={() => launchSession('counter')} disabled={!operational} className="fnb-quick-session-create">Mở phiên mua nhanh</Button> : null}
                        {sessions.some((session) => !sessionTableId(session)) ? <div className="fnb-counter-session-grid">
                            {sessions.filter((session) => !sessionTableId(session)).map((session) => (
                                <button type="button" key={compactIdentifier(session)} className="fnb-counter-session-tile" onClick={() => setSelectedSessionId(compactIdentifier(session))}>
                                    <span className="fnb-counter-session-heading"><strong>{sessionDisplay(session, tables)}</strong><FnbStatusTag status={sessionDisplayStatus(session)} /></span>
                                    <span className="fnb-counter-session-caption">{session.guest_count ?? 1} khách · {formatDateTime(session.opened_at, { timeZone: session.timezone_snapshot ?? selectedOutlet?.timezone })}</span>
                                    <span className="fnb-counter-session-footer"><span>{asArray(session.orders).reduce((count, order) => count + asArray(order.lines).length, 0)} dòng món</span><strong>Xem chi tiết <ArrowRightOutlined /></strong></span>
                                </button>
                            ))}
                        </div> : <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Chưa có phiên mua nhanh hoặc mang đi" />}
                    </Card>
                </section>
            </div>

            <Drawer
                open={Boolean(selectedSession)}
                title={<Space wrap><span>{selectedSession ? sessionDisplay(selectedSession, tables) : 'Chi tiết phiên'}</span>{selectedSession ? <FnbStatusTag status={selectedSessionStatus} /> : null}</Space>}
                width="min(760px, 100vw)"
                zIndex={900}
                push={false}
                onClose={saving ? undefined : () => setSelectedSessionId(null)}
                closable={!saving}
                maskClosable={!saving}
                keyboard={!saving}
                destroyOnHidden
                className="fnb-session-detail-drawer"
                extra={selectedSession && can('fnb.order.update') ? <Button type="primary" icon={<PlusOutlined />} disabled={!operational || saving || !['open', 'settling'].includes(selectedSession.status)} onClick={() => {
                    setSearch('');
                    setCategoryId('all');
                    setMenuPickerOpen(true);
                }}>Thêm món</Button> : null}
                footer={selectedSession && ((hasPaymentWork && can('fnb.payment.collect')) || (selectedSession.status === 'open' && selectedSessionStatus !== 'paid' && can('fnb.order.update'))) ? (
                    <div className="fnb-pos-actions">
                        {hasPaymentWork && can('fnb.payment.collect') ? <Button type="primary" size="large" icon={<CreditCardOutlined />} onClick={() => beginPayment()} loading={saving} disabled={!operational || !orders.length || !methods.length}>Thanh toán / tách bill</Button> : null}
                        {hasPaymentWork && sessionTableId(selectedSession) && can('fnb.payment.collect') && can('fnb.order.update') ? <Button size="large" icon={<CheckOutlined />} onClick={() => beginPayment(true)} loading={saving} disabled={!operational || !orders.length || !methods.length}>Thanh toán & trả bàn</Button> : null}
                        {selectedSession.status === 'open' && selectedSessionStatus !== 'paid' && can('fnb.order.update') ? <Button size="large" disabled={saving} onClick={() => updateSessionState('settle')}>Yêu cầu tính tiền</Button> : null}
                    </div>
                ) : null}
            >
                {selectedSession ? <div className="fnb-session-details">
                    <div className="fnb-order-meta">
                        <Text type="secondary">{selectedSession.guest_count ?? 1} khách · mở {formatDateTime(selectedSession.opened_at, { timeZone: selectedSession.timezone_snapshot ?? selectedOutlet?.timezone })}</Text>
                        <Space wrap className="fnb-table-actions">
                            {sessionTableId(selectedSession) && can('fnb.order.transfer') ? <Button size="large" className="fnb-table-action fnb-table-transfer" disabled={saving} icon={<SwapOutlined />} onClick={() => { transferForm.resetFields(); setTransferOpen(true); }}>Chuyển bàn</Button> : null}
                            {['open', 'settling'].includes(selectedSession.status) && can('fnb.order.update') ? (
                                <Button type="primary" size="large" className="fnb-table-action" icon={<CheckOutlined />} loading={saving} onClick={() => {
                                    setCompletionSessionId(selectedSessionId);
                                    reload().catch(() => {});
                                }}>
                                    {sessionTableId(selectedSession) ? 'Trả bàn' : 'Kết thúc phiên'}
                                </Button>
                            ) : null}
                        </Space>
                    </div>
                    <div className="fnb-session-section-heading"><Text strong>Món đã gọi</Text><Text type="secondary">{orders.reduce((count, order) => count + asArray(order.lines).length, 0)} dòng món</Text></div>
                    <div className="fnb-order-lines">
                        {orders.length ? orders.map((order) => (
                            <section className="fnb-order-group" key={order.id}>
                                <div className="fnb-order-group-head"><Text strong>{order.order_no ?? `Order #${order.id}`}</Text><FnbStatusTag status={orderStatus(order)} /></div>
                                {asArray(order.lines).map((line) => (
                                    <div className="fnb-order-line" key={line.id}>
                                        <div><Text strong>{formatQuantity(line.ordered_quantity ?? line.quantity)}× {line.item_name_snapshot ?? line.item_name}</Text><div><Text type="secondary">{line.variant_name_snapshot}{line.note ? ` · ${line.note}` : ''}</Text></div>{asArray(line.modifiers).length ? <div className="fnb-order-modifiers">{asArray(line.modifiers).map((modifier) => modifier.option_name_snapshot ?? modifier.name_snapshot ?? modifier.name).join(', ')}</div> : null}<FnbStatusTag status={lineStatus(line)} /></div>
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
                                            {canCompensate ? <Button danger size="small" onClick={() => launchCompensation(check, checkLine, source.order, source.line, maxQuantity)}>Hoàn tiền</Button> : null}
                                        </div>;
                                    })}
                                    <div className="fnb-order-total"><Text>Số phải thu</Text><Text strong>{formatMinorMoney(serverAmountDue(check), currency)}</Text></div>
                                    {['open', 'finalized'].includes(checkStatus(check)) && can('fnb.payment.collect') ? <Button block loading={saving} disabled={!operational} onClick={() => preparePayment(null, check)}>Thu bill này</Button> : null}
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


                </div> : null}
            </Drawer>

            <Modal
                title={selectedSession ? `Thêm món · ${sessionDisplay(selectedSession, tables)}` : 'Chọn món'}
                open={menuPickerOpen && Boolean(selectedSession)}
                onCancel={() => setMenuPickerOpen(false)}
                width={960}
                centered
                footer={<Button onClick={() => setMenuPickerOpen(false)}>Đóng</Button>}
                afterOpenChange={(open) => { if (open) menuSearchRef.current?.focus(); }}
                className="fnb-menu-picker"
            >
                <Input.Search ref={menuSearchRef} allowClear value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Tìm tên món hoặc mã món" aria-label="Tìm món trong thực đơn" />
                <div className="fnb-category-strip">
                    <Button type={categoryId === 'all' ? 'primary' : 'default'} onClick={() => setCategoryId('all')}>Tất cả</Button>
                    {categories.map((category) => <Button key={category.id} type={String(categoryId) === String(category.id) ? 'primary' : 'default'} onClick={() => setCategoryId(category.id)}>{category.name}</Button>)}
                </div>
                <div className="fnb-menu-picker-results">
                    {resource.loading ? <div className="fnb-centered"><Spin size="large" /></div> : menuItems.length ? (
                        <div className="fnb-product-grid">
                            {menuItems.slice(0, visibleItemCount).map((item) => {
                                const variant = asArray(item.variants).find((candidate) => candidate.is_default) ?? asArray(item.variants)[0];
                                return (
                                    <button type="button" className="fnb-product-tile" key={item.public_id ?? item.id} disabled={!selectedSession || !operational || !can('fnb.order.update')} onClick={() => launchAddItem(item)}>
                                        <span className="fnb-product-icon">{item.image_url ? <img src={item.image_url} alt="" loading="lazy" /> : <CoffeeOutlined />}</span>
                                        <strong>{item.name}</strong>
                                        <span>{formatMinorMoney(variant?.price_minor ?? variant?.base_price_minor, currency)}</span>
                                    </button>
                                );
                            })}
                        </div>
                    ) : <Empty description="Không có món phù hợp" />}
                    {menuItems.length > visibleItemCount ? <Button block onClick={() => setVisibleItemCount((count) => count + 48)} style={{ marginTop: 12 }}>Xem thêm món ({menuItems.length - visibleItemCount})</Button> : null}
                </div>
            </Modal>

            <FnbSessionCompletionDrawer
                key={`${outletId}:${terminalId}:${selectedSessionId}`}
                open={Boolean(selectedSession && completionSessionId === selectedSessionId)}
                session={selectedSession}
                title={selectedSession ? sessionDisplay(selectedSession, tables) : 'Phiên phục vụ'}
                refreshing={resource.loading || resource.refreshing}
                error={resource.error}
                saving={saving}
                onReload={reload}
                onClose={() => setCompletionSessionId(null)}
                onSubmitOrder={submitOrder}
                onRelease={() => updateSessionState('close')}
            />
            <OpenSessionModal open={openSession} form={sessionForm} areas={areas} tables={tables} occupiedTableIds={occupiedTableIds} customers={customerChoices} onCustomerChange={(id) => setSessionCustomer(customerChoices.find((customer) => String(customer.id) === String(id)) ?? null)} onCreateCustomer={quickCreateCustomer} customerSearching={customerSearching} canLookupCustomer={can('fnb.customer.lookup')} canCreateCustomer={can('fnb.customer.create')} canUpdateCustomer={can('fnb.customer.update')} saving={saving} onCustomerSearch={searchCustomers} onClose={() => setOpenSession(null)} onSubmit={createSession} />
            <AddItemModal item={addItem} editing={Boolean(editingLine)} form={itemForm} currency={currency} saving={saving} onClose={() => { setAddItem(null); setEditingLine(null); }} onSubmit={saveLine} />
            <FnbPaymentModal open={paymentAction} form={paymentForm} methods={methods} currency={currency} saving={saving} operational={operational} onClose={() => setPaymentAction(null)} onSubmit={collectPayment} />
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

            <Modal title={<Space><SwapOutlined />Chuyển bàn</Space>} open={transferOpen} width={600} centered className="fnb-transfer-table-modal" destroyOnHidden onCancel={saving ? undefined : () => setTransferOpen(false)} closable={!saving} maskClosable={!saving} keyboard={!saving} onOk={() => transferForm.submit()} confirmLoading={saving} okText="Xác nhận chuyển bàn" cancelText="Hủy" cancelButtonProps={{ disabled: saving }} okButtonProps={{ disabled: !transferTables.some((table) => String(table.id) === String(transferTableId)) }}>
                <div className="fnb-transfer-source"><span className="fnb-transfer-table-icon"><ShopOutlined /></span><div><Text type="secondary">Bàn hiện tại</Text><div><Text strong>{selectedSession ? sessionDisplay(selectedSession, tables) : '—'}</Text></div></div><ArrowRightOutlined /><Text strong className="fnb-transfer-destination">{transferTables.find((table) => String(table.id) === String(transferTableId))?.name ?? 'Chọn bàn đích'}</Text></div>
                <Form form={transferForm} layout="vertical" onFinish={transferSession}>
                    <Form.Item name="table_id" label="Chọn bàn đích" rules={[{ required: true, message: 'Chọn bàn mới.' }, { validator: (_, value) => !value || transferTables.some((table) => String(table.id) === String(value)) ? Promise.resolve() : Promise.reject(new Error('Bàn này không còn trống. Vui lòng chọn bàn khác.')) }]}>
                        <TransferTablePicker tables={transferTables} areas={areas} disabled={saving} />
                    </Form.Item>
                </Form>
            </Modal>
        </div>
    );
}
