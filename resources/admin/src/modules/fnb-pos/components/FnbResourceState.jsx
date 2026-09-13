import Alert from 'antd/es/alert';
import Button from 'antd/es/button';
import Empty from 'antd/es/empty';
import ReloadOutlined from '@ant-design/icons/ReloadOutlined';

export function FnbResourceError({ error, onRetry, title = 'Không tải được dữ liệu' }) {
    if (!error) {
        return null;
    }

    return (
        <Alert
            type="error"
            showIcon
            message={title}
            description={(
                <div>
                    <div>{error.message || 'Vui lòng thử lại.'}</div>
                    {error.requestId ? <div className="fnb-request-id">Mã đối soát: {error.requestId}</div> : null}
                </div>
            )}
            action={onRetry ? <Button size="small" icon={<ReloadOutlined />} onClick={() => onRetry()}>Thử lại</Button> : null}
        />
    );
}

export function FnbEmpty({ description = 'Chưa có dữ liệu', action = null }) {
    return <Empty image={Empty.PRESENTED_IMAGE_SIMPLE} description={description}>{action}</Empty>;
}

