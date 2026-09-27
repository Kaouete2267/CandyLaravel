<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hébergement OVH : PHP compresse la sortie (zlib.output_compression) sans corriger le Content-Length posé par
 * l'application. Pour un fichier servi par PHP (livewire.min.js, aperçus d'upload, téléchargements), le navigateur
 * attend la taille non compressée, abandonne au bout de quelques secondes (ERR_HTTP2_PROTOCOL_ERROR) et le fichier
 * est perdu : sans le script Livewire, la connexion au back-office tourne en boucle.
 * Sans Content-Length, la réponse est envoyée en « chunked », comme les pages HTML.
 */
class DropContentLengthWhenPhpCompresses
{
    public function __construct(private ?bool $phpCompressesOutput = null) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->phpCompressesOutput()) {
            $response->headers->remove('Content-Length');
        }

        return $response;
    }

    private function phpCompressesOutput(): bool
    {
        if ($this->phpCompressesOutput !== null) {
            return $this->phpCompressesOutput;
        }

        // « On », « 1 » ou une taille de tampon (ex. « 4096 ») : compression active.
        return ! in_array(strtolower(trim((string) ini_get('zlib.output_compression'))), ['', '0', 'off'], true);
    }
}
