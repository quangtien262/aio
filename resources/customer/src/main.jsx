import React from 'react';
import ReactDOM from 'react-dom/client';
import 'antd/dist/reset.css';
import CustomerApp from './app/CustomerApp';
import './styles/index.css';

ReactDOM.createRoot(document.getElementById('customer-root')).render(
    <React.StrictMode>
        <CustomerApp />
    </React.StrictMode>
);