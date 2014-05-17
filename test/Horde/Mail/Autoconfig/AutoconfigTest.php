<?php
/**
 * Copyright 2014 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @copyright  2014 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Mail_Autoconfig
 * @subpackage UnitTests
 */

/**
 * Tests for the base autoconfig object.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2014 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Mail_Autoconfig
 * @subpackage UnitTests
 */
class Horde_Mail_Autoconfig_AutoconfigTest extends PHPUnit_Framework_TestCase
{
    public function testGetDrivers()
    {
        $drivers = Horde_Mail_Autoconfig::getDrivers();
        $this->assertNotEmpty($drivers);
    }

}
