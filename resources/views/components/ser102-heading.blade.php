@props(['heading' => []])

<div class="ser102-heading">
    @if(filled($heading['kicker'] ?? null))<span>{{ $heading['kicker'] }}</span>@endif
    <h2>{{ $heading['title'] ?? '' }}</h2>
    @if(filled($heading['summary'] ?? null))<p>{{ $heading['summary'] }}</p>@endif
</div>
