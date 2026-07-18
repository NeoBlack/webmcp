<?php

declare(strict_types=1);

namespace Neoblack\Webmcp\Controller;

use Neoblack\Webmcp\Dto\Filter;
use Neoblack\Webmcp\Service\StatisticsService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;

/**
 * Backend module "WebMCP": reads the request filter, delegates the aggregation
 * to the StatisticsService and renders the dashboard. No business logic or
 * database access here.
 */
final class DashboardController
{
    /** Selectable timeframes in days (labels are translated in the template). */
    private const TIMEFRAMES = [7, 30, 90];

    private const DEFAULT_DAYS = 30;

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly StatisticsService $statisticsService,
    ) {}

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $tool = (string)($params['tool'] ?? '');
        $client = (string)($params['client'] ?? '');
        $days = (int)($params['days'] ?? self::DEFAULT_DAYS);
        if (!in_array($days, self::TIMEFRAMES, true)) {
            $days = self::DEFAULT_DAYS;
        }

        $statistics = $this->statisticsService->collect(new Filter($tool, $client, $days));

        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle('WebMCP');
        $view->assignMultiple([
            'timeframes' => self::TIMEFRAMES,
            'current' => ['tool' => $tool, 'client' => $client, 'days' => $days],
            'statistics' => $statistics,
        ]);

        return $view->renderResponse('Dashboard/Overview');
    }
}
