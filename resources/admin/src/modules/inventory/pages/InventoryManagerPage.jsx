import { adminApi } from '../../../shared/config/routes';
import { useMemo, useState } from 'react';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import SyncOutlined from '@ant-design/icons/SyncOutlined';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Descriptions from 'antd/es/descriptions';
import Drawer from 'antd/es/drawer';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import Modal from 'antd/es/modal';
import Popconfirm from 'antd/es/popconfirm';
import Row from 'antd/es/row';
import Col from 'antd/es/col';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import Statistic from 'antd/es/statistic';
import Switch from 'antd/es/switch';
import AntTable from 'antd/es/table';
import ConfigProvider from 'antd/es/config-provider';
import viVN from 'antd/es/locale/vi_VN';
import '../styles/inventory.css';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import message from 'antd/es/message';
import dayjs from 'dayjs';
import useAdminRouteResource from '../../../shared/hooks/useAdminRouteResource';

const { Text, Title } = Typography;
const Table = (props) => <AntTable scroll={{ x: 'max-content' }} locale={{ emptyText: <Empty description="Chưa có dữ liệu." image={Empty.PRESENTED_IMAGE_SIMPLE} /> }} {...props} />;
const locationTypeLabels = { storage: 'Lưu trữ', receiving: 'Nhận hàng', shipping: 'Xuất hàng', quality: 'Kiểm tra chất lượng' };
const serialStatusLabels = { in_stock: 'Trong kho', issued: 'Đã xuất', reserved: 'Đã giữ', sold: 'Đã bán' };
const syncActionLabels = { created: 'Tạo mới', updated: 'Cập nhật', skipped: 'Bỏ qua', failed: 'Lỗi' };
const barcodeTypeLabels = { item: 'hàng hóa', location: 'vị trí kho', serial: 'số sê-ri' };

const documentTypeOptions = [
    { label: 'Nhập kho', value: 'receipt' },
    { label: 'Xuất kho', value: 'issue' },
    { label: 'Chuyển kho', value: 'transfer' },
    { label: 'Điều chỉnh', value: 'adjustment' },
];

const documentTypeLabels = {
    receipt: 'Nhập kho',
    issue: 'Xuất kho',
    transfer: 'Chuyển kho',
    adjustment: 'Điều chỉnh',
};

async function validateForm(form) {
    try {
        return await form.validateFields();
    } catch (error) {
        if (error?.errorFields) return null;
        throw error;
    }
}

function formatDateTime(value) {
    return value ? dayjs(value).format('DD/MM/YYYY HH:mm') : '-';
}

function formatQuantity(value) {
    return Number(value ?? 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });
}

function resolveEndpoint(sectionKey) {
    if (sectionKey === 'inventory-warehouses') return adminApi('inventory/warehouses');
    if (sectionKey === 'inventory-locations') return adminApi('inventory/locations');
    if (sectionKey === 'inventory-items') return adminApi('inventory/items');
    if (sectionKey === 'inventory-replenishment') return adminApi('inventory/replenishment');
    if (sectionKey === 'inventory-batches') return adminApi('inventory/batches');
    if (sectionKey === 'inventory-balances') return adminApi('inventory/balances');
    if (sectionKey === 'inventory-documents') return adminApi('inventory/documents');
    if (sectionKey === 'inventory-movements') return adminApi('inventory/movements');
    return adminApi('inventory/dashboard');
}

