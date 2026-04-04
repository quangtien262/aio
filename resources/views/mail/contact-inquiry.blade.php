@php
    $companyName = $branding['company_name'] ?? config('app.name', 'AIO Website');
    $supportEmail = $branding['support_email'] ?? config('mail.from.address', 'cs@aio.local');
    $supportHotline = $branding['support_hotline'] ?? '1900 6760';
@endphp
<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="utf-8">
        <title>Liên hệ mới</title>
    </head>
    <body style="margin:0; padding:24px; background:#f5f5f7; font-family:Segoe UI, Arial, sans-serif; color:#1f2937;">
        <table role="presentation" style="width:100%; max-width:720px; margin:0 auto; border-collapse:collapse; background:#ffffff; border-radius:18px; overflow:hidden;">
            <tr>
                <td style="padding:24px 28px; background:#ef2b2d; color:#ffffff;">
                    <div style="font-size:12px; letter-spacing:.12em; text-transform:uppercase; opacity:.92;">Contact Inquiry</div>
                    <h1 style="margin:10px 0 0; font-size:28px; line-height:1.2;">Yêu cầu liên hệ mới từ website</h1>
                </td>
            </tr>
            <tr>
                <td style="padding:28px;">
                    <p style="margin:0 0 18px; line-height:1.7;">Bạn vừa nhận được một yêu cầu liên hệ mới từ khách truy cập trên <strong>{{ $companyName }}</strong>.</p>

                    <table role="presentation" style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td style="width:160px; padding:10px 0; color:#6b7280;">Họ và tên</td>
                            <td style="padding:10px 0; font-weight:700;">{{ $payload['name'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td style="padding:10px 0; color:#6b7280;">Email</td>
                            <td style="padding:10px 0; font-weight:700;">{{ $payload['email'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td style="padding:10px 0; color:#6b7280;">Số điện thoại</td>
                            <td style="padding:10px 0; font-weight:700;">{{ $payload['phone'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td style="padding:10px 0; color:#6b7280;">Chủ đề</td>
                            <td style="padding:10px 0; font-weight:700;">{{ $payload['subject'] ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td style="padding:10px 0; color:#6b7280; vertical-align:top;">Nội dung</td>
                            <td style="padding:10px 0; line-height:1.8;">{!! nl2br(e($payload['message'] ?? '-')) !!}</td>
                        </tr>
                    </table>

                    <div style="margin-top:24px; padding:18px; border-radius:16px; background:#fff6f6; border:1px solid #ffd7d7;">
                        <div style="font-weight:700; margin-bottom:8px;">Thông tin hệ thống</div>
                        <div style="line-height:1.7; color:#4b5563;">Gửi lúc: {{ $payload['submitted_at'] ?? now()->toDateTimeString() }}</div>
                        <div style="line-height:1.7; color:#4b5563;">Trang gửi: {{ $payload['page_url'] ?? '-' }}</div>
                    </div>

                    <p style="margin:24px 0 0; color:#6b7280; line-height:1.7;">Phản hồi khách qua email này hoặc liên hệ trực tiếp {{ $supportHotline }}. Mail hỗ trợ hiện tại: {{ $supportEmail }}.</p>
                </td>
            </tr>
        </table>
    </body>
</html>
