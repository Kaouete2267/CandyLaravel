@php
    $decoded = json_decode($response ?? '', true);
    $formattedResponse = json_last_error() === JSON_ERROR_NONE && is_array($decoded)
        ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : $response;
@endphp

<div class="flex flex-col gap-3 rounded-lg border border-dashed border-gray-300 p-3 dark:border-white/20">
    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Debug</h4>

    <details class="flex flex-col gap-1.5">
        <summary class="cursor-pointer text-sm font-medium text-gray-950 dark:text-white">Prompt envoyé à l'IA</summary>
        <pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-md bg-gray-50 p-3 font-mono text-xs text-gray-800 dark:bg-white/5 dark:text-gray-200">{{ $prompt }}</pre>
    </details>

    <details open class="flex flex-col gap-1.5">
        <summary class="cursor-pointer text-sm font-medium text-gray-950 dark:text-white">Réponse de l'IA</summary>
        <pre class="max-h-96 overflow-auto rounded-md bg-gray-50 p-3 font-mono text-xs text-gray-800 dark:bg-white/5 dark:text-gray-200">{{ $formattedResponse }}</pre>
    </details>
</div>
