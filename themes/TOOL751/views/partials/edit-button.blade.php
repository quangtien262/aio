@if($canEditLanding ?? false)
    <button type="button" class="t751-edit-block" data-landing-block-edit="{{ data_get($block, 'id') }}" aria-label="Chỉnh sửa khối"><i class="fa-solid fa-pen"></i></button>
@endif
