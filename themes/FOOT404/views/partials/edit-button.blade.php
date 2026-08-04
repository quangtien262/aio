@if(auth('admin')->check() && request('mod') === 'admin' && filled(data_get($block ?? [], 'id')))
    <button class="f404-edit" type="button" data-xd-edit-block="{{ data_get($block, 'id') }}"><i class="fa-solid fa-pen"></i> Sửa khối</button>
@endif