export default function InventoryManagerPage({ moduleMenu, callAdminApi, runAdminAction, currentPermissions }) {
    const sectionKey = moduleMenu?.key ?? 'inventory-dashboard';
    const endpoint = resolveEndpoint(sectionKey);
    const [messageApi, messageContextHolder] = message.useMessage();
    const [warehouseDrawerOpen, setWarehouseDrawerOpen] = useState(false);
    const [locationDrawerOpen, setLocationDrawerOpen] = useState(false);
    const [itemDrawerOpen, setItemDrawerOpen] = useState(false);
    const [documentDrawerOpen, setDocumentDrawerOpen] = useState(false);
    const [editingWarehouse, setEditingWarehouse] = useState(null);
    const [editingLocation, setEditingLocation] = useState(null);
    const [editingItem, setEditingItem] = useState(null);
    const [syncReport, setSyncReport] = useState(null);
    const [syncing, setSyncing] = useState(false);
    const [barcodeCode, setBarcodeCode] = useState('');
    const [barcodeResult, setBarcodeResult] = useState(null);
    const [warehouseForm] = Form.useForm();
    const [locationForm] = Form.useForm();
    const [itemForm] = Form.useForm();
    const [documentForm] = Form.useForm();

    const permissions = useMemo(() => ({
        canManageWarehouse: currentPermissions.includes('inventory.warehouse.manage'),
        canManageLocation: currentPermissions.includes('inventory.location.manage'),
        canManageItem: currentPermissions.includes('inventory.item.manage'),
        canSyncItems: currentPermissions.includes('inventory.item.sync'),
        canCreateDocument: currentPermissions.includes('inventory.receipt.manage')
            || currentPermissions.includes('inventory.issue.manage')
            || currentPermissions.includes('inventory.transfer.manage')
            || currentPermissions.includes('inventory.adjustment.manage'),
    }), [currentPermissions]);

    const { data, loading, error, reload } = useAdminRouteResource({
        enabled: true,
        loader: async () => {
            const payload = await callAdminApi(endpoint);
            const needsReferences = ['inventory-documents', 'inventory-balances', 'inventory-locations'].includes(sectionKey);

            if (!needsReferences) {
                return payload.data ?? null;
            }

            const [warehousePayload, itemPayload, locationPayload] = await Promise.all([
                callAdminApi(adminApi('inventory/warehouses')),
                callAdminApi(adminApi('inventory/items')),
                callAdminApi(adminApi('inventory/locations')),
            ]);

            return {
                ...(payload.data ?? {}),
                references: {
                    warehouses: warehousePayload.data?.items ?? [],
                    items: itemPayload.data?.items ?? [],
                    locations: locationPayload.data?.items ?? [],
                },
            };
        },
        deps: [endpoint, sectionKey],
    });

    const warehouseOptions = useMemo(() => (data?.references?.warehouses ?? data?.items ?? [])
        .filter((warehouse) => warehouse.is_active !== false)
        .map((warehouse) => ({
            value: warehouse.id,
            label: `${warehouse.name} (${warehouse.code})`,
        })), [data]);

    const itemOptions = useMemo(() => (data?.references?.items ?? data?.items ?? [])
        .map((item) => ({
            value: item.id,
            label: item.sku ? `${item.name} (${item.sku})` : item.name,
        })), [data]);

    const locationOptions = useMemo(() => (data?.references?.locations ?? data?.items ?? [])
        .filter((location) => location.is_active !== false)
        .map((location) => ({
            value: location.id,
            label: `${location.name} (${location.code})`,
            warehouseId: location.warehouse_id,
        })), [data]);

    const openCreateWarehouse = () => {
        setEditingWarehouse(null);
        warehouseForm.setFieldsValue({
            code: '',
            name: '',
            phone: '',
            email: '',
            address: '',
            description: '',
            is_default: false,
            is_active: true,
        });
        setWarehouseDrawerOpen(true);
    };

    const openEditWarehouse = (warehouse) => {
        setEditingWarehouse(warehouse);
        warehouseForm.setFieldsValue(warehouse);
        setWarehouseDrawerOpen(true);
    };

    const submitWarehouse = async () => {
        const values = await validateForm(warehouseForm);
        if (!values) return;
        const url = editingWarehouse ? adminApi(`inventory/warehouses/${editingWarehouse.id}`) : adminApi('inventory/warehouses');
        const method = editingWarehouse ? 'PUT' : 'POST';
        const ok = await runAdminAction(
            () => callAdminApi(url, { method, body: JSON.stringify(values) }),
            editingWarehouse ? 'Đã cập nhật kho.' : 'Đã tạo kho.',
            reload,
        );

        if (ok) {
            setWarehouseDrawerOpen(false);
            setEditingWarehouse(null);
        }
    };

    const deleteWarehouse = (warehouse) => runAdminAction(
        () => callAdminApi(adminApi(`inventory/warehouses/${warehouse.id}`), { method: 'DELETE' }),
        'Đã xóa kho.',
        reload,
    );

    const openCreateLocation = () => {
        setEditingLocation(null);
        locationForm.setFieldsValue({
            warehouse_id: warehouseOptions[0]?.value ?? null,
            parent_id: null,
            code: '',
            name: '',
            barcode: '',
            type: 'storage',
            sort_order: 0,
            is_default: false,
            is_active: true,
        });
        setLocationDrawerOpen(true);
    };

    const openEditLocation = (location) => {
        setEditingLocation(location);
        locationForm.setFieldsValue(location);
        setLocationDrawerOpen(true);
    };

    const submitLocation = async () => {
        const values = await validateForm(locationForm);
        if (!values) return;
        const url = editingLocation ? adminApi(`inventory/locations/${editingLocation.id}`) : adminApi('inventory/locations');
        const method = editingLocation ? 'PUT' : 'POST';
        const ok = await runAdminAction(
            () => callAdminApi(url, { method, body: JSON.stringify(values) }),
            editingLocation ? 'Đã cập nhật vị trí kho.' : 'Đã tạo vị trí kho.',
            reload,
        );

        if (ok) {
            setLocationDrawerOpen(false);
            setEditingLocation(null);
        }
    };

    const deleteLocation = (location) => runAdminAction(
        () => callAdminApi(adminApi(`inventory/locations/${location.id}`), { method: 'DELETE' }),
        'Đã xóa vị trí kho.',
        reload,
    );

    const openEditItem = (item) => {
        setEditingItem(item);
        itemForm.setFieldsValue(item);
        setItemDrawerOpen(true);
    };

    const submitItem = async () => {
        const values = await validateForm(itemForm);
        if (!values) return;
        const ok = await runAdminAction(
            () => callAdminApi(adminApi(`inventory/items/${editingItem.id}`), { method: 'PUT', body: JSON.stringify(values) }),
            'Đã cập nhật cấu hình hàng hóa.',
            reload,
        );

        if (ok) {
            setItemDrawerOpen(false);
            setEditingItem(null);
        }
    };

    const syncProducts = async () => {
        try {
            setSyncing(true);
            const payload = await callAdminApi(adminApi('inventory/items/sync-products'), { method: 'POST' });
            setSyncReport(payload.data ?? null);
            messageApi.success('Đã đồng bộ sản phẩm.');
            await reload();
        } catch (nextError) {
            messageApi.error(nextError instanceof Error ? nextError.message : 'Không đồng bộ được sản phẩm.');
        } finally {
            setSyncing(false);
        }
    };

    const lookupBarcode = async () => {
        if (!barcodeCode.trim()) {
            return;
        }

        try {
            const query = new URLSearchParams({ code: barcodeCode.trim() });
            const payload = await callAdminApi(adminApi(`inventory/barcode-lookup?${query.toString()}`));
            setBarcodeResult(payload.data ?? null);
        } catch (nextError) {
            setBarcodeResult({ type: 'not_found', message: nextError instanceof Error ? nextError.message : 'Không tìm thấy.' });
        }
    };

    const openCreateDocument = () => {
        documentForm.setFieldsValue({
            type: 'receipt',
            source_warehouse_id: null,
            destination_warehouse_id: warehouseOptions[0]?.value ?? null,
            reference: '',
            note: '',
            lines: [{ item_id: itemOptions[0]?.value ?? null, source_location_id: null, destination_location_id: null, batch_code: '', expires_at: '', serial_numbers_text: '', quantity: 1, unit_cost: 0, note: '' }],
        });
        setDocumentDrawerOpen(true);
    };

    const submitDocument = async () => {
        const values = await validateForm(documentForm);
        if (!values) return;
        const payload = {
            ...values,
            lines: (values.lines ?? []).map((line) => ({
                ...line,
                serial_numbers: String(line.serial_numbers_text ?? '')
                    .split(/[\n,]+/)
                    .map((value) => value.trim())
                    .filter(Boolean),
            })),
        };
        const ok = await runAdminAction(
            () => callAdminApi(adminApi('inventory/documents'), { method: 'POST', body: JSON.stringify(payload) }),
            'Đã tạo phiếu kho.',
            reload,
        );

        if (ok) {
            setDocumentDrawerOpen(false);
        }
    };

    const renderDashboard = () => (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Row gutter={[16, 16]}>
                <Col xs={24} sm={12} lg={8}><Card><Statistic title="Kho" value={data?.metrics?.warehouses ?? 0} /></Card></Col>
                <Col xs={24} sm={12} lg={8}><Card><Statistic title="Hàng hóa đang bật" value={data?.metrics?.active_items ?? 0} /></Card></Col>
                <Col xs={24} sm={12} lg={8}><Card><Statistic title="Tổng tồn" value={data?.metrics?.total_on_hand ?? 0} precision={0} /></Card></Col>
                <Col xs={24} sm={12} lg={8}><Card><Statistic title="Sắp hết hàng" value={data?.metrics?.low_stock_items ?? 0} /></Card></Col>
                <Col xs={24} sm={12} lg={8}><Card><Statistic title="Hết hàng" value={data?.metrics?.out_of_stock_items ?? 0} /></Card></Col>
                <Col xs={24} sm={12} lg={8}><Card><Statistic title="Phiếu kho" value={data?.metrics?.documents ?? 0} /></Card></Col>
            </Row>

            <Card title="Lịch sử gần đây">
                <MovementTable items={data?.recent_movements ?? []} />
            </Card>

            <Card title="Tra cứu mã vạch / SKU">
                <Space direction="vertical" size={12} style={{ width: '100%' }}>
                    <Input.Search
                        value={barcodeCode}
                        onChange={(event) => setBarcodeCode(event.target.value)}
                        onSearch={lookupBarcode}
                        enterButton="Tìm"
                        placeholder="Quét hoặc nhập mã vạch, SKU, số sê-ri"
                        style={{ maxWidth: 420 }}
                    />
                    {barcodeResult ? <BarcodeResult result={barcodeResult} /> : null}
                </Space>
            </Card>
        </Space>
    );

    const renderWarehouses = () => (
        <Card
            title={`Kho (${data?.total ?? 0})`}
            extra={permissions.canManageWarehouse ? <Button type="primary" icon={<PlusOutlined />} onClick={openCreateWarehouse}>Thêm kho</Button> : null}
        >
            <Table
                rowKey="id"
                dataSource={data?.items ?? []}
                pagination={{ pageSize: 10, hideOnSinglePage: true }}
                columns={[
                    { title: 'Kho', dataIndex: 'name', key: 'name', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.name}</Text><Text type="secondary">{record.code}</Text></Space> },
                    { title: 'Địa chỉ', dataIndex: 'address', key: 'address', render: (value) => value || '-' },
                    { title: 'Trạng thái', dataIndex: 'is_active', key: 'is_active', render: (_, record) => <Space wrap>{record.is_default ? <Tag color="blue">Mặc định</Tag> : null}<Tag color={record.is_active ? 'green' : 'default'}>{record.is_active ? 'Đang bật' : 'Tắt'}</Tag></Space> },
                    { title: 'Tồn', dataIndex: 'items_count', key: 'items_count', render: (value) => value ?? 0 },
                    {
                        title: 'Thao tác',
                        key: 'actions',
                        render: (_, record) => permissions.canManageWarehouse ? (
                            <Space wrap>
                                <Button size="small" onClick={() => openEditWarehouse(record)}>Sửa</Button>
                                <Popconfirm title="Xóa kho này?" onConfirm={() => deleteWarehouse(record)}>
                                    <Button size="small" danger disabled={(record.items_count ?? 0) > 0}>Xóa</Button>
                                </Popconfirm>
                            </Space>
                        ) : null,
                    },
                ]}
            />
        </Card>
    );

    const renderLocations = () => (
        <Card
            title={`Vị trí kho (${data?.total ?? 0})`}
            extra={permissions.canManageLocation ? <Button type="primary" icon={<PlusOutlined />} onClick={openCreateLocation}>Thêm vị trí</Button> : null}
        >
            <Table
                rowKey="id"
                dataSource={data?.items ?? []}
                pagination={{ pageSize: 10 }}
                columns={[
                    { title: 'Vị trí', dataIndex: 'name', key: 'name', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.name}</Text><Text type="secondary">{record.code}</Text></Space> },
                    { title: 'Kho', dataIndex: 'warehouse_name', key: 'warehouse_name' },
                    { title: 'Mã vạch', dataIndex: 'barcode', key: 'barcode', render: (value) => value || '-' },
                    { title: 'Loại', dataIndex: 'type', key: 'type', render: (value) => <Tag>{locationTypeLabels[value] ?? value}</Tag> },
                    { title: 'Trạng thái', dataIndex: 'is_active', key: 'is_active', render: (_, record) => <Space wrap>{record.is_default ? <Tag color="blue">Mặc định</Tag> : null}<Tag color={record.is_active ? 'green' : 'default'}>{record.is_active ? 'Đang bật' : 'Tắt'}</Tag></Space> },
                    {
                        title: 'Thao tác',
                        key: 'actions',
                        render: (_, record) => permissions.canManageLocation ? (
                            <Space wrap>
                                <Button size="small" onClick={() => openEditLocation(record)}>Sửa</Button>
                                <Popconfirm title="Xóa vị trí này?" onConfirm={() => deleteLocation(record)}>
                                    <Button size="small" danger>Xóa</Button>
                                </Popconfirm>
                            </Space>
                        ) : null,
                    },
                ]}
            />
        </Card>
    );

    const renderItems = () => (
        <Card
            title={`Hàng hóa (${data?.total ?? 0})`}
            extra={permissions.canSyncItems ? <Button icon={<SyncOutlined spin={syncing} />} loading={syncing} onClick={syncProducts}>Đồng bộ sản phẩm</Button> : null}
        >
            <Table
                rowKey="id"
                dataSource={data?.items ?? []}
                pagination={{ pageSize: 10 }}
                columns={[
                    { title: 'Hàng hóa', dataIndex: 'name', key: 'name', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.name}</Text><Text type="secondary">{record.sku || 'Chưa có SKU'}</Text></Space> },
                    { title: 'Mã vạch', dataIndex: 'barcode', key: 'barcode', render: (value) => value || '-' },
                    { title: 'ID sản phẩm Catalog', dataIndex: 'catalog_product_id', key: 'catalog_product_id', render: (value) => value || '-' },
                    { title: 'Tổng tồn', dataIndex: 'total_on_hand', key: 'total_on_hand', render: formatQuantity },
                    { title: 'Tối thiểu / tối đa', key: 'reorder', render: (_, record) => `${formatQuantity(record.reorder_min)} / ${formatQuantity(record.reorder_max)}` },
                    { title: 'Truy xuất', key: 'trace', render: (_, record) => <Space wrap>{record.track_batch ? <Tag color="blue">Lô</Tag> : null}{record.track_serial ? <Tag color="purple">Số sê-ri</Tag> : null}</Space> },
                    { title: 'Giá bán', dataIndex: 'sale_price', key: 'sale_price', render: (value) => Number(value ?? 0).toLocaleString('vi-VN') },
                    { title: 'Đồng bộ gần nhất', dataIndex: 'last_synced_at', key: 'last_synced_at', render: formatDateTime },
                    { title: 'Trạng thái', dataIndex: 'is_active', key: 'is_active', render: (value) => <Tag color={value ? 'green' : 'default'}>{value ? 'Đang bật' : 'Tắt'}</Tag> },
                    { title: 'Thao tác', key: 'actions', render: (_, record) => permissions.canManageItem ? <Button size="small" onClick={() => openEditItem(record)}>Cấu hình</Button> : null },
                ]}
            />
        </Card>
    );

    const renderReplenishment = () => (
        <Card title={`Gợi ý bổ sung hàng (${data?.total ?? 0})`}>
            <Table
                rowKey="item_id"
                dataSource={data?.items ?? []}
                pagination={{ pageSize: 10 }}
                columns={[
                    { title: 'Hàng hóa', dataIndex: 'item_name', key: 'item_name', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.item_name}</Text><Text type="secondary">{record.item_sku || '-'}</Text></Space> },
                    { title: 'Tồn hiện tại', dataIndex: 'on_hand', key: 'on_hand', render: formatQuantity },
                    { title: 'Tối thiểu', dataIndex: 'reorder_min', key: 'reorder_min', render: formatQuantity },
                    { title: 'Tối đa', dataIndex: 'reorder_max', key: 'reorder_max', render: formatQuantity },
                    { title: 'Đề xuất nhập', dataIndex: 'suggested_quantity', key: 'suggested_quantity', render: (value) => <Text strong>{formatQuantity(value)}</Text> },
                    { title: 'Nhà cung cấp ưu tiên', dataIndex: 'preferred_supplier', key: 'preferred_supplier', render: (value) => value || '-' },
                    { title: 'Trạng thái', dataIndex: 'status', key: 'status', render: (value) => <Tag color={value === 'out' ? 'red' : 'orange'}>{value === 'out' ? 'Hết hàng' : 'Sắp hết hàng'}</Tag> },
                ]}
            />
        </Card>
    );

    const renderBatches = () => (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card title={`Lô hàng (${data?.total ?? 0})`}>
                <Table
                    rowKey="id"
                    dataSource={data?.items ?? []}
                    pagination={{ pageSize: 10 }}
                    columns={[
                        { title: 'Lô', dataIndex: 'batch_code', key: 'batch_code', render: (value) => <Text strong>{value}</Text> },
                        { title: 'Hàng hóa', dataIndex: 'item_name', key: 'item_name', render: (_, record) => <Space direction="vertical" size={0}><Text>{record.item_name}</Text><Text type="secondary">{record.item_sku || '-'}</Text></Space> },
                        { title: 'Hạn dùng', dataIndex: 'expires_at', key: 'expires_at', render: (value) => value || '-' },
                        { title: 'Trạng thái', dataIndex: 'is_active', key: 'is_active', render: (value) => <Tag color={value ? 'green' : 'default'}>{value ? 'Đang bật' : 'Tắt'}</Tag> },
                    ]}
                />
            </Card>
            <Card title="Số sê-ri gần đây">
                <SerialNumbersTable callAdminApi={callAdminApi} />
            </Card>
        </Space>
    );

    const renderBalances = () => (
        <Card title={`Tồn kho (${data?.total ?? 0})`}>
            <Table
                rowKey="id"
                dataSource={data?.items ?? []}
                pagination={{ pageSize: 10 }}
                columns={[
                    { title: 'Kho', dataIndex: 'warehouse_name', key: 'warehouse_name', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.warehouse_name}</Text><Text type="secondary">{record.warehouse_code}</Text></Space> },
                    { title: 'Hàng hóa', dataIndex: 'item_name', key: 'item_name', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.item_name}</Text><Text type="secondary">{record.item_sku || '-'}</Text></Space> },
                    { title: 'Lô', dataIndex: 'batch_code', key: 'batch_code', render: (value) => value || '-' },
                    { title: 'Tồn thực tế', dataIndex: 'quantity_on_hand', key: 'quantity_on_hand', render: formatQuantity },
                    { title: 'Đã giữ', dataIndex: 'quantity_reserved', key: 'quantity_reserved', render: formatQuantity },
                    { title: 'Có thể dùng', dataIndex: 'quantity_available', key: 'quantity_available', render: formatQuantity },
                    { title: 'Cập nhật', dataIndex: 'last_movement_at', key: 'last_movement_at', render: formatDateTime },
                ]}
            />
        </Card>
    );

    const renderDocuments = () => (
        <Card
            title={`Phiếu kho (${data?.total ?? 0})`}
            extra={permissions.canCreateDocument ? <Button type="primary" icon={<PlusOutlined />} onClick={openCreateDocument}>Tạo phiếu</Button> : null}
        >
            <Table
                rowKey="id"
                dataSource={data?.items ?? []}
                pagination={{ pageSize: 10 }}
                expandable={{
                    expandedRowRender: (record) => (
                        <Table
                            rowKey="id"
                            size="small"
                            pagination={false}
                            dataSource={record.lines ?? []}
                            columns={[
                                { title: 'Hàng hóa', dataIndex: 'item_name', key: 'item_name' },
                                { title: 'SKU', dataIndex: 'item_sku', key: 'item_sku', render: (value) => value || '-' },
                                { title: 'Lô', dataIndex: 'batch_code', key: 'batch_code', render: (value) => value || '-' },
                                { title: 'Số lượng', dataIndex: 'quantity', key: 'quantity', render: formatQuantity },
                                { title: 'Đơn giá vốn', dataIndex: 'unit_cost', key: 'unit_cost', render: (value) => Number(value ?? 0).toLocaleString('vi-VN') },
                            ]}
                        />
                    ),
                }}
                columns={[
                    { title: 'Mã phiếu', dataIndex: 'code', key: 'code', render: (value) => <Text strong>{value}</Text> },
                    { title: 'Loại', dataIndex: 'type', key: 'type', render: (value) => <Tag>{documentTypeLabels[value] ?? value}</Tag> },
                    { title: 'Kho nguồn', dataIndex: 'source_warehouse_name', key: 'source_warehouse_name', render: (value) => value || '-' },
                    { title: 'Kho đích', dataIndex: 'destination_warehouse_name', key: 'destination_warehouse_name', render: (value) => value || '-' },
                    { title: 'Tham chiếu', dataIndex: 'reference', key: 'reference', render: (value) => value || '-' },
                    { title: 'Ngày ghi sổ', dataIndex: 'posted_at', key: 'posted_at', render: formatDateTime },
                ]}
            />
        </Card>
    );

    const renderMovements = () => (
        <Card title={`Lịch sử kho (${data?.total ?? 0})`}>
            <MovementTable items={data?.items ?? []} />
        </Card>
    );

    if (loading) {
        return <Card loading title={moduleMenu?.label ?? 'Quản lý kho'} />;
    }

    if (error) {
        return <Alert type="error" showIcon message={error} />;
    }

    return (
        <ConfigProvider locale={viVN}>
            {messageContextHolder}
            <Space className="inventory-workspace" direction="vertical" size={16} style={{ width: '100%' }}>
                <Card>
                    <Space direction="vertical" size={4}>
                        <Text className="card-label">QUẢN LÝ KHO</Text>
                        <Title level={3} style={{ margin: 0 }}>{moduleMenu?.label ?? 'Tổng quan kho'}</Title>
                        <Text type="secondary">Quản lý nhiều kho, đồng bộ hàng hóa từ CMS/Catalog và theo dõi lịch sử tồn kho.</Text>
                    </Space>
                </Card>

                {sectionKey === 'inventory-dashboard' ? renderDashboard() : null}
                {sectionKey === 'inventory-warehouses' ? renderWarehouses() : null}
                {sectionKey === 'inventory-locations' ? renderLocations() : null}
                {sectionKey === 'inventory-items' ? renderItems() : null}
                {sectionKey === 'inventory-replenishment' ? renderReplenishment() : null}
                {sectionKey === 'inventory-batches' ? renderBatches() : null}
                {sectionKey === 'inventory-balances' ? renderBalances() : null}
                {sectionKey === 'inventory-documents' ? renderDocuments() : null}
                {sectionKey === 'inventory-movements' ? renderMovements() : null}
            </Space>

            <WarehouseDrawer
                open={warehouseDrawerOpen}
                form={warehouseForm}
                warehouse={editingWarehouse}
                onClose={() => setWarehouseDrawerOpen(false)}
                onSubmit={submitWarehouse}
            />

            <LocationDrawer
                open={locationDrawerOpen}
                form={locationForm}
                location={editingLocation}
                warehouseOptions={warehouseOptions}
                locationOptions={locationOptions}
                onClose={() => setLocationDrawerOpen(false)}
                onSubmit={submitLocation}
            />

            <ItemConfigDrawer
                open={itemDrawerOpen}
                form={itemForm}
                item={editingItem}
                onClose={() => setItemDrawerOpen(false)}
                onSubmit={submitItem}
            />

            <DocumentDrawer
                open={documentDrawerOpen}
                form={documentForm}
                warehouseOptions={warehouseOptions}
                locationOptions={locationOptions}
                itemOptions={itemOptions}
                onClose={() => setDocumentDrawerOpen(false)}
                onSubmit={submitDocument}
            />

            <SyncReportModal report={syncReport} onClose={() => setSyncReport(null)} />
        </ConfigProvider>
    );
}

