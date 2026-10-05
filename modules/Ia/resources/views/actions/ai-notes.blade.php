{{-- $notes : HTML déjà nettoyé (Str::sanitizeHtml) dans ReviewIngredientsDiff::fillForm(). --}}
@if (filled($notes ?? null))
    <div class="flex flex-col gap-1.5 border-t border-gray-200 pt-4 dark:border-white/10">
        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Notes de l'IA</h4>
        <div class="text-sm text-gray-950 dark:text-white [&_ol]:list-decimal [&_ol]:ps-5 [&_ul]:list-disc [&_ul]:ps-5 [&_li]:mt-0.5">
            {!! $notes !!}
        </div>
    </div>
@endif
