@php
    $tabs = [
        'admin.dashboard' => __('Overview'),
        'admin.users' => __('Users'),
        'admin.characters' => __('Characters'),
        'admin.renders' => __('Renders'),
    ];
@endphp

<nav class="mb-6 flex flex-wrap gap-1 border-b border-gray-200 dark:border-gray-700">
    @foreach ($tabs as $route => $label)
        <a href="{{ route($route) }}" wire:navigate
           @class([
               '-mb-px border-b-2 px-4 py-2 text-sm font-medium',
               'border-indigo-500 text-indigo-600 dark:text-indigo-400' => request()->routeIs($route),
               'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' => ! request()->routeIs($route),
           ])>
            {{ $label }}
        </a>
    @endforeach
</nav>
