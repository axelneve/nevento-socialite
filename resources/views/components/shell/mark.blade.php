@props([
    'kind' => 'workspace',
    'item' => [],
    'size' => 24,
])

@if ($kind === 'app')
    <x-nevento::app-mark :app="$item" :size="$size" />
@else
    <x-nevento::workspace-mark :workspace="$item" :size="$size" />
@endif
