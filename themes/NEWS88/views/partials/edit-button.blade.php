@if(($canEditLanding ?? false) && filled(data_get($block ?? [], 'id')))
    <button type="button" class="xd-landing-edit-button n88-edit" data-xd-edit-block="{{ data_get($block, 'id') }}"><i class="fa-solid fa-pen"></i><span>Sửa khối</span></button>
@endif
