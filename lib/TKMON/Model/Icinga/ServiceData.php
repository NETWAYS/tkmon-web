<?php
/**
 * This file is part of TKMON
 *
 * TKMON is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * TKMON is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with TKMON.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Marius Hein <marius.hein@netways.de>
 * @copyright 2012-2013 NETWAYS GmbH <info@netways.de>
 */

namespace TKMON\Model\Icinga;

/**
 * Model handle service creation
 * @package TKMON\Model
 * @author Marius Hein <marius.hein@netways.de>
 */
class ServiceData extends \TKMON\Model\ApplicationModel
{
    // ------------------------------------------------------------------------
    // Data api
    // ------------------------------------------------------------------------

    /**
     * Create a service
     *
     * - Based on parameter attributes
     * - Added default values from config
     *
     * @param \NETWAYS\Common\ArrayObject $attributes
     * @return \ICINGA\Base\Object
     */
    public function createService(\NETWAYS\Common\ArrayObject $attributes)
    {
        $default = $this->container['config']['icinga.record.service'];
        $attributes->fromVoyagerObject($default);
        $service = \ICINGA\Object\Service::createObjectFromArray($attributes);
        return $service;
    }
}
