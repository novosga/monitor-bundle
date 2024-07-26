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

use Novosga\Entity\PrioridadeInterface;
use Novosga\Entity\ServicoUnidadeInterface;
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
            ->addOrderBy('e.nome', 'ASC')
            ->getQuery()
            ->getResult();

        $builder
            ->add('servico', ChoiceType::class, [
                'choices' => $servicos,
                'choice_label' => function (?ServicoUnidadeInterface $su) {
                    return sprintf('%s - %s', $su?->getSigla(), $su?->getServico()->getNome());
                },
                'placeholder' => '',
                'label' => 'transferir.type.servico',
            ])
            ->add('prioridade', ChoiceType::class, [
                'choices' => $prioridades,
                'choice_label' => fn (?PrioridadeInterface $prioridade) => $prioridade?->getNome(),
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
                'csrf_protection' => false,
            ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }
}
