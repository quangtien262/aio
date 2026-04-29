import Button from 'antd/es/button';
import DatePicker from 'antd/es/date-picker';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import dayjs from 'dayjs';

const { TextArea } = Input;

export default function ProjectTaskDrawer({ open, onClose, onSubmit, task, references, canSubmit }) {
    const [form] = Form.useForm();
    const taskStatuses = references?.task_statuses ?? [];
    const priorities = references?.priorities ?? [];
    const admins = references?.admins ?? [];

    const initialValues = task ? {
        ...task,
        start_date: task.start_date ? dayjs(task.start_date) : null,
        due_date: task.due_date ? dayjs(task.due_date) : null,
    } : {
        task_status_id: taskStatuses[0]?.id,
        priority_id: priorities[1]?.id ?? priorities[0]?.id,
        progress: 0,
    };

    return (
        <Drawer
            title={task ? 'Cập nhật công việc' : 'Thêm công việc'}
            open={open}
            onClose={onClose}
            width={560}
            destroyOnClose
            extra={
                <Space>
                    <Button onClick={onClose}>Hủy</Button>
                    <Button type="primary" onClick={() => form.submit()} disabled={!canSubmit}>
                        {task ? 'Lưu công việc' : 'Tạo công việc'}
                    </Button>
                </Space>
            }
        >
            <Form
                layout="vertical"
                form={form}
                initialValues={initialValues}
                key={task?.id ?? 'new-task'}
                onFinish={(values) => onSubmit({
                    ...values,
                    start_date: values.start_date?.format?.('YYYY-MM-DD') ?? null,
                    due_date: values.due_date?.format?.('YYYY-MM-DD') ?? null,
                })}
            >
                <Form.Item name="title" label="Tên công việc" rules={[{ required: true, message: 'Vui lòng nhập tên công việc.' }]}>
                    <Input placeholder="Nhập tên công việc" />
                </Form.Item>

                <Form.Item name="description" label="Mô tả">
                    <TextArea rows={4} placeholder="Mô tả ngắn cho công việc" />
                </Form.Item>

                <div className="detail-grid detail-grid-2">
                    <Form.Item name="task_status_id" label="Trạng thái" rules={[{ required: true, message: 'Vui lòng chọn trạng thái.' }]}>
                        <Select options={taskStatuses.map((item) => ({ value: item.id, label: item.name }))} />
                    </Form.Item>
                    <Form.Item name="priority_id" label="Ưu tiên" rules={[{ required: true, message: 'Vui lòng chọn ưu tiên.' }]}>
                        <Select options={priorities.map((item) => ({ value: item.id, label: item.name }))} />
                    </Form.Item>
                    <Form.Item name="assignee_admin_id" label="Người thực hiện">
                        <Select allowClear options={admins.map((item) => ({ value: item.id, label: `${item.name} (${item.email})` }))} />
                    </Form.Item>
                    <Form.Item name="progress" label="Tiến độ (%)">
                        <Input type="number" min={0} max={100} />
                    </Form.Item>
                    <Form.Item name="start_date" label="Ngày bắt đầu">
                        <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" />
                    </Form.Item>
                    <Form.Item name="due_date" label="Hạn hoàn thành">
                        <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" />
                    </Form.Item>
                </div>
            </Form>
        </Drawer>
    );
}
