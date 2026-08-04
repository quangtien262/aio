@php
    $url = data_get($item, 'url', '#'); $img = data_get($item, 'image', data_get($item, 'image_url'));
    $area = data_get($item, 'area'); $location = data_get($item, 'location');
@endphp
<article class="b702-project-card {{ ($compact ?? false) ? 'compact' : '' }}"><a class="b702-project-image" href="{{ $url }}"><img src="{{ $img ?: 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1000&q=85' }}" alt="{{ data_get($item, 'title') }}"></a><div><h3><a href="{{ $url }}">{{ data_get($item, 'title') }}</a></h3><p><i class="fa-solid fa-location-dot"></i>{{ $location ?: 'Đang cập nhật vị trí' }}</p><div class="b702-spec"><span>Diện tích: {{ $area ?: '—' }}{{ $area ? 'm²' : '' }}</span><span>Phòng ngủ: {{ data_get($item, 'bedrooms', '—') }}</span><span>Phòng tắm: {{ data_get($item, 'bathrooms', '—') }}</span><span>{{ data_get($item, 'property_type', 'Bất động sản') }}</span></div></div></article>
