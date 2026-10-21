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
        <div className="fnb-overview-heading fnb-overview-title"><span>{selectedOutlet?.name ?? 'Điểm bán'}</span><h2>{active === 'reports' ? 'Báo cáo' : 'Tổng quan hôm nay'}</h2></div>
        {active === 'today' ? <FnbDashboardPage embedded /> : active === 'reports' ? <FnbReportsPage embedded /> : null}
    </div>;
}
