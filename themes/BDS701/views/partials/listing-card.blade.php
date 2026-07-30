@php
    $isArray = is_array($item);
    $value = fn ($key, $default = null) => $isArray ? data_get($item, $key, $default) : data_get($item, $key, $default);
    $imageUrl = $value('image') ?: $value('image_url') ?: data_get($item, 'media.0.media_url');
    $url = $value('url') ?: (filled($value('slug')) ? route('site.real-estate.show', ['locale' => app()->getLocale(), 'slug' => $value('slug')]) : '#');
    $transaction = $value('transaction_type', 'sale');
    $price = $value('price');
    $location = $value('location') ?: collect([$value('ward'), $value('district'), $value('province')])->filter()->implode(', ');
    $area = $value('area') ?: $value('floor_area') ?: $value('land_area');
@endphp
<article class="bds-property-card">
    <a class="bds-property-media" href="{{ $url }}">
        <img src="{{ $imageUrl ?: 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=85' }}" alt="{{ $value('title') }}">
        <span class="bds-status {{ $transaction === 'rent' ? 'rent' : '' }}">{{ $transaction === 'rent' ? app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('BDS701', app()->getLocale(), 'rent', 'Cho thuê') : app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('BDS701', app()->getLocale(), 'sale', 'Bán') }}</span>
        @if($value('is_hot'))<span class="bds-hot">Hot</span>@endif
        @if($value('virtual_tour_url'))<span class="bds-tour">360°</span>@endif
    </a>
    <div class="bds-property-body">
        <h3><a href="{{ $url }}">{{ $value('title') }}</a></h3>
        <p class="bds-location"><i class="fa-solid fa-location-dot"></i> {{ $location ?: app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('BDS701', app()->getLocale(), 'listing.location_updating', 'Đang cập nhật vị trí') }}</p>
        <p class="bds-price"><i class="fa-solid fa-phone"></i> {{ $price ? number_format((float) $price, 0, ',', '.').' '.$value('currency', 'VND') : app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('BDS701', app()->getLocale(), 'listing.contact_price', 'Liên hệ') }}@if($value('price_unit') === 'tháng')<small>@themeT('listing.per_month', '/Tháng')</small>@endif</p>
    </div>
    <div class="bds-property-specs">
        <span><i class="fa-solid fa-bed"></i> {{ $value('bedrooms', 0) }} @themeT('listing.bed', 'Ngủ')</span>
        <span><i class="fa-solid fa-bath"></i> {{ $value('bathrooms', 0) }} @themeT('listing.bath', 'Tắm')</span>
        <span><i class="fa-regular fa-square"></i> {{ $area ? rtrim(rtrim(number_format((float) $area, 2, '.', ''), '0'), '.') : 0 }}m²</span>
    </div>
</article>
