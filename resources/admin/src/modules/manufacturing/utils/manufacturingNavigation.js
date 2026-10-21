const groups = [
    { key: 'manufacturing-preparation-group', label: 'Chuẩn bị', icon: 'solution', members: ['manufacturing-demands', 'manufacturing-engineering', 'manufacturing-planning'] },
    { key: 'manufacturing-production-group', label: 'Sản xuất', icon: 'tool', members: ['manufacturing-materials', 'manufacturing-cutting', 'manufacturing-execution', 'manufacturing-quality', 'manufacturing-subcontracts'] },
    { key: 'manufacturing-finished-group', label: 'Thành phẩm', icon: 'inbox', members: ['manufacturing-fulfillment', 'manufacturing-costing'] },
];

// The shell supplies only visible, permission-filtered links. Grouping must not
// create routes or restore destinations that the current actor cannot access.
export function buildManufacturingNavigation(items, resolveIcon = (icon) => icon) {
    const groupedKeys = new Set(groups.flatMap((group) => group.members));
    const overview = items.filter((item) => item.key === 'manufacturing-overview');
    const settings = items.filter((item) => item.key === 'manufacturing-settings');
    const grouped = groups.flatMap(({ key, label, icon, members }) => {
        const children = members.flatMap((member) => items.filter((item) => item.key === member));
        return children.length ? [{ key, label, icon: resolveIcon(icon), children }] : [];
    });
    const remaining = items.filter((item) => !groupedKeys.has(item.key) && !['manufacturing-overview', 'manufacturing-settings'].includes(item.key));
    return [...overview, ...grouped, ...settings, ...remaining];
}
