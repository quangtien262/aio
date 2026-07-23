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
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Typography from 'antd/es/typography';
import message from 'antd/es/message';
import dayjs from 'dayjs';
import useAdminRouteResource from '../../../shared/hooks/useAdminRouteResource';

const { Text, Title } = Typography;

const documentTypeOptions = [
    { label: 'Nhap kho', value: 'receipt' },
    { label: 'Xuat kho', value: 'issue' },
    { label: 'Chuyen kho', value: 'transfer' },
    { label: 'Dieu chinh', value: 'adjustment' },
];

const documentTypeLabels = {
    receipt: 'Nhap kho',
    issue: 'Xuat kho',
    transfer: 'Chuyen kho',
    adjustment: 'Dieu chinh',
};

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
        const values = await warehouseForm.validateFields();
        const url = editingWarehouse ? adminApi(`inventory/warehouses/${editingWarehouse.id}`) : adminApi('inventory/warehouses');
        const method = editingWarehouse ? 'PUT' : 'POST';
        const ok = await runAdminAction(
            () => callAdminApi(url, { method, body: JSON.stringify(values) }),
            editingWarehouse ? 'Da cap nhat kho.' : 'Da tao kho.',
            reload,
        );

        if (ok) {
            setWarehouseDrawerOpen(false);
            setEditingWarehouse(null);
        }
    };

    const deleteWarehouse = (warehouse) => runAdminAction(
        () => callAdminApi(adminApi(`inventory/warehouses/${warehouse.id}`), { method: 'DELETE' }),
        'Da xoa kho.',
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
        const values = await locationForm.validateFields();
        const url = editingLocation ? adminApi(`inventory/locations/${editingLocation.id}`) : adminApi('inventory/locations');
        const method = editingLocation ? 'PUT' : 'POST';
        const ok = await runAdminAction(
            () => callAdminApi(url, { method, body: JSON.stringify(values) }),
            editingLocation ? 'Da cap nhat vi tri kho.' : 'Da tao vi tri kho.',
            reload,
        );

        if (ok) {
            setLocationDrawerOpen(false);
            setEditingLocation(null);
        }
    };

    const deleteLocation = (location) => runAdminAction(
        () => callAdminApi(adminApi(`inventory/locations/${location.id}`), { method: 'DELETE' }),
        'Da xoa vi tri kho.',
        reload,
    );

    const openEditItem = (item) => {
        setEditingItem(item);
        itemForm.setFieldsValue(item);
        setItemDrawerOpen(true);
    };

    const submitItem = async () => {
        const values = await itemForm.validateFields();
        const ok = await runAdminAction(
            () => callAdminApi(adminApi(`inventory/items/${editingItem.id}`), { method: 'PUT', body: JSON.stringify(values) }),
            'Da cap nhat cau hinh hang hoa.',
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
            messageApi.success('Da dong bo san pham.');
            await reload();
        } catch (nextError) {
            messageApi.error(nextError instanceof Error ? nextError.message : 'Khong dong bo duoc san pham.');
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
            setBarcodeResult({ type: 'not_found', message: nextError instanceof Error ? nextError.message : 'Khong tim thay.' });
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
        const values = await documentForm.validateFields();
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
            'Da tao phieu kho.',
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
                <Col xs={24} sm={12} lg={8}><Card><Statistic title="Hang hoa dang bat" value={data?.metrics?.active_items ?? 0} /></Card></Col>
                <Col xs={24} sm={12} lg={8}><Card><Statistic title="Tong ton" value={data?.metrics?.total_on_hand ?? 0} precision={0} /></Card></Col>
                <Col xs={24} sm={12} lg={8}><Card><Statistic title="Sap het hang" value={data?.metrics?.low_stock_items ?? 0} /></Card></Col>
                <Col xs={24} sm={12} lg={8}><Card><Statistic title="Het hang" value={data?.metrics?.out_of_stock_items ?? 0} /></Card></Col>
                <Col xs={24} sm={12} lg={8}><Card><Statistic title="Phieu kho" value={data?.metrics?.documents ?? 0} /></Card></Col>
            </Row>

            <Card title="Lich su gan day">
                <MovementTable items={data?.recent_movements ?? []} />
            </Card>

            <Card title="Barcode / SKU lookup">
                <Space direction="vertical" size={12} style={{ width: '100%' }}>
                    <Input.Search
                        value={barcodeCode}
                        onChange={(event) => setBarcodeCode(event.target.value)}
                        onSearch={lookupBarcode}
                        enterButton="Tim"
                        placeholder="Quet hoac nhap barcode, SKU, serial"
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
            extra={permissions.canManageWarehouse ? <Button type="primary" icon={<PlusOutlined />} onClick={openCreateWarehouse}>Them kho</Button> : null}
        >
            <Table
                rowKey="id"
                dataSource={data?.items ?? []}
                pagination={{ pageSize: 10, hideOnSinglePage: true }}
                columns={[
                    { title: 'Kho', dataIndex: 'name', key: 'name', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.name}</Text><Text type="secondary">{record.code}</Text></Space> },
                    { title: 'Dia chi', dataIndex: 'address', key: 'address', render: (value) => value || '-' },
                    { title: 'Trang thai', dataIndex: 'is_active', key: 'is_active', render: (_, record) => <Space>{record.is_default ? <Tag color="blue">Mac dinh</Tag> : null}<Tag color={record.is_active ? 'green' : 'default'}>{record.is_active ? 'Dang bat' : 'Tat'}</Tag></Space> },
                    { title: 'Ton', dataIndex: 'items_count', key: 'items_count', render: (value) => value ?? 0 },
                    {
                        title: 'Tac vu',
                        key: 'actions',
                        render: (_, record) => permissions.canManageWarehouse ? (
                            <Space>
                                <Button size="small" onClick={() => openEditWarehouse(record)}>Sua</Button>
                                <Popconfirm title="Xoa kho nay?" onConfirm={() => deleteWarehouse(record)}>
                                    <Button size="small" danger disabled={(record.items_count ?? 0) > 0}>Xoa</Button>
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
            title={`Vi tri kho (${data?.total ?? 0})`}
            extra={permissions.canManageLocation ? <Button type="primary" icon={<PlusOutlined />} onClick={openCreateLocation}>Them vi tri</Button> : null}
        >
            <Table
                rowKey="id"
                dataSource={data?.items ?? []}
                pagination={{ pageSize: 10 }}
                columns={[
                    { title: 'Vi tri', dataIndex: 'name', key: 'name', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.name}</Text><Text type="secondary">{record.code}</Text></Space> },
                    { title: 'Kho', dataIndex: 'warehouse_name', key: 'warehouse_name' },
                    { title: 'Barcode', dataIndex: 'barcode', key: 'barcode', render: (value) => value || '-' },
                    { title: 'Loai', dataIndex: 'type', key: 'type', render: (value) => <Tag>{value}</Tag> },
                    { title: 'Trang thai', dataIndex: 'is_active', key: 'is_active', render: (_, record) => <Space>{record.is_default ? <Tag color="blue">Mac dinh</Tag> : null}<Tag color={record.is_active ? 'green' : 'default'}>{record.is_active ? 'Dang bat' : 'Tat'}</Tag></Space> },
                    {
                        title: 'Tac vu',
                        key: 'actions',
                        render: (_, record) => permissions.canManageLocation ? (
                            <Space>
                                <Button size="small" onClick={() => openEditLocation(record)}>Sua</Button>
                                <Popconfirm title="Xoa vi tri nay?" onConfirm={() => deleteLocation(record)}>
                                    <Button size="small" danger>Xoa</Button>
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
            title={`Hang hoa (${data?.total ?? 0})`}
            extra={permissions.canSyncItems ? <Button icon={<SyncOutlined spin={syncing} />} loading={syncing} onClick={syncProducts}>Dong bo san pham</Button> : null}
        >
            <Table
                rowKey="id"
                dataSource={data?.items ?? []}
                pagination={{ pageSize: 10 }}
                columns={[
                    { title: 'Hang hoa', dataIndex: 'name', key: 'name', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.name}</Text><Text type="secondary">{record.sku || 'Chua co SKU'}</Text></Space> },
                    { title: 'Barcode', dataIndex: 'barcode', key: 'barcode', render: (value) => value || '-' },
                    { title: 'Catalog ID', dataIndex: 'catalog_product_id', key: 'catalog_product_id', render: (value) => value || '-' },
                    { title: 'Ton tong', dataIndex: 'total_on_hand', key: 'total_on_hand', render: formatQuantity },
                    { title: 'Min/Max', key: 'reorder', render: (_, record) => `${formatQuantity(record.reorder_min)} / ${formatQuantity(record.reorder_max)}` },
                    { title: 'Trace', key: 'trace', render: (_, record) => <Space>{record.track_batch ? <Tag color="blue">Batch</Tag> : null}{record.track_serial ? <Tag color="purple">Serial</Tag> : null}</Space> },
                    { title: 'Gia ban', dataIndex: 'sale_price', key: 'sale_price', render: (value) => Number(value ?? 0).toLocaleString('vi-VN') },
                    { title: 'Sync gan nhat', dataIndex: 'last_synced_at', key: 'last_synced_at', render: formatDateTime },
                    { title: 'Trang thai', dataIndex: 'is_active', key: 'is_active', render: (value) => <Tag color={value ? 'green' : 'default'}>{value ? 'Dang bat' : 'Tat'}</Tag> },
                    { title: 'Tac vu', key: 'actions', render: (_, record) => permissions.canManageItem ? <Button size="small" onClick={() => openEditItem(record)}>Cau hinh</Button> : null },
                ]}
            />
        </Card>
    );

    const renderReplenishment = () => (
        <Card title={`Goi y bo sung hang (${data?.total ?? 0})`}>
            <Table
                rowKey="item_id"
                dataSource={data?.items ?? []}
                pagination={{ pageSize: 10 }}
                columns={[
                    { title: 'Hang hoa', dataIndex: 'item_name', key: 'item_name', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.item_name}</Text><Text type="secondary">{record.item_sku || '-'}</Text></Space> },
                    { title: 'Ton hien tai', dataIndex: 'on_hand', key: 'on_hand', render: formatQuantity },
                    { title: 'Min', dataIndex: 'reorder_min', key: 'reorder_min', render: formatQuantity },
                    { title: 'Max', dataIndex: 'reorder_max', key: 'reorder_max', render: formatQuantity },
                    { title: 'De xuat nhap', dataIndex: 'suggested_quantity', key: 'suggested_quantity', render: (value) => <Text strong>{formatQuantity(value)}</Text> },
                    { title: 'NCC uu tien', dataIndex: 'preferred_supplier', key: 'preferred_supplier', render: (value) => value || '-' },
                    { title: 'Trang thai', dataIndex: 'status', key: 'status', render: (value) => <Tag color={value === 'out' ? 'red' : 'orange'}>{value === 'out' ? 'Het hang' : 'Sap het'}</Tag> },
                ]}
            />
        </Card>
    );

    const renderBatches = () => (
        <Space direction="vertical" size={16} style={{ width: '100%' }}>
            <Card title={`Batch / Lot (${data?.total ?? 0})`}>
                <Table
                    rowKey="id"
                    dataSource={data?.items ?? []}
                    pagination={{ pageSize: 10 }}
                    columns={[
                        { title: 'Batch', dataIndex: 'batch_code', key: 'batch_code', render: (value) => <Text strong>{value}</Text> },
                        { title: 'Hang hoa', dataIndex: 'item_name', key: 'item_name', render: (_, record) => <Space direction="vertical" size={0}><Text>{record.item_name}</Text><Text type="secondary">{record.item_sku || '-'}</Text></Space> },
                        { title: 'Han dung', dataIndex: 'expires_at', key: 'expires_at', render: (value) => value || '-' },
                        { title: 'Trang thai', dataIndex: 'is_active', key: 'is_active', render: (value) => <Tag color={value ? 'green' : 'default'}>{value ? 'Dang bat' : 'Tat'}</Tag> },
                    ]}
                />
            </Card>
            <Card title="Serial gan day">
                <SerialNumbersTable callAdminApi={callAdminApi} />
            </Card>
        </Space>
    );

    const renderBalances = () => (
        <Card title={`Ton kho (${data?.total ?? 0})`}>
            <Table
                rowKey="id"
                dataSource={data?.items ?? []}
                pagination={{ pageSize: 10 }}
                columns={[
                    { title: 'Kho', dataIndex: 'warehouse_name', key: 'warehouse_name', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.warehouse_name}</Text><Text type="secondary">{record.warehouse_code}</Text></Space> },
                    { title: 'Hang hoa', dataIndex: 'item_name', key: 'item_name', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.item_name}</Text><Text type="secondary">{record.item_sku || '-'}</Text></Space> },
                    { title: 'Batch', dataIndex: 'batch_code', key: 'batch_code', render: (value) => value || '-' },
                    { title: 'Ton thuc te', dataIndex: 'quantity_on_hand', key: 'quantity_on_hand', render: formatQuantity },
                    { title: 'Da giu', dataIndex: 'quantity_reserved', key: 'quantity_reserved', render: formatQuantity },
                    { title: 'Co the dung', dataIndex: 'quantity_available', key: 'quantity_available', render: formatQuantity },
                    { title: 'Cap nhat', dataIndex: 'last_movement_at', key: 'last_movement_at', render: formatDateTime },
                ]}
            />
        </Card>
    );

    const renderDocuments = () => (
        <Card
            title={`Phieu kho (${data?.total ?? 0})`}
            extra={permissions.canCreateDocument ? <Button type="primary" icon={<PlusOutlined />} onClick={openCreateDocument}>Tao phieu</Button> : null}
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
                                { title: 'Hang hoa', dataIndex: 'item_name', key: 'item_name' },
                                { title: 'SKU', dataIndex: 'item_sku', key: 'item_sku', render: (value) => value || '-' },
                                { title: 'Batch', dataIndex: 'batch_code', key: 'batch_code', render: (value) => value || '-' },
                                { title: 'So luong', dataIndex: 'quantity', key: 'quantity', render: formatQuantity },
                                { title: 'Don gia von', dataIndex: 'unit_cost', key: 'unit_cost', render: (value) => Number(value ?? 0).toLocaleString('vi-VN') },
                            ]}
                        />
                    ),
                }}
                columns={[
                    { title: 'Ma phieu', dataIndex: 'code', key: 'code', render: (value) => <Text strong>{value}</Text> },
                    { title: 'Loai', dataIndex: 'type', key: 'type', render: (value) => <Tag>{documentTypeLabels[value] ?? value}</Tag> },
                    { title: 'Kho nguon', dataIndex: 'source_warehouse_name', key: 'source_warehouse_name', render: (value) => value || '-' },
                    { title: 'Kho dich', dataIndex: 'destination_warehouse_name', key: 'destination_warehouse_name', render: (value) => value || '-' },
                    { title: 'Tham chieu', dataIndex: 'reference', key: 'reference', render: (value) => value || '-' },
                    { title: 'Ngay post', dataIndex: 'posted_at', key: 'posted_at', render: formatDateTime },
                ]}
            />
        </Card>
    );

    const renderMovements = () => (
        <Card title={`Lich su kho (${data?.total ?? 0})`}>
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
        <>
            {messageContextHolder}
            <Space direction="vertical" size={16} style={{ width: '100%' }}>
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
        </>
    );
}

