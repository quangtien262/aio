import CheckOutlined from '@ant-design/icons/CheckOutlined';
import FnbStatusTag from './FnbStatusTag';

const steps = [
    { status: 'waiting', label: 'Chờ pha chế', color: '#a86a16', background: '#fff2d8' },
    { status: 'preparing', label: 'Đang pha', color: '#1677cc', background: '#e7f2ff' },
    { status: 'ready', label: 'Chờ phục vụ', color: '#07857d', background: '#ddf5ee' },
    { status: 'served', label: 'Đã giao món', color: '#438218', background: '#edf6df' },
];

export default function FnbKitchenProgress({ status }) {
    const current = steps.findIndex((step) => step.status === status);
    if (current < 0) return <FnbStatusTag status={status} />;

    return <ol className="fnb-kitchen-progress" aria-label="Tiến độ chế biến">
        {steps.map((step, index) => <li key={step.status}
            className={`fnb-kitchen-progress-step ${index === current ? 'is-current' : index < current ? 'is-complete' : 'is-upcoming'}`}
            style={{ '--step-color': step.color, '--step-background': step.background }}
            aria-current={index === current ? 'step' : undefined}
            aria-label={`${step.label}: ${index === current ? 'hiện tại' : index < current ? 'đã qua' : 'chưa tới'}`}>
            <span className="fnb-kitchen-progress-dot" aria-hidden="true">{index < current ? <CheckOutlined /> : index + 1}</span>
            <span className="fnb-kitchen-progress-label">{step.label}</span>
        </li>)}
    </ol>;
}
