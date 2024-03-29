<?php

declare(strict_types=1);

namespace T3thi\Transfusion\Controller;

use Doctrine\DBAL\Exception;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use T3thi\Transfusion\Domain\Repository\TransfusionRepository;
use TYPO3\CMS\Backend\Exception\AccessDeniedException;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
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
    protected BackendUserAuthentication $backendUser;
    protected array $cmdMap = [];
    protected array $dataMap = [];
    protected array $fullDataMap = [];

    public function __construct()
    {
        $this->backendUser = $this->getBackendUser();
        $this->transfusionRepository = GeneralUtility::makeInstance(TransfusionRepository::class);
        $this->dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $this->moduleTemplateFactory = GeneralUtility::makeInstance(ModuleTemplateFactory::class);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception
     * @throws AccessDeniedException
     */
    public function connectAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->checkAccess();

        $queryParams = $request->getQueryParams();
        $this->cmdMap = $request->getParsedBody()['cmdMap'] ?? [];
        $this->dataMap = $request->getParsedBody()['dataMap'] ?? [];

        if (!empty($this->cmdMap) || !empty($this->dataMap)) {
            $this->executeDataHandler();
        }

        if (
            empty($queryParams['connect']['page'])
            || empty($queryParams['connect']['language'])
            || empty($queryParams['connect']['tables'])
        ) {
            return new Response();
        }

        $needsInteraction = false;
        $moduleTemplate = $this->moduleTemplateFactory->create($request);

        $tables = $queryParams['connect']['tables'];
        $language = (int)$queryParams['connect']['language'];
        $page = (int)$queryParams['connect']['page'];

        foreach ($tables as $table) {
            $disconnectedRecords = $this->transfusionRepository->fetchDisconnectedRecordsAndPrepareDataMap(
                $table,
                $language,
                $page,
                'connect',
                $this->fullDataMap,
            );
            $this->dataMap[$table] = $disconnectedRecords['dataMap'] ?? [];
            if ($disconnectedRecords['needsInteraction']) {
                $needsInteraction = true;
            }
        }

        if ($needsInteraction) {
            $moduleTemplate->getDocHeaderComponent()->setMetaInformation(
                BackendUtility::readPageAccess(
                    $page,
                    $this->backendUser->getPagePermsClause(Permission::PAGE_SHOW)
                )
            );
            $moduleTemplate->assignMultiple(
                [
                    'docHeader' => $moduleTemplate->getDocHeaderComponent()->docHeaderContent(),
                    'workspace' => $this->backendUser->workspace,
                    'connect' => $queryParams['connect'],
                    'returnUrl' => $queryParams['returnUrl'] ?? '',
                    'defaultLanguageRecords' => $this->transfusionRepository->fetchDefaultLanguageRecordsAndConnections(
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

    /**
     * @throws AccessDeniedException
     */
    protected function checkAccess(): void
    {
        if (empty($this->backendUser->loginType)) {
            throw new AccessDeniedException(
                'You need to be logged in as a TYPO3 backend user to modify translation connections',
                1706372241
            );
        }
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    protected function executeDataHandler(): void
    {
        $this->dataHandler->start($this->dataMap, $this->cmdMap);
        $this->dataHandler->process_cmdmap();
        $this->dataHandler->process_datamap();
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception
     * @throws AccessDeniedException
     */
    public function disconnectAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->checkAccess();

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $queryParams = $request->getQueryParams();

        if (
            !empty($queryParams['disconnect']['page'])
            && !empty($queryParams['disconnect']['language'])
            && !empty($queryParams['disconnect']['tables'])
        ) {
            $tables = $queryParams['disconnect']['tables'];
            $language = (int)$queryParams['disconnect']['language'];
            $page = (int)$queryParams['disconnect']['page'];

            foreach ($tables as $table) {
                $this->dataMap[$table] = $this->transfusionRepository->fetchConnectedRecordsAndPrepareDataMap(
                    $table,
                    $language,
                    $page,
                    'disconnect'
                );
            }
        }

        $this->executeDataHandler();

        if (!empty($queryParams['returnUrl'])) {
            return new RedirectResponse(GeneralUtility::locationHeaderUrl($queryParams['returnUrl']), 303);
        }
        return $moduleTemplate->renderResponse();

    }
}
