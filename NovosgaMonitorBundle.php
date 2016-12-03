<?php

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\MonitorBundle;

use Novosga\Module\BaseModule;

class NovosgaMonitorBundle extends BaseModule
{
    public function getIconName()
    {
        return 'desktop';
    }

    public function getDisplayName()
    {
        return 'Monitor';
    }

    public function getHomeRoute()
    {
        return 'novosga_monitor_index';
    }
}
