    @if ($canEditLanding)
        <div class="xd-editor" data-xd-editor hidden>
            <form class="xd-editor-card" data-xd-editor-form>
                <div class="xd-editor-head"><h3>Sửa khối landing</h3><button type="button" class="xd-editor-close" data-xd-editor-close>&times;</button></div>
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
                    <label class="xd-editor-field"><span>Tiêu đề</span><input data-xd-field="title"></label>
                    <label class="xd-editor-field"><span>Nhãn phụ</span><input data-xd-field="subtitle"></label>
                    <label class="xd-editor-field is-wide"><span>Mô tả</span><textarea data-xd-field="description"></textarea></label>
                    <label class="xd-editor-field" data-xd-block-cta><span>Nút CTA</span><input data-xd-field="button_label"></label>
                    <label class="xd-editor-field" data-xd-block-cta><span>Link CTA</span><input data-xd-field="cta_url" placeholder="/gioi-thieu hoặc https://..."></label>
                    <label class="xd-editor-field"><span>Hiển thị</span><input data-xd-field="is_visible" type="checkbox"></label>
                    <section class="xd-editor-source" data-xd-media-editor hidden>
                        <div>
                            <h4>Ảnh tổng hợp khối giới thiệu</h4>
                            <p class="xd-editor-source-note">Cột trái dùng duy nhất một ảnh hoàn chỉnh, bao gồm cả hình ảnh và nội dung kinh nghiệm nếu cần.</p>
                        </div>
                        <div class="xd-editor-grid">
                            <div class="xd-editor-field is-wide" data-xd-media-row>
                                <span>Ảnh giới thiệu</span>
                                <input data-xd-media-field="image" aria-label="Ảnh giới thiệu" placeholder="https://... hoặc /storage/...">
                                <div class="xd-item-upload">
                                    <input type="file" accept="image/*" data-xd-media-upload="image" hidden>
                                    <button type="button" data-xd-media-upload-trigger>Upload ảnh giới thiệu</button>
                                    <small data-xd-media-upload-status></small>
                                </div>
                            </div>
                        </div>
                    </section>
                    <section class="xd-editor-source" data-xd-contact-editor hidden>
                        <div>
                            <h4>Nội dung liên hệ</h4>
                            <p class="xd-editor-source-note">Các trường này chỉ dùng cho block liên hệ trên landingpage.</p>
                        </div>
                        <div class="xd-editor-grid">
                            <label class="xd-editor-field"><span>Tiêu đề form</span><input data-xd-content-field="form_title"></label>
                            <label class="xd-editor-field"><span>Tiêu đề ghi chú</span><input data-xd-content-field="note_title"></label>
                            <label class="xd-editor-field is-wide"><span>Nội dung ghi chú</span><textarea data-xd-content-field="note_text"></textarea></label>
                        </div>
                    </section>
                    <section class="xd-editor-source" data-xd-faq-editor hidden>
                        <div>
                            <h4>Nội dung minh họa bên phải</h4>
                            <p class="xd-editor-source-note">Chỉnh ảnh, tiêu đề, mô tả và nút tư vấn thuộc chính khối Hỏi đáp.</p>
                        </div>
                        <div class="xd-editor-grid">
                            <div class="xd-editor-field is-wide" data-xd-faq-media-row>
                                <span>Ảnh minh họa</span>
                                <input data-xd-faq-media-field="aside_image" aria-label="Ảnh minh họa FAQ" placeholder="https://... hoặc /storage/...">
                                <div class="xd-item-upload">
                                    <input type="file" accept="image/*" data-xd-faq-media-upload="aside_image" hidden>
                                    <button type="button" data-xd-faq-media-upload-trigger>Upload ảnh minh họa</button>
                                    <small data-xd-faq-media-upload-status></small>
                                </div>
                            </div>
                            <label class="xd-editor-field"><span>Tiêu đề minh họa</span><input data-xd-faq-content-field="aside_title"></label>
                            <label class="xd-editor-field"><span>Tên nút</span><input data-xd-faq-content-field="aside_button_label"></label>
                            <label class="xd-editor-field is-wide"><span>Mô tả minh họa</span><textarea data-xd-faq-content-field="aside_description"></textarea></label>
                            <label class="xd-editor-field is-wide"><span>Link nút</span><input data-xd-faq-content-field="aside_button_url" placeholder="/contact hoặc #lien-he"></label>
                        </div>
                    </section>
                    <section class="xd-editor-source" data-xd-source-editor hidden>
                        <div>
                            <h4>Nguồn nội dung</h4>
                            <p class="xd-editor-source-note">Chọn bảng dữ liệu dùng để tự động lấy danh sách item cho khối này.</p>
                        </div>
                        <div class="xd-editor-source-grid">
                            <label><span>Lấy từ bảng</span><select data-xd-setting-field="source"></select></label>
                            <label><span>Vị trí menu</span><input type="text" data-xd-setting-field="menu_location" placeholder="primary-navigation"></label>
                            <label><span>Số lượng</span><input type="number" min="1" max="12" data-xd-setting-field="limit"></label>
                            <label><span>Danh mục</span><select data-xd-setting-field="category_id"></select></label>
                            <label class="xd-editor-source-check"><input type="checkbox" data-xd-setting-field="featured_only"><span>Chỉ nổi bật</span></label>
                        </div>
                    </section>
                    <section class="xd-editor-items" data-xd-items-editor hidden>
                        <div class="xd-editor-items-head">
                            <div>
                                <h4 data-xd-items-title>Danh sách nội dung</h4>
                                <p class="xd-editor-help" data-xd-items-help>Chỉnh từng mục bằng form, không cần nhập JSON.</p>
                            </div>
                            <div class="xd-editor-items-actions">
                                <a class="xd-editor-manage" data-xd-manage-source href="#" target="_blank" rel="noopener" hidden>Quản lý nội dung</a>
                                <button type="button" class="xd-editor-add" data-xd-add-item>Thêm mục</button>
                            </div>
                        </div>
                        <div class="xd-editor-item-list" data-xd-item-list></div>
                    </section>
                    <textarea class="xd-editor-hidden-json" data-xd-field="content" aria-hidden="true" tabindex="-1"></textarea>
                    <textarea class="xd-editor-hidden-json" data-xd-field="settings" aria-hidden="true" tabindex="-1"></textarea>
                    <textarea class="xd-editor-hidden-json" data-xd-field="media" aria-hidden="true" tabindex="-1"></textarea>
                </div>
                <div class="xd-editor-actions"><button type="button" data-xd-editor-close>Hủy</button><button type="submit">Lưu</button></div>
            </form>
        </div>
        <div class="xd-item-modal" data-xd-item-modal hidden>
            <form class="xd-item-card" data-xd-item-form>
                <div class="xd-item-card-head">
                    <h3 data-xd-item-title>Thêm mục</h3>
                    <button type="button" class="xd-item-close" data-xd-item-close>&times;</button>
                </div>
                <input type="hidden" data-xd-item-index>
                <div class="xd-item-form" data-xd-item-form-fields></div>
                <div class="xd-item-actions">
                    <button type="button" data-xd-item-close>Hủy</button>
                    <button type="submit">Lưu mục</button>
                </div>
            </form>
        </div>
    @endif