function MovementTable({ items }) {
    if (!items.length) {
        return <Empty description="Chua co giao dich kho." image={Empty.PRESENTED_IMAGE_SIMPLE} />;
    }

    return (
        <Table
            rowKey="id"
            dataSource={items}
            pagination={{ pageSize: 10, hideOnSinglePage: items.length <= 10 }}
            columns={[
                { title: 'Thoi gian', dataIndex: 'created_at', key: 'created_at', render: formatDateTime },
                { title: 'Loai', dataIndex: 'type', key: 'type', render: (value) => <Tag>{documentTypeLabels[value] ?? value}</Tag> },
                { title: 'Kho', dataIndex: 'warehouse_name', key: 'warehouse_name' },
                { title: 'Hang hoa', dataIndex: 'item_name', key: 'item_name', render: (_, record) => <Space direction="vertical" size={0}><Text strong>{record.item_name}</Text><Text type="secondary">{record.item_sku || '-'}</Text></Space> },
                { title: 'Batch', dataIndex: 'batch_code', key: 'batch_code', render: (value) => value || '-' },
                { title: 'Tang/Giam', dataIndex: 'quantity_delta', key: 'quantity_delta', render: (value) => <Text type={Number(value) < 0 ? 'danger' : 'success'}>{formatQuantity(value)}</Text> },
                { title: 'Ton sau', dataIndex: 'balance_after', key: 'balance_after', render: formatQuantity },
                { title: 'Ghi chu', dataIndex: 'note', key: 'note', render: (value) => value || '-' },
            ]}
        />
    );
}

