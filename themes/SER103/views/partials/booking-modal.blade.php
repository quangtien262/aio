@php $customer = auth('customer')->user(); @endphp
<div class="ser103-booking" data-ser103-booking hidden>
    <button class="ser103-booking__backdrop" type="button" data-ser103-booking-close aria-label="Đóng"></button>
    <div class="ser103-booking__dialog" role="dialog" aria-modal="true" aria-labelledby="ser103-booking-title">
        <button class="ser103-booking__close" type="button" data-ser103-booking-close aria-label="Đóng">&times;</button>
        <section class="ser103-booking__form-panel">
            <div class="ser103-booking__heading"><i class="fa-regular fa-calendar-check"></i><div><h2 id="ser103-booking-title">Đặt lịch tư vấn cưới</h2><p>Chia sẻ mong muốn để Bøhu chuẩn bị buổi hẹn phù hợp.</p></div></div>
            <form data-ser103-booking-form novalidate>
                <input type="hidden" name="source" value="quote_modal"><input type="hidden" name="subject" value="Yêu cầu đặt lịch tư vấn cưới SER103"><input type="hidden" name="message" data-ser103-booking-message>
                <div class="ser103-booking__fields">
                    <label class="ser103-field is-wide"><span>Dịch vụ quan tâm *</span><select data-ser103-service required><option value="">Chọn dịch vụ</option><option>Wedding planning trọn gói</option><option>Trang điểm cô dâu</option><option>Quay phim - chụp ảnh</option><option>Trang trí tiệc cưới</option><option>Thuê xe cưới</option></select><small data-error="service"></small></label>
                    <label class="ser103-field"><span>Ngày hẹn *</span><input type="date" data-ser103-date required><small data-error="date"></small></label><label class="ser103-field"><span>Giờ hẹn *</span><input type="time" data-ser103-time required><small data-error="time"></small></label>
                    <label class="ser103-field"><span>Họ và tên *</span><input name="name" value="{{ $customer?->name }}" required><small data-error="name"></small></label><label class="ser103-field"><span>Số điện thoại *</span><input name="phone" value="{{ $customer?->phone }}" required><small data-error="phone"></small></label>
                    <label class="ser103-field is-wide"><span>Email *</span><input type="email" name="email" value="{{ $customer?->email }}" required><small data-error="email"></small></label><label class="ser103-field is-wide"><span>Điều bạn đang mong đợi</span><textarea data-ser103-note rows="3" maxlength="500" placeholder="Ngày cưới dự kiến, phong cách, số khách..."></textarea></label>
                </div>
                <button class="ser103-booking__submit" type="submit">Gửi yêu cầu <i class="fa-solid fa-arrow-right-long"></i></button>
                <p class="ser103-booking__feedback" data-ser103-booking-feedback hidden></p>
            </form>
        </section>
        <aside class="ser103-booking__visual"><img src="/theme-previews/SER103/service-planning.webp" alt="Wedding planner"><div><span>Bøhu Wedding</span><h3>Ngày của bạn,<br>câu chuyện của bạn.</h3><p>Mỗi chi tiết đều được chăm chút bằng sự thấu hiểu và gu thẩm mỹ tinh tế.</p></div></aside>
    </div>
</div>