function MovementTable({ items }) {
    if (!items.length) {
        return <Empty description="Chưa có giao dịch kho." image={Empty.PRESENTED_IMAGE_SIMPLE} />;
    }

    return (
        <Table
            rowKey="id"
            dataSource={items}
            pagination={{ pageSize: 10, hideOnSinglePage: items.length <= 10 }}
            columns={[
                { title: 'Thời gian', dataIndex: 'created_at', key: 'created_at', render: formatDateTime },
                { title: 'Loại', dataIndex: 'type', key: 'type', render: (value) => <Tag>{documentTypeLabels[value] ?? value}</Tag> },
                { title: 'Kho', dataIndex: 'warehouse_name', key: 'warehouse_name' },
                { title: 'Hàng hóa', dataIndex: 'item_name', key: 'item_name', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.item_name}</Text><Text type="secondary">{record.item_sku || '-'}</Text></Space> },
                { title: 'Lô', dataIndex: 'batch_code', key: 'batch_code', render: (value) => value || '-' },
                { title: 'Tăng/Giảm', dataIndex: 'quantity_delta', key: 'quantity_delta', render: (value) => <Text type={Number(value) < 0 ? 'danger' : 'success'}>{formatQuantity(value)}</Text> },
                { title: 'Tồn sau', dataIndex: 'balance_after', key: 'balance_after', render: formatQuantity },
                { title: 'Ghi chú', dataIndex: 'note', key: 'note', render: (value) => value || '-' },
            ]}
        />
    );
}

