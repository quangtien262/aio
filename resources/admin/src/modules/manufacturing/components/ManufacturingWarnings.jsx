import { useState } from 'react';
import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Modal from 'antd/es/modal';
import Typography from 'antd/es/typography';
import { adminPath } from '../../../shared/config/routes.js';

const moduleGuides = {
    purchasing: { name: 'Mua hàng và nhà cung cấp', purpose: 'Mua nguyên phụ liệu, duyệt đơn mua và nhận hàng cho sản xuất FOB.' },
    inventory: { name: 'Quản lý kho', purpose: 'Quản lý tồn kho, giữ và cấp nguyên phụ liệu cho lệnh sản xuất.' },
    merchandise: { name: 'Danh mục hàng hóa dùng chung', purpose: 'Quản lý danh mục nguyên phụ liệu và biến thể màu, kích cỡ.' },
};
const blockerMessage = (item) => {
    const message = typeof item === 'string' ? item : item?.message ?? item?.label ?? item?.code ?? 'Cần kiểm tra thiết lập vận hành.';
    const name = typeof message === 'string' ? Object.values(moduleGuides).find((guide) => message.includes(guide.name))?.name : null;
    if (!name) return message;
    const start = message.indexOf(name);
    return <>{message.slice(0, start)}<strong>{name}</strong>{message.slice(start + name.length)}</>;
};

export default function ManufacturingWarnings({ readiness, paused, loading, error, onCheck, onResume }) {
    const [active, setActive] = useState(null);
    const blockers = (Array.isArray(readiness) ? readiness : Array.isArray(readiness?.blockers) ? readiness.blockers : []).filter(Boolean);
    const canOpenStore = readiness?.can_open_app_store === true;
    const hasModuleGuide = blockers.some((item) => Object.hasOwn(moduleGuides, item?.code));
    const resolved = active === 'paused' ? !paused : blockers.length === 0;
    const warning = (kind, title, description) => <Alert
        className="mfg-actionable-warning" type="warning" showIcon message={title} description={description}
        onClick={() => setActive(kind)}
        action={<Button onClick={(event) => { event.stopPropagation(); setActive(kind); }}>Xem cách khắc phục</Button>}
    />;

    return <>
        {paused && warning('paused', 'Hệ thống đang tạm dừng ghi dữ liệu', 'Có thể xem và đối chiếu trước khi tiếp tục vận hành.')}
        {blockers.length > 0 && warning('readiness', 'Cần hoàn tất thiết lập vận hành', <ul>{blockers.map((item, index) => <li key={`${item?.code ?? 'warning'}-${index}`}>{blockerMessage(item)}</li>)}</ul>)}
        <Modal title={active === 'paused' ? 'Khắc phục tạm dừng vận hành' : 'Hoàn tất thiết lập vận hành'} open={Boolean(active)} onCancel={() => setActive(null)} width={680} rootClassName="mfg-remediation-modal" footer={[
            <Button key="close" onClick={() => setActive(null)}>Đóng</Button>,
            active === 'readiness' && !resolved && hasModuleGuide && canOpenStore && <Button key="store" href={adminPath('modules')} target="_blank" rel="noopener noreferrer">Mở App Store</Button>,
            active === 'paused' && !resolved && onResume && <Button key="resume" type="primary" disabled={loading} onClick={() => { setActive(null); onResume(); }}>Tiếp tục vận hành</Button>,
            <Button key="check" type="primary" loading={loading} onClick={onCheck}>Kiểm tra lại</Button>,
        ].filter(Boolean)}>
            {error ? <Alert type="error" showIcon message="Chưa kiểm tra được trạng thái vận hành" description={error} />
                : loading ? <Typography.Paragraph>Đang kiểm tra trạng thái vận hành…</Typography.Paragraph>
                    : resolved ? <Alert type="success" showIcon message={active === 'paused' ? 'Hệ thống đã tiếp tục vận hành.' : 'Đã hoàn tất các thiết lập được cảnh báo.'} />
                        : active === 'paused' ? <>
                            <Typography.Paragraph>Hệ thống đang tạm dừng ghi dữ liệu trên toàn app. Chỉ tiếp tục khi đã hoàn tất bảo trì hoặc đối chiếu.</Typography.Paragraph>
                            <Typography.Paragraph>{onResume ? 'Chọn “Tiếp tục vận hành”, kiểm tra thông tin và xác nhận trong biểu mẫu.' : 'Liên hệ System Owner hoặc quản trị toàn hệ thống để mở Thiết lập sản xuất và chọn “Tiếp tục vận hành”.'}</Typography.Paragraph>
                        </> : <>
                            {blockers.map((item, index) => {
                                const guide = Object.hasOwn(moduleGuides, item?.code) ? moduleGuides[item.code] : null;
                                return <section className="mfg-remediation-section" key={`${item?.code ?? 'warning'}-${index}`}>
                                    <Typography.Title level={5}>{guide?.name ?? 'Thiết lập cần kiểm tra'}</Typography.Title>
                                    <Typography.Paragraph>{blockerMessage(item)}</Typography.Paragraph>
                                    {guide ? <>
                                        <Typography.Paragraph type="secondary">{guide.purpose}</Typography.Paragraph>
                                        <ol>
                                            <li>Mở App Store và tìm ứng dụng <strong>{guide.name}</strong>.</li>
                                            <li>Nếu chưa cài, chọn “Cài đặt App”. Nếu đã cài, kiểm tra và nâng cấp lên phiên bản hỗ trợ sản xuất khi có bản cập nhật.</li>
                                            <li>Chọn “Bật App” nếu đang tắt. Quay lại cửa sổ này và chọn “Kiểm tra lại”.</li>
                                        </ol>
                                    </> : <Typography.Paragraph>Kiểm tra nội dung cảnh báo trong Thiết lập sản xuất hoặc nhờ quản trị viên hỗ trợ, sau đó chọn “Kiểm tra lại”.</Typography.Paragraph>}
                                </section>;
                            })}
                            {hasModuleGuide && <Typography.Paragraph type="secondary">{canOpenStore ? 'App Store mở trong tab mới. Việc cài đặt, bật và nâng cấp cần quyền tương ứng. Nếu chưa có website hoạt động để truy cập App Store, hãy nhờ quản trị hệ thống kiểm tra cấu hình website.' : 'Tài khoản hiện tại chưa có quyền truy cập App Store. Gửi nội dung cảnh báo cho quản trị hệ thống để cài, nâng cấp hoặc bật ứng dụng.'}</Typography.Paragraph>}
                        </>}
        </Modal>
    </>;
}
