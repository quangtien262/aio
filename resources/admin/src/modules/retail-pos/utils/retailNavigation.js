const groups = [
    {
        key: 'retail-merchandise-group', label: 'Hàng hóa & kho', icon: 'inbox',
        members: ['retail-products', 'retail-purchasing', 'retail-inventory', 'retail-transfers', 'retail-stocktakes'],
    },
    {
        key: 'retail-operations-group', label: 'Vận hành', icon: 'shop',
        members: ['retail-branches', 'retail-shifts', 'retail-returns', 'retail-customers'],
    },
];
const icons = {
    'retail-dashboard': 'dashboard', 'retail-pos': 'shop', 'retail-products': 'tags',
    'retail-purchasing': 'shopping', 'retail-inventory': 'inbox', 'retail-transfers': 'swap',
    'retail-stocktakes': 'check-circle', 'retail-branches': 'apartment', 'retail-shifts': 'wallet',
    'retail-returns': 'swap', 'retail-customers': 'team', 'retail-reports': 'bar-chart', 'retail-settings': 'setting',
};

// Receive the shell's permission-filtered links; grouping never grants access
// or introduces destinations that were absent from that list.
export function buildRetailNavigation(items, resolveIcon = (icon) => icon) {
    // POS exposes shift management in a modal; keep the link for shift-only roles.
    if (items.some((item) => item.key === 'retail-pos')) items = items.filter((item) => item.key !== 'retail-shifts');
    const leaf = (item) => ({ ...item, icon: icons[item.key] ? resolveIcon(icons[item.key]) : item.icon });
    const links = (keys) => keys.flatMap((key) => items.filter((item) => item.key === key).map(leaf));
    const overviewKeys = ['retail-dashboard', 'retail-reports'];
    const overviewLink = links(overviewKeys)[0];
    const overview = overviewLink ? [{ ...overviewLink, label: 'Tổng quan', icon: resolveIcon('dashboard') }] : [];
    const leading = ['retail-pos'];
    const trailing = ['retail-settings'];
    const groupedKeys = new Set([...overviewKeys, ...leading, ...trailing, ...groups.flatMap((group) => group.members)]);
    const grouped = groups.flatMap(({ key, label, icon, members }) => {
        const children = links(members);
        return children.length ? [{ key, label, icon: resolveIcon(icon), children }] : [];
    });
    return [...overview, ...links(leading), ...grouped, ...links(trailing), ...items.filter((item) => !groupedKeys.has(item.key))];
}