function BarcodeResult({ result }) {
    if (result.type === 'not_found') {
        return <Alert type="warning" showIcon message={result.message || 'Không tìm thấy.'} />;
    }

    return (
        <Alert
            type="success"
            showIcon
            message={`Tìm thấy ${barcodeTypeLabels[result.type] ?? result.type}`}
            description={result.record?.name || result.record?.serial_number || result.record?.code || result.record?.item_name}
        />
    );
}

function WarehouseDrawer({ open, form, warehouse, onClose, onSubmit }) {
    return (
        <Drawer
            title={warehouse ? 'Sửa kho' : 'Thêm kho'}
            open={open}
            onClose={onClose}
            width={520}
            destroyOnHidden
            extra={<Button type="primary" onClick={onSubmit}>Lưu</Button>}
        >
            <Form form={form} layout="vertical" validateMessages={{ required: 'Vui lòng nhập ${label}.' }}>
                <Form.Item name="code" label="Mã kho">
                    <Input placeholder="Tự động nếu để trống" />
                </Form.Item>
                <Form.Item name="name" label="Tên kho" rules={[{ required: true, message: 'Nhập tên kho' }]}>
                    <Input />
                </Form.Item>
                <Form.Item name="phone" label="Điện thoại">
                    <Input />
                </Form.Item>
                <Form.Item name="email" label="Email">
                    <Input />
                </Form.Item>
                <Form.Item name="address" label="Địa chỉ">
                    <Input />
                </Form.Item>
                <Form.Item name="description" label="Ghi chú">
                    <Input.TextArea rows={4} />
                </Form.Item>
                <Space wrap>
                    <Form.Item name="is_default" label="Kho mặc định" valuePropName="checked">
                        <Switch />
                    </Form.Item>
                    <Form.Item name="is_active" label="Đang bật" valuePropName="checked">
                        <Switch />
                    </Form.Item>
                </Space>
            </Form>
        </Drawer>
    );
}

