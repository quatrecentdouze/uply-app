<x-mail::message>
# {{ $title }}

{{ $summary }}

@foreach ($fields as $field)
**{{ $field['label'] }}**: {{ $field['value'] }}

@endforeach
@if ($link !== null)
<x-mail::button :url="$link">
{{ $linkLabel }}
</x-mail::button>
@endif
</x-mail::message>
