<?php
/**
 * Copyright 2014-2016 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Mail_Autoconfig
 * @subpackage UnitTests
 */

/**
 * Tests for the SRV Driver.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Mail_Autoconfig
 * @subpackage UnitTests
 */
class Horde_Mail_Autoconfig_Driver_SrvTest
extends Horde_Mail_Autoconfig_Driver_TestBase
{
    protected function _getDriver()
    {
        return new Horde_Mail_Autoconfig_Driver_Srv();
    }

}
