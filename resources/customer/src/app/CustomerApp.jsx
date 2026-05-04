import App from 'antd/es/app';
import ConfigProvider from 'antd/es/config-provider';
import { BrowserRouter } from 'react-router-dom';
import CustomerLayout from '../layouts/CustomerLayout';

export default function CustomerApp({ basename = '/account', apiBase = '/account/api' }) {
    return (
        <ConfigProvider
            theme={{
                token: {
                    colorPrimary: '#c2410c',
                    colorInfo: '#c2410c',
                    colorBgLayout: '#fffaf3',
                    borderRadius: 18,
                    fontFamily: 'Segoe UI, sans-serif',
                },
            }}
        >
            <App>
                <BrowserRouter basename={basename}>
                    <CustomerLayout apiBase={apiBase} />
                </BrowserRouter>
            </App>
        </ConfigProvider>
    );
}
