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
use Novosga\Http\Envelope;
use Novosga\Entity\Unidade;
use Novosga\Entity\Atendimento;
use Novosga\Service\AtendimentoService;
use Novosga\Service\FilaService;
use Novosga\Service\ServicoService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Novosga\MonitorBundle\Form\TransferirType;

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
    public function indexAction(Request $request)
    {
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        $servicos = $this->servicos($unidade, 'e.status = 1');
        
        $transferirForm = $this->createTransferirForm($request);

        return $this->render('NovosgaMonitorBundle:default:index.html.twig', [
            'unidade' => $unidade,
            'servicos' => $servicos,
            'transferirForm' => $transferirForm->createView(),
            'milis' => time() * 1000
        ]);
    }

    private function servicos(Unidade $unidade, $where = '')
    {
        $em = $this->getDoctrine()->getManager();

        $service = new ServicoService($em);

        return $service->servicosUnidade($unidade, $where);
    }
    
    /**
     * @return TransferirType
     */
    private function createTransferirForm(Request $request)
    {
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        $servicos = $this->servicos($unidade, 'e.status = 1');
        
        $transferirForm = $this->createForm(TransferirType::class, null, [
            'csrf_protection' => false,
            'servicos' => array_map(function ($su) {
                            return $su->getServico();
                        }, $servicos)
        ]);
        
        return $transferirForm;
    }

    /**
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @Route("/ajax_update", name="novosga_monitor_ajaxupdate")
     */
    public function ajaxUpdateAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $envelope = new Envelope();
        $usuario = $this->getUser();
        $unidade = $usuario->getLotacao()->getUnidade();
        $filaService = new FilaService($em);
        
        try {
            if (!$unidade) {
                throw new Exception(_('Nenhuma unidade escolhida'));
            }
            
            $data = [];
            $ids = explode(',', $request->get('ids'));
            
            if (count($ids)) {
                $servicos = $this->servicos($unidade, ' e.servico IN ('.implode(',', $ids).') ');
                
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
        } catch (Exception $e) {
            $envelope->exception($e);
        }

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
        
        try {
            $usuario = $this->getUser();
            $unidade = $usuario->getLotacao()->getUnidade();
            
            $this->checkAtendimento($unidade, $atendimento);
            
            $data = $atendimento->jsonSerialize();
            $envelope->setData($data);
        } catch (Exception $e) {
            $envelope->exception($e);
        }

        return $this->json($envelope);
    }

    /**
     * Busca os atendimentos a partir do número da senha.
     *
     * @param Request $request
     * 
     * @Route("/buscar", name="novosga_monitor_buscar")
     */
    public function buscarAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $envelope = new Envelope();
        
        try {
            $usuario = $this->getUser();
            $unidade = $usuario->getLotacao()->getUnidade();
            
            $numero = $request->get('numero');
            $service = new AtendimentoService($em);
            $atendimentos = $service->buscaAtendimentos($unidade, $numero);
            $envelope->setData($atendimentos);
        } catch (Exception $e) {
            $envelope->exception($e);
        }
        
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
    public function transferirAction(Request $request, Atendimento $atendimento)
    {
        $em = $this->getDoctrine()->getManager();
        $envelope = new Envelope();
        
        try {
            $usuario = $this->getUser();
            $unidade = $usuario->getLotacao()->getUnidade();
            
            $this->checkAtendimento($unidade, $atendimento);
            
            $data = json_decode($request->getContent(), true);
            
            $transferirForm = $this->createTransferirForm($request);
            $transferirForm->submit($data);
            
            if (!$transferirForm->isValid()) {
                throw new Exception(_('Formulário inválido'));
            }

            $service = new AtendimentoService($em);
            $service->transferir(
                    $atendimento, 
                    $unidade, 
                    $transferirForm->get('servico')->getData(), 
                    $transferirForm->get('prioridade')->getData()
            );
        } catch (Exception $e) {
            $envelope->exception($e);
        }

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
        $em = $this->getDoctrine()->getManager();
        $envelope = new Envelope();
        
        try {
            $usuario = $this->getUser();
            $unidade = $usuario->getLotacao()->getUnidade();
            
            $conn = $em->getConnection();
            $status = implode(',', [AtendimentoService::SENHA_CANCELADA, AtendimentoService::NAO_COMPARECEU]);
            // reativa apenas se estiver finalizada (data fim diferente de nulo)
            $stmt = $conn->prepare("
                UPDATE
                    atendimentos
                SET
                    status = :status,
                    dt_fim = NULL
                WHERE
                    id = :id AND
                    unidade_id = :unidade AND
                    status IN ({$status})
            ");
            $stmt->bindValue('id', $atendimento->getId());
            $stmt->bindValue('status', AtendimentoService::SENHA_EMITIDA);
            $stmt->bindValue('unidade', $unidade->getId());
            $stmt->execute() > 0;
        } catch (Exception $e) {
            $envelope->exception($e);
        }

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
    public function cancelarAction(Request $request, Atendimento $atendimento)
    {
        $em = $this->getDoctrine()->getManager();
        $envelope = new Envelope();
        
        try {
            $usuario = $this->getUser();
            $unidade = $usuario->getLotacao()->getUnidade();
            
            $this->checkAtendimento($unidade, $atendimento);
            $service = new AtendimentoService($em);
            $service->cancelar($atendimento, $unidade);
        } catch (Exception $e) {
            $envelope->exception($e);
        }

        return $this->json($envelope);
    }

    private function checkAtendimento(Unidade $unidade, Atendimento $atendimento)
    {
        if ($atendimento->getServicoUnidade()->getUnidade()->getId() != $unidade->getId()) {
            throw new Exception(_('Atendimento inválido'));
        }
    }
}