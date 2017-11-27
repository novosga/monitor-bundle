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

use Exception;
use Novosga\Entity\Atendimento;
use Novosga\Entity\Unidade;
use Novosga\Http\Envelope;
use Novosga\MonitorBundle\Form\TransferirType;
use Novosga\Service\AtendimentoService;
use Novosga\Service\FilaService;
use Novosga\Service\ServicoService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * DefaultController
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
class DefaultController extends Controller
{

    /**
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/", name="novosga_monitor_index")
     */
    public function indexAction(Request $request, ServicoService $servicoService)
    {
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        $servicos = $servicoService->servicosUnidade($unidade, ['ativo' => true]);
        
        $transferirForm = $this->createTransferirForm($request, $servicoService);

        return $this->render('@NovosgaMonitor/default/index.html.twig', [
            'unidade' => $unidade,
            'servicos' => $servicos,
            'transferirForm' => $transferirForm->createView(),
            'milis' => time() * 1000
        ]);
    }

    /**
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/ajax_update", name="novosga_monitor_ajaxupdate")
     */
    public function ajaxUpdateAction(
        Request $request,
        ServicoService $servicoService,
        FilaService $filaService
    ) {
        $envelope = new Envelope();
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        
        if (!$unidade) {
            throw new Exception(_('Nenhuma unidade escolhida'));
        }

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
     * @Route("/info_senha/{id}", name="novosga_monitor_infosenha")
     */
    public function infoSenhaAction(Request $request, Atendimento $atendimento)
    {
        $envelope = new Envelope();
        
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $this->checkAtendimento($unidade, $atendimento);

        $data = $atendimento->jsonSerialize();
        $envelope->setData($data);

        return $this->json($envelope);
    }

    /**
     * Busca os atendimentos a partir do número da senha.
     *
     * @param Request $request
     *
     * @Route("/buscar", name="novosga_monitor_buscar")
     */
    public function buscarAction(Request $request, AtendimentoService $atendimentoService)
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
     * @Route("/transferir/{id}", name="novosga_monitor_transferir")
     * @Method("POST")
     */
    public function transferirAction(
        Request $request,
        AtendimentoService $atendimentoService,
        ServicoService $servicoService,
        Atendimento $atendimento
    ) {
        $envelope = new Envelope();
        
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $this->checkAtendimento($unidade, $atendimento);

        $data = json_decode($request->getContent(), true);

        $transferirForm = $this->createTransferirForm($request, $servicoService);
        $transferirForm->submit($data);

        if (!$transferirForm->isValid()) {
            throw new Exception(_('Formulário inválido'));
        }

        $atendimentoService->transferir(
            $atendimento,
            $unidade,
            $transferirForm->get('servico')->getData(),
            $transferirForm->get('prioridade')->getData()
        );

        return $this->json($envelope);
    }

    /**
     * Reativa o atendimento para o mesmo serviço e mesma prioridade.
     * Só pode reativar atendimentos que foram: Cancelados ou Não Compareceu.
     *
     * @param Request $request
     *
     * @Route("/reativar/{id}", name="novosga_monitor_reativar")
     * @Method("POST")
     */
    public function reativarAction(Request $request, Atendimento $atendimento)
    {
        $em       = $this->getDoctrine()->getManager();
        $envelope = new Envelope();
        $usuario  = $this->getUser();
        $unidade  = $usuario->getLotacao()->getUnidade();
        $statuses = [AtendimentoService::SENHA_CANCELADA, AtendimentoService::NAO_COMPARECEU];

        if ($atendimento->getUnidade()->getId() !== $unidade->getId()) {
            throw new \Exception(_('Tentando reavitvar um atendimento de outra unidade.'));
        }

        if (!in_array($atendimento->getStatus(), $statuses)) {
            throw new \Exception(_('Só é possível reativar um atendimento cancelado ou que não compareceu.'));
        }
        
        $atendimento->setStatus(AtendimentoService::SENHA_EMITIDA);
        $atendimento->setDataFim(null);
        
        $em->merge($atendimento);
        $em->flush();
        
        return $this->json($envelope);
    }

    /**
     * Atualiza o status da senha para cancelado.
     *
     * @param Request $request
     *
     * @Route("/cancelar/{id}", name="novosga_monitor_cancelar")
     * @Method("POST")
     */
    public function cancelarAction(
        Request $request,
        AtendimentoService $atendimentoService,
        Atendimento $atendimento
    ) {
        $envelope = new Envelope();
        
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();

        $this->checkAtendimento($unidade, $atendimento);
        $atendimentoService->cancelar($atendimento, $unidade);

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
            'servicos' => array_map(function ($su) {
                            return $su->getServico();
            }, $servicos)
        ]);
        
        return $transferirForm;
    }

    private function checkAtendimento(Unidade $unidade, Atendimento $atendimento)
    {
        if ($atendimento->getUnidade()->getId() != $unidade->getId()) {
            throw new Exception(_('Atendimento inválido'));
        }
    }
}
