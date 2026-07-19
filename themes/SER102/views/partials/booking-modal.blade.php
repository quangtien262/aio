@php
    $branding = data_get($siteProfile ?? [], 'branding', []);
    $hotline = data_get($branding, 'support_hotline', data_get($siteProfile ?? [], 'hotline', '1900 6750'));
    $customer = auth('customer')->user();
@endphp
<div class="ser102-booking" data-ser102-booking hidden>
    <button class="ser102-booking__backdrop" type="button" data-ser102-booking-close aria-label="Đóng"></button>
    <div class="ser102-booking__dialog" role="dialog" aria-modal="true" aria-labelledby="ser102-booking-title">
        <button class="ser102-booking__close" type="button" data-ser102-booking-close aria-label="Đóng">&times;</button>
        <section class="ser102-booking__form-panel">
            <div class="ser102-booking__heading">
                <i class="fa-regular fa-calendar-check"></i>
                <div><h2 id="ser102-booking-title">@themeT('SER102.booking.title')</h2><p>@themeT('SER102.booking.subtitle')</p></div>
            </div>
            <form data-ser102-booking-form novalidate>
                <input type="hidden" name="source" value="quote_modal">
                <input type="hidden" name="subject" value="Yêu cầu đặt lịch dịch vụ SER102">
                <input type="hidden" name="route_summary" value="Đặt lịch tại trung tâm">
                <input type="hidden" name="message" data-ser102-booking-message>
                <div class="ser102-booking__section"><strong>1. Chọn dịch vụ</strong>
                    <label class="ser102-field is-wide"><span>Dịch vụ quan tâm *</span><select data-ser102-service required><option value="">Chọn dịch vụ</option><option>Rửa xe cao cấp</option><option>Chăm sóc nội thất</option><option>Hiệu chỉnh sơn</option><option>Phủ ceramic</option><option>Dán phim bảo vệ</option><option value="Dịch vụ khác">Dịch vụ khác</option></select><small data-error="service"></small></label>
                </div>
                <div class="ser102-booking__section"><strong>2. Chọn ngày & giờ</strong><div class="ser102-booking__fields"><label class="ser102-field"><span>Ngày hẹn *</span><input type="date" data-ser102-date required><small data-error="date"></small></label><label class="ser102-field"><span>Giờ hẹn *</span><input type="time" data-ser102-time required><small data-error="time"></small></label></div></div>
                <div class="ser102-booking__section"><strong>3. Thông tin khách hàng</strong><div class="ser102-booking__fields"><label class="ser102-field"><span>Họ và tên *</span><input name="name" value="{{ $customer?->name }}" required><small data-error="name"></small></label><label class="ser102-field"><span>Số điện thoại *</span><input name="phone" value="{{ $customer?->phone }}" required><small data-error="phone"></small></label><label class="ser102-field is-wide"><span>Email *</span><input type="email" name="email" value="{{ $customer?->email }}" required><small data-error="email"></small></label><label class="ser102-field is-wide"><span>Yêu cầu đặc biệt</span><textarea data-ser102-note rows="3" maxlength="500" placeholder="Tình trạng xe, dòng xe hoặc lưu ý khác..."></textarea></label></div></div>
                <div class="ser102-booking__privacy"><i class="fa-solid fa-shield-halved"></i><span><strong>Thông tin của bạn được bảo mật</strong><small>Chỉ dùng để xác nhận và phục vụ lịch hẹn.</small></span></div>
                <button class="ser102-booking__submit" type="submit"><i class="fa-regular fa-calendar-check"></i> @themeT('SER102.booking.submit')</button>
                <p class="ser102-booking__feedback" data-ser102-booking-feedback hidden></p>
            </form>
        </section>
        <aside class="ser102-booking__visual">
            <img src="/theme-previews/SER102/appointment.png" alt="Kỹ thuật viên chăm sóc xe">
            <div class="ser102-booking__benefits"><h3>Vì sao chọn <span>SER102?</span></h3><div><i class="fa-regular fa-user"></i><span><strong>Kỹ thuật viên chuyên nghiệp</strong><small>Đào tạo bài bản, giàu kinh nghiệm.</small></span></div><div><i class="fa-solid fa-flask"></i><span><strong>Sản phẩm chính hãng</strong><small>Dung dịch và thiết bị cao cấp.</small></span></div><div><i class="fa-solid fa-arrows-rotate"></i><span><strong>Quy trình tiêu chuẩn</strong><small>Kiểm soát chất lượng ở từng bước.</small></span></div><div><i class="fa-solid fa-shield-halved"></i><span><strong>Bảo hành dài hạn</strong><small>An tâm sử dụng dịch vụ.</small></span></div><a href="tel:{{ preg_replace('/\D+/', '', $hotline) }}"><small>Cần hỗ trợ?</small><strong>{{ $hotline }}</strong></a></div>
        </aside>
    </div>
</div>
