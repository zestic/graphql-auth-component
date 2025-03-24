<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DB;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Zestic\GraphQL\AuthComponent\Application\DB\AuthPDO;

class AuthPDOTest extends TestCase
{
    private AuthPDO&MockObject $pdo;

    protected function setUp(): void
    {
        $this->pdo = $this->getMockBuilder(AuthPDO::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAttribute'])
            ->getMock();
    }

    public function testConstructCreatesValidPDOInstance(): void
    {
        $this->assertInstanceOf(AuthPDO::class, $this->pdo);
    }

    public function testConstructSetsErrorMode(): void
    {
        $this->pdo->expects($this->once())
            ->method('getAttribute')
            ->with(\PDO::ATTR_ERRMODE)
            ->willReturn(\PDO::ERRMODE_EXCEPTION);

        $this->assertEquals(\PDO::ERRMODE_EXCEPTION, $this->pdo->getAttribute(\PDO::ATTR_ERRMODE));
    }
}
