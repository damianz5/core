<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace Ibexa\Core\MVC\Symfony\View;

use Ibexa\Contracts\Core\Repository\Repository;
use Ibexa\Contracts\Core\Repository\Values\Content\Content;
use Ibexa\Contracts\Core\Repository\Values\Content\Location;
use Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface;
use Ibexa\Core\MVC\Symfony\Event\PreContentViewEvent;
use Ibexa\Core\MVC\Symfony\MVCEvents;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

class Manager implements ViewManagerInterface
{
    /** @var \Twig\Environment */
    protected $templateEngine;

    /** @var \Psr\Log\LoggerInterface */
    protected $logger;

    /**
     * @var array Array indexed by priority.
     *            Each priority key is an array of Content View Provider objects having this priority.
     *            The highest priority number is the highest priority
     */
    protected $contentViewProviders = [];

    /**
     * @var array Array indexed by priority.
     *            Each priority key is an array of Location View Provider objects having this priority.
     *            The highest priority number is the highest priority
     */
    protected $locationViewProviders = [];

    /** @var \Ibexa\Core\MVC\Symfony\View\Provider\Content[] */
    protected $sortedContentViewProviders;

    /** @var \Ibexa\Core\MVC\Symfony\View\Provider\Location[] */
    protected $sortedLocationViewProviders;

    /** @var \Ibexa\Contracts\Core\Repository\Repository */
    protected $repository;

    /** @var \Symfony\Component\EventDispatcher\EventDispatcherInterface */
    protected $eventDispatcher;

    /**
     * The base layout template to use when the view is requested to be generated
     * outside of the pagelayout.
     *
     * @var string
     */
    protected $viewBaseLayout;

    /** @var \Ibexa\Contracts\Core\SiteAccess\ConfigResolverInterface */
    protected $configResolver;

    /** @var \Ibexa\Core\MVC\Symfony\View\Configurator */
    private $viewConfigurator;

    public function __construct(
        Environment $templateEngine,
        EventDispatcherInterface $eventDispatcher,
        Repository $repository,
        ConfigResolverInterface $configResolver,
        $viewBaseLayout,
        $viewConfigurator,
        LoggerInterface $logger = null
    ) {
        $this->templateEngine = $templateEngine;
        $this->eventDispatcher = $eventDispatcher;
        $this->repository = $repository;
        $this->configResolver = $configResolver;
        $this->viewBaseLayout = $viewBaseLayout;
        $this->logger = $logger;
        $this->viewConfigurator = $viewConfigurator;
    }

    /**
     * Helper for {@see addContentViewProvider()} and {@see addLocationViewProvider()}.
     *
     * @param array $property
     * @param \Ibexa\Core\MVC\Symfony\View\ViewProvider $viewProvider
     * @param int $priority
     */
    private function addViewProvider(&$property, $viewProvider, $priority)
    {
        $priority = (int)$priority;
        if (!isset($property[$priority])) {
            $property[$priority] = [];
        }

        $property[$priority][] = $viewProvider;
    }

    /**
     * Registers $viewProvider as a valid content view provider.
     * When this view provider will be called in the chain depends on $priority. The highest $priority is, the earliest the router will be called.
     *
     * @param \Ibexa\Core\MVC\Symfony\View\ViewProvider $viewProvider
     * @param int $priority
     */
    public function addContentViewProvider(ViewProvider $viewProvider, $priority = 0)
    {
        $this->addViewProvider($this->contentViewProviders, $viewProvider, $priority);
    }

    /**
     * Registers $viewProvider as a valid location view provider.
     * When this view provider will be called in the chain depends on $priority. The highest $priority is, the earliest the router will be called.
     *
     * @param \Ibexa\Core\MVC\Symfony\View\ViewProvider $viewProvider
     * @param int $priority
     */
    public function addLocationViewProvider(ViewProvider $viewProvider, $priority = 0)
    {
        $this->addViewProvider($this->locationViewProviders, $viewProvider, $priority);
    }

