<script data-landing-admin-sort>
(() => {
    const list = document.querySelector('.aio-landing-editor-toolbar-content[data-reorder-url]');
    if (!list) return;
    const status = list.parentElement.querySelector('[role="status"]');
    const rows = () => Array.from(list.children);
    const ids = () => rows().map((row) => row.dataset.sortBlockId);
    let committed = ids();
    let busy = false;
    let drag = null;

    const restore = () => {
        const byId = new Map(rows().map((row) => [row.dataset.sortBlockId, row]));
        committed.forEach((id) => list.append(byId.get(id)));
    };
    const save = async () => {
        const ordered = ids();
        if (ordered.every((id, index) => id === committed[index])) return;
        busy = true;
        list.querySelectorAll('button').forEach((button) => button.disabled = true);
        status.textContent = 'Đang lưu thứ tự…';
        try {
            const response = await fetch(list.dataset.reorderUrl, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({blocks: ordered.map((id, index) => ({id: Number(id), sort_order: (index + 1) * 10}))}),
            });
            if (!response.ok || response.redirected) throw new Error('save-failed');
            // Require the API's JSON response, including when authentication has expired.
            await response.json();
            committed = ordered;
            document.dispatchEvent(new CustomEvent('landing-blocks-reordered', {detail: ordered}));
            status.textContent = 'Đã lưu thứ tự.';
        } catch (_) {
            restore();
            status.textContent = 'Không lưu được thứ tự. Đã khôi phục thứ tự cũ; vui lòng thử lại.';
        } finally {
            busy = false;
            list.querySelectorAll('button').forEach((button) => button.disabled = false);
        }
    };

    list.addEventListener('pointerdown', (event) => {
        const handle = event.target.closest('.aio-landing-editor-drag');
        if (!handle || busy || drag || event.button !== 0) return;
        drag = {handle, row: handle.parentElement, pointerId: event.pointerId, startY: event.clientY, moved: false};
        list.setPointerCapture(event.pointerId);
    });
    list.addEventListener('pointermove', (event) => {
        if (!drag || event.pointerId !== drag.pointerId) return;
        if (!drag.moved && Math.abs(event.clientY - drag.startY) < 6) return;
        drag.moved = true;
        drag.row.classList.add('is-dragging');
        const target = document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-sort-block-id]');
        if (target && target !== drag.row && target.parentElement === list) {
            const after = event.clientY > target.getBoundingClientRect().top + target.offsetHeight / 2;
            list.insertBefore(drag.row, after ? target.nextSibling : target);
        }
        const toolbar = list.closest('aside');
        const bounds = toolbar.getBoundingClientRect();
        if (event.clientY > bounds.bottom - 40) toolbar.scrollTop += 18;
        if (event.clientY < bounds.top + 40) toolbar.scrollTop -= 18;
    });
    const finish = (event, cancelled = false) => {
        if (!drag || event.pointerId !== drag.pointerId) return;
        const current = drag;
        drag = null;
        current.row.classList.remove('is-dragging');
        if (list.hasPointerCapture(event.pointerId)) list.releasePointerCapture(event.pointerId);
        if (cancelled) restore();
        else if (current.moved) save();
    };
    list.addEventListener('pointerup', (event) => finish(event));
    list.addEventListener('pointercancel', (event) => finish(event, true));
    list.addEventListener('lostpointercapture', (event) => finish(event, true));
    list.addEventListener('keydown', (event) => {
        const handle = event.target.closest('.aio-landing-editor-drag');
        if (!handle || busy || drag || !['ArrowUp', 'ArrowDown'].includes(event.key)) return;
        event.preventDefault();
        const row = handle.parentElement;
        const sibling = event.key === 'ArrowUp' ? row.previousElementSibling : row.nextElementSibling;
        if (!sibling) return;
        list.insertBefore(row, event.key === 'ArrowUp' ? sibling : sibling.nextSibling);
        save().then(() => handle.focus());
    });
})();
</script>