function LocationDrawer({ open, form, location, warehouseOptions, locationOptions, onClose, onSubmit }) {
    return (
        <Drawer
            title={location ? 'Sửa vị trí kho' : 'Thêm vị trí kho'}
            open={open}
            onClose={onClose}
            width={560}
            destroyOnHidden
            extra={<Button type="primary" onClick={onSubmit}>Lưu</Button>}
        >
            <Form form={form} layout="vertical" validateMessages={{ required: 'Vui lòng nhập ${label}.' }}>
                <Form.Item name="warehouse_id" label="Kho" rules={[{ required: true, message: 'Chọn kho' }]}>
                    <Select options={warehouseOptions} />
                </Form.Item>
                <Form.Item name="parent_id" label="Vị trí cha">
                    <Select allowClear options={locationOptions.filter((option) => option.value !== location?.id)} />
                </Form.Item>
                <Form.Item name="code" label="Mã vị trí" rules={[{ required: true, message: 'Nhập mã vị trí' }]}>
                    <Input />
                </Form.Item>
                <Form.Item name="name" label="Tên vị trí" rules={[{ required: true, message: 'Nhập tên vị trí' }]}>
                    <Input />
                </Form.Item>
                <Form.Item name="barcode" label="Mã vạch">
                    <Input />
                </Form.Item>
                <Form.Item name="type" label="Loại vị trí">
                    <Select options={[
                        { value: 'storage', label: 'Lưu trữ' },
                        { value: 'receiving', label: 'Nhận hàng' },
                        { value: 'shipping', label: 'Xuất hàng' },
                        { value: 'quality', label: 'Kiểm tra chất lượng' },
                    ]} />
                </Form.Item>
                <Form.Item name="sort_order" label="Thứ tự">
                    <InputNumber min={0} style={{ width: '100%' }} />
                </Form.Item>
                <Space wrap>
                    <Form.Item name="is_default" label="Mặc định" valuePropName="checked">
                        <Switch />
                    </Form.Item>
                    <Form.Item name="is_active" label="Đang bật" valuePropName="checked">
                        <Switch />
                    </Form.Item>
                </Space>
            </Form>
        </Drawer>
    );
}

