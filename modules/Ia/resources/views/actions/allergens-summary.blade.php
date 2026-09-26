@if ($allergenesEnabled)
    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-white/10">
        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Allergènes</h4>
        <div class="mt-1.5 flex flex-wrap gap-1.5">
            @forelse ($allergensContains as $name)
                <span @class([
                    'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium',
                    'bg-green-50 text-green-700 dark:bg-green-400/10 dark:text-green-400' => in_array($name, $allergensAdded, true),
                    'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300' => ! in_array($name, $allergensAdded, true),
                ])>{{ in_array($name, $allergensAdded, true) ? '+ ' : '' }}{{ $name }}</span>
            @empty
                <span class="text-sm text-gray-500 dark:text-gray-400">Aucun</span>
            @endforelse
        </div>

        @if ($allergensMayContain !== [])
            <p class="mt-2 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Traces possibles</p>
            <div class="mt-1.5 flex flex-wrap gap-1.5">
                @foreach ($allergensMayContain as $name)
                    <span @class([
                        'inline-flex items-center rounded-md px-2 py-1 text-xs font-medium',
                        'bg-green-50 text-green-700 dark:bg-green-400/10 dark:text-green-400' => in_array($name, $allergensAdded, true),
                        'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300' => ! in_array($name, $allergensAdded, true),
                    ])>{{ in_array($name, $allergensAdded, true) ? '+ ' : '' }}{{ $name }}</span>
                @endforeach
            </div>
        @endif
    </div>
@endif
