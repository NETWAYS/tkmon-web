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

namespace TKMON\Action\Expose\Monitor\Icinga;

use ICINGA\Object\Service;
use NETWAYS\Common\ArrayObject;
use NETWAYS\Common\ArrayObjectValidator;
use NETWAYS\Common\ValidatorObject;
use NETWAYS\Http\CgiParams;
use NETWAYS\Intl\SimpleTranslator;
use TKMON\Action\Base;
use TKMON\Model\Icinga\ServiceData;
use TKMON\Mvc\Output\JsonResponse;
use TKMON\Mvc\Output\TwigTemplate;

/**
 * Action handle contacts views
 *
 * @package TKMON\Action
 * @author Marius Hein <marius.hein@netways.de>
 */
class Services extends Base
{
    /**
     * Method to display main entry screen
     * @param ArrayObject $params
     * @return TwigTemplate
     */
    public function actionEdit(ArrayObject $params)
    {
        $template = new TwigTemplate($this->container['template']);
        $template->setTemplateName('views/Monitor/Icinga/Services/List.twig');

        $template['hostName'] = $params->get('hostName');

        return $template;
    }

    /**
     * Renders an embedded service list for a host
     * @param ArrayObject $params
     * @return JsonResponse
     */
    public function actionEmbeddedList(ArrayObject $params)
    {
        $response = new JsonResponse();

        try {

            $validator = new ArrayObjectValidator();
            $validator->addValidatorObject(
                ValidatorObject::create(
                    'hostName',
                    'Hostname',
                    ValidatorObject::VALIDATE_MANDATORY
                )
            );

            $validator->validateArrayObject($params);

            $template = new TwigTemplate($this->container['template']);
            $template->setTemplateName('views/Monitor/Icinga/Services/EmbeddedList.twig');

            /** @var $hostData \TKMON\Model\Icinga\HostData */
            $hostData = $this->container['hostData'];
            $hostData->load();

            $host = $hostData->getHost($params['hostName']);
            $services = $host->getServices();

            $template['services'] = $services;
            $template['host'] = $host;

            $response->addData($template->toString());

            $response->setSuccess();
        } catch (\Exception $e) {
            $response->addException($e);
        }

        return $response;
    }

    /**
     * Renders en embedded edit form
     * @param ArrayObject $params
     * @return JsonResponse
     */
    public function actionEmbeddedEdit(ArrayObject $params)
    {
        $response = new JsonResponse();

        try {

            $validator = new ArrayObjectValidator();
            $validator->addValidatorObject(
                ValidatorObject::create(
                    'serviceDescription',
                    'serviceDescription',
                    ValidatorObject::VALIDATE_MANDATORY
                )
            );

            $validator->addValidatorObject(
                ValidatorObject::create(
                    'hostName',
                    'hostName',
                    ValidatorObject::VALIDATE_MANDATORY
                )
            );

            $validator->validateArrayObject($params);

            $template = new TwigTemplate($this->container['template']);
            $template->setTemplateName('views/Monitor/Icinga/Services/EmbeddedCreate.twig');

            /** @var $hostData \TKMON\Model\Icinga\HostData */
            $hostData = $this->container['hostData'];
            $hostData->load();

            $host = $hostData->getHost($params['hostName']);

            $service = $host->getService($params['serviceDescription']);

            $template['mode'] = 'edit';
            $template['service'] = $service;
            $template['host'] = $host;

            /** @var ServiceData $serviceData */
            $serviceData = $this->container['serviceData'];

            $catalogueId = $service->getCustomVariable('name'); // Internal reference to catalogue

            $template['arguments'] = $serviceData->getCommandArgumentFieldsReadyValues($service, $catalogueId);

            $meta = $this->container['serviceCatalogue']->getAttributes($catalogueId);

            $this->additionalMetaProcessing($template, $meta, $service);

            $response->addData($template->toString());

            $response->setSuccess(true);
        } catch (\Exception $e) {
            $response->addException($e);
        }

        return $response;
    }

    /**
     * Renders an embedded creation form
     * @param ArrayObject $params
     * @return JsonResponse
     */
    public function actionEmbeddedCreate(ArrayObject $params)
    {
        $response = new JsonResponse();

        try {

            $validator = new ArrayObjectValidator();
            $validator->addValidatorObject(
                ValidatorObject::create(
                    'serviceCatalogueId',
                    'serviceCatalogueId',
                    ValidatorObject::VALIDATE_MANDATORY
                )
            );

            $validator->addValidatorObject(
                ValidatorObject::create(
                    'hostName',
                    'hostName',
                    ValidatorObject::VALIDATE_MANDATORY
                )
            );

            $validator->validateArrayObject($params);

            $template = new TwigTemplate($this->container['template']);
            $template->setTemplateName('views/Monitor/Icinga/Services/EmbeddedCreate.twig');

            /** @var $hostData \TKMON\Model\Icinga\HostData */
            $hostData = $this->container['hostData'];
            $hostData->load();

            $host = $hostData->getHost($params['hostName']);

            /** @var $serviceCatalogue \ICINGA\Catalogue\Services */
            $serviceCatalogue = $this->container['serviceCatalogue'];

            /** @var ServiceData $serviceData */
            $serviceData = $this->container['serviceData'];

            $item = $serviceCatalogue->getItem($params['serviceCatalogueId']);

            $meta = $serviceCatalogue->getAttributes($params['serviceCatalogueId']);

            $this->additionalMetaProcessing($template, $meta);

            $template['service'] = $item;
            $template['host'] = $host;
            $template['arguments'] = $serviceData->getCommandArgumentFields($params['serviceCatalogueId']);

            $response->addData($template->toString());

            $response->setSuccess();
        } catch (\Exception $e) {
            $response->addException($e);
        }

        return $response;
    }