function BarcodeResult({ result }) {
    if (result.type === 'not_found') {
        return <Alert type="warning" showIcon message={result.message || 'Khong tim thay.'} />;
    }

    return (
        <Alert
            type="success"
            showIcon
            message={`Tim thay ${result.type}`}
            description={result.record?.name || result.record?.serial_number || result.record?.code || result.record?.item_name}
        />
    );
}

function WarehouseDrawer({ open, form, warehouse, onClose, onSubmit }) {
    return (
        <Drawer
            title={warehouse ? 'Sua kho' : 'Them kho'}
            open={open}
            onClose={onClose}
            width={520}
            destroyOnHidden
            extra={<Button type="primary" onClick={onSubmit}>Luu</Button>}
        >
            <Form form={form} layout="vertical">
                <Form.Item name="code" label="Ma kho">
                    <Input placeholder="Tu dong neu de trong" />
                </Form.Item>
                <Form.Item name="name" label="Ten kho" rules={[{ required: true, message: 'Nhap ten kho' }]}>
                    <Input />
                </Form.Item>
                <Form.Item name="phone" label="Dien thoai">
                    <Input />
                </Form.Item>
                <Form.Item name="email" label="Email">
                    <Input />
                </Form.Item>
                <Form.Item name="address" label="Dia chi">
                    <Input />
                </Form.Item>
                <Form.Item name="description" label="Ghi chu">
                    <Input.TextArea rows={4} />
                </Form.Item>
                <Space>
                    <Form.Item name="is_default" label="Kho mac dinh" valuePropName="checked">
                        <Switch />
                    </Form.Item>
                    <Form.Item name="is_active" label="Dang bat" valuePropName="checked">
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
            title={location ? 'Sua vi tri kho' : 'Them vi tri kho'}
            open={open}
            onClose={onClose}
            width={560}
            destroyOnHidden
            extra={<Button type="primary" onClick={onSubmit}>Luu</Button>}
        >
            <Form form={form} layout="vertical">
                <Form.Item name="warehouse_id" label="Kho" rules={[{ required: true, message: 'Chon kho' }]}>
                    <Select options={warehouseOptions} />
                </Form.Item>
                <Form.Item name="parent_id" label="Vi tri cha">
                    <Select allowClear options={locationOptions.filter((option) => option.value !== location?.id)} />
                </Form.Item>
                <Form.Item name="code" label="Ma vi tri" rules={[{ required: true, message: 'Nhap ma vi tri' }]}>
                    <Input />
                </Form.Item>
                <Form.Item name="name" label="Ten vi tri" rules={[{ required: true, message: 'Nhap ten vi tri' }]}>
                    <Input />
                </Form.Item>
                <Form.Item name="barcode" label="Barcode">
                    <Input />
                </Form.Item>
                <Form.Item name="type" label="Loai vi tri">
                    <Select options={[
                        { value: 'storage', label: 'Storage' },
                        { value: 'receiving', label: 'Receiving' },
                        { value: 'shipping', label: 'Shipping' },
                        { value: 'quality', label: 'Quality' },
                    ]} />
                </Form.Item>
                <Form.Item name="sort_order" label="Thu tu">
                    <InputNumber min={0} style={{ width: '100%' }} />
                </Form.Item>
                <Space>
                    <Form.Item name="is_default" label="Mac dinh" valuePropName="checked">
                        <Switch />
                    </Form.Item>
                    <Form.Item name="is_active" label="Dang bat" valuePropName="checked">
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
            title={item ? `Cau hinh ${item.name}` : 'Cau hinh hang hoa'}
            open={open}
            onClose={onClose}
            width={560}
            destroyOnHidden
            extra={<Button type="primary" onClick={onSubmit}>Luu</Button>}
        >
            <Form form={form} layout="vertical">
                <Form.Item name="barcode" label="Barcode">
                    <Input />
                </Form.Item>
                <Form.Item name="unit" label="Don vi tinh">
                    <Input />
                </Form.Item>
                <Form.Item name="costing_method" label="Phuong phap gia von">
                    <Select options={[{ value: 'fifo', label: 'FIFO' }, { value: 'avg', label: 'Average' }]} />
                </Form.Item>
                <Space>
                    <Form.Item name="track_batch" label="Track batch" valuePropName="checked">
                        <Switch />
                    </Form.Item>
                    <Form.Item name="track_serial" label="Track serial" valuePropName="checked">
                        <Switch />
                    </Form.Item>
                    <Form.Item name="is_active" label="Dang bat" valuePropName="checked">
                        <Switch />
                    </Form.Item>
                </Space>
                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="reorder_min" label="Ton toi thieu">
                            <InputNumber min={0} style={{ width: '100%' }} />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="reorder_max" label="Muc bo sung toi da">
                            <InputNumber min={0} style={{ width: '100%' }} />
                        </Form.Item>
                    </Col>
                </Row>
                <Form.Item name="preferred_supplier" label="Nha cung cap uu tien">
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
            title="Tao phieu kho"
            open={open}
            onClose={onClose}
            width={760}
            destroyOnHidden
            extra={<Button type="primary" onClick={onSubmit}>Post phieu</Button>}
        >
            <Form form={form} layout="vertical">
                <Row gutter={16}>
                    <Col span={12}>
                        <Form.Item name="type" label="Loai phieu" rules={[{ required: true }]}>
                            <Select options={documentTypeOptions} />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="reference" label="Tham chieu">
                            <Input placeholder="PO/SO/ghi chu tham chieu" />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="source_warehouse_id" label="Kho nguon" rules={type === 'issue' || type === 'transfer' ? [{ required: true, message: 'Chon kho nguon' }] : []}>
                            <Select allowClear options={warehouseOptions} disabled={type === 'receipt'} />
                        </Form.Item>
                    </Col>
                    <Col span={12}>
                        <Form.Item name="destination_warehouse_id" label="Kho dich" rules={type !== 'issue' ? [{ required: true, message: 'Chon kho dich' }] : []}>
                            <Select allowClear options={warehouseOptions} disabled={type === 'issue'} />
                        </Form.Item>
                    </Col>
                </Row>

                <Form.Item name="note" label="Ghi chu">
                    <Input.TextArea rows={3} />
                </Form.Item>

                <Form.List name="lines">
                    {(fields, { add, remove }) => (
                        <Space direction="vertical" size={12} style={{ width: '100%' }}>
                            {fields.map((field) => (
                                <Card key={field.key} size="small">
                                    <Row gutter={12}>
                                        <Col span={10}>
                                            <Form.Item {...field} name={[field.name, 'item_id']} label="Hang hoa" rules={[{ required: true, message: 'Chon hang hoa' }]}>
                                                <Select showSearch optionFilterProp="label" options={itemOptions} />
                                            </Form.Item>
                                        </Col>
                                        <Col span={5}>
                                            <Form.Item {...field} name={[field.name, 'quantity']} label="So luong" rules={[{ required: true, message: 'Nhap so luong' }]}>
                                                <InputNumber style={{ width: '100%' }} min={type === 'adjustment' ? undefined : 0.001} step={1} />
                                            </Form.Item>
                                        </Col>
                                        <Col span={5}>
                                            <Form.Item {...field} name={[field.name, 'unit_cost']} label="Gia von">
                                                <InputNumber style={{ width: '100%' }} min={0} step={1000} />
                                            </Form.Item>
                                        </Col>
                                        <Col span={4}>
                                            <Form.Item label=" ">
                                                <Button danger block onClick={() => remove(field.name)} disabled={fields.length <= 1}>Xoa</Button>
                                            </Form.Item>
                                        </Col>
                                        <Col span={8}>
                                            <Form.Item {...field} name={[field.name, 'batch_code']} label="Batch/Lot">
                                                <Input />
                                            </Form.Item>
                                        </Col>
                                        <Col span={6}>
                                            <Form.Item {...field} name={[field.name, 'expires_at']} label="Han dung">
                                                <Input placeholder="YYYY-MM-DD" />
                                            </Form.Item>
                                        </Col>
                                        <Col span={10}>
                                            <Form.Item {...field} name={[field.name, 'serial_numbers_text']} label="Serial numbers">
                                                <Input.TextArea rows={1} placeholder="Moi serial cach nhau bang dau phay hoac xuong dong" />
                                            </Form.Item>
                                        </Col>
                                        <Col span={12}>
                                            <Form.Item {...field} name={[field.name, 'source_location_id']} label="Vi tri nguon">
                                                <Select allowClear showSearch optionFilterProp="label" options={locationOptions} disabled={type === 'receipt'} />
                                            </Form.Item>
                                        </Col>
                                        <Col span={12}>
                                            <Form.Item {...field} name={[field.name, 'destination_location_id']} label="Vi tri dich">
                                                <Select allowClear showSearch optionFilterProp="label" options={locationOptions} disabled={type === 'issue'} />
                                            </Form.Item>
                                        </Col>
                                    </Row>
                                </Card>
                            ))}
                            <Button onClick={() => add({ item_id: null, source_location_id: null, destination_location_id: null, batch_code: '', expires_at: '', serial_numbers_text: '', quantity: 1, unit_cost: 0, note: '' })}>Them dong hang</Button>
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
                { title: 'Serial', dataIndex: 'serial_number', key: 'serial_number', render: (value) => <Text strong>{value}</Text> },
                { title: 'Hang hoa', dataIndex: 'item_name', key: 'item_name' },
                { title: 'Batch', dataIndex: 'batch_code', key: 'batch_code', render: (value) => value || '-' },
                { title: 'Trang thai', dataIndex: 'status', key: 'status', render: (value) => <Tag color={value === 'in_stock' ? 'green' : 'default'}>{value}</Tag> },
                { title: 'Ngay nhap', dataIndex: 'received_at', key: 'received_at', render: formatDateTime },
            ]}
        />
    );
}

