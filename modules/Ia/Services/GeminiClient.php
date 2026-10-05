<?php

namespace Modules\Ia\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Ia\Models\IaSetting;

class GeminiClient
{
    /**
     * Envoie un prompt (+ images) et renvoie la réponse JSON décodée, conforme au schéma.
     *
     * @param  array<string, mixed>  $schema  schéma de réponse Gemini (types en majuscules)
     * @param  array<int, array{mime: string, data: string}>  $images  voir ImagePayload
     * @param  ?string  $systemInstruction  rôle et règles absolues, envoyés à part du prompt (`systemInstruction`)
     * @return array<string, mixed>
     */
    public function generateJson(string $prompt, array $schema, array $images = [], float $temperature = 0.2, ?string $systemInstruction = null): array
    {
        // La clé/le modèle réglés depuis le backoffice (Réglages IA) prennent le pas sur le .env,
        // qui reste un repli utile (déploiement sans base, ou avant la première visite de la page).
        $settings = IaSetting::current();
        $key = $settings->gemini_api_key ?: config('bonbon.gemini.key');
        $model = $settings->gemini_model ?: config('bonbon.gemini.model');

        if (! $key) {
            throw new GeminiException("La clé Gemini n'est pas configurée (menu Réglages IA).");
        }

        $parts = [['text' => $prompt]];
        foreach ($images as $image) {
            $parts[] = ['inlineData' => ['mimeType' => $image['mime'], 'data' => $image['data']]];
        }

        $url = sprintf('%s/models/%s:generateContent', config('bonbon.gemini.endpoint'), $model);

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $key])
                ->timeout(config('bonbon.gemini.timeout'))
                ->retry(2, 1500, fn ($e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && in_array($e->response->status(), [500, 503])), throw: false)
                ->post($url, array_filter([
                    'systemInstruction' => $systemInstruction !== null ? ['parts' => [['text' => $systemInstruction]]] : null,
                    'contents' => [['role' => 'user', 'parts' => $parts]],
                    'generationConfig' => [
                        'temperature' => $temperature,
                        'responseMimeType' => 'application/json',
                        'responseSchema' => $schema,
                    ],
                ]));
        } catch (ConnectionException $e) {
            Log::warning('Gemini injoignable', ['error' => $e->getMessage()]);
            throw new GeminiException("Le service d'IA est injoignable. Réessayez dans un instant.");
        }

        if ($response->failed()) {
            Log::warning('Gemini erreur', ['status' => $response->status(), 'body' => $response->body()]);

            throw new GeminiException(match ($response->status()) {
                400 => "Requête refusée par l'IA (image ou modèle invalide) : ".($response->json('error.message') ?? ''),
                403 => 'Clé API Gemini refusée : vérifiez la clé dans Réglages IA.',
                429 => "Quota gratuit de l'IA atteint. Réessayez dans une minute (ou demain si le quota journalier est épuisé).",
                default => "Le service d'IA a renvoyé une erreur ({$response->status()}).",
            });
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if ($text === null) {
            $reason = $response->json('promptFeedback.blockReason') ?? $response->json('candidates.0.finishReason') ?? 'inconnue';
            throw new GeminiException("L'IA n'a pas produit de réponse (raison : {$reason}).");
        }

        $data = json_decode($text, true);

        if (! is_array($data)) {
            throw new GeminiException("La réponse de l'IA n'est pas un JSON valide.");
        }

        return $data;
    }
}
