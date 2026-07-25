@if($canEditLanding ?? false)
<button class="ca50-edit-block" type="button" data-xd-edit-block="{{ data_get($block ?? [], 'id') }}"><i class="fa-solid fa-pen"></i> Sửa khối</button>
@endif
