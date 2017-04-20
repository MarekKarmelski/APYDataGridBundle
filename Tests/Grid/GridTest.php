<?php

namespace APY\DataGridBundle\Grid\Tests;

use APY\DataGridBundle\Grid\Column\Column;
use APY\DataGridBundle\Grid\Columns;
use APY\DataGridBundle\Grid\Grid;
use APY\DataGridBundle\Grid\GridConfigInterface;
use APY\DataGridBundle\Grid\Source\Entity;
use APY\DataGridBundle\Grid\Source\Source;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class GridTest extends TestCase
{
    /**
     * @var Grid
     */
    private $grid;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $router;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $container;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $authChecker;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $request;

    public function testInitializeWithoutAnyConfiguration()
    {
        $this->arrange();

        $column = $this->createMock(Column::class);
        $this->grid->addColumn($column);

        $this->grid->initialize();

        $this->assertAttributeEquals(false, 'persistence', $this->grid);
        $this->assertAttributeEmpty('routeParameters', $this->grid);
        $this->assertAttributeEmpty('routeUrl', $this->grid);
        $this->assertAttributeEmpty('source', $this->grid);
        $this->assertAttributeEmpty('defaultOrder', $this->grid);
        $this->assertAttributeEmpty('limits', $this->grid);
        $this->assertAttributeEmpty('maxResults', $this->grid);
        $this->assertAttributeEmpty('page', $this->grid);

        $this->router->expects($this->never())->method($this->anything());
        $column->expects($this->never())->method($this->anything());
    }

    public function testInitializePersistence()
    {
        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('isPersisted')->willReturn(true);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        $this->assertAttributeEquals(true, 'persistence', $this->grid);
    }

    public function testInitializeRouteParams()
    {
        $routeParams = ['foo' => 1, 'bar' => 2];

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getRouteParameters')->willReturn($routeParams);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        $this->assertAttributeEquals($routeParams, 'routeParameters', $this->grid);
    }

    public function testInitializeRouteUrlWithoutParams()
    {
        $route = 'vendor.bundle.controller.route_name';
        $routeParams = ['foo' => 1, 'bar' => 2];
        $url = 'aRandomUrl';

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getRouteParameters')->willReturn($routeParams);
        $gridConfig->method('getRoute')->willReturn($route);

        $this->arrange($gridConfig);
        $this->router->method('generate')->with($route, $routeParams)->willReturn($url);

        $this->grid->initialize();

        $this->assertAttributeEquals($url, 'routeUrl', $this->grid);
    }

    public function testInitializeRouteUrlWithParams()
    {
        $route = 'vendor.bundle.controller.route_name';
        $url = 'aRandomUrl';
        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getRoute')->willReturn($route);

        $this->arrange($gridConfig);
        $this->router->method('generate')->with($route, null)->willReturn($url);

        $this->grid->initialize();

        $this->assertAttributeEquals($url, 'routeUrl', $this->grid);
    }

    public function testInizializeColumnsNotFilterableAsGridIsNotFilterable()
    {
        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('isFilterable')->willReturn(false);

        $column = $this->createMock(Column::class);

        $this->arrange($gridConfig);
        $this->grid->addColumn($column);

        $this->grid->initialize();

        $column->expects($this->any())->method('setFilterable')->with(false);
    }

    public function testInizializeColumnsNotSortableAsGridIsNotSortable()
    {
        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('isSortable')->willReturn(false);

        $column = $this->createMock(Column::class);

        $this->arrange($gridConfig);
        $this->grid->addColumn($column);

        $this->grid->initialize();

        $column->expects($this->any())->method('setSortable')->with(false);
    }

    public function testInitializeNotEntitySource()
    {
        $source = $this->createMock(Source::class);

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getSource')->willReturn($source);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        $source->expects($this->any())->method('initialise')->with($gridConfig);
    }

    public function testInitializeEntitySourceWithoutGroupByFunction()
    {
        $source = $this->createMock(Entity::class);

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getSource')->willReturn($source);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        $source->expects($this->any())->method('initialise')->with($gridConfig);
        $source->expects($this->never())->method('setGroupBy');
    }

    public function testInitializeEntitySourceWithoutGroupByScalarValue()
    {
        $groupByField = 'groupBy';

        $source = $this->createMock(Entity::class);

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getSource')->willReturn($source);
        $gridConfig->method('getGroupBy')->willReturn($groupByField);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        $source->expects($this->any())->method('initialise')->with($gridConfig);
        $source->expects($this->any())->method('setGroupBy')->with([$groupByField]);
    }

    public function testInitializeEntitySourceWithoutGroupByArrayValues()
    {
        $groupByArray = ['groupByFoo', 'groupByBar'];

        $source = $this->createMock(Entity::class);

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getSource')->willReturn($source);
        $gridConfig->method('getGroupBy')->willReturn($groupByArray);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        $source->expects($this->any())->method('initialise')->with($gridConfig);
        $source->expects($this->any())->method('setGroupBy')->with($groupByArray);
    }

    public function testInizializeDefaultOrder()
    {
        $sortBy = 'SORTBY';
        $orderBy = 'ORDERBY';

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getSortBy')->willReturn($sortBy);
        $gridConfig->method('getOrder')->willReturn($orderBy);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        $this->assertAttributeEquals(sprintf('%s|%s', $sortBy, strtolower($orderBy)), 'defaultOrder', $this->grid);
    }

    public function testInizializeDefaultOrderWithoutOrder()
    {
        $sortBy = 'SORTBY';

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getSortBy')->willReturn($sortBy);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        // @todo: is this an admitted case?
        $this->assertAttributeEquals("$sortBy|", 'defaultOrder', $this->grid);
    }

    public function testInizializeLimits()
    {
        $maxPerPage = 10;

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getMaxPerPage')->willReturn($maxPerPage);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        $this->assertAttributeEquals([$maxPerPage => (string) $maxPerPage], 'limits', $this->grid);
    }

    public function testInizializeMaxResults()
    {
        $maxResults = 50;

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getMaxResults')->willReturn($maxResults);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        $this->assertAttributeEquals($maxResults, 'maxResults', $this->grid);
    }

    public function testInizializePage()
    {
        $page = 1;

        $gridConfig = $this->createMock(GridConfigInterface::class);
        $gridConfig->method('getPage')->willReturn($page);

        $this->arrange($gridConfig);

        $this->grid->initialize();

        $this->assertAttributeEquals($page, 'page', $this->grid);
    }

    public function testSetSourceOneThanOneTime()
    {
        $source = $this->createMock(Source::class);

        // @todo maybe this exception should not be \InvalidArgumentException?
        $this->expectException(\InvalidArgumentException::class);

        $this->grid->setSource($source);
        $this->grid->setSource($source);
    }

    public function testSetSource()
    {
        $source = $this->createMock(Source::class);
        $source->expects($this->once())->method('initialise')->with($this->container);
        $source->expects($this->once())->method('getColumns')->with($this->isInstanceOf(Columns::class));

        $this->grid->setSource($source);

        $this->assertAttributeEquals($source, 'source', $this->grid);
    }

    public function testGetSource()
    {
        $source = $this->createMock(Source::class);

        $this->grid->setSource($source);

        $this->assertEquals($source, $this->grid->getSource());
    }

    public function testHandlRequest()
    {
        // @todo: split in more than one test if needed
    }

    public function testIsReadyForRedirect()
    {
        // @todo: split in more than one test if needed
    }

    public function setUp()
    {
        $this->arrange($this->createMock(GridConfigInterface::class));
    }

    /**
     * @param $gridConfigInterface
     */
    private function arrange($gridConfigInterface = null)
    {
        $container = $this->getMockBuilder(Container::class)->disableOriginalConstructor()->getMock();
        $this->container = $container;

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(new Request());
        $this->router = $this->getMockBuilder(Router::class)->disableOriginalConstructor()->getMock();
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->authChecker = $authChecker;
        $container->method('get')->withConsecutive(
            ['router'], ['request_stack'], ['security.authorization_checker']
        )->willReturnOnConsecutiveCalls($this->router, $requestStack, $authChecker);

        $this->grid = new Grid($container, 'id', $gridConfigInterface);
    }
}