    /**
     * @return \Ibexa\Core\MVC\Symfony\View\ViewProvider[]
     */
    public function getAllContentViewProviders()
    {
        if (empty($this->sortedContentViewProviders)) {
            $this->sortedContentViewProviders = $this->sortViewProviders($this->contentViewProviders);
        }

        return $this->sortedContentViewProviders;
    }

    /**
     * @return \Ibexa\Core\MVC\Symfony\View\ViewProvider[]
     */
    public function getAllLocationViewProviders()
    {
        if (empty($this->sortedLocationViewProviders)) {
            $this->sortedLocationViewProviders = $this->sortViewProviders($this->locationViewProviders);
        }

        return $this->sortedLocationViewProviders;
    }

    /**
     * Sort the registered view providers by priority.
     * The highest priority number is the highest priority (reverse sorting).
     *
     * @param array $property view providers to sort
     *
     * @return \Ibexa\Core\MVC\Symfony\View\Provider\Content[]|\Ibexa\Core\MVC\Symfony\View\Provider\Location[]
     */
    protected function sortViewProviders($property)
    {
        $sortedViewProviders = [];
        krsort($property);

        foreach ($property as $viewProvider) {
            $sortedViewProviders = array_merge($sortedViewProviders, $viewProvider);
        }

        return $sortedViewProviders;
    }

    /**
     * Renders $content by selecting the right template.
     * $content will be injected in the selected template.
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Content $content
     * @param string $viewType Variation of display for your content. Default is 'full'.
     * @param array $parameters Parameters to pass to the template called to
     *        render the view. By default, it's empty. 'content' entry is
     *        reserved for the Content that is rendered.
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    public function renderContent(Content $content, $viewType = ViewManagerInterface::VIEW_TYPE_FULL, $parameters = [])
    {
        $view = new ContentView(null, $parameters, $viewType);
        $view->setContent($content);
        if (isset($parameters['location'])) {
            $view->setLocation($parameters['location']);
        }

        $this->viewConfigurator->configure($view);

        if ($view->getTemplateIdentifier() === null) {
            throw new RuntimeException('Unable to find a template for #' . $content->contentInfo->id);
        }

        return $this->renderContentView($view, $parameters);
    }

    /**
     * Renders $location by selecting the right template for $viewType.
     * $content and $location will be injected in the selected template.
     *
     * @param \Ibexa\Contracts\Core\Repository\Values\Content\Location $location
     * @param string $viewType Variation of display for your content. Default is 'full'.
     * @param array $parameters Parameters to pass to the template called to
     *        render the view. By default, it's empty. 'location' and 'content'
     *        entries are reserved for the Location (and its Content) that is
     *        viewed.
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    public function renderLocation(Location $location, $viewType = ViewManagerInterface::VIEW_TYPE_FULL, $parameters = [])
    {
        if (!isset($parameters['location'])) {
            $parameters['location'] = $location;
        }

        if (!isset($parameters['content'])) {
            $parameters['content'] = $this->repository->getContentService()->loadContentByContentInfo(
                $location->contentInfo,
                $this->configResolver->getParameter('languages')
            );
        }

        return $this->renderContent($parameters['content'], $viewType, $parameters);
    }

    /**
     * Renders passed ContentView object via the template engine.
     * If $view's template identifier is a closure, then it is called directly and the result is returned as is.
     *
     * @param \Ibexa\Core\MVC\Symfony\View\View $view
     * @param array $defaultParams
     *
     * @return string
     */
    public function renderContentView(View $view, array $defaultParams = [])
    {
        $defaultParams['view_base_layout'] = $this->viewBaseLayout;
        $view->addParameters($defaultParams);
        $this->eventDispatcher->dispatch(new PreContentViewEvent($view), MVCEvents::PRE_CONTENT_VIEW);

        $templateIdentifier = $view->getTemplateIdentifier();
        $params = $view->getParameters();
        if ($templateIdentifier instanceof \Closure) {
            return $templateIdentifier($params);
        }

        return $this->templateEngine->render($templateIdentifier, $params);
    }
}

class_alias(Manager::class, 'eZ\Publish\Core\MVC\Symfony\View\Manager');
