<?php

declare(strict_types=1);

namespace T3thi\Transfusion\Controller;

use Doctrine\DBAL\Exception;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use T3thi\Transfusion\Domain\Repository\TransfusionRepository;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Controller responsible for connection or disconnection
 * of translated records and their translation parents
 */
class TransfusionController
{
    protected TransfusionRepository $transfusionRepository;
    protected ModuleTemplateFactory $moduleTemplateFactory;
    protected IconFactory $iconFactory;
    protected DataHandler $dataHandler;
    protected array $dataMap = [];
    protected array $fullDataMap = [];

    public function __construct()
    {
        $this->transfusionRepository = GeneralUtility::makeInstance(TransfusionRepository::class);
        $this->dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $this->moduleTemplateFactory = GeneralUtility::makeInstance(ModuleTemplateFactory::class);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception
     */
    public function connectAction(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();

        if (
            empty($queryParams['connect']['page'])
            || empty($queryParams['connect']['language'])
            || empty($queryParams['connect']['tables'])
        ) {
            return new Response();
        }

        $missingInformation = false;
        $moduleTemplate = $this->moduleTemplateFactory->create($request);

        $tables = $queryParams['connect']['tables'];
        $language = (int)$queryParams['connect']['language'];
        $page = (int)$queryParams['connect']['page'];

        foreach ($tables as $table) {
            $disconnectMapper = $this->transfusionRepository->fetchDisconnectedRecordsAndPrepareDataMap(
                $table,
                $language,
                $page,
                'connect',
                $this->fullDataMap,
            );
            $this->dataMap[$table] = $disconnectMapper['dataMap'] ?? [];
            if ($disconnectMapper['missingInformation']) {
                $missingInformation = true;
            }
        }

        if ($missingInformation || 1 === 1) {
            $moduleTemplate->assignMultiple(
                [
                    'connect' => $queryParams['connect'],
                    'returnUrl' => $queryParams['returnUrl'] ?? '',
                    'fullDataMap' => $this->fullDataMap,
                    'defaultLanguageRecords' => $this->transfusionRepository->fetchDefaultLanguageRecords(
                        $tables,
                        $language,
                        $page,
                        $this->fullDataMap
                    )
                ]
            );
            $moduleTemplate->setModuleClass('module  module-transfusion-connector');
            $moduleTemplate->setModuleId('transfusion-connector-' . $page . '-' . $language);
            return $moduleTemplate->renderResponse('Wizard');
        }

        $this->executeDataHandler();

        if (!empty($queryParams['returnUrl'])) {
            return new RedirectResponse(GeneralUtility::locationHeaderUrl($queryParams['returnUrl']), 303);
        }

        return new Response();

    }

    protected function executeDataHandler(): void
    {
        $this->dataHandler->start($this->dataMap, []);
        $this->dataHandler->process_datamap();
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception
     */
    public function disconnectAction(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $queryParams = $request->getQueryParams();

        DebugUtility::debug($queryParams);
        if (
            !empty($queryParams['disconnect']['page'])
            && !empty($queryParams['disconnect']['language'])
            && !empty($queryParams['disconnect']['tables'])
        ) {
            $tables = $queryParams['disconnect']['tables'];
            $language = (int)$queryParams['disconnect']['language'];
            $page = (int)$queryParams['disconnect']['page'];

            if (!empty($tables)) {
                foreach ($tables as $table) {
                    $this->dataMap[$table] = $this->transfusionRepository->fetchConnectedRecordsAndPrepareDataMap(
                        $table,
                        $language,
                        $page,
                        'disconnect'
                    );
                }
            }
        }

        $this->executeDataHandler();

        if (!empty($queryParams['returnUrl'])) {
            return new RedirectResponse(GeneralUtility::locationHeaderUrl($queryParams['returnUrl']), 303);
        }
        return $moduleTemplate->renderResponse();

    }

}
