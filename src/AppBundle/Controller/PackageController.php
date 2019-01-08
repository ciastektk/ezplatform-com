<?php

namespace AppBundle\Controller;

use AppBundle\Form\PackageOrderType;
use AppBundle\Form\PackageSearchType;
use AppBundle\QueryType\PackagesQueryType;
use AppBundle\Service\Packagist\PackagistServiceProviderInterface;
use eZ\Bundle\EzPublishCoreBundle\Routing\DefaultRouter;
use eZ\Bundle\EzPublishCoreBundle\Routing\UrlAliasRouter;
use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\API\Repository\SearchService;
use eZ\Publish\Core\Pagination\Pagerfanta\ContentSearchHitAdapter;
use Netgen\TagsBundle\API\Repository\TagsService;
use Netgen\TagsBundle\API\Repository\Values\Tags\Tag;
use Pagerfanta\Pagerfanta;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Templating\EngineInterface;

class PackageController
{
    private const DEFAULT_ORDER_CLAUSE = 'default';

    private const DEFAULT_PACKAGE_CATEGORY = 'all';

    /**
     * @var \Symfony\Bundle\TwigBundle\TwigEngine
     */
    private $templating;

    /**
     * @var \eZ\Publish\API\Repository\SearchService
     */
    private $searchService;

    /**
     * @var \eZ\Bundle\EzPublishCoreBundle\Routing\UrlAliasRouter
     */
    private $aliasRouter;

    /**
     * @var \AppBundle\QueryType\PackagesQueryType
     */
    private $packagesQueryType;

    /**
     * @var \AppBundle\Service\Packagist\PackagistServiceProviderInterface;
     */
    private $packagistServiceProvider;

    /**
     * @var \Symfony\Component\Form\FormFactory
     */
    private $formFactory;

    /**
     * @var \eZ\Bundle\EzPublishCoreBundle\Routing\DefaultRouter
     */
    private $router;

    /**
     * @var \eZ\Publish\API\Repository\LocationService
     */
    private $locationService;

    /**
     * @var \Netgen\TagsBundle\API\Repository\TagsService;
     */
    private $tagsService;

    /**
     * @var int
     */
    private $packageListLocationId;

    /**
     * @var int
     */
    private $packageListCardsLimit;

    /**
     * @var int
     */
    private $packageCategoriesParentTagId;

    /**
     * PackageBundleController constructor.
     * @param EngineInterface $templating
     * @param SearchService $searchService
     * @param UrlAliasRouter $aliasRouter
     * @param PackagesQueryType $packagesQueryType
     * @param PackagistServiceProviderInterface $packagistServiceProvider
     * @param FormFactory $formFactory
     * @param DefaultRouter $router
     * @param TagsService $tagsService
     * @param LocationService $locationService
     * @param int $packageListLocationId
     * @param int $packageListCardsLimit
     * @param int $packageCategoriesParentTagId
     */
    public function __construct(
        EngineInterface $templating,
        SearchService $searchService,
        UrlAliasRouter $aliasRouter,
        PackagesQueryType $packagesQueryType,
        PackagistServiceProviderInterface $packagistServiceProvider,
        FormFactory $formFactory,
        DefaultRouter $router,
        TagsService $tagsService,
        LocationService $locationService,
        int $packageListLocationId,
        int $packageListCardsLimit,
        int $packageCategoriesParentTagId
    ) {
        $this->templating = $templating;
        $this->searchService = $searchService;
        $this->aliasRouter = $aliasRouter;
        $this->packagesQueryType = $packagesQueryType;
        $this->packagistServiceProvider = $packagistServiceProvider;
        $this->formFactory = $formFactory;
        $this->router = $router;
        $this->tagsService = $tagsService;
        $this->locationService = $locationService;
        $this->packageListLocationId = $packageListLocationId;
        $this->packageListCardsLimit = $packageListCardsLimit;
        $this->packageCategoriesParentTagId = $packageCategoriesParentTagId;
    }