function ItemConfigDrawer({ open, form, item, onClose, onSubmit }) {
    return (
        <Drawer
            title={item ? `Cấu hình ${item.name}` : 'Cấu hình hàng hóa'}
            open={open}
            onClose={onClose}
            width={560}
            destroyOnHidden
            extra={<Button type="primary" onClick={onSubmit}>Lưu</Button>}
        >
            <Form form={form} layout="vertical" validateMessages={{ required: 'Vui lòng nhập ${label}.' }}>
                <Form.Item name="barcode" label="Mã vạch">
                    <Input />
                </Form.Item>
                <Form.Item name="unit" label="Đơn vị tính">
                    <Input />
                </Form.Item>
                <Form.Item name="costing_method" label="Phương pháp giá vốn">
                    <Select options={[{ value: 'fifo', label: 'Nhập trước, xuất trước (FIFO)' }, { value: 'avg', label: 'Bình quân' }]} />
                </Form.Item>
                <Space wrap>
                    <Form.Item name="track_batch" label="Theo dõi lô" valuePropName="checked">
                        <Switch />
                    </Form.Item>
                    <Form.Item name="track_serial" label="Theo dõi số sê-ri" valuePropName="checked">
                        <Switch />
                    </Form.Item>
                    <Form.Item name="is_active" label="Đang bật" valuePropName="checked">
                        <Switch />
                    </Form.Item>
                </Space>
                <Row gutter={16}>
                    <Col xs={24} sm={12}>
                        <Form.Item name="reorder_min" label="Tồn tối thiểu">
                            <InputNumber min={0} style={{ width: '100%' }} />
                        </Form.Item>
                    </Col>
                    <Col xs={24} sm={12}>
                        <Form.Item name="reorder_max" label="Mức bổ sung tối đa">
                            <InputNumber min={0} style={{ width: '100%' }} />
                        </Form.Item>
                    </Col>
                </Row>
                <Form.Item name="preferred_supplier" label="Nhà cung cấp ưu tiên">
                    <Input />
                </Form.Item>
            </Form>
        </Drawer>
    );
}

