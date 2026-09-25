@php
    $items = collect($products ?? []);
    $categories = collect($searchCategories ?? []);
    $filters = (array) ($searchFilters ?? []);
    $query = (string) ($searchQuery ?? '');
    $currentCategory = (string) ($filters['category'] ?? '');
    $formatPrice = fn ($value) => is_numeric($value) && (float) $value > 0 ? number_format((float) $value, 0, ',', '.').'đ' : 'Liên hệ';
@endphp
@extends('theme-foot403::layout')
@section('title', ($query !== '' ? 'Tìm kiếm: '.$query : 'Tìm kiếm món ăn').'')
@push('head')
<style>
.dr-catalog-page{min-height:70vh;padding:145px 0 90px;background:#f6f1e8;color:#173d37}.dr-catalog-head{display:flex;justify-content:space-between;gap:30px;align-items:end;margin-bottom:34px}.dr-catalog-head span{color:#b47827;text-transform:uppercase;letter-spacing:2px;font-weight:700}.dr-catalog-head h1{margin:8px 0 0;font-size:clamp(34px,5vw,58px);font-family:'Dancing Script',cursive}.dr-search-form{display:flex;width:min(500px,100%);background:#fff;border:1px solid #dbcdb8;border-radius:10px;overflow:hidden}.dr-search-form input{flex:1;min-width:0;border:0;padding:15px 17px;font:inherit}.dr-search-form button{border:0;background:var(--dr-gold);color:#fff;padding:0 24px;font-weight:700}.dr-catalog-shell{display:grid;grid-template-columns:250px 1fr;gap:30px}.dr-filter{background:#fff;padding:24px;border-radius:12px;height:max-content;box-shadow:0 12px 35px rgba(42,34,19,.08)}.dr-filter h2{margin:0 0 18px}.dr-filter label{display:block;font-size:13px;font-weight:700;margin:18px 0 7px}.dr-filter select{width:100%;height:44px;border:1px solid #dbcdb8;border-radius:7px;padding:0 10px;background:#fff}.dr-filter button{width:100%;margin-top:20px;border:0;border-radius:7px;background:var(--dr-green);color:#fff;padding:13px;font-weight:700}.dr-filter__links{display:grid;gap:8px;margin-top:22px}.dr-filter__links a{padding:10px 12px;border-radius:7px;background:#f7f3ec}.dr-filter__links a.is-active,.dr-filter__links a:hover{background:var(--dr-gold);color:#fff}.dr-result-meta{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;color:#65756f}.dr-search-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:22px}.dr-search-card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 12px 30px rgba(42,34,19,.08)}.dr-search-card img{height:230px}.dr-search-card__body{padding:18px}.dr-search-card h3{margin:0 0 12px;font-size:17px;line-height:1.45}.dr-search-card strong{color:#dc3f3f;font-size:19px}.dr-search-card del{color:#999;margin-left:8px;font-size:12px}.dr-search-card small{display:block;color:#b47827;margin-bottom:8px}.dr-catalog-empty{padding:55px;text-align:center;background:#fff;border-radius:12px}.dr-pagination{margin-top:30px}.dr-pagination nav{display:flex;justify-content:center}.dr-pagination svg{width:18px}.dr-pagination p{display:none}@media(max-width:900px){.dr-catalog-shell{grid-template-columns:1fr}.dr-search-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:620px){.dr-catalog-page{padding-top:115px}.dr-catalog-head{display:grid}.dr-search-form{width:100%}.dr-search-grid{grid-template-columns:1fr}.dr-search-card img{height:260px}}
</style>
@endpush
@section('content')
@include('themes.common.catalog-listing', ['catalogMode' => 'search'])
@endsection
