    @if ($canEditLanding)
        <div class="xd-editor" data-xd-editor hidden>
            <form class="xd-editor-card" data-xd-editor-form>
                <div class="xd-editor-head"><h3>Sá»­a khá»‘i landing</h3><button type="button" class="xd-editor-close" data-xd-editor-close>&times;</button></div>
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
                    <label class="xd-editor-field"><span>TiÃªu Ä‘á»</span><input data-xd-field="title"></label>
                    <label class="xd-editor-field"><span>NhÃ£n phá»¥</span><input data-xd-field="subtitle"></label>
                    <label class="xd-editor-field is-wide"><span>MÃ´ táº£</span><textarea data-xd-field="description"></textarea></label>
                    <label class="xd-editor-field"><span>NÃºt CTA</span><input data-xd-field="button_label"></label>
                    <label class="xd-editor-field"><span>Link CTA</span><input data-xd-field="cta_url" placeholder="/p/gioi-thieu hoáº·c https://..."></label>
                    <label class="xd-editor-field"><span>Hiá»ƒn thá»‹</span><input data-xd-field="is_visible" type="checkbox"></label>
                    <section class="xd-editor-source" data-xd-contact-editor hidden>
                        <div>
                            <h4>Ná»™i dung liÃªn há»‡</h4>
                            <p class="xd-editor-source-note">CÃ¡c trÆ°á»ng nÃ y chá»‰ dÃ¹ng cho block liÃªn há»‡ trÃªn landingpage.</p>
                        </div>
                        <div class="xd-editor-grid">
                            <label class="xd-editor-field"><span>TiÃªu Ä‘á» form</span><input data-xd-content-field="form_title"></label>
                            <label class="xd-editor-field"><span>TiÃªu Ä‘á» ghi chÃº</span><input data-xd-content-field="note_title"></label>
                            <label class="xd-editor-field is-wide"><span>Ná»™i dung ghi chÃº</span><textarea data-xd-content-field="note_text"></textarea></label>
                        </div>
                    </section>
                    <section class="xd-editor-source" data-xd-source-editor hidden>
                        <div>
                            <h4>Nguá»“n ná»™i dung</h4>
                            <p class="xd-editor-source-note">Chá»n báº£ng dá»¯ liá»‡u dÃ¹ng Ä‘á»ƒ tá»± Ä‘á»™ng láº¥y danh sÃ¡ch item cho khá»‘i nÃ y.</p>
                        </div>
                        <div class="xd-editor-source-grid">
                            <label><span>Láº¥y tá»« báº£ng</span><select data-xd-setting-field="source"></select></label>
                            <label><span>Sá»‘ lÆ°á»£ng</span><input type="number" min="1" max="12" data-xd-setting-field="limit"></label>
                            <label><span>Danh má»¥c</span><select data-xd-setting-field="category_id"></select></label>
                            <label class="xd-editor-source-check"><input type="checkbox" data-xd-setting-field="featured_only"><span>Chá»‰ ná»•i báº­t</span></label>
                        </div>
                    </section>
                    <section class="xd-editor-items" data-xd-items-editor hidden>
                        <div class="xd-editor-items-head">
                            <div>
                                <h4>Danh sÃ¡ch ná»™i dung</h4>
                                <p class="xd-editor-help" data-xd-items-help>Chá»‰nh tá»«ng má»¥c báº±ng form, khÃ´ng cáº§n nháº­p JSON.</p>
                            </div>
                            <div class="xd-editor-items-actions">
                                <a class="xd-editor-manage" data-xd-manage-source href="#" target="_blank" rel="noopener" hidden>Quáº£n lÃ½ ná»™i dung</a>
                                <button type="button" class="xd-editor-add" data-xd-add-item>ThÃªm má»¥c</button>
                            </div>
                        </div>
                        <div class="xd-editor-item-list" data-xd-item-list></div>
                    </section>
                    <textarea class="xd-editor-hidden-json" data-xd-field="content" aria-hidden="true" tabindex="-1"></textarea>
                    <textarea class="xd-editor-hidden-json" data-xd-field="settings" aria-hidden="true" tabindex="-1"></textarea>
                    <textarea class="xd-editor-hidden-json" data-xd-field="media" aria-hidden="true" tabindex="-1"></textarea>
                </div>
                <div class="xd-editor-actions"><button type="button" data-xd-editor-close>Há»§y</button><button type="submit">LÆ°u</button></div>
            </form>
        </div>
        <div class="xd-item-modal" data-xd-item-modal hidden>
            <form class="xd-item-card" data-xd-item-form>
                <div class="xd-item-card-head">
                    <h3 data-xd-item-title>ThÃªm má»¥c</h3>
                    <button type="button" class="xd-item-close" data-xd-item-close>&times;</button>
                </div>
                <input type="hidden" data-xd-item-index>
                <div class="xd-item-form" data-xd-item-form-fields></div>
                <div class="xd-item-actions">
                    <button type="button" data-xd-item-close>Há»§y</button>
                    <button type="submit">LÆ°u má»¥c</button>
                </div>
            </form>
        </div>
    @endif

