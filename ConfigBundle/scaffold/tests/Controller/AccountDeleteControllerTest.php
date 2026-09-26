<?php

namespace App\Tests\Controller;

use App\Entity\User;
use c975L\ConfigBundle\Contract\InactivityAwareInterface;
use c975L\ConfigBundle\Event\UserAnonymizedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

// ConfigBundle's /account/delete, through the site's own firewall and User: the account's owner deleting it, anonymized as c975l:config:users-cleanup does
class AccountDeleteControllerTest extends FunctionalTestCase
{
    // The firewall sends an anonymous visitor to the login form, as for any page reserved to an account
    public function testAnonymousIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/account/delete');

        $this->assertResponseRedirects('/login');
    }

    // Another address than the account's own is refused, and the account stays as it was
    public function testAWrongEmailChangesNothing(): void
    {
        $client = $this->authenticatedClient();

        $this->confirm($client, 'someone-else@example.test');

        $this->assertResponseStatusCodeSame(422);
        $user = $this->reloadedUser();
        $this->assertSame('functional-tests@example.test', $user->getEmail());
        $this->assertTrue($user->isEnabled());
    }

    // The account's own address anonymizes it, tells the site through UserAnonymizedEvent, and logs its owner out
    public function testTheRightEmailAnonymizesTheAccountAndLogsOut(): void
    {
        $client = $this->authenticatedClient();

        $dispatched = [];
        static::getContainer()->get('event_dispatcher')->addListener(UserAnonymizedEvent::class, static function (UserAnonymizedEvent $event) use (&$dispatched): void {
            $dispatched[] = $event->user;
        });

        $this->confirm($client, 'functional-tests@example.test');

        $this->assertResponseRedirects('/');
        $this->assertCount(1, $dispatched);

        $user = $this->reloadedUser();
        $this->assertStringEndsWith('@' . InactivityAwareInterface::ANONYMIZED_DOMAIN, (string) $user->getEmail());
        $this->assertFalse($user->isEnabled());

        // The flash survives the logout, which empties the session it was added to - read from the next request's session rather than from the home page, which a site may redirect further
        $client->followRedirect();
        $this->assertNotEmpty($client->getRequest()->getSession()->getFlashBag()->peek('success'));

        // Logged out: the page is now the firewall's again
        $client->request('GET', '/account/delete');
        $this->assertResponseRedirects('/login');
    }

    // Kept on one kernel, so the listener above and the entity manager read back below are the ones the requests used
    private function authenticatedClient(): KernelBrowser
    {
        // A site whose User doesn't implement it yet gets a 404 there, as intended (see UPGRADE.md)
        if (!is_subclass_of(User::class, InactivityAwareInterface::class)) {
            $this->markTestSkipped('App\\Entity\\User does not implement InactivityAwareInterface yet.');
        }

        $client = $this->createAuthenticatedClient();
        $client->disableReboot();

        return $client;
    }

    // Fills and posts the form the page renders, CSRF token included
    private function confirm(KernelBrowser $client, string $email): void
    {
        $crawler = $client->request('GET', '/account/delete');
        $this->assertResponseIsSuccessful();

        $client->submit($crawler->filter('form[name="account_delete"]')->form(['account_delete[email]' => $email]));
    }

    // Read back from the database rather than from the identity map
    private function reloadedUser(): User
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();

        return $entityManager->find(User::class, $this->authenticatedUser?->getId());
    }
}
