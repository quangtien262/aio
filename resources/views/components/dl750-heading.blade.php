@props(['block', 'align' => 'center', 'light' => false])
<header class="dl-heading {{ $align === 'left' ? 'left' : '' }} {{ $light ? 'light' : '' }}"><span>FOREST CAMP</span><h2>{{ data_get($block, 'data.title') }}</h2>@if(data_get($block, 'data.subtitle'))<p>{{ data_get($block, 'data.subtitle') }}</p>@endif</header>
