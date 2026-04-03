import { App, ConfigProvider } from 'antd';
import AdminLayout from '../layouts/AdminLayout';

export default function AdminApp() {
    return (
        <ConfigProvider
            theme={{
                token: {
                    colorPrimary: '#0f766e',
                    colorBgLayout: '#f3f7f6',
                    borderRadius: 14,
                    fontFamily: 'Segoe UI, sans-serif',
                },
            }}
        >
            <App>
                <AdminLayout />
            </App>
        </ConfigProvider>
    );
}
