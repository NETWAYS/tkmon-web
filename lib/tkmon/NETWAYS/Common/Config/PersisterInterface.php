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
 * @copyright 2012-2015 NETWAYS GmbH <info@netways.de>
 */

namespace NETWAYS\Common\Config;

/**
 * Interface to persist configuration data
 *
 * @package NETWAYS\Common\Config
 * @author Marius Hein <marius.hein@netways.de>
 */
interface PersisterInterface
{
    /**
     * Persists a single value
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function persist($key, $value);

    /**
     * Drops a single key
     * @param $key
     * @return mixed
     */
    public function drop($key);

    /**
     * Purges all the data
     */
    public function purge();
}
