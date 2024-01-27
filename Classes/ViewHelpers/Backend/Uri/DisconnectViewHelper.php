<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace T3thi\Transfusion\ViewHelpers\Backend\Uri;

use Closure;
use InvalidArgumentException;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * ViewHelper to create links for the disconnection of a single language column on a given page
 *
 * It needs the IDs of the page and the language as well as a table to be disconnected
 * Default currently is tt_content only - more tables will be supported in the future
 */
final class DisconnectViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    /**
     * @param array<string, mixed> $arguments
     *
     * @throws InvalidArgumentException
     * @throws RouteNotFoundException
     */
    public static function renderStatic(
            array $arguments,
            Closure $renderChildrenClosure,
            RenderingContextInterface $renderingContext
    ): string {
        if ($arguments['page'] < 1) {
            throw new InvalidArgumentException(
                    'Page must be a positive integer, ' . $arguments['page'] . ' given.',
                    1706372241
            );
        }
        if ($arguments['language'] < 1) {
            throw new InvalidArgumentException(
                    'Language must be a positive integer, ' . $arguments['language'] . ' given.', 1706372241
            );
        }
        if (empty($arguments['tables'])) {
            $arguments['tables'] = ['tt_content'];
        }
        foreach ($arguments['tables'] as $table) {
            if (empty($GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'])) {
                throw new InvalidArgumentException(
                        'Table must be translatable and provide a transOrigPointerField to be connected. This table can\'t be disconnected',
                        1706372241
                );
            }
        }
        if (empty($arguments['returnUrl'])) {
            /** @var RenderingContext $renderingContext */
            $request = $renderingContext->getRequest();
            $arguments['returnUrl'] = $request->getAttribute('normalizedParams')->getRequestUri();
        }

        $params = [
                'disconnect' => [$arguments['page'] => [$arguments['language'] => $arguments['tables']]],
                'redirect' => $arguments['returnUrl'],
        ];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        return (string)$uriBuilder->buildUriFromRoute('language_disconnect', $params);
    }

    public function initializeArguments(): void
    {
        $this->registerArgument('page', 'int', 'uid of record to be edited, 0 for creation', true);
        $this->registerArgument('language', 'int', 'id of the language to be disconnected', true);
        $this->registerArgument('tables', 'array', 'target database tables', false, ['tt_content']);
        $this->registerArgument('returnUrl', 'string', 'return to this URL after closing the edit dialog', false, '');
    }
}
