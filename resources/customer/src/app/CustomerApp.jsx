import App from 'antd/es/app';
import ConfigProvider from 'antd/es/config-provider';
import { BrowserRouter } from 'react-router-dom';
import CustomerLayout from '../layouts/CustomerLayout';

export default function CustomerApp({ basename = '/account', apiBase = '/account/api', homeUrl = '/', logoutUrl = '/logout' }) {
    return (
        <ConfigProvider
            theme={{
                token: {
                    colorPrimary: '#0f766e',
                    colorInfo: '#0f766e',
                    colorBgLayout: '#f5fbf9',
                    borderRadius: 18,
                    fontFamily: 'Be Vietnam Pro, Segoe UI, sans-serif',
                },
            }}
        >
            <App>
                <BrowserRouter basename={basename}>
                    <CustomerLayout apiBase={apiBase} homeUrl={homeUrl} logoutUrl={logoutUrl} />
                </BrowserRouter>
            </App>
        </ConfigProvider>
    );
}
