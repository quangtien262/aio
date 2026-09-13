import Tag from 'antd/es/tag';
import { statusColor, statusLabel } from '../utils/fnbFormat';

export default function FnbStatusTag({ status, children }) {
    return <Tag color={statusColor(status)}>{children ?? statusLabel(status)}</Tag>;
}

