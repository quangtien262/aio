@if(session('cart_success') || session('contact_status') || session('success'))<div class="book20-feedback" role="status">{{ session('cart_success') ?: session('contact_status') ?: session('success') }}</div>@endif
@if(isset($errors) && $errors->any())<div class="book20-feedback is-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
