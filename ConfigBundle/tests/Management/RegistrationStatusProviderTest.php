<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\RegistrationStatusProvider;
use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Repository\FormRepository;
use PHPUnit\Framework\TestCase;

class RegistrationStatusProviderTest extends TestCase
{
    private function createProvider(?Form $form): RegistrationStatusProvider
    {
        $formRepository = $this->createStub(FormRepository::class);
        $formRepository->method('findOneBy')->willReturn($form);

        return new RegistrationStatusProvider($formRepository);
    }

    public function testGetStatusKey(): void
    {
        $this->assertSame('registration', $this->createProvider(null)->getStatusKey());
    }

    public function testAnEnabledRegisterFormReportsRegistrationsAsOpen(): void
    {
        $data = $this->createProvider(new Form()->setEnabled(true))->getStatusData();

        $this->assertTrue($data['open']);
        $this->assertSame('enabled', $data['form']);
    }

    public function testADisabledRegisterFormReportsRegistrationsAsClosed(): void
    {
        $data = $this->createProvider(new Form()->setEnabled(false))->getStatusData();

        $this->assertFalse($data['open']);
        $this->assertSame('disabled', $data['form']);
    }

    // A site whose form was never seeded and one that closed its registrations look the same to a visitor, and not at all the same to whoever has to decide whether something is missing
    public function testAnAbsentFormIsToldApartFromAClosedOne(): void
    {
        $data = $this->createProvider(null)->getStatusData();

        $this->assertFalse($data['open']);
        $this->assertSame('absent', $data['form']);
    }

    // Only counts and booleans travel: which fields the form holds, and who filled it, stay on the site
    public function testTheReportCarriesNothingFromTheFormItself(): void
    {
        $form = new Form()
            ->setName('register')
            ->setEnabled(true)
            ->setActionConfig(['secret' => 'must not travel']);

        $encoded = (string) json_encode($this->createProvider($form)->getStatusData());

        $this->assertStringNotContainsString('must not travel', $encoded);
        $this->assertStringNotContainsString('register', $encoded);
    }
}
