@php
    $isProjectListing = $isProjectListing ?? false;
    $listingTextKey = $isProjectListing ? 'projects' : 'services';
    $listingTitleId = $isProjectListing ? 'ser-project-title' : 'ser-service-title';
@endphp
<style>
.ser-service-listing{padding:28px 0 64px;color:var(--ser-ink)}
.ser-service-breadcrumb{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:24px;font-size:13px;color:var(--ser-muted)}
.ser-service-heading{display:flex;align-items:center;justify-content:space-between;gap:32px;padding:32px 36px;margin-bottom:32px;border:1px solid var(--ser-line);border-radius:20px;background:linear-gradient(120deg,var(--ser-surface),var(--ser-mist));border-left:5px solid var(--ser-accent)}
.ser-service-heading>div{max-width:720px;min-width:0}.ser-service-eyebrow{font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--ser-primary)}
.ser-service-heading h1{font-size:clamp(30px,3vw,44px);font-weight:800;line-height:1.2;letter-spacing:-.03em;color:var(--ser-navy);margin:10px 0 16px}
.ser-service-heading p{margin:0;font-size:16px;line-height:1.8;color:var(--ser-muted)}
.ser-service-consult{display:inline-flex;align-items:center;justify-content:center;gap:16px;flex-shrink:0;border-radius:10px;padding:14px 20px;background:var(--ser-navy);color:#fff;font-size:14px;font-weight:700}
.ser-service-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}
.ser-service-card{display:flex;flex-direction:column;min-width:0;background:var(--ser-surface);border:1px solid var(--ser-line);border-radius:16px;overflow:hidden;box-shadow:0 8px 24px #102a4308;transition:box-shadow .2s,border-color .2s}
.ser-service-card:hover{border-color:var(--ser-primary);box-shadow:0 12px 32px #102a4314}
.ser-service-image{display:block;overflow:hidden;background:var(--ser-mist)}.ser-service-image img{display:block;width:100%;aspect-ratio:16/10;object-fit:cover}
.ser-service-body{display:flex;flex-direction:column;flex:1;padding:24px}
.ser-service-body h2{margin:0 0 12px;font-size:22px;font-weight:750;line-height:1.4;color:var(--ser-navy);overflow-wrap:anywhere}
.ser-service-body p{margin:0 0 22px;font-size:15px;line-height:1.8;color:var(--ser-muted);overflow-wrap:anywhere}
.ser-service-more{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:auto;padding-top:16px;border-top:1px solid var(--ser-line);font-size:14px;font-weight:700;color:var(--ser-primary)}
.ser-service-listing a:focus-visible{outline:3px solid var(--ser-accent);outline-offset:4px}.ser-service-empty{padding:32px;border:1px dashed var(--ser-line);border-radius:16px;background:#fff;color:var(--ser-muted)}
.ser-service-pagination{margin-top:32px}
@media(max-width:1000px){.ser-service-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.ser-service-heading{align-items:flex-start;flex-direction:column;gap:20px}}
@media(max-width:600px){.ser-service-listing{padding-top:20px}.ser-service-heading{padding:24px 20px;border-radius:14px}.ser-service-grid{grid-template-columns:minmax(0,1fr);gap:20px}.ser-service-body{padding:22px}.ser-service-consult{width:100%}}
</style>
<section class="ser-service-listing" aria-labelledby="{{ $listingTitleId }}">
    <nav class="ser-service-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('site.home') }}">{{ $t('services.home', 'Trang chủ') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $pageTitle }}</span></nav>
    <header class="ser-service-heading">
        <div><span class="ser-service-eyebrow">{{ $t($listingTextKey.'.eyebrow', $isProjectListing ? 'Hành trình & giải pháp' : 'Đồng hành cùng hành trình của bạn') }}</span><h1 id="{{ $listingTitleId }}">{{ $pageTitle }}</h1><p>{{ $t($listingTextKey.'.intro', $isProjectListing ? 'Khám phá các phương án triển khai, từ đưa đón sân bay đến vận chuyển doanh nghiệp. Mỗi dự án trình bày nhu cầu, cách tổ chức và các hạng mục bàn giao.' : 'Khám phá dịch vụ phù hợp với nhu cầu của bạn. Trao đổi cùng đội ngũ tư vấn để lựa chọn phương án và thống nhất lịch trình.') }}</p></div>
        <a class="ser-service-consult" href="{{ route('site.contact') }}">{{ $t('services.consult', 'Nhận tư vấn') }} <span aria-hidden="true">↗</span></a>
    </header>
    <div class="ser-service-grid">
        @forelse($listingItems as $item)
            @php($serviceUrl = route($isProjectListing ? 'site.projects.show' : 'site.services.show', ['slug' => $item->slug]))
            <article class="ser-service-card">
                @if($item->featuredImage?->image_url)<a class="ser-service-image" href="{{ $serviceUrl }}" aria-label="{{ $item->title }}"><img src="{{ $item->featuredImage->image_url }}" alt="" loading="lazy"></a>@endif
                <div class="ser-service-body"><h2><a href="{{ $serviceUrl }}">{{ $item->title }}</a></h2>@if(filled($item->summary))<p>{{ strip_tags($item->summary) }}</p>@endif<a class="ser-service-more" href="{{ $serviceUrl }}">{{ $t($listingTextKey.'.details', $isProjectListing ? 'Xem dự án' : 'Khám phá dịch vụ') }}<span aria-hidden="true">→</span></a></div>
            </article>
        @empty
            <p class="ser-service-empty">{{ $t($listingTextKey.'.empty', $isProjectListing ? 'Dự án đang được cập nhật. Vui lòng liên hệ để trao đổi nhu cầu của bạn.' : 'Chưa có dịch vụ phù hợp. Vui lòng liên hệ để được tư vấn thêm.') }}</p>
        @endforelse
    </div>
    @if(method_exists($listingItems, 'links'))<div class="ser-service-pagination">{{ $listingItems->links() }}</div>@endif
</section>
