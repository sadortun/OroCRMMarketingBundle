<?php

namespace Oro\Bundle\CampaignBundle\Tests\Unit\Form\Type;

use Oro\Bundle\CampaignBundle\Form\Type\InternalTransportSettingsType;
use Symfony\Component\Form\FormEvents;

class InternalTransportSettingsTypeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var InternalTransportSettingsType
     */
    protected $type;

    /**
     * Setup test env
     */
    protected function setUp()
    {
        $this->type = new InternalTransportSettingsType();
    }

    public function testGetName()
    {
        $this->assertEquals(InternalTransportSettingsType::NAME, $this->type->getName());
    }

    public function testConfigureOptions()
    {
        $resolver = $this->createMock('Symfony\Component\OptionsResolver\OptionsResolver');
        $resolver
            ->expects($this->once())
            ->method('setDefaults')
            ->with($this->isType('array'));

        $this->type->configureOptions($resolver);
    }

    public function testBuildForm()
    {
        $formBuilder = $this
            ->getMockBuilder('Symfony\Component\Form\FormBuilder')
            ->disableOriginalConstructor()
            ->getMock();
        $subscriber  = $this->createMock('Symfony\Component\EventDispatcher\EventSubscriberInterface');

        $formBuilder
            ->expects($this->once())
            ->method('add')
            ->will($this->returnSelf());

        $formBuilder
            ->expects($this->once())
            ->method('addEventSubscriber')
            ->with($this->equalTo($subscriber))
            ->will($this->returnSelf());

        $formBuilder
            ->expects($this->once())
            ->method('addEventListener')
            ->with($this->equalTo(FormEvents::PRE_SUBMIT), $this->isType('callable'))
            ->will($this->returnSelf());

        $this->type->addSubscriber($subscriber);
        $this->type->buildForm($formBuilder, []);
    }
}
