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
     * Creates a service
     *
     * - From catalogue values
     * - Add defaults from config
     *
     * @param string $catalogueName
     * @return \ICINGA\Object\Service
     * @throws \TKMON\Exception\ModelException
     */
    public function createServiceFromCatalogue($catalogueName)
    {
        /** @var $catalogue \ICINGA\Catalogue\Services */
        $catalogue = $this->container['serviceCatalogue'];

        $service = $catalogue->getItem($catalogueName);

        if ($service instanceof \ICINGA\Object\Service) {
            $default = $this->container['config']['icinga.record.service'];
            $service->fromVoyagerObject($default);
            return $service;
        }

        throw new \TKMON\Exception\ModelException('Service not found in catalogue: '. $catalogueName);
    }

    /**
     * Return a "ready to write" service object
     * @param string $catalogueName
     * @param \NETWAYS\Common\ArrayObject $argumentValues
     * @return \ICINGA\Object\Service
     * @throws \TKMON\Exception\ModelException
     */
    public function createServiceFromCatalogueWithArgumentValues(
        $catalogueName,
        \NETWAYS\Common\ArrayObject $argumentValues
    ) {
        $service = $this->createServiceFromCatalogue($catalogueName);
        $command = $service->getCommand();

        if ($argumentValues->count() !== count($command->getArguments())) {
            throw new \TKMON\Exception\ModelException('Count $argumentValues differs from catalogue object');
        }

        foreach ($argumentValues as $index => $val) {
            $command->setArgumentValue($index, $val);
        }

        return $service;
    }

    /**
     * Return an array of form fields
     *
     * @param string $catalogueName
     * @param string $nameBase Base name prefix
     * @return \TKMON\Form\Field[]
     */
    public function getCommandArgumentFields($catalogueName, $nameBase = 'arguments')
    {
        $service = $this->createServiceFromCatalogue($catalogueName);
        $command = $service->getCommand();
        $out = array();
        foreach ($command->getArguments() as $argument) {
            $field = new \TKMON\Form\Field\Text($nameBase. '[]', $argument->getLabel());
            $field->setDescription($argument->getDescription());
            $field->setTemplate($this->container['template']);
            $out[] = $field;
        }

        return $out;
    }

    /**
     * Return an array of fields based on a real service
     * @param \ICINGA\Object\Service $service
     * @param string $catalogueName
     * @param string $nameBase
     * @return \TKMON\Form\Field[]
     */
    public function getCommandArgumentFieldsReadyValues(
        \ICINGA\Object\Service $service,
        $catalogueName,
        $nameBase = 'arguments'
    ) {
        $catDef = $this->createServiceFromCatalogue($catalogueName);
        $command = $catDef->getCommand();

        $commandString = $service->checkCommand;
        $commandParts = explode('!', $commandString);
        array_shift($commandParts);

        $out = array();

        foreach ($command->getArguments() as $index => $argument) {
            $field = new \TKMON\Form\Field\Text($nameBase. '[]', $argument->getLabel());
            $field->setValue($commandParts[$index]);
            $field->setDescription($argument->getDescription());
            $field->setTemplate($this->container['template']);
            $out[] = $field;
        }

        return $out;
    }

    /**
     * Creates validator based on catalogue definition
     * @param string $catalogueName
     * @return \NETWAYS\Common\ArrayObjectValidator
     */
    public function createValidator($catalogueName)
    {
        $service = $this->createServiceFromCatalogue($catalogueName);
        $command = $service->getCommand();
        $validator = new \NETWAYS\Common\ArrayObjectValidator();

        foreach ($command->getArguments() as $index => $argument) {
            $vo = new \NETWAYS\Common\ValidatorObject();
            $vo->setField((string)$index);
            $vo->setHumanDescription($argument->getLabel(). ', '. $argument->getDescription());
            $vo->setType(\NETWAYS\Common\ValidatorObject::VALIDATE_MANDATORY);
            $validator->addValidatorObject($vo);
        }

        return $validator;
    }
}