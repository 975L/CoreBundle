<?php

namespace App\Tests\Entity;

use App\Entity\ResetPasswordRequest;
use Doctrine\ORM\Mapping\JoinColumn;
use PHPUnit\Framework\TestCase;

class ResetPasswordRequestTest extends TestCase
{
    // The foreign key is what lets an account with a pending reset be deleted, a functional test on SQLite not enforcing it
    public function testTheUserJoinColumnCascadesOnDelete(): void
    {
        $joinColumn = new \ReflectionProperty(ResetPasswordRequest::class, 'user')->getAttributes(JoinColumn::class)[0]->newInstance();

        $this->assertFalse($joinColumn->nullable);
        $this->assertSame('CASCADE', $joinColumn->onDelete);
    }
}
