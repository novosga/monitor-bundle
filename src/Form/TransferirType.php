<?php

declare(strict_types=1);

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\MonitorBundle\Form;

use Novosga\Repository\PrioridadeRepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TransferirType extends AbstractType
{
    public function __construct(
        private readonly PrioridadeRepositoryInterface $prioridadeRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $servicos = $options['servicos'];
        $prioridades = $this
            ->prioridadeRepository
                ->createQueryBuilder('e')
                ->where('e.ativo = TRUE')
                ->orderBy('e.peso', 'ASC')
                ->addOrderBy('e.nome', 'ASC');

        $builder
            ->add('servico', ChoiceType::class, [
                'choices' => $servicos,
                'placeholder' => '',
                'label' => 'transferir.type.servico',
            ])
            ->add('prioridade', ChoiceType::class, [
                'choices' => $prioridades,
                'placeholder' => '',
                'label' => 'transferir.type.prioridade',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setRequired('servicos')
            ->setDefaults([
                'translation_domain' => 'NovosgaMonitorBundle',
            ]);
    }

    public function getBlockPrefix()
    {
        return '';
    }
}
