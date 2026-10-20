import DeleteOutlined from '@ant-design/icons/DeleteOutlined';
import PlusOutlined from '@ant-design/icons/PlusOutlined';
import StarFilled from '@ant-design/icons/StarFilled';
import UploadOutlined from '@ant-design/icons/UploadOutlined';
import App from 'antd/es/app';
import Button from 'antd/es/button';
import Image from 'antd/es/image';
import { useRef, useState } from 'react';
import { createIdempotencyKey } from '../api/fnbApi';
import { useFnbWorkspace } from '../state/FnbWorkspaceContext';

export default function FnbItemImages({ value, onChange, disabled, onUploadingChange }) {
    const { api } = useFnbWorkspace();
    const { message } = App.useApp();
    const input = useRef(null);
    const [uploading, setUploading] = useState(false);
    const urls = value?.urls ?? [];
    const cover = urls.includes(value?.cover) ? value.cover : urls[0];

    const upload = async (event) => {
        const files = Array.from(event.target.files ?? []);
        event.target.value = '';
        if (!files.length) return;
        if (urls.length + files.length > 12) { message.warning('Mỗi món có tối đa 12 ảnh.'); return; }
        if (files.some((file) => !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024)) {
            message.warning('Chọn ảnh JPG, PNG hoặc WebP, tối đa 5 MB mỗi ảnh.'); return;
        }
        setUploading(true);
        onUploadingChange?.(true);
        const uploaded = [];
        try {
            for (const file of files) {
                const body = new FormData();
                body.append('file', file);
                const result = await api.command('menu/images', body, { idempotencyKey: createIdempotencyKey('menu-image') });
                if (!result.data?.url) throw new Error('Máy chủ chưa trả lại đường dẫn ảnh.');
                uploaded.push(result.data.url);
            }
            message.success(`Đã tải ${uploaded.length} ảnh.`);
        } catch (exception) {
            message.error(exception.message || 'Không thể tải ảnh món.');
        } finally {
            // Keep successful files visible even if a later upload fails.
            if (uploaded.length) {
                const next = [...new Set([...urls, ...uploaded])];
                onChange?.({ urls: next, cover: cover ?? next[0] });
            }
            setUploading(false);
            onUploadingChange?.(false);
        }
    };

    return <div className="fnb-item-images">
        <input ref={input} type="file" accept="image/jpeg,image/png,image/webp" multiple hidden onChange={upload} aria-label="Chọn ảnh món để tải lên" />
        <div className="fnb-item-images-toolbar"><Button icon={<UploadOutlined />} loading={uploading} disabled={disabled || urls.length >= 12} onClick={() => input.current?.click()}>Tải ảnh lên</Button><span>{urls.length}/12 ảnh · JPG, PNG, WebP · tối đa 5 MB/ảnh</span></div>
        {urls.length ? <Image.PreviewGroup><div className="fnb-item-images-grid">{urls.map((url, index) => <div key={url} className={`fnb-item-image-card ${cover === url ? 'is-cover' : ''}`}>
            <div className="fnb-item-image-preview"><Image src={url} alt={`Ảnh món ${index + 1}`} preview={{ mask: 'Xem ảnh' }} />{cover === url ? <span className="fnb-item-image-cover"><StarFilled /> Đại diện</span> : null}</div>
            <div className="fnb-item-image-actions"><Button size="small" type={cover === url ? 'text' : 'link'} disabled={disabled || uploading || cover === url} onClick={() => onChange?.({ urls, cover: url })}>{cover === url ? 'Ảnh đại diện' : 'Đặt đại diện'}</Button><Button size="small" type="text" danger icon={<DeleteOutlined />} aria-label={`Bỏ ảnh món ${index + 1}`} disabled={disabled || uploading} onClick={() => {
                const next = urls.filter((item) => item !== url);
                onChange?.({ urls: next, cover: cover === url ? next[0] : cover });
            }} /></div>
        </div>)}</div></Image.PreviewGroup> : <button type="button" className="fnb-item-images-empty" disabled={disabled || uploading} onClick={() => input.current?.click()}><PlusOutlined /><strong>Thêm hình ảnh cho món</strong><span>Có thể chọn nhiều ảnh. Ảnh đầu tiên tự làm ảnh đại diện.</span></button>}
        {urls.length ? <div className="fnb-item-images-hint">Bấm vào ảnh để xem lớn. Chọn “Đặt đại diện” cho ảnh hiển thị trên thực đơn.</div> : null}
    </div>;
}
