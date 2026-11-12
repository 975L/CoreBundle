<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Map;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Map\GoogleMapsProvider;
use c975L\UiBundle\Map\LeafletProvider;
use c975L\UiBundle\Service\MapProviderResolver;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;

// The resolver with the two shipped providers over the config given, as the container builds it
final class MapResolverFactory
{
    public static function create(ConfigServiceInterface $configService): MapProviderResolver
    {
        return new MapProviderResolver($configService, [
            new LeafletProvider(new Packages(new Package(new EmptyVersionStrategy()))),
            new GoogleMapsProvider($configService),
        ]);
    }
}
