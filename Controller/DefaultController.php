<?php

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\MonitorBundle\Controller;

use App\Service\SecurityService;
use Exception;
use Novosga\Entity\Atendimento;
use Novosga\Entity\Unidade;
use Novosga\Http\Envelope;
use Novosga\MonitorBundle\Form\TransferirType;
use Novosga\Service\AtendimentoService;
use Novosga\Service\FilaService;
use Novosga\Service\ServicoService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * DefaultController
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class DefaultController extends AbstractController
{
    const DOMAIN = 'NovosgaMonitorBundle';
    
    /**
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/", name="novosga_monitor_index", methods={"GET"})
     */
    public function index(Request $request, ServicoService $servicoService, SecurityService $securityService)
    {
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        $servicos = $servicoService->servicosUnidade($unidade, ['ativo' => true]);
        
        $transferirForm = $this->createTransferirForm($request, $servicoService);

        return $this->render('@NovosgaMonitor/default/index.html.twig', [
            'usuario'        => $usuario,
            'unidade'        => $unidade,
            'servicos'       => $servicos,
            'transferirForm' => $transferirForm->createView(),
            'milis'          => time() * 1000,
            'wsSecret'       => $securityService->getWebsocketSecret(),
        ]);
    }

    /**
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/ajax_update", name="novosga_monitor_ajaxupdate", methods={"GET"})
     */
    public function ajaxUpdate(
        Request $request,
        ServicoService $servicoService,
        FilaService $filaService
    ) {
        $envelope = new Envelope();
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        
        $data  = [];
        $param = $request->get('ids');
        $ids   = explode(',', $param ?: '0');
        
        if (count($ids)) {
            $servicos = $servicoService->servicosUnidade($unidade, ['servico' => $ids]);

            if ($servicos) {
                foreach ($servicos as $su) {
                    $rs = $filaService->filaServico($unidade, $su->getServico());
                    $total = count($rs);
                    // prevent overhead
                    if ($total) {
                        $fila = [];
                        foreach ($rs as $atendimento) {
                            $arr = $atendimento->jsonSerialize(true);
                            $fila[] = $arr;
                        }
                        $data[] = [
                            'servicoUnidade' => $su,
                            'fila' => $fila,
                        ];
                    }
                }
            }
        }
        
        $envelope->setData($data);

        return $this->json($envelope);
    }

    /**
     *
     * @param Request $request
     * @return Response
     *
     * @Route("/info_senha/{id}", name="novosga_monitor_infosenha", methods={"GET"})
     */
    public function infoSenha(Request $request, Atendimento $atendimento, TranslatorInterface $translator)
    {
        $envelope = new Envelope();
        
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $this->checkAtendimento($unidade, $atendimento, $translator);

        $data = $atendimento->jsonSerialize();
        $envelope->setData($data);

        return $this->json($envelope);
    }

    /**
     * Busca os atendimentos a partir do número da senha.
     *
     * @param Request $request
     *
     * @Route("/buscar", name="novosga_monitor_buscar", methods={"GET"})
     */
    public function buscar(Request $request, AtendimentoService $atendimentoService)
    {
        $envelope = new Envelope();
        
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        $numero = $request->get('numero');

        $atendimentos = $atendimentoService->buscaAtendimentos($unidade, $numero);
        $envelope->setData($atendimentos);
        
        return $this->json($envelope);
    }

    /**
     * Transfere o atendimento para outro serviço e prioridade.
     *
     * @param Request $request
     *
     * @Route("/transferir/{id}", name="novosga_monitor_transferir", methods={"POST"})
     */
    public function transferir(
        Request $request,
        AtendimentoService $atendimentoService,
        ServicoService $servicoService,
        Atendimento $atendimento,
        TranslatorInterface $translator
    ) {
        $envelope = new Envelope();
        
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $this->checkAtendimento($unidade, $atendimento, $translator);

        $data = json_decode($request->getContent(), true);

        $transferirForm = $this->createTransferirForm($request, $servicoService);
        $transferirForm->submit($data);

        if (!$transferirForm->isValid()) {
            throw new Exception($translator->trans('error.invalid_form', [], self::DOMAIN));
        }
        
        $servicoUnidade = $transferirForm->get('servico')->getData();
        $prioridade     = $transferirForm->get('prioridade')->getData();

        $atendimentoService->transferir(
            $atendimento,
            $unidade,
            $servicoUnidade->getServico(),
            $prioridade
        );

        return $this->json($envelope);
    }

    /**
     * Reativa o atendimento para o mesmo serviço e mesma prioridade.
     * Só pode reativar atendimentos que foram: Cancelados ou Não Compareceu.
     *
     * @param Request $request
     *
     * @Route("/reativar/{id}", name="novosga_monitor_reativar", methods={"POST"})
     */
    public function reativar(
        Request $request,
        Atendimento $atendimento,
        AtendimentoService $atendimentoService,
        TranslatorInterface $translator
    ) {
        $envelope = new Envelope();
        $usuario  = $this->getUser();
        $unidade  = $usuario->getLotacao()->getUnidade();
        $statuses = [AtendimentoService::SENHA_CANCELADA, AtendimentoService::NAO_COMPARECEU];

        if ($atendimento->getUnidade()->getId() !== $unidade->getId()) {
            throw new Exception($translator->trans('error.reactive.invalid_unity', [], self::DOMAIN));
        }

        if (!in_array($atendimento->getStatus(), $statuses)) {
            throw new Exception($translator->trans('error.reactive.invalid_status', [], self::DOMAIN));
        }
        
        $atendimentoService->reativar($atendimento, $unidade);
        
        return $this->json($envelope);
    }

    /**
     * Atualiza o status da senha para cancelado.
     *
     * @param Request $request
     *
     * @Route("/cancelar/{id}", name="novosga_monitor_cancelar", methods={"POST"})
     */
    public function cancelar(
        Request $request,
        AtendimentoService $atendimentoService,
        Atendimento $atendimento,
        TranslatorInterface $translator
    ) {
        $envelope = new Envelope();
        
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $this->checkAtendimento($unidade, $atendimento, $translator);
        $atendimentoService->cancelar($atendimento);

        return $this->json($envelope);
    }

    /**
     * @return TransferirType
     */
    private function createTransferirForm(Request $request, ServicoService $servicoService)
    {
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        $servicos = $servicoService->servicosUnidade($unidade, ['ativo' => true]);
        
        $transferirForm = $this->createForm(TransferirType::class, null, [
            'csrf_protection' => false,
            'servicos'        => $servicos,
        ]);
        
        return $transferirForm;
    }

    private function checkAtendimento(Unidade $unidade, Atendimento $atendimento, TranslatorInterface $translator)
    {
        if ($atendimento->getUnidade()->getId() != $unidade->getId()) {
            throw new Exception($translator->trans('error.ticket.invalid_unity', [], self::DOMAIN));
        }
    }
}