function SyncReportModal({ report, onClose }) {
    return (
        <Modal title="Bao cao dong bo san pham" open={Boolean(report)} onCancel={onClose} footer={<Button type="primary" onClick={onClose}>Dong</Button>} width={900}>
            {report ? (
                <Space direction="vertical" size={16} style={{ width: '100%' }}>
                    <Descriptions bordered size="small" column={4}>
                        <Descriptions.Item label="Tao moi">{report.created_count}</Descriptions.Item>
                        <Descriptions.Item label="Cap nhat">{report.updated_count}</Descriptions.Item>
                        <Descriptions.Item label="Bo qua">{report.skipped_count}</Descriptions.Item>
                        <Descriptions.Item label="Loi">{report.failed_count}</Descriptions.Item>
                    </Descriptions>
                    <Table
                        rowKey="id"
                        size="small"
                        dataSource={report.lines ?? []}
                        pagination={{ pageSize: 8 }}
                        columns={[
                            { title: 'San pham', dataIndex: 'name', key: 'name' },
                            { title: 'SKU', dataIndex: 'sku', key: 'sku', render: (value) => value || '-' },
                            { title: 'Ket qua', dataIndex: 'action', key: 'action', render: (value) => <Tag color={value === 'failed' ? 'red' : value === 'created' ? 'green' : 'blue'}>{value}</Tag> },
                            { title: 'Ghi chu', dataIndex: 'message', key: 'message' },
                        ]}
                    />
                </Space>
            ) : null}
        </Modal>
    );
}
