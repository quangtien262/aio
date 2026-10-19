export const overviewPermissions = ['fnb.dashboard.view', 'fnb.report.operations.view', 'fnb.report.financial.view'];

export function overviewViews(can) {
    return [
        ...(can('fnb.dashboard.view') ? ['today'] : []),
        ...(can('fnb.report.operations.view') || can('fnb.report.financial.view') ? ['reports'] : []),
    ];
}

export function fnbSectionPath(key) {
    return key === 'reports' ? '/modules/fnb-pos/dashboard?view=reports' : `/modules/fnb-pos/${key}`;
}

export function overviewView(requested, available) {
    return available.includes(requested) ? requested : available[0];
}
