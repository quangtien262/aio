import React from 'react';
import ReactDOM from 'react-dom/client';
import 'antd/dist/reset.css';
import CustomerApp from './app/CustomerApp';
import './styles/index.css';

const customerRoot = document.getElementById('customer-root');

ReactDOM.createRoot(customerRoot).render(
    <React.StrictMode>
        <CustomerApp
            basename={customerRoot?.dataset.basename || '/account'}
            apiBase={customerRoot?.dataset.apiBase || '/account/api'}
        />
    </React.StrictMode>
);
