import { fnbSectionPath, overviewView } from './fnbOverviewNavigation.js';

const configurationKeys = new Set(['onboarding', 'staff', 'chain', 'settings', 'integrations', 'guide']);
const icons = { onboarding: 'appstore', dashboard: 'dashboard', pos: 'shop', kitchen: 'coffee', menu: 'menu', shifts: 'wallet', customers: 'team', staff: 'team', chain: 'appstore', integrations: 'swap', settings: 'setting', guide: 'read' };
export const fnbWorkspaceMenuKey = (key) => `fnb-workspace-${key}`;

// Sections have already been filtered by live outlet/HQ permissions by the app.
export function buildFnbWorkspaceNavigation(sections, views) {
    const item = (section) => ({ key: fnbWorkspaceMenuKey(section.key), label: section.label, icon: icons[section.key], route: fnbSectionPath(section.key) });
    const main = sections.filter((section) => !configurationKeys.has(section.key)).map((section) => {
        if (section.key !== 'dashboard') return item(section);
        const children = views.map((view) => ({ key: fnbWorkspaceMenuKey(view), label: view === 'today' ? 'Hôm nay' : 'Báo cáo', icon: view === 'today' ? 'dashboard' : 'bar-chart', route: `${fnbSectionPath('dashboard')}?view=${view}` }));
        return children.length > 1 ? { key: fnbWorkspaceMenuKey('overview'), label: 'Tổng quan', icon: 'dashboard', children } : children.map((child) => ({ ...child, label: child.key === fnbWorkspaceMenuKey('today') ? 'Tổng quan' : child.label }))[0];
    }).filter(Boolean);
    const configuration = sections.filter((section) => configurationKeys.has(section.key)).map(item);
    return [...main, ...(configuration.length ? [{ key: fnbWorkspaceMenuKey('configuration'), label: 'Cấu hình', icon: 'setting', children: configuration }] : [])];
}

export function selectedFnbWorkspaceMenuKey(section, search, views) {
    return fnbWorkspaceMenuKey(section === 'dashboard' ? overviewView(new URLSearchParams(search).get('view'), views) : section);
}
