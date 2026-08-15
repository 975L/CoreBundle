<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

// What the PHP process actually allows, as opposed to what a host's control panel says it allows - the two drift apart through a .user.ini, a pool override or a php.ini the panel does not show, and only the process itself can settle it. Deliberately says nothing about any host or hosting plan: it reports what it measures where it runs, and every caller decides on its own whether a missing capability matters to it.
//
// Every reading is SAPI-bound: php-fpm and php-cli are separate configurations, so the same site can allow a function to its web requests and refuse it to its cron, or the reverse. Nothing here can tell what the other SAPI allows - which is why getSapi() travels with the rest, a reading without it being unattributable and therefore useless to compare across sites.
class EnvironmentProbe
{
    // Only ever a binary name here, never a path or an argument - the value reaches a shell, and the one thing that must be impossible is a caller turning a lookup into a command
    private const string BINARY_NAME_PATTERN = '/^[a-z0-9][a-z0-9_-]*$/i';

    // The ini directives worth comparing between two sites: the ones that silently cap what an upload or a long task can do, and whose value is a plain scalar a maintainer can read at a glance
    private const array REPORTED_DIRECTIVES = [
        'memory_limit',
        'max_execution_time',
        'upload_max_filesize',
        'post_max_size',
        'max_input_vars',
    ];

    /** @var array<string, ?string> Memoized per instance: a binary lookup costs a process, and a report asking for the same one twice must not pay it twice */
    private array $binaryVersions = [];

    // The SAPI this reading was taken through, the scope every other reading here is bound to
    public function getSapi(): string
    {
        return \PHP_SAPI;
    }

    // Whether exec() can be called at all. A function listed in disable_functions is undefined as far as function_exists() is concerned, so this covers being disabled and being absent from the build alike
    public function canExec(): bool
    {
        return \function_exists('exec');
    }

    // The version string an external binary reports, or null when it is absent, silent, or unreachable because exec() itself is unavailable. Null is deliberately the single answer to all three: a caller needing the binary is blocked the same way whichever it is, and the reason belongs to the report, not to the lookup
    public function getBinaryVersion(string $binary): ?string
    {
        if (\array_key_exists($binary, $this->binaryVersions)) {
            return $this->binaryVersions[$binary];
        }

        return $this->binaryVersions[$binary] = $this->lookUpBinaryVersion($binary);
    }

    public function hasExtension(string $extension): bool
    {
        return \extension_loaded($extension);
    }

    /**
     * The ini directives listed above, as they are actually applied.
     *
     * @return array<string, string>
     */
    public function getDirectives(): array
    {
        $directives = [];

        foreach (self::REPORTED_DIRECTIVES as $directive) {
            $directives[$directive] = (string) \ini_get($directive);
        }

        return $directives;
    }

    /**
     * The whole reading, as a json-serializable array.
     *
     * Holds capabilities and limits, never the configuration that produced them: disable_functions in full
     * names every function a host left reachable, which describes an attack surface rather than a capability,
     * and this payload is built to leave the site (see StatusReportBuilder). "exec: false" is what a maintainer
     * has to act on; the list it came from is theirs to read on their own server.
     *
     * @param list<string> $binaries   external binaries to look up, by name
     * @param list<string> $extensions php extensions to check for
     *
     * @return array<string, mixed>
     */
    public function describe(array $binaries = [], array $extensions = []): array
    {
        $description = [
            'sapi' => $this->getSapi(),
            'exec' => $this->canExec(),
            'directives' => $this->getDirectives(),
        ];

        foreach ($binaries as $binary) {
            $description['binaries'][$binary] = $this->getBinaryVersion($binary);
        }

        foreach ($extensions as $extension) {
            $description['extensions'][$extension] = $this->hasExtension($extension);
        }

        return $description;
    }

    // Kept apart from getBinaryVersion() so the memoization above holds one plain expression, this being the part that can fail in several ways
    private function lookUpBinaryVersion(string $binary): ?string
    {
        if (!$this->canExec() || 1 !== \preg_match(self::BINARY_NAME_PATTERN, $binary)) {
            return null;
        }

        $output = [];
        $returnVar = 1;

        // Stderr is dropped rather than merged: a binary answering its version on stderr is rarer than one printing a warning there, and a warning read as a version would travel as one
        @\exec(\sprintf('%s --version 2>/dev/null', \escapeshellarg($binary)), $output, $returnVar);

        if (0 !== $returnVar || [] === $output) {
            return null;
        }

        // First line only: most binaries answer one, and the few that print a licence underneath would otherwise carry it into every report
        return \trim((string) $output[0]);
    }
}
