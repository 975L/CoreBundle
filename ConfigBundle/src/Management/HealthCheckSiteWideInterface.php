<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

// Implemented by a HealthCheckProvider checking the site once rather than page by page - the certificate, the security headers, a shop's orders against their payments. Its rows are then shown in the Health check page's own "Site" section instead of the per-page table, where they would read as one page among many (see HealthCheckController::index()).
//
// This is what a satellite bundle declares for itself: HealthCheckController still holds a list of its own site-wide kinds, but that list is written in ConfigBundle and no bundle installed beside it can add to it - a check like PaymentBundle's basket-integrity, site-wide by nature, ended up under "Pages" for want of a way to say so.
interface HealthCheckSiteWideInterface extends HealthCheckProviderInterface
{
}