    /**
     * Renders full view `package_list`.
     *
     * @param Request $request
     * @param string $category
     * @param int $page
     * @param string $order
     * @param string $searchText
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Twig\Error\Error
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function showPackageListAction(Request $request, string $category = self::DEFAULT_PACKAGE_CATEGORY, $page = 1, $order = self::DEFAULT_ORDER_CLAUSE, $searchText = '')
    {
        $orderForm = $this->formFactory->create(PackageOrderType::class);
        $orderForm->handleRequest($request);
        if ($orderForm->isSubmitted() && $orderForm->isValid()) {
            $order = $orderForm->get('order')->getData();
        }

        $searchForm = $this->formFactory->create(PackageSearchType::class);
        $searchForm->handleRequest($request);
        if ($searchForm->isSubmitted() || $searchForm->isValid()) {
            $searchText = $searchForm->get('search')->getData();
        }

        $tagId = null;

        if ($category && $category !== self::DEFAULT_PACKAGE_CATEGORY) {
            $tagId = $this->getCategoryTagId($category);
        }

        $content = $this->locationService->loadLocation($this->packageListLocationId)->getContent();

        // Create pager
        $adapter = new ContentSearchHitAdapter($this->getPackagesQuery(0, $order, $searchText, $tagId), $this->searchService);

        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage($this->packageListCardsLimit);
        $pagerfanta->setCurrentPage($page);

        // Get list of packages using already fetched data from pager
        $packages = $this->getList($adapter->getSlice(($page - 1) * $this->packageListCardsLimit, $this->packageListCardsLimit));

        return $this->templating->renderResponse('@ezdesign/full/package_list.html.twig', [
            'items' => $packages,
            'content' => $content,
            'order' => $order,
            'pager' => $pagerfanta,
            'searchText' => $searchText,
            'packageCategories' => $this->getPackageCategoriesList($this->packageCategoriesParentTagId),
            'selectedPackageCategory' => $category !== self::DEFAULT_PACKAGE_CATEGORY ? mb_strtolower($category) : ''
        ]);
    }

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Twig\Error\Error
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function getPackageDetailsAction(Request $request)
    {
        $content = $this->locationService->loadLocation($request->get('locationId'))->getContent();

        return $this->templating->renderResponse('@ezdesign/full/package.html.twig', [
            'content' => $content,
            'package' => $this->packagistServiceProvider->getPackageDetails($content->getName())
        ]);
    }

    /**
     * Validates search query.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function searchPackagesAction(Request $request)
    {
        $searchForm = $this->formFactory->create(PackageSearchType::class);
        $searchForm->handleRequest($request);

        if (!$searchForm->isSubmitted() || !$searchForm->isValid()) {
            return new RedirectResponse($this->aliasRouter->generate('ez_urlalias',
                ['locationId' => $this->packageListLocationId], UrlGeneratorInterface::ABSOLUTE_PATH));
        }
        $searchText = $searchForm->get('search')->getData();

        return new RedirectResponse($this->router->generate('_ezplatform_package_list_search', [
            'page' => 1,
            'order' => self::DEFAULT_ORDER_CLAUSE,
            'searchText' => $searchText,
            'category' => self::DEFAULT_PACKAGE_CATEGORY
        ]));
    }

    /**
     * @param string $order
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Twig\Error\Error
     */
    public function renderSortOrderPackageForm($order)
    {
        $sortOrderPackageForm = $this->formFactory->create(PackageOrderType::class, [
            'order' => $order,
        ]);

        return $this->templating->renderResponse(
            '@ezdesign/form/package_sort_order.html.twig',
            [
                'sortOrderPackageForm' => $sortOrderPackageForm->createView(),
            ]
        )->setPrivate();
    }

    /**
     * @param string $searchText
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Twig\Error\Error
     */
    public function renderSearchPackageForm($searchText)
    {
        $searchPackageForm = $this->formFactory->create(PackageSearchType::class, [
            'search' => $searchText,
        ]);

        return $this->templating->renderResponse(
            '@ezdesign/form/package_search.html.twig',
            [
                'searchPackageForm' => $searchPackageForm->createView(),
            ]
        )->setPrivate();
    }

    /**
     * Returns list with package categories
     *
     * @var int $categoryId
     *
     * @return \Netgen\TagsBundle\API\Repository\Values\Tags\Tag[]
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    private function getPackageCategoriesList(int $categoryId): array
    {
        $tag = $this->tagsService->loadTag($categoryId);

        return $this->tagsService->loadTagChildren($tag);
    }

    /**
     * @param string $category
     * @param string $language
     *
     * @return int
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    private function getCategoryTagId(string $category, string $language = ''): int
    {
        $tags = $this->tagsService->loadTagsByKeyword($category, $language);

        $tags = array_filter($tags, function(Tag $tag) {
            return mb_strtolower($tag->parentTagId) === mb_strtolower($this->packageCategoriesParentTagId);
        });

        $tag = reset($tags);

        return $tag->id;
    }

    /**
     * @param int $offset
     * @param null $order
     * @param string $searchText
     * @param int $tagId
     *
     * @return \eZ\Publish\API\Repository\Values\Content\LocationQuery
     */
    private function getPackagesQuery($offset = 0, $order = null, $searchText = '', $tagId = null)
    {
        return $this->packagesQueryType->getQuery([
            'parent_location_id' => $this->packageListLocationId,
            'limit' => $this->packageListCardsLimit,
            'offset' => $offset,
            'order' => $order,
            'search' => $searchText,
            'tag_id' => $tagId
        ]);
    }

    /**
     * Returns list of packages with package details for given $searchResult set.
     *
     * @param array $searchHits
     *
     * @return array
     */
    private function getList(array $searchHits)
    {
        $packages = [];
        foreach ($searchHits as $searchHit) {
            $packages[] = [
                'package' => $searchHit,
                'packageDetails' => $this->packagistServiceProvider->getPackageDetails($searchHit->valueObject->contentInfo->name),
            ];
        }

        return $packages;
    }
}