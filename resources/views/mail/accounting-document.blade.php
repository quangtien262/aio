<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $snapshot['document']['document_no'] ?? 'Chứng từ kế toán' }}</title>
</head>
<body style="margin:0;background:#f4f7f6;color:#17201f;font-family:Arial,sans-serif;">
<div style="max-width:720px;margin:0 auto;padding:28px 16px;">
    <div style="background:#fff;border:1px solid #dfe8e5;border-radius:14px;padding:28px;">
        <p style="margin:0 0 8px;color:#0f766e;font-weight:700;">{{ $snapshot['organization']['legal_name'] ?? $snapshot['organization']['name'] ?? config('app.name') }}</p>
        <h1 style="margin:0 0 20px;font-size:24px;">Hóa đơn / chứng từ {{ $snapshot['document']['document_no'] ?? ('#'.($snapshot['document']['id'] ?? '')) }}</h1>

        <p>Kính gửi {{ $snapshot['party']['name'] ?? 'Quý khách' }},</p>
        <p>Chúng tôi gửi thông tin chứng từ và các file đối chiếu kèm theo email này.</p>

        <table style="width:100%;border-collapse:collapse;margin:22px 0;">
            <tr><td style="padding:8px;border-bottom:1px solid #e8eeec;">Ngày chứng từ</td><td style="padding:8px;border-bottom:1px solid #e8eeec;text-align:right;">{{ $snapshot['document']['document_date'] ?? '-' }}</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #e8eeec;">Tiền trước thuế</td><td style="padding:8px;border-bottom:1px solid #e8eeec;text-align:right;">{{ number_format((float) ($snapshot['document']['subtotal'] ?? 0), 0, ',', '.') }} {{ $snapshot['document']['currency'] ?? 'VND' }}</td></tr>
            <tr><td style="padding:8px;border-bottom:1px solid #e8eeec;">Tiền thuế</td><td style="padding:8px;border-bottom:1px solid #e8eeec;text-align:right;">{{ number_format((float) ($snapshot['document']['tax_total'] ?? 0), 0, ',', '.') }} {{ $snapshot['document']['currency'] ?? 'VND' }}</td></tr>
            <tr><td style="padding:10px 8px;font-weight:700;">Tổng thanh toán</td><td style="padding:10px 8px;text-align:right;font-weight:700;color:#0f766e;">{{ number_format((float) ($snapshot['document']['grand_total'] ?? 0), 0, ',', '.') }} {{ $snapshot['document']['currency'] ?? 'VND' }}</td></tr>
        </table>

        <p style="font-size:13px;color:#5f6e6b;">Email được tạo từ ảnh chụp dữ liệu tại {{ $snapshot['captured_at'] ?? '-' }}. Vui lòng dùng XML/PDF do nhà cung cấp hóa đơn điện tử phát hành làm chứng từ pháp lý khi có.</p>
    </div>
</div>
</body>
</html>
