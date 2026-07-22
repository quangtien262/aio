@if(($canEditLanding ?? false) && filled(data_get($block ?? [], 'id')))
    <button type="button" class="xd-edit-block" data-xd-edit-block="{{ data_get($block, 'id') }}">Sửa khối</button>
@endif
