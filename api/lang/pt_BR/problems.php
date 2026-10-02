<?php

declare(strict_types=1);

return [

    /*
    | Titles and details for generic HTTP failures (`type: about:blank`).
    */

    'status' => [
        400 => ['title' => 'Requisição inválida', 'detail' => 'A requisição não pôde ser processada.'],
        401 => ['title' => 'Não autenticado', 'detail' => 'Entre na sua conta para continuar.'],
        403 => ['title' => 'Acesso negado', 'detail' => 'Você não tem permissão para realizar esta ação.'],
        404 => ['title' => 'Não encontrado', 'detail' => 'O que você procura não existe ou foi removido.'],
        405 => ['title' => 'Método não permitido', 'detail' => 'Esta operação não é permitida neste endereço.'],
        419 => ['title' => 'Sessão expirada', 'detail' => 'Sua sessão expirou. Recarregue a página e tente novamente.'],
        429 => ['title' => 'Muitas tentativas', 'detail' => 'Você fez muitas tentativas. Aguarde um pouco e tente novamente.'],
        500 => ['title' => 'Erro interno', 'detail' => 'Ocorreu um erro inesperado. Se continuar, informe o código de rastreio ao suporte.'],
        503 => ['title' => 'Serviço indisponível', 'detail' => 'O serviço está temporariamente indisponível. Tente novamente em instantes.'],
    ],

    /*
    | Application-specific problem types, keyed by their `type` slug.
    */

    'types' => [
        'validation-error' => [
            'title' => 'Dados inválidos',
            'detail' => 'Alguns campos não foram preenchidos corretamente.',
        ],
    ],

];
