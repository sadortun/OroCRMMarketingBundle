<?php

namespace Oro\Bundle\MarketingListBundle\Tests\Unit\Datagrid\Extension;

use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\Func;

use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datasource\Orm\OrmDatasource;
use Oro\Bundle\MarketingListBundle\Datagrid\ConfigurationProvider;
use Oro\Bundle\MarketingListBundle\Datagrid\Extension\MarketingListExtension;
use Oro\Bundle\MarketingListBundle\Entity\MarketingList;
use Oro\Bundle\MarketingListBundle\Model\MarketingListHelper;
use Oro\Bundle\SegmentBundle\Entity\Segment;

class MarketingListExtensionTest extends \PHPUnit_Framework_TestCase
{
    /** @var MarketingListExtension */
    protected $extension;

    /** @var \PHPUnit_Framework_MockObject_MockObject|MarketingListHelper */
    protected $marketingListHelper;

    protected function setUp()
    {
        $this->marketingListHelper = $this->createMock(MarketingListHelper::class);

        $this->extension = new MarketingListExtension($this->marketingListHelper);
    }

    public function testIsApplicableIncorrectDataSource()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|DatagridConfiguration $config */
        $config = $this->createMock(DatagridConfiguration::class);
        $config
            ->expects($this->once())
            ->method('isOrmDatasource')
            ->will($this->returnValue(false));

        $config
            ->expects($this->once())
            ->method('getName')
            ->will($this->returnValue('grid'));

