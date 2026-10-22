import { useState } from 'react';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Modal from 'antd/es/modal';
import Select from 'antd/es/select';
import Tag from 'antd/es/tag';
import Tooltip from 'antd/es/tooltip';
import EditOutlined from '@ant-design/icons/EditOutlined';
import LockOutlined from '@ant-design/icons/LockOutlined';
import ShopOutlined from '@ant-design/icons/ShopOutlined';
import SettingOutlined from '@ant-design/icons/SettingOutlined';
import ApiOutlined from '@ant-design/icons/ApiOutlined';
import RetailIntegrationPanel from './RetailIntegrationPanel';

const features = [
    ['online', 'Bán hàng trực tuyến', 'Giao dịch được xác nhận qua máy chủ.'],
    ['offline', 'Bán khi mất mạng', 'Chưa hỗ trợ thu tiền khi mất kết nối. Giỏ hàng được giữ lại trên thiết bị.'],
    ['purchasing', 'Mua hàng & nhà cung cấp', 'Lập đơn mua, nhận hàng và theo dõi công nợ nhà cung cấp.'],
    ['electronic_payment_provider', 'Kết nối thanh toán điện tử', 'Chưa kết nối nhà cung cấp thanh toán tự động; chuyển khoản hiện được xác nhận thủ công.'],
    ['accounting', 'Kế toán & thuế', 'Liên kết giao dịch bán hàng với chứng từ kế toán nội bộ dạng nháp.'],
];

export default function RetailSettingsPanel({ branch, branchId, branchOptions, onBranchChange, bootstrap, callAdminApi, identity, canIntegrate, onCommand, integrationReloadRef }) {
    const [branchEditing, setBranchEditing] = useState(false);
    const [selectedBranch, setSelectedBranch] = useState(branchId);
    const [featureDetail, setFeatureDetail] = useState(null);
    const settings = [
        { key: 'branch', label: 'Chi nhánh đang vận hành', value: branch?.name ?? 'Chưa chọn chi nhánh', hint: 'Áp dụng cho màn hình làm việc hiện tại.', edit: () => { setSelectedBranch(branchId); setBranchEditing(true); } },
        { key: 'currency', label: 'Tiền tệ', value: 'Việt Nam đồng (VND)', hint: 'Giá bán, tiền quầy và báo cáo sử dụng đồng Việt Nam.', locked: 'POS hiện chỉ hỗ trợ tiền tệ VND; không đổi tiền tệ của giao dịch đã ghi nhận.' },
        { key: 'mode', label: 'Chế độ giao dịch', value: 'Bán hàng trực tuyến', hint: 'Có phục hồi kết quả khi yêu cầu bị gián đoạn.', locked: 'Bán hàng cần kết nối máy chủ. Chế độ bán khi mất mạng chưa sẵn sàng.' },
        { key: 'stock', label: 'Nguồn tồn kho', value: 'Phân hệ Quản lý kho', hint: 'Tồn kho được quản lý tập trung theo chi nhánh.', locked: 'Nguồn tồn kho chính thức do phân hệ Quản lý kho cung cấp.' },
    ];
    return <div className="retail-settings">
        <div className="retail-settings-grid">
            <Card size="small" title={<span className="retail-settings-card-title"><SettingOutlined /> Thiết lập vận hành</span>}>
                {settings.map((item) => <div className="retail-setting-row" key={item.key}><div><div className="retail-setting-label">{item.label}</div><strong>{item.value}</strong><small>{item.hint}</small></div><Tooltip title={item.locked ?? `Sửa ${item.label.toLocaleLowerCase()}`}><span><Button type="text" size="small" icon={item.locked ? <LockOutlined /> : <EditOutlined />} aria-label={item.locked ? `${item.label}: cấu hình cố định` : `Sửa ${item.label.toLocaleLowerCase()}`} onClick={item.edit} disabled={Boolean(item.locked)} /></span></Tooltip></div>)}
            </Card>
            <Card size="small" title={<span className="retail-settings-card-title"><ApiOutlined /> Tính năng & kết nối</span>}>
                {features.map(([key, label, description]) => <div className="retail-feature-row" key={key}><span>{label}</span><div><Tag color={bootstrap?.capabilities?.[key] ? 'green' : 'default'}>{bootstrap?.capabilities?.[key] ? 'Sẵn sàng' : 'Chưa sẵn sàng'}</Tag><Tooltip title={`Xem thiết lập ${label.toLocaleLowerCase()}`}><Button size="small" type="text" icon={<SettingOutlined />} aria-label={`Thiết lập ${label.toLocaleLowerCase()}`} onClick={() => setFeatureDetail({ key, label, description })} /></Tooltip></div></div>)}
                <div className="retail-settings-note"><LockOutlined /><span>Trạng thái tính năng phụ thuộc các phân hệ đã cài đặt và kết nối hiện có.</span></div>
            </Card>
        </div>
        {canIntegrate && <RetailIntegrationPanel branch={branch} callAdminApi={callAdminApi} identity={identity} onCommand={onCommand} reloadRef={integrationReloadRef} />}
        <Modal title={<span><ShopOutlined /> Chọn chi nhánh vận hành</span>} open={branchEditing} centered width={460} okText="Áp dụng" cancelText="Hủy" onCancel={() => setBranchEditing(false)} okButtonProps={{ disabled: !selectedBranch }} onOk={() => { setBranchEditing(false); onBranchChange(selectedBranch); }}><p>Chọn chi nhánh để xem dữ liệu và thực hiện nghiệp vụ.</p><Select aria-label="Chi nhánh vận hành mới" showSearch optionFilterProp="label" value={selectedBranch} onChange={setSelectedBranch} options={branchOptions} style={{ width: '100%' }} /></Modal>
        <Modal title={featureDetail?.label} open={Boolean(featureDetail)} centered width={480} onCancel={() => setFeatureDetail(null)} footer={<Button onClick={() => setFeatureDetail(null)}>Đóng</Button>}><Tag color={bootstrap?.capabilities?.[featureDetail?.key] ? 'green' : 'default'}>{bootstrap?.capabilities?.[featureDetail?.key] ? 'Sẵn sàng' : 'Chưa sẵn sàng'}</Tag><p>{featureDetail?.description}</p>{featureDetail?.key === 'accounting' && <p>Chỉnh liên kết pháp nhân và hàng hóa tại mục Tích hợp kế toán bên dưới khi phân hệ Kế toán & thuế sẵn sàng.</p>}</Modal>
    </div>;
}
