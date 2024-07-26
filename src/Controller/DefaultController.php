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

namespace Novosga\MonitorBundle\Controller;

use Exception;
use Novosga\Entity\AtendimentoInterface;
use Novosga\Entity\UnidadeInterface;
use Novosga\Entity\UsuarioInterface;
use Novosga\Http\Envelope;
use Novosga\MonitorBundle\Dto\TransferirAtendimentoDto;
use Novosga\MonitorBundle\Form\TransferirType;
use Novosga\MonitorBundle\NovosgaMonitorBundle;
use Novosga\Service\AtendimentoServiceInterface;
use Novosga\Service\FilaServiceInterface;
use Novosga\Service\ServicoServiceInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * DefaultController
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
#[Route("/", name: "novosga_monitor_")]
class DefaultController extends AbstractController
{
    #[Route("/", name: "index", methods: ["GET"])]
    public function index(ServicoServiceInterface $servicoService): Response
    {
        /** @var UsuarioInterface */
        $usuario  = $this->getUser();
        $unidade  = $usuario->getLotacao()->getUnidade();
        $servicos = $servicoService->servicosUnidade($unidade, ['ativo' => true]);

        $transferirForm = $this->createTransferirForm($servicoService);

        return $this->render('@NovosgaMonitor/default/index.html.twig', [
            'usuario'        => $usuario,
            'unidade'        => $unidade,
            'servicos'       => $servicos,
            'transferirForm' => $transferirForm,
            'milis'          => time() * 1000,
        ]);
    }

    #[Route("/ajax_update", name: "ajaxupdate", methods: ["GET"])]
    public function ajaxUpdate(
        Request $request,
        ServicoServiceInterface $servicoService,
        FilaServiceInterface $filaService,
    ): Response {
        $envelope = new Envelope();
        /** @var UsuarioInterface */
        $usuario  = $this->getUser();
        $unidade  = $usuario->getLotacao()->getUnidade();

        $data  = [];
        $param = $request->get('ids', '');
        $ids = array_filter(explode(',', $param), fn ($i) => $i > 0);
        $data[] = [
            'fila' => $filaService->getFilaUnidade($unidade),
        ];

        if (count($ids)) {
            $servicos = $servicoService->servicosUnidade($unidade, ['servico' => $ids]);
            foreach ($servicos as $su) {
                $rs = $filaService->getFilaServico($unidade, $su->getServico());
                $total = count($rs);
                // prevent overhead
                if ($total) {
                    $fila = [];
                    foreach ($rs as $atendimento) {
                        $arr = $atendimento->jsonSerialize();
                        $fila[] = $arr;
                    }
                    $data[] = [
                        'servicoUnidade' => $su,
                        'fila' => $fila,
                    ];
                }
            }
        }

        $envelope->setData($data);

        return $this->json($envelope);
    }

    #[Route("/info_senha/{id}", name: "infosenha", methods: ["GET"])]
    public function infoSenha(
        AtendimentoServiceInterface $atendimentoService,
        TranslatorInterface $translator,
        int $id,
    ): Response {
        $atendimento = $atendimentoService->getById($id);
        if (!$atendimento) {
            throw $this->createNotFoundException();
        }

        $envelope = new Envelope();

        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $this->checkAtendimento($unidade, $atendimento, $translator);

        $data = $atendimento->jsonSerialize();
        $envelope->setData($data);

        return $this->json($envelope);
    }

    /**
     * Busca os atendimentos a partir do número da senha.
     */
    #[Route("/buscar", name: "buscar", methods: ["GET"])]
    public function buscar(Request $request, AtendimentoServiceInterface $atendimentoService): Response
    {
        $envelope = new Envelope();

        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        $numero = $request->get('numero');

        $atendimentos = $atendimentoService->buscaAtendimentos($unidade, $numero);
        $envelope->setData($atendimentos);

        return $this->json($envelope);
    }

