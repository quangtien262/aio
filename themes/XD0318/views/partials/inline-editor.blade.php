    @if ($canEditLanding)
        <div class="xd-editor" data-xd-editor hidden>
            <form class="xd-editor-card" data-xd-editor-form>
                <div class="xd-editor-head"><h3>SÃ¡Â»Â­a khÃ¡Â»â€˜i landing</h3><button type="button" class="xd-editor-close" data-xd-editor-close>&times;</button></div>
                <input type="hidden" name="block_id" data-xd-field="block_id">
                <input type="hidden" data-xd-field="locale" value="{{ app()->getLocale() }}">
                <div class="xd-editor-locale-tabs" data-xd-locale-tabs>
                    @foreach ($editorLocales as $locale)
                        <button type="button" class="xd-editor-locale-tab {{ $locale['code'] === app()->getLocale() ? 'is-active' : '' }}" data-xd-locale-tab="{{ $locale['code'] }}">
                            {{ $locale['label'] }}
                        </button>
                    @endforeach
                </div>
                <div class="xd-editor-grid">
                    <label class="xd-editor-field"><span>Anchor</span><input data-xd-field="anchor_id"></label>
                    <label class="xd-editor-field"><span>TiÃƒÂªu Ã„â€˜Ã¡Â»Â</span><input data-xd-field="title"></label>
                    <label class="xd-editor-field"><span>NhÃƒÂ£n phÃ¡Â»Â¥</span><input data-xd-field="subtitle"></label>
                    <label class="xd-editor-field is-wide"><span>MÃƒÂ´ tÃ¡ÂºÂ£</span><textarea data-xd-field="description"></textarea></label>
                    <label class="xd-editor-field"><span>NÃƒÂºt CTA</span><input data-xd-field="button_label"></label>
                    <label class="xd-editor-field"><span>Link CTA</span><input data-xd-field="cta_url" placeholder="/gioi-thieu hoÃ¡ÂºÂ·c https://..."></label>
                    <label class="xd-editor-field"><span>HiÃ¡Â»Æ’n thÃ¡Â»â€¹</span><input data-xd-field="is_visible" type="checkbox"></label>
                    <section class="xd-editor-source" data-xd-contact-editor hidden>
                        <div>
                            <h4>NÃ¡Â»â„¢i dung liÃƒÂªn hÃ¡Â»â€¡</h4>
                            <p class="xd-editor-source-note">CÃƒÂ¡c trÃ†Â°Ã¡Â»Âng nÃƒÂ y chÃ¡Â»â€° dÃƒÂ¹ng cho block liÃƒÂªn hÃ¡Â»â€¡ trÃƒÂªn landingpage.</p>
                        </div>
                        <div class="xd-editor-grid">
                            <label class="xd-editor-field"><span>TiÃƒÂªu Ã„â€˜Ã¡Â»Â form</span><input data-xd-content-field="form_title"></label>
                            <label class="xd-editor-field"><span>TiÃƒÂªu Ã„â€˜Ã¡Â»Â ghi chÃƒÂº</span><input data-xd-content-field="note_title"></label>
                            <label class="xd-editor-field is-wide"><span>NÃ¡Â»â„¢i dung ghi chÃƒÂº</span><textarea data-xd-content-field="note_text"></textarea></label>
                        </div>
                    </section>
                    <section class="xd-editor-source" data-xd-source-editor hidden>
                        <div>
                            <h4>NguÃ¡Â»â€œn nÃ¡Â»â„¢i dung</h4>
                            <p class="xd-editor-source-note">ChÃ¡Â»Ân bÃ¡ÂºÂ£ng dÃ¡Â»Â¯ liÃ¡Â»â€¡u dÃƒÂ¹ng Ã„â€˜Ã¡Â»Æ’ tÃ¡Â»Â± Ã„â€˜Ã¡Â»â„¢ng lÃ¡ÂºÂ¥y danh sÃƒÂ¡ch item cho khÃ¡Â»â€˜i nÃƒÂ y.</p>
                        </div>
                        <div class="xd-editor-source-grid">
                            <label><span>LÃ¡ÂºÂ¥y tÃ¡Â»Â« bÃ¡ÂºÂ£ng</span><select data-xd-setting-field="source"></select></label>
                            <label><span>SÃ¡Â»â€˜ lÃ†Â°Ã¡Â»Â£ng</span><input type="number" min="1" max="12" data-xd-setting-field="limit"></label>
                            <label><span>Danh mÃ¡Â»Â¥c</span><select data-xd-setting-field="category_id"></select></label>
                            <label class="xd-editor-source-check"><input type="checkbox" data-xd-setting-field="featured_only"><span>ChÃ¡Â»â€° nÃ¡Â»â€¢i bÃ¡ÂºÂ­t</span></label>
                        </div>
                    </section>
                    <section class="xd-editor-items" data-xd-items-editor hidden>
                        <div class="xd-editor-items-head">
                            <div>
                                <h4>Danh sÃƒÂ¡ch nÃ¡Â»â„¢i dung</h4>
                                <p class="xd-editor-help" data-xd-items-help>ChÃ¡Â»â€°nh tÃ¡Â»Â«ng mÃ¡Â»Â¥c bÃ¡ÂºÂ±ng form, khÃƒÂ´ng cÃ¡ÂºÂ§n nhÃ¡ÂºÂ­p JSON.</p>
                            </div>
                            <div class="xd-editor-items-actions">
                                <a class="xd-editor-manage" data-xd-manage-source href="#" target="_blank" rel="noopener" hidden>QuÃ¡ÂºÂ£n lÃƒÂ½ nÃ¡Â»â„¢i dung</a>
                                <button type="button" class="xd-editor-add" data-xd-add-item>ThÃƒÂªm mÃ¡Â»Â¥c</button>
                            </div>
                        </div>
                        <div class="xd-editor-item-list" data-xd-item-list></div>
                    </section>
                    <textarea class="xd-editor-hidden-json" data-xd-field="content" aria-hidden="true" tabindex="-1"></textarea>
                    <textarea class="xd-editor-hidden-json" data-xd-field="settings" aria-hidden="true" tabindex="-1"></textarea>
                    <textarea class="xd-editor-hidden-json" data-xd-field="media" aria-hidden="true" tabindex="-1"></textarea>
                </div>
                <div class="xd-editor-actions"><button type="button" data-xd-editor-close>HÃ¡Â»Â§y</button><button type="submit">LÃ†Â°u</button></div>
            </form>
        </div>
        <div class="xd-item-modal" data-xd-item-modal hidden>
            <form class="xd-item-card" data-xd-item-form>
                <div class="xd-item-card-head">
                    <h3 data-xd-item-title>ThÃƒÂªm mÃ¡Â»Â¥c</h3>
                    <button type="button" class="xd-item-close" data-xd-item-close>&times;</button>
                </div>
                <input type="hidden" data-xd-item-index>
                <div class="xd-item-form" data-xd-item-form-fields></div>
                <div class="xd-item-actions">
                    <button type="button" data-xd-item-close>HÃ¡Â»Â§y</button>
                    <button type="submit">LÃ†Â°u mÃ¡Â»Â¥c</button>
                </div>
            </form>
        </div>
    @endif
