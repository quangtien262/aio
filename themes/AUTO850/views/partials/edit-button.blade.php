@if($canEditLanding ?? false)
    <button type="button" class="a850-edit-block" data-landing-block-edit="{{ data_get($block, 'id') }}" aria-label="Chỉnh sửa khối"><i class="fa-solid fa-pen"></i></button>
@endif
