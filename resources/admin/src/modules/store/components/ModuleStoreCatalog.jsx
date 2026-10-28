import { useMemo, useRef, useState } from 'react';
import AppstoreOutlined from '@ant-design/icons/AppstoreOutlined';
import ArrowRightOutlined from '@ant-design/icons/ArrowRightOutlined';
import AuditOutlined from '@ant-design/icons/AuditOutlined';
import BuildOutlined from '@ant-design/icons/BuildOutlined';
import CheckCircleFilled from '@ant-design/icons/CheckCircleFilled';
import DatabaseOutlined from '@ant-design/icons/DatabaseOutlined';
import DownloadOutlined from '@ant-design/icons/DownloadOutlined';
import ExperimentOutlined from '@ant-design/icons/ExperimentOutlined';
import FileTextOutlined from '@ant-design/icons/FileTextOutlined';
import GlobalOutlined from '@ant-design/icons/GlobalOutlined';
import HomeOutlined from '@ant-design/icons/HomeOutlined';
import HistoryOutlined from '@ant-design/icons/HistoryOutlined';
import InboxOutlined from '@ant-design/icons/InboxOutlined';
import LinkOutlined from '@ant-design/icons/LinkOutlined';
import ProjectOutlined from '@ant-design/icons/ProjectOutlined';
import SearchOutlined from '@ant-design/icons/SearchOutlined';
import ShopOutlined from '@ant-design/icons/ShopOutlined';
import ShoppingCartOutlined from '@ant-design/icons/ShoppingCartOutlined';
import SolutionOutlined from '@ant-design/icons/SolutionOutlined';
import TeamOutlined from '@ant-design/icons/TeamOutlined';
import WalletOutlined from '@ant-design/icons/WalletOutlined';
import Button from 'antd/es/button';
import Empty from 'antd/es/empty';
import Input from 'antd/es/input';
import Select from 'antd/es/select';
import Tooltip from 'antd/es/tooltip';
import { toAppTerminology } from '../utils/appTerminology';
import '../styles/module-store.css';

const categories = [
    { key: 'all', name: 'Tất cả ứng dụng', icon: AppstoreOutlined },
    { key: 'sales', name: 'Bán hàng & dịch vụ', icon: ShopOutlined },
    { key: 'operations', name: 'Kho & vận hành', icon: DatabaseOutlined },
    { key: 'website', name: 'Website & nội dung', icon: FileTextOutlined },
    { key: 'finance', name: 'Tài chính & kế toán', icon: WalletOutlined },
    { key: 'people', name: 'Nhân sự & cộng tác', icon: TeamOutlined },
    { key: 'other', name: 'Tiện ích khác', icon: ProjectOutlined },
];

const presentations = {
    'website-library': { category: 'website', icon: GlobalOutlined, color: '#167d88', background: '#e6f5f5', rank: 0 },
    'retail-pos': { category: 'sales', icon: ShoppingCartOutlined, color: '#0b806a', background: '#e5f5ed', rank: 1 },
    'fnb-pos': { category: 'sales', icon: ShopOutlined, color: '#b66b22', background: '#fff0dc', rank: 2 },
    cms: { category: 'website', icon: FileTextOutlined, color: '#6455bb', background: '#eeebfc', rank: 3 },
    inventory: { category: 'operations', icon: DatabaseOutlined, color: '#31799b', background: '#e6f2f8', rank: 4 },
    merchandise: { category: 'operations', icon: InboxOutlined, color: '#bc7140', background: '#fbecdf', rank: 5 },
    purchasing: { category: 'operations', icon: ShoppingCartOutlined, color: '#388a75', background: '#e8f5ef', rank: 6 },
    manufacturing: { category: 'operations', icon: BuildOutlined, color: '#7960af', background: '#f0ebfa', rank: 6 },
    'accounting-tax': { category: 'finance', icon: AuditOutlined, color: '#597142', background: '#eef3e3', rank: 7 },
    'minvoice-connector': { category: 'finance', icon: LinkOutlined, color: '#6288b2', background: '#edf3fc', rank: 8 },
    hrm: { category: 'people', icon: TeamOutlined, color: '#358a8c', background: '#e5f5f3', rank: 9 },
    payroll: { category: 'people', icon: WalletOutlined, color: '#8b7351', background: '#f6f0e5', rank: 10 },
    catalog: { category: 'website', icon: AppstoreOutlined, color: '#5178bf', background: '#eaf0fc', rank: 11 },
    'real-estate': { category: 'sales', icon: HomeOutlined, color: '#a16c86', background: '#f9eaf0', rank: 12 },
    project: { category: 'people', icon: ProjectOutlined, color: '#7765a4', background: '#f0ebf8', rank: 13 },
    customers: { category: 'sales', icon: SolutionOutlined, color: '#0d817b', background: '#e3f5f1', rank: 14 },
};

