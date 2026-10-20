import { useMemo, useRef, useState } from 'react';
import AppstoreOutlined from '@ant-design/icons/AppstoreOutlined';
import ArrowRightOutlined from '@ant-design/icons/ArrowRightOutlined';
import AuditOutlined from '@ant-design/icons/AuditOutlined';
import CheckCircleFilled from '@ant-design/icons/CheckCircleFilled';
import DatabaseOutlined from '@ant-design/icons/DatabaseOutlined';
import FileTextOutlined from '@ant-design/icons/FileTextOutlined';
import HomeOutlined from '@ant-design/icons/HomeOutlined';
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
import { toAppTerminology } from '../utils/appTerminology';
import '../styles/module-store.css';

const categories = [
    { key: 'all', name: 'Tất cả ứng dụng', icon: AppstoreOutlined },
    { key: 'sales', name: 'Bán hàng & dịch vụ', icon: ShopOutlined },
    { key: 'operations', name: 'Kho & mua hàng', icon: DatabaseOutlined },
    { key: 'website', name: 'Website & nội dung', icon: FileTextOutlined },
    { key: 'finance', name: 'Tài chính & kế toán', icon: WalletOutlined },
    { key: 'people', name: 'Nhân sự & cộng tác', icon: TeamOutlined },
    { key: 'other', name: 'Tiện ích khác', icon: ProjectOutlined },
];

