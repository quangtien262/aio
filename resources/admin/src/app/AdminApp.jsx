import App from 'antd/es/app';
import ConfigProvider from 'antd/es/config-provider';
import { BrowserRouter } from 'react-router-dom';
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
                <BrowserRouter basename="/admin">
                    <AdminLayout />
                </BrowserRouter>
            </App>
        </ConfigProvider>
    );
}
