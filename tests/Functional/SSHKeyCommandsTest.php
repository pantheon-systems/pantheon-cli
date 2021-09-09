<?php

namespace Pantheon\Terminus\Tests\Functional;

use Pantheon\Terminus\Tests\Traits\TerminusTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Class SSHKeyCommandsTest
 *
 * @package Pantheon\Terminus\Tests\Functional
 */
class SSHKeyCommandsTest extends TestCase
{
    use TerminusTestTrait;

    /**
     * @test
     * @covers \Pantheon\Terminus\Commands\SSHKey\ListCommand
     * @covers \Pantheon\Terminus\Commands\SSHKey\AddCommand
     * @covers \Pantheon\Terminus\Commands\SSHKey\RemoveCommand
     *
     * @group ssh
     * @group short
     */
    public function testSSHKeyCommand()
    {
        $cwd = getcwd();
        $dummy_key_file = "$cwd/tests/config/dummy_key.pub";

        // Initial list
        $ssh_key_list = $this->terminusJsonResponse('ssh-key:list');
        $original_id_list = array_keys($ssh_key_list);
        $key_count = count($ssh_key_list);
        echo "Original key count: $key_count\n";

        // Add new key
        $this->terminus("ssh-key:add $dummy_key_file");
        $ssh_key_list_new = $this->terminusJsonResponse('ssh-key:list');
        echo "new SSH key list: " . print_r($ssh_key_list_new, true) . "\n";

        $this->assertGreaterThan($key_count, count($ssh_key_list_new));
        $new_id_list = array_keys($ssh_key_list_new);
        echo "New ID list: " . print_r($new_id_list) . "\n";
        $new_key = array_diff($new_id_list, $original_id_list);
        echo "New key: " . print_r($new_key, true) . "\n";
        if (is_array($new_key)) {
            $new_key = array_pop($new_key);
        }

        // Remove
        $this->terminus("ssh-key:remove $new_key");
        $ssh_key_list_new2 = $this->terminusJsonResponse('ssh-key:list');
        $this->assertEquals($key_count, count($ssh_key_list_new2));
    }
}