    /**
     * Transfere o atendimento para outro serviço e prioridade.
     */
    #[Route("/transferir/{id}", name: "transferir", methods: ["POST"])]
    public function transferir(
        AtendimentoServiceInterface $atendimentoService,
        ServicoServiceInterface $servicoService,
        TranslatorInterface $translator,
        int $id,
        #[MapRequestPayload] TransferirAtendimentoDto $data,
    ): Response {
        $atendimento = $atendimentoService->getById($id);
        if (!$atendimento) {
            throw $this->createNotFoundException();
        }

        $envelope = new Envelope();

        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $this->checkAtendimento($unidade, $atendimento, $translator);

        $transferirForm = $this
            ->createTransferirForm($servicoService)
            ->submit($data->jsonSerialize());

        if (!$transferirForm->isSubmitted() || !$transferirForm->isValid()) {
            throw new Exception($translator->trans('error.invalid_form', [], NovosgaMonitorBundle::getDomain()));
        }

        $servicoUnidade = $transferirForm->get('servico')->getData();
        $novaPrioridade = $transferirForm->get('prioridade')->getData();

        $atendimentoService->transferir(
            $atendimento,
            $usuario,
            $servicoUnidade->getServico(),
            $novaPrioridade,
        );

        return $this->json($envelope);
    }

    /**
     * Reativa o atendimento para o mesmo serviço e mesma prioridade.
     * Só pode reativar atendimentos que foram: Cancelados ou Não Compareceu.
     */
    #[Route("/reativar/{id}", name: "reativar", methods: ["POST"])]
    public function reativar(
        AtendimentoServiceInterface $atendimentoService,
        TranslatorInterface $translator,
        int $id,
    ): Response {
        $atendimento = $atendimentoService->getById($id);
        if (!$atendimento) {
            throw $this->createNotFoundException();
        }

        $envelope = new Envelope();
        /** @var UsuarioInterface */
        $usuario  = $this->getUser();
        $unidade  = $usuario->getLotacao()->getUnidade();
        $statuses = [AtendimentoServiceInterface::SENHA_CANCELADA, AtendimentoServiceInterface::NAO_COMPARECEU];

        if ($atendimento->getUnidade()->getId() !== $unidade->getId()) {
            throw new Exception(
                $translator->trans(
                    'error.reactive.invalid_unity',
                    [],
                    NovosgaMonitorBundle::getDomain(),
                ),
            );
        }

        if (!in_array($atendimento->getStatus(), $statuses)) {
            throw new Exception(
                $translator->trans(
                    'error.reactive.invalid_status',
                    [],
                    NovosgaMonitorBundle::getDomain(),
                ),
            );
        }

        $atendimentoService->reativar($atendimento, $usuario);

        return $this->json($envelope);
    }

    /**
     * Atualiza o status da senha para cancelado.
     */
    #[Route("/cancelar/{id}", name: "_cancelar", methods: ["POST"])]
    public function cancelar(
        AtendimentoServiceInterface $atendimentoService,
        TranslatorInterface $translator,
        int $id,
    ): Response {
        $atendimento = $atendimentoService->getById($id);
        if (!$atendimento) {
            throw $this->createNotFoundException();
        }

        $envelope = new Envelope();

        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $this->checkAtendimento($unidade, $atendimento, $translator);
        $atendimentoService->cancelar($atendimento, $usuario);

        return $this->json($envelope);
    }

    private function createTransferirForm(ServicoServiceInterface $servicoService): FormInterface
    {
        /** @var UsuarioInterface */
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        $servicos = $servicoService->servicosUnidade($unidade, ['ativo' => true]);

        $transferirForm = $this->createForm(TransferirType::class, null, [
            'servicos' => $servicos,
        ]);

        return $transferirForm;
    }

    private function checkAtendimento(
        UnidadeInterface $unidade,
        AtendimentoInterface $atendimento,
        TranslatorInterface $translator,
    ): void {
        if ($atendimento->getUnidade()->getId() != $unidade->getId()) {
            throw new Exception(
                $translator->trans(
                    'error.ticket.invalid_unity',
                    [],
                    NovosgaMonitorBundle::getDomain(),
                ),
            );
        }
    }
}
