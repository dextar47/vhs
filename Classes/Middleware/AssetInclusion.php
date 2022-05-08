<?php
declare(strict_types=1);

namespace FluidTYPO3\Vhs\Middleware;

use FluidTYPO3\Vhs\Service\AssetService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class AssetInclusion implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $body = $response->getBody();
        $body->rewind();
        $contents = $body->getContents();

        GeneralUtility::makeInstance(ObjectManager::class)->get(AssetService::class)->buildAllUncached([], $GLOBALS['TSFE'], $contents);

        $stream = fopen('php://temp', 'rw+');
        fputs($stream, $contents);

        $response = $response->withBody(new Stream($stream));

        // Copied from \TYPO3\CMS\Frontend\Middleware\ContentLengthResponseHeader to ensure proper content-length.
        if ($GLOBALS['TSFE'] instanceof TypoScriptFrontendController) {
            if (
                (!isset($GLOBALS['TSFE']->config['config']['enableContentLengthHeader']) || $GLOBALS['TSFE']->config['config']['enableContentLengthHeader'])
                && !$GLOBALS['TSFE']->isBackendUserLoggedIn() && !($GLOBALS['TYPO3_CONF_VARS']['FE']['debug'] ?? false)
                && !($GLOBALS['TSFE']->config['config']['debug'] ?? false) && !$GLOBALS['TSFE']->doWorkspacePreview()
            ) {
                $response = $response->withHeader('Content-Length', (string)$response->getBody()->getSize());
            }
        }
        return $response;
    }
}
