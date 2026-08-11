<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Repository\ConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ConfigService implements ConfigServiceInterface
{
    private const string CACHE_KEY = 'site_configs_all';

    // Every optional metadata key a configs.json entry can declare, with what it means when left out ("label" and "slug" are required, so they're not in here)
    private const array METADATA_DEFAULTS = [
        'kind' => 'text',
        'group' => null,
        'description' => null,
        'severity' => null,
        'restricted' => false,
        // Only a "choice" kind entry declares one, see Config::TYPE_CHOICE
        'choices' => null,
    ];

    private ?array $configs = null;

    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly CacheInterface $cache,
        private readonly ParameterBagInterface $params,
        private readonly EntityManagerInterface $manager,
        private readonly VaultEncryptor $vaultEncryptor,
    ) {
    }

    // Returns the value of a config (or null if not found)
    public function get(string $key): mixed
    {
        $configs = $this->loadAll();

        return $configs[$key] ?? null;
    }

    // Returns the boolean value of a config (true or false)
    public function getBool($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    // Returns true if the parameter exists in the configs, false otherwise
    public function hasParameter(string $parameter): bool
    {
        $configs = $this->loadAll();

        return array_key_exists($parameter, $configs);
    }

    // Returns the value of a container parameter (or null if not found)
    public function getContainerParameter(string $parameter): mixed
    {
        return $this->params->get($parameter);
    }

    // Invalidates the configs cache (to be called after any modification).
    public function invalidateCache(): void
    {
        $this->configs = null;
        try {
            $this->cache->delete(self::CACHE_KEY);
        } catch (InvalidArgumentException) {
            // Quiet - cache will be simply recalculated on next access
        }
    }

    // Loads all configs in cache and returns them as an associative array (slug => value)
    public function loadAll(): array
    {
        if (null !== $this->configs) {
            return $this->configs;
        }

        // $this->invalidateCache(); // For debug
        $this->configs = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(null);

            $configs = [];
            foreach ($this->configRepository->findAll() as $configEntry) {
                $value = $configEntry->getValue();

                // Decrypt sensitive values that have been encrypted
                if ($configEntry->getIsSensitive() && null !== $value && '' !== $value) {
                    if ($this->vaultEncryptor->isEncrypted($value)) {
                        $value = $this->vaultEncryptor->decrypt($value);
                    }
                }

                $configs[$configEntry->getSlug()] = $this->castValue($configEntry->getKind(), $value);
            }

            // Missing from the database, site-role-admin would lock the whole back-office out, so it falls back on its configs.json default
            $configs['site-role-admin'] ??= 'ROLE_ADMIN';

            return $configs;
        });

        return $this->configs;
    }

    private function castValue(string $kind, mixed $value): mixed
    {
        return match ($kind) {
            Config::TYPE_BOOL => $this->getBool($value),
            Config::TYPE_INT => (int) $value,
            Config::TYPE_JSON => is_string($value) ? (json_decode($value, true) ?? []) : [],
            default => $value,
        };
    }

    // Loads default config values in the database (if not already present)
    public function loadDefaultConfig(string $jsonPath): void
    {
        $configs = json_decode((string) file_get_contents($jsonPath), true);

        // A malformed file must be reported, not skipped: c975l:config:load-all would otherwise announce the bundle as loaded, and c975l:config:prune would take its entries for orphans
        if (!is_array($configs)) {
            throw new \RuntimeException(sprintf('Unable to parse "%s": %s.', $jsonPath, json_last_error_msg()));
        }

        foreach ($configs as $configData) {
            $existing = $this->configRepository->findOneBySlug($configData['slug']);

            if ($existing) {
                $this->syncMetadata($existing, $configData);
                // After syncMetadata(), so a value seeded into a row whose sensitive flag just flipped is encrypted or not according to the flag it ends the run with
                $this->syncSeededValue($existing, $configData);

                continue;
            }

            $this->createConfig($configData);
        }

        $this->manager->flush();

        $this->invalidateCache();
    }

    // Fills a row that holds nothing with the value its bundle declares, so an entry reads in the back office as the value the site is actually served - a color, a delay, a retention - instead of as an empty field whose real answer lives in a fallback the admin cannot see. What a fresh install is created with, given to sites installed before the entry gained a default too
    // Only an empty row: anything an admin typed is production state and is never touched, here no more than in syncMetadata()
    // Which is what an entry whose emptiness carries a meaning has to reckon with - "keep every archive", "review this list by hand". Emptying a field writes null, the very state this reads as "never filled in", so such an entry names its meaning with a value of its own: 0 for the retentions, "none" for seo-robots-ai-crawlers-source. An entry with nothing sensible to seed simply declares no value and is left alone
    private function syncSeededValue(Config $config, array $configData): void
    {
        $current = $config->getValue();
        $declared = $configData['value'] ?? null;

        if ((null !== $current && '' !== $current) || null === $declared || '' === $declared) {
            return;
        }

        $config->setValue($this->storableValue($declared, (bool) ($configData['sensitive'] ?? false)));
        $config->setModification(new \DateTime());

        $this->manager->persist($config);
    }

    // A declared value as it is stored: encrypted when the entry is sensitive and a vault key is defined, left as it stands otherwise - a sensitive value kept in clear for want of a key is what c975l:config:load-all warns about and c975l:config:encrypt-sensitive picks up later
    private function storableValue(mixed $value, bool $isSensitive): mixed
    {
        if (!$isSensitive || null === $value || '' === $value || !$this->vaultEncryptor->isKeyDefined()) {
            return $value;
        }

        return $this->vaultEncryptor->encrypt($value);
    }

    // Label/kind/choices/group/description/severity/isRestricted/isSensitive are metadata fixed by the bundle author (not user data), so they're kept in sync even on existing configs; only the value carries production state and is never overwritten here - except when the sensitive flag itself changes, see syncSensitive()
    private function syncMetadata(Config $config, array $configData): void
    {
        $metadata = $this->declaredMetadata($configData);
        $sensitiveChanged = $this->syncSensitive($config, $configData);

        if (!$sensitiveChanged && !$this->metadataDiffers($config, $metadata)) {
            return;
        }

        $config->setLabel($metadata['label']);
        $config->setKind($metadata['kind']);
        $config->setChoices($metadata['choices']);
        $config->setGroup($metadata['group']);
        $config->setDescription($metadata['description']);
        $config->setSeverity($metadata['severity']);
        $config->setIsRestricted($metadata['restricted']);
        $config->setModification(new \DateTime());

        $this->manager->persist($config);
    }

    // The metadata a configs.json entry fixes, defaults applied - the value itself never comes from here
    private function declaredMetadata(array $configData): array
    {
        $metadata = ['label' => $configData['label']];
        foreach (self::METADATA_DEFAULTS as $key => $default) {
            $metadata[$key] = $configData[$key] ?? $default;
        }

        return $metadata;
    }

    // Nothing is written back when the row already matches its declaration - saves a persist()/modification date on every single entry of every c975l:config:load-all run
    private function metadataDiffers(Config $config, array $metadata): bool
    {
        return $config->getLabel() !== $metadata['label']
            || $config->getKind() !== $metadata['kind']
            // Normalized the same way setChoices() does, otherwise a declaration listing no choice would differ from the null it is stored as, and rewrite the row on every single run
            || $config->getChoices() !== ([] === $metadata['choices'] ? null : $metadata['choices'])
            || $config->getGroup() !== $metadata['group']
            || $config->getDescription() !== $metadata['description']
            || $config->getSeverity() !== $metadata['severity']
            || $config->getIsRestricted() !== $metadata['restricted'];
    }

    // Aligns the stored sensitive flag on the declaration - the admin form disables that field, so the json is its only source. The value is converted in the same move (encrypted when it becomes sensitive, decrypted when it stops being), otherwise a flag flip would leave an unreadable "C975L:..." string in a plain-text setting. When the conversion can't be done (no vault key, value encrypted with another one), the flag is left untouched rather than storing something unusable. Returns true when something changed
    private function syncSensitive(Config $config, array $configData): bool
    {
        $declared = (bool) ($configData['sensitive'] ?? false);

        if ($declared === (bool) $config->getIsSensitive()) {
            return false;
        }

        // An empty value has nothing to convert - the flag alone flips
        $value = $config->getValue();
        if (null !== $value && '' !== $value && !$this->convertValue($config, $value, $declared)) {
            return false;
        }

        $config->setIsSensitive($declared);

        return true;
    }

    // Encrypts the stored value as the setting becomes sensitive, decrypts it as it stops being - false when that can't be done (no vault key, value encrypted with another one), which is what leaves the flag untouched rather than storing something unusable
    private function convertValue(Config $config, string $value, bool $declared): bool
    {
        if (!$declared) {
            if (!$this->vaultEncryptor->isEncrypted($value)) {
                return true;
            }

            try {
                $config->setValue($this->vaultEncryptor->decrypt($value));
            } catch (\RuntimeException) {
                return false;
            }

            return true;
        }

        if (!$this->vaultEncryptor->isKeyDefined()) {
            return false;
        }

        if (!$this->vaultEncryptor->isEncrypted($value)) {
            $config->setValue($this->vaultEncryptor->encrypt($value));
        }

        return true;
    }

    private function createConfig(array $configData): void
    {
        $metadata = $this->declaredMetadata($configData);
        $isSensitive = $configData['sensitive'] ?? false;
        $rawValue = $configData['value'] ?? null;

        $config = new Config();
        $config->setLabel($metadata['label']);
        $config->setSlug($configData['slug']);
        $config->setIsSensitive($isSensitive);
        $config->setIsRestricted($metadata['restricted']);
        $config->setKind($metadata['kind']);
        $config->setChoices($metadata['choices']);
        $config->setGroup($metadata['group']);
        $config->setDescription($metadata['description']);
        $config->setSeverity($metadata['severity']);
        $config->setCreation(new \DateTime());
        $config->setModification(new \DateTime());

        // Encrypt non-empty sensitive values on import, the same way syncSeededValue() does for a row that already exists
        $config->setValue($this->storableValue($rawValue, (bool) $isSensitive));

        $this->manager->persist($config);
    }
}
