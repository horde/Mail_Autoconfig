<?php
/**
 * Copyright 2014-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Mail_Autoconfig
 * @subpackage UnitTests
 */
namespace Horde\Mail;
use \Autoconfig;
use Horde_Test_Case as TestCase;
use Horde_Mail_Autoconfig;

/**
 * Tests for the base autoconfig object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Mail_Autoconfig
 * @subpackage UnitTests
 */
class AutoconfigTest extends TestCase
{
    private $aconfig;

    public function setUp(): void
    {
        $this->aconfig = new Horde_Mail_Autoconfig();
    }

    public function testGetDrivers()
    {
        $drivers = $this->aconfig->getDrivers();
        $this->assertNotEmpty($drivers);
    }

    /**
     * @dataProvider provider
     */
    public function testGetMsaConfigWithoutAuth($email, $success)
    {
       // if (!$email) {
            $this->markTestSkipped();
        //}

        $config = $this->aconfig->getMsaConfig($email);

        if ($success) {
            $this->assertNotFalse($config);
        } else {
            $this->assertFalse($config);
        }
    }

    /**
     * @dataProvider provider
     */
    public function testGetMailConfigWithoutAuth($email, $success)
    {
        //if (!$email) {
            $this->markTestSkipped();
        //}

        $config = $this->aconfig->getMailConfig($email);

        if ($success) {
            $this->assertNotFalse($config);
        } else {
            $this->assertFalse($config);
        }
    }

    public function provider()
    {
        $config = self::getConfig('MAILAUTOCONFIG_TEST_CONFIG');
        if (!is_null($config)) {
            $out = array();

            if (!empty($config['mail_autoconfig']['nonauth_emails'])) {
                foreach ($config['mail_autoconfig']['nonauth_emails'] as $val) {
                    $out[] = array($val, true);
                }
            }

            if (!empty($config['mail_autoconfig']['nonauth_emails_bad'])) {
                foreach ($config['mail_autoconfig']['nonauth_emails_bad'] as $val) {
                    $out[] = array($val, false);
                }
            }

            if (!empty($out)) {
                return $out;
            }
        }

        return array(array(null, null));
    }

}
