import Button from 'antd/es/button';
import DatePicker from 'antd/es/date-picker';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Space from 'antd/es/space';
import dayjs from 'dayjs';

const { TextArea } = Input;

export default function ProjectReportDrawer({ open, onClose, onSubmit, report, canSubmit }) {
    const [form] = Form.useForm();

    const initialValues = report ? {
        ...report,
        report_date: report.report_date ? dayjs(report.report_date) : null,
    } : {
        report_date: dayjs(),
    };

    return (
        <Drawer
            title={report ? 'Cập nhật báo cáo' : 'Thêm báo cáo'}
            open={open}
            onClose={onClose}
            width={640}
            destroyOnClose
            extra={
                <Space>
                    <Button onClick={onClose}>Hủy</Button>
                    <Button type="primary" onClick={() => form.submit()} disabled={!canSubmit}>
                        {report ? 'Lưu báo cáo' : 'Tạo báo cáo'}
                    </Button>
                </Space>
            }
        >
            <Form
                layout="vertical"
                form={form}
                initialValues={initialValues}
                key={report?.id ?? 'new-report'}
                onFinish={(values) => onSubmit({
                    ...values,
                    report_date: values.report_date?.format?.('YYYY-MM-DD') ?? null,
                })}
            >
                <Form.Item name="title" label="Tiêu đề báo cáo" rules={[{ required: true, message: 'Vui lòng nhập tiêu đề báo cáo.' }]}>
                    <Input placeholder="Ví dụ: Báo cáo tiến độ tuần 18" />
                </Form.Item>

                <Form.Item name="report_date" label="Ngày báo cáo" rules={[{ required: true, message: 'Vui lòng chọn ngày báo cáo.' }]}>
                    <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" />
                </Form.Item>

                <Form.Item name="summary" label="Tóm tắt">
                    <TextArea rows={3} placeholder="Tóm tắt nhanh nội dung chính" />
                </Form.Item>

                <Form.Item name="content" label="Nội dung chi tiết">
                    <TextArea rows={10} placeholder="Nội dung báo cáo chi tiết" />
                </Form.Item>
            </Form>
        </Drawer>
    );
}
