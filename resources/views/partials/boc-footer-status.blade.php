@php
    $bocBranding = (array) ($branding ?? []);
    $bocStatus = trim((string) data_get($bocBranding, 'boc_status', 'not_notified'));
    $bocConfirmationUrl = trim((string) data_get($bocBranding, 'boc_confirmation_url', ''));
    $bocFooterNote = trim((string) data_get($bocBranding, 'boc_footer_note', '')) ?: 'Website đang chờ khai báo Bộ Công Thương';
    $bocClass = trim((string) ($class ?? 'footer-boc-status'));
    $bocStyle = trim((string) ($style ?? 'display:inline-flex;margin-top:14px;color:inherit;font-size:13px;line-height:1.5;'));
@endphp

@if ($bocStatus === 'notified' && $bocConfirmationUrl !== '')
    <a class="{{ $bocClass }}" style="{{ $bocStyle }}" href="{{ $bocConfirmationUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Đã thông báo Bộ Công Thương">
        <img src="/img/dathongbao-bo-cong-thuong.png" alt="Đã thông báo Bộ Công Thương" style="display:block;width:min(210px,100%);height:auto;">
    </a>
@elseif ($bocStatus === 'pending')
    <p class="{{ $bocClass }}" style="{{ $bocStyle }}"><em>{{ $bocFooterNote }}</em></p>
@endif
