import { useState } from 'react';
import Input from 'antd/es/input';
import Modal from 'antd/es/modal';
import Empty from 'antd/es/empty';
import SwapOutlined from '@ant-design/icons/es/icons/SwapOutlined';
import CheckOutlined from '@ant-design/icons/es/icons/CheckOutlined';
import { recordId } from '../utils/pharmacyData';

export default function PharmacyContextSwitcher({ pharmacies, currentId, disabled, open, onOpen, onClose, onChoose }) {
    const [search, setSearch] = useState('');
    const current = pharmacies.find((item) => recordId(item) === currentId);
    const normalize = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLocaleLowerCase('vi');
    const visible = pharmacies.filter((item) => normalize(item.name ?? '').includes(normalize(search.trim())));
    return <>
        <div className="ph-context">
            <span className="ph-context-label">Nhà thuốc</span>
            <button type="button" className="ph-context-trigger" aria-label="Đổi nhà thuốc đang làm việc" aria-haspopup="dialog" aria-expanded={open} disabled={disabled || pharmacies.length === 0} onClick={onOpen}>
                <span>{current?.name ?? 'Chọn nhà thuốc'}</span><SwapOutlined />
            </button>
        </div>
        <Modal open={open} title="Đổi nhà thuốc" centered width={480} wrapClassName="ph-context-modal" onCancel={onClose} footer={null} afterClose={() => setSearch('')}>
            <p className="ph-context-intro">Chọn nhà thuốc để tiếp tục làm việc.</p>
            <Input.Search aria-label="Tìm nhà thuốc" placeholder="Tìm theo tên nhà thuốc" value={search} onChange={(event) => setSearch(event.target.value)} allowClear />
            <div className="ph-context-options" aria-label="Danh sách nhà thuốc">
                {visible.map((item) => {
                    const id = recordId(item);
                    const selected = id === currentId;
                    return <button key={id} type="button" className={`ph-context-option${selected ? ' is-current' : ''}`} aria-label={selected ? `${item.name} — Đang làm việc` : `Chuyển sang ${item.name}`} aria-current={selected ? 'true' : undefined} disabled={disabled} onClick={() => selected ? onClose() : onChoose(id)}>
                        <strong>{item.name ?? 'Nhà thuốc'}</strong>
                        {selected ? <span className="ph-context-current"><CheckOutlined /> Đang làm việc</span> : <SwapOutlined />}
                    </button>;
                })}
                {visible.length === 0 && <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description="Không tìm thấy nhà thuốc phù hợp" />}
            </div>
        </Modal>
    </>;
}