    /**
     * Process catalogue meta data and template vars
     * @param TwigTemplate $template
     * @param \stdClass $meta
     * @param \ICINGA\Object\Service $service
     */
    private function additionalMetaProcessing
    (TwigTemplate $template, \stdClass $meta, Service $service = null)
    {
        /*
         * Thomas-Krenn flag if we want to switch on notification
         * @todo No function to extend this
         */
        if (isset($meta->tk_notify) && $meta->tk_notify === true) {
            $template['tk_notify'] = true;

            $template['tk_notify_default'] =
                isset($meta->tk_notify_default) ? (boolean) $meta->tk_notify_default : true;

            if ($service) {
                $thomasKrennTemplate = $this->container['config']['thomaskrenn.template.service'];
                if ($thomasKrennTemplate === $service->getUse()) {
                    $template['tk_notify_default'] = true;
                } else {
                    $template['tk_notify_default'] = false;
                }
            }
        }

        if (isset($meta->links)) {
            $template['links'] = $meta->links;
        }

        if (isset($meta->doc)) {
            $template['doc'] = SimpleTranslator::textProcessor($meta->doc);
        }
    }

    /**
     * Ajax endpoint to search the services catalogue
     * @param ArrayObject $params
     * @return JsonResponse
     */
    public function actionCatalogueSearch(ArrayObject $params)
    {
        $response = new JsonResponse();

        try {
            $validator = new ArrayObjectValidator();

            $validator->addValidatorObject(
                ValidatorObject::create(
                    'q',
                    'Query',
                    ValidatorObject::VALIDATE_MANDATORY
                )
            );

            $validator->validateArrayObject($params);

            /** @var $serviceCatalogue \ICINGA\Catalogue\Services */
            $serviceCatalogue = $this->container['serviceCatalogue'];
            $result = $serviceCatalogue->query($params['q']);

            $response->setData($result->getArrayCopy());
            $response->setSuccess(true);
        } catch (\Exception $e) {
            $response->addException($e);
        }

        return $response;
    }

    /**
     * Remove service for a host
     * @param ArrayObject $params
     * @return JsonResponse
     */
    public function actionRemove(ArrayObject $params)
    {
        $response = new JsonResponse();

        try {

            $validator = new ArrayObjectValidator();

            $validator->addValidatorObject(
                ValidatorObject::create(
                    'hostName',
                    'Hostname',
                    ValidatorObject::VALIDATE_MANDATORY
                )
            );

            $validator->addValidatorObject(
                ValidatorObject::create(
                    'serviceId',
                    'ID of service',
                    ValidatorObject::VALIDATE_MANDATORY
                )
            );

            $validator->validateArrayObject($params);

            /** @var $hostData \TKMON\Model\Icinga\HostData */
            $hostData = $this->container['hostData'];
            $hostData->load();

            $host = $hostData->getHost($params['hostName']);

            $service = $host->getService($params['serviceId']);
            $host->removeService($service);

            $hostData->updateHost($host);

            $hostData->write();

            $response->setSuccess(true);

        } catch (\Exception $e) {
            $response->addException($e);
        }

        return $response;
    }

    /**
     * Write add/edit services for a host (ajax)
     *
     * - Also write data to disk
     *
     * @param ArrayObject $params
     * @return JsonResponse
     */
    public function actionWrite(ArrayObject $params)
    {
        $response = new JsonResponse();

        try {

            // ----------------------------------------------------------------
            // Validation of needed arguments
            // ----------------------------------------------------------------

            $validator = new ArrayObjectValidator();

            $validator->addValidatorObject(
                ValidatorObject::create(
                    'host_name',
                    _('Hostname'),
                    ValidatorObject::VALIDATE_MANDATORY
                )
            );

            $validator->addValidatorObject(
                ValidatorObject::create(
                    'service_description',
                    _('Servicename'),
                    ValidatorObject::VALIDATE_MANDATORY
                )
            );

            $validator->addValidatorObject(
                ValidatorObject::create(
                    'cv_name',
                    _('Catalogue identifier'),
                    ValidatorObject::VALIDATE_MANDATORY
                )
            );

            /** @var ServiceData $serviceData */
            $serviceData = $this->container['serviceData'];

            $validator->validateArrayObject($params);

            $catalogueName = $params['cv_name'];

            $hostName = $params['host_name'];

            // ----------------------------------------------------------------
            // Validation of arguments (if any)
            // ----------------------------------------------------------------

            $arguments = new ArrayObject();
            $cgiParams = new CgiParams(false);

            if ($cgiParams->hasParameter('arguments')) {
                $plainArguments = $cgiParams->getParameter('arguments');
                $arguments->fromArray($plainArguments);
                $argumentValidator = $serviceData->createValidator($catalogueName);
                $argumentValidator->validateArrayObject($arguments);
            }

            // ----------------------------------------------------------------
            // Good, prepare to write
            // ----------------------------------------------------------------

            /** @var $hostData \TKMON\Model\Icinga\HostData */
            $hostData = $this->container['hostData'];
            $hostData->load();

            $host = $hostData->getHost($hostName);
            $service = $serviceData->createServiceFromCatalogueWithArgumentValues($catalogueName, $arguments);



            // Overriding data from html form
            $service->serviceDescription = $params['service_description'];
            $service->displayName = $params['display_name'];

            // Rewrite
            $serviceData->hookBeforeCreate($service, $params);

            // Glue
            $host->addService($service);

            // Bada ...
            $hostData->updateHost($host);

            // BOOM!
            $hostData->write();

            $response->setSuccess(true);

        } catch (\Exception $e) {
            $response->addException($e);
        }

        return $response;
    }
}
