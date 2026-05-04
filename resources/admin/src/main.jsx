import React from 'react';
import ReactDOM from 'react-dom/client';
import '@ant-design/v5-patch-for-react-19';
import 'antd/dist/reset.css';
import AdminApp from './app/AdminApp';
import './styles/index.css';

ReactDOM.createRoot(document.getElementById('admin-root')).render(
    <React.StrictMode>
        <AdminApp />
    </React.StrictMode>
);
