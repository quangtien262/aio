export function currentWorkspaceNavigationItem(items, pathname) {
    return items.find((item) => item.route === pathname)
        ?? items.filter((item) => item.route !== '/' && pathname.startsWith(`${item.route}/`))
            .sort((left, right) => right.route.length - left.route.length)[0]
        ?? null;
}

export function findWorkspaceMenuItem(items, key) {
    for (const item of items) {
        if (item.key === key) return item;
        const child = item.children && findWorkspaceMenuItem(item.children, key);
        if (child) return child;
    }
    return null;
}

export function moduleWorkspaceNavigation(currentItem, navigation, identity) {
    return currentItem?.source === 'module' && navigation?.moduleKey === currentItem.moduleKey && navigation.identity === identity ? navigation : null;
}