const presentations = {
    'retail-pos': { category: 'sales', icon: ShoppingCartOutlined, color: '#0b806a', background: '#e5f5ed', rank: 1 },
    'fnb-pos': { category: 'sales', icon: ShopOutlined, color: '#b66b22', background: '#fff0dc', rank: 2 },
    cms: { category: 'website', icon: FileTextOutlined, color: '#6455bb', background: '#eeebfc', rank: 3 },
    inventory: { category: 'operations', icon: DatabaseOutlined, color: '#31799b', background: '#e6f2f8', rank: 4 },
    merchandise: { category: 'operations', icon: InboxOutlined, color: '#bc7140', background: '#fbecdf', rank: 5 },
    purchasing: { category: 'operations', icon: ShoppingCartOutlined, color: '#388a75', background: '#e8f5ef', rank: 6 },
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

export default function ModuleStoreCatalog({ modules = [], onOpenDetails, onOpenChangelog, busy = false }) {
    const [query, setQuery] = useState('');
    const [category, setCategory] = useState('all');
    const [status, setStatus] = useState('all');
    const [sort, setSort] = useState('recommended');
    const catalogRef = useRef(null);
    const installed = modules.filter((item) => item.is_installed);
    const active = modules.filter((item) => item.status === 'enabled');
    const featured = modules.find((item) => item.key === 'retail-pos') ?? modules[0];
    const featuredIsRetail = featured?.key === 'retail-pos';
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
                <div>
                    <span className="market-eyebrow">AIO APP STORE</span>
                    <h1>Không gian ứng dụng</h1>
                    <p>Công cụ cho từng nhu cầu. Kết nối trong một hệ thống.</p>
                </div>
                <div className="market-library-summary"><span><CheckCircleFilled /> {active.length} app đang hoạt động</span><span>{modules.length} ứng dụng trong thư viện</span></div>
            </header>

            <section className="market-spotlight-grid" aria-label="Ứng dụng nổi bật">
                {featured ? <div className="market-featured">
                    <div className="market-featured-copy">
                        <span className="market-featured-label">KHÁM PHÁ GIẢI PHÁP AIO</span>
                        <h2>{featuredIsRetail ? <>Quản lý bán lẻ,<br />kết nối toàn chuỗi.</> : featured.name}</h2>
                        <p>{featuredIsRetail ? 'Bán hàng, quản lý tồn kho và điều hành nhiều chi nhánh ngay trên AIO.' : toAppTerminology(featured.description)}</p>
                        <Button className="market-featured-cta" disabled={busy} onClick={() => onOpenDetails(featured)}>Khám phá ứng dụng <ArrowRightOutlined /></Button>
                        <span className="market-featured-app-name">{featured.name} <span>· v{featured.latest_version}</span></span>
                    </div>
                    <div className="market-featured-art" aria-hidden="true">
                        <div className="market-art-orbit market-art-orbit--one" /><div className="market-art-orbit market-art-orbit--two" />
                        <AppIcon moduleCard={featured} className="market-featured-icon" />
                        <span className="market-art-tile market-art-tile--stock"><DatabaseOutlined /><span>{featuredIsRetail ? 'Tồn kho' : 'Dữ liệu'}</span></span>
                        <span className="market-art-tile market-art-tile--branch"><ShopOutlined /><span>{featuredIsRetail ? 'Chi nhánh' : 'Kết nối'}</span></span>
                        <span className="market-art-connection market-art-connection--one" /><span className="market-art-connection market-art-connection--two" />
                    </div>
                </div> : null}
                <div className="market-library-card">
                    <div className="market-library-card-heading"><span className="market-eyebrow">KHÔNG GIAN CỦA BẠN</span><span className="market-live-dot" /></div>
                    <h2>Ứng dụng của bạn</h2>
                    <p>{installed.length ? `${installed.length} ứng dụng đã được cài đặt trên hệ thống.` : 'Bắt đầu bằng ứng dụng phù hợp với doanh nghiệp của bạn.'}</p>
                    <div className="market-library-apps">
                        {active.slice(0, 3).map((item) => <button key={item.key} className="market-library-app" disabled={busy} onClick={() => onOpenDetails(item)}><AppIcon moduleCard={item} /><span>{item.name}</span><ArrowRightOutlined /></button>)}
                        {!active.length ? <div className="market-library-placeholder"><AppstoreOutlined /><span>Các ứng dụng đang bật sẽ xuất hiện tại đây.</span></div> : null}
                    </div>
                    <button className="market-library-link" onClick={showInstalled}>Xem ứng dụng đã cài <ArrowRightOutlined /></button>
                </div>
            </section>

            <section className="market-catalog" ref={catalogRef} aria-label="Danh mục ứng dụng">
                <aside className="market-sidebar">
                    <span className="market-sidebar-label">DANH MỤC</span>
                    <nav aria-label="Nhóm ứng dụng">
                        {visibleCategories.map((item) => {
                            const Icon = item.icon;
                            const count = item.key === 'all' ? modules.length : modules.filter((moduleCard) => presentationFor(moduleCard).category === item.key).length;
                            return <button key={item.key} className={`market-category ${category === item.key ? 'is-active' : ''}`} aria-pressed={category === item.key} onClick={() => setCategory(item.key)}><Icon /><span>{item.name}</span><small>{count}</small></button>;
                        })}
                    </nav>
                    <div className="market-sidebar-note"><LinkOutlined /><strong>Kết nối để làm được nhiều hơn</strong><p>Xem các app cần thiết và cài đặt ngay trong phần chi tiết ứng dụng.</p></div>
                </aside>
                <div className="market-catalog-content">
                    <div className="market-search-row">
                        <Input aria-label="Tìm ứng dụng" prefix={<SearchOutlined />} placeholder="Tìm ứng dụng, tính năng…" allowClear value={query} onChange={(event) => setQuery(event.target.value)} className="market-search" />
                        <Select aria-label="Sắp xếp ứng dụng" value={sort} onChange={setSort} options={[{ value: 'recommended', label: 'Đề xuất' }, { value: 'name', label: 'Tên A–Z' }]} className="market-sort" />
                    </div>
                    <div className="market-filter-tabs" role="group" aria-label="Lọc trạng thái cài đặt">
                        {filters.map((item) => <button key={item.key} className={status === item.key ? 'is-active' : ''} aria-pressed={status === item.key} onClick={() => setStatus(item.key)}>{item.name}<span>{counts[item.key]}</span></button>)}
                    </div>
                    <div className="market-results-heading"><h2>{categories.find((item) => item.key === category)?.name}</h2><span role="status" aria-live="polite">{filteredModules.length} ứng dụng</span></div>
                    {filteredModules.length ? <div className="market-app-grid">
                        {filteredModules.map((item) => {
                            const appearance = presentationFor(item);
                            const categoryName = categories.find((entry) => entry.key === appearance.category)?.name;
                            const state = statusFor(item);
                            return <article key={item.key} className="market-app-card">
                                <div className="market-app-card-heading"><AppIcon moduleCard={item} /><span className={`market-app-state market-app-state--${state.tone}`}><i />{state.label}</span></div>
                                <span className="market-app-category">{categoryName}</span>
                                <h3><button disabled={busy} onClick={() => onOpenDetails(item)}>{item.name}</button></h3>
                                <p className="market-app-description">{toAppTerminology(item.description) || 'Mở rộng tính năng quản trị trên nền tảng AIO.'}</p>
                                <div className="market-app-meta"><span>v{item.installed_version ?? item.latest_version}</span>{item.dependencies?.length ? <span><LinkOutlined /> {item.dependencies.length} app cần thiết</span> : <span>Ứng dụng độc lập</span>}</div>
                                <div className="market-app-card-footer"><button className="market-changelog-link" disabled={busy} onClick={() => onOpenChangelog(item)}>Nhật ký thay đổi</button><Button disabled={busy} onClick={() => onOpenDetails(item)}>{item.is_installed ? 'Quản lý' : 'Xem chi tiết'}<ArrowRightOutlined /></Button></div>
                            </article>;
                        })}
                    </div> : <div className="market-empty"><Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Không tìm thấy ứng dụng phù hợp" /><p>Thử từ khóa khác hoặc thay đổi bộ lọc.</p><Button onClick={resetFilters}>Xóa bộ lọc</Button></div>}
                    <footer className="market-catalog-footer"><AppstoreOutlined /><span>Các ứng dụng được quản lý tập trung qua App Store AIO.</span></footer>
                </div>
            </section>
        </div>
    );
}
