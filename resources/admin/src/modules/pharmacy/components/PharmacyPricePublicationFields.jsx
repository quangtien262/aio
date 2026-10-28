import { useEffect, useState } from 'react';
import Alert from 'antd/es/alert';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Select from 'antd/es/select';
import { PharmacyError } from './PharmacyCommon';
import { money, recordId, recordLabel, resourceRows } from '../utils/pharmacyData';

export default function PharmacyPricePublicationFields({ form, api, pharmacies, product }) {
    const selected = Form.useWatch('target_ids', form) ?? [];
    const [error, setError] = useState(null);
    const [busy, setBusy] = useState(false);
    const [prices, setPrices] = useState([]);
    const key = JSON.stringify(selected);
    useEffect(() => {
        let current = true; const ids = JSON.parse(key);
        setError(null); setPrices([]); form.setFieldValue('targets', []);
        if (!ids.length) { setBusy(false); return; }
        setBusy(true);
        Promise.all(ids.map(async (id) => {
            const response = await api.read('prices', { pharmacy_id: id, q: product.code, per_page: 100 });
            const row = resourceRows(response.data).find((item) => recordId(item) === recordId(product));
            if (!row) throw new Error('Không đối chiếu được hàng hóa tại một nhà thuốc. Thử tải lại giá hiện tại.');
            return { pharmacy_id: id, expected_version: row.price_version ?? null, current_minor: row.price_minor };
        })).then((rows) => { if (current) { setPrices(rows); form.setFieldValue('targets', rows.map(({ pharmacy_id, expected_version }) => ({ pharmacy_id, expected_version }))); } }).catch((failure) => { if (current) setError(failure); }).finally(() => { if (current) setBusy(false); });
        return () => { current = false; };
    }, [api, key, product.code, product.id, product.public_id, form]);
    return <><Form.Item name="target_ids" label="Nhà thuốc nhận giá" rules={[{ required: true }]}><Select mode="multiple" optionFilterProp="label" options={pharmacies.map((row) => ({ value: recordId(row), label: recordLabel(row) }))} /></Form.Item><PharmacyError error={error} /><Form.List name="targets" rules={[{ validator: (_, rows) => selected.length && rows?.length === selected.length && !busy && !error ? Promise.resolve() : Promise.reject(new Error('Chọn điểm nhận và đợi đối chiếu đủ giá hiện tại.')) }]}>{(fields, _, { errors }) => <>{fields.map((field) => <div key={field.key}><Form.Item hidden name={[field.name, 'pharmacy_id']}><Input /></Form.Item><Form.Item hidden name={[field.name, 'expected_version']}><Input /></Form.Item></div>)}<Form.ErrorList errors={errors} /></>}</Form.List><Alert type="info" showIcon message={busy ? 'Đang đối chiếu giá và phiên bản ở từng điểm…' : 'Áp dụng đồng thời ở các nhà thuốc đã chọn'} description={prices.map((row) => <div key={row.pharmacy_id}>{pharmacies.find((site) => recordId(site) === row.pharmacy_id)?.name ?? row.pharmacy_id}: {row.expected_version ? `${money(row.current_minor)} · phiên bản ${row.expected_version}` : 'Chưa có giá cơ sở'}</div>)} /></>;
}
