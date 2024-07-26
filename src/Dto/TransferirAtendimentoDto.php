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

namespace Novosga\MonitorBundle\Dto;

use JsonSerializable;

/**
 * TransferirAtendimentoDto
 *
 * @author Rogerio Lino <rogeriolino@gmail.com>
 */
final readonly class TransferirAtendimentoDto implements JsonSerializable
{
    public function __construct(
        public ?int $servico = null,
        public ?int $prioridade = null,
    ) {
    }

    public function jsonSerialize(): mixed
    {
        return [
            'servico' => $this->servico,
            'prioridade' => $this->prioridade,
        ];
    }
}