function DocumentDrawer({ open, form, warehouseOptions, locationOptions, itemOptions, onClose, onSubmit }) {
    const type = Form.useWatch('type', form);

    return (
        <Drawer
            title="Tạo phiếu kho"
            open={open}
            onClose={onClose}
            width={760}
            destroyOnHidden
            extra={<Button type="primary" onClick={onSubmit}>Ghi sổ phiếu</Button>}
        >
            <Form form={form} layout="vertical" validateMessages={{ required: 'Vui lòng nhập ${label}.' }}>
                <Row gutter={16}>
                    <Col xs={24} sm={12}>
                        <Form.Item name="type" label="Loại phiếu" rules={[{ required: true }]}>
                            <Select options={documentTypeOptions} />
                        </Form.Item>
                    </Col>
                    <Col xs={24} sm={12}>
                        <Form.Item name="reference" label="Tham chiếu">
                            <Input placeholder="Đơn mua/đơn bán/ghi chú tham chiếu" />
                        </Form.Item>
                    </Col>
                    <Col xs={24} sm={12}>
                        <Form.Item name="source_warehouse_id" label="Kho nguồn" rules={type === 'issue' || type === 'transfer' ? [{ required: true, message: 'Chọn kho nguồn' }] : []}>
                            <Select allowClear options={warehouseOptions} disabled={type === 'receipt'} />
                        </Form.Item>
                    </Col>
                    <Col xs={24} sm={12}>
                        <Form.Item name="destination_warehouse_id" label="Kho đích" rules={type !== 'issue' ? [{ required: true, message: 'Chọn kho đích' }] : []}>
                            <Select allowClear options={warehouseOptions} disabled={type === 'issue'} />
                        </Form.Item>
                    </Col>
                </Row>

                <Form.Item name="note" label="Ghi chú">
                    <Input.TextArea rows={3} />
                </Form.Item>

                <Form.List name="lines">
                    {(fields, { add, remove }) => (
                        <Space direction="vertical" size={12} style={{ width: '100%' }}>
                            {fields.map((field) => (
                                <Card key={field.key} size="small">
                                    <Row gutter={12}>
                                        <Col xs={24} sm={10}>
                                            <Form.Item {...field} name={[field.name, 'item_id']} label="Hàng hóa" rules={[{ required: true, message: 'Chọn hàng hóa' }]}>
                                                <Select showSearch optionFilterProp="label" options={itemOptions} />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={12} sm={5}>
                                            <Form.Item {...field} name={[field.name, 'quantity']} label="Số lượng" rules={[{ required: true, message: 'Nhập số lượng' }]}>
                                                <InputNumber style={{ width: '100%' }} min={type === 'adjustment' ? undefined : 0.001} step={1} />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={12} sm={5}>
                                            <Form.Item {...field} name={[field.name, 'unit_cost']} label="Giá vốn">
                                                <InputNumber style={{ width: '100%' }} min={0} step={1000} />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} sm={4}>
                                            <Form.Item label=" ">
                                                <Button danger block onClick={() => remove(field.name)} disabled={fields.length <= 1}>Xóa</Button>
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} sm={8}>
                                            <Form.Item {...field} name={[field.name, 'batch_code']} label="Mã lô">
                                                <Input />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} sm={6}>
                                            <Form.Item {...field} name={[field.name, 'expires_at']} label="Hạn dùng">
                                                <Input placeholder="YYYY-MM-DD" />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} sm={10}>
                                            <Form.Item {...field} name={[field.name, 'serial_numbers_text']} label="Số sê-ri">
                                                <Input.TextArea rows={1} placeholder="Các số sê-ri cách nhau bằng dấu phẩy hoặc xuống dòng" />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} sm={12}>
                                            <Form.Item {...field} name={[field.name, 'source_location_id']} label="Vị trí nguồn">
                                                <Select allowClear showSearch optionFilterProp="label" options={locationOptions} disabled={type === 'receipt'} />
                                            </Form.Item>
                                        </Col>
                                        <Col xs={24} sm={12}>
                                            <Form.Item {...field} name={[field.name, 'destination_location_id']} label="Vị trí đích">
                                                <Select allowClear showSearch optionFilterProp="label" options={locationOptions} disabled={type === 'issue'} />
                                            </Form.Item>
                                        </Col>
                                    </Row>
                                </Card>
                            ))}
                            <Button onClick={() => add({ item_id: null, source_location_id: null, destination_location_id: null, batch_code: '', expires_at: '', serial_numbers_text: '', quantity: 1, unit_cost: 0, note: '' })}>Thêm dòng hàng</Button>
                        </Space>
                    )}
                </Form.List>
            </Form>
        </Drawer>
    );
}

