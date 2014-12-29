<?php

namespace VCR\Example\Guzzle;

/**
* Tests Guzzle class.
*/
class GithubProjectTest extends \PHPUnit_Framework_TestCase
{

    protected function getInfo() {
        $githubProject = new GithubProject('php-vcr/php-vcr');

        return $githubProject->getInfo();
    }

    protected function getInfoIntercepted($deleteFixture = false) {
        if ($deleteFixture) {
            unlink(\VCR\VCR::configure()->getCassettePath() . '/github_adri_php-vcr.yml');
        }
        \VCR\VCR::turnOn();
        \VCR\VCR::insertCassette('github_adri_php-vcr.yml');
        $info =  $this->getInfo();
        \VCR\VCR::turnOff();

        return $info;
    }

    protected function assertValidInfo($info) {
        $this->assertTrue(is_array($info), 'Response is not an array.');
        $this->assertArrayHasKey('full_name', $info, "Key 'full_name' not found.");
        $this->assertEquals('php-vcr/php-vcr', $info['full_name'], "Value for key 'full_name' wrong.");
        $this->assertArrayHasKey('private', $info, "Key 'private' not found.");
        $this->assertFalse($info['private'], "Key 'private' is not false.");
    }

    public function testGithubInfoForExistingProjectDirect()
    {
        $info = $this->getInfo();
        $this->assertValidInfo($info);
    }

    public function testGithubInfoForExistingProjectIntercepted()
    {
        $info = $this->getInfoIntercepted();
        $this->assertValidInfo($info);
    }

    public function testGithubInfoDirectEqualsIntercepted() {
        $this->assertEquals($this->getInfo(), $this->getInfoIntercepted());
    }

    public function testGithubInfoForNonExistingProject()
    {
        $githubProject = new GithubProject('php-vcr/random_stuff');
        $info = $githubProject->getInfo();

        $this->assertNull($info, 'Response is not null.');
    }
}
