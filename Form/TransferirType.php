<?php

namespace Novosga\MonitorBundle\Form;

use Doctrine\ORM\EntityRepository;
use Novosga\Entity\Prioridade;
use Novosga\Entity\Servico;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class TransferirType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $servicos = $options['servicos'];
        
        $builder
            ->add('servico', EntityType::class, [
                'class' => Servico::class,
                'choices' => $servicos,
                'placeholder' => ''
            ])
            ->add('prioridade', EntityType::class, [
                'class' => Prioridade::class,
                'query_builder' => function (EntityRepository $repo) {
                        return $repo->createQueryBuilder('e')
                                ->where('e.status = 1')
                                ->orderBy('e.peso', 'ASC')
                                ->addOrderBy('e.nome', 'ASC');
                },
                'placeholder' => ''
            ])
        ;
    }
    
    public function configureOptions(\Symfony\Component\OptionsResolver\OptionsResolver $resolver)
    {
        $resolver->setRequired('servicos');
    }

    
    public function getBlockPrefix()
    {
        return null;
    }

}