function SerialNumbersTable({ callAdminApi }) {
    const { data, loading, error } = useAdminRouteResource({
        loader: async () => {
            const payload = await callAdminApi(adminApi('inventory/serial-numbers'));

            return payload.data ?? null;
        },
        deps: [],
    });

    if (error) {
        return <Alert type="error" showIcon message={error} />;
    }

    return (
        <Table
            rowKey="id"
            loading={loading}
            dataSource={data?.items ?? []}
            pagination={{ pageSize: 8 }}
            columns={[
                { title: 'Số sê-ri', dataIndex: 'serial_number', key: 'serial_number', render: (value) => <Text strong>{value}</Text> },
                { title: 'Hàng hóa', dataIndex: 'item_name', key: 'item_name' },
                { title: 'Lô', dataIndex: 'batch_code', key: 'batch_code', render: (value) => value || '-' },
                { title: 'Trạng thái', dataIndex: 'status', key: 'status', render: (value) => <Tag color={value === 'in_stock' ? 'green' : 'default'}>{serialStatusLabels[value] ?? value}</Tag> },
                { title: 'Ngày nhập', dataIndex: 'received_at', key: 'received_at', render: formatDateTime },
            ]}
        />
    );
}

function SyncReportModal({ report, onClose }) {
    return (
        <Modal title="Báo cáo đồng bộ sản phẩm" open={Boolean(report)} onCancel={onClose} footer={<Button type="primary" onClick={onClose}>Đóng</Button>} width={900}>
            {report ? (
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Descriptions bordered size="small" column={4}>
                        <Descriptions.Item label="Tạo mới">{report.created_count}</Descriptions.Item>
                        <Descriptions.Item label="Cập nhật">{report.updated_count}</Descriptions.Item>
                        <Descriptions.Item label="Bỏ qua">{report.skipped_count}</Descriptions.Item>
                        <Descriptions.Item label="Lỗi">{report.failed_count}</Descriptions.Item>
                    </Descriptions>
                    <Table
                        rowKey="id"
                        size="small"
                        dataSource={report.lines ?? []}
                        pagination={{ pageSize: 8 }}
                        columns={[
                            { title: 'Sản phẩm', dataIndex: 'name', key: 'name' },
                            { title: 'SKU', dataIndex: 'sku', key: 'sku', render: (value) => value || '-' },
                            { title: 'Kết quả', dataIndex: 'action', key: 'action', render: (value) => <Tag color={value === 'failed' ? 'red' : value === 'created' ? 'green' : 'blue'}>{syncActionLabels[value] ?? value}</Tag> },
                            { title: 'Ghi chú', dataIndex: 'message', key: 'message' },
                        ]}
                    />
                </Space>
            ) : null}
        </Modal>
    );
}
