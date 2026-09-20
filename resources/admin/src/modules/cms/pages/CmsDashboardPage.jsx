import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Empty from 'antd/es/empty';
import Tag from 'antd/es/tag';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import ArrowRightOutlined from '@ant-design/icons/ArrowRightOutlined';
import useAdminRouteResource from '../../../shared/hooks/useAdminRouteResource';
import { adminApi } from '../../../shared/config/routes';
import './CmsDashboardPage.css';

const shortcuts = [
    ['Tin tức', 'Biên tập bài viết, danh mục và chuyên đề', 'posts', 'cms.post.view'],
    ['Trang nội dung', 'Giới thiệu, liên hệ và các trang thông tin', 'pages', 'cms.view'],
    ['Landing pages', 'Sắp xếp nội dung trang chủ và chiến dịch', 'landing-pages', 'cms.view'],
    ['Thư viện media', 'Quản lý hình ảnh và tài liệu', 'media', 'cms.media.manage'],
    ['Menus', 'Tổ chức điều hướng trên website', 'menus', 'cms.menu.manage'],
];

export default function CmsDashboardPage({ callAdminApi, currentPermissions = [] }) {
    const { data, loading, error, reload } = useAdminRouteResource({
        loader: async () => (await callAdminApi(adminApi('cms/dashboard'))).data,
    });
    const metrics = [
        ['Trang nội dung', data?.pages, 'pages'],
        ['Bài viết', data?.posts?.total, 'posts'],
        ['Tệp media', data?.media, 'media'],
        ['Menu điều hướng', data?.menus, 'menus'],
    ].filter(([, value]) => value != null);
    const states = [['Đã xuất bản', 'published', '#0f766e'], ['Bản nháp', 'draft', '#d97706'], ['Hẹn giờ', 'scheduled', '#2563eb']];
    return <div className="cms-overview">
        <header className="cms-overview-hero">
            <div><span className="cms-overview-eyebrow">QUẢN TRỊ NỘI DUNG</span><h1>Tổng quan CMS</h1><p>Theo dõi nội dung và quản lý hoạt động xuất bản của website.</p></div>
            <Button icon={<ReloadOutlined />} onClick={reload} loading={loading}>Làm mới</Button>
        </header>
        {error && <Alert type="error" showIcon message="Không tải được tổng quan" description={error} action={<Button onClick={reload}>Thử lại</Button>} />}
        {loading ? <Card loading /> : data && <>
            <div className="cms-overview-metrics">{metrics.map(([label, value, path]) => <a key={path} href={`/admin/cms/${path}`} className="cms-overview-metric"><span>{label}</span><strong>{value.toLocaleString('vi-VN')}</strong><small>Quản lý <ArrowRightOutlined /></small></a>)}</div>
            <div className="cms-overview-columns">
                <Card title="Truy cập nhanh" className="cms-overview-shortcuts">
                    {shortcuts.filter(([, , , permission]) => currentPermissions.includes(permission)).map(([label, description, path]) => <a href={`/admin/cms/${path}`} key={path}><div><strong>{label}</strong><p>{description}</p></div><ArrowRightOutlined /></a>)}
                </Card>
                {data.posts && <Card title="Tình hình xuất bản">
                    <p className="cms-overview-note">Thống kê bài viết gốc của website đang chọn.</p>
                    {states.map(([label, key, color]) => <div className="cms-overview-state" key={key}><div><span>{label}</span><strong>{data.posts[key]}</strong></div><div className="cms-overview-bar"><span style={{ width: `${data.posts.total ? data.posts[key] / data.posts.total * 100 : 0}%`, background: color }} /></div></div>)}
                    <p className="cms-overview-note">Bài hẹn giờ được tính riêng, chưa thuộc số bài đã xuất bản.</p>
                </Card>}
            </div>
            {data.posts && <Card title="Bài viết vừa cập nhật" extra={<a href="/admin/cms/posts">Quản lý tin tức <ArrowRightOutlined /></a>}>
                {data.recent_posts.length ? <div className="cms-overview-recent">{data.recent_posts.map(post => {
                    const scheduled = post.status === 'published' && post.publish_at && new Date(post.publish_at) > new Date();
                    return <div key={post.id}><strong>{post.title}</strong><span><Tag color={scheduled ? 'blue' : post.status === 'published' ? 'green' : 'gold'}>{scheduled ? 'Hẹn giờ' : post.status === 'published' ? 'Đã xuất bản' : 'Bản nháp'}</Tag><time>{new Date(post.updated_at).toLocaleDateString('vi-VN')}</time></span></div>;
                })}</div> : <Empty description="Chưa có bài viết. Bắt đầu tại mục Tin tức." />}
            </Card>}
        </>}
    </div>;
}
