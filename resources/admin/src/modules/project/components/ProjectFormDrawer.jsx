import Button from 'antd/es/button';
import ColorPicker from 'antd/es/color-picker';
import DatePicker from 'antd/es/date-picker';
import Drawer from 'antd/es/drawer';
import Form from 'antd/es/form';
import Input from 'antd/es/input';
import Select from 'antd/es/select';
import Space from 'antd/es/space';
import dayjs from 'dayjs';

const { TextArea } = Input;

export default function ProjectFormDrawer({ open, onClose, onSubmit, project, references, canSubmit }) {
    const [form] = Form.useForm();

    const projectStatuses = references?.project_statuses ?? [];
    const projectTypes = references?.project_types ?? [];
    const priorities = references?.priorities ?? [];
    const admins = references?.admins ?? [];

    const initialValues = project ? {
        ...project,
        start_date: project.start_date ? dayjs(project.start_date) : null,
        due_date: project.due_date ? dayjs(project.due_date) : null,
        completed_at: project.completed_at ? dayjs(project.completed_at) : null,
        member_admin_ids: (project.members ?? []).map((member) => member.admin?.id).filter(Boolean),
    } : {
        project_status_id: projectStatuses[0]?.id,
        priority_id: priorities[1]?.id ?? priorities[0]?.id,
        color: '#1677ff',
        progress: 0,
        member_admin_ids: [],
    };

    return (
        <Drawer
            title={project ? 'Cập nhật dự án' : 'Tạo dự án mới'}
            open={open}
            onClose={onClose}
            width={720}
            destroyOnClose
            extra={
                <Space>
                    <Button onClick={onClose}>Hủy</Button>
                    <Button type="primary" onClick={() => form.submit()} disabled={!canSubmit}>
                        {project ? 'Lưu thay đổi' : 'Tạo dự án'}
                    </Button>
                </Space>
            }
        >
            <Form
                layout="vertical"
                form={form}
                initialValues={initialValues}
                key={project?.id ?? 'new-project'}
                onFinish={(values) => onSubmit({
                    ...values,
                    color: values.color?.toHexString?.() ?? values.color ?? '#1677ff',
                    start_date: values.start_date?.format?.('YYYY-MM-DD') ?? null,
                    due_date: values.due_date?.format?.('YYYY-MM-DD') ?? null,
                    completed_at: values.completed_at?.format?.('YYYY-MM-DD') ?? null,
                })}
            >
                <Form.Item name="name" label="Tên dự án" rules={[{ required: true, message: 'Vui lòng nhập tên dự án.' }]}>
                    <Input placeholder="Nhập tên dự án" />
                </Form.Item>

                <Form.Item name="code" label="Mã dự án">
                    <Input placeholder="Để trống để hệ thống tự sinh mã" />
                </Form.Item>

                <Form.Item name="description" label="Mô tả">
                    <TextArea rows={4} placeholder="Mô tả ngắn về mục tiêu và phạm vi dự án" />
                </Form.Item>

                <div className="detail-grid detail-grid-2">
                    <Form.Item name="project_type_id" label="Loại dự án">
                        <Select allowClear options={projectTypes.map((item) => ({ value: item.id, label: item.name }))} />
                    </Form.Item>
                    <Form.Item name="project_status_id" label="Trạng thái" rules={[{ required: true, message: 'Vui lòng chọn trạng thái.' }]}>
                        <Select options={projectStatuses.map((item) => ({ value: item.id, label: item.name }))} />
                    </Form.Item>
                    <Form.Item name="priority_id" label="Ưu tiên" rules={[{ required: true, message: 'Vui lòng chọn ưu tiên.' }]}>
                        <Select options={priorities.map((item) => ({ value: item.id, label: item.name }))} />
                    </Form.Item>
                    <Form.Item name="manager_admin_id" label="Người quản lý">
                        <Select allowClear options={admins.map((item) => ({ value: item.id, label: `${item.name} (${item.email})` }))} />
                    </Form.Item>
                    <Form.Item name="start_date" label="Ngày bắt đầu">
                        <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" />
                    </Form.Item>
                    <Form.Item name="due_date" label="Ngày hoàn thành dự kiến">
                        <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" />
                    </Form.Item>
                    <Form.Item name="progress" label="Tiến độ (%)">
                        <Input type="number" min={0} max={100} />
                    </Form.Item>
                    <Form.Item name="color" label="Màu nhận diện">
                        <ColorPicker format="hex" showText />
                    </Form.Item>
                </div>

                <Form.Item name="member_admin_ids" label="Thành viên dự án">
                    <Select
                        mode="multiple"
                        allowClear
                        options={admins.map((item) => ({ value: item.id, label: `${item.name} (${item.email})` }))}
                        placeholder="Chọn các admin tham gia dự án"
                    />
                </Form.Item>
            </Form>
        </Drawer>
    );
}
