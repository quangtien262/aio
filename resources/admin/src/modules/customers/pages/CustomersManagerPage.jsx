import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import TeamOutlined from '@ant-design/icons/TeamOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';
import ArchiveOutlined from '@ant-design/icons/InboxOutlined';
import BookOutlined from '@ant-design/icons/BookOutlined';
import CheckCircleOutlined from '@ant-design/icons/CheckCircleOutlined';
import CopyOutlined from '@ant-design/icons/CopyOutlined';
import EditOutlined from '@ant-design/icons/EditOutlined';
import FilterOutlined from '@ant-design/icons/FilterOutlined';
import MailOutlined from '@ant-design/icons/MailOutlined';
import PhoneOutlined from '@ant-design/icons/PhoneOutlined';
import SafetyCertificateOutlined from '@ant-design/icons/SafetyCertificateOutlined';
import SaveOutlined from '@ant-design/icons/SaveOutlined';
import SettingOutlined from '@ant-design/icons/SettingOutlined';
import SwapOutlined from '@ant-design/icons/SwapOutlined';
import UndoOutlined from '@ant-design/icons/UndoOutlined';
import Alert from 'antd/es/alert';
import App from 'antd/es/app';
import Avatar from 'antd/es/avatar';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Empty from 'antd/es/empty';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Popconfirm from 'antd/es/popconfirm';
import Select from 'antd/es/select';
import Skeleton from 'antd/es/skeleton';
import Space from 'antd/es/space';
import Table from 'antd/es/table';
import Tag from 'antd/es/tag';
import Tooltip from 'antd/es/tooltip';
import Typography from 'antd/es/typography';
import { createCustomersApi, pendingCustomerCommands } from '../api/customersApi';
import { customerDate, customerFilterSnapshot, customerId, customerRows } from '../utils/customerData';
import CustomerProfileEditor from '../components/CustomerProfileEditor';
import CustomerProfileDrawer from '../components/CustomerProfileDrawer';
import CustomerBookSettings from '../components/CustomerBookSettings';
import CustomerDataTools from '../components/CustomerDataTools';
import CustomerDuplicates from '../components/CustomerDuplicates';
import '../styles/customers.css';

const { Text, Title, Paragraph } = Typography;
const statusLabel = { active: 'Đang hoạt động', archived: 'Đã lưu trữ', merged: 'Đã hợp nhất' };
const sections = {
    'customers-directory': { key: 'profiles', title: 'Danh bạ khách hàng', description: 'Kết nối hồ sơ, thấu hiểu khách hàng tại mọi điểm chạm.', icon: TeamOutlined },
    'customers-duplicates': { key: 'duplicates', title: 'Hồ sơ trùng', description: 'Rà soát thông tin và giữ danh bạ nhất quán, chính xác.', icon: CopyOutlined },
    'customers-data': { key: 'data', title: 'Nhập & xuất dữ liệu', description: 'Chuyển dữ liệu có kiểm soát, từ file nguồn đến danh bạ chung.', icon: SwapOutlined },
    'customers-settings': { key: 'settings', title: 'Thiết lập khách hàng', description: 'Thiết kế hồ sơ và quản lý cách ứng dụng sử dụng dữ liệu.', icon: SettingOutlined },
};

