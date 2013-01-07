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

namespace TKMON\Action\Expose\System\Configuration;

/**
 * Action to handle basic configuration tasks
 *
 * @package TKMON\Action
 * @author Marius Hein <marius.hein@netways.de>
 */
class Backup extends \TKMON\Action\Base
{
    public function actionIndex()
    {
        $template = new \TKMON\Mvc\Output\TwigTemplate($this->container['template']);
        $template->setTemplateName('views/System/Configuration/Backup.twig');
        return $template;
    }

    public function actionApplianceReboot()
    {
        $params = $this->container['params'];

        $response = new \TKMON\Mvc\Output\JsonResponse();

        try {
            if ($params->getParameter('reboot', false) === '1') {
                $command = $this->container['command']->create('reboot');
                $command->execute();
                $response->setSuccess(true);
            } else {
                throw new \TKMON\Exception\ModelException("Not properly parametrized: Action ApplianceReboot");
            }
        } catch(\Exception $e) {
            $response->addException($e);
        }

        return $response;
    }
}