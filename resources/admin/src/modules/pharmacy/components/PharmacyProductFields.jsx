import Button from 'antd/es/button';
import Collapse from 'antd/es/collapse';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import InputNumber from 'antd/es/input-number';
import DeleteOutlined from '@ant-design/icons/es/icons/DeleteOutlined';
import PlusOutlined from '@ant-design/icons/es/icons/PlusOutlined';
import { pharmacyFlag, quantityFormatter } from '../utils/pharmacyData';

export default function PharmacyProductFields({ fields = [], renderField, initial = {} }) {
    const field = (name) => {
        const definition = fields.find((item) => item.name === name);
        return definition ? renderField(definition) : null;
    };
    return <div className="ph-product-form-sections">
        <section className="ph-product-form-section" aria-labelledby="ph-product-general">
            <h3 id="ph-product-general">Thông tin hàng hóa</h3>
            <div className="ph-product-form-grid">
                <div className="ph-product-col-8">{field('name')}</div><div className="ph-product-col-4">{field('code')}</div>
                <div className="ph-product-col-4">{field('registration_number')}</div><div className="ph-product-col-4">{field('dosage_form')}</div><div className="ph-product-col-4">{field('strength')}</div>
            </div>
        </section>
        <section className="ph-product-form-section" aria-labelledby="ph-product-selling">
            <h3 id="ph-product-selling">Đơn vị & giá bán</h3>
            <div className="ph-product-form-grid"><div className="ph-product-col-4">{field('base_unit')}</div><div className="ph-product-col-4">{field('price_minor')}</div><div className="ph-product-col-4">{field('barcode')}</div></div>
            <div className="ph-product-prescription">{field('requires_prescription')}<span>Cần đối chiếu đơn thuốc khi cấp.</span></div>
        </section>
        <section className="ph-product-form-section" aria-labelledby="ph-product-ingredients">
            <Form.List name="ingredients">{(rows, { add, remove }) => <>
                <div className="ph-product-section-heading"><h3 id="ph-product-ingredients">Hoạt chất theo hồ sơ</h3><Button size="small" type="text" icon={<PlusOutlined />} disabled={rows.length >= 30} onClick={() => add({})}>Thêm hoạt chất</Button></div>
                {!rows.length && <p className="ph-product-empty-ingredients">Chưa có hoạt chất. Thêm thông tin theo hồ sơ hàng hóa khi cần.</p>}
                {rows.map((row) => <div className="ph-product-ingredient" key={row.key}>
                    <Form.Item name={[row.name, 'name']} label="Tên hoạt chất" rules={[{ required: true, message: 'Nhập tên hoạt chất theo hồ sơ.' }]}><Input maxLength={255} placeholder="Tên theo hồ sơ" /></Form.Item>
                    <Form.Item name={[row.name, 'strength']} label="Hàm lượng"><Input maxLength={100} placeholder="VD: 500" /></Form.Item>
                    <Form.Item name={[row.name, 'unit']} label="Đơn vị hàm lượng"><Input maxLength={50} placeholder="VD: mg" /></Form.Item>
                    <Button type="text" danger icon={<DeleteOutlined />} aria-label={`Xóa hoạt chất ${row.name + 1}`} onClick={() => remove(row.name)} />
                </div>)}
            </>}</Form.List>
        </section>
        <Collapse className="ph-product-extra" defaultActiveKey={pharmacyFlag(initial.controlled) || pharmacyFlag(initial.cold_chain) ? ['details'] : []} items={[{
            key: 'details', label: 'Thông tin bổ sung & phân loại', forceRender: true,
            children: <div className="ph-product-extra-content">
                <div className="ph-product-form-grid"><Form.Item className="ph-product-col-6" name="route" label="Đường dùng theo hồ sơ hàng hóa"><Input maxLength={255} placeholder="Nhập theo hồ sơ" /></Form.Item><Form.Item className="ph-product-col-6" name="minimum_increment" label="Bước số lượng nhỏ nhất"><InputNumber stringMode min="0.000001" precision={6} formatter={quantityFormatter} controls={false} style={{ width: '100%' }} /></Form.Item></div>
                <div className="ph-product-classifications">{field('controlled')}{field('cold_chain')}</div>
                <p className="ph-product-classification-note">Thuốc kiểm soát đặc biệt và hàng yêu cầu chuỗi lạnh chưa được mở nghiệp vụ bán, cấp trong phiên bản này.</p>
            </div>,
        }]} />
    </div>;
}
