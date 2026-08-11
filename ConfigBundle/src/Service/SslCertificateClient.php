<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

// Reads a host's TLS certificate via a raw TLS handshake - openssl_x509_parse() needs the certificate itself, not an HTTP response, so this opens (then immediately closes) a bare TLS socket rather than issuing a real request. Peer verification is deliberately disabled: this is a read-only diagnostic reading whatever certificate the host presents (including an expired one, a self-signed one, or one issued for an entirely different host), not a connection meant to carry real traffic. Used by SslCertificateHealthCheckProvider and DeploymentHealthCheckProvider, only ever from the c975l:health-check:run command
class SslCertificateClient
{
    public function fetchExpiry(string $host, int $port = 443): \DateTimeImmutable
    {
        $certificate = $this->fetchCertificate($host, $port);

        return new \DateTimeImmutable()->setTimestamp($certificate['validTo_time_t']);
    }

    // Every hostname the certificate this host presents is valid for: its common name plus every DNS entry of its subjectAltName, lowercased. What a browser compares the address it was given against, and what a certificate issued for the apex alone fails to list for its own "www" alias - the host then serves a certificate covering neither, and every client refuses the connection (see DeploymentHealthCheckProvider)
    // @return list<string>
    public function fetchSubjectNames(string $host, int $port = 443): array
    {
        $certificate = $this->fetchCertificate($host, $port);

        $names = [];
        $commonName = $certificate['subject']['CN'] ?? null;
        if (\is_string($commonName) && '' !== $commonName) {
            $names[] = strtolower($commonName);
        }

        // subjectAltName comes back as one comma-separated string ("DNS:example.com, DNS:*.example.com"), and carries entry types other than DNS (IP, email) that name no host
        foreach (explode(',', (string) ($certificate['extensions']['subjectAltName'] ?? '')) as $entry) {
            $entry = trim($entry);
            if (str_starts_with($entry, 'DNS:')) {
                $names[] = strtolower(substr($entry, 4));
            }
        }

        return array_values(array_unique($names));
    }

    // The parsed certificate the host presents, shared by both reads above so one handshake is described in one place
    // @return array<string, mixed>
    private function fetchCertificate(string $host, int $port): array
    {
        $context = stream_context_create(['ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]]);

        $client = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            10,
            \STREAM_CLIENT_CONNECT,
            $context,
        );
        if (false === $client) {
            throw new \RuntimeException($errstr ?: 'TLS connection failed');
        }

        $params = stream_context_get_params($client);
        fclose($client);

        $certificate = openssl_x509_parse($params['options']['ssl']['peer_certificate'] ?? '');
        if (false === $certificate || !isset($certificate['validTo_time_t'])) {
            throw new \RuntimeException('Unable to read the certificate');
        }

        return $certificate;
    }
}
