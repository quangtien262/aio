@once
    <datalist id="th-product-search-suggestions"></datalist>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const inputs = Array.from(document.querySelectorAll('[data-th-product-search]'));

            if (inputs.length === 0) {
                return;
            }

            const datalist = document.getElementById('th-product-search-suggestions');
            const cache = new Map();
            const suggestionsByInput = new WeakMap();
            let activeController = null;

            if (!datalist) {
                return;
            }

            const renderOptions = (items) => {
                datalist.innerHTML = '';

                items.forEach((item) => {
                    if (!item || !item.value) {
                        return;
                    }

                    const option = document.createElement('option');
                    const meta = [item.sku, item.price_label].filter(Boolean).join(' · ');

                    option.value = item.value;

                    if (meta) {
                        option.label = meta;
                    }

                    option.textContent = [item.label, item.category].filter(Boolean).join(' · ');
                    datalist.appendChild(option);
                });
            };

            const findMatchingSuggestion = (input) => {
                const currentValue = input.value.trim().toLowerCase();

                if (!currentValue) {
                    return null;
                }

                return (suggestionsByInput.get(input) || []).find((item) => {
                    return typeof item?.value === 'string'
                        && item.value.trim().toLowerCase() === currentValue
                        && typeof item?.url === 'string'
                        && item.url !== '';
                }) || null;
            };

            const redirectToSuggestion = (input) => {
                const match = findMatchingSuggestion(input);

                if (!match) {
                    return false;
                }

                window.location.assign(match.url);
                return true;
            };

            const fetchSuggestions = (input, term) => {
                const suggestUrl = input.dataset.suggestUrl;

                if (!suggestUrl || term.length < 2) {
                    suggestionsByInput.set(input, []);
                    renderOptions([]);
                    return;
                }

                const cacheKey = `${suggestUrl}::${term.toLowerCase()}`;

                if (cache.has(cacheKey)) {
                    const cachedItems = cache.get(cacheKey);

                    suggestionsByInput.set(input, cachedItems);
                    renderOptions(cachedItems);
                    return;
                }

                if (activeController) {
                    activeController.abort();
                }

                activeController = new AbortController();

                const url = new URL(suggestUrl, window.location.origin);
                url.searchParams.set('q', term);

                fetch(url.toString(), {
                    headers: { Accept: 'application/json' },
                    signal: activeController.signal,
                })
                    .then((response) => response.ok ? response.json() : Promise.reject(new Error('Request failed')))
                    .then((payload) => {
                        const items = Array.isArray(payload.data) ? payload.data : [];
                        cache.set(cacheKey, items);
                        suggestionsByInput.set(input, items);

                        if (input.value.trim() === term) {
                            renderOptions(items);
                        }
                    })
                    .catch((error) => {
                        if (error.name !== 'AbortError') {
                            suggestionsByInput.set(input, []);
                            renderOptions([]);
                        }
                    });
            };

            inputs.forEach((input) => {
                let debounceTimer = null;

                input.setAttribute('list', 'th-product-search-suggestions');
                input.setAttribute('autocomplete', 'off');

                input.addEventListener('input', () => {
                    const term = input.value.trim();

                    window.clearTimeout(debounceTimer);
                    debounceTimer = window.setTimeout(() => {
                        fetchSuggestions(input, term);
                    }, 180);
                });

                input.addEventListener('change', () => {
                    redirectToSuggestion(input);
                });

                input.addEventListener('keydown', (event) => {
                    if (event.key !== 'Enter') {
                        return;
                    }

                    if (redirectToSuggestion(input)) {
                        event.preventDefault();
                    }
                });

                input.addEventListener('focus', () => {
                    const term = input.value.trim();

                    if (term.length >= 2) {
                        fetchSuggestions(input, term);
                    }
                });
            });
        });
    </script>
@endonce