        $this->assertFalse($this->extension->isApplicable($config));
    }

    public function testIsApplicableVisitTwice()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|DatagridConfiguration $config */
        $config = $this->createMock(DatagridConfiguration::class);
        $config
            ->expects($this->atLeastOnce())
            ->method('isOrmDatasource')
            ->will($this->returnValue(true));

        $config
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue(ConfigurationProvider::GRID_PREFIX . '1'));

        $this->marketingListHelper->expects($this->any())
            ->method('getMarketingListIdByGridName')
            ->with(ConfigurationProvider::GRID_PREFIX . '1')
            ->will($this->returnValue(1));

        $marketingList = new MarketingList();
        $marketingList->setSegment(new Segment())->setDefinition(json_encode(['filters' => ['filter' => 'dummy']]));

        $this->marketingListHelper->expects($this->any())
            ->method('getMarketingList')
            ->with(1)
            ->willReturn($marketingList);

        $this->assertTrue($this->extension->isApplicable($config));

        $qb = $this->getQbMock();

        /** @var \PHPUnit_Framework_MockObject_MockObject|OrmDatasource $dataSource */
        $dataSource = $this->createMock(OrmDatasource::class);

        $condition = new Andx();
        $condition->add('argument');

        $qb
            ->expects($this->once())
            ->method('getDQLParts')
            ->will($this->returnValue(['where' => $condition]));

        $dataSource
            ->expects($this->once())
            ->method('getQueryBuilder')
            ->will($this->returnValue($qb));

        $this->extension->visitDatasource($config, $dataSource);
        $this->assertFalse($this->extension->isApplicable($config));
    }

    /**
     * @dataProvider applicableDataProvider
     *
     * @param int|null    $marketingListId
     * @param object|null $marketingList
     * @param bool        $expected
     */
    public function testIsApplicable($marketingListId, $marketingList, $expected)
    {
        $gridName = 'test_grid';
        $config   = $this->assertIsApplicable($marketingListId, $marketingList, $gridName);

        $this->assertEquals($expected, $this->extension->isApplicable($config));
    }

    /**
     * @return array
     */
    public function applicableDataProvider()
    {
        $nonManualMarketingList = $this->createMock(MarketingList::class);
        $nonManualMarketingList->expects($this->once())
            ->method('isManual')
            ->will($this->returnValue(false));
        $nonManualMarketingList->expects($this->once())
            ->method('getDefinition')
            ->willReturn(json_encode(['filters' => ['filter' => 'dummy']]));

        $manualMarketingList = $this->createMock(MarketingList::class);
        $manualMarketingList->expects($this->once())
            ->method('isManual')
            ->will($this->returnValue(true));
        $manualMarketingList->expects($this->never())
            ->method('getDefinition');

        $nonManualMarketingListWithoutFilters = $this->createMock(MarketingList::class);
        $nonManualMarketingListWithoutFilters->expects($this->once())
            ->method('isManual')
            ->will($this->returnValue(false));
        $nonManualMarketingListWithoutFilters->expects($this->once())
            ->method('getDefinition')->willReturn(json_encode(['filters' => []]));

        return [
            [null, null, false],
            [1, null, false],
            [2, $manualMarketingList, false],
            [3, $nonManualMarketingList, true],
            [4, $nonManualMarketingListWithoutFilters, false],
        ];
    }

    /**
     * @param array $dqlParts
     * @param bool  $expected
     * @param bool
     *
     * @dataProvider dataSourceDataProvider
     */
    public function testVisitDatasource($dqlParts, $expected, $isObject = false)
    {
        $marketingListId        = 1;
        $nonManualMarketingList = $this->createMock(MarketingList::class);

        $nonManualMarketingList->expects($this->once())
            ->method('isManual')
            ->will($this->returnValue(false));

        $nonManualMarketingList->expects($this->once())->method('getDefinition')->willReturn(
            json_encode(['filters' => ['filter' => 'dummy']])
        );

        $gridName = 'test_grid';
        $config   = $this->assertIsApplicable($marketingListId, $nonManualMarketingList, $gridName);

        /** @var \PHPUnit_Framework_MockObject_MockObject|OrmDatasource $dataSource */
        $dataSource = $this->createMock(OrmDatasource::class);

        $qb = $this->getQbMock();

        if (!empty($dqlParts['where'])) {
            /** @var Andx $where */
            $where = $dqlParts['where'];
            $parts = $where->getParts();

            if ($expected && !$isObject) {
                $qb
                    ->expects($this->exactly(count($parts)))
                    ->method('andWhere');
            } elseif ($expected && $isObject) {
                $qb
                    ->expects(static::any())
                    ->method('andWhere');
            }

            $functionParts = array_filter(
                $parts,
                function ($part) {
                    return !is_string($part);
                }
            );

            if ($functionParts && $expected) {
                $qb
                    ->expects($this->once())
                    ->method('setParameter')
                    ->with($this->equalTo('marketingListId'), $this->equalTo($marketingListId));
            }
        }

        if ($expected) {
            $qb
                ->expects($this->once())
                ->method('getDQLParts')
                ->will($this->returnValue($dqlParts));

            $dataSource
                ->expects($this->once())
                ->method('getQueryBuilder')
                ->will($this->returnValue($qb));
        }

        $this->extension->visitDatasource($config, $dataSource);
    }

    /**
     * @param int|null    $marketingListId
     * @param object|null $marketingList
     * @param string      $gridName
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|DatagridConfiguration
     */
    protected function assertIsApplicable($marketingListId, $marketingList, $gridName)
    {
        $config = $this->createMock(DatagridConfiguration::class);
        $config
            ->expects($this->atLeastOnce())
            ->method('isOrmDatasource')
            ->will($this->returnValue(true));

        $config
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue($gridName));

        $this->marketingListHelper->expects($this->any())
            ->method('getMarketingListIdByGridName')
            ->with($gridName)
            ->will($this->returnValue($marketingListId));
        if ($marketingListId) {
            $this->marketingListHelper->expects($this->any())
                ->method('getMarketingList')
                ->with($marketingListId)
                ->will($this->returnValue($marketingList));
        } else {
            $this->marketingListHelper->expects($this->never())
                ->method('getMarketingList');
        }

        return $config;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|QueryBuilder
     */
    protected function getQbMock()
    {
        $em = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();

        $qb = $this
            ->getMockBuilder('Doctrine\ORM\QueryBuilder')
            ->setConstructorArgs([$em])
            ->getMock();

        $qb
            ->expects($this->any())
            ->method('from')
            ->will($this->returnSelf());

        $qb
            ->expects($this->any())
            ->method('leftJoin')
            ->will($this->returnSelf());

        $qb
            ->expects($this->any())
            ->method('select')
            ->will($this->returnSelf());

        $qb
            ->expects($this->any())
            ->method('andWhere')
            ->will($this->returnSelf());

        $expr = $this
            ->getMockBuilder('Doctrine\ORM\Query\Expr')
            ->disableOriginalConstructor()
            ->getMock();

        $qb
            ->expects($this->any())
            ->method('expr')
            ->will($this->returnValue($expr));

        $orX = $this
            ->getMockBuilder('Doctrine\ORM\Query\Expr')
            ->disableOriginalConstructor()
            ->getMock();

        $expr
            ->expects($this->any())
            ->method('orX')
            ->will($this->returnValue($orX));

        $expr
            ->expects($this->any())
            ->method('exists')
            ->will($this->returnValue($orX));

        return $qb;
    }

    /**
     * @return array
     */
    public function dataSourceDataProvider()
    {
        return [
            [['where' => []], true],
            [['where' => new Andx()], true],
            [['where' => new Andx(['test'])], true],
            [['where' => new Andx([new Func('func condition', ['argument'])])], true, true],
            [['where' => new Andx(['test', new Func('func condition', ['argument'])])], true, true]
        ];
    }

    public function testGetPriority()
    {
        $this->assertInternalType('integer', $this->extension->getPriority());
    }
}
