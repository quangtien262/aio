<script data-landing-admin-sort>
(() => {
    const list = document.querySelector('.aio-landing-editor-toolbar-content[data-reorder-url]');
    if (!list) return;
    const status = list.parentElement.querySelector('[role="status"]');
    const toolbar = list.closest('aside');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
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

    const animations = new Map();
    const moveRow = (row, before) => {
        if (before === row || row.nextElementSibling === before) return;
        const positions = new Map(rows().map((item) => [item, item.offsetTop]));
        list.insertBefore(row, before);
        if (reducedMotion.matches) return;
        rows().forEach((item) => {
            const delta = positions.get(item) - item.offsetTop;
            if (!delta || item === row) return;
            animations.get(item)?.cancel();
            const animation = item.animate([
                {transform: `translateY(${delta}px)`},
                {transform: 'translateY(0)'},
            ], {duration: 160, easing: 'ease-out'});
            animations.set(item, animation);
            animation.onfinish = () => {
                if (animations.get(item) === animation) animations.delete(item);
            };
        });
    };
    const updateDrag = (time, schedule = true) => {
        if (!drag?.moved) return;
        const current = drag;
        const bounds = toolbar.getBoundingClientRect();
        const elapsed = Math.min(time - current.lastTime, 32);
        current.lastTime = time;
        // Scroll on animation frames even when the pointer is held still near an edge.
        const edge = Math.min(56, bounds.height / 4);
        const withinX = current.x >= bounds.left - 24 && current.x <= bounds.right + 24;
        const speed = !withinX ? 0 : current.y < bounds.top + edge
            ? -Math.min(1, (bounds.top + edge - current.y) / edge)
            : current.y > bounds.bottom - edge
                ? Math.min(1, (current.y - bounds.bottom + edge) / edge) : 0;
        if (speed) toolbar.scrollTop += speed * elapsed * .55;
        const top = Math.max(bounds.top, Math.min(current.y - current.grabY, bounds.bottom - current.height));
        current.ghost.style.transform = `translate3d(0,${top - current.originTop}px,0)`;
        const center = top + current.height / 2;
        const listTop = list.getBoundingClientRect().top;
        // Use layout positions, ignoring the temporary transforms of animated siblings.
        if (withinX) {
            const siblings = rows().filter((item) => item !== current.row);
            const before = siblings.find((item) => center < listTop + item.offsetTop + item.offsetHeight / 2) || null;
            moveRow(current.row, before);
        }
        if (schedule) current.frame = requestAnimationFrame(updateDrag);
    };
    list.addEventListener('pointerdown', (event) => {
        const handle = event.target.closest('.aio-landing-editor-drag');
        if (!handle || busy || drag || event.button !== 0 || !event.isPrimary) return;
        const row = handle.parentElement;
        const bounds = row.getBoundingClientRect();
        drag = {
            handle, row, pointerId: event.pointerId, startY: event.clientY,
            x: event.clientX, y: event.clientY, moved: false, frame: null,
            grabY: event.clientY - bounds.top, height: bounds.height, originTop: bounds.top,
        };
        list.setPointerCapture(event.pointerId);
    });
    list.addEventListener('pointermove', (event) => {
        if (!drag || event.pointerId !== drag.pointerId) return;
        drag.x = event.clientX;
        drag.y = event.clientY;
        if (drag.moved || Math.abs(drag.y - drag.startY) < 6) return;
        const bounds = drag.row.getBoundingClientRect();
        const ghost = drag.row.cloneNode(true);
        ghost.removeAttribute('data-sort-block-id');
        ghost.classList.add('aio-landing-editor-toolbar', 'aio-landing-editor-ghost');
        ghost.setAttribute('aria-hidden', 'true');
        ghost.querySelectorAll('button').forEach((button) => {
            button.tabIndex = -1;
            button.removeAttribute('data-xd-edit-block');
        });
        Object.assign(ghost.style, {left: `${bounds.left}px`, top: `${drag.originTop}px`, width: `${bounds.width}px`});
        // Render outside the scrolling toolbar so the preview is never clipped.
        document.body.append(ghost);
        drag.ghost = ghost;
        drag.moved = true;
        drag.lastTime = performance.now();
        drag.row.classList.add('is-dragging');
        toolbar.classList.add('is-sorting');
        status.textContent = 'Thả để lưu thứ tự. Nhấn Esc để hủy.';
        drag.frame = requestAnimationFrame(updateDrag);
    });
    const finish = (event, cancelled = false) => {
        if (!drag || (event?.pointerId != null && event.pointerId !== drag.pointerId)) return;
        const current = drag;
        if (current.moved && !cancelled) updateDrag(performance.now(), false);
        drag = null;
        cancelAnimationFrame(current.frame);
        current.ghost?.remove();
        current.row.classList.remove('is-dragging');
        toolbar.classList.remove('is-sorting');
        animations.forEach((animation) => animation.cancel());
        animations.clear();
        if (list.hasPointerCapture(current.pointerId)) list.releasePointerCapture(current.pointerId);
        if (cancelled) {
            restore();
            status.textContent = current.moved ? 'Đã hủy sắp xếp.' : '';
        } else if (current.moved) {
            status.textContent = '';
            save();
        }
    };
    list.addEventListener('pointerup', (event) => finish(event));
    list.addEventListener('pointercancel', (event) => finish(event, true));
    list.addEventListener('lostpointercapture', (event) => finish(event, true));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && drag) {
            event.preventDefault();
            finish(null, true);
        }
    });
    window.addEventListener('blur', () => finish(null, true));
    list.addEventListener('keydown', (event) => {
        const handle = event.target.closest('.aio-landing-editor-drag');
        if (!handle || busy || drag || !['ArrowUp', 'ArrowDown'].includes(event.key)) return;
        event.preventDefault();
        const row = handle.parentElement;
        const sibling = event.key === 'ArrowUp' ? row.previousElementSibling : row.nextElementSibling;
        if (!sibling) return;
        moveRow(row, event.key === 'ArrowUp' ? sibling : sibling.nextSibling);
        save().then(() => handle.focus());
    });
})();
</script>
