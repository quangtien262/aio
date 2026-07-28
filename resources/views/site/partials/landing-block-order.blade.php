@php
    $orderedBlockIds = collect($landingBlocks ?? [])
        ->pluck('id')
        ->filter()
        ->map(fn ($id) => (string) $id)
        ->values()
        ->all();
@endphp
@if(count($orderedBlockIds) > 1)
<script data-landing-block-order>
(() => {
    const orderedIds = @json($orderedBlockIds);
    const orderById = new Map(orderedIds.map((id, index) => [String(id), index]));
    const nodes = Array.from(document.querySelectorAll('[data-landing-block-id]'))
        .filter((node) => orderById.has(String(node.dataset.landingBlockId || '')));
    const nodesByParent = new Map();

    nodes.forEach((node) => {
        if (!node.parentNode) return;
        const siblings = nodesByParent.get(node.parentNode) || [];
        siblings.push(node);
        nodesByParent.set(node.parentNode, siblings);
    });

    nodesByParent.forEach((siblings, parent) => {
        if (siblings.length < 2) return;

        const desired = [...siblings].sort((left, right) => (
            orderById.get(String(left.dataset.landingBlockId))
            - orderById.get(String(right.dataset.landingBlockId))
        ));
        const alreadyOrdered = siblings.every((node, index) => node === desired[index]);

        if (alreadyOrdered) return;

        const placeholders = siblings.map((node) => {
            const placeholder = document.createComment('landing-block-slot');
            parent.insertBefore(placeholder, node);
            node.remove();
            return placeholder;
        });

        desired.forEach((node, index) => placeholders[index].replaceWith(node));
    });
})();
</script>
@endif
