import DashboardOutlined from '@ant-design/icons/DashboardOutlined';
import BarChartOutlined from '@ant-design/icons/BarChartOutlined';
import Tabs from 'antd/es/tabs';
import { useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useFnbWorkspace } from '../../state/FnbWorkspaceContext';
import { overviewView, overviewViews } from '../../utils/fnbOverviewNavigation';
import FnbDashboardPage from './FnbDashboardPage';
import FnbReportsPage from './FnbReportsPage';

export default function FnbOverviewPage() {
    const { can, selectedOutlet } = useFnbWorkspace();
    const [searchParams, setSearchParams] = useSearchParams();
    const available = overviewViews(can);
    const requested = searchParams.get('view');
    const active = overviewView(requested, available);
    useEffect(() => {
        if (!active || requested === active) return;
        setSearchParams((current) => {
            const next = new URLSearchParams(current);
            next.set('view', active);
            return next;
        }, { replace: true });
    }, [active, requested, setSearchParams]);

    return <div className="fnb-page-stack fnb-overview-page">
        <Tabs className="fnb-overview-tabs" tabBarExtraContent={{ left: <div className="fnb-overview-title"><span>{selectedOutlet?.name ?? 'Điểm bán'}</span><h2>Tổng quan</h2></div> }} activeKey={active} onChange={(key) => setSearchParams((current) => {
            const next = new URLSearchParams(current);
            next.set('view', key);
            return next;
        })} items={available.map((key) => key === 'today'
            ? { key, label: 'Hôm nay', icon: <DashboardOutlined />, children: <FnbDashboardPage embedded /> }
            : { key, label: 'Báo cáo', icon: <BarChartOutlined />, children: <FnbReportsPage embedded /> })} />
    </div>;
}