export default function CustomersManagerPage({ moduleMenu, callAdminApi, currentAdmin, currentPermissions = [] }) {
    const { message } = App.useApp();
    const website = currentAdmin?.current_website?.website_key ?? 'website-main';
    const identity = `${website}:admin-${currentAdmin?.id ?? 'unknown'}`;
    const [books, setBooks] = useState([]);
    const [booksLoading, setBooksLoading] = useState(true);
    const [bookId, setBookId] = useState(null);
    const [resource, setResource] = useState(null);
    const [fields, setFields] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [keyword, setKeyword] = useState('');
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState('active');
    const [type, setType] = useState('');
    const [tag, setTag] = useState('');
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(20);
    const [detailId, setDetailId] = useState(null);
    const [editing, setEditing] = useState(null);
    const [creatingBook, setCreatingBook] = useState(false);
    const [savingBook, setSavingBook] = useState(false);
    const [bookError, setBookError] = useState(null);
    const [pending, setPending] = useState([]);
    const [recovering, setRecovering] = useState(null);
    const [epoch, setEpoch] = useState(0);
    const [savedFilters, setSavedFilters] = useState([]);
    const [selectedFilter, setSelectedFilter] = useState(null);
    const [savingFilter, setSavingFilter] = useState(false);
    const [filterBusy, setFilterBusy] = useState(false);
    const [filterError, setFilterError] = useState(null);
    const [bookForm] = Form.useForm();
    const [filterForm] = Form.useForm();
    const generation = useRef(0);
    const booksGeneration = useRef(0);
    const selectedBook = useRef(bookId);
    selectedBook.current = bookId;
    const api = useMemo(() => createCustomersApi(callAdminApi, identity, bookId), [callAdminApi, identity, bookId]);
    const book = books.find((item) => customerId(item) === bookId);
    const can = (permission) => {
        if (Array.isArray(book?.permissions)) return book.permissions.includes(permission);
        return currentPermissions.includes(permission) || Boolean(currentAdmin?.is_system_owner) || currentAdmin?.id === 1;
    };
    const loadBooks = useCallback(async () => {
        const request = ++booksGeneration.current;
        setBooksLoading(true);
        try {
            const response = await api.read('books');
            if (request !== booksGeneration.current) return;
            const next = customerRows(response.data);
            setBooks(next);
            setBookId((current) => next.some((item) => customerId(item) === current) ? current : customerId(next[0]) || null);
        } finally { if (request === booksGeneration.current) setBooksLoading(false); }
    }, [api]);
    useEffect(() => {
        setBooks([]); setBooksLoading(true); setBookId(null); setResource(null); setDetailId(null); setEditing(null); setSelectedFilter(null); setSavedFilters([]); setSavingFilter(false);
        setPending(pendingCustomerCommands(identity));
    }, [identity]);
    useEffect(() => { loadBooks().catch((failure) => setError(failure.message)); return () => { booksGeneration.current += 1; }; }, [loadBooks]);
    useEffect(() => { const timer = window.setTimeout(() => { setQuery(keyword.trim()); setPage(1); }, 300); return () => window.clearTimeout(timer); }, [keyword]);
    useEffect(() => { setResource(null); setFields([]); setDetailId(null); setEditing(null); setPage(1); setSelectedFilter(null); setSavedFilters([]); setSavingFilter(false); }, [bookId]);
    useEffect(() => { setDetailId(null); setEditing(null); setSavingFilter(false); setCreatingBook(false); }, [moduleMenu?.key]);
    const loadSavedFilters = useCallback(async () => {
        if (!bookId) return;
        const response = await api.read(`books/${bookId}/saved-filters`);
        if (selectedBook.current === bookId) setSavedFilters(customerRows(response.data));
    }, [api, bookId]);
    useEffect(() => { loadSavedFilters().catch((failure) => { if (selectedBook.current === bookId) setError(failure.message); }); }, [loadSavedFilters]);
    const applySavedFilter = (value) => {
        setSelectedFilter(value ?? null);
        if (!value) return;
        const saved = savedFilters.find((item) => customerId(item) === value);
        if (!saved) return;
        const filters = customerFilterSnapshot(saved.filters ?? {});
        setKeyword(filters.q); setQuery(filters.q); setStatus(filters.status); setType(filters.type ?? ''); setTag(filters.tag ?? ''); setPerPage(filters.per_page); setPage(1);
    };
    const saveFilter = async (values) => {
        setFilterBusy(true); setFilterError(null);
        try {
            const result = await api.command(`books/${bookId}/saved-filters`, { name: values.name.trim(), filters: customerFilterSnapshot({ q: keyword, status, type, tag, per_page: perPage }) });
            await loadSavedFilters(); setSelectedFilter(customerId(result.data)); setSavingFilter(false); message.success('Đã lưu bộ lọc riêng của bạn.');
        } catch (failure) { setFilterError(failure.message); }
        finally { setFilterBusy(false); setPending(pendingCustomerCommands(identity)); }
    };
    const deleteFilter = async () => {
        if (!selectedFilter) return;
        setFilterBusy(true);
        try { await api.command(`books/${bookId}/saved-filters/${selectedFilter}`, {}, 'DELETE'); setSelectedFilter(null); await loadSavedFilters(); message.success('Đã xóa bộ lọc đã lưu.'); }
        catch (failure) { setError(failure.message); }
        finally { setFilterBusy(false); setPending(pendingCustomerCommands(identity)); }
    };
    const reload = useCallback(async () => {
        if (!bookId) return;
        const request = ++generation.current;
        setLoading(true); setError(null);
        try {
            const [profiles, definitions] = await Promise.allSettled([api.read(`books/${bookId}/profiles`, { q: query, status, type, tag, page, per_page: perPage }), api.read(`books/${bookId}/fields`)]);
            if (request !== generation.current) return;
            if (profiles.status === 'rejected') throw profiles.reason;
            setResource(profiles.value.data ?? {});
            if (definitions.status === 'fulfilled') setFields(customerRows(definitions.value.data));
        } catch (failure) { if (request === generation.current) setError(failure.message); }
        finally { if (request === generation.current) { setLoading(false); setPending(pendingCustomerCommands(identity)); } }
    }, [api, bookId, query, status, type, tag, page, perPage, identity]);
    useEffect(() => { reload(); return () => { generation.current += 1; }; }, [reload]);
    const changed = async () => { setPending(pendingCustomerCommands(identity)); await reload(); };
    const openEditor = async (profile = null) => {
        setError(null);
        try {
            const full = profile ? (await api.read(`books/${bookId}/profiles/${customerId(profile)}`)).data : null;
            if (selectedBook.current !== bookId) return;
            setDetailId(null); setEditing({ profile: full });
        } catch (failure) { setError(failure.message); }
    };
    const saveProfile = async (values, id) => {
        try {
            const result = await api.command(`books/${bookId}/profiles${id ? `/${id}` : ''}`, values, id ? 'PUT' : 'POST');
            message.success(id ? 'Đã cập nhật hồ sơ.' : 'Đã thêm khách hàng.'); await changed();
            if (result.data) setDetailId(customerId(result.data));
        } finally { setPending(pendingCustomerCommands(identity)); }
    };
    const archive = async (profile) => {
        try {
            await api.command(`books/${bookId}/profiles/${customerId(profile)}/archive`, { expected_version: profile.version, archived: profile.status !== 'archived' });
            message.success(profile.status === 'archived' ? 'Đã khôi phục khách hàng.' : 'Đã lưu trữ khách hàng.'); await changed();
        } catch (failure) { setError(failure.message); }
        finally { setPending(pendingCustomerCommands(identity)); }
    };
    const saveBook = async (values) => {
        setSavingBook(true); setBookError(null);
        try {
            const result = await api.command('books', values);
            setCreatingBook(false); await loadBooks();
            if (customerId(result.data)) setBookId(customerId(result.data));
            message.success('Đã tạo danh bạ khách hàng.');
        } catch (failure) { setBookError(failure.message); }
        finally { setSavingBook(false); setPending(pendingCustomerCommands(identity)); }
    };
    const recover = async (entry) => {
        setRecovering(entry.key);
        try {
            const response = await api.recover(entry);
            if (['committed', 'completed'].includes(response.data?.status)) {
                message.success('Thao tác đã được ghi nhận. Dữ liệu đã được tải lại.'); setEditing(null); setDetailId(null); setEpoch((current) => current + 1); await loadBooks(); await reload();
                if (entry.path.includes('/saved-filters')) { setSavingFilter(false); setSelectedFilter(null); await loadSavedFilters(); }
            } else message.info('Chưa xác định được kết quả. Giữ nguyên nội dung và gửi lại cùng thao tác để hệ thống kiểm tra.');
        } catch (failure) { setError(failure.message); }
        finally { setRecovering(null); setPending(pendingCustomerCommands(identity)); }
    };
    const profiles = customerRows(resource);
    const stats = resource?.stats ?? {};
    const section = sections[moduleMenu?.key] ?? sections['customers-directory'];
    const activeSection = section.key;
    const SectionIcon = section.icon;
    const sectionAllowed = activeSection === 'profiles' || (activeSection === 'duplicates' && can('customers.merge')) || (activeSection === 'data' && (can('customers.import') || can('customers.export'))) || (activeSection === 'settings' && can('customers.settings'));
    const activeFilterCount = [keyword.trim(), status !== 'active', type, tag.trim()].filter(Boolean).length;
    const resetFilters = () => { setKeyword(''); setQuery(''); setStatus('active'); setType(''); setTag(''); setPage(1); setSelectedFilter(null); };
    const startBook = () => { bookForm.resetFields(); setBookError(null); setCreatingBook(true); };
    const columns = [
        {
            title: 'Khách hàng', width: 250,
            render: (_, profile) => <div className="customers-name-cell">
                <Avatar shape={profile.type === 'company' ? 'square' : 'circle'} size={40}>{profile.name?.slice(0, 1)}</Avatar>
                <div><Button type="link" onClick={() => setDetailId(customerId(profile))}>{profile.name}</Button><div className="customers-muted customers-customer-code">{profile.code ?? customerId(profile).slice(-8)}</div></div>
            </div>,
        },
        {
            title: 'Thông tin liên hệ', width: 240,
            render: (_, profile) => <div className="customers-contact-cell">
                <span><PhoneOutlined /><span>{profile.phone ?? profile.phone_masked ?? 'Chưa có điện thoại'}</span></span>
                <span className="customers-muted"><MailOutlined /><span>{profile.email ?? 'Chưa có email'}</span></span>
            </div>,
        },
        { title: 'Loại hồ sơ', dataIndex: 'type', width: 135, responsive: ['lg'], render: (value) => <span className="customers-type-label">{value === 'company' ? 'Doanh nghiệp' : 'Cá nhân'}</span> },
        { title: 'Nhãn', width: 150, responsive: ['xl'], render: (_, profile) => customerRows(profile.tags).length ? customerRows(profile.tags).map((item) => <Tag className="customers-label-tag" key={typeof item === 'string' ? item : item.name}>{typeof item === 'string' ? item : item.name ?? item.label}</Tag>) : <span className="customers-muted">—</span> },
        { title: 'Trạng thái', dataIndex: 'status', width: 155, render: (value) => <span className={`customers-status customers-status--${value}`}><i aria-hidden="true" />{statusLabel[value] ?? value}</span> },
        { title: 'Cập nhật', dataIndex: 'updated_at', render: (value) => <span className="customers-updated-at">{customerDate(value)}</span>, width: 160, responsive: ['xl'] },
        {
            title: 'Thao tác', width: 110, fixed: 'right', align: 'right',
            render: (_, profile) => <Space size={4} className="customers-row-actions">
                {can('customers.update') && profile.status !== 'merged' && <Tooltip title="Chỉnh sửa hồ sơ"><Button className="customers-icon-action" aria-label={`Chỉnh sửa ${profile.name}`} icon={<EditOutlined />} onClick={() => openEditor(profile)} /></Tooltip>}
                {can('customers.archive') && profile.status !== 'merged' && <Popconfirm title={profile.status === 'archived' ? 'Khôi phục hồ sơ này?' : 'Lưu trữ hồ sơ này?'} description="Lịch sử và liên kết giao dịch vẫn được giữ." onConfirm={() => archive(profile)} okText="Xác nhận" cancelText="Hủy"><Tooltip title={profile.status === 'archived' ? 'Khôi phục hồ sơ' : 'Lưu trữ hồ sơ'}><Button className="customers-icon-action" aria-label={`${profile.status === 'archived' ? 'Khôi phục' : 'Lưu trữ'} ${profile.name}`} icon={profile.status === 'archived' ? <UndoOutlined /> : <ArchiveOutlined />} /></Tooltip></Popconfirm>}
            </Space>,
        },
    ];
    return <div className="customers-workspace">
        <header className="customers-header">
            <div className="customers-heading">
                <div className="customers-app-icon"><SectionIcon /></div>
                <div><span className="customers-eyebrow">AIO CUSTOMERS</span><Title level={2}>{section.title}</Title><p className="customers-header-description">{section.description}</p></div>
            </div>
            <div className="customers-header-actions">
                <label className="customers-book-switcher"><span>Danh bạ làm việc</span><Select aria-label="Chọn danh bạ làm việc" value={bookId ?? undefined} loading={booksLoading} placeholder="Chọn danh bạ" options={books.map((item) => ({ value: customerId(item), label: item.name }))} onChange={setBookId} /></label>
                <div className="customers-header-buttons"><Tooltip title="Làm mới danh bạ"><Button aria-label="Làm mới danh bạ" icon={<ReloadOutlined />} loading={loading || booksLoading} onClick={() => bookId ? reload() : loadBooks().catch((failure) => setError(failure.message))}>Làm mới</Button></Tooltip>{can('customers.settings') && <Tooltip title="Tạo danh bạ"><Button aria-label="Tạo danh bạ" icon={<PlusOutlined />} onClick={startBook}>Tạo danh bạ</Button></Tooltip>}</div>
            </div>
        </header>
        {pending.length > 0 && <Alert type="warning" showIcon className="customers-page-alert" message={`${pending.length} thao tác cần kiểm tra kết quả`} description={<Space direction="vertical">{pending.map((entry) => <Space wrap key={entry.key}><Text>{entry.path} · {customerDate(entry.created_at)}</Text><Button size="small" loading={recovering === entry.key} onClick={() => recover(entry)}>Kiểm tra kết quả</Button></Space>)}</Space>} />}
        {error && <Alert type="error" showIcon className="customers-page-alert" message={error} action={<Button size="small" onClick={reload}>Thử lại</Button>} />}
        {booksLoading && !book ? <Card className="customers-loading-card"><Skeleton active paragraph={{ rows: 4 }} /></Card> : !book ? <Card className="customers-empty-book"><TeamOutlined /><Title level={3}>{books.length ? 'Chọn danh bạ để bắt đầu' : 'Một nơi lưu giữ quan hệ với khách hàng'}</Title><Paragraph type="secondary">Tạo danh bạ rồi kết nối các ứng dụng và chi nhánh cần sử dụng chung hồ sơ.</Paragraph>{can('customers.settings') && <Button type="primary" icon={<PlusOutlined />} onClick={startBook}>Tạo danh bạ đầu tiên</Button>}</Card> : <>
            <div className="customers-book-context"><span className="customers-context-book"><BookOutlined /><strong>{book.name}</strong>{book.code && <span className="customers-book-code">{book.code}</span>}</span><span className="customers-context-policy"><SafetyCertificateOutlined /> Chia sẻ theo quyền truy cập</span></div>
            {activeSection === 'profiles' && <div className="customers-metrics" aria-label="Thống kê danh bạ">
                <div className="customers-metric"><span className="customers-metric-icon"><TeamOutlined /></span><div><span className="customers-metric-label">Hồ sơ phù hợp</span><strong>{resource?.total ?? '—'}</strong><span className="customers-metric-note">Theo bộ lọc hiện tại</span></div></div>
                <div className="customers-metric"><span className="customers-metric-icon customers-metric-icon--active"><CheckCircleOutlined /></span><div><span className="customers-metric-label">Đang hoạt động</span><strong>{resource ? stats.active ?? 0 : '—'}</strong><span className="customers-metric-note">Trong danh bạ đang chọn</span></div></div>
                <div className="customers-metric"><span className="customers-metric-icon customers-metric-icon--archived"><ArchiveOutlined /></span><div><span className="customers-metric-label">Đã lưu trữ</span><strong>{resource ? stats.archived ?? 0 : '—'}</strong><span className="customers-metric-note">Giữ lại lịch sử và liên kết</span></div></div>
            </div>}
            {!sectionAllowed ? <Alert type="warning" showIcon message="Bạn chưa được cấp quyền sử dụng chức năng này trong danh bạ hiện tại." description="Chọn danh bạ khác hoặc liên hệ người quản lý để được cấp quyền phù hợp." /> : activeSection === 'profiles' ? <Card className="customers-directory-card">
                <div className="customers-section-heading"><div><Title level={4}>Hồ sơ khách hàng</Title><p>Tìm kiếm, cập nhật và quản lý thông tin khách hàng tại một nơi.</p></div>{can('customers.create') && <Button type="primary" icon={<PlusOutlined />} onClick={() => openEditor()}>Thêm khách hàng</Button>}</div>
                <div className="customers-filter-panel">
                    <Input.Search value={keyword} onChange={(event) => { setKeyword(event.target.value); setSelectedFilter(null); }} maxLength={128} allowClear placeholder="Tìm theo tên, mã khách hàng, điện thoại hoặc email…" aria-label="Tìm khách hàng" className="customers-directory-search" />
                    <div className="customers-filter-grid">
                        <label><span>Trạng thái</span><Select aria-label="Lọc theo trạng thái" value={status} onChange={(value) => { setStatus(value); setPage(1); setSelectedFilter(null); }} options={[{ value: 'active', label: 'Đang hoạt động' }, { value: 'archived', label: 'Đã lưu trữ' }, { value: 'merged', label: 'Đã hợp nhất' }, { value: 'all', label: 'Mọi trạng thái' }]} /></label>
                        <label><span>Loại hồ sơ</span><Select aria-label="Lọc theo loại hồ sơ" value={type} onChange={(value) => { setType(value); setPage(1); setSelectedFilter(null); }} options={[{ value: '', label: 'Mọi loại hồ sơ' }, { value: 'person', label: 'Cá nhân' }, { value: 'company', label: 'Doanh nghiệp' }]} /></label>
                        <label><span>Nhãn khách hàng</span><Input aria-label="Lọc theo nhãn khách hàng" value={tag} placeholder="Nhập nhãn cần tìm" allowClear onChange={(event) => { setTag(event.target.value); setPage(1); setSelectedFilter(null); }} /></label>
                        <label><span>Bộ lọc của tôi</span><Select aria-label="Bộ lọc đã lưu của tôi" allowClear value={selectedFilter ?? undefined} placeholder="Chọn bộ lọc đã lưu" options={savedFilters.map((item) => ({ value: customerId(item), label: item.name }))} onChange={applySavedFilter} /></label>
                    </div>
                    <div className="customers-filter-footer"><span className="customers-filter-note"><FilterOutlined />{activeFilterCount ? `${activeFilterCount} điều kiện lọc tùy chỉnh` : 'Đang xem các hồ sơ hoạt động'}{activeFilterCount > 0 && <Button type="link" size="small" onClick={resetFilters}>Đặt lại</Button>}</span><Space wrap size={8}><Button icon={<SaveOutlined />} size="small" onClick={() => { filterForm.resetFields(); setFilterError(null); setSavingFilter(true); }}>Lưu bộ lọc</Button>{selectedFilter && <Popconfirm title="Xóa bộ lọc đã lưu này?" description="Danh sách khách hàng không bị thay đổi." onConfirm={deleteFilter} okText="Xóa bộ lọc" cancelText="Hủy"><Button danger size="small" loading={filterBusy}>Xóa bộ lọc</Button></Popconfirm>}<Text type="secondary" className="customers-private-filter-note">Chỉ hiển thị với bạn</Text></Space></div>
                </div>
                <div className="customers-results-heading"><span>Kết quả danh bạ <span className="customers-result-count">{resource?.total ?? '—'}</span></span><span className="customers-muted">Chọn tên khách hàng để xem hồ sơ</span></div>
                <Table className="customers-directory-table" rowKey={customerId} dataSource={profiles} columns={columns} loading={loading} scroll={{ x: 760 }} pagination={{ current: page, pageSize: perPage, total: resource?.total ?? 0, showSizeChanger: true, pageSizeOptions: [10, 20, 50, 100], showTotal: (total) => `${total} hồ sơ`, onChange: (next, size) => { setPage(size === perPage ? next : 1); setPerPage(size); if (size !== perPage) setSelectedFilter(null); } }} locale={{ emptyText: <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description={<span className="customers-empty-description"><strong>{query || type || tag ? 'Không có hồ sơ phù hợp' : 'Chưa có hồ sơ trong phạm vi này'}</strong><span>Thử thay đổi điều kiện lọc hoặc thêm khách hàng mới.</span></span>} /> }} />
            </Card>
                : activeSection === 'duplicates' ? <CustomerDuplicates key={`${bookId}:${epoch}`} api={api} bookId={bookId} canMerge={can('customers.merge')} onChanged={changed} onView={setDetailId} />
                    : activeSection === 'data' ? <CustomerDataTools key={`${bookId}:${epoch}`} api={api} bookId={bookId} can={can} onChanged={changed} />
                        : activeSection === 'settings' ? <CustomerBookSettings key={`${bookId}:${epoch}`} api={api} book={book} onChanged={changed} /> : null}
            <footer className="customers-workspace-note"><SafetyCertificateOutlined /><span>Hồ sơ dùng chung theo quyền được cấp. Thông tin giao dịch được lưu tại ứng dụng nguồn.</span></footer>
        </>}
        <CustomerProfileDrawer key={bookId} profileId={detailId} bookId={bookId} api={api} fields={fields} can={can} nativePermissions={currentPermissions} organizationOptions={currentAdmin?.organization_options ?? []} onClose={() => setDetailId(null)} onEdit={openEditor} onChanged={changed} />
        <CustomerProfileEditor key={`editor:${bookId}`} open={Boolean(editing)} profile={editing?.profile} fields={fields} sensitiveAllowed={can('customers.sensitive')} onClose={() => setEditing(null)} onSave={saveProfile} />
        <Modal className="customers-dialog" open={savingFilter} title="Lưu bộ lọc riêng" onCancel={() => !filterBusy && setSavingFilter(false)} onOk={() => filterForm.submit()} confirmLoading={filterBusy} okText="Lưu bộ lọc" cancelText="Hủy" maskClosable={false}>{filterError && <Alert type="error" showIcon message={filterError} style={{ marginBottom: 16 }} />}<Form form={filterForm} layout="vertical" disabled={filterBusy} onFinish={saveFilter}><Form.Item name="name" label="Tên bộ lọc" rules={[{ required: true, whitespace: true, message: 'Nhập tên bộ lọc.' }]}><Input maxLength={100} placeholder="Ví dụ: Doanh nghiệp đang hoạt động" /></Form.Item><Paragraph type="secondary">Lưu từ khóa, trạng thái, loại hồ sơ, nhãn và số dòng mỗi trang. Bộ lọc được lưu riêng trong danh bạ hiện tại.</Paragraph></Form></Modal>
        <Modal className="customers-dialog" open={creatingBook} title="Tạo danh bạ khách hàng" onCancel={() => !savingBook && setCreatingBook(false)} onOk={() => bookForm.submit()} confirmLoading={savingBook} okText="Tạo danh bạ" cancelText="Hủy" maskClosable={false}>{bookError && <Alert type="error" showIcon message={bookError} style={{ marginBottom: 16 }} />}<Form form={bookForm} layout="vertical" onFinish={saveBook} disabled={savingBook}><Form.Item name="name" label="Tên danh bạ" rules={[{ required: true, whitespace: true }]}><Input placeholder="Ví dụ: Khách hàng AIO Mart" maxLength={150} /></Form.Item><Form.Item name="code" label="Mã danh bạ" rules={[{ required: true }, { pattern: /^[A-Za-z0-9_-]+$/, message: 'Dùng chữ, số, dấu gạch ngang hoặc gạch dưới.' }]}><Input placeholder="AIO-MART" maxLength={60} /></Form.Item><Paragraph type="secondary">Danh bạ thuộc website đang chọn. Bạn có thể cấp quyền và kết nối ứng dụng sau khi tạo.</Paragraph></Form></Modal>
    </div>;
}
