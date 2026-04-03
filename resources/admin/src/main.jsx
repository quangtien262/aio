import React from 'react';
import ReactDOM from 'react-dom/client';
import 'antd/dist/reset.css';
import AdminApp from './app/AdminApp';
import './styles/index.css';

ReactDOM.createRoot(document.getElementById('admin-root')).render(
    <React.StrictMode>
        <AdminApp />
    </React.StrictMode>
);
