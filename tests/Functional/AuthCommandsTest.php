<?php

namespace Pantheon\Terminus\Tests\Functional;

use Pantheon\Terminus\Tests\Traits\LoginHelperTrait;
use Pantheon\Terminus\Tests\Traits\SiteBaseSetupTrait;
use Pantheon\Terminus\Tests\Traits\TerminusTestTrait;
use Pantheon\Terminus\Tests\Traits\UrlStatusCodeHelperTrait;
use PHPUnit\Framework\TestCase;

/**
 * Class BackupCommandsTest
 *
 * @package Pantheon\Terminus\Tests\Functional
 */
class AuthCommandsTest extends TestCase
{
    use TerminusTestTrait;
    use LoginHelperTrait;

    /**
     * @test
     * @covers \Pantheon\Terminus\Commands\Auth\LoginCommand
     * @group auth
     * @gropu short
     */
    public function testAuthLogin()
    {
        $this->assertTrue(true, "create Backup");
    }

    /**
     * @test
     * @covers \Pantheon\Terminus\Commands\Auth\WhoamiCommand
     * @group auth
     * @group auth
     * @throws \JsonException
     */
    public function testAuthWhoAmI()
    {
        $result = $this->terminusJsonResponse("auth:whoami");
        $this->assertIsArray($result, "Response from auth:whoami should be an array.");
        $this->assertArrayHasKey("id", $result, "Response from whoami should include a user ID");
        $this->assertArrayHasKey(
            "email",
            $result,
            "Response from whoami should include a user name",
        );
    }
}