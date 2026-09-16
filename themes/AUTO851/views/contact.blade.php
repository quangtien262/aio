@php($pageTitle = app(\App\Core\Themes\ThemeTranslationService::class)->bladeText('AUTO851', app()->getLocale(), 'contact', 'Liên hệ'))
@php($content = data_get($page ?? null, 'content', data_get($page ?? null, 'body', '<p>'.__('Hãy gửi nhu cầu để đội ngũ kỹ thuật tư vấn giải pháp phù hợp.').'</p>')))
@include('theme-auto851::partials.content')
