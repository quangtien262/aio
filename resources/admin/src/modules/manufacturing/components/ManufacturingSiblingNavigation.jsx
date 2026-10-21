import { useEffect, useRef } from 'react';
import { Link } from 'react-router-dom';

export default function ManufacturingSiblingNavigation({ group, selectedKey }) {
    const navigation = useRef(null);

    useEffect(() => {
        const container = navigation.current;
        const active = container?.querySelector('[aria-current="page"]');
        if (!active) return;
        const viewport = container.getBoundingClientRect();
        const item = active.getBoundingClientRect();
        if (item.left < viewport.left) container.scrollLeft -= viewport.left - item.left;
        else if (item.right > viewport.right) container.scrollLeft += item.right - viewport.right;
    }, [group.key, selectedKey]);

    return <nav ref={navigation} className="admin-manufacturing-subnav" aria-label={`Chức năng ${group.label}`}>
        {group.children.filter((item) => item.route).map((item) => <Link key={item.key} to={item.route}
            className={`admin-manufacturing-subnav-link${item.key === selectedKey ? ' is-active' : ''}`}
            aria-current={item.key === selectedKey ? 'page' : undefined}>
            <span aria-hidden="true">{item.icon}</span><span>{item.label}</span>
        </Link>)}
    </nav>;
}
