<?php

declare(strict_types=1);

namespace Hesabe\Http;

/**
 * The default sender, on ext-curl, which every PHP shop platform already requires.
 *
 * One handle is reused per sender so TLS connections stay open between calls.
 */
final class CurlSender implements Sender
{
    private ?\CurlHandle $handle = null;

    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): Response
    {
        if ($method === '' || $url === '') {
            throw new \InvalidArgumentException('A request needs a method and a URL');
        }
        $this->handle ??= curl_init() ?: throw new \RuntimeException('curl_init failed');
        $handle = $this->handle;
        curl_reset($handle);

        $received = [];
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            // Hesabe never redirects an API call, and following one would re-send the
            // accessCode header to wherever it points.
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            // A whole-call deadline, unlike a per-read socket timeout, also bounds a server
            // that drips bytes.
            CURLOPT_TIMEOUT_MS => (int) round($timeout * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int) round(min($timeout, 10.0) * 1000),
            CURLOPT_HEADERFUNCTION => static function (\CurlHandle $curl, string $line) use (&$received): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $received[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ];
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($handle, $options);

        $content = curl_exec($handle);
        if (!is_string($content)) {
            throw new \RuntimeException(curl_error($handle) ?: 'curl_exec failed');
        }
        return new Response((int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $content, $received);
    }
}
