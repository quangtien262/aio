const groups = [
    { key: 'pharmacy-records-group', label: 'Hồ sơ', icon: 'solution', members: ['pharmacy-prescriptions', 'pharmacy-customers', 'pharmacy-sales', 'pharmacy-shifts'] },
    { key: 'pharmacy-products-group', label: 'Hàng hóa', icon: 'inbox', members: ['pharmacy-products', 'pharmacy-prices'] },
    { key: 'pharmacy-stock-group', label: 'Kho & mua hàng', icon: 'database', members: ['pharmacy-inventory', 'pharmacy-purchasing', 'pharmacy-receipts', 'pharmacy-transfers', 'pharmacy-stocktakes', 'pharmacy-quality'] },
    { key: 'pharmacy-management-group', label: 'Quản trị', icon: 'setting', members: ['pharmacy-operations', 'pharmacy-reports', 'pharmacy-integrations', 'pharmacy-settings'] },
];

// Only regroup links already filtered by the shell's authority projection.
export function buildPharmacyNavigation(items, resolveIcon = (icon) => icon) {
    const quickKeys = ['pharmacy-work', 'pharmacy-pos'];
    const known = new Set([...quickKeys, ...groups.flatMap((group) => group.members)]);
    const quick = quickKeys.flatMap((key) => items.filter((item) => item.key === key));
    const grouped = groups.flatMap(({ key, label, icon, members }) => {
        const children = members.flatMap((member) => items.filter((item) => item.key === member));
        return children.length ? [{ key, label, icon: resolveIcon(icon), children }] : [];
    });
    return [...quick, ...grouped, ...items.filter((item) => !known.has(item.key))];
}
