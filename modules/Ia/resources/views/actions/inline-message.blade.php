@if (filled($message ?? null))
    <div @class([
        'rounded-lg p-3 text-sm border',
        'bg-red-50 text-red-700 border-red-200 dark:bg-red-400/10 dark:text-red-400 dark:border-red-400/20' => $type === 'danger',
        'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-400/10 dark:text-amber-400 dark:border-amber-400/20' => $type === 'warning',
        'bg-green-50 text-green-700 border-green-200 dark:bg-green-400/10 dark:text-green-400 dark:border-green-400/20' => $type === 'success',
    ])>
        {{ $message }}
    </div>
@endif