function presentationFor(moduleCard) {
    return presentations[moduleCard.key] ?? { category: 'other', icon: AppstoreOutlined, color: '#60758a', background: '#ecf0f5', rank: 99 };
}

function normalizeSearch(value) {
    return String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLocaleLowerCase('vi');
}

function statusFor(moduleCard) {
    if (moduleCard.built_in) return { label: 'Có sẵn', tone: 'enabled' };
    if (moduleCard.upgrade_available) return { label: 'Có bản cập nhật', tone: 'update' };
    if (!moduleCard.is_installed) return { label: 'Chưa cài đặt', tone: 'available' };
    if (moduleCard.status === 'enabled') return { label: 'Đang hoạt động', tone: 'enabled' };
    if (moduleCard.status === 'disabled') return { label: 'Đang tắt', tone: 'disabled' };
    return { label: 'Đã cài đặt', tone: 'installed' };
}

function AppIcon({ moduleCard, className = '' }) {
    const appearance = presentationFor(moduleCard);
    const Icon = appearance.icon;
    return <span className={`market-app-icon ${className}`} style={{ '--app-color': appearance.color, '--app-background': appearance.background }}><Icon /></span>;
}

export default function ModuleStoreCatalog({ modules = [], onOpenDetails, onOpenChangelog, onOpenTestData, canManageTestData = false, busy = false }) {
    const [query, setQuery] = useState('');
    const [category, setCategory] = useState('all');
    const [status, setStatus] = useState('all');
    const [sort, setSort] = useState('recommended');
    const catalogRef = useRef(null);
    const installed = modules.filter((item) => item.is_installed);
    const active = modules.filter((item) => item.status === 'enabled');
    const featured = modules.find((item) => item.key === 'retail-pos') ?? modules[0];
    const featuredIsRetail = featured?.key === 'retail-pos';
    const secondaryFeatured = modules.find((item) => item.key === 'fnb-pos' && item.key !== featured?.key)
        ?? modules.find((item) => item.key !== featured?.key);
    const filters = [
        { key: 'all', name: 'Tất cả' }, { key: 'installed', name: 'Đã cài đặt' },
        { key: 'available', name: 'Chưa cài đặt' }, { key: 'updates', name: 'Cần cập nhật' },
    ];
    const visibleCategories = categories.filter((item) => item.key === 'all' || modules.some((moduleCard) => presentationFor(moduleCard).category === item.key));
    const matchingModules = useMemo(() => {
        const needle = normalizeSearch(query.trim());
        return modules.filter((item) => {
            if (category !== 'all' && presentationFor(item).category !== category) return false;
            return !needle || normalizeSearch(`${item.name} ${item.description} ${item.key}`).includes(needle);
        });
    }, [modules, query, category]);
    const counts = {
        all: matchingModules.length,
        installed: matchingModules.filter((item) => item.is_installed).length,
        available: matchingModules.filter((item) => !item.is_installed).length,
        updates: matchingModules.filter((item) => item.upgrade_available).length,
    };
    const filteredModules = useMemo(() => {
        return matchingModules.filter((item) => {
            if (status === 'installed' && !item.is_installed) return false;
            if (status === 'available' && item.is_installed) return false;
            if (status === 'updates' && !item.upgrade_available) return false;
            return true;
        }).sort((a, b) => sort === 'name'
            ? a.name.localeCompare(b.name, 'vi')
            : presentationFor(a).rank - presentationFor(b).rank || a.name.localeCompare(b.name, 'vi'));
    }, [matchingModules, status, sort]);
    const resetFilters = () => { setQuery(''); setCategory('all'); setStatus('all'); };
    const showInstalled = () => {
        resetFilters();
        setStatus('installed');
        catalogRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    return (
        <div className="app-marketplace">
            <header className="market-page-heading">
                <div className="market-brand">
                    <span className="market-brand-icon"><AppstoreOutlined /></span>
                    <div><h1>App Store</h1><p>Ứng dụng cho mọi hoạt động của doanh nghiệp.</p></div>
                </div>
                <Input aria-label="Tìm ứng dụng" prefix={<SearchOutlined />} placeholder="Tìm ứng dụng, tính năng…" allowClear value={query} onChange={(event) => setQuery(event.target.value)} className="market-search" />
                {canManageTestData ? <Button className="market-test-data-shortcut" icon={<ExperimentOutlined />} aria-label="Quản lý toàn bộ data demo" title="Quản lý toàn bộ data demo" disabled={busy} onClick={() => onOpenTestData(null)}>Data demo</Button> : null}
                <Button className="market-library-shortcut" icon={<DownloadOutlined />} onClick={showInstalled}>Ứng dụng của tôi <span>{installed.length}</span></Button>
            </header>

            <section className="market-catalog" aria-label="Danh mục ứng dụng">
                <aside className="market-sidebar">
                    <span className="market-sidebar-label">KHÁM PHÁ</span>
                    <nav aria-label="Nhóm ứng dụng">
                        {visibleCategories.map((item) => {
                            const Icon = item.icon;
                            const count = item.key === 'all' ? modules.length : modules.filter((moduleCard) => presentationFor(moduleCard).category === item.key).length;
                            return <button key={item.key} className={`market-category ${category === item.key ? 'is-active' : ''}`} aria-pressed={category === item.key} onClick={() => setCategory(item.key)}><Icon /><span>{item.name}</span><small>{count}</small></button>;
                        })}
                    </nav>
                    <div className="market-sidebar-library">
                        <span className="market-sidebar-label">THƯ VIỆN CỦA BẠN</span>
                        <button onClick={showInstalled}><DownloadOutlined /><span>Đã cài đặt</span><strong>{installed.length}</strong></button>
                        <div><CheckCircleFilled /><span>Đang hoạt động</span><strong>{active.length}</strong></div>
                    </div>
                    <div className="market-sidebar-note"><LinkOutlined /><strong>Một hệ thống, nhiều khả năng</strong><p>Các ứng dụng kết nối và dùng chung dữ liệu trên nền tảng AIO.</p></div>
                </aside>
                <div className="market-catalog-content">
                    {!query.trim() && category === 'all' && status === 'all' && featured ? <section className={`market-spotlight-grid${!secondaryFeatured ? ' market-spotlight-grid--single' : ''}`} aria-label="Ứng dụng nổi bật">
                        <div className="market-featured">
                            <div className="market-featured-copy">
                                <span className="market-featured-label">TIÊU ĐIỂM ỨNG DỤNG</span>
                                <h2>{featuredIsRetail ? <>Bán lẻ liền mạch.<br />Quản lý toàn chuỗi.</> : featured.name}</h2>
                                <p>{featuredIsRetail ? 'Bán hàng, tồn kho và nhiều chi nhánh trong một ứng dụng.' : toAppTerminology(featured.description)}</p>
                                <Button className="market-featured-cta" disabled={busy} onClick={() => onOpenDetails(featured)}>Khám phá ngay <ArrowRightOutlined /></Button>
                            </div>
                            <div className="market-featured-art" aria-hidden="true">
                                <span className="market-art-orbit" /><AppIcon moduleCard={featured} className="market-featured-icon" />
                                <span className="market-art-tile"><DatabaseOutlined /><ShopOutlined /></span>
                            </div>
                        </div>
                        {secondaryFeatured ? <div className="market-secondary-feature">
                            <div><AppIcon moduleCard={secondaryFeatured} /><span className="market-featured-label">GIẢI PHÁP NỔI BẬT</span></div>
                            <h2>{secondaryFeatured.name}</h2>
                            <p>{toAppTerminology(secondaryFeatured.description)}</p>
                            <button disabled={busy} onClick={() => onOpenDetails(secondaryFeatured)}>Tìm hiểu ứng dụng <ArrowRightOutlined /></button>
                        </div> : null}
                    </section> : null}
                    <div className="market-results-heading" ref={catalogRef}>
                        <div><h2>{categories.find((item) => item.key === category)?.name}</h2><span role="status" aria-live="polite">{filteredModules.length} ứng dụng{query.trim() ? ` cho “${query.trim()}”` : ' trong thư viện'}</span></div>
                        <div className="market-results-controls">
                            <div className="market-filter-tabs" role="group" aria-label="Lọc trạng thái cài đặt">
                                {filters.map((item) => <button key={item.key} className={status === item.key ? 'is-active' : ''} aria-pressed={status === item.key} onClick={() => setStatus(item.key)}>{item.name}<span>{counts[item.key]}</span></button>)}
                            </div>
                            <Select aria-label="Sắp xếp ứng dụng" value={sort} onChange={setSort} options={[{ value: 'recommended', label: 'Đề xuất' }, { value: 'name', label: 'Tên A–Z' }]} className="market-sort" />
                        </div>
                    </div>
                    {filteredModules.length ? <div className="market-app-grid">
                        {filteredModules.map((item) => {
                            const appearance = presentationFor(item);
                            const categoryName = categories.find((entry) => entry.key === appearance.category)?.name;
                            const state = statusFor(item);
                            return <article key={item.key} className="market-app-card">
                                <div className="market-app-card-heading">
                                    <button className="market-app-icon-button" disabled={busy} onClick={() => onOpenDetails(item)} aria-label={`Xem chi tiết ${item.name}`}><AppIcon moduleCard={item} /></button>
                                    <div><h3><button disabled={busy} onClick={() => onOpenDetails(item)}>{item.name}</button></h3><span className="market-app-category">{categoryName}</span></div>
                                </div>
                                <p className="market-app-description">{toAppTerminology(item.description) || 'Mở rộng tính năng quản trị trên nền tảng AIO.'}</p>
                                <div className="market-app-meta">{item.built_in ? <span><GlobalOutlined /> Thư viện mẫu website</span> : <button className="market-changelog-link" disabled={busy} onClick={() => onOpenChangelog(item)} aria-label={`Nhật ký thay đổi của ${item.name}`} title="Xem nhật ký thay đổi"><HistoryOutlined /> v{item.installed_version ?? item.latest_version}</button>}{item.dependencies?.length ? <span><LinkOutlined /> {item.dependencies.length} app cần thiết</span> : null}</div>
                                <div className="market-app-card-footer"><span className={`market-app-state market-app-state--${state.tone}`}><i />{state.label}</span><div className="market-app-card-actions">
                                    {!item.built_in && canManageTestData && item.status === 'enabled' ? <Tooltip title={!item.test_data?.supported ? 'App chưa hỗ trợ dữ liệu demo' : !item.test_data?.storage_ready ? 'Cần cập nhật cấu trúc dữ liệu' : 'Tạo và quản lý dữ liệu demo theo đợt'}><Button icon={<ExperimentOutlined />} aria-label={`Tạo data demo cho ${item.name}`} disabled={busy || !item.test_data?.supported || !item.test_data?.storage_ready} onClick={() => onOpenTestData(item)}>Tạo data demo</Button></Tooltip> : null}
                                    <Button disabled={busy} onClick={() => onOpenDetails(item)}>{item.built_in ? 'Khám phá' : item.is_installed ? 'Quản lý' : 'Chi tiết'}<ArrowRightOutlined /></Button>
                                </div></div>
                            </article>;
                        })}
                    </div> : <div className="market-empty"><Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Không tìm thấy ứng dụng phù hợp" /><p>Thử từ khóa khác hoặc thay đổi bộ lọc.</p><Button onClick={resetFilters}>Xóa bộ lọc</Button></div>}
                    <footer className="market-catalog-footer"><AppstoreOutlined /><span>Các ứng dụng được quản lý tập trung qua App Store AIO.</span></footer>
                </div>
            </section>
        </div>
    );
}
