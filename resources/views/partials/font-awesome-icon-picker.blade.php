@once
    @php
        $fontAwesomeIconCatalog = json_decode((string) file_get_contents(resource_path('shared/font-awesome-free-icons.json')), true) ?: [];
    @endphp
    <div class="aio-fa-picker" data-aio-fa-picker hidden aria-hidden="true">
        <section class="aio-fa-picker__dialog" role="dialog" aria-modal="true" aria-labelledby="aio-fa-picker-title">
            <header class="aio-fa-picker__head">
                <div><small>FONT AWESOME FREE</small><h3 id="aio-fa-picker-title">Chọn biểu tượng</h3><p>Chọn một icon để sử dụng, không cần nhớ tên class kỹ thuật.</p></div>
                <button type="button" class="aio-fa-picker__close" data-aio-fa-picker-close aria-label="Đóng">&times;</button>
            </header>
            <div class="aio-fa-picker__tools">
                <label><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="Tìm theo tên hoặc nhóm..." data-aio-fa-picker-search></label>
                <div class="aio-fa-picker__categories" data-aio-fa-picker-categories></div>
            </div>
            <div class="aio-fa-picker__grid" data-aio-fa-picker-grid></div>
            <div class="aio-fa-picker__empty" data-aio-fa-picker-empty hidden>Không tìm thấy icon phù hợp.</div>
            <footer><span data-aio-fa-picker-count></span><button type="button" data-aio-fa-picker-clear>Bỏ chọn icon</button></footer>
        </section>
    </div>
    <style>
        .xd-icon-picker-field{grid-column:1/-1}.xd-icon-picker-trigger{display:grid!important;grid-template-columns:44px minmax(0,1fr) auto;align-items:center;gap:12px;width:100%;min-height:62px!important;padding:8px 13px!important;border:1px solid #dfe4eb!important;border-radius:12px!important;background:#f8fafb!important;color:#263b66!important;text-align:left!important}.xd-icon-picker-trigger>i{display:grid;place-items:center;width:42px;height:42px;border-radius:10px;background:#fff;box-shadow:0 4px 14px rgba(38,59,102,.1);font-size:20px}.xd-icon-picker-trigger>span{font-size:14px;font-weight:850;text-transform:none}.xd-icon-picker-trigger>small{color:#7b8493;font-size:11px;font-weight:700;text-transform:none}.xd-icon-picker-trigger:hover,.xd-icon-picker-trigger.has-value{border-color:#263b66!important;background:#eff4ff!important}
        .aio-fa-picker[hidden]{display:none!important}.aio-fa-picker{position:fixed;inset:0;z-index:360;display:grid;place-items:center;padding:20px;background:rgba(15,23,42,.72);font-family:inherit;color:#17243a}.aio-fa-picker__dialog{width:min(920px,100%);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;border-radius:22px;background:#fff;box-shadow:0 32px 100px rgba(0,0,0,.36)}.aio-fa-picker__head{display:flex;align-items:flex-start;justify-content:space-between;gap:24px;padding:24px 26px 18px;border-bottom:1px solid #e5e9ef}.aio-fa-picker__head small{color:#718096;font-size:10px;font-weight:900;letter-spacing:.14em}.aio-fa-picker__head h3{margin:4px 0 3px!important;color:#17243a!important;font-size:25px!important}.aio-fa-picker__head p{margin:0;color:#6b7484;font-size:13px}.aio-fa-picker__close{display:grid;place-items:center;width:40px;height:40px;flex:0 0 auto;border:1px solid #dfe4eb;border-radius:50%;background:#f7f8fa;color:#17243a;font-size:24px;cursor:pointer}.aio-fa-picker__tools{display:grid;gap:13px;padding:18px 26px;border-bottom:1px solid #edf0f4}.aio-fa-picker__tools>label{display:flex!important;align-items:center;gap:11px;min-height:48px;padding:0 15px;border:1px solid #dfe4eb;border-radius:12px;background:#f9fafb}.aio-fa-picker__tools input{width:100%!important;min-height:0!important;padding:0!important;border:0!important;background:transparent!important;outline:0;font:inherit;text-transform:none!important}.aio-fa-picker__categories{display:flex;gap:7px;overflow:auto;padding-bottom:2px}.aio-fa-picker__categories button{min-height:34px!important;flex:0 0 auto;padding:0 12px!important;border:1px solid #dfe4eb!important;border-radius:999px!important;background:#fff!important;color:#354157!important;font-size:12px!important}.aio-fa-picker__categories button.is-active{border-color:#263b66!important;background:#263b66!important;color:#fff!important}.aio-fa-picker__grid{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));gap:10px;overflow:auto;padding:22px 26px}.aio-fa-picker__icon{display:grid!important;place-items:center;gap:8px;min-height:94px!important;padding:11px 7px!important;border:1px solid #e2e7ed!important;border-radius:14px!important;background:#fff!important;color:#263b66!important;text-align:center;cursor:pointer;transition:.18s}.aio-fa-picker__icon i{font-size:25px}.aio-fa-picker__icon span{overflow:hidden;width:100%;font-size:10px;font-weight:700;line-height:1.25;text-overflow:ellipsis;white-space:nowrap;text-transform:none}.aio-fa-picker__icon:hover,.aio-fa-picker__icon.is-selected{border-color:#263b66!important;background:#eff4ff!important;transform:translateY(-2px)}.aio-fa-picker__empty{padding:55px;text-align:center;color:#7b8493}.aio-fa-picker__dialog>footer{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:15px 26px;border-top:1px solid #e5e9ef;color:#697386;font-size:12px}.aio-fa-picker__dialog>footer button{min-height:38px!important;border:1px solid #dfe4eb!important;border-radius:999px!important;background:#fff!important;color:#9b2c2c!important;padding:0 14px!important}@media(max-width:700px){.aio-fa-picker{padding:10px}.aio-fa-picker__dialog{max-height:94vh}.aio-fa-picker__head{padding:19px}.aio-fa-picker__tools{padding:14px 19px}.aio-fa-picker__grid{grid-template-columns:repeat(4,minmax(0,1fr));padding:17px 19px}.aio-fa-picker__icon{min-height:82px!important}.aio-fa-picker__head p{display:none}}
    </style>
    <script>
        (() => {
            if (window.AioFontAwesomeIconPicker) return;
            const icons = @json($fontAwesomeIconCatalog);
            const root = document.querySelector('[data-aio-fa-picker]');
            if (!root) return;
            const grid = root.querySelector('[data-aio-fa-picker-grid]');
            const search = root.querySelector('[data-aio-fa-picker-search]');
            const categoriesNode = root.querySelector('[data-aio-fa-picker-categories]');
            const empty = root.querySelector('[data-aio-fa-picker-empty]');
            const count = root.querySelector('[data-aio-fa-picker-count]');
            const categories = ['Tất cả', ...new Set(icons.map((icon) => icon.category))];
            let activeCategory = 'Tất cả';
            let selectedValue = '';
            let selectHandler = null;
            let returnFocus = null;

            const normalized = (value) => String(value || '').toLocaleLowerCase('vi');
            const close = () => {
                root.hidden = true;
                root.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
                returnFocus?.focus?.();
            };
            const choose = (value) => {
                selectHandler?.(value);
                close();
            };
            const render = () => {
                const term = normalized(search.value);
                const visibleIcons = icons.filter((icon) => (activeCategory === 'Tất cả' || icon.category === activeCategory)
                    && (!term || normalized(`${icon.label} ${icon.className} ${icon.category}`).includes(term)));
                grid.innerHTML = '';
                visibleIcons.forEach((icon) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = `aio-fa-picker__icon${icon.className === selectedValue ? ' is-selected' : ''}`;
                    button.title = `${icon.label} — ${icon.className}`;
                    button.innerHTML = `<i class="${icon.className}" aria-hidden="true"></i><span>${icon.label}</span>`;
                    button.addEventListener('click', () => choose(icon.className));
                    grid.appendChild(button);
                });
                empty.hidden = visibleIcons.length > 0;
                count.textContent = `${visibleIcons.length} icon`;
            };
            categories.forEach((category) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = category;
                button.classList.toggle('is-active', category === activeCategory);
                button.addEventListener('click', () => {
                    activeCategory = category;
                    categoriesNode.querySelectorAll('button').forEach((item) => item.classList.toggle('is-active', item === button));
                    render();
                });
                categoriesNode.appendChild(button);
            });
            search.addEventListener('input', render);
            root.querySelectorAll('[data-aio-fa-picker-close]').forEach((button) => button.addEventListener('click', close));
            root.querySelector('[data-aio-fa-picker-clear]')?.addEventListener('click', () => choose(''));
            root.addEventListener('click', (event) => { if (event.target === root) close(); });
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !root.hidden) close(); });
            window.AioFontAwesomeIconPicker = {
                open({value = '', onSelect, trigger = null} = {}) {
                    selectedValue = value;
                    selectHandler = onSelect;
                    returnFocus = trigger || document.activeElement;
                    activeCategory = 'Tất cả';
                    search.value = '';
                    categoriesNode.querySelectorAll('button').forEach((button, index) => button.classList.toggle('is-active', index === 0));
                    render();
                    root.hidden = false;
                    root.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                    window.setTimeout(() => search.focus(), 30);
                },
            };
        })();
    </script>
@endonce
