const key = (row) => String(row?.public_id ?? row?.id ?? '');

export function manufacturingOrganization(factories, workshops, lines) {
    const groups = new Map(factories.map((factory) => [key(factory), { factory, workshops: [], otherLines: [], workshopMap: new Map() }]));
    for (const workshop of workshops) {
        const group = groups.get(String(workshop.factory_id));
        if (!group) continue;
        const item = { workshop, lines: [] };
        group.workshops.push(item);
        group.workshopMap.set(key(workshop), item);
    }
    for (const line of lines) {
        const group = groups.get(String(line.factory_id));
        if (!group) continue;
        const parent = group.workshopMap.get(String(line.workshop_id));
        (parent ? parent.lines : group.otherLines).push(line);
    }
    return [...groups.values()].map(({ workshopMap, ...group }) => group);
}

const searchText = (value) => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[đĐ]/g, 'd').toLowerCase();
const recordText = (row) => `${row?.code ?? ''} ${row?.name ?? ''}`;

export function manufacturingCompanyOrganization(companies, factoryGroups, query = '') {
    const groups = new Map(companies.map((company) => [key(company), { company, factories: [] }]));
    const needle = searchText(query).trim();
    for (const factory of factoryGroups) {
        const parent = groups.get(String(factory.factory.company_id));
        if (!parent) continue;
        const text = [recordText(parent.company), recordText(factory.factory),
            ...factory.workshops.flatMap((item) => [recordText(item.workshop), ...item.lines.map(recordText)]),
            ...factory.otherLines.map(recordText)].join(' ');
        // Keep the entire factory visible when a descendant matches, so its context stays clear.
        if (!needle || searchText(text).includes(needle)) parent.factories.push(factory);
    }
    return [...groups.values()].filter((group) => !needle || group.factories.length || searchText(recordText(group.company)).includes(needle));
}

export function manufacturingOrganizationRows(companies, groups, query = '') {
    const rows = [];
    const add = (kind, record, depth, parent, detail, factoryId = null, workshopId = null) => rows.push({
        key: `${kind}:${key(record)}`, kind, record, depth, parent, detail, factoryId, workshopId,
    });
    for (const { company, factories } of manufacturingCompanyOrganization(companies, groups, query)) {
        add('company', company, 0, '—', `${factories.length} nhà máy`);
        for (const group of factories) {
            const factoryId = key(group.factory);
            const lineCount = group.otherLines.length + group.workshops.reduce((sum, item) => sum + item.lines.length, 0);
            add('factory', group.factory, 1, company.name, `${group.workshops.length} phân xưởng · ${lineCount} chuyền`, factoryId);
            for (const item of group.workshops) {
                const workshopId = key(item.workshop);
                add('workshop', item.workshop, 2, `${group.factory.code} · ${group.factory.name}`, `${item.lines.length} chuyền`, factoryId, workshopId);
                for (const line of item.lines) add('line', line, 3, `${group.factory.code} / ${item.workshop.code} · ${item.workshop.name}`, line.capacity_minutes, factoryId, workshopId);
            }
            for (const line of group.otherLines) add('line', line, 2, `${group.factory.code} · Chưa gắn phân xưởng`, line.capacity_minutes, factoryId);
        }
    }
    return rows;
}
