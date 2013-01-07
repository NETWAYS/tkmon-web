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
 * Security settings
 *
 * @package TKMON\Action
 * @author Marius Hein <marius.hein@netways.de>
 */
class Security extends \TKMON\Action\Base
{
    /**
     * Display the html side of basic security settings
     * @return \TKMON\Mvc\Output\TwigTemplate
     */
    public function actionIndex()
    {
        $output = new \TKMON\Mvc\Output\TwigTemplate($this->container['template']);
        $output->setTemplateName('views/System/Configuration/Security.twig');

        $user = $this->container['user'];
        $output['system_enabled'] = $user->getSystemAccess();
        $output['user'] = $user;

        return $output;
    }

    /**
     * Action to trigger password changes
     * @return \TKMON\Mvc\Output\JsonResponse
     */
    public function actionChangePassword()
    {
        $params = $this->container['params'];
        $user = $this->container['user'];

        $r = new \TKMON\Mvc\Output\JsonResponse();

        try {
            $re = $user->changePassword(
                $params->getParameter('current_password'),
                $params->getParameter('password'),
                $params->getParameter('password_verify')
            );
            $r->setSuccess($re);
        } catch (\Exception $e) {
            $r->setSuccess(false);
            $r->addException($e);
        }

        return $r;
    }

    /**
     * Return a html fragment
     * which indicates if the user has system access
     * @return \TKMON\Mvc\Output\SimpleString
     */
    public function actionSystemAccess()
    {
        $user = $this->container['user'];

        if ($user->getSystemAccess() === true) {
            return  new \TKMON\Mvc\Output\SimpleString(
                '<span class="label label-success">Enabled</span>'
            );
        }

        return  new \TKMON\Mvc\Output\SimpleString(
            '<span class="label label-important">Disabled</span>'
        );

    }

    /**
     * Changer for system controll access
     * @return \TKMON\Mvc\Output\JsonResponse
     * @throws \TKMON\Exception\ModelException
     */
    public function actionChangeSystemAccess()
    {
        $user = $this->container['user'];
        $params = $this->container['params'];
        $val = $params->getParameter('access');
        $response = new \TKMON\Mvc\Output\JsonResponse();

        try {
            if ($val === "1") {
                $user->controlSystemAccess(true);
                $response->setSuccess(true);
            } elseif ($val === "0") {
                $user->controlSystemAccess(false);
                $response->setSuccess(true);
            } else {
                throw new \TKMON\Exception\ModelException(
                    "Invalid arguments, access have to be 0/1"
                );
            }
        } catch (\Exception $e) {
            $response->setSuccess(false);
            $response->addException($e);
        }

        return $response;
    }
}