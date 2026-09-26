<?php

namespace App\Tests\Controller;

use App\Entity\User;
use c975L\ConfigBundle\Account\AccountSectionBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

// ConfigBundle's /account, through the site's own firewall and User: the profile, the password change and the sections every installed bundle contributes
class AccountControllerTest extends FunctionalTestCase
{
    private const string CURRENT_PASSWORD = 'Old-Passw0rd!';
    private const string NEW_PASSWORD = 'N3w-Str0ng!Pass';

    // The firewall sends an anonymous visitor to the login form, as for any page reserved to an account
    public function testAnonymousIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/account');

        $this->assertResponseRedirects('/login');
    }

    // The profile and the password first, then one page section per section the installed bundles and the site contribute for this member
    public function testShowsTheProfileAndEverySection(): void
    {
        $client = $this->authenticatedClient();

        $crawler = $client->request('GET', '/account');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#account-profile', 'functional-tests@example.test');
        $this->assertCount(1, $crawler->filter('form[name="account_password"]'));

        $sections = static::getContainer()->get(AccountSectionBuilder::class)->getSections($this->authenticatedUser);
        $this->assertCount(2 + \count($sections), $crawler->filter('.account > *'));
    }

    // A wrong current password is refused, and the password stays as it was
    public function testAWrongCurrentPasswordChangesNothing(): void
    {
        $client = $this->authenticatedClient();

        $this->changePassword($client, 'not-the-password');

        $this->assertResponseStatusCodeSame(422);
        $this->assertTrue($this->passwordIs(self::CURRENT_PASSWORD));
    }

    // The right current password has the new one saved, the member staying logged in
    public function testTheRightCurrentPasswordChangesIt(): void
    {
        $client = $this->authenticatedClient();

        $this->changePassword($client, self::CURRENT_PASSWORD);

        $this->assertResponseRedirects('/account');
        $this->assertTrue($this->passwordIs(self::NEW_PASSWORD));

        $client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    // Kept on one kernel and read in the site's default language, which the bare url answers in without moving the browser, the member given a real hashed password the form can check
    private function authenticatedClient(): KernelBrowser
    {
        $client = $this->createAuthenticatedClient();
        $client->disableReboot();

        $container = static::getContainer();
        $client->setServerParameter('HTTP_ACCEPT_LANGUAGE', (string) $container->getParameter('kernel.default_locale'));
        $this->authenticatedUser->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($this->authenticatedUser, self::CURRENT_PASSWORD));
        $container->get(EntityManagerInterface::class)->flush();
        $client->loginUser($this->authenticatedUser);

        return $client;
    }

    // Fills and posts the form the page renders, CSRF token included
    private function changePassword(KernelBrowser $client, string $currentPassword): void
    {
        $crawler = $client->request('GET', '/account');
        $this->assertResponseIsSuccessful();

        $client->submit($crawler->filter('form[name="account_password"]')->form([
            'account_password[currentPassword]' => $currentPassword,
            'account_password[plainPassword][first]' => self::NEW_PASSWORD,
            'account_password[plainPassword][second]' => self::NEW_PASSWORD,
        ]));
    }

    // Read back from the database rather than from the identity map
    private function passwordIs(string $plainPassword): bool
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->clear();
        $user = $entityManager->find(User::class, $this->authenticatedUser?->getId());

        return $container->get(UserPasswordHasherInterface::class)->isPasswordValid($user, $plainPassword);
    }
}
