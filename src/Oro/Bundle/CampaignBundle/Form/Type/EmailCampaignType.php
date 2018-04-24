<?php

namespace Oro\Bundle\CampaignBundle\Form\Type;

use Oro\Bundle\CampaignBundle\Entity\EmailCampaign;
use Oro\Bundle\CampaignBundle\Form\Type\CampaignSelectType;
use Oro\Bundle\CampaignBundle\Form\Type\EmailTransportSelectType;
use Oro\Bundle\CampaignBundle\Provider\EmailTransportProvider;
use Oro\Bundle\FormBundle\Form\Type\OroDateTimeType;
use Oro\Bundle\FormBundle\Form\Type\OroResizeableRichTextType;
use Oro\Bundle\MarketingListBundle\Form\Type\MarketingListSelectType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EmailCampaignType extends AbstractType
{
    /**
     * @var EventSubscriberInterface[]
     */
    protected $subscribers = [];

    /**
     * @var EmailTransportProvider
     */
    protected $emailTransportProvider;

    /**
     * @param EmailTransportProvider $emailTransportProvider
     */
    public function __construct(EmailTransportProvider $emailTransportProvider)
    {
        $this->emailTransportProvider = $emailTransportProvider;
    }

    /**
     * @param EventSubscriberInterface $subscriber
     */
    public function addSubscriber(EventSubscriberInterface $subscriber)
    {
        $this->subscribers[] = $subscriber;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        foreach ($this->subscribers as $subscriber) {
            $builder->addEventSubscriber($subscriber);
        }

        $builder
            ->add(
                'name',
                TextType::class,
                ['label' => 'oro.campaign.emailcampaign.name.label']
            )
            ->add(
                'senderEmail',
                TextType::class,
                [
                    'label'    => 'oro.campaign.emailcampaign.sender_email.label',
                    'required' => false
                ]
            )
            ->add(
                'senderName',
                TextType::class,
                [
                    'label'    => 'oro.campaign.emailcampaign.sender_name.label',
                    'required' => false
                ]
            )
            ->add(
                'schedule',
                ChoiceType::class,
                [
                    'choices' => [
                        EmailCampaign::SCHEDULE_MANUAL   => 'oro.campaign.emailcampaign.schedule.manual',
                        EmailCampaign::SCHEDULE_DEFERRED => 'oro.campaign.emailcampaign.schedule.deferred'
                    ],
                    'label'   => 'oro.campaign.emailcampaign.schedule.label',
                ]
            )
            ->add(
                'scheduledFor',
                OroDateTimeType::class,
                [
                    'label'    => 'oro.campaign.emailcampaign.scheduled_for.label',
                    'required' => false,
                ]
            )
            ->add(
                'campaign',
                CampaignSelectType::class,
                ['label' => 'oro.campaign.emailcampaign.campaign.label']
            )
            ->add(
                'marketingList',
                MarketingListSelectType::class,
                ['label' => 'oro.campaign.emailcampaign.marketing_list.label', 'required' => true]
            )
            ->add(
                'description',
                OroResizeableRichTextType::class,
                [
                    'label'    => 'oro.campaign.emailcampaign.description.label',
                    'required' => false,
                ]
            );
        $this->addTransport($builder);
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => 'Oro\Bundle\CampaignBundle\Entity\EmailCampaign',
            ]
        );
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'oro_email_campaign';
    }

    /**
     * @param FormBuilderInterface $builder
     */
    protected function addTransport(FormBuilderInterface $builder)
    {
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) {
                $options = [
                    'label' => 'oro.campaign.emailcampaign.transport.label',
                    'required' => true,
                    'mapped' => false
                ];

                /** @var EmailCampaign $data */
                $data = $event->getData();
                if ($data) {
                    $choices = $this->emailTransportProvider->getVisibleTransportChoices();
                    $currentTransportName = $data->getTransport();
                    if (!array_key_exists($currentTransportName, $choices)) {
                        $currentTransport = $this->emailTransportProvider
                            ->getTransportByName($currentTransportName);
                        $choices[$currentTransport->getName()] = $currentTransport->getLabel();
                        $options['choices'] = $choices;
                    }
                }

                $form = $event->getForm();
                $form->add('transport', EmailTransportSelectType::class, $options);
            }
        );
    }
}
